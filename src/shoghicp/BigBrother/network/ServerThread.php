<?php
/**
 *  ______  __         ______               __    __
 * |   __ \|__|.-----.|   __ \.----..-----.|  |_ |  |--..-----..----.
 * |   __ <|  ||  _  ||   __ <|   _||  _  ||   _||     ||  -__||   _|
 * |______/|__||___  ||______/|__|  |_____||____||__|__||_____||__|
 *             |_____|
 *
 * BigBrother plugin for PocketMine-MP
 * Copyright (C) 2014-2015 shoghicp <https://github.com/shoghicp/BigBrother>
 * Copyright (C) 2016- BigBrotherTeam
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @author BigBrotherTeam
 * @link   https://github.com/BigBrotherTeam/BigBrother
 *
 */

declare(strict_types=1);

namespace shoghicp\BigBrother\network;

use ClassLoader;
use Exception;
use ReflectionClass;
use Thread;
use Threaded;
use ThreadedLogger;
use function array_reverse;
use function class_exists;
use function fwrite;
use function interface_exists;
use function register_shutdown_function;
use function serialize;
use function socket_last_error;
use function socket_strerror;
use function stream_set_blocking;
use function stream_socket_pair;
use function strtoupper;
use function substr;
use function unserialize;

class ServerThread extends Thread{
	protected string $data;

	public array $loadPaths;

	protected bool $shutdown;

	protected Threaded $externalQueue;
	protected Threaded $internalQueue;

	/** @var resource */
	protected $externalSocket;
	/** @var resource */
	protected $internalSocket;

	public function __construct(protected ThreadedLogger $logger, protected ClassLoader $loader, protected int $port, protected string $interface = "0.0.0.0", string $motd = "Minecraft: PE server", string $icon = null, bool $autoStart = true){
		if($port < 1 || $port > 65536){
			throw new Exception("Invalid port range");
		}

		$this->data = serialize([
			"motd" => $motd,
			"icon" => $icon
		]);

		$loadPaths = [];
		$this->addDependency($loadPaths, new ReflectionClass($logger));
		$this->addDependency($loadPaths, new ReflectionClass($loader));
		$this->loadPaths = array_reverse($loadPaths);
		$this->shutdown = false;

		$this->externalQueue = new Threaded;
		$this->internalQueue = new Threaded;

		if(($sockets = stream_socket_pair((strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? STREAM_PF_INET : STREAM_PF_UNIX), STREAM_SOCK_STREAM, STREAM_IPPROTO_IP)) === false){
			throw new Exception("Could not create IPC streams. Reason: " . socket_strerror(socket_last_error()));
		}

		$this->internalSocket = $sockets[0];
		stream_set_blocking($this->internalSocket, false);
		$this->externalSocket = $sockets[1];
		stream_set_blocking($this->externalSocket, false);

		if($autoStart){
			$this->start();
		}
	}

	/**
	 * @param array            &$loadPaths
	 *
	 * @phpstan-param array     $loadPaths
	 *
	 * @param ReflectionClass   $dep
	 */
	protected function addDependency(array &$loadPaths, ReflectionClass $dep){
		if($dep->getFileName() !== false){
			$loadPaths[$dep->getName()] = $dep->getFileName();
		}

		if($dep->getParentClass() instanceof ReflectionClass){
			$this->addDependency($loadPaths, $dep->getParentClass());
		}

		foreach($dep->getInterfaces() as $interface){
			$this->addDependency($loadPaths, $interface);
		}
	}

	public function isShutdown() : bool{
		return $this->shutdown;
	}

	public function shutdown(){
		$this->shutdown = true;
	}

	public function getPort() : int{
		return $this->port;
	}

	public function getInterface() : string{
		return $this->interface;
	}

	public function getLogger() : ThreadedLogger{
		return $this->logger;
	}

	public function getExternalQueue() : Threaded{
		return $this->externalQueue;
	}

	public function getInternalQueue() : Threaded{
		return $this->internalQueue;
	}

	public function getInternalSocket(){
		return $this->internalSocket;
	}

	public function pushMainToThreadPacket(string $str) : void{
		$this->internalQueue[] = $str;
		@fwrite($this->externalSocket, "\xff", 1); //Notify
	}

	public function readMainToThreadPacket() : ?string{
		return $this->internalQueue->shift();
	}

	public function pushThreadToMainPacket(string $str) : void{
		$this->externalQueue[] = $str;
	}

	public function readThreadToMainPacket() : ?string{
		return $this->externalQueue->shift();
	}

	public function shutdownHandler() : void{
		if($this->shutdown !== true){
			$this->getLogger()->emergency("[ServerThread #" . Thread::getCurrentThreadId() . "] ServerThread crashed!");
		}
	}

	public function run() : void{
		//Load removed dependencies, can't use require_once()
		foreach($this->loadPaths as $name => $path){
			if(!class_exists($name, false) && !interface_exists($name, false)){
				require $path;
			}
		}
		$this->loader->register();

		register_shutdown_function([$this, "shutdownHandler"]);

		$data = unserialize($this->data, ["allowed_classes" => false]);
		new ServerManager($this, $this->port, $this->interface, $data["motd"], $data["icon"]);
	}

	public function setGarbage(){
	}
}

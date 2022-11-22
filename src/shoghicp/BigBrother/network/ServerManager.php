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

use Logger;
use shoghicp\BigBrother\utils\Binary;
use function base64_encode;
use function chr;
use function fclose;
use function file_get_contents;
use function fread;
use function is_string;
use function json_decode;
use function ord;
use function stream_select;
use function stream_socket_accept;
use function stream_socket_server;
use function stream_socket_shutdown;
use function strlen;
use function substr;
use function usleep;

class ServerManager{

	const VERSION = "1.16.4";
	const PROTOCOL = 754;

	/*
	 * Internal Packet:
	 * int32 (length without this field)
	 * byte (packet ID)
	 * payload
	 */

	/*
	 * SEND_PACKET payload:
	 * int32 (session identifier)
	 * packet (binary payload)
	 */
	const PACKET_SEND_PACKET = 0x01;

	/*
	 * OPEN_SESSION payload:
	 * int32 (session identifier)
	 * byte (address length)
	 * byte[] (address)
	 * short (port)
	 */
	const PACKET_OPEN_SESSION = 0x02;

	/*
	 * CLOSE_SESSION payload:
	 * int32 (session identifier)
	 */
	const PACKET_CLOSE_SESSION = 0x03;

	/*
	 * ENABLE_ENCRYPTION payload:
	 * int32 (session identifier)
	 * byte[] (secret)
	 */
	const PACKET_ENABLE_ENCRYPTION = 0x04;

	/*
	 * ENABLE_ENCRYPTION payload:
	 * int32 (session identifier)
	 * int (threshold)
	 */
	const PACKET_SET_COMPRESSION = 0x05;

	const PACKET_SET_OPTION = 0x06;

	/*
	 * no payload
	 */
	const PACKET_SHUTDOWN = 0xfe;

	/*
	 * no payload
	 */
	const PACKET_EMERGENCY_SHUTDOWN = 0xff;

	/** @var resource */
	protected $fp;
	/** @var resource */
	protected $socket;
	protected int $identifier = 0;
	/** @var resource[] */
	protected array $sockets = [];
	/** @var Session[] */
	protected array $sessions = [];
	protected Logger $logger;
	protected bool $shutdown = false;

	/** @var string[] */
	public array $sample = [];
	public ?string $favicon;
	public array $serverData = [
		"MaxPlayers" => 20,
		"OnlinePlayers" => 0,
	];

	public function __construct(protected ServerThread $thread, int $port, string $interface, public string $description = "", string $favicon = null){
		$this->favicon = $favicon === null || ($image = file_get_contents($favicon)) == "" ? null : "data:image/png;base64," . base64_encode($image);

		$this->logger = $this->thread->getLogger();
		$this->fp = $this->thread->getInternalSocket();

		if($interface === ""){
			$interface = "0.0.0.0";
		}

		$this->socket = stream_socket_server("tcp://$interface:$port", $errno, $errstr, STREAM_SERVER_LISTEN | STREAM_SERVER_BIND);
		if(!$this->socket){
			$this->logger->critical("[BigBrother] **** FAILED TO BIND TO " . $interface . ":" . $port . "!");
			$this->logger->critical("[BigBrother] Perhaps a server is already running on that port?");
			exit(1);
		}

		$this->sockets[-1] = $this->socket;
		$this->sockets[0] = $this->fp;

		$this->process();
	}

	/**
	 * @return array
	 */
	public function getServerData() : array{
		return $this->serverData;
	}

	public function shutdown() : void{
		$this->thread->shutdown();
		usleep(50000); //Sleep for 1 tick
	}

	/**
	 * @return bool false if there is no packet to process else true
	 */
	protected function processPacket() : bool{
		@fread($this->fp, 1);
		if(is_string($packet = $this->thread->readMainToThreadPacket())){
			$pid = ord($packet[0]);
			$buffer = substr($packet, 1);

			switch($pid){
				case self::PACKET_SEND_PACKET:
					$id = Binary::readInt(substr($buffer, 0, 4));
					$data = substr($buffer, 4);

					if(!isset($this->sessions[$id])){
						$this->closeSession($id);
						return true;
					}
					$this->sessions[$id]->writeRaw($data);
					break;
				case self::PACKET_ENABLE_ENCRYPTION:
					$id = Binary::readInt(substr($buffer, 0, 4));
					$secret = substr($buffer, 4);

					if(!isset($this->sessions[$id])){
						$this->closeSession($id);
						return true;
					}
					$this->sessions[$id]->enableEncryption($secret);
					break;
				case self::PACKET_SET_COMPRESSION:
					$id = Binary::readInt(substr($buffer, 0, 4));
					$threshold = Binary::readInt(substr($buffer, 4, 4));

					if(!isset($this->sessions[$id])){
						$this->closeSession($id);
						return true;
					}
					$this->sessions[$id]->setCompression($threshold);
					break;
				case self::PACKET_SET_OPTION:
					$offset = 1;
					$len = ord($packet[$offset++]);
					$name = substr($packet, $offset, $len);
					$offset += $len;
					$value = substr($packet, $offset);
					$this->serverData = match($name){
						"name" => json_decode($value, true),
					};
					break;
				case self::PACKET_CLOSE_SESSION:
					$id = Binary::readInt(substr($buffer, 0, 4));
					if(isset($this->sessions[$id])){
						$this->close($this->sessions[$id]);
					}else{
						$this->closeSession($id);
					}
					break;
				case self::PACKET_SHUTDOWN:
					foreach($this->sessions as $session){
						$session->close();
					}

					$this->shutdown();
					stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
					$this->shutdown = true;
					break;
				case self::PACKET_EMERGENCY_SHUTDOWN:
					$this->shutdown = true;
					break;
			}

			return true;
		}

		return false;
	}

	/**
	 * @param int    $id
	 * @param string $buffer
	 */
	public function sendPacket(int $id, string $buffer) : void{
		$this->thread->pushThreadToMainPacket(chr(self::PACKET_SEND_PACKET) . Binary::writeInt($id) . $buffer);
	}

	/**
	 * @param Session $session
	 */
	public function openSession(Session $session) : void{
		$data = chr(self::PACKET_OPEN_SESSION) . Binary::writeInt($session->getID()) . chr(strlen($session->getAddress())) . $session->getAddress() . Binary::writeShort($session->getPort());
		$this->thread->pushThreadToMainPacket($data);
	}

	/**
	 * @param int $id
	 */
	protected function closeSession(int $id) : void{
		$this->thread->pushThreadToMainPacket(chr(self::PACKET_CLOSE_SESSION) . Binary::writeInt($id));
	}

	private function process() : void{
		while($this->shutdown !== true){
			$sockets = $this->sockets;
			$write = null;
			$except = null;
			if(@stream_select($sockets, $write, $except, null) > 0){
				if(isset($sockets[-1])){
					unset($sockets[-1]);
					if($connection = stream_socket_accept($this->socket, 0)){
						$this->identifier++;
						$this->sockets[$this->identifier] = $connection;
						$this->sessions[$this->identifier] = new Session($this, $this->identifier, $connection);
					}
				}elseif(isset($sockets[0])){
					if($sockets[0] !== $this->fp){
						$this->findSocket($sockets[0]);
					}else{
						while($this->processPacket()){
						}
					}
					unset($sockets[0]);
				}

				foreach($sockets as $identifier => $socket){
					if(isset($this->sessions[$identifier]) && $this->sockets[$identifier] === $socket){
						$this->sessions[$identifier]->process();
					}else{
						$this->findSocket($socket);
					}
				}
			}
		}
	}

	/**
	 * @param resource $s
	 */
	protected function findSocket($s) : void{
		foreach($this->sockets as $identifier => $socket){
			if($identifier > 0 && $socket === $s){
				$this->sessions[$identifier]->process();
				break;
			}
		}
	}

	/**
	 * @param Session $session
	 */
	public function close(Session $session) : void{
		$identifier = $session->getID();
		fclose($this->sockets[$identifier]);
		unset($this->sockets[$identifier]);
		unset($this->sessions[$identifier]);
		$this->closeSession($identifier);
	}
}

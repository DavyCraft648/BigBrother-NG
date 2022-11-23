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

use pocketmine\network\mcpe\compression\ZlibCompressor;
use pocketmine\network\mcpe\PacketSender;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\StandardPacketBroadcaster;
use pocketmine\network\NetworkInterface;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\MainLogger;
use shoghicp\BigBrother\BigBrother;
use shoghicp\BigBrother\network\protocol\Login\ServerboundKeyPacket;
use shoghicp\BigBrother\network\protocol\Login\ServerboundHelloPacket;
use shoghicp\BigBrother\network\protocol\Play\Client\ServerboundSeenAdvancementsPacket;
use shoghicp\BigBrother\network\protocol\Play\Client\ServerboundSwingPacket;
use shoghicp\BigBrother\network\protocol\Play\Client\ServerboundChatPacket;
use shoghicp\BigBrother\network\protocol\Play\Client\ServerboundContainerClickPacket;
use shoghicp\BigBrother\network\protocol\Play\Client\ServerboundClientInformationPacket;
use shoghicp\BigBrother\network\protocol\Play\Client\ServerboundClientCommandPacket;
use shoghicp\BigBrother\network\protocol\Play\Client\ServerboundContainerClosePacket;
use shoghicp\BigBrother\network\protocol\Play\Client\ServerboundPlaceRecipePacket;
use shoghicp\BigBrother\network\protocol\Play\Client\ServerboundSetCreativeModeSlotPacket;
use shoghicp\BigBrother\network\protocol\Play\Client\ServerboundPlayerCommandPacket;
use shoghicp\BigBrother\network\protocol\Play\Client\ServerboundSetCarriedItemPacket;
use shoghicp\BigBrother\network\protocol\Play\Client\ServerboundInteractPacket;
use shoghicp\BigBrother\network\protocol\Play\Client\ServerboundKeepAlivePacket;
use shoghicp\BigBrother\network\protocol\Play\Client\ServerboundPlayerAbilitiesPacket;
use shoghicp\BigBrother\network\protocol\Play\Client\ServerboundUseItemOnPacket;
use shoghicp\BigBrother\network\protocol\Play\Client\ServerboundPlayerActionPacket;
use shoghicp\BigBrother\network\protocol\Play\Client\ServerboundMovePlayerStatusOnlyPacket;
use shoghicp\BigBrother\network\protocol\Play\Client\ServerboundMovePlayerPosRotPacket;
use shoghicp\BigBrother\network\protocol\Play\Client\ServerboundMovePlayerPosPacket;
use shoghicp\BigBrother\network\protocol\Play\Client\ServerboundMovePlayerRotPacket;
use shoghicp\BigBrother\network\protocol\Play\Client\ServerboundCustomPayloadPacket;
use shoghicp\BigBrother\network\protocol\Play\Client\ServerboundCommandSuggestionPacket;
use shoghicp\BigBrother\network\protocol\Play\Client\ServerboundAcceptTeleportationPacket;
use shoghicp\BigBrother\network\protocol\Play\Client\ServerboundSignUpdatePacket;
use shoghicp\BigBrother\network\protocol\Play\Client\ServerboundUseItemPacket;
use shoghicp\BigBrother\network\protocol\Play\Client\WindowConfirmationPacket;
use shoghicp\BigBrother\utils\Binary;
use function bin2hex;
use function chr;
use function is_array;
use function is_string;
use function json_encode;
use function ord;
use function strlen;
use function substr;

class ProtocolInterface implements NetworkInterface{
	protected ServerThread $thread;

	/**
	 * @var \SplObjectStorage<int>
	 * @phpstan-var \SplObjectStorage<int>|\SplObjectStorage
	 */
	protected \SplObjectStorage $sessions;

	/** @var DesktopNetworkSession[] */
	protected array $sessionsPlayers = [];

	public function __construct(protected BigBrother $plugin, protected Server $server, protected Translator $translator, private int $threshold){
		$this->thread = new ServerThread($server->getLogger(), $server->getLoader(), $plugin->getPort(), $plugin->getIp(), $plugin->getMotd(), $plugin->getDataFolder() . "server-icon.png", false);
		$this->sessions = new \SplObjectStorage();
	}

	public function start() : void{
		$this->thread->start();
	}

	public function shutdown() : void{
		$this->thread->pushMainToThreadPacket(chr(ServerManager::PACKET_SHUTDOWN));
		$this->thread->join();
	}

	public function setName(string $name) : void{
		$info = $this->plugin->getServer()->getQueryInformation();
		$value = [
			"MaxPlayers" => $info->getMaxPlayerCount(),
			"OnlinePlayers" => $info->getPlayerCount(),
		];
		$buffer = chr(ServerManager::PACKET_SET_OPTION) . chr(strlen("name")) . "name" . json_encode($value);
		$this->thread->pushMainToThreadPacket($buffer);
	}

	public function closeSession(int $identifier) : void{
		if(isset($this->sessionsPlayers[$identifier])){
			$session = $this->sessionsPlayers[$identifier];
			unset($this->sessionsPlayers[$identifier]);
			$session->onClientDisconnect("Connection closed");
		}
	}

	public function close(Player $player, string $reason = "unknown reason") : void{
		if(isset($this->sessions[$player])){
			/** @var int $identifier */
			$identifier = $this->sessions[$player];
			$this->sessions->detach($player);
			$this->thread->pushMainToThreadPacket(chr(ServerManager::PACKET_CLOSE_SESSION) . Binary::writeInt($identifier));
		}
	}

	protected function sendPacket(int $target, Packet $packet) : void{
		if($packet->pid() !== OutboundPacket::KEEP_ALIVE_PACKET && $packet->pid() !== OutboundPacket::PLAYER_POSITION_PACKET){
			try{
				echo "[Send][Interface] 0x" . bin2hex(chr($packet->pid())) . ": " . strlen($packet->write()) . "\n";
				echo (new \ReflectionClass($packet))->getName() . "\n";
			}catch(\ReflectionException){
			}
		}

		try{
			$data = chr(ServerManager::PACKET_SEND_PACKET) . Binary::writeInt($target) . $packet->write();
			$this->thread->pushMainToThreadPacket($data);
		}catch(\Throwable){
		}
	}

	public function setCompression(DesktopNetworkSession $player) : void{
		if(isset($this->sessions[$player])){
			/** @var int $target */
			$target = $this->sessions[$player];
			$data = chr(ServerManager::PACKET_SET_COMPRESSION) . Binary::writeInt($target) . Binary::writeInt($this->threshold);
			$this->thread->pushMainToThreadPacket($data);
		}
	}

	public function enableEncryption(DesktopNetworkSession $player, string $secret) : void{
		if(isset($this->sessions[$player])){
			/** @var int $target */
			$target = $this->sessions[$player];
			$data = chr(ServerManager::PACKET_ENABLE_ENCRYPTION) . Binary::writeInt($target) . $secret;
			$this->thread->pushMainToThreadPacket($data);
		}
	}

	public function putRawPacket(DesktopNetworkSession $player, Packet $packet) : void{
		if(isset($this->sessions[$player])){
			/** @var int $target */
			$target = $this->sessions[$player];
			$this->sendPacket($target, $packet);
		}
	}

	public function putPacket(DesktopNetworkSession $player, DataPacket $packet, bool $needACK = false, bool $immediate = true) : void{
		$packets = $this->translator->serverToInterface($player, $packet);
		if($packets !== null && $this->sessions->contains($player)){
			/** @var int $target */
			$target = $this->sessions[$player];
			if(is_array($packets)){
				foreach($packets as $packet){
					$this->sendPacket($target, $packet);
				}
			}else{
				$this->sendPacket($target, $packets);
			}
		}
	}

	protected function receivePacket(DesktopNetworkSession $session, Packet $packet) : void{
		$packets = $this->translator->interfaceToServer($session, $packet);
		if($packets !== null){
			if(is_array($packets)){
				foreach($packets as $packet){
					$session->handleDataPacketNoDecode($packet);
				}
			}else{
				$session->handleDataPacketNoDecode($packets);
			}
		}
	}

	protected function handlePacket(DesktopNetworkSession $session, string $payload) : void{
		$pid = ord($payload[0]);
		if($pid !== InboundPacket::KEEP_ALIVE_PACKET && $pid !== InboundPacket::ACCEPT_TELEPORTATION_PACKET && $pid !== InboundPacket::MOVE_PLAYER_POSITION_AND_ROTATION_PACKET){
			echo "[Receive][Interface] 0x" . bin2hex(chr(ord($payload[0]))) . "\n";
		}

		$offset = 1;

		$status = $session->status;

		if($status === 1){
			switch($pid){
				case InboundPacket::ACCEPT_TELEPORTATION_PACKET:
					$pk = new ServerboundAcceptTeleportationPacket();
					break;
				case InboundPacket::CHAT_PACKET:
					$pk = new ServerboundChatPacket();
					break;
				case InboundPacket::CLIENT_COMMAND_PACKET:
					$pk = new ServerboundClientCommandPacket();
					break;
				case InboundPacket::CLIENT_INFORMATION_PACKET:
					$pk = new ServerboundClientInformationPacket();
					break;
				case InboundPacket::COMMAND_SUGGESTION_PACKET:
					$pk = new ServerboundCommandSuggestionPacket();
					break;
				case InboundPacket::WINDOW_CONFIRMATION_PACKET:
					$pk = new WindowConfirmationPacket();
					break;
				case InboundPacket::CONTAINER_CLICK_PACKET:
					$pk = new ServerboundContainerClickPacket();
					break;
				case InboundPacket::CONTAINER_CLOSE_PACKET:
					$pk = new ServerboundContainerClosePacket();
					break;
				case InboundPacket::CUSTOM_PAYLOAD_PACKET:
					$pk = new ServerboundCustomPayloadPacket();
					break;
				case InboundPacket::INTERACT_PACKET:
					$pk = new ServerboundInteractPacket();
					break;
				case InboundPacket::KEEP_ALIVE_PACKET:
					$pk = new ServerboundKeepAlivePacket();
					break;
				case InboundPacket::MOVE_PLAYER_POSITION_PACKET:
					$pk = new ServerboundMovePlayerPosPacket();
					break;
				case InboundPacket::MOVE_PLAYER_POSITION_AND_ROTATION_PACKET:
					$pk = new ServerboundMovePlayerPosRotPacket();
					break;
				case InboundPacket::MOVE_PLAYER_ROTATION_PACKET:
					$pk = new ServerboundMovePlayerRotPacket();
					break;
				case InboundPacket::MOVE_PLAYER_STATUS_ONLY_PACKET:
					$pk = new ServerboundMovePlayerStatusOnlyPacket();
					break;
				case InboundPacket::PLACE_RECIPE_PACKET:
					$pk = new ServerboundPlaceRecipePacket();
					break;
				case InboundPacket::PLAYER_ABILITIES_PACKET:
					$pk = new ServerboundPlayerAbilitiesPacket();
					break;
				case InboundPacket::PLAYER_ACTION_PACKET:
					$pk = new ServerboundPlayerActionPacket();
					break;
				case InboundPacket::PLAYER_COMMAND_PACKET:
					$pk = new ServerboundPlayerCommandPacket();
					break;
				case InboundPacket::SEEN_ADVANCEMENTS_PACKET:
					$pk = new ServerboundSeenAdvancementsPacket();
					break;
				case InboundPacket::SET_CARRIED_ITEM_PACKET:
					$pk = new ServerboundSetCarriedItemPacket();
					break;
				case InboundPacket::SET_CREATIVE_MODE_SLOT_PACKET:
					$pk = new ServerboundSetCreativeModeSlotPacket();
					break;
				case InboundPacket::SIGN_UPDATE_PACKET:
					$pk = new ServerboundSignUpdatePacket();
					break;
				case InboundPacket::SWING_PACKET:
					$pk = new ServerboundSwingPacket();
					break;
				case InboundPacket::USE_ITEM_ON_PACKET:
					$pk = new ServerboundUseItemOnPacket();
					break;
				case InboundPacket::USE_ITEM_PACKET:
					$pk = new ServerboundUseItemPacket();
					break;
				default:
					echo "[Receive][Interface] 0x" . bin2hex(chr($pid)) . " Not implemented\n"; //Debug
					return;
			}

			$pk->read($payload, $offset);
			$this->receivePacket($session, $pk);
		}elseif($status === 0){
			if($pid === InboundPacket::HELLO_PACKET){
				$pk = new ServerboundHelloPacket();
				$pk->read($payload, $offset);
				$session->bigBrother_handleAuthentication($pk->name, $this->plugin->isOnlineMode());
			}elseif($pid === InboundPacket::KEY_PACKET && $this->plugin->isOnlineMode()){
				$pk = new ServerboundKeyPacket();
				$pk->read($payload, $offset);
				$session->bigBrother_processAuthentication($pk);
			}else{
				$session->disconnect("Unexpected packet $pid", true);
			}
		}
	}

	public function tick() : void{
		while(is_string($buffer = $this->thread->readThreadToMainPacket())){
			$offset = 1;
			$pid = ord($buffer[0]);

			if($pid === ServerManager::PACKET_SEND_PACKET){
				$id = Binary::readInt(substr($buffer, $offset, 4));
				$offset += 4;
				if(isset($this->sessionsPlayers[$id])){
					$payload = substr($buffer, $offset);
					try{
						$this->handlePacket($this->sessionsPlayers[$id], $payload);
					}catch(\Exception $e){
						$logger = $this->server->getLogger();
						if($logger instanceof MainLogger){
							$logger->debug("DesktopPacket 0x" . bin2hex($payload));
							$logger->logException($e);
						}
					}
				}
			}elseif($pid === ServerManager::PACKET_OPEN_SESSION){
				$id = Binary::readInt(substr($buffer, $offset, 4));
				$offset += 4;
				if(isset($this->sessionsPlayers[$id])){
					continue;
				}
				$len = ord($buffer[$offset++]);
				$address = substr($buffer, $offset, $len);
				$offset += $len;
				$port = Binary::readShort(substr($buffer, $offset, 2));

				$identifier = "$id:$address:$port";

				$session = new DesktopNetworkSession($this->server, $this->server->getNetwork()->getSessionManager(), PacketPool::getInstance(), new class implements PacketSender{
					public function send(string $payload, bool $immediate) : void{
					}

					public function close(string $reason = "unknown reason") : void{
					}
				}, new StandardPacketBroadcaster(Server::getInstance(), ProtocolInfo::CURRENT_PROTOCOL), ZlibCompressor::getInstance(), $address, $port, $this->plugin);
				$this->sessions->attach($session, $id);
				$this->sessionsPlayers[$id] = $session;
				$this->server->getNetwork()->getSessionManager()->add($session);
			}elseif($pid === ServerManager::PACKET_CLOSE_SESSION){
				$id = Binary::readInt(substr($buffer, $offset, 4));
				if(!isset($this->sessionsPlayers[$id])){
					continue;
				}
				$this->server->getNetwork()->getSessionManager()->remove($this->sessionsPlayers[$id]);
				$this->closeSession($id);
			}

		}
	}
}

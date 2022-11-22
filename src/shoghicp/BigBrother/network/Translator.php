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

use pocketmine\block\BaseSign;
use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Door;
use pocketmine\block\FenceGate;
use pocketmine\block\Trapdoor;
use pocketmine\block\utils\SignText;
use pocketmine\entity\Attribute;
use pocketmine\entity\Entity;
use pocketmine\item\ItemTypeIds;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\convert\RuntimeBlockMapping;
use pocketmine\network\mcpe\handler\DeathPacketHandler;
use pocketmine\network\mcpe\handler\InGamePacketHandler;
use pocketmine\network\mcpe\protocol\ActorEventPacket;
use pocketmine\network\mcpe\protocol\AddPaintingPacket;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\network\mcpe\protocol\BlockEventPacket;
use pocketmine\network\mcpe\protocol\BossEventPacket;
use pocketmine\network\mcpe\protocol\ChangeDimensionPacket;
use pocketmine\network\mcpe\protocol\ChunkRadiusUpdatedPacket;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\DisconnectPacket;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\MobEffectPacket;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\network\mcpe\protocol\MoveActorAbsolutePacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\PlayerListPacket;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\network\mcpe\protocol\PlayStatusPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\ProtocolInfo as Info;
use pocketmine\network\mcpe\protocol\RemoveActorPacket;
use pocketmine\network\mcpe\protocol\RemoveObjectivePacket;
use pocketmine\network\mcpe\protocol\ServerboundPacket;
use pocketmine\network\mcpe\protocol\SetActorMotionPacket;
use pocketmine\network\mcpe\protocol\SetDifficultyPacket;
use pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket;
use pocketmine\network\mcpe\protocol\SetHealthPacket;
use pocketmine\network\mcpe\protocol\SetPlayerGameTypePacket;
use pocketmine\network\mcpe\protocol\SetScorePacket;
use pocketmine\network\mcpe\protocol\SetSpawnPositionPacket;
use pocketmine\network\mcpe\protocol\SetTimePacket;
use pocketmine\network\mcpe\protocol\SetTitlePacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\TakeItemActorPacket;
use pocketmine\network\mcpe\protocol\TextPacket;
use pocketmine\network\mcpe\protocol\types\ActorEvent;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\GameMode as ProtocolGameMode;
use pocketmine\network\mcpe\protocol\types\LevelEvent;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;
use pocketmine\network\mcpe\protocol\types\ParticleIds;
use pocketmine\network\mcpe\protocol\types\PlayerListAdditionEntries;
use pocketmine\network\mcpe\protocol\UpdateAttributesPacket;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\player\GameMode;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use pocketmine\world\Position;
use Ramsey\Uuid\Uuid;
use shoghicp\BigBrother\BigBrother;
use shoghicp\BigBrother\network\protocol\Login\LoginDisconnectPacket;
use shoghicp\BigBrother\network\protocol\Play\Client\AdvancementTabPacket;
use shoghicp\BigBrother\network\protocol\Play\Client\ClickWindowPacket;
use shoghicp\BigBrother\network\protocol\Play\Client\ClientSettingsPacket;
use shoghicp\BigBrother\network\protocol\Play\Client\ClientStatusPacket;
use shoghicp\BigBrother\network\protocol\Play\Client\CloseWindowPacket;
use shoghicp\BigBrother\network\protocol\Play\Client\EntityActionPacket;
use shoghicp\BigBrother\network\protocol\Play\Client\PlayerDiggingPacket;
use shoghicp\BigBrother\network\protocol\Play\Client\PlayerPositionPacket;
use shoghicp\BigBrother\network\protocol\Play\Client\PlayerRotationPacket;
use shoghicp\BigBrother\network\protocol\Play\Client\UpdateSignPacket;
use shoghicp\BigBrother\network\protocol\Play\Server\BlockActionPacket;
use shoghicp\BigBrother\network\protocol\Play\Server\BlockChangePacket;
use shoghicp\BigBrother\network\protocol\Play\Server\BossBarPacket;
use shoghicp\BigBrother\network\protocol\Play\Server\ChatMessagePacket;
use shoghicp\BigBrother\network\protocol\Play\Server\DestroyEntitiesPacket;
use shoghicp\BigBrother\network\protocol\Play\Server\DisplayScoreboardPacket;
use shoghicp\BigBrother\network\protocol\Play\Server\EffectPacket;
use shoghicp\BigBrother\network\protocol\Play\Server\EntityAnimationPacket as STCAnimatePacket;
use shoghicp\BigBrother\network\protocol\Play\Server\EntityEffectPacket;
use shoghicp\BigBrother\network\protocol\Play\Server\EntityEquipmentPacket;
use shoghicp\BigBrother\network\protocol\Play\Server\EntityHeadLookPacket;
use shoghicp\BigBrother\network\protocol\Play\Server\EntityMetadataPacket;
use shoghicp\BigBrother\network\protocol\Play\Server\EntityPropertiesPacket;
use shoghicp\BigBrother\network\protocol\Play\Server\EntityRotationPacket;
use shoghicp\BigBrother\network\protocol\Play\Server\EntityStatusPacket;
use shoghicp\BigBrother\network\protocol\Play\Server\EntityTeleportPacket;
use shoghicp\BigBrother\network\protocol\Play\Server\EntityVelocityPacket;
use shoghicp\BigBrother\network\protocol\Play\Server\JoinGamePacket;
use shoghicp\BigBrother\network\protocol\Play\Server\KeepAlivePacket;
use shoghicp\BigBrother\network\protocol\Play\Server\NamedSoundEffectPacket;
use shoghicp\BigBrother\network\protocol\Play\Server\ParticlePacket;
use shoghicp\BigBrother\network\protocol\Play\Server\PlayDisconnectPacket;
use shoghicp\BigBrother\network\protocol\Play\Server\PlayerAbilitiesPacket;
use shoghicp\BigBrother\network\protocol\Play\Server\PlayerInfoPacket;
use shoghicp\BigBrother\network\protocol\Play\Server\PlayerPositionAndLookPacket;
use shoghicp\BigBrother\network\protocol\Play\Server\PluginMessagePacket;
use shoghicp\BigBrother\network\protocol\Play\Server\RemoveEntityEffectPacket;
use shoghicp\BigBrother\network\protocol\Play\Server\RespawnPacket;
use shoghicp\BigBrother\network\protocol\Play\Server\ScoreboardObjectivePacket;
use shoghicp\BigBrother\network\protocol\Play\Server\SelectAdvancementTabPacket;
use shoghicp\BigBrother\network\protocol\Play\Server\SetExperiencePacket;
use shoghicp\BigBrother\network\protocol\Play\Server\SpawnPaintingPacket;
use shoghicp\BigBrother\network\protocol\Play\Server\SpawnPlayerPacket;
use shoghicp\BigBrother\network\protocol\Play\Server\SpawnPositionPacket;
use shoghicp\BigBrother\network\protocol\Play\Server\StatisticsPacket;
use shoghicp\BigBrother\network\protocol\Play\Server\TitlePacket;
use shoghicp\BigBrother\network\protocol\Play\Server\UpdateHealthPacket;
use shoghicp\BigBrother\network\protocol\Play\Server\UpdateScorePacket;
use shoghicp\BigBrother\network\protocol\Play\Server\UpdateViewPositionPacket;
use shoghicp\BigBrother\utils\ConvertUtils;
use function base64_decode;
use function base64_encode;
use function bin2hex;
use function chr;
use function count;
use function explode;
use function intval;
use function json_decode;
use function json_encode;
use function mt_rand;
use function str_replace;
use function substr;

class Translator{

	/**
	 * @return ServerboundPacket|ServerboundPacket[]|null
	 */
	public function interfaceToServer(DesktopNetworkSession $session, Packet $packet) : ServerboundPacket|array|null{
		switch($packet->pid()){
			case InboundPacket::TELEPORT_CONFIRM_PACKET:
			case InboundPacket::WINDOW_CONFIRMATION_PACKET://Transaction Confirm
			case InboundPacket::TAB_COMPLETE_PACKET:
				return null;

			case InboundPacket::CHAT_MESSAGE_PACKET:
				if($session->getHandler() instanceof InGamePacketHandler){
					/** @var protocol\Play\Client\ChatMessagePacket $packet */
					if(substr($packet->message, 0, 12) === ")respondform"){
						if(!isset($session->bigBrother_formId)){
							$session->getPlayer()->sendMessage(TextFormat::RED . "Form already closed.");
							return null;
						}
						$value = explode(" ", $packet->message)[1];
						$session->getPlayer()->onFormSubmit($session->bigBrother_formId, $value === "ESC" ? null : intval($value));
						unset($session->bigBrother_formId);
						return null;
					}
					$session->getPlayer()->chat($packet->message);
				}
				return null;

			case InboundPacket::CLIENT_STATUS_PACKET:
				/** @var ClientStatusPacket $packet */
				switch($packet->actionId){
					case 0:
						if($session->getHandler() instanceof DeathPacketHandler){
							$session->getPlayer()->respawn();
						}
						return null;
					case 1:
						//TODO: stat https://gist.github.com/Alvin-LB/8d0d13db00b3c00fd0e822a562025eff
						$statistic = [];

						$pk = new StatisticsPacket();
						$pk->count = count($statistic);
						$pk->statistic = $statistic;
						$session->putRawPacket($pk);
						break;
					default:
						echo "ClientStatusPacket: " . $packet->actionId . "\n";
						break;
				}
				return null;

			case InboundPacket::CLIENT_SETTINGS_PACKET:
				/** @var ClientSettingsPacket $packet */
				$session->setClientSettings([
					"ChatMode" => $packet->chatMode,
					"ChatColor" => $packet->chatColors,
					"SkinSettings" => $packet->displayedSkinParts,
				]);

				$pk = new EntityMetadataPacket();
				$pk->entityId = $session->getPlayer()->getId();
				$pk->metadata = [//Enable Display Skin Parts
					16 => [0, $packet->displayedSkinParts],
					"convert" => true,
				];
				$loggedInPlayers = Server::getInstance()->getOnlinePlayers();
				$plugin = $session->getPlugin();
				foreach($loggedInPlayers as $playerData){
					if($plugin->isJavaPlayer($playerData)){
						/** @noinspection PhpPossiblePolymorphicInvocationInspection */
						$playerData->getNetworkSession()->putRawPacket($pk);
					}
				}

				$session->getPlayer()->setViewDistance($packet->viewDistance);
				return null;

			case InboundPacket::CLICK_WINDOW_PACKET:
				/** @var ClickWindowPacket $packet */
				return $session->getInventoryUtils()->onWindowClick($packet);

			case InboundPacket::CLOSE_WINDOW_PACKET:
				/** @var CloseWindowPacket $packet */
				return $session->getInventoryUtils()->onWindowCloseFromPCtoPE($packet);

			case InboundPacket::PLUGIN_MESSAGE_PACKET:
				/** @var PluginMessagePacket $packet */
				switch($packet->channel){
					case "minecraft:brand":
						//TODO: brand
						break;
					/*case "MC|BEdit":
						$packets = [];
						/** @var Item $item *//*
						$item = clone $packet->data[0];

						if(!is_null(($pages = $item->getNamedTagEntry("pages")))){
							foreach($pages as $pageNumber => $pageTags){
								if($pageTags instanceof CompoundTag){
									foreach($pageTags as $name => $tag){
										if($tag instanceof StringTag){
											if($tag->getName() === "text"){
												$pk = new BookEditPacket();
												$pk->type = BookEditPacket::TYPE_REPLACE_PAGE;
												$pk->inventorySlot = $player->getInventory()->getHeldItemIndex() + 9;
												$pk->pageNumber = (int) $pageNumber;
												$pk->text = $tag->getValue();
												$pk->photoName = "";//Not implement

												$packets[] = $pk;
											}
										}
									}
								}
							}
						}

						return $packets;
					case "MC|BSign":
						$packets = [];
						/** @var Item $item *//*
						$item = clone $packet->data[0];

						if(!is_null(($pages = $item->getNamedTagEntry("pages")))){
							foreach($pages as $pageNumber => $pageTags){
								if($pageTags instanceof CompoundTag){
									foreach($pageTags as $name => $tag){
										if($tag instanceof StringTag){
											if($tag->getName() === "text"){
												$pk = new BookEditPacket();
												$pk->type = BookEditPacket::TYPE_REPLACE_PAGE;
												$pk->inventorySlot = $player->getInventory()->getHeldItemIndex() + 9;
												$pk->pageNumber = (int) $pageNumber;
												$pk->text = $tag->getValue();
												$pk->photoName = "";//Not implement

												$packets[] = $pk;
											}
										}
									}
								}
							}
						}

						$pk = new BookEditPacket();
						$pk->type = BookEditPacket::TYPE_SIGN_BOOK;
						$pk->inventorySlot = $player->getInventory()->getHeldItemIndex();
						$pk->title = $item->getNamedTagEntry("title")->getValue();
						$pk->author = $item->getNamedTagEntry("author")->getValue();

						$packets[] = $pk;

						return $packets;
					break;*/
				}
				return null;

			case InboundPacket::KEEP_ALIVE_PACKET:
				$pk = new KeepAlivePacket();
				$pk->keepAliveId = mt_rand();
				$session->putRawPacket($pk);
				return null;

			case InboundPacket::PLAYER_POSITION_PACKET:
				/** @var PlayerPositionPacket $packet */
				if($session->getPlayer()->isImmobile()){
					$pk = new PlayerPositionAndLookPacket();
					$pos = $session->getPlayer()->getLocation();
					$pk->x = $pos->x;
					$pk->y = $pos->y;
					$pk->z = $pos->z;
					$pk->yaw = $pos->yaw;
					$pk->pitch = $pos->pitch;
					$pk->onGround = $session->getPlayer()->isOnGround();
					$session->putRawPacket($pk);
					return null;
				}

				if($session->getHandler() instanceof InGamePacketHandler){
					$newPos = new Vector3($packet->x, $packet->feetY, $packet->z);
					/** @noinspection PhpPossiblePolymorphicInvocationInspection */
					if($session->getHandler()->forceMoveSync){
						$curPos = $session->getPlayer()->getPosition();

						if($newPos->distanceSquared($curPos) > 1){
							$session->getLogger()->debug("Got outdated pre-teleport movement, received " . $newPos . ", expected " . $curPos);
							return null;
						}

						/** @noinspection PhpPossiblePolymorphicInvocationInspection */
						$session->getHandler()->forceMoveSync = false;
					}
					$session->getPlayer()->handleMovement($newPos);

					if($session->getPlayer()->isOnGround() && !$packet->onGround){
						$session->getPlayer()->jump();
					}
				}
				return null;

			case InboundPacket::PLAYER_POSITION_AND_ROTATION_PACKET:
				/** @var protocol\Play\Client\PlayerPositionAndRotationPacket $packet */
				if($session->getPlayer()->isImmobile()){
					$pk = new PlayerPositionAndLookPacket();
					$pos = $session->getPlayer()->getLocation();
					$pk->x = $pos->x;
					$pk->y = $pos->y;
					$pk->z = $pos->z;
					$pk->yaw = $pos->yaw;
					$pk->pitch = $pos->pitch;
					$pk->onGround = $session->getPlayer()->isOnGround();
					$session->putRawPacket($pk);
					return null;
				}

				if($session->getHandler() instanceof InGamePacketHandler){
					$newPos = new Vector3($packet->x, $packet->feetY, $packet->z);
					/** @noinspection PhpPossiblePolymorphicInvocationInspection */
					if($session->getHandler()->forceMoveSync){
						$curPos = $session->getPlayer()->getPosition();

						if($newPos->distanceSquared($curPos) > 1){
							$session->getLogger()->debug("Got outdated pre-teleport movement, received " . $newPos . ", expected " . $curPos);
							return null;
						}

						/** @noinspection PhpPossiblePolymorphicInvocationInspection */
						$session->getHandler()->forceMoveSync = false;
					}
					$session->getPlayer()->handleMovement($newPos);
					$session->getPlayer()->setRotation($packet->yaw, $packet->pitch);

					if($session->getPlayer()->isOnGround() && !$packet->onGround){
						$session->getPlayer()->jump();
					}
				}
				return null;

			case InboundPacket::PLAYER_ROTATION_PACKET:
				/** @var PlayerRotationPacket $packet */
				$pos = $session->getPlayer()->getLocation();
				if($session->getPlayer()->isImmobile()){
					$pk = new PlayerPositionAndLookPacket();
					$pk->x = $pos->x;
					$pk->y = $pos->y;
					$pk->z = $pos->z;
					$pk->yaw = $pos->yaw;
					$pk->pitch = $pos->pitch;
					$pk->onGround = $session->getPlayer()->isOnGround();
					$session->putRawPacket($pk);
					return null;
				}

				if($session->getHandler() instanceof InGamePacketHandler){
					$session->getPlayer()->setRotation($packet->yaw, $packet->pitch);
				}
				return null;

			case InboundPacket::PLAYER_DIGGING_PACKET:
				if($session->getHandler() instanceof InGamePacketHandler){
					/** @var PlayerDiggingPacket $packet */
					switch($packet->status){
						case 0:
							if($session->getPlayer()->getGamemode()->equals(GameMode::CREATIVE())){
								$session->getPlayer()->breakBlock(new Vector3($packet->x, $packet->y, $packet->z));
								return null;
							}
							$session->bigBrother_breakPosition = [$pos = new Vector3($packet->x, $packet->y, $packet->z), $packet->face];

							$session->getPlayer()->attackBlock($pos, $packet->face);

							$block = $session->getPlayer()->getWorld()->getBlockAt($packet->x, $packet->y, $packet->z);
							if($block->getBreakInfo()->getHardness() === (float) 0){
								$session->getPlayer()->stopBreakBlock($pos);

								$session->getPlayer()->breakBlock($pos);

								$session->getPlayer()->stopBreakBlock($pos);
							}
							return null;
						case 1:
							$session->bigBrother_breakPosition = [new Vector3(0, 0, 0), 0];

							$session->getPlayer()->stopBreakBlock(new Vector3($packet->x, $packet->y, $packet->z));
							return null;
						case 2:
							if(!$session->getPlayer()->getGamemode()->equals(GameMode::CREATIVE())){
								$session->bigBrother_breakPosition = [new Vector3(0, 0, 0), 0];

								$session->getPlayer()->stopBreakBlock($pos = new Vector3($packet->x, $packet->y, $packet->z));
								$session->getPlayer()->breakBlock($pos);
								$session->getPlayer()->stopBreakBlock(new Vector3($packet->x, $packet->y, $packet->z));
								return null;
							}
							break;
						default:
							echo "PlayerDiggingPacket: " . $packet->status . "\n";
							break;
					}
				}
				return null;

			case InboundPacket::ENTITY_ACTION_PACKET:
				if($session->getHandler() instanceof InGamePacketHandler){
					/** @var EntityActionPacket $packet */
					switch($packet->actionId){
						case 0://Start sneaking
							$session->getPlayer()->setSneaking(true);
							return null;
						case 1://Stop sneaking
							$session->getPlayer()->setSneaking(false);
							return null;
						case 2://leave bed
							$session->getPlayer()->stopSleep();
							return null;
						case 3://Start sprinting
							$session->getPlayer()->setSprinting(true);
							return null;
						case 4://Stop sprinting
							$session->getPlayer()->setSprinting(false);
							return null;
						default:
							echo "EntityActionPacket: " . $packet->actionId . "\n";
							break;
					}
				}
				return null;

			case InboundPacket::ADVANCEMENT_TAB_PACKET:
				/** @var AdvancementTabPacket $packet */
				if($packet->status === 0){
					$pk = new SelectAdvancementTabPacket();
					$pk->hasId = true;
					$pk->identifier = $packet->tabId;
					$session->putRawPacket($pk);
				}
				return null;

			case InboundPacket::UPDATE_SIGN_PACKET:
				if($session->getHandler() instanceof InGamePacketHandler){
					/** @var UpdateSignPacket $packet */
					$pos = new Vector3($packet->x, $packet->y, $packet->z);
					if($pos->distanceSquared($session->getPlayer()->getLocation()) > 10000){
						return null;
					}

					$block = $session->getPlayer()->getLocation()->getWorld()->getBlock($pos);
					if($block instanceof BaseSign){
						$text = $block->getText();
						$block->updateText($session->getPlayer(), new SignText([$packet->line1, $packet->line2, $packet->line3, $packet->line4], $text->getBaseColor(), $text->isGlowing()));
					}
				}
				return null;

//			case InboundPacket::ANIMATION_PACKET:
//				$pk = new AnimatePacket();
//				$pk->action = 1;
//				$pk->actorRuntimeId = $session->getId();
//
//				$pos = $session->bigBrother_getBreakPosition();
//				/**
//				 * @var Vector3[]                   $pos
//				 * @phpstan-var array{Vector3, int} $pos
//				 */
//				if(!$pos[0]->equals(new Vector3(0, 0, 0))){
//					$packets = [$pk];
//
//					$pk = new PlayerActionPacket();
//					$pk->actorRuntimeId = $session->getId();
//					$pk->action = PlayerAction::CONTINUE_DESTROY_BLOCK;
//					$pk->x = $pos[0]->x;
//					$pk->y = $pos[0]->y;
//					$pk->z = $pos[0]->z;
//					$pk->face = $pos[1];
//					$packets[] = $pk;
//
//					return $packets;
//				}
//
//				return $pk;

//			case InboundPacket::PLAYER_BLOCK_PLACEMENT_PACKET:
//				/** @var PlayerBlockPlacementPacket $packet */
//				$blockClicked = $session->getLevel()->getBlock(new Vector3($packet->x, $packet->y, $packet->z));
//				$blockReplace = $blockClicked->getSide($packet->face);
//
//				if(ItemFrameBlockEntity::exists($session->getLevel(), $blockReplace->getX(), $blockReplace->getY(), $blockReplace->getZ())){
//					$pk = new BlockChangePacket();//Cancel place block
//					$pk->x = $blockReplace->getX();
//					$pk->y = $blockReplace->getY();
//					$pk->z = $blockReplace->getZ();
//					$pk->blockId = Block::AIR;
//					$pk->blockMeta = 0;
//					$session->putRawPacket($pk);
//					return null;
//				}
//
//				$clickPos = new Vector3($packet->x, $packet->y, $packet->z);
//
//				$pk = new InventoryTransactionPacket();
//				$pk->trData = UseItemTransactionData::new(
//					[],
//					UseItemTransactionData::ACTION_CLICK_BLOCK,
//					$clickPos,
//					$packet->direction,
//					$session->getInventory()->getHeldItemIndex(),
//					ItemStackWrapper::legacy($session->getInventory()->getItemInHand()),
//					$session->asVector3(),
//					$clickPos,
//					$session->getLevel()->getBlock($clickPos)->getRuntimeId());
//				return $pk;

//			case InboundPacket::USE_ITEM_PACKET:
//				if($session->getInventory()->getItemInHand()->getId() === Item::WRITTEN_BOOK){
//					$pk = new PluginMessagePacket();
//					$pk->channel = "MC|BOpen";
//					$pk->data[] = 0;//main hand
//
//					$session->putRawPacket($pk);
//					return null;
//				}
//
//				$clickPos = new Vector3(0, 0, 0);
//
//				$pk = new InventoryTransactionPacket();
//				$pk->trData = UseItemTransactionData::new(
//					[],
//					UseItemTransactionData::ACTION_CLICK_AIR,
//					$clickPos,
//					-1,
//					$session->getInventory()->getHeldItemIndex(),
//					ItemStackWrapper::legacy($session->getInventory()->getItemInHand()),
//					$session->asVector3(),
//					$clickPos,
//					$session->getLevel()->getBlock($clickPos)->getRuntimeId()
//				);
//				return $pk;
			default:
				return null;
		}
	}

	/**
	 * @return ClientboundPacket|ClientboundPacket[]|null
	 * @throws \UnexpectedValueException
	 */
	public function serverToInterface(DesktopNetworkSession $session, ClientboundPacket $packet) : array|null|OutboundPacket{
		switch($packet->pid()){
			case Info::PLAY_STATUS_PACKET:
				/** @var PlayStatusPacket $packet */
				if($packet->status === PlayStatusPacket::PLAYER_SPAWN){
					$pk = new PlayerPositionAndLookPacket();//for loading screen
					$pos = $session->getPlayer()->getPosition();
					$pk->x = $pos->x;
					$pk->y = $pos->y;
					$pk->z = $pos->z;
					$pk->yaw = 0;
					$pk->pitch = 0;
					$pk->flags = 0;

					return $pk;
				}

				return null;

			case Info::DISCONNECT_PACKET:
				/** @var DisconnectPacket $packet */
				$pk = $session->status === 0 ? new LoginDisconnectPacket() : new PlayDisconnectPacket();
				$pk->reason = BigBrother::toJSON($packet->message);

				return $pk;

			case Info::TEXT_PACKET:
				/** @var TextPacket $packet */
				if($packet->message === "chat.type.achievement"){
					$packet->message = "chat.type.advancement.task";
				}
				switch($packet->type){
					case TextPacket::TYPE_RAW:
					case TextPacket::TYPE_CHAT:
					case TextPacket::TYPE_SYSTEM:
					case TextPacket::TYPE_WHISPER:
					case TextPacket::TYPE_ANNOUNCEMENT:
					case TextPacket::TYPE_JSON_WHISPER:
					case TextPacket::TYPE_JSON:
					case TextPacket::TYPE_JSON_ANNOUNCEMENT:
						$session->onRawChatMessage($packet->message, $packet->type === TextPacket::TYPE_SYSTEM);
						break;

					case TextPacket::TYPE_TRANSLATION:
						$session->onTranslatedChatMessage($packet->message, $packet->parameters);
						break;

					case TextPacket::TYPE_JUKEBOX_POPUP: // What is this?
						break;

					case TextPacket::TYPE_POPUP:
						$session->onPopup($packet->message);
						break;

					case TextPacket::TYPE_TIP:
						$session->onTip($packet->message);
				}
				return null;

			case Info::SET_TIME_PACKET:
				/** @var SetTimePacket $packet */
				$session->syncWorldTime($packet->time);
				return null;

			case Info::START_GAME_PACKET:
				/** @var StartGamePacket $packet */
				$packets = [];

				$pk = new JoinGamePacket();
				$pk->isHardcore = Server::getInstance()->isHardcore();
				$pk->entityId = $packet->actorRuntimeId;
				$pk->gamemode = $packet->playerGamemode;
				$pk->previousGamemode = 0;
				$pk->worldNames = ["minecraft:overworld", "minecraft:the_nether", "minecraft:the_end"];
				$pk->dimension = base64_decode("CgAAAQALcGlnbGluX3NhZmUAAQAHbmF0dXJhbAEFAA1hbWJpZW50X2xpZ2h0AAAAAAgACmluZmluaWJ1cm4AHm1pbmVjcmFmdDppbmZpbmlidXJuX292ZXJ3b3JsZAEAFHJlc3Bhd25fYW5jaG9yX3dvcmtzAAEADGhhc19za3lsaWdodAEBAAliZWRfd29ya3MBCAAHZWZmZWN0cwATbWluZWNyYWZ0Om92ZXJ3b3JsZAEACWhhc19yYWlkcwEDAA5sb2dpY2FsX2hlaWdodAAAAQAGABBjb29yZGluYXRlX3NjYWxlP/AAAAAAAAABAAl1bHRyYXdhcm0AAQALaGFzX2NlaWxpbmcAAA==");
				$pk->dimensionCodec = base64_decode("CgAACgAYbWluZWNyYWZ0OmRpbWVuc2lvbl90eXBlCAAEdHlwZQAYbWluZWNyYWZ0OmRpbWVuc2lvbl90eXBlCQAFdmFsdWUKAAAABAgABG5hbWUAE21pbmVjcmFmdDpvdmVyd29ybGQDAAJpZAAAAAAKAAdlbGVtZW50AQALcGlnbGluX3NhZmUAAQAHbmF0dXJhbAEFAA1hbWJpZW50X2xpZ2h0AAAAAAgACmluZmluaWJ1cm4AHm1pbmVjcmFmdDppbmZpbmlidXJuX292ZXJ3b3JsZAEAFHJlc3Bhd25fYW5jaG9yX3dvcmtzAAEADGhhc19za3lsaWdodAEBAAliZWRfd29ya3MBCAAHZWZmZWN0cwATbWluZWNyYWZ0Om92ZXJ3b3JsZAEACWhhc19yYWlkcwEDAA5sb2dpY2FsX2hlaWdodAAAAQAGABBjb29yZGluYXRlX3NjYWxlP/AAAAAAAAABAAl1bHRyYXdhcm0AAQALaGFzX2NlaWxpbmcAAAAIAARuYW1lABltaW5lY3JhZnQ6b3ZlcndvcmxkX2NhdmVzAwACaWQAAAABCgAHZWxlbWVudAEAC3BpZ2xpbl9zYWZlAAEAB25hdHVyYWwBBQANYW1iaWVudF9saWdodAAAAAAIAAppbmZpbmlidXJuAB5taW5lY3JhZnQ6aW5maW5pYnVybl9vdmVyd29ybGQBABRyZXNwYXduX2FuY2hvcl93b3JrcwABAAxoYXNfc2t5bGlnaHQBAQAJYmVkX3dvcmtzAQgAB2VmZmVjdHMAE21pbmVjcmFmdDpvdmVyd29ybGQBAAloYXNfcmFpZHMBAwAObG9naWNhbF9oZWlnaHQAAAEABgAQY29vcmRpbmF0ZV9zY2FsZT/wAAAAAAAAAQAJdWx0cmF3YXJtAAEAC2hhc19jZWlsaW5nAQAACAAEbmFtZQAUbWluZWNyYWZ0OnRoZV9uZXRoZXIDAAJpZAAAAAIKAAdlbGVtZW50AQALcGlnbGluX3NhZmUBAQAHbmF0dXJhbAAFAA1hbWJpZW50X2xpZ2h0PczMzQgACmluZmluaWJ1cm4AG21pbmVjcmFmdDppbmZpbmlidXJuX25ldGhlcgEAFHJlc3Bhd25fYW5jaG9yX3dvcmtzAQEADGhhc19za3lsaWdodAABAAliZWRfd29ya3MACAAHZWZmZWN0cwAUbWluZWNyYWZ0OnRoZV9uZXRoZXIEAApmaXhlZF90aW1lAAAAAAAARlABAAloYXNfcmFpZHMAAwAObG9naWNhbF9oZWlnaHQAAACABgAQY29vcmRpbmF0ZV9zY2FsZUAgAAAAAAAAAQAJdWx0cmF3YXJtAQEAC2hhc19jZWlsaW5nAQAACAAEbmFtZQARbWluZWNyYWZ0OnRoZV9lbmQDAAJpZAAAAAMKAAdlbGVtZW50AQALcGlnbGluX3NhZmUAAQAHbmF0dXJhbAAFAA1hbWJpZW50X2xpZ2h0AAAAAAgACmluZmluaWJ1cm4AGG1pbmVjcmFmdDppbmZpbmlidXJuX2VuZAEAFHJlc3Bhd25fYW5jaG9yX3dvcmtzAAEADGhhc19za3lsaWdodAABAAliZWRfd29ya3MACAAHZWZmZWN0cwARbWluZWNyYWZ0OnRoZV9lbmQEAApmaXhlZF90aW1lAAAAAAAAF3ABAAloYXNfcmFpZHMBAwAObG9naWNhbF9oZWlnaHQAAAEABgAQY29vcmRpbmF0ZV9zY2FsZT/wAAAAAAAAAQAJdWx0cmF3YXJtAAEAC2hhc19jZWlsaW5nAAAAAAoAGG1pbmVjcmFmdDp3b3JsZGdlbi9iaW9tZQgABHR5cGUAGG1pbmVjcmFmdDp3b3JsZGdlbi9iaW9tZQkABXZhbHVlCgAAAE8IAARuYW1lAA9taW5lY3JhZnQ6b2NlYW4DAAJpZAAAAAAKAAdlbGVtZW50CAANcHJlY2lwaXRhdGlvbgAEcmFpbgoAB2VmZmVjdHMDAAlza3lfY29sb3IAe6T/AwAPd2F0ZXJfZm9nX2NvbG9yAAUFMwMACWZvZ19jb2xvcgDA2P8DAAt3YXRlcl9jb2xvcgA/duQKAAptb29kX3NvdW5kAwAKdGlja19kZWxheQAAF3AGAAZvZmZzZXRAAAAAAAAAAAgABXNvdW5kABZtaW5lY3JhZnQ6YW1iaWVudC5jYXZlAwATYmxvY2tfc2VhcmNoX2V4dGVudAAAAAgAAAUABWRlcHRov4AAAAUAC3RlbXBlcmF0dXJlPwAAAAUABXNjYWxlPczMzQUACGRvd25mYWxsPwAAAAgACGNhdGVnb3J5AAVvY2VhbgAACAAEbmFtZQAQbWluZWNyYWZ0OnBsYWlucwMAAmlkAAAAAQoAB2VsZW1lbnQIAA1wcmVjaXBpdGF0aW9uAARyYWluCgAHZWZmZWN0cwMACXNreV9jb2xvcgB4p/8DAA93YXRlcl9mb2dfY29sb3IABQUzAwAJZm9nX2NvbG9yAMDY/wMAC3dhdGVyX2NvbG9yAD925AoACm1vb2Rfc291bmQDAAp0aWNrX2RlbGF5AAAXcAYABm9mZnNldEAAAAAAAAAACAAFc291bmQAFm1pbmVjcmFmdDphbWJpZW50LmNhdmUDABNibG9ja19zZWFyY2hfZXh0ZW50AAAACAAABQAFZGVwdGg+AAAABQALdGVtcGVyYXR1cmU/TMzNBQAFc2NhbGU9TMzNBQAIZG93bmZhbGw+zMzNCAAIY2F0ZWdvcnkABnBsYWlucwAACAAEbmFtZQAQbWluZWNyYWZ0OmRlc2VydAMAAmlkAAAAAgoAB2VsZW1lbnQIAA1wcmVjaXBpdGF0aW9uAARub25lCgAHZWZmZWN0cwMACXNreV9jb2xvcgBusf8DAA93YXRlcl9mb2dfY29sb3IABQUzAwAJZm9nX2NvbG9yAMDY/wMAC3dhdGVyX2NvbG9yAD925AoACm1vb2Rfc291bmQDAAp0aWNrX2RlbGF5AAAXcAYABm9mZnNldEAAAAAAAAAACAAFc291bmQAFm1pbmVjcmFmdDphbWJpZW50LmNhdmUDABNibG9ja19zZWFyY2hfZXh0ZW50AAAACAAABQAFZGVwdGg+AAAABQALdGVtcGVyYXR1cmVAAAAABQAFc2NhbGU9TMzNBQAIZG93bmZhbGwAAAAACAAIY2F0ZWdvcnkABmRlc2VydAAACAAEbmFtZQATbWluZWNyYWZ0Om1vdW50YWlucwMAAmlkAAAAAwoAB2VsZW1lbnQIAA1wcmVjaXBpdGF0aW9uAARyYWluCgAHZWZmZWN0cwMACXNreV9jb2xvcgB9ov8DAA93YXRlcl9mb2dfY29sb3IABQUzAwAJZm9nX2NvbG9yAMDY/wMAC3dhdGVyX2NvbG9yAD925AoACm1vb2Rfc291bmQDAAp0aWNrX2RlbGF5AAAXcAYABm9mZnNldEAAAAAAAAAACAAFc291bmQAFm1pbmVjcmFmdDphbWJpZW50LmNhdmUDABNibG9ja19zZWFyY2hfZXh0ZW50AAAACAAABQAFZGVwdGg/gAAABQALdGVtcGVyYXR1cmU+TMzNBQAFc2NhbGU/AAAABQAIZG93bmZhbGw+mZmaCAAIY2F0ZWdvcnkADWV4dHJlbWVfaGlsbHMAAAgABG5hbWUAEG1pbmVjcmFmdDpmb3Jlc3QDAAJpZAAAAAQKAAdlbGVtZW50CAANcHJlY2lwaXRhdGlvbgAEcmFpbgoAB2VmZmVjdHMDAAlza3lfY29sb3IAeab/AwAPd2F0ZXJfZm9nX2NvbG9yAAUFMwMACWZvZ19jb2xvcgDA2P8DAAt3YXRlcl9jb2xvcgA/duQKAAptb29kX3NvdW5kAwAKdGlja19kZWxheQAAF3AGAAZvZmZzZXRAAAAAAAAAAAgABXNvdW5kABZtaW5lY3JhZnQ6YW1iaWVudC5jYXZlAwATYmxvY2tfc2VhcmNoX2V4dGVudAAAAAgAAAUABWRlcHRoPczMzQUAC3RlbXBlcmF0dXJlPzMzMwUABXNjYWxlPkzMzQUACGRvd25mYWxsP0zMzQgACGNhdGVnb3J5AAZmb3Jlc3QAAAgABG5hbWUAD21pbmVjcmFmdDp0YWlnYQMAAmlkAAAABQoAB2VsZW1lbnQIAA1wcmVjaXBpdGF0aW9uAARyYWluCgAHZWZmZWN0cwMACXNreV9jb2xvcgB9o/8DAA93YXRlcl9mb2dfY29sb3IABQUzAwAJZm9nX2NvbG9yAMDY/wMAC3dhdGVyX2NvbG9yAD925AoACm1vb2Rfc291bmQDAAp0aWNrX2RlbGF5AAAXcAYABm9mZnNldEAAAAAAAAAACAAFc291bmQAFm1pbmVjcmFmdDphbWJpZW50LmNhdmUDABNibG9ja19zZWFyY2hfZXh0ZW50AAAACAAABQAFZGVwdGg+TMzNBQALdGVtcGVyYXR1cmU+gAAABQAFc2NhbGU+TMzNBQAIZG93bmZhbGw/TMzNCAAIY2F0ZWdvcnkABXRhaWdhAAAIAARuYW1lAA9taW5lY3JhZnQ6c3dhbXADAAJpZAAAAAYKAAdlbGVtZW50CAANcHJlY2lwaXRhdGlvbgAEcmFpbgoAB2VmZmVjdHMIABRncmFzc19jb2xvcl9tb2RpZmllcgAFc3dhbXADAAlza3lfY29sb3IAeKf/AwANZm9saWFnZV9jb2xvcgBqcDkDAA93YXRlcl9mb2dfY29sb3IAIyMXAwAJZm9nX2NvbG9yAMDY/wMAC3dhdGVyX2NvbG9yAGF7ZAoACm1vb2Rfc291bmQDAAp0aWNrX2RlbGF5AAAXcAYABm9mZnNldEAAAAAAAAAACAAFc291bmQAFm1pbmVjcmFmdDphbWJpZW50LmNhdmUDABNibG9ja19zZWFyY2hfZXh0ZW50AAAACAAABQAFZGVwdGi+TMzNBQALdGVtcGVyYXR1cmU/TMzNBQAFc2NhbGU9zMzNBQAIZG93bmZhbGw/ZmZmCAAIY2F0ZWdvcnkABXN3YW1wAAAIAARuYW1lAA9taW5lY3JhZnQ6cml2ZXIDAAJpZAAAAAcKAAdlbGVtZW50CAANcHJlY2lwaXRhdGlvbgAEcmFpbgoAB2VmZmVjdHMDAAlza3lfY29sb3IAe6T/AwAPd2F0ZXJfZm9nX2NvbG9yAAUFMwMACWZvZ19jb2xvcgDA2P8DAAt3YXRlcl9jb2xvcgA/duQKAAptb29kX3NvdW5kAwAKdGlja19kZWxheQAAF3AGAAZvZmZzZXRAAAAAAAAAAAgABXNvdW5kABZtaW5lY3JhZnQ6YW1iaWVudC5jYXZlAwATYmxvY2tfc2VhcmNoX2V4dGVudAAAAAgAAAUABWRlcHRovwAAAAUAC3RlbXBlcmF0dXJlPwAAAAUABXNjYWxlAAAAAAUACGRvd25mYWxsPwAAAAgACGNhdGVnb3J5AAVyaXZlcgAACAAEbmFtZQAXbWluZWNyYWZ0Om5ldGhlcl93YXN0ZXMDAAJpZAAAAAgKAAdlbGVtZW50CAANcHJlY2lwaXRhdGlvbgAEbm9uZQoAB2VmZmVjdHMKAAVtdXNpYwEAFXJlcGxhY2VfY3VycmVudF9tdXNpYwADAAltYXhfZGVsYXkAAF3ACAAFc291bmQAJG1pbmVjcmFmdDptdXNpYy5uZXRoZXIubmV0aGVyX3dhc3RlcwMACW1pbl9kZWxheQAALuAAAwAJc2t5X2NvbG9yAG6x/wgADWFtYmllbnRfc291bmQAJG1pbmVjcmFmdDphbWJpZW50Lm5ldGhlcl93YXN0ZXMubG9vcAoAD2FkZGl0aW9uc19zb3VuZAgABXNvdW5kACltaW5lY3JhZnQ6YW1iaWVudC5uZXRoZXJfd2FzdGVzLmFkZGl0aW9ucwYAC3RpY2tfY2hhbmNlP4a7mMfigkEAAwAPd2F0ZXJfZm9nX2NvbG9yAAUFMwMACWZvZ19jb2xvcgAzCAgDAAt3YXRlcl9jb2xvcgA/duQKAAptb29kX3NvdW5kAwAKdGlja19kZWxheQAAF3AGAAZvZmZzZXRAAAAAAAAAAAgABXNvdW5kACRtaW5lY3JhZnQ6YW1iaWVudC5uZXRoZXJfd2FzdGVzLm1vb2QDABNibG9ja19zZWFyY2hfZXh0ZW50AAAACAAABQAFZGVwdGg9zMzNBQALdGVtcGVyYXR1cmVAAAAABQAFc2NhbGU+TMzNBQAIZG93bmZhbGwAAAAACAAIY2F0ZWdvcnkABm5ldGhlcgAACAAEbmFtZQARbWluZWNyYWZ0OnRoZV9lbmQDAAJpZAAAAAkKAAdlbGVtZW50CAANcHJlY2lwaXRhdGlvbgAEbm9uZQoAB2VmZmVjdHMDAAlza3lfY29sb3IAAAAAAwAPd2F0ZXJfZm9nX2NvbG9yAAUFMwMACWZvZ19jb2xvcgCggKADAAt3YXRlcl9jb2xvcgA/duQKAAptb29kX3NvdW5kAwAKdGlja19kZWxheQAAF3AGAAZvZmZzZXRAAAAAAAAAAAgABXNvdW5kABZtaW5lY3JhZnQ6YW1iaWVudC5jYXZlAwATYmxvY2tfc2VhcmNoX2V4dGVudAAAAAgAAAUABWRlcHRoPczMzQUAC3RlbXBlcmF0dXJlPwAAAAUABXNjYWxlPkzMzQUACGRvd25mYWxsPwAAAAgACGNhdGVnb3J5AAd0aGVfZW5kAAAIAARuYW1lABZtaW5lY3JhZnQ6ZnJvemVuX29jZWFuAwACaWQAAAAKCgAHZWxlbWVudAgADXByZWNpcGl0YXRpb24ABHNub3cKAAdlZmZlY3RzAwAJc2t5X2NvbG9yAH+h/wMAD3dhdGVyX2ZvZ19jb2xvcgAFBTMDAAlmb2dfY29sb3IAwNj/AwALd2F0ZXJfY29sb3IAOTjJCgAKbW9vZF9zb3VuZAMACnRpY2tfZGVsYXkAABdwBgAGb2Zmc2V0QAAAAAAAAAAIAAVzb3VuZAAWbWluZWNyYWZ0OmFtYmllbnQuY2F2ZQMAE2Jsb2NrX3NlYXJjaF9leHRlbnQAAAAIAAAFAAVkZXB0aL+AAAAFAAt0ZW1wZXJhdHVyZQAAAAAFAAVzY2FsZT3MzM0FAAhkb3duZmFsbD8AAAAIAAhjYXRlZ29yeQAFb2NlYW4IABR0ZW1wZXJhdHVyZV9tb2RpZmllcgAGZnJvemVuAAAIAARuYW1lABZtaW5lY3JhZnQ6ZnJvemVuX3JpdmVyAwACaWQAAAALCgAHZWxlbWVudAgADXByZWNpcGl0YXRpb24ABHNub3cKAAdlZmZlY3RzAwAJc2t5X2NvbG9yAH+h/wMAD3dhdGVyX2ZvZ19jb2xvcgAFBTMDAAlmb2dfY29sb3IAwNj/AwALd2F0ZXJfY29sb3IAOTjJCgAKbW9vZF9zb3VuZAMACnRpY2tfZGVsYXkAABdwBgAGb2Zmc2V0QAAAAAAAAAAIAAVzb3VuZAAWbWluZWNyYWZ0OmFtYmllbnQuY2F2ZQMAE2Jsb2NrX3NlYXJjaF9leHRlbnQAAAAIAAAFAAVkZXB0aL8AAAAFAAt0ZW1wZXJhdHVyZQAAAAAFAAVzY2FsZQAAAAAFAAhkb3duZmFsbD8AAAAIAAhjYXRlZ29yeQAFcml2ZXIAAAgABG5hbWUAFm1pbmVjcmFmdDpzbm93eV90dW5kcmEDAAJpZAAAAAwKAAdlbGVtZW50CAANcHJlY2lwaXRhdGlvbgAEc25vdwoAB2VmZmVjdHMDAAlza3lfY29sb3IAf6H/AwAPd2F0ZXJfZm9nX2NvbG9yAAUFMwMACWZvZ19jb2xvcgDA2P8DAAt3YXRlcl9jb2xvcgA/duQKAAptb29kX3NvdW5kAwAKdGlja19kZWxheQAAF3AGAAZvZmZzZXRAAAAAAAAAAAgABXNvdW5kABZtaW5lY3JhZnQ6YW1iaWVudC5jYXZlAwATYmxvY2tfc2VhcmNoX2V4dGVudAAAAAgAAAUABWRlcHRoPgAAAAUAC3RlbXBlcmF0dXJlAAAAAAUABXNjYWxlPUzMzQUACGRvd25mYWxsPwAAAAgACGNhdGVnb3J5AANpY3kAAAgABG5hbWUAGW1pbmVjcmFmdDpzbm93eV9tb3VudGFpbnMDAAJpZAAAAA0KAAdlbGVtZW50CAANcHJlY2lwaXRhdGlvbgAEc25vdwoAB2VmZmVjdHMDAAlza3lfY29sb3IAf6H/AwAPd2F0ZXJfZm9nX2NvbG9yAAUFMwMACWZvZ19jb2xvcgDA2P8DAAt3YXRlcl9jb2xvcgA/duQKAAptb29kX3NvdW5kAwAKdGlja19kZWxheQAAF3AGAAZvZmZzZXRAAAAAAAAAAAgABXNvdW5kABZtaW5lY3JhZnQ6YW1iaWVudC5jYXZlAwATYmxvY2tfc2VhcmNoX2V4dGVudAAAAAgAAAUABWRlcHRoPuZmZgUAC3RlbXBlcmF0dXJlAAAAAAUABXNjYWxlPpmZmgUACGRvd25mYWxsPwAAAAgACGNhdGVnb3J5AANpY3kAAAgABG5hbWUAGW1pbmVjcmFmdDptdXNocm9vbV9maWVsZHMDAAJpZAAAAA4KAAdlbGVtZW50CAANcHJlY2lwaXRhdGlvbgAEcmFpbgoAB2VmZmVjdHMDAAlza3lfY29sb3IAd6j/AwAPd2F0ZXJfZm9nX2NvbG9yAAUFMwMACWZvZ19jb2xvcgDA2P8DAAt3YXRlcl9jb2xvcgA/duQKAAptb29kX3NvdW5kAwAKdGlja19kZWxheQAAF3AGAAZvZmZzZXRAAAAAAAAAAAgABXNvdW5kABZtaW5lY3JhZnQ6YW1iaWVudC5jYXZlAwATYmxvY2tfc2VhcmNoX2V4dGVudAAAAAgAAAUABWRlcHRoPkzMzQUAC3RlbXBlcmF0dXJlP2ZmZgUABXNjYWxlPpmZmgUACGRvd25mYWxsP4AAAAgACGNhdGVnb3J5AAhtdXNocm9vbQAACAAEbmFtZQAebWluZWNyYWZ0Om11c2hyb29tX2ZpZWxkX3Nob3JlAwACaWQAAAAPCgAHZWxlbWVudAgADXByZWNpcGl0YXRpb24ABHJhaW4KAAdlZmZlY3RzAwAJc2t5X2NvbG9yAHeo/wMAD3dhdGVyX2ZvZ19jb2xvcgAFBTMDAAlmb2dfY29sb3IAwNj/AwALd2F0ZXJfY29sb3IAP3bkCgAKbW9vZF9zb3VuZAMACnRpY2tfZGVsYXkAABdwBgAGb2Zmc2V0QAAAAAAAAAAIAAVzb3VuZAAWbWluZWNyYWZ0OmFtYmllbnQuY2F2ZQMAE2Jsb2NrX3NlYXJjaF9leHRlbnQAAAAIAAAFAAVkZXB0aAAAAAAFAAt0ZW1wZXJhdHVyZT9mZmYFAAVzY2FsZTzMzM0FAAhkb3duZmFsbD+AAAAIAAhjYXRlZ29yeQAIbXVzaHJvb20AAAgABG5hbWUAD21pbmVjcmFmdDpiZWFjaAMAAmlkAAAAEAoAB2VsZW1lbnQIAA1wcmVjaXBpdGF0aW9uAARyYWluCgAHZWZmZWN0cwMACXNreV9jb2xvcgB4p/8DAA93YXRlcl9mb2dfY29sb3IABQUzAwAJZm9nX2NvbG9yAMDY/wMAC3dhdGVyX2NvbG9yAD925AoACm1vb2Rfc291bmQDAAp0aWNrX2RlbGF5AAAXcAYABm9mZnNldEAAAAAAAAAACAAFc291bmQAFm1pbmVjcmFmdDphbWJpZW50LmNhdmUDABNibG9ja19zZWFyY2hfZXh0ZW50AAAACAAABQAFZGVwdGgAAAAABQALdGVtcGVyYXR1cmU/TMzNBQAFc2NhbGU8zMzNBQAIZG93bmZhbGw+zMzNCAAIY2F0ZWdvcnkABWJlYWNoAAAIAARuYW1lABZtaW5lY3JhZnQ6ZGVzZXJ0X2hpbGxzAwACaWQAAAARCgAHZWxlbWVudAgADXByZWNpcGl0YXRpb24ABG5vbmUKAAdlZmZlY3RzAwAJc2t5X2NvbG9yAG6x/wMAD3dhdGVyX2ZvZ19jb2xvcgAFBTMDAAlmb2dfY29sb3IAwNj/AwALd2F0ZXJfY29sb3IAP3bkCgAKbW9vZF9zb3VuZAMACnRpY2tfZGVsYXkAABdwBgAGb2Zmc2V0QAAAAAAAAAAIAAVzb3VuZAAWbWluZWNyYWZ0OmFtYmllbnQuY2F2ZQMAE2Jsb2NrX3NlYXJjaF9leHRlbnQAAAAIAAAFAAVkZXB0aD7mZmYFAAt0ZW1wZXJhdHVyZUAAAAAFAAVzY2FsZT6ZmZoFAAhkb3duZmFsbAAAAAAIAAhjYXRlZ29yeQAGZGVzZXJ0AAAIAARuYW1lABZtaW5lY3JhZnQ6d29vZGVkX2hpbGxzAwACaWQAAAASCgAHZWxlbWVudAgADXByZWNpcGl0YXRpb24ABHJhaW4KAAdlZmZlY3RzAwAJc2t5X2NvbG9yAHmm/wMAD3dhdGVyX2ZvZ19jb2xvcgAFBTMDAAlmb2dfY29sb3IAwNj/AwALd2F0ZXJfY29sb3IAP3bkCgAKbW9vZF9zb3VuZAMACnRpY2tfZGVsYXkAABdwBgAGb2Zmc2V0QAAAAAAAAAAIAAVzb3VuZAAWbWluZWNyYWZ0OmFtYmllbnQuY2F2ZQMAE2Jsb2NrX3NlYXJjaF9leHRlbnQAAAAIAAAFAAVkZXB0aD7mZmYFAAt0ZW1wZXJhdHVyZT8zMzMFAAVzY2FsZT6ZmZoFAAhkb3duZmFsbD9MzM0IAAhjYXRlZ29yeQAGZm9yZXN0AAAIAARuYW1lABVtaW5lY3JhZnQ6dGFpZ2FfaGlsbHMDAAJpZAAAABMKAAdlbGVtZW50CAANcHJlY2lwaXRhdGlvbgAEcmFpbgoAB2VmZmVjdHMDAAlza3lfY29sb3IAfaP/AwAPd2F0ZXJfZm9nX2NvbG9yAAUFMwMACWZvZ19jb2xvcgDA2P8DAAt3YXRlcl9jb2xvcgA/duQKAAptb29kX3NvdW5kAwAKdGlja19kZWxheQAAF3AGAAZvZmZzZXRAAAAAAAAAAAgABXNvdW5kABZtaW5lY3JhZnQ6YW1iaWVudC5jYXZlAwATYmxvY2tfc2VhcmNoX2V4dGVudAAAAAgAAAUABWRlcHRoPuZmZgUAC3RlbXBlcmF0dXJlPoAAAAUABXNjYWxlPpmZmgUACGRvd25mYWxsP0zMzQgACGNhdGVnb3J5AAV0YWlnYQAACAAEbmFtZQAXbWluZWNyYWZ0Om1vdW50YWluX2VkZ2UDAAJpZAAAABQKAAdlbGVtZW50CAANcHJlY2lwaXRhdGlvbgAEcmFpbgoAB2VmZmVjdHMDAAlza3lfY29sb3IAfaL/AwAPd2F0ZXJfZm9nX2NvbG9yAAUFMwMACWZvZ19jb2xvcgDA2P8DAAt3YXRlcl9jb2xvcgA/duQKAAptb29kX3NvdW5kAwAKdGlja19kZWxheQAAF3AGAAZvZmZzZXRAAAAAAAAAAAgABXNvdW5kABZtaW5lY3JhZnQ6YW1iaWVudC5jYXZlAwATYmxvY2tfc2VhcmNoX2V4dGVudAAAAAgAAAUABWRlcHRoP0zMzQUAC3RlbXBlcmF0dXJlPkzMzQUABXNjYWxlPpmZmgUACGRvd25mYWxsPpmZmggACGNhdGVnb3J5AA1leHRyZW1lX2hpbGxzAAAIAARuYW1lABBtaW5lY3JhZnQ6anVuZ2xlAwACaWQAAAAVCgAHZWxlbWVudAgADXByZWNpcGl0YXRpb24ABHJhaW4KAAdlZmZlY3RzAwAJc2t5X2NvbG9yAHeo/wMAD3dhdGVyX2ZvZ19jb2xvcgAFBTMDAAlmb2dfY29sb3IAwNj/AwALd2F0ZXJfY29sb3IAP3bkCgAKbW9vZF9zb3VuZAMACnRpY2tfZGVsYXkAABdwBgAGb2Zmc2V0QAAAAAAAAAAIAAVzb3VuZAAWbWluZWNyYWZ0OmFtYmllbnQuY2F2ZQMAE2Jsb2NrX3NlYXJjaF9leHRlbnQAAAAIAAAFAAVkZXB0aD3MzM0FAAt0ZW1wZXJhdHVyZT9zMzMFAAVzY2FsZT5MzM0FAAhkb3duZmFsbD9mZmYIAAhjYXRlZ29yeQAGanVuZ2xlAAAIAARuYW1lABZtaW5lY3JhZnQ6anVuZ2xlX2hpbGxzAwACaWQAAAAWCgAHZWxlbWVudAgADXByZWNpcGl0YXRpb24ABHJhaW4KAAdlZmZlY3RzAwAJc2t5X2NvbG9yAHeo/wMAD3dhdGVyX2ZvZ19jb2xvcgAFBTMDAAlmb2dfY29sb3IAwNj/AwALd2F0ZXJfY29sb3IAP3bkCgAKbW9vZF9zb3VuZAMACnRpY2tfZGVsYXkAABdwBgAGb2Zmc2V0QAAAAAAAAAAIAAVzb3VuZAAWbWluZWNyYWZ0OmFtYmllbnQuY2F2ZQMAE2Jsb2NrX3NlYXJjaF9leHRlbnQAAAAIAAAFAAVkZXB0aD7mZmYFAAt0ZW1wZXJhdHVyZT9zMzMFAAVzY2FsZT6ZmZoFAAhkb3duZmFsbD9mZmYIAAhjYXRlZ29yeQAGanVuZ2xlAAAIAARuYW1lABVtaW5lY3JhZnQ6anVuZ2xlX2VkZ2UDAAJpZAAAABcKAAdlbGVtZW50CAANcHJlY2lwaXRhdGlvbgAEcmFpbgoAB2VmZmVjdHMDAAlza3lfY29sb3IAd6j/AwAPd2F0ZXJfZm9nX2NvbG9yAAUFMwMACWZvZ19jb2xvcgDA2P8DAAt3YXRlcl9jb2xvcgA/duQKAAptb29kX3NvdW5kAwAKdGlja19kZWxheQAAF3AGAAZvZmZzZXRAAAAAAAAAAAgABXNvdW5kABZtaW5lY3JhZnQ6YW1iaWVudC5jYXZlAwATYmxvY2tfc2VhcmNoX2V4dGVudAAAAAgAAAUABWRlcHRoPczMzQUAC3RlbXBlcmF0dXJlP3MzMwUABXNjYWxlPkzMzQUACGRvd25mYWxsP0zMzQgACGNhdGVnb3J5AAZqdW5nbGUAAAgABG5hbWUAFG1pbmVjcmFmdDpkZWVwX29jZWFuAwACaWQAAAAYCgAHZWxlbWVudAgADXByZWNpcGl0YXRpb24ABHJhaW4KAAdlZmZlY3RzAwAJc2t5X2NvbG9yAHuk/wMAD3dhdGVyX2ZvZ19jb2xvcgAFBTMDAAlmb2dfY29sb3IAwNj/AwALd2F0ZXJfY29sb3IAP3bkCgAKbW9vZF9zb3VuZAMACnRpY2tfZGVsYXkAABdwBgAGb2Zmc2V0QAAAAAAAAAAIAAVzb3VuZAAWbWluZWNyYWZ0OmFtYmllbnQuY2F2ZQMAE2Jsb2NrX3NlYXJjaF9leHRlbnQAAAAIAAAFAAVkZXB0aL/mZmYFAAt0ZW1wZXJhdHVyZT8AAAAFAAVzY2FsZT3MzM0FAAhkb3duZmFsbD8AAAAIAAhjYXRlZ29yeQAFb2NlYW4AAAgABG5hbWUAFW1pbmVjcmFmdDpzdG9uZV9zaG9yZQMAAmlkAAAAGQoAB2VsZW1lbnQIAA1wcmVjaXBpdGF0aW9uAARyYWluCgAHZWZmZWN0cwMACXNreV9jb2xvcgB9ov8DAA93YXRlcl9mb2dfY29sb3IABQUzAwAJZm9nX2NvbG9yAMDY/wMAC3dhdGVyX2NvbG9yAD925AoACm1vb2Rfc291bmQDAAp0aWNrX2RlbGF5AAAXcAYABm9mZnNldEAAAAAAAAAACAAFc291bmQAFm1pbmVjcmFmdDphbWJpZW50LmNhdmUDABNibG9ja19zZWFyY2hfZXh0ZW50AAAACAAABQAFZGVwdGg9zMzNBQALdGVtcGVyYXR1cmU+TMzNBQAFc2NhbGU/TMzNBQAIZG93bmZhbGw+mZmaCAAIY2F0ZWdvcnkABG5vbmUAAAgABG5hbWUAFW1pbmVjcmFmdDpzbm93eV9iZWFjaAMAAmlkAAAAGgoAB2VsZW1lbnQIAA1wcmVjaXBpdGF0aW9uAARzbm93CgAHZWZmZWN0cwMACXNreV9jb2xvcgB/of8DAA93YXRlcl9mb2dfY29sb3IABQUzAwAJZm9nX2NvbG9yAMDY/wMAC3dhdGVyX2NvbG9yAD1X1goACm1vb2Rfc291bmQDAAp0aWNrX2RlbGF5AAAXcAYABm9mZnNldEAAAAAAAAAACAAFc291bmQAFm1pbmVjcmFmdDphbWJpZW50LmNhdmUDABNibG9ja19zZWFyY2hfZXh0ZW50AAAACAAABQAFZGVwdGgAAAAABQALdGVtcGVyYXR1cmU9TMzNBQAFc2NhbGU8zMzNBQAIZG93bmZhbGw+mZmaCAAIY2F0ZWdvcnkABWJlYWNoAAAIAARuYW1lABZtaW5lY3JhZnQ6YmlyY2hfZm9yZXN0AwACaWQAAAAbCgAHZWxlbWVudAgADXByZWNpcGl0YXRpb24ABHJhaW4KAAdlZmZlY3RzAwAJc2t5X2NvbG9yAHql/wMAD3dhdGVyX2ZvZ19jb2xvcgAFBTMDAAlmb2dfY29sb3IAwNj/AwALd2F0ZXJfY29sb3IAP3bkCgAKbW9vZF9zb3VuZAMACnRpY2tfZGVsYXkAABdwBgAGb2Zmc2V0QAAAAAAAAAAIAAVzb3VuZAAWbWluZWNyYWZ0OmFtYmllbnQuY2F2ZQMAE2Jsb2NrX3NlYXJjaF9leHRlbnQAAAAIAAAFAAVkZXB0aD3MzM0FAAt0ZW1wZXJhdHVyZT8ZmZoFAAVzY2FsZT5MzM0FAAhkb3duZmFsbD8ZmZoIAAhjYXRlZ29yeQAGZm9yZXN0AAAIAARuYW1lABxtaW5lY3JhZnQ6YmlyY2hfZm9yZXN0X2hpbGxzAwACaWQAAAAcCgAHZWxlbWVudAgADXByZWNpcGl0YXRpb24ABHJhaW4KAAdlZmZlY3RzAwAJc2t5X2NvbG9yAHql/wMAD3dhdGVyX2ZvZ19jb2xvcgAFBTMDAAlmb2dfY29sb3IAwNj/AwALd2F0ZXJfY29sb3IAP3bkCgAKbW9vZF9zb3VuZAMACnRpY2tfZGVsYXkAABdwBgAGb2Zmc2V0QAAAAAAAAAAIAAVzb3VuZAAWbWluZWNyYWZ0OmFtYmllbnQuY2F2ZQMAE2Jsb2NrX3NlYXJjaF9leHRlbnQAAAAIAAAFAAVkZXB0aD7mZmYFAAt0ZW1wZXJhdHVyZT8ZmZoFAAVzY2FsZT6ZmZoFAAhkb3duZmFsbD8ZmZoIAAhjYXRlZ29yeQAGZm9yZXN0AAAIAARuYW1lABVtaW5lY3JhZnQ6ZGFya19mb3Jlc3QDAAJpZAAAAB0KAAdlbGVtZW50CAANcHJlY2lwaXRhdGlvbgAEcmFpbgoAB2VmZmVjdHMIABRncmFzc19jb2xvcl9tb2RpZmllcgALZGFya19mb3Jlc3QDAAlza3lfY29sb3IAeab/AwAPd2F0ZXJfZm9nX2NvbG9yAAUFMwMACWZvZ19jb2xvcgDA2P8DAAt3YXRlcl9jb2xvcgA/duQKAAptb29kX3NvdW5kAwAKdGlja19kZWxheQAAF3AGAAZvZmZzZXRAAAAAAAAAAAgABXNvdW5kABZtaW5lY3JhZnQ6YW1iaWVudC5jYXZlAwATYmxvY2tfc2VhcmNoX2V4dGVudAAAAAgAAAUABWRlcHRoPczMzQUAC3RlbXBlcmF0dXJlPzMzMwUABXNjYWxlPkzMzQUACGRvd25mYWxsP0zMzQgACGNhdGVnb3J5AAZmb3Jlc3QAAAgABG5hbWUAFW1pbmVjcmFmdDpzbm93eV90YWlnYQMAAmlkAAAAHgoAB2VsZW1lbnQIAA1wcmVjaXBpdGF0aW9uAARzbm93CgAHZWZmZWN0cwMACXNreV9jb2xvcgCDnv8DAA93YXRlcl9mb2dfY29sb3IABQUzAwAJZm9nX2NvbG9yAMDY/wMAC3dhdGVyX2NvbG9yAD1X1goACm1vb2Rfc291bmQDAAp0aWNrX2RlbGF5AAAXcAYABm9mZnNldEAAAAAAAAAACAAFc291bmQAFm1pbmVjcmFmdDphbWJpZW50LmNhdmUDABNibG9ja19zZWFyY2hfZXh0ZW50AAAACAAABQAFZGVwdGg+TMzNBQALdGVtcGVyYXR1cmW/AAAABQAFc2NhbGU+TMzNBQAIZG93bmZhbGw+zMzNCAAIY2F0ZWdvcnkABXRhaWdhAAAIAARuYW1lABttaW5lY3JhZnQ6c25vd3lfdGFpZ2FfaGlsbHMDAAJpZAAAAB8KAAdlbGVtZW50CAANcHJlY2lwaXRhdGlvbgAEc25vdwoAB2VmZmVjdHMDAAlza3lfY29sb3IAg57/AwAPd2F0ZXJfZm9nX2NvbG9yAAUFMwMACWZvZ19jb2xvcgDA2P8DAAt3YXRlcl9jb2xvcgA9V9YKAAptb29kX3NvdW5kAwAKdGlja19kZWxheQAAF3AGAAZvZmZzZXRAAAAAAAAAAAgABXNvdW5kABZtaW5lY3JhZnQ6YW1iaWVudC5jYXZlAwATYmxvY2tfc2VhcmNoX2V4dGVudAAAAAgAAAUABWRlcHRoPuZmZgUAC3RlbXBlcmF0dXJlvwAAAAUABXNjYWxlPpmZmgUACGRvd25mYWxsPszMzQgACGNhdGVnb3J5AAV0YWlnYQAACAAEbmFtZQAabWluZWNyYWZ0OmdpYW50X3RyZWVfdGFpZ2EDAAJpZAAAACAKAAdlbGVtZW50CAANcHJlY2lwaXRhdGlvbgAEcmFpbgoAB2VmZmVjdHMDAAlza3lfY29sb3IAfKP/AwAPd2F0ZXJfZm9nX2NvbG9yAAUFMwMACWZvZ19jb2xvcgDA2P8DAAt3YXRlcl9jb2xvcgA/duQKAAptb29kX3NvdW5kAwAKdGlja19kZWxheQAAF3AGAAZvZmZzZXRAAAAAAAAAAAgABXNvdW5kABZtaW5lY3JhZnQ6YW1iaWVudC5jYXZlAwATYmxvY2tfc2VhcmNoX2V4dGVudAAAAAgAAAUABWRlcHRoPkzMzQUAC3RlbXBlcmF0dXJlPpmZmgUABXNjYWxlPkzMzQUACGRvd25mYWxsP0zMzQgACGNhdGVnb3J5AAV0YWlnYQAACAAEbmFtZQAgbWluZWNyYWZ0OmdpYW50X3RyZWVfdGFpZ2FfaGlsbHMDAAJpZAAAACEKAAdlbGVtZW50CAANcHJlY2lwaXRhdGlvbgAEcmFpbgoAB2VmZmVjdHMDAAlza3lfY29sb3IAfKP/AwAPd2F0ZXJfZm9nX2NvbG9yAAUFMwMACWZvZ19jb2xvcgDA2P8DAAt3YXRlcl9jb2xvcgA/duQKAAptb29kX3NvdW5kAwAKdGlja19kZWxheQAAF3AGAAZvZmZzZXRAAAAAAAAAAAgABXNvdW5kABZtaW5lY3JhZnQ6YW1iaWVudC5jYXZlAwATYmxvY2tfc2VhcmNoX2V4dGVudAAAAAgAAAUABWRlcHRoPuZmZgUAC3RlbXBlcmF0dXJlPpmZmgUABXNjYWxlPpmZmgUACGRvd25mYWxsP0zMzQgACGNhdGVnb3J5AAV0YWlnYQAACAAEbmFtZQAabWluZWNyYWZ0Ondvb2RlZF9tb3VudGFpbnMDAAJpZAAAACIKAAdlbGVtZW50CAANcHJlY2lwaXRhdGlvbgAEcmFpbgoAB2VmZmVjdHMDAAlza3lfY29sb3IAfaL/AwAPd2F0ZXJfZm9nX2NvbG9yAAUFMwMACWZvZ19jb2xvcgDA2P8DAAt3YXRlcl9jb2xvcgA/duQKAAptb29kX3NvdW5kAwAKdGlja19kZWxheQAAF3AGAAZvZmZzZXRAAAAAAAAAAAgABXNvdW5kABZtaW5lY3JhZnQ6YW1iaWVudC5jYXZlAwATYmxvY2tfc2VhcmNoX2V4dGVudAAAAAgAAAUABWRlcHRoP4AAAAUAC3RlbXBlcmF0dXJlPkzMzQUABXNjYWxlPwAAAAUACGRvd25mYWxsPpmZmggACGNhdGVnb3J5AA1leHRyZW1lX2hpbGxzAAAIAARuYW1lABFtaW5lY3JhZnQ6c2F2YW5uYQMAAmlkAAAAIwoAB2VsZW1lbnQIAA1wcmVjaXBpdGF0aW9uAARub25lCgAHZWZmZWN0cwMACXNreV9jb2xvcgB1qv8DAA93YXRlcl9mb2dfY29sb3IABQUzAwAJZm9nX2NvbG9yAMDY/wMAC3dhdGVyX2NvbG9yAD925AoACm1vb2Rfc291bmQDAAp0aWNrX2RlbGF5AAAXcAYABm9mZnNldEAAAAAAAAAACAAFc291bmQAFm1pbmVjcmFmdDphbWJpZW50LmNhdmUDABNibG9ja19zZWFyY2hfZXh0ZW50AAAACAAABQAFZGVwdGg+AAAABQALdGVtcGVyYXR1cmU/mZmaBQAFc2NhbGU9TMzNBQAIZG93bmZhbGwAAAAACAAIY2F0ZWdvcnkAB3NhdmFubmEAAAgABG5hbWUAGW1pbmVjcmFmdDpzYXZhbm5hX3BsYXRlYXUDAAJpZAAAACQKAAdlbGVtZW50CAANcHJlY2lwaXRhdGlvbgAEbm9uZQoAB2VmZmVjdHMDAAlza3lfY29sb3IAdqj/AwAPd2F0ZXJfZm9nX2NvbG9yAAUFMwMACWZvZ19jb2xvcgDA2P8DAAt3YXRlcl9jb2xvcgA/duQKAAptb29kX3NvdW5kAwAKdGlja19kZWxheQAAF3AGAAZvZmZzZXRAAAAAAAAAAAgABXNvdW5kABZtaW5lY3JhZnQ6YW1iaWVudC5jYXZlAwATYmxvY2tfc2VhcmNoX2V4dGVudAAAAAgAAAUABWRlcHRoP8AAAAUAC3RlbXBlcmF0dXJlP4AAAAUABXNjYWxlPMzMzQUACGRvd25mYWxsAAAAAAgACGNhdGVnb3J5AAdzYXZhbm5hAAAIAARuYW1lABJtaW5lY3JhZnQ6YmFkbGFuZHMDAAJpZAAAACUKAAdlbGVtZW50CAANcHJlY2lwaXRhdGlvbgAEbm9uZQoAB2VmZmVjdHMDAAlza3lfY29sb3IAbrH/AwALZ3Jhc3NfY29sb3IAkIFNAwANZm9saWFnZV9jb2xvcgCegU0DAA93YXRlcl9mb2dfY29sb3IABQUzAwAJZm9nX2NvbG9yAMDY/wMAC3dhdGVyX2NvbG9yAD925AoACm1vb2Rfc291bmQDAAp0aWNrX2RlbGF5AAAXcAYABm9mZnNldEAAAAAAAAAACAAFc291bmQAFm1pbmVjcmFmdDphbWJpZW50LmNhdmUDABNibG9ja19zZWFyY2hfZXh0ZW50AAAACAAABQAFZGVwdGg9zMzNBQALdGVtcGVyYXR1cmVAAAAABQAFc2NhbGU+TMzNBQAIZG93bmZhbGwAAAAACAAIY2F0ZWdvcnkABG1lc2EAAAgABG5hbWUAIW1pbmVjcmFmdDp3b29kZWRfYmFkbGFuZHNfcGxhdGVhdQMAAmlkAAAAJgoAB2VsZW1lbnQIAA1wcmVjaXBpdGF0aW9uAARub25lCgAHZWZmZWN0cwMACXNreV9jb2xvcgBusf8DAAtncmFzc19jb2xvcgCQgU0DAA1mb2xpYWdlX2NvbG9yAJ6BTQMAD3dhdGVyX2ZvZ19jb2xvcgAFBTMDAAlmb2dfY29sb3IAwNj/AwALd2F0ZXJfY29sb3IAP3bkCgAKbW9vZF9zb3VuZAMACnRpY2tfZGVsYXkAABdwBgAGb2Zmc2V0QAAAAAAAAAAIAAVzb3VuZAAWbWluZWNyYWZ0OmFtYmllbnQuY2F2ZQMAE2Jsb2NrX3NlYXJjaF9leHRlbnQAAAAIAAAFAAVkZXB0aD/AAAAFAAt0ZW1wZXJhdHVyZUAAAAAFAAVzY2FsZTzMzM0FAAhkb3duZmFsbAAAAAAIAAhjYXRlZ29yeQAEbWVzYQAACAAEbmFtZQAabWluZWNyYWZ0OmJhZGxhbmRzX3BsYXRlYXUDAAJpZAAAACcKAAdlbGVtZW50CAANcHJlY2lwaXRhdGlvbgAEbm9uZQoAB2VmZmVjdHMDAAlza3lfY29sb3IAbrH/AwALZ3Jhc3NfY29sb3IAkIFNAwANZm9saWFnZV9jb2xvcgCegU0DAA93YXRlcl9mb2dfY29sb3IABQUzAwAJZm9nX2NvbG9yAMDY/wMAC3dhdGVyX2NvbG9yAD925AoACm1vb2Rfc291bmQDAAp0aWNrX2RlbGF5AAAXcAYABm9mZnNldEAAAAAAAAAACAAFc291bmQAFm1pbmVjcmFmdDphbWJpZW50LmNhdmUDABNibG9ja19zZWFyY2hfZXh0ZW50AAAACAAABQAFZGVwdGg/wAAABQALdGVtcGVyYXR1cmVAAAAABQAFc2NhbGU8zMzNBQAIZG93bmZhbGwAAAAACAAIY2F0ZWdvcnkABG1lc2EAAAgABG5hbWUAG21pbmVjcmFmdDpzbWFsbF9lbmRfaXNsYW5kcwMAAmlkAAAAKAoAB2VsZW1lbnQIAA1wcmVjaXBpdGF0aW9uAARub25lCgAHZWZmZWN0cwMACXNreV9jb2xvcgAAAAADAA93YXRlcl9mb2dfY29sb3IABQUzAwAJZm9nX2NvbG9yAKCAoAMAC3dhdGVyX2NvbG9yAD925AoACm1vb2Rfc291bmQDAAp0aWNrX2RlbGF5AAAXcAYABm9mZnNldEAAAAAAAAAACAAFc291bmQAFm1pbmVjcmFmdDphbWJpZW50LmNhdmUDABNibG9ja19zZWFyY2hfZXh0ZW50AAAACAAABQAFZGVwdGg9zMzNBQALdGVtcGVyYXR1cmU/AAAABQAFc2NhbGU+TMzNBQAIZG93bmZhbGw/AAAACAAIY2F0ZWdvcnkAB3RoZV9lbmQAAAgABG5hbWUAFm1pbmVjcmFmdDplbmRfbWlkbGFuZHMDAAJpZAAAACkKAAdlbGVtZW50CAANcHJlY2lwaXRhdGlvbgAEbm9uZQoAB2VmZmVjdHMDAAlza3lfY29sb3IAAAAAAwAPd2F0ZXJfZm9nX2NvbG9yAAUFMwMACWZvZ19jb2xvcgCggKADAAt3YXRlcl9jb2xvcgA/duQKAAptb29kX3NvdW5kAwAKdGlja19kZWxheQAAF3AGAAZvZmZzZXRAAAAAAAAAAAgABXNvdW5kABZtaW5lY3JhZnQ6YW1iaWVudC5jYXZlAwATYmxvY2tfc2VhcmNoX2V4dGVudAAAAAgAAAUABWRlcHRoPczMzQUAC3RlbXBlcmF0dXJlPwAAAAUABXNjYWxlPkzMzQUACGRvd25mYWxsPwAAAAgACGNhdGVnb3J5AAd0aGVfZW5kAAAIAARuYW1lABdtaW5lY3JhZnQ6ZW5kX2hpZ2hsYW5kcwMAAmlkAAAAKgoAB2VsZW1lbnQIAA1wcmVjaXBpdGF0aW9uAARub25lCgAHZWZmZWN0cwMACXNreV9jb2xvcgAAAAADAA93YXRlcl9mb2dfY29sb3IABQUzAwAJZm9nX2NvbG9yAKCAoAMAC3dhdGVyX2NvbG9yAD925AoACm1vb2Rfc291bmQDAAp0aWNrX2RlbGF5AAAXcAYABm9mZnNldEAAAAAAAAAACAAFc291bmQAFm1pbmVjcmFmdDphbWJpZW50LmNhdmUDABNibG9ja19zZWFyY2hfZXh0ZW50AAAACAAABQAFZGVwdGg9zMzNBQALdGVtcGVyYXR1cmU/AAAABQAFc2NhbGU+TMzNBQAIZG93bmZhbGw/AAAACAAIY2F0ZWdvcnkAB3RoZV9lbmQAAAgABG5hbWUAFW1pbmVjcmFmdDplbmRfYmFycmVucwMAAmlkAAAAKwoAB2VsZW1lbnQIAA1wcmVjaXBpdGF0aW9uAARub25lCgAHZWZmZWN0cwMACXNreV9jb2xvcgAAAAADAA93YXRlcl9mb2dfY29sb3IABQUzAwAJZm9nX2NvbG9yAKCAoAMAC3dhdGVyX2NvbG9yAD925AoACm1vb2Rfc291bmQDAAp0aWNrX2RlbGF5AAAXcAYABm9mZnNldEAAAAAAAAAACAAFc291bmQAFm1pbmVjcmFmdDphbWJpZW50LmNhdmUDABNibG9ja19zZWFyY2hfZXh0ZW50AAAACAAABQAFZGVwdGg9zMzNBQALdGVtcGVyYXR1cmU/AAAABQAFc2NhbGU+TMzNBQAIZG93bmZhbGw/AAAACAAIY2F0ZWdvcnkAB3RoZV9lbmQAAAgABG5hbWUAFG1pbmVjcmFmdDp3YXJtX29jZWFuAwACaWQAAAAsCgAHZWxlbWVudAgADXByZWNpcGl0YXRpb24ABHJhaW4KAAdlZmZlY3RzAwAJc2t5X2NvbG9yAHuk/wMAD3dhdGVyX2ZvZ19jb2xvcgAEHzMDAAlmb2dfY29sb3IAwNj/AwALd2F0ZXJfY29sb3IAQ9XuCgAKbW9vZF9zb3VuZAMACnRpY2tfZGVsYXkAABdwBgAGb2Zmc2V0QAAAAAAAAAAIAAVzb3VuZAAWbWluZWNyYWZ0OmFtYmllbnQuY2F2ZQMAE2Jsb2NrX3NlYXJjaF9leHRlbnQAAAAIAAAFAAVkZXB0aL+AAAAFAAt0ZW1wZXJhdHVyZT8AAAAFAAVzY2FsZT3MzM0FAAhkb3duZmFsbD8AAAAIAAhjYXRlZ29yeQAFb2NlYW4AAAgABG5hbWUAGG1pbmVjcmFmdDpsdWtld2FybV9vY2VhbgMAAmlkAAAALQoAB2VsZW1lbnQIAA1wcmVjaXBpdGF0aW9uAARyYWluCgAHZWZmZWN0cwMACXNreV9jb2xvcgB7pP8DAA93YXRlcl9mb2dfY29sb3IABBYzAwAJZm9nX2NvbG9yAMDY/wMAC3dhdGVyX2NvbG9yAEWt8goACm1vb2Rfc291bmQDAAp0aWNrX2RlbGF5AAAXcAYABm9mZnNldEAAAAAAAAAACAAFc291bmQAFm1pbmVjcmFmdDphbWJpZW50LmNhdmUDABNibG9ja19zZWFyY2hfZXh0ZW50AAAACAAABQAFZGVwdGi/gAAABQALdGVtcGVyYXR1cmU/AAAABQAFc2NhbGU9zMzNBQAIZG93bmZhbGw/AAAACAAIY2F0ZWdvcnkABW9jZWFuAAAIAARuYW1lABRtaW5lY3JhZnQ6Y29sZF9vY2VhbgMAAmlkAAAALgoAB2VsZW1lbnQIAA1wcmVjaXBpdGF0aW9uAARyYWluCgAHZWZmZWN0cwMACXNreV9jb2xvcgB7pP8DAA93YXRlcl9mb2dfY29sb3IABQUzAwAJZm9nX2NvbG9yAMDY/wMAC3dhdGVyX2NvbG9yAD1X1goACm1vb2Rfc291bmQDAAp0aWNrX2RlbGF5AAAXcAYABm9mZnNldEAAAAAAAAAACAAFc291bmQAFm1pbmVjcmFmdDphbWJpZW50LmNhdmUDABNibG9ja19zZWFyY2hfZXh0ZW50AAAACAAABQAFZGVwdGi/gAAABQALdGVtcGVyYXR1cmU/AAAABQAFc2NhbGU9zMzNBQAIZG93bmZhbGw/AAAACAAIY2F0ZWdvcnkABW9jZWFuAAAIAARuYW1lABltaW5lY3JhZnQ6ZGVlcF93YXJtX29jZWFuAwACaWQAAAAvCgAHZWxlbWVudAgADXByZWNpcGl0YXRpb24ABHJhaW4KAAdlZmZlY3RzAwAJc2t5X2NvbG9yAHuk/wMAD3dhdGVyX2ZvZ19jb2xvcgAEHzMDAAlmb2dfY29sb3IAwNj/AwALd2F0ZXJfY29sb3IAQ9XuCgAKbW9vZF9zb3VuZAMACnRpY2tfZGVsYXkAABdwBgAGb2Zmc2V0QAAAAAAAAAAIAAVzb3VuZAAWbWluZWNyYWZ0OmFtYmllbnQuY2F2ZQMAE2Jsb2NrX3NlYXJjaF9leHRlbnQAAAAIAAAFAAVkZXB0aL/mZmYFAAt0ZW1wZXJhdHVyZT8AAAAFAAVzY2FsZT3MzM0FAAhkb3duZmFsbD8AAAAIAAhjYXRlZ29yeQAFb2NlYW4AAAgABG5hbWUAHW1pbmVjcmFmdDpkZWVwX2x1a2V3YXJtX29jZWFuAwACaWQAAAAwCgAHZWxlbWVudAgADXByZWNpcGl0YXRpb24ABHJhaW4KAAdlZmZlY3RzAwAJc2t5X2NvbG9yAHuk/wMAD3dhdGVyX2ZvZ19jb2xvcgAEFjMDAAlmb2dfY29sb3IAwNj/AwALd2F0ZXJfY29sb3IARa3yCgAKbW9vZF9zb3VuZAMACnRpY2tfZGVsYXkAABdwBgAGb2Zmc2V0QAAAAAAAAAAIAAVzb3VuZAAWbWluZWNyYWZ0OmFtYmllbnQuY2F2ZQMAE2Jsb2NrX3NlYXJjaF9leHRlbnQAAAAIAAAFAAVkZXB0aL/mZmYFAAt0ZW1wZXJhdHVyZT8AAAAFAAVzY2FsZT3MzM0FAAhkb3duZmFsbD8AAAAIAAhjYXRlZ29yeQAFb2NlYW4AAAgABG5hbWUAGW1pbmVjcmFmdDpkZWVwX2NvbGRfb2NlYW4DAAJpZAAAADEKAAdlbGVtZW50CAANcHJlY2lwaXRhdGlvbgAEcmFpbgoAB2VmZmVjdHMDAAlza3lfY29sb3IAe6T/AwAPd2F0ZXJfZm9nX2NvbG9yAAUFMwMACWZvZ19jb2xvcgDA2P8DAAt3YXRlcl9jb2xvcgA9V9YKAAptb29kX3NvdW5kAwAKdGlja19kZWxheQAAF3AGAAZvZmZzZXRAAAAAAAAAAAgABXNvdW5kABZtaW5lY3JhZnQ6YW1iaWVudC5jYXZlAwATYmxvY2tfc2VhcmNoX2V4dGVudAAAAAgAAAUABWRlcHRov+ZmZgUAC3RlbXBlcmF0dXJlPwAAAAUABXNjYWxlPczMzQUACGRvd25mYWxsPwAAAAgACGNhdGVnb3J5AAVvY2VhbgAACAAEbmFtZQAbbWluZWNyYWZ0OmRlZXBfZnJvemVuX29jZWFuAwACaWQAAAAyCgAHZWxlbWVudAgADXByZWNpcGl0YXRpb24ABHJhaW4KAAdlZmZlY3RzAwAJc2t5X2NvbG9yAHuk/wMAD3dhdGVyX2ZvZ19jb2xvcgAFBTMDAAlmb2dfY29sb3IAwNj/AwALd2F0ZXJfY29sb3IAOTjJCgAKbW9vZF9zb3VuZAMACnRpY2tfZGVsYXkAABdwBgAGb2Zmc2V0QAAAAAAAAAAIAAVzb3VuZAAWbWluZWNyYWZ0OmFtYmllbnQuY2F2ZQMAE2Jsb2NrX3NlYXJjaF9leHRlbnQAAAAIAAAFAAVkZXB0aL/mZmYFAAt0ZW1wZXJhdHVyZT8AAAAFAAVzY2FsZT3MzM0FAAhkb3duZmFsbD8AAAAIAAhjYXRlZ29yeQAFb2NlYW4IABR0ZW1wZXJhdHVyZV9tb2RpZmllcgAGZnJvemVuAAAIAARuYW1lABJtaW5lY3JhZnQ6dGhlX3ZvaWQDAAJpZAAAAH8KAAdlbGVtZW50CAANcHJlY2lwaXRhdGlvbgAEbm9uZQoAB2VmZmVjdHMDAAlza3lfY29sb3IAe6T/AwAPd2F0ZXJfZm9nX2NvbG9yAAUFMwMACWZvZ19jb2xvcgDA2P8DAAt3YXRlcl9jb2xvcgA/duQKAAptb29kX3NvdW5kAwAKdGlja19kZWxheQAAF3AGAAZvZmZzZXRAAAAAAAAAAAgABXNvdW5kABZtaW5lY3JhZnQ6YW1iaWVudC5jYXZlAwATYmxvY2tfc2VhcmNoX2V4dGVudAAAAAgAAAUABWRlcHRoPczMzQUAC3RlbXBlcmF0dXJlPwAAAAUABXNjYWxlPkzMzQUACGRvd25mYWxsPwAAAAgACGNhdGVnb3J5AARub25lAAAIAARuYW1lABptaW5lY3JhZnQ6c3VuZmxvd2VyX3BsYWlucwMAAmlkAAAAgQoAB2VsZW1lbnQIAA1wcmVjaXBpdGF0aW9uAARyYWluCgAHZWZmZWN0cwMACXNreV9jb2xvcgB4p/8DAA93YXRlcl9mb2dfY29sb3IABQUzAwAJZm9nX2NvbG9yAMDY/wMAC3dhdGVyX2NvbG9yAD925AoACm1vb2Rfc291bmQDAAp0aWNrX2RlbGF5AAAXcAYABm9mZnNldEAAAAAAAAAACAAFc291bmQAFm1pbmVjcmFmdDphbWJpZW50LmNhdmUDABNibG9ja19zZWFyY2hfZXh0ZW50AAAACAAABQAFZGVwdGg+AAAABQALdGVtcGVyYXR1cmU/TMzNBQAFc2NhbGU9TMzNBQAIZG93bmZhbGw+zMzNCAAIY2F0ZWdvcnkABnBsYWlucwAACAAEbmFtZQAWbWluZWNyYWZ0OmRlc2VydF9sYWtlcwMAAmlkAAAAggoAB2VsZW1lbnQIAA1wcmVjaXBpdGF0aW9uAARub25lCgAHZWZmZWN0cwMACXNreV9jb2xvcgBusf8DAA93YXRlcl9mb2dfY29sb3IABQUzAwAJZm9nX2NvbG9yAMDY/wMAC3dhdGVyX2NvbG9yAD925AoACm1vb2Rfc291bmQDAAp0aWNrX2RlbGF5AAAXcAYABm9mZnNldEAAAAAAAAAACAAFc291bmQAFm1pbmVjcmFmdDphbWJpZW50LmNhdmUDABNibG9ja19zZWFyY2hfZXh0ZW50AAAACAAABQAFZGVwdGg+ZmZmBQALdGVtcGVyYXR1cmVAAAAABQAFc2NhbGU+gAAABQAIZG93bmZhbGwAAAAACAAIY2F0ZWdvcnkABmRlc2VydAAACAAEbmFtZQAcbWluZWNyYWZ0OmdyYXZlbGx5X21vdW50YWlucwMAAmlkAAAAgwoAB2VsZW1lbnQIAA1wcmVjaXBpdGF0aW9uAARyYWluCgAHZWZmZWN0cwMACXNreV9jb2xvcgB9ov8DAA93YXRlcl9mb2dfY29sb3IABQUzAwAJZm9nX2NvbG9yAMDY/wMAC3dhdGVyX2NvbG9yAD925AoACm1vb2Rfc291bmQDAAp0aWNrX2RlbGF5AAAXcAYABm9mZnNldEAAAAAAAAAACAAFc291bmQAFm1pbmVjcmFmdDphbWJpZW50LmNhdmUDABNibG9ja19zZWFyY2hfZXh0ZW50AAAACAAABQAFZGVwdGg/gAAABQALdGVtcGVyYXR1cmU+TMzNBQAFc2NhbGU/AAAABQAIZG93bmZhbGw+mZmaCAAIY2F0ZWdvcnkADWV4dHJlbWVfaGlsbHMAAAgABG5hbWUAF21pbmVjcmFmdDpmbG93ZXJfZm9yZXN0AwACaWQAAACECgAHZWxlbWVudAgADXByZWNpcGl0YXRpb24ABHJhaW4KAAdlZmZlY3RzAwAJc2t5X2NvbG9yAHmm/wMAD3dhdGVyX2ZvZ19jb2xvcgAFBTMDAAlmb2dfY29sb3IAwNj/AwALd2F0ZXJfY29sb3IAP3bkCgAKbW9vZF9zb3VuZAMACnRpY2tfZGVsYXkAABdwBgAGb2Zmc2V0QAAAAAAAAAAIAAVzb3VuZAAWbWluZWNyYWZ0OmFtYmllbnQuY2F2ZQMAE2Jsb2NrX3NlYXJjaF9leHRlbnQAAAAIAAAFAAVkZXB0aD3MzM0FAAt0ZW1wZXJhdHVyZT8zMzMFAAVzY2FsZT7MzM0FAAhkb3duZmFsbD9MzM0IAAhjYXRlZ29yeQAGZm9yZXN0AAAIAARuYW1lABltaW5lY3JhZnQ6dGFpZ2FfbW91bnRhaW5zAwACaWQAAACFCgAHZWxlbWVudAgADXByZWNpcGl0YXRpb24ABHJhaW4KAAdlZmZlY3RzAwAJc2t5X2NvbG9yAH2j/wMAD3dhdGVyX2ZvZ19jb2xvcgAFBTMDAAlmb2dfY29sb3IAwNj/AwALd2F0ZXJfY29sb3IAP3bkCgAKbW9vZF9zb3VuZAMACnRpY2tfZGVsYXkAABdwBgAGb2Zmc2V0QAAAAAAAAAAIAAVzb3VuZAAWbWluZWNyYWZ0OmFtYmllbnQuY2F2ZQMAE2Jsb2NrX3NlYXJjaF9leHRlbnQAAAAIAAAFAAVkZXB0aD6ZmZoFAAt0ZW1wZXJhdHVyZT6AAAAFAAVzY2FsZT7MzM0FAAhkb3duZmFsbD9MzM0IAAhjYXRlZ29yeQAFdGFpZ2EAAAgABG5hbWUAFW1pbmVjcmFmdDpzd2FtcF9oaWxscwMAAmlkAAAAhgoAB2VsZW1lbnQIAA1wcmVjaXBpdGF0aW9uAARyYWluCgAHZWZmZWN0cwgAFGdyYXNzX2NvbG9yX21vZGlmaWVyAAVzd2FtcAMACXNreV9jb2xvcgB4p/8DAA1mb2xpYWdlX2NvbG9yAGpwOQMAD3dhdGVyX2ZvZ19jb2xvcgAjIxcDAAlmb2dfY29sb3IAwNj/AwALd2F0ZXJfY29sb3IAYXtkCgAKbW9vZF9zb3VuZAMACnRpY2tfZGVsYXkAABdwBgAGb2Zmc2V0QAAAAAAAAAAIAAVzb3VuZAAWbWluZWNyYWZ0OmFtYmllbnQuY2F2ZQMAE2Jsb2NrX3NlYXJjaF9leHRlbnQAAAAIAAAFAAVkZXB0aL3MzM0FAAt0ZW1wZXJhdHVyZT9MzM0FAAVzY2FsZT6ZmZoFAAhkb3duZmFsbD9mZmYIAAhjYXRlZ29yeQAFc3dhbXAAAAgABG5hbWUAFG1pbmVjcmFmdDppY2Vfc3Bpa2VzAwACaWQAAACMCgAHZWxlbWVudAgADXByZWNpcGl0YXRpb24ABHNub3cKAAdlZmZlY3RzAwAJc2t5X2NvbG9yAH+h/wMAD3dhdGVyX2ZvZ19jb2xvcgAFBTMDAAlmb2dfY29sb3IAwNj/AwALd2F0ZXJfY29sb3IAP3bkCgAKbW9vZF9zb3VuZAMACnRpY2tfZGVsYXkAABdwBgAGb2Zmc2V0QAAAAAAAAAAIAAVzb3VuZAAWbWluZWNyYWZ0OmFtYmllbnQuY2F2ZQMAE2Jsb2NrX3NlYXJjaF9leHRlbnQAAAAIAAAFAAVkZXB0aD7ZmZoFAAt0ZW1wZXJhdHVyZQAAAAAFAAVzY2FsZT7mZmcFAAhkb3duZmFsbD8AAAAIAAhjYXRlZ29yeQADaWN5AAAIAARuYW1lABltaW5lY3JhZnQ6bW9kaWZpZWRfanVuZ2xlAwACaWQAAACVCgAHZWxlbWVudAgADXByZWNpcGl0YXRpb24ABHJhaW4KAAdlZmZlY3RzAwAJc2t5X2NvbG9yAHeo/wMAD3dhdGVyX2ZvZ19jb2xvcgAFBTMDAAlmb2dfY29sb3IAwNj/AwALd2F0ZXJfY29sb3IAP3bkCgAKbW9vZF9zb3VuZAMACnRpY2tfZGVsYXkAABdwBgAGb2Zmc2V0QAAAAAAAAAAIAAVzb3VuZAAWbWluZWNyYWZ0OmFtYmllbnQuY2F2ZQMAE2Jsb2NrX3NlYXJjaF9leHRlbnQAAAAIAAAFAAVkZXB0aD5MzM0FAAt0ZW1wZXJhdHVyZT9zMzMFAAVzY2FsZT7MzM0FAAhkb3duZmFsbD9mZmYIAAhjYXRlZ29yeQAGanVuZ2xlAAAIAARuYW1lAB5taW5lY3JhZnQ6bW9kaWZpZWRfanVuZ2xlX2VkZ2UDAAJpZAAAAJcKAAdlbGVtZW50CAANcHJlY2lwaXRhdGlvbgAEcmFpbgoAB2VmZmVjdHMDAAlza3lfY29sb3IAd6j/AwAPd2F0ZXJfZm9nX2NvbG9yAAUFMwMACWZvZ19jb2xvcgDA2P8DAAt3YXRlcl9jb2xvcgA/duQKAAptb29kX3NvdW5kAwAKdGlja19kZWxheQAAF3AGAAZvZmZzZXRAAAAAAAAAAAgABXNvdW5kABZtaW5lY3JhZnQ6YW1iaWVudC5jYXZlAwATYmxvY2tfc2VhcmNoX2V4dGVudAAAAAgAAAUABWRlcHRoPkzMzQUAC3RlbXBlcmF0dXJlP3MzMwUABXNjYWxlPszMzQUACGRvd25mYWxsP0zMzQgACGNhdGVnb3J5AAZqdW5nbGUAAAgABG5hbWUAG21pbmVjcmFmdDp0YWxsX2JpcmNoX2ZvcmVzdAMAAmlkAAAAmwoAB2VsZW1lbnQIAA1wcmVjaXBpdGF0aW9uAARyYWluCgAHZWZmZWN0cwMACXNreV9jb2xvcgB6pf8DAA93YXRlcl9mb2dfY29sb3IABQUzAwAJZm9nX2NvbG9yAMDY/wMAC3dhdGVyX2NvbG9yAD925AoACm1vb2Rfc291bmQDAAp0aWNrX2RlbGF5AAAXcAYABm9mZnNldEAAAAAAAAAACAAFc291bmQAFm1pbmVjcmFmdDphbWJpZW50LmNhdmUDABNibG9ja19zZWFyY2hfZXh0ZW50AAAACAAABQAFZGVwdGg+TMzNBQALdGVtcGVyYXR1cmU/GZmaBQAFc2NhbGU+zMzNBQAIZG93bmZhbGw/GZmaCAAIY2F0ZWdvcnkABmZvcmVzdAAACAAEbmFtZQAabWluZWNyYWZ0OnRhbGxfYmlyY2hfaGlsbHMDAAJpZAAAAJwKAAdlbGVtZW50CAANcHJlY2lwaXRhdGlvbgAEcmFpbgoAB2VmZmVjdHMDAAlza3lfY29sb3IAeqX/AwAPd2F0ZXJfZm9nX2NvbG9yAAUFMwMACWZvZ19jb2xvcgDA2P8DAAt3YXRlcl9jb2xvcgA/duQKAAptb29kX3NvdW5kAwAKdGlja19kZWxheQAAF3AGAAZvZmZzZXRAAAAAAAAAAAgABXNvdW5kABZtaW5lY3JhZnQ6YW1iaWVudC5jYXZlAwATYmxvY2tfc2VhcmNoX2V4dGVudAAAAAgAAAUABWRlcHRoPwzMzQUAC3RlbXBlcmF0dXJlPxmZmgUABXNjYWxlPwAAAAUACGRvd25mYWxsPxmZmggACGNhdGVnb3J5AAZmb3Jlc3QAAAgABG5hbWUAG21pbmVjcmFmdDpkYXJrX2ZvcmVzdF9oaWxscwMAAmlkAAAAnQoAB2VsZW1lbnQIAA1wcmVjaXBpdGF0aW9uAARyYWluCgAHZWZmZWN0cwgAFGdyYXNzX2NvbG9yX21vZGlmaWVyAAtkYXJrX2ZvcmVzdAMACXNreV9jb2xvcgB5pv8DAA93YXRlcl9mb2dfY29sb3IABQUzAwAJZm9nX2NvbG9yAMDY/wMAC3dhdGVyX2NvbG9yAD925AoACm1vb2Rfc291bmQDAAp0aWNrX2RlbGF5AAAXcAYABm9mZnNldEAAAAAAAAAACAAFc291bmQAFm1pbmVjcmFmdDphbWJpZW50LmNhdmUDABNibG9ja19zZWFyY2hfZXh0ZW50AAAACAAABQAFZGVwdGg+TMzNBQALdGVtcGVyYXR1cmU/MzMzBQAFc2NhbGU+zMzNBQAIZG93bmZhbGw/TMzNCAAIY2F0ZWdvcnkABmZvcmVzdAAACAAEbmFtZQAfbWluZWNyYWZ0OnNub3d5X3RhaWdhX21vdW50YWlucwMAAmlkAAAAngoAB2VsZW1lbnQIAA1wcmVjaXBpdGF0aW9uAARzbm93CgAHZWZmZWN0cwMACXNreV9jb2xvcgCDnv8DAA93YXRlcl9mb2dfY29sb3IABQUzAwAJZm9nX2NvbG9yAMDY/wMAC3dhdGVyX2NvbG9yAD1X1goACm1vb2Rfc291bmQDAAp0aWNrX2RlbGF5AAAXcAYABm9mZnNldEAAAAAAAAAACAAFc291bmQAFm1pbmVjcmFmdDphbWJpZW50LmNhdmUDABNibG9ja19zZWFyY2hfZXh0ZW50AAAACAAABQAFZGVwdGg+mZmaBQALdGVtcGVyYXR1cmW/AAAABQAFc2NhbGU+zMzNBQAIZG93bmZhbGw+zMzNCAAIY2F0ZWdvcnkABXRhaWdhAAAIAARuYW1lABxtaW5lY3JhZnQ6Z2lhbnRfc3BydWNlX3RhaWdhAwACaWQAAACgCgAHZWxlbWVudAgADXByZWNpcGl0YXRpb24ABHJhaW4KAAdlZmZlY3RzAwAJc2t5X2NvbG9yAH2j/wMAD3dhdGVyX2ZvZ19jb2xvcgAFBTMDAAlmb2dfY29sb3IAwNj/AwALd2F0ZXJfY29sb3IAP3bkCgAKbW9vZF9zb3VuZAMACnRpY2tfZGVsYXkAABdwBgAGb2Zmc2V0QAAAAAAAAAAIAAVzb3VuZAAWbWluZWNyYWZ0OmFtYmllbnQuY2F2ZQMAE2Jsb2NrX3NlYXJjaF9leHRlbnQAAAAIAAAFAAVkZXB0aD5MzM0FAAt0ZW1wZXJhdHVyZT6AAAAFAAVzY2FsZT5MzM0FAAhkb3duZmFsbD9MzM0IAAhjYXRlZ29yeQAFdGFpZ2EAAAgABG5hbWUAIm1pbmVjcmFmdDpnaWFudF9zcHJ1Y2VfdGFpZ2FfaGlsbHMDAAJpZAAAAKEKAAdlbGVtZW50CAANcHJlY2lwaXRhdGlvbgAEcmFpbgoAB2VmZmVjdHMDAAlza3lfY29sb3IAfaP/AwAPd2F0ZXJfZm9nX2NvbG9yAAUFMwMACWZvZ19jb2xvcgDA2P8DAAt3YXRlcl9jb2xvcgA/duQKAAptb29kX3NvdW5kAwAKdGlja19kZWxheQAAF3AGAAZvZmZzZXRAAAAAAAAAAAgABXNvdW5kABZtaW5lY3JhZnQ6YW1iaWVudC5jYXZlAwATYmxvY2tfc2VhcmNoX2V4dGVudAAAAAgAAAUABWRlcHRoPkzMzQUAC3RlbXBlcmF0dXJlPoAAAAUABXNjYWxlPkzMzQUACGRvd25mYWxsP0zMzQgACGNhdGVnb3J5AAV0YWlnYQAACAAEbmFtZQAlbWluZWNyYWZ0Om1vZGlmaWVkX2dyYXZlbGx5X21vdW50YWlucwMAAmlkAAAAogoAB2VsZW1lbnQIAA1wcmVjaXBpdGF0aW9uAARyYWluCgAHZWZmZWN0cwMACXNreV9jb2xvcgB9ov8DAA93YXRlcl9mb2dfY29sb3IABQUzAwAJZm9nX2NvbG9yAMDY/wMAC3dhdGVyX2NvbG9yAD925AoACm1vb2Rfc291bmQDAAp0aWNrX2RlbGF5AAAXcAYABm9mZnNldEAAAAAAAAAACAAFc291bmQAFm1pbmVjcmFmdDphbWJpZW50LmNhdmUDABNibG9ja19zZWFyY2hfZXh0ZW50AAAACAAABQAFZGVwdGg/gAAABQALdGVtcGVyYXR1cmU+TMzNBQAFc2NhbGU/AAAABQAIZG93bmZhbGw+mZmaCAAIY2F0ZWdvcnkADWV4dHJlbWVfaGlsbHMAAAgABG5hbWUAG21pbmVjcmFmdDpzaGF0dGVyZWRfc2F2YW5uYQMAAmlkAAAAowoAB2VsZW1lbnQIAA1wcmVjaXBpdGF0aW9uAARub25lCgAHZWZmZWN0cwMACXNreV9jb2xvcgB2qf8DAA93YXRlcl9mb2dfY29sb3IABQUzAwAJZm9nX2NvbG9yAMDY/wMAC3dhdGVyX2NvbG9yAD925AoACm1vb2Rfc291bmQDAAp0aWNrX2RlbGF5AAAXcAYABm9mZnNldEAAAAAAAAAACAAFc291bmQAFm1pbmVjcmFmdDphbWJpZW50LmNhdmUDABNibG9ja19zZWFyY2hfZXh0ZW50AAAACAAABQAFZGVwdGg+uZmaBQALdGVtcGVyYXR1cmU/jMzNBQAFc2NhbGU/nMzNBQAIZG93bmZhbGwAAAAACAAIY2F0ZWdvcnkAB3NhdmFubmEAAAgABG5hbWUAI21pbmVjcmFmdDpzaGF0dGVyZWRfc2F2YW5uYV9wbGF0ZWF1AwACaWQAAACkCgAHZWxlbWVudAgADXByZWNpcGl0YXRpb24ABG5vbmUKAAdlZmZlY3RzAwAJc2t5X2NvbG9yAHao/wMAD3dhdGVyX2ZvZ19jb2xvcgAFBTMDAAlmb2dfY29sb3IAwNj/AwALd2F0ZXJfY29sb3IAP3bkCgAKbW9vZF9zb3VuZAMACnRpY2tfZGVsYXkAABdwBgAGb2Zmc2V0QAAAAAAAAAAIAAVzb3VuZAAWbWluZWNyYWZ0OmFtYmllbnQuY2F2ZQMAE2Jsb2NrX3NlYXJjaF9leHRlbnQAAAAIAAAFAAVkZXB0aD+GZmYFAAt0ZW1wZXJhdHVyZT+AAAAFAAVzY2FsZT+bMzQFAAhkb3duZmFsbAAAAAAIAAhjYXRlZ29yeQAHc2F2YW5uYQAACAAEbmFtZQAZbWluZWNyYWZ0OmVyb2RlZF9iYWRsYW5kcwMAAmlkAAAApQoAB2VsZW1lbnQIAA1wcmVjaXBpdGF0aW9uAARub25lCgAHZWZmZWN0cwMACXNreV9jb2xvcgBusf8DAAtncmFzc19jb2xvcgCQgU0DAA1mb2xpYWdlX2NvbG9yAJ6BTQMAD3dhdGVyX2ZvZ19jb2xvcgAFBTMDAAlmb2dfY29sb3IAwNj/AwALd2F0ZXJfY29sb3IAP3bkCgAKbW9vZF9zb3VuZAMACnRpY2tfZGVsYXkAABdwBgAGb2Zmc2V0QAAAAAAAAAAIAAVzb3VuZAAWbWluZWNyYWZ0OmFtYmllbnQuY2F2ZQMAE2Jsb2NrX3NlYXJjaF9leHRlbnQAAAAIAAAFAAVkZXB0aD3MzM0FAAt0ZW1wZXJhdHVyZUAAAAAFAAVzY2FsZT5MzM0FAAhkb3duZmFsbAAAAAAIAAhjYXRlZ29yeQAEbWVzYQAACAAEbmFtZQAqbWluZWNyYWZ0Om1vZGlmaWVkX3dvb2RlZF9iYWRsYW5kc19wbGF0ZWF1AwACaWQAAACmCgAHZWxlbWVudAgADXByZWNpcGl0YXRpb24ABG5vbmUKAAdlZmZlY3RzAwAJc2t5X2NvbG9yAG6x/wMAC2dyYXNzX2NvbG9yAJCBTQMADWZvbGlhZ2VfY29sb3IAnoFNAwAPd2F0ZXJfZm9nX2NvbG9yAAUFMwMACWZvZ19jb2xvcgDA2P8DAAt3YXRlcl9jb2xvcgA/duQKAAptb29kX3NvdW5kAwAKdGlja19kZWxheQAAF3AGAAZvZmZzZXRAAAAAAAAAAAgABXNvdW5kABZtaW5lY3JhZnQ6YW1iaWVudC5jYXZlAwATYmxvY2tfc2VhcmNoX2V4dGVudAAAAAgAAAUABWRlcHRoPuZmZgUAC3RlbXBlcmF0dXJlQAAAAAUABXNjYWxlPpmZmgUACGRvd25mYWxsAAAAAAgACGNhdGVnb3J5AARtZXNhAAAIAARuYW1lACNtaW5lY3JhZnQ6bW9kaWZpZWRfYmFkbGFuZHNfcGxhdGVhdQMAAmlkAAAApwoAB2VsZW1lbnQIAA1wcmVjaXBpdGF0aW9uAARub25lCgAHZWZmZWN0cwMACXNreV9jb2xvcgBusf8DAAtncmFzc19jb2xvcgCQgU0DAA1mb2xpYWdlX2NvbG9yAJ6BTQMAD3dhdGVyX2ZvZ19jb2xvcgAFBTMDAAlmb2dfY29sb3IAwNj/AwALd2F0ZXJfY29sb3IAP3bkCgAKbW9vZF9zb3VuZAMACnRpY2tfZGVsYXkAABdwBgAGb2Zmc2V0QAAAAAAAAAAIAAVzb3VuZAAWbWluZWNyYWZ0OmFtYmllbnQuY2F2ZQMAE2Jsb2NrX3NlYXJjaF9leHRlbnQAAAAIAAAFAAVkZXB0aD7mZmYFAAt0ZW1wZXJhdHVyZUAAAAAFAAVzY2FsZT6ZmZoFAAhkb3duZmFsbAAAAAAIAAhjYXRlZ29yeQAEbWVzYQAACAAEbmFtZQAXbWluZWNyYWZ0OmJhbWJvb19qdW5nbGUDAAJpZAAAAKgKAAdlbGVtZW50CAANcHJlY2lwaXRhdGlvbgAEcmFpbgoAB2VmZmVjdHMDAAlza3lfY29sb3IAd6j/AwAPd2F0ZXJfZm9nX2NvbG9yAAUFMwMACWZvZ19jb2xvcgDA2P8DAAt3YXRlcl9jb2xvcgA/duQKAAptb29kX3NvdW5kAwAKdGlja19kZWxheQAAF3AGAAZvZmZzZXRAAAAAAAAAAAgABXNvdW5kABZtaW5lY3JhZnQ6YW1iaWVudC5jYXZlAwATYmxvY2tfc2VhcmNoX2V4dGVudAAAAAgAAAUABWRlcHRoPczMzQUAC3RlbXBlcmF0dXJlP3MzMwUABXNjYWxlPkzMzQUACGRvd25mYWxsP2ZmZggACGNhdGVnb3J5AAZqdW5nbGUAAAgABG5hbWUAHW1pbmVjcmFmdDpiYW1ib29fanVuZ2xlX2hpbGxzAwACaWQAAACpCgAHZWxlbWVudAgADXByZWNpcGl0YXRpb24ABHJhaW4KAAdlZmZlY3RzAwAJc2t5X2NvbG9yAHeo/wMAD3dhdGVyX2ZvZ19jb2xvcgAFBTMDAAlmb2dfY29sb3IAwNj/AwALd2F0ZXJfY29sb3IAP3bkCgAKbW9vZF9zb3VuZAMACnRpY2tfZGVsYXkAABdwBgAGb2Zmc2V0QAAAAAAAAAAIAAVzb3VuZAAWbWluZWNyYWZ0OmFtYmllbnQuY2F2ZQMAE2Jsb2NrX3NlYXJjaF9leHRlbnQAAAAIAAAFAAVkZXB0aD7mZmYFAAt0ZW1wZXJhdHVyZT9zMzMFAAVzY2FsZT6ZmZoFAAhkb3duZmFsbD9mZmYIAAhjYXRlZ29yeQAGanVuZ2xlAAAIAARuYW1lABptaW5lY3JhZnQ6c291bF9zYW5kX3ZhbGxleQMAAmlkAAAAqgoAB2VsZW1lbnQIAA1wcmVjaXBpdGF0aW9uAARub25lCgAHZWZmZWN0cwoABW11c2ljAQAVcmVwbGFjZV9jdXJyZW50X211c2ljAAMACW1heF9kZWxheQAAXcAIAAVzb3VuZAAnbWluZWNyYWZ0Om11c2ljLm5ldGhlci5zb3VsX3NhbmRfdmFsbGV5AwAJbWluX2RlbGF5AAAu4AADAAlza3lfY29sb3IAbrH/CAANYW1iaWVudF9zb3VuZAAnbWluZWNyYWZ0OmFtYmllbnQuc291bF9zYW5kX3ZhbGxleS5sb29wCgAPYWRkaXRpb25zX3NvdW5kCAAFc291bmQALG1pbmVjcmFmdDphbWJpZW50LnNvdWxfc2FuZF92YWxsZXkuYWRkaXRpb25zBgALdGlja19jaGFuY2U/hruYx+KCQQAKAAhwYXJ0aWNsZQUAC3Byb2JhYmlsaXR5O8zMzQoAB29wdGlvbnMIAAR0eXBlAA1taW5lY3JhZnQ6YXNoAAADAA93YXRlcl9mb2dfY29sb3IABQUzAwAJZm9nX2NvbG9yABtHRQMAC3dhdGVyX2NvbG9yAD925AoACm1vb2Rfc291bmQDAAp0aWNrX2RlbGF5AAAXcAYABm9mZnNldEAAAAAAAAAACAAFc291bmQAJ21pbmVjcmFmdDphbWJpZW50LnNvdWxfc2FuZF92YWxsZXkubW9vZAMAE2Jsb2NrX3NlYXJjaF9leHRlbnQAAAAIAAAFAAVkZXB0aD3MzM0FAAt0ZW1wZXJhdHVyZUAAAAAFAAVzY2FsZT5MzM0FAAhkb3duZmFsbAAAAAAIAAhjYXRlZ29yeQAGbmV0aGVyAAAIAARuYW1lABhtaW5lY3JhZnQ6Y3JpbXNvbl9mb3Jlc3QDAAJpZAAAAKsKAAdlbGVtZW50CAANcHJlY2lwaXRhdGlvbgAEbm9uZQoAB2VmZmVjdHMKAAVtdXNpYwEAFXJlcGxhY2VfY3VycmVudF9tdXNpYwADAAltYXhfZGVsYXkAAF3ACAAFc291bmQAJW1pbmVjcmFmdDptdXNpYy5uZXRoZXIuY3JpbXNvbl9mb3Jlc3QDAAltaW5fZGVsYXkAAC7gAAMACXNreV9jb2xvcgBusf8IAA1hbWJpZW50X3NvdW5kACVtaW5lY3JhZnQ6YW1iaWVudC5jcmltc29uX2ZvcmVzdC5sb29wCgAPYWRkaXRpb25zX3NvdW5kCAAFc291bmQAKm1pbmVjcmFmdDphbWJpZW50LmNyaW1zb25fZm9yZXN0LmFkZGl0aW9ucwYAC3RpY2tfY2hhbmNlP4a7mMfigkEACgAIcGFydGljbGUFAAtwcm9iYWJpbGl0eTzMzM0KAAdvcHRpb25zCAAEdHlwZQAXbWluZWNyYWZ0OmNyaW1zb25fc3BvcmUAAAMAD3dhdGVyX2ZvZ19jb2xvcgAFBTMDAAlmb2dfY29sb3IAMwMDAwALd2F0ZXJfY29sb3IAP3bkCgAKbW9vZF9zb3VuZAMACnRpY2tfZGVsYXkAABdwBgAGb2Zmc2V0QAAAAAAAAAAIAAVzb3VuZAAlbWluZWNyYWZ0OmFtYmllbnQuY3JpbXNvbl9mb3Jlc3QubW9vZAMAE2Jsb2NrX3NlYXJjaF9leHRlbnQAAAAIAAAFAAVkZXB0aD3MzM0FAAt0ZW1wZXJhdHVyZUAAAAAFAAVzY2FsZT5MzM0FAAhkb3duZmFsbAAAAAAIAAhjYXRlZ29yeQAGbmV0aGVyAAAIAARuYW1lABdtaW5lY3JhZnQ6d2FycGVkX2ZvcmVzdAMAAmlkAAAArAoAB2VsZW1lbnQIAA1wcmVjaXBpdGF0aW9uAARub25lCgAHZWZmZWN0cwoABW11c2ljAQAVcmVwbGFjZV9jdXJyZW50X211c2ljAAMACW1heF9kZWxheQAAXcAIAAVzb3VuZAAkbWluZWNyYWZ0Om11c2ljLm5ldGhlci53YXJwZWRfZm9yZXN0AwAJbWluX2RlbGF5AAAu4AADAAlza3lfY29sb3IAbrH/CAANYW1iaWVudF9zb3VuZAAkbWluZWNyYWZ0OmFtYmllbnQud2FycGVkX2ZvcmVzdC5sb29wCgAPYWRkaXRpb25zX3NvdW5kCAAFc291bmQAKW1pbmVjcmFmdDphbWJpZW50LndhcnBlZF9mb3Jlc3QuYWRkaXRpb25zBgALdGlja19jaGFuY2U/hruYx+KCQQAKAAhwYXJ0aWNsZQUAC3Byb2JhYmlsaXR5PGn2qQoAB29wdGlvbnMIAAR0eXBlABZtaW5lY3JhZnQ6d2FycGVkX3Nwb3JlAAADAA93YXRlcl9mb2dfY29sb3IABQUzAwAJZm9nX2NvbG9yABoFGgMAC3dhdGVyX2NvbG9yAD925AoACm1vb2Rfc291bmQDAAp0aWNrX2RlbGF5AAAXcAYABm9mZnNldEAAAAAAAAAACAAFc291bmQAJG1pbmVjcmFmdDphbWJpZW50LndhcnBlZF9mb3Jlc3QubW9vZAMAE2Jsb2NrX3NlYXJjaF9leHRlbnQAAAAIAAAFAAVkZXB0aD3MzM0FAAt0ZW1wZXJhdHVyZUAAAAAFAAVzY2FsZT5MzM0FAAhkb3duZmFsbAAAAAAIAAhjYXRlZ29yeQAGbmV0aGVyAAAIAARuYW1lABdtaW5lY3JhZnQ6YmFzYWx0X2RlbHRhcwMAAmlkAAAArQoAB2VsZW1lbnQIAA1wcmVjaXBpdGF0aW9uAARub25lCgAHZWZmZWN0cwoABW11c2ljAQAVcmVwbGFjZV9jdXJyZW50X211c2ljAAMACW1heF9kZWxheQAAXcAIAAVzb3VuZAAkbWluZWNyYWZ0Om11c2ljLm5ldGhlci5iYXNhbHRfZGVsdGFzAwAJbWluX2RlbGF5AAAu4AADAAlza3lfY29sb3IAbrH/CAANYW1iaWVudF9zb3VuZAAkbWluZWNyYWZ0OmFtYmllbnQuYmFzYWx0X2RlbHRhcy5sb29wCgAPYWRkaXRpb25zX3NvdW5kCAAFc291bmQAKW1pbmVjcmFmdDphbWJpZW50LmJhc2FsdF9kZWx0YXMuYWRkaXRpb25zBgALdGlja19jaGFuY2U/hruYx+KCQQAKAAhwYXJ0aWNsZQUAC3Byb2JhYmlsaXR5PfHa6woAB29wdGlvbnMIAAR0eXBlABNtaW5lY3JhZnQ6d2hpdGVfYXNoAAADAA93YXRlcl9mb2dfY29sb3IAQj5CAwAJZm9nX2NvbG9yAGhfcAMAC3dhdGVyX2NvbG9yAD925AoACm1vb2Rfc291bmQDAAp0aWNrX2RlbGF5AAAXcAYABm9mZnNldEAAAAAAAAAACAAFc291bmQAJG1pbmVjcmFmdDphbWJpZW50LmJhc2FsdF9kZWx0YXMubW9vZAMAE2Jsb2NrX3NlYXJjaF9leHRlbnQAAAAIAAAFAAVkZXB0aD3MzM0FAAt0ZW1wZXJhdHVyZUAAAAAFAAVzY2FsZT5MzM0FAAhkb3duZmFsbAAAAAAIAAhjYXRlZ29yeQAGbmV0aGVyAAAAAA==");

				//$player->bigBrother_getDimensionPEToPC($packet->generator);
				$pk->worldName = "minecraft:world";//TODO: dimensionとセットなのでここを更新するときはそれ用のdimension.datを手に入れる必要がある
				$pk->hashedSeed = 0;
				$pk->maxPlayers = Server::getInstance()->getMaxPlayers();
				$pk->viewDistance = 4;
				$pk->enableRespawnScreen = true;
				$packets[] = $pk;

				$pk = new PluginMessagePacket();
				$pk->channel = "minecraft:brand";
				$pk->data[] = "BigBrother";//displayed "BigBrother" server on debug mode
				$packets[] = $pk;

				$pk = new SpawnPositionPacket();
				$pk->x = (int) $packet->playerPosition->x;
				$pk->y = (int) $packet->playerPosition->y;
				$pk->z = (int) $packet->playerPosition->z;
				$packets[] = $pk;

				$pk = new UpdateViewPositionPacket();
				$pk->chunkX = $packet->playerPosition->x >> 4;
				$pk->chunkZ = $packet->playerPosition->z >> 4;
				$packets[] = $pk;

				$pk = new PlayerAbilitiesPacket();
				$pk->flyingSpeed = 0.05;
				$pk->viewModifierField = 0.1;
				$pk->canFly = ($packet->playerGamemode & 0x01) > 0;
				$pk->damageDisabled = ($packet->playerGamemode & 0x01) > 0;
				$pk->isFlying = false;
				$pk->isCreative = ($packet->playerGamemode & 0x01) > 0;
				$packets[] = $pk;

				return $packets;

			case Info::ADD_PLAYER_PACKET:
				/** @var AddPlayerPacket $packet */
				$packets = [];

				$pk = new SpawnPlayerPacket();
				$pk->entityId = $packet->actorRuntimeId;
				$pk->uuid = $packet->uuid->getBytes();
				$pk->x = $packet->position->x;
				$pk->y = $packet->position->y;
				$pk->z = $packet->position->z;
				$pk->yaw = $packet->yaw;
				$pk->pitch = $packet->pitch;
				$packets[] = $pk;

				$pk = new EntityTeleportPacket();
				$pk->entityId = $packet->actorRuntimeId;
				$pk->x = $packet->position->x;
				$pk->y = $packet->position->y;
				$pk->z = $packet->position->z;
				$pk->yaw = $packet->yaw;
				$pk->pitch = $packet->pitch;
				$packets[] = $pk;

				$pk = new EntityEquipmentPacket();
				$pk->entityId = $packet->actorRuntimeId;
				$pk->slot = 0;//main hand
				$pk->item = $packet->item->getItemStack();
				$packets[] = $pk;

				$pk = new EntityHeadLookPacket();
				$pk->entityId = $packet->actorRuntimeId;
				$pk->yaw = $packet->yaw;
				$packets[] = $pk;

				$session->addEntityList($packet->actorRuntimeId, "player");
				if(isset($packet->metadata[EntityMetadataProperties::NAMETAG])){
					$session->bigBrother_setBossBarData("nameTag", $packet->metadata[EntityMetadataProperties::NAMETAG]->getValue());
				}

				return $packets;

			case Info::REMOVE_ACTOR_PACKET:
				/** @var RemoveActorPacket $packet */
				$packets = [];

				if($packet->actorUniqueId === $session->bigBrother_getBossBarData("actorRuntimeId")){
					$uuid = $session->bigBrother_getBossBarData("uuid");
					if($uuid === ""){
						return null;
					}
					$pk = new BossBarPacket();
					$pk->uuid = $uuid;
					$pk->actionId = BossBarPacket::TYPE_REMOVE;

					$session->bigBrother_setBossBarData("actorRuntimeId", -1);
					$session->bigBrother_setBossBarData("uuid", "");

					$packets[] = $pk;
				}
				$pk = new DestroyEntitiesPacket();
				$pk->entityIds[] = $packet->actorUniqueId;

				$session->removeEntityList($packet->actorUniqueId);

				$packets[] = $pk;
				return $packets;

			case Info::TAKE_ITEM_ACTOR_PACKET:
				/** @var TakeItemActorPacket $packet */
				return $session->getInventoryUtils()->onTakeItemEntity($packet);

			case Info::MOVE_ACTOR_ABSOLUTE_PACKET:
				/** @var MoveActorAbsolutePacket $packet */
				if($packet->actorRuntimeId === $session->getPlayer()->getId()){//TODO
					return null;
				}else{
					$baseOffset = 0;
					$isOnGround = true;
					$entity = $session->getPlayer()->getWorld()->getEntity($packet->actorRuntimeId);
					if($entity instanceof Entity){
						$baseOffset = match($entity::getNetworkTypeId()){
							EntityIds::PLAYER => 1.62,
							EntityIds::ITEM => 0.125,
							EntityIds::TNT, EntityIds::FALLING_BLOCK => 0.49,
						};

						$isOnGround = $entity->isOnGround();
					}

					$packets = [];

					$pk = new EntityTeleportPacket();
					$pk->entityId = $packet->actorRuntimeId;
					$pk->x = $packet->position->x;
					$pk->y = $packet->position->y - $baseOffset;
					$pk->z = $packet->position->z;
					$pk->yaw = $packet->yaw;
					$pk->pitch = $packet->pitch;
					$packets[] = $pk;

					$pk = new EntityRotationPacket();
					$pk->entityId = $packet->actorRuntimeId;
					$pk->yaw = $packet->yaw;
					$pk->pitch = $packet->pitch;
					$pk->onGround = $isOnGround;
					$packets[] = $pk;

					$pk = new EntityHeadLookPacket();
					$pk->entityId = $packet->actorRuntimeId;
					$pk->yaw = $packet->yaw;
					$packets[] = $pk;

					return $packets;
				}

			case Info::MOVE_PLAYER_PACKET:
				/** @var MovePlayerPacket $packet */
				if($packet->actorRuntimeId === $session->getPlayer()->getId()){
					if($session->getPlayer()->spawned){//for Loading Chunks
						$pk = new PlayerPositionAndLookPacket();//
						$pk->x = $packet->position->x;
						$pk->y = $packet->position->y - $session->getPlayer()->getEyeHeight();
						$pk->z = $packet->position->z;
						$pk->yaw = $packet->yaw;
						$pk->pitch = $packet->pitch;
						$pk->onGround = $packet->onGround;

						return $pk;
					}
				}else{
					$packets = [];

					$pk = new EntityTeleportPacket();
					$pk->entityId = $packet->actorRuntimeId;
					$pk->x = $packet->position->x;
					$pk->y = $packet->position->y - $session->getPlayer()->getEyeHeight();
					$pk->z = $packet->position->z;
					$pk->yaw = $packet->yaw;
					$pk->pitch = $packet->pitch;
					$packets[] = $pk;

					$pk = new EntityRotationPacket();
					$pk->entityId = $packet->actorRuntimeId;
					$pk->yaw = $packet->headYaw;
					$pk->pitch = $packet->pitch;
					$pk->onGround = $packet->onGround;
					$packets[] = $pk;

					$pk = new EntityHeadLookPacket();
					$pk->entityId = $packet->actorRuntimeId;
					$pk->yaw = $packet->headYaw;
					$packets[] = $pk;
					return $packets;
				}
				return null;

			case Info::UPDATE_BLOCK_PACKET:
				/** @var UpdateBlockPacket $packet */
				if($packet->dataLayerId === UpdateBlockPacket::DATA_LAYER_NORMAL){
					$pk = new BlockChangePacket();
					$pk->x = $packet->blockPosition->getX();
					$pk->y = $packet->blockPosition->getY();
					$pk->z = $packet->blockPosition->getZ();
					$pk->blockId = ConvertUtils::getBlockStateIndex($packet->blockRuntimeId);
					return $pk;
				}
				return null;

			case Info::ADD_PAINTING_PACKET:
				/** @var AddPaintingPacket $packet */
				$spawnPaintingPos = (new Vector3($packet->position->x, $packet->position->y, $packet->position->z))->floor();
				$motives = ["Plant" => 5];

				echo $packet->title . "\n";

				$pk = new SpawnPaintingPacket();
				$pk->entityId = $packet->actorRuntimeId;
				$pk->uuid = Uuid::uuid4()->getBytes();
				$pk->x = $spawnPaintingPos->x;
				$pk->y = $spawnPaintingPos->y;
				$pk->z = $spawnPaintingPos->z;
				$pk->motive = $motives[$packet->title];
				$pk->direction = $packet->direction;

				return $pk;

			case Info::CHANGE_DIMENSION_PACKET:
				/** @var ChangeDimensionPacket $packet */
				$pk = new RespawnPacket();
				$pk->dimension = $session->bigBrother_getDimension();
				$pk->worldName = "minecraft:overworld";
				$pk->hashedSeed = 0;
				$pk->gamemode = match($session->getPlayer()->getGamemode()->getEnglishName()){
					GameMode::SURVIVAL()->getEnglishName() => 0,
					GameMode::CREATIVE()->getEnglishName() => 1,
					GameMode::ADVENTURE()->getEnglishName() => 2,
					GameMode::SPECTATOR()->getEnglishName() => 3
				};
				$pk->previousGamemode = -1;

				$session->respawn();
				return $pk;

			case Info::PLAY_SOUND_PACKET:
				/** @var PlaySoundPacket $packet */
				$pk = new NamedSoundEffectPacket();
				$pk->soundCategory = 0;
				$pk->effectPositionX = (int) $packet->x;
				$pk->effectPositionY = (int) $packet->y;
				$pk->effectPositionZ = (int) $packet->z;
				$pk->volume = $packet->volume * 0.25;
				$pk->pitch = $packet->pitch;
				$pk->soundName = $packet->soundName;

				return $pk;

			case Info::LEVEL_SOUND_EVENT_PACKET:
				/** @var LevelSoundEventPacket $packet */
				$volume = 1;
				$pitch = $packet->extraData;

				switch($packet->sound){
					case LevelSoundEvent::EXPLODE:
						$isSoundEffect = true;
						$category = 0;

						$name = "entity.generic.explode";
						break;
					case LevelSoundEvent::CHEST_OPEN:
						$isSoundEffect = true;
						$category = 1;

						$name = $session->getPlayer()->getWorld()->getBlock($packet->position)->getTypeId() === BlockTypeIds::ENDER_CHEST ? "block.enderchest.open" : "block.chest.open";
						break;
					case LevelSoundEvent::CHEST_CLOSED:
						$isSoundEffect = true;
						$category = 1;

						$name = $session->getPlayer()->getWorld()->getBlock($packet->position)->getTypeId() === BlockTypeIds::ENDER_CHEST ? "block.enderchest.close" : "block.chest.close";
						break;
					case LevelSoundEvent::NOTE:
						$isSoundEffect = true;
						$category = 2;
						$volume = 3;
						$name = "block.note.harp";//TODO

						$pitch /= 2.0;
						break;
					case LevelSoundEvent::PLACE://unused
						return null;
					default:
						return null;
				}

				if($isSoundEffect){
					$pk = new NamedSoundEffectPacket();
					$pk->soundCategory = $category;
					$pk->effectPositionX = (int) $packet->position->x;
					$pk->effectPositionY = (int) $packet->position->y;
					$pk->effectPositionZ = (int) $packet->position->z;
					$pk->volume = $volume;
					$pk->pitch = $pitch;
					$pk->soundName = $name;

					return $pk;
				}

				return null;

			case Info::LEVEL_EVENT_PACKET://TODO
				/** @var LevelEventPacket $packet */
				$isSoundEffect = false;
				$isParticle = false;
				$addData = [];
				$category = 0;
				$name = "";
				$id = 0;

				switch($packet->eventId){
					case LevelEvent::PARTICLE_DESTROY;
						return null;
					case LevelEvent::SOUND_IGNITE:
						$isSoundEffect = true;
						$name = "entity.tnt.primed";
						break;
					case LevelEvent::SOUND_SHOOT:
						$isSoundEffect = true;

						$name = match(($id = $session->getPlayer()->getInventory()->getItemInHand()->getTypeId())){
							ItemTypeIds::EGG => "entity.egg.throw",
							ItemTypeIds::EXPERIENCE_BOTTLE => "entity.experience_bottle.throw",
							ItemTypeIds::SPLASH_POTION => "entity.splash_potion.throw",
							ItemTypeIds::BOW => "entity.arrow.shoot",
							ItemTypeIds::ENDER_PEARL => "entity.enderpearl.throw",
							default => "entity.snowball.throw",
						};
						break;
					case LevelEvent::SOUND_DOOR:
						$isSoundEffect = true;

						$block = $session->getPlayer()->getWorld()->getBlock($packet->position);

						/** @var Door|Trapdoor|FenceGate $block */
						switch($block->getTypeId()){
							case BlockTypeIds::OAK_DOOR:
							case BlockTypeIds::SPRUCE_DOOR:
							case BlockTypeIds::BIRCH_DOOR:
							case BlockTypeIds::JUNGLE_DOOR:
							case BlockTypeIds::ACACIA_DOOR:
							case BlockTypeIds::DARK_OAK_DOOR:
								$name = $block->isOpen() ? "block.wooden_door.open" : "block.wooden_door.close";
								break;
							case BlockTypeIds::IRON_DOOR:
								$name = $block->isOpen() ? "block.iron_door.open" : "block.iron_door.close";
								break;
							case BlockTypeIds::OAK_TRAPDOOR:
							case BlockTypeIds::SPRUCE_TRAPDOOR:
							case BlockTypeIds::BIRCH_TRAPDOOR:
							case BlockTypeIds::JUNGLE_TRAPDOOR:
							case BlockTypeIds::ACACIA_TRAPDOOR:
							case BlockTypeIds::DARK_OAK_TRAPDOOR:
								$name = $block->isOpen() ? "block.wooden_trapdoor.open" : "block.wooden_trapdoor.close";
								break;
							case BlockTypeIds::IRON_TRAPDOOR:
								$name = $block->isOpen() ? "block.iron_trapdoor.open" : "block.iron_trapdoor.close";
								break;
							case BlockTypeIds::OAK_FENCE_GATE:
							case BlockTypeIds::SPRUCE_FENCE_GATE:
							case BlockTypeIds::BIRCH_FENCE_GATE:
							case BlockTypeIds::JUNGLE_FENCE_GATE:
							case BlockTypeIds::DARK_OAK_FENCE_GATE:
							case BlockTypeIds::ACACIA_FENCE_GATE:
								$name = $block->isOpen() ? "block.fence_gate.open" : "block.fence_gate.close";
								break;
							default:
								echo "[LevelEventPacket] Unknown DoorSound\n";
								return null;
						}
						break;
					case LevelEvent::ADD_PARTICLE_MASK | ParticleIds::CRITICAL:
						$isParticle = true;
						$id = 9;
						break;
					case LevelEvent::ADD_PARTICLE_MASK | ParticleIds::HUGE_EXPLODE_SEED:
						$isParticle = true;
						$id = 2;
						break;
					case LevelEvent::ADD_PARTICLE_MASK | ParticleIds::TERRAIN:
						$isParticle = true;

						/** @noinspection PhpInternalEntityUsedInspection */
						$block = GlobalBlockStateHandlers::getDeserializer()->deserialize(RuntimeBlockMapping::getInstance()->getBlockStateDictionary(RuntimeBlockMapping::getMappingProtocol(ProtocolInfo::CURRENT_PROTOCOL))->getDataFromStateId($packet->eventData));
						$id = $block >> Block::INTERNAL_STATE_DATA_BITS;
						$meta = $block & Block::INTERNAL_STATE_DATA_MASK;
						ConvertUtils::convertBlockData(true, $block[0], $block[1]);

						$packet->eventData = $id | ($meta << 12);

						$id = 37;
						$addData = [
							$packet->eventData
						];
						break;
					case LevelEvent::ADD_PARTICLE_MASK | ParticleIds::DUST:
						$isParticle = true;
						$id = 46;
						$addData = [
							$packet->data//TODO: RGBA
						];
						break;
					/*case LevelEventPacket::EVENT_ADD_PARTICLE_MASK | Particle::TYPE_INK:
					break;*/
					case LevelEvent::ADD_PARTICLE_MASK | ParticleIds::SNOWBALL_POOF:
						$isParticle = true;
						$id = 31;
						break;
					case LevelEvent::ADD_PARTICLE_MASK | ParticleIds::ITEM_BREAK:
						//TODO
						break;
					case LevelEvent::PARTICLE_DESTROY:
						/** @noinspection PhpInternalEntityUsedInspection */
						$block = GlobalBlockStateHandlers::getDeserializer()->deserialize(RuntimeBlockMapping::getInstance()->getBlockStateDictionary(RuntimeBlockMapping::getMappingProtocol(ProtocolInfo::CURRENT_PROTOCOL))->getDataFromStateId($packet->eventData));
						$id = $block >> Block::INTERNAL_STATE_DATA_BITS;
						$meta = $block & Block::INTERNAL_STATE_DATA_MASK;
						ConvertUtils::convertBlockData(true, $id, $meta);

						$packet->eventData = $id | ($meta << 12);
						break;
					case LevelEvent::PARTICLE_PUNCH_BLOCK:
						//TODO: BreakAnimation
						return null;
					case LevelEvent::BLOCK_START_BREAK:
						//TODO: set BreakTime
						return null;
					case LevelEvent::BLOCK_STOP_BREAK:
						//TODO: remove BreakTime

						return null;
					default:
						if(($packet->eventId & LevelEvent::ADD_PARTICLE_MASK) === LevelEvent::ADD_PARTICLE_MASK){
							$packet->eventId ^= LevelEvent::ADD_PARTICLE_MASK;
						}

						echo "LevelEventPacket: " . $packet->eventId . "\n";
						return null;
				}

				if($isSoundEffect){
					$pk = new NamedSoundEffectPacket();
					$pk->soundCategory = $category;
					$pk->effectPositionX = (int) $packet->position->x;
					$pk->effectPositionY = (int) $packet->position->y;
					$pk->effectPositionZ = (int) $packet->position->z;
					$pk->volume = 0.5;
					$pk->pitch = 1.0;
					$pk->soundName = $name;
				}elseif($isParticle){
					$pk = new ParticlePacket();
					$pk->particleId = $id;
					$pk->longDistance = false;
					$pk->x = $packet->position->x;
					$pk->y = $packet->position->y;
					$pk->z = $packet->position->z;
					$pk->offsetX = 0;
					$pk->offsetY = 0;
					$pk->offsetZ = 0;
					$pk->particleData = $packet->eventData;
					$pk->particleCount = 1;
					$pk->data = $addData;//!!!!!!!!!!!!!!!!!!!!!!!!!!!
				}else{
					$pk = new EffectPacket();
					$pk->effectId = $packet->eventId;
					$pk->x = (int) $packet->position->x;
					$pk->y = (int) $packet->position->y;
					$pk->z = (int) $packet->position->z;
					$pk->data = $packet->eventData;
					$pk->disableRelativeVolume = false;
				}

				return $pk;

			case Info::BLOCK_EVENT_PACKET:
				/** @var BlockEventPacket $packet */
				$pk = new BlockActionPacket();
				$pk->x = $packet->blockPosition->getX();
				$pk->y = $packet->blockPosition->getY();
				$pk->z = $packet->blockPosition->getZ();
				$pk->actionId = $packet->eventType;
				$pk->actionParam = $packet->eventData;
				$pk->blockType = $blockId = $session->getPlayer()->getWorld()->getBlock(new Vector3($packet->blockPosition->getX(), $packet->blockPosition->getY(), $packet->blockPosition->getZ()))->getTypeId();

				return $pk;

			case Info::SET_TITLE_PACKET:
				/** @var SetTitlePacket $packet */
				switch($packet->type){
					case SetTitlePacket::TYPE_CLEAR_TITLE:
						$pk = new TitlePacket();
						$pk->actionId = TitlePacket::TYPE_HIDE;

						return $pk;
					case SetTitlePacket::TYPE_RESET_TITLE:
						$pk = new TitlePacket();
						$pk->actionId = TitlePacket::TYPE_RESET;

						return $pk;
					case SetTitlePacket::TYPE_SET_TITLE:
						$pk = new TitlePacket();
						$pk->actionId = TitlePacket::TYPE_SET_TITLE;
						$pk->data = BigBrother::toJSON($packet->text);

						return $pk;
					case SetTitlePacket::TYPE_SET_SUBTITLE:
						$pk = new TitlePacket();
						$pk->actionId = TitlePacket::TYPE_SET_SUB_TITLE;
						$pk->data = BigBrother::toJSON($packet->text);

						return $pk;
					case SetTitlePacket::TYPE_SET_ACTIONBAR_MESSAGE:
						$pk = new TitlePacket();
						$pk->actionId = TitlePacket::TYPE_SET_ACTION_BAR;
						$pk->data = BigBrother::toJSON($packet->text);

						return $pk;
					case SetTitlePacket::TYPE_SET_ANIMATION_TIMES:
						$pk = new TitlePacket();
						$pk->actionId = TitlePacket::TYPE_SET_SETTINGS;
						$pk->data = [];
						$pk->data[0] = $packet->fadeInTime;
						$pk->data[1] = $packet->stayTime;
						$pk->data[2] = $packet->fadeOutTime;

						return $pk;
					default:
						echo "SetTitlePacket: " . $packet->type . "\n";
						break;
				}
				return null;

			case Info::ACTOR_EVENT_PACKET:
				/** @var ActorEventPacket $packet */
				switch($packet->eventId){
					case ActorEvent::HURT_ANIMATION:
						$type = $session->bigBrother_getEntityList($packet->actorRuntimeId);

						$packets = [];

						$pk = new EntityStatusPacket();
						$pk->entityStatus = 2;
						$pk->entityId = $packet->actorRuntimeId;
						$packets[] = $pk;

						$pk = new NamedSoundEffectPacket();
						$pk->soundCategory = 0;
						$pk->effectPositionX = (int) $session->getPlayer()->getPosition()->getX();
						$pk->effectPositionY = (int) $session->getPlayer()->getPosition()->getY();
						$pk->effectPositionZ = (int) $session->getPlayer()->getPosition()->getZ();
						$pk->volume = 0.5;
						$pk->pitch = 1.0;
						$pk->soundName = "entity." . $type . ".hurt";
						$packets[] = $pk;

						return $packets;
					case ActorEvent::DEATH_ANIMATION:
						$type = $session->bigBrother_getEntityList($packet->actorRuntimeId);

						$packets = [];

						$pk = new EntityStatusPacket();
						$pk->entityStatus = 3;
						$pk->entityId = $packet->actorRuntimeId;
						$packets[] = $pk;

						$pk = new NamedSoundEffectPacket();
						$pk->soundCategory = 0;
						$pk->effectPositionX = (int) $session->getPlayer()->getPosition()->getX();
						$pk->effectPositionY = (int) $session->getPlayer()->getPosition()->getY();
						$pk->effectPositionZ = (int) $session->getPlayer()->getPosition()->getZ();
						$pk->volume = 0.5;
						$pk->pitch = 1.0;
						$pk->soundName = "entity." . $type . ".death";
						$packets[] = $pk;

						return $packets;
					case ActorEvent::RESPAWN:
						//unused
						break;
					default:
						break;
				}
				return null;

			case Info::MOB_EFFECT_PACKET:
				/** @var MobEffectPacket $packet */
				switch($packet->eventId){
					case MobEffectPacket::EVENT_ADD:
					case MobEffectPacket::EVENT_MODIFY:
						$flags = 0;
						if($packet->particles){
							$flags |= 0x02;
						}

						$pk = new EntityEffectPacket();
						$pk->entityId = $packet->actorRuntimeId;
						$pk->effectId = $packet->effectId;
						$pk->amplifier = $packet->amplifier;
						$pk->duration = $packet->duration;
						$pk->flags = $flags;

						return $pk;
					case MobEffectPacket::EVENT_REMOVE:
						$pk = new RemoveEntityEffectPacket();
						$pk->entityId = $packet->actorRuntimeId;
						$pk->effectId = $packet->effectId;

						return $pk;
					default:
						echo "MobEffectPacket: " . $packet->eventId . "\n";
						break;
				}
				return null;

			case Info::UPDATE_ATTRIBUTES_PACKET:
				/** @var UpdateAttributesPacket $packet */
				$packets = [];
				$entries = [];

				/** @var \pocketmine\network\mcpe\protocol\types\entity\Attribute $entry */
				foreach($packet->entries as $entry){
					switch($entry->getId()){
						case Attribute::SATURATION: //TODO
						case Attribute::EXHAUSTION: //TODO
						case Attribute::ABSORPTION: //TODO
							break;
						case Attribute::HUNGER: //move to minecraft:health
							break;
						case Attribute::HEALTH:
							if($packet->actorRuntimeId === $session->getPlayer()->getId()){
								$pk = new UpdateHealthPacket();
								$pk->health = $entry->getCurrent();//TODO: Default Value
								$pk->food = (int) $session->getPlayer()->getHungerManager()->getFood();//TODO: Default Value
								$pk->foodSaturation = $session->getPlayer()->getHungerManager()->getSaturation();//TODO: Default Value
							}else{
								$pk = new EntityMetadataPacket();
								$pk->entityId = $packet->actorRuntimeId;
								$pk->metadata = [
									8 => [2, $entry->getCurrent()],
									"convert" => true,
								];
							}

							$packets[] = $pk;
							break;
						case Attribute::MOVEMENT_SPEED:
							$entries[] = [
								"generic.movement_speed",
								$entry->getCurrent()//TODO: Default Value
							];
							break;
						case Attribute::EXPERIENCE_LEVEL: //move to minecraft:player.experience
							break;
						case Attribute::EXPERIENCE:
							if($packet->actorRuntimeId === $session->getPlayer()->getId()){
								$pk = new SetExperiencePacket();
								$pk->experienceBar = $entry->getCurrent();//TODO: Default Value
								$pk->level = $session->getPlayer()->getXpManager()->getXpLevel();//TODO: Default Value
								$pk->totalExperience = $session->getPlayer()->getXpManager()->getLifetimeTotalXp();//TODO: Default Value

								$packets[] = $pk;
							}
							break;
						case Attribute::ATTACK_DAMAGE:
							$entries[] = [
								"generic.attack_damage",
								$entry->getCurrent()//TODO: Default Value
							];
							break;
						case Attribute::KNOCKBACK_RESISTANCE:
							$entries[] = [
								"generic.knockback_resistance",
								$entry->getCurrent()//TODO: Default Value
							];
							break;
						case Attribute::FOLLOW_RANGE:
							$entries[] = [
								"generic.follow_range",
								$entry->getCurrent()//TODO: Default Value
							];
							break;
						default:
							echo "UpdateAtteributesPacket: " . $entry->getId() . "\n";
							break;
					}
				}

				if(count($entries) > 0){
					$pk = new EntityPropertiesPacket();
					$pk->entityId = $packet->actorRuntimeId;
					$pk->entries = $entries;
					$packets[] = $pk;
				}
				return $packets;

			case Info::SET_ACTOR_MOTION_PACKET:
				/** @var SetActorMotionPacket $packet */
				$pk = new EntityVelocityPacket();
				$pk->entityId = $packet->actorRuntimeId;
				$pk->velocityX = $packet->motion->x;
				$pk->velocityY = $packet->motion->y;
				$pk->velocityZ = $packet->motion->z;
				return $pk;

			case Info::SET_HEALTH_PACKET:
				/** @var SetHealthPacket $packet */
				$pk = new UpdateHealthPacket();
				$pk->health = $packet->health;//TODO: Default Value
				$pk->food = (int) $session->getPlayer()->getHungerManager()->getFood();//TODO: Default Value
				$pk->foodSaturation = $session->getPlayer()->getHungerManager()->getSaturation();//TODO: Default Value
				return $pk;

			case Info::SET_SPAWN_POSITION_PACKET:
				/** @var SetSpawnPositionPacket $packet */
				if($packet->spawnType === SetSpawnPositionPacket::TYPE_PLAYER_SPAWN){
					$session->syncPlayerSpawnPoint(new Position($packet->spawnPosition->getX(), $packet->spawnPosition->getY(), $packet->spawnPosition->getZ(), null));
				}elseif($packet->spawnType === SetSpawnPositionPacket::TYPE_WORLD_SPAWN){
					$session->syncWorldSpawnPoint(new Position($packet->spawnPosition->getX(), $packet->spawnPosition->getY(), $packet->spawnPosition->getZ(), null));
				}
				return null;

			case Info::ANIMATE_PACKET:
				/** @var AnimatePacket $packet */
				switch($packet->action){
					case 1:
						$pk = new STCAnimatePacket();
						$pk->animation = 0;
						$pk->entityId = $packet->actorRuntimeId;
						return $pk;
					case 3: //Leave Bed
						$pk = new STCAnimatePacket();
						$pk->animation = 2;
						$pk->entityId = $packet->actorRuntimeId;
						return $pk;
					default:
						echo "AnimationPacket: " . $packet->action . "\n";
						break;
				}
				return null;

			case Info::SET_DIFFICULTY_PACKET:
				/** @var SetDifficultyPacket $packet */
				$session->syncWorldDifficulty($packet->difficulty);
				return null;

			case Info::SET_PLAYER_GAME_TYPE_PACKET:
				/** @var SetPlayerGameTypePacket $packet */
				$session->syncGameMode(match($packet->gamemode){
					ProtocolGameMode::SURVIVAL => GameMode::SURVIVAL(),
					ProtocolGameMode::CREATIVE => GameMode::CREATIVE(),
					ProtocolGameMode::ADVENTURE => GameMode::ADVENTURE(),
					ProtocolGameMode::SURVIVAL_VIEWER, ProtocolGameMode::CREATIVE_VIEWER => GameMode::SPECTATOR(),
				});
				return null;

			case Info::PLAYER_LIST_PACKET:
				/** @var PlayerListPacket $packet */
				$pk = new PlayerInfoPacket();

				if($packet->list instanceof PlayerListAdditionEntries){
					$pk->actionId = PlayerInfoPacket::TYPE_ADD;

					$loggedInPlayers = Server::getInstance()->getOnlinePlayers();
					foreach($packet->list->entries as $entry){
						$playerData = null;
						$gameMode = 0;
						$displayName = $entry->username;
						if(isset($loggedInPlayers[$entry->uuid->getBytes()])){
							$playerData = $loggedInPlayers[$entry->uuid->getBytes()];
							$gameMode = match($session->getPlayer()->getGamemode()->getEnglishName()){
								GameMode::SURVIVAL()->getEnglishName() => 0,
								GameMode::CREATIVE()->getEnglishName() => 1,
								GameMode::ADVENTURE()->getEnglishName() => 2,
								GameMode::SPECTATOR()->getEnglishName() => 3
							};
							$displayName = $playerData->getNameTag();
						}

						if($playerData instanceof DesktopNetworkSession){
							$properties = $playerData->bigBrother_getProperties();
						}else{
							//TODO: Skin Problem
							$value = [//Dummy Data
								"timestamp" => 0,
								"profileId" => str_replace("-", "", $entry->uuid->toString()),
								"profileName" => TextFormat::clean($entry->username),
								"textures" => [
									"SKIN" => [
										//TODO
									]
								]
							];

							$properties = [
								[
									"name" => "textures",
									"value" => base64_encode(json_encode($value)),
								]
							];
						}

						$pk->players[] = [
							$entry->uuid->getBytes(),
							substr(TextFormat::clean($displayName), 0, 16),
							$properties,
							$gameMode,
							0,
							true,
							BigBrother::toJSON($entry->username)
						];
					}
				}else{
					$pk->actionId = PlayerInfoPacket::TYPE_REMOVE;

					foreach($packet->list->entries as $entry){
						$pk->players[] = [
							$entry->uuid->getBytes(),
						];
					}
					break;
				}
				return $pk;

			case Info:: CHUNK_RADIUS_UPDATED_PACKET:
				/** @var ChunkRadiusUpdatedPacket $packet */
				$session->syncViewAreaRadius($packet->radius);
				return null;

			case Info::SET_DISPLAY_OBJECTIVE_PACKET:
				/** @var SetDisplayObjectivePacket $packet */

				$packets = [];

				$pk = new ScoreboardObjectivePacket();
				$pk->action = ScoreboardObjectivePacket::ACTION_ADD;
				$pk->displayName = $packet->displayName;
				$pk->type = ScoreboardObjectivePacket::TYPE_INTEGER;
				$pk->name = $packet->objectiveName;
				$packets[] = $pk;

				$pk = new DisplayScoreboardPacket();
				$pk->position = DisplayScoreboardPacket::POSITION_SIDEBAR;
				$pk->name = $packet->objectiveName;
				$packets[] = $pk;
				return $packets;

			case Info::SET_SCORE_PACKET:
				/** @var SetScorePacket $packet */
				$packets = [];
				$i = 16;
				foreach($packet->entries as $entry){
					$i--;
					$pk = new UpdateScorePacket();
					$pk->action = UpdateScorePacket::ACTION_ADD_OR_UPDATE;
					$pk->value = $i;
					$pk->objective = $entry->objectiveName;
					$pk->entry = $entry->customName;
					$packets[] = $pk;
				}
				return $packets;

			case Info::REMOVE_OBJECTIVE_PACKET:
				/** @var RemoveObjectivePacket $packet */
				$pk = new ScoreboardObjectivePacket();
				$pk->action = ScoreboardObjectivePacket::ACTION_REMOVE;
				$pk->name = $packet->objectiveName;
				return $pk;

			case Info::MODAL_FORM_REQUEST_PACKET:
				/** @var ModalFormRequestPacket $packet */
				$formData = json_decode($packet->formData, true);
				$packets = [];
				if($formData["type"] === "form"){
					$pk = new ChatMessagePacket();
					$pk->message = json_encode(["text" => TextFormat::BOLD . TextFormat::GRAY . "============ [> " . TextFormat::RESET . $formData["title"] . TextFormat::RESET . " <] ============\n" . TextFormat::RESET . $formData["content"] . TextFormat::RESET . "\n\n"]);
					$packets[] = $pk;
					foreach($formData["buttons"] as $i => $a){
						$pk = new ChatMessagePacket();
						$pk->message = json_encode(["text" => TextFormat::BOLD . TextFormat::GOLD . "[CLICK #" . $i . "] " . TextFormat::RESET . $a["text"], "clickEvent" => ["action" => "run_command", "value" => ")respondform " . $i]]);
						$packets[] = $pk;
					}
					$pk = new ChatMessagePacket();
					$pk->message = json_encode(["text" => TextFormat::BOLD . TextFormat::GOLD . "[CLOSE] ", "clickEvent" => ["action" => "run_command", "value" => ")respondform ESC"]]);
					$packets[] = $pk;
				}
				$session->bigBrother_formId = $packet->formId;
				return $packets;

			case Info::RESOURCE_PACKS_INFO_PACKET:
			case Info::RESPAWN_PACKET:
			case Info::ADVENTURE_SETTINGS_PACKET:
			case Info::AVAILABLE_COMMANDS_PACKET:
			case Info::AVAILABLE_ACTOR_IDENTIFIERS_PACKET:
			case Info::NETWORK_CHUNK_PUBLISHER_UPDATE_PACKET:
			case Info::BIOME_DEFINITION_LIST_PACKET:
			case Info::CREATIVE_CONTENT_PACKET:
				return null;
		}
		echo "[Send][Translator] 0x" . bin2hex(chr($packet->pid())) . " Not implemented\n";
		return null;
	}
}

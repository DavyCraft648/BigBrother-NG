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

namespace shoghicp\BigBrother\entity;

use pocketmine\block\ItemFrame;
use pocketmine\entity\Entity;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\world\Position;
use pocketmine\world\World;
use Ramsey\Uuid\Uuid;
use shoghicp\BigBrother\network\DesktopNetworkSession;
use shoghicp\BigBrother\network\protocol\Play\Server\DestroyEntitiesPacket;
use shoghicp\BigBrother\network\protocol\Play\Server\SpawnEntityPacket;
use shoghicp\BigBrother\network\protocol\Play\Server\EntityMetadataPacket;
use shoghicp\BigBrother\utils\ConvertUtils;
use shoghicp\BigBrother\DesktopPlayer;
use function array_diff;

class ItemFrameBlockEntity extends Position{
	/** @var ItemFrameBlockEntity[][] */
	protected static array $itemFrames = [];
	/** @var ItemFrameBlockEntity[][] */
	protected static array $itemFramesAt = [];
	/** @var ItemFrameBlockEntity[][][] */
	protected static array $itemFramesInChunk = [];

	private static array $mapping = [
		Facing::EAST => [-90, 3],
		Facing::WEST => [+90, 1],
		Facing::SOUTH => [0, 0],
		Facing::NORTH => [-180, 2]
	];

	private int $eid;
	private string $uuid;
	private int $yaw;

	private function __construct(World $world, int $x, int $y, int $z, private int $facing){
		parent::__construct($x, $y, $z, $world);
		$this->eid = Entity::nextRuntimeId();
		$this->uuid = Uuid::uuid4()->getBytes();
		$this->yaw = self::$mapping[$facing][0] ?? 0;
	}

	/**
	 * @return int
	 */
	public function getEntityId() : int{
		return $this->eid;
	}

	/**
	 * @return int
	 */
	public function getFacing() : int{
		return $this->facing;
	}

	/**
	 * @return bool
	 */
	public function hasItem() : bool{
		$block = $this->getWorld()->getBlock($this);
		if($block instanceof ItemFrame){
			return $block->getFramedItem() !== null;
		}

		return false;
	}

	public function spawnTo(DesktopNetworkSession $player){
		$pk = new SpawnEntityPacket();
		$pk->entityId = $this->eid;
		$pk->uuid = $this->uuid;
		$pk->type = SpawnEntityPacket::ITEM_FRAMES;
		$pk->x = $this->x;
		$pk->y = $this->y;
		$pk->z = $this->z;
		$pk->yaw = $this->yaw;
		$pk->pitch = 0;
		$pk->data = self::$mapping[$this->facing][1] ?? 0;
		$pk->sendVelocity = true;
		$pk->velocityX = 0;
		$pk->velocityY = 0;
		$pk->velocityZ = 0;
		$player->putRawPacket($pk);

		$pk = new EntityMetadataPacket();
		$pk->entityId = $this->eid;
		$pk->metadata = ["convert" => true];

		$block = $this->getWorld()->getTile($this);
		if($block instanceof ItemFrame){
			$item = $block->getFramedItem() ?? VanillaItems::AIR();

			/*if($item->getId() === Item::FILLED_MAP){
				$mapId = $item->getNamedTag()->getLong("map_uuid");
				if($mapId !== null){
					// store $mapId as meta
					$item->setDamage($mapId);

					$req  = new MapInfoRequestPacket();
					$req->mapId = $mapId;
					$player->handleDataPacket($req);
				}
			}*/

			ConvertUtils::convertItemData(true, $item);
			$pk->metadata[6] = [5, $item];
			$pk->metadata[7] = [1, $block->getItemRotation()];
		}

		$player->putRawPacket($pk);
	}

	/**
	 * @param DesktopPlayer $player
	 */
	public function despawnFrom(DesktopPlayer $player) : void{
		$pk = new DestroyEntitiesPacket();
		$pk->entityIds[] = $this->eid;
		$player->putRawPacket($pk);
	}

	public function despawnFromAll() : void{
		foreach($this->getWorld()->getChunkLoaders($this->x >> 4, $this->z >> 4) as $player){
			if($player instanceof DesktopPlayer){
				$this->despawnFrom($player);
			}
		}
		self::removeItemFrame($this);
	}

	public static function exists(World $world, int $x, int $y, int $z) : bool{
		return isset(self::$itemFramesAt[$world->getId()][World::blockHash($x, $y, $z)]);
	}

	public static function getItemFrame(World $world, int $x, int $y, int $z, int $facing = Facing::DOWN, bool $create = false) : ?ItemFrameBlockEntity{
		$entity = null;

		if(isset(self::$itemFramesAt[$world_id = $world->getId()][$index = World::blockHash($x, $y, $z)])){
			$entity = self::$itemFramesAt[$world_id][$index];
		}elseif($create){
			$entity = new ItemFrameBlockEntity($world, $x, $y, $z, $facing);
			self::$itemFrames[$world_id][$entity->eid] = $entity;
			self::$itemFramesAt[$world_id][$index] = $entity;

			if(!isset(self::$itemFramesInChunk[$world_id][$index = World::chunkHash($x >> 4, $z >> 4)])){
				self::$itemFramesInChunk[$world_id][$index] = [];
			}
			self::$itemFramesInChunk[$world_id][$index] [] = $entity;
		}

		return $entity;
	}

	public static function getItemFrameById(World $world, int $eid) : ?ItemFrameBlockEntity{
		return self::$itemFrames[$world->getId()][$eid] ?? null;
	}

	public static function getItemFrameByBlock(ItemFrame $block, bool $create = false) : ?ItemFrameBlockEntity{
		$pos = $block->getPosition();
		return self::getItemFrame($pos->getWorld(), $pos->x, $pos->y, $pos->z, $block->getFacing(), $create);
	}

	public static function getItemFramesInChunk(World $world, int $x, int $z) : array{
		return self::$itemFramesInChunk[$world->getId()][World::chunkHash($x, $z)] ?? [];
	}

	public static function removeItemFrame(ItemFrameBlockEntity $entity) : void{
		unset(self::$itemFrames[$entity->world->getId()][$entity->eid]);
		unset(self::$itemFramesAt[$entity->world->getId()][World::blockHash($entity->x, $entity->y, $entity->z)]);
		if(isset(self::$itemFramesInChunk[$world_id = $entity->getWorld()->getId()][$index = World::chunkHash($entity->x >> 4, $entity->z >> 4)])){
			self::$itemFramesInChunk[$world_id][$index] = array_diff(self::$itemFramesInChunk[$world_id][$index], [$entity]);
		}
	}
}

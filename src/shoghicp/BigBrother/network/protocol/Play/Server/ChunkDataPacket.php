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

namespace shoghicp\BigBrother\network\protocol\Play\Server;

use pocketmine\nbt\tag\CompoundTag;
use shoghicp\BigBrother\network\OutboundPacket;
use shoghicp\BigBrother\utils\ConvertUtils;

class ChunkDataPacket extends OutboundPacket{

	/** @var int */
	public $chunkX;
	/** @var int */
	public $chunkZ;
	/** @var bool */
	public $isFullChunk;
	/** @var int */
	public $primaryBitmap;
	/** @var CompoundTag */
	public $heightMaps;
	/** @var string */
	public $payload;
	/** @var string */
	public $biomes;
	/** @var array */
	public $blockEntities = [];

	public function pid() : int{
		return self::CHUNK_DATA_PACKET;
	}

	protected function encode() : void{
		$this->putInt($this->chunkX);
		$this->putInt($this->chunkZ);
		$this->putBool($this->isFullChunk);
		$this->putVarInt($this->primaryBitmap);
		$this->put(ConvertUtils::convertNBTDataFromPEtoPC($this->heightMaps));
		$this->putVarInt(strlen($this->payload));
		$this->put($this->payload);
		$this->putVarInt(count($this->blockEntities));
		foreach($this->blockEntities as $blockEntity){
			$this->put(ConvertUtils::convertNBTDataFromPEtoPC(ConvertUtils::convertBlockEntity(true, $blockEntity)));
		}
	}
}

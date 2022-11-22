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

namespace shoghicp\BigBrother;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\convert\RuntimeBlockMapping;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;
use shoghicp\BigBrother\entity\ItemFrameBlockEntity;
use shoghicp\BigBrother\nbt\tag\LongArrayTag;
use shoghicp\BigBrother\network\DesktopNetworkSession;
use shoghicp\BigBrother\utils\Binary;
use shoghicp\BigBrother\utils\ConvertUtils;
use function array_search;
use function chr;
use function count;
use function microtime;
use function ord;
use function strlen;
use function strrev;

class DesktopChunk{
	private World $world;
	private int $chunkBitmask = 0;
	private bool $isFullChunk = false;
	private string $chunkData;
	private CompoundTag $heightMaps;
	private string $biomes;
	private int $skyLightBitMask = 0;
	private int $blockLightBitMask = 0;
	/** @var string[] */
	private array $skyLight = [];
	/** @var string[] */
	private array $blockLight = [];

	public function __construct(private DesktopNetworkSession $session, private int $chunkX, private int $chunkZ){
		$this->world = $session->getPlayer()->getWorld();

		$start = microtime(true);
		$this->generateChunk();
		$this->generateHeightMaps();
		echo "Chunk generate takes " . (microtime(true) - $start) . "\n";
	}

	public function generateChunk() : void{
		$chunk = $this->world->getChunk($this->chunkX, $this->chunkZ);
		$this->isFullChunk = $chunk->getHeight() === Chunk::MAX_SUBCHUNKS;
		$this->biomes = $chunk->getBiomeIdArray();

		$payload = "";

		foreach($chunk->getSubChunks() as $num => $subChunk){
			if($num < 0 || $num > 15){
				continue;
			}
			if($subChunk->isEmptyAuthoritative()){
				continue;
			}

			$this->chunkBitmask |= (0x01 << $num);
			$this->skyLightBitMask |= (0x01 << $num + 1);
			$this->blockLightBitMask |= (0x01 << $num + 1);

			$palette = [];
			$blockCount = 0;
			$bitsPerBlock = 8;

			$chunkData = "";
			for($y = 0; $y < 16; ++$y){
				for($z = 0; $z < 16; ++$z){

					$data = "";
					for($x = 0; $x < 16; ++$x){
						$stateId = $subChunk->getFullBlock($x, $y, $z);
						$typeId = $stateId >> Block::INTERNAL_STATE_DATA_BITS;
						$stateData = $stateId & Block::INTERNAL_STATE_DATA_MASK;

						if($typeId === BlockTypeIds::ITEM_FRAME){
							ItemFrameBlockEntity::getItemFrame($this->session->getPlayer()->getWorld(), $x + ($this->chunkX << 4), $y + ($num << 4), $z + ($this->chunkZ << 4), $stateData, true);
							$block = 0;
						}else{
							if($typeId !== BlockTypeIds::AIR){
								$blockCount++;
							}

							$stateId = ConvertUtils::getBlockStateIndex(RuntimeBlockMapping::getInstance()->toRuntimeId($stateId, RuntimeBlockMapping::getMappingProtocol(ProtocolInfo::CURRENT_PROTOCOL)));
							$block = $stateId;
						}

						if(($key = array_search($block, $palette, true)) === false){
							$key = count($palette);
							$palette[$key] = $block;
						}
						$data .= chr($key);

						if($x === 7 || $x === 15){//Reset ChunkData
							$chunkData .= strrev($data);
							$data = "";
						}
					}
				}
			}

			$blockLightData = "";
			$skyLightData = "";
			for($y = 0; $y < 16; ++$y){
				for($z = 0; $z < 16; ++$z){
					for($x = 0; $x < 16; $x += 2){
						$blockLight = $subChunk->getBlockLightArray()->get($x, $y, $z) | ($subChunk->getBlockLightArray()->get($x + 1, $y, $z) << 4);
						$skyLight = $subChunk->getBlockSkyLightArray()->get($x, $y, $z) | ($subChunk->getBlockSkyLightArray()->get($x + 1, $y, $z) << 4);

						$blockLightData .= chr($blockLight);
						$skyLightData .= chr($skyLight);
					}
				}
			}
			$this->skyLight[] = $skyLightData;
			$this->blockLight[] = $blockLightData;

			/* Bits Per Block & Palette Length */
			$payload .= Binary::writeShort($blockCount) . Binary::writeByte($bitsPerBlock) . Binary::writeComputerVarInt(count($palette));

			/* Palette */
			foreach($palette as $value){
				$payload .= Binary::writeComputerVarInt($value);
			}

			/* Data Array Length */
			$payload .= Binary::writeComputerVarInt(strlen($chunkData) / 8);

			/* Data Array */
			$payload .= $chunkData;
		}

		$this->chunkData = $payload;
	}

	public function generateHeightMaps() : void{
		$chunk = $this->world->getChunk($this->chunkX, $this->chunkZ);

		$long = 0x00;
		$longData = [];
		$shiftCount = 0;
		foreach($chunk->getHeightMapArray() as $value){
			$long <<= 9;
			$long |= ($value & 0x1fff);
			$shiftCount++;
			if($shiftCount === 7){
				$longData[] = $long;
				$long = 0x00;
				$shiftCount = 0;
			}
		}
		$longData[] = $long;

		$this->heightMaps = CompoundTag::create()->setTag("MOTION_BLOCKING", new LongArrayTag($longData));

		$payload = "";
		for($i = 0; $i < 256; $i++){
			$payload .= Binary::writeInt(ord($chunk->getBiomeIdArray()[$i]));
		}
		$this->biomes = $payload;
	}

	public function getChunkBitMask() : int{
		return $this->chunkBitmask;
	}

	public function isFullChunk() : bool{
		return $this->isFullChunk;
	}

	public function getChunkData() : string{
		return $this->chunkData;
	}

	public function getHeightMaps() : CompoundTag{
		return $this->heightMaps;
	}

	public function getBiomes() : string{
		return $this->biomes;
	}

	public function getSkyLightBitMask() : int{
		return $this->skyLightBitMask;
	}

	public function getBlockLightBitMask() : int{
		return $this->blockLightBitMask;
	}

	public function getSkyLight() : array{
		return $this->skyLight;
	}

	public function getBlockLight() : array{
		return $this->blockLight;
	}
}

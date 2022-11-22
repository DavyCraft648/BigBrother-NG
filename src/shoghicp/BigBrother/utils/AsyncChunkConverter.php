<?php

declare(strict_types=1);

namespace shoghicp\BigBrother\utils;

use pocketmine\block\Block;
use pocketmine\scheduler\AsyncTask;
use pocketmine\world\format\Chunk;
use shoghicp\BigBrother\DesktopPlayer;
use shoghicp\BigBrother\network\protocol\Play\Server\ChunkDataPacket;
use function array_search;
use function chr;
use function count;
use function strlen;
use function strrev;

class AsyncChunkConverter extends AsyncTask{
	public string $payload;
	public string $chunk;
	public int $dimension;
	public int $bitMap = 0;
	public string $biomes;
	public int $chunkX;
	public int $chunkZ;

	public function __construct(DesktopPlayer $player, Chunk $chunk){
		$this->storeLocal($player);
		$this->chunkX = $chunk->getX();
		$this->chunkZ = $chunk->getZ();
		$this->chunk = $chunk->fastSerialize();
		$this->dimension = $player->bigBrother_getDimension();
	}

	public function onRun() : void{
		$chunk = Chunk::fastDeserialize($this->chunk);

		$this->biomes = $chunk->getBiomeIdArray();

		$payload = "";

		ConvertUtils::lazyLoad();

		foreach($chunk->getSubChunks() as $num => $subChunk){
			if($subChunk->isEmpty()){
				continue;
			}

			$this->bitMap |= 0x01 << $num;

			$palette = [];
			$bitsPerBlock = 8;

			$chunkData = "";
			for($y = 0; $y < 16; ++$y){
				for($z = 0; $z < 16; ++$z){

					$data = "";
					for($x = 0; $x < 16; ++$x){
						$blockId = $subChunk->getBlockId($x, $y, $z);
						$blockData = $subChunk->getBlockData($x, $y, $z);

						if($blockId == Block::FRAME_BLOCK){
							//ItemFrameBlockEntity::getItemFrame($this->player->getLevel(), $x + ($this->chunkX << 4), $y + ($num << 4), $z + ($this->chunkZ << 4), $blockData, true);
							$block = Block::AIR;
						}else{
							ConvertUtils::convertBlockData(true, $blockId, $blockData);
							$block = (int) ($blockId << 4) | $blockData;
						}

						if(($key = array_search($block, $palette, true)) === false){
							$key = count($palette);
							$palette[$key] = $block;
						}
						$data .= chr($key);//bit

						if($x === 7 or $x === 15){//Reset ChunkData
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
						$blockLight = $subChunk->getBlockLight($x, $y, $z) | ($subChunk->getBlockLight($x + 1, $y, $z) << 4);
						$skyLight = $subChunk->getBlockSkyLight($x, $y, $z) | ($subChunk->getBlockSkyLight($x + 1, $y, $z) << 4);

						$blockLightData .= chr($blockLight);
						$skyLightData .= chr($skyLight);
					}
				}
			}

			/* Bits Per Block & Palette Length */
			$payload .= Binary::writeByte($bitsPerBlock) . Binary::writeComputerVarInt(count($palette));

			/* Palette */
			foreach($palette as $value){
				$payload .= Binary::writeComputerVarInt($value);
			}

			/* Data Array Length */
			$payload .= Binary::writeComputerVarInt(strlen($chunkData) / 8);

			/* Data Array */
			$payload .= $chunkData;

			/* Block Light*/
			$payload .= $blockLightData;

			/* Sky Light Only Over World */
			if($this->dimension === 0){
				$payload .= $skyLightData;
			}
		}
		$this->payload = $payload;
	}

	public function onCompletion() : void{
		$player = $this->fetchLocal();
		if($player instanceof DesktopPlayer && $player->isConnected()){
			$blockEntities = [];
			foreach($player->getLevel()->getChunkTiles($this->chunkX, $this->chunkZ) as $tile){
				if($tile instanceof Spawnable){
					$blockEntities[] = clone $tile->getSpawnCompound();
				}
			}

			$pk = new ChunkDataPacket();
			$pk->payload = $this->payload;
			$pk->biomes = $this->biomes;
			$pk->groundUp = true;
			$pk->chunkX = $this->chunkX;
			$pk->chunkZ = $this->chunkZ;
			$pk->primaryBitmap = $this->bitMap;
			$pk->blockEntities = $blockEntities;

			$player->putRawPacket($pk);
		}
	}
}

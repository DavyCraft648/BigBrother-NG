<?php

declare(strict_types=1);

namespace shoghicp\BigBrother\network;

use Closure;
use pocketmine\block\tile\Spawnable;
use pocketmine\entity\Skin;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\compression\Compressor;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\PacketBroadcaster;
use pocketmine\network\mcpe\PacketSender;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\protocol\ServerboundPacket;
use pocketmine\network\mcpe\protocol\TextPacket;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\network\NetworkSessionManager;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\timings\Timings;
use pocketmine\utils\Internet;
use pocketmine\world\Position;
use pocketmine\world\World;
use Ramsey\Uuid\Uuid;
use shoghicp\BigBrother\BigBrother;
use shoghicp\BigBrother\DesktopChunk;
use shoghicp\BigBrother\entity\ItemFrameBlockEntity;
use shoghicp\BigBrother\network\protocol\Login\EncryptionRequestPacket;
use shoghicp\BigBrother\network\protocol\Login\EncryptionResponsePacket;
use shoghicp\BigBrother\network\protocol\Login\LoginSuccessPacket;
use shoghicp\BigBrother\network\protocol\Play\Server\ChangeGameStatePacket;
use shoghicp\BigBrother\network\protocol\Play\Server\ChatMessagePacket;
use shoghicp\BigBrother\network\protocol\Play\Server\ChunkDataPacket;
use shoghicp\BigBrother\network\protocol\Play\Server\PlayerAbilitiesPacket;
use shoghicp\BigBrother\network\protocol\Play\Server\PlayerPositionAndLookPacket;
use shoghicp\BigBrother\network\protocol\Play\Server\ServerDifficultyPacket;
use shoghicp\BigBrother\network\protocol\Play\Server\SpawnPositionPacket;
use shoghicp\BigBrother\network\protocol\Play\Server\TimeUpdatePacket;
use shoghicp\BigBrother\network\protocol\Play\Server\UnloadChunkPacket;
use shoghicp\BigBrother\network\protocol\Play\Server\UpdateLightPacket;
use shoghicp\BigBrother\network\protocol\Play\Server\UpdateViewDistancePacket;
use shoghicp\BigBrother\network\protocol\Play\Server\UpdateViewPositionPacket;
use shoghicp\BigBrother\utils\Binary;
use shoghicp\BigBrother\utils\InventoryUtils;
use shoghicp\BigBrother\utils\RecipeUtils;
use shoghicp\BigBrother\utils\SkinImage;
use function array_reduce;
use function base64_decode;
use function base64_encode;
use function file_get_contents;
use function hexdec;
use function http_build_query;
use function is_array;
use function is_null;
use function json_decode;
use function json_encode;
use function mt_rand;
use function str_repeat;
use function str_replace;
use function str_split;
use function strlen;
use function var_dump;

class DesktopNetworkSession extends NetworkSession{
	public int $status = 0;

	private string $checkToken;
	private string $secret;

	public string $username = "";
	public string $uuid = "";
	public string $formattedUUID = "";

	/**
	 * @var int[]|Vector3[]
	 * @phpstan-var array{Vector3, int}
	 */
	public array $bigBrother_breakPosition;
	protected array $bigBrother_properties = [];
	/** @var string[] */
	private array $entityList = [];
	private array $clientSettings = [];
	private int $bigBrother_dimension = 0;
	private array $bigBrother_bossBarData = [
		"entityRuntimeId" => -1,
		"uuid" => "",
		"nameTag" => ""
	];
	public int $bigBrother_formId;

	private InventoryUtils $inventoryUtils;
	private RecipeUtils $recipeUtils;


	public function __construct(Server $server, NetworkSessionManager $manager, PacketPool $packetPool, PacketSender $sender, PacketBroadcaster $broadcaster, Compressor $compressor, string $ip, int $port, private BigBrother $plugin){
		parent::__construct($server, $manager, $packetPool, $sender, $broadcaster, $compressor, $ip, $port);
		$this->bigBrother_breakPosition = [new Vector3(0, 0, 0), 0];
		$this->inventoryUtils = new InventoryUtils($this);
		$this->recipeUtils = new RecipeUtils($this);
	}

	public function getInventoryUtils() : InventoryUtils{
		return $this->inventoryUtils;
	}

	public function getRecipeUtils() : RecipeUtils{
		return $this->recipeUtils;
	}

	public function bigBrother_getDimension() : int{
		return $this->bigBrother_dimension;
	}

	/**
	 * @param int $level_dimension
	 *
	 * @return int dimension of pc version converted from $level_dimension
	 */
	public function bigBrother_getDimensionPEToPC(int $level_dimension) : int{
		$dimension = match($level_dimension){
			DimensionIds::NETHER => -1,
			DimensionIds::THE_END => 1,
			default => 0,
		};
		$this->bigBrother_dimension = $dimension;
		return $dimension;
	}

	public function bigBrother_getBossBarData(string $bossBarData = "") : array|int|string{
		if($bossBarData === ""){
			return $this->bigBrother_bossBarData;
		}
		return $this->bigBrother_bossBarData[$bossBarData];
	}

	public function bigBrother_setBossBarData(string $bossBarData, array|int|string $data) : void{
		$this->bigBrother_bossBarData[$bossBarData] = $data;
	}

	public function stopUsingChunk(int $chunkX, int $chunkZ) : void{
		$pk = new UnloadChunkPacket();
		$pk->chunkX = $chunkX;
		$pk->chunkZ = $chunkZ;
		$this->putRawPacket($pk);

		if($this->getPlayer() !== null){
			foreach(ItemFrameBlockEntity::getItemFramesInChunk($this->getPlayer()->getWorld(), $chunkX, $chunkZ) as $frame){
				$frame->despawnFromAll();
			}
		}
	}

	public function putRawPacket(OutboundPacket $packet) : void{
		$this->plugin->getInterface()->putRawPacket($this, $packet);
	}

	public function handleDataPacketNoDecode(ServerboundPacket $packet) : void{
		$timings = Timings::getHandleDataPacketTimings($packet);
		$timings->startTiming();
		try{
			$ev = new DataPacketReceiveEvent($this, $packet);
			$ev->call();
			if(!$ev->isCancelled() && ($this->getHandler() === null || !$packet->handle($this->getHandler()))){
				$this->plugin->getLogger()->debug("Unhandled " . $packet->getName());
			}
		}finally{
			$timings->stopTiming();
		}
	}

	public function respawn() : void{
		$pk = new PlayerPositionAndLookPacket();
		$pk->x = $this->getPlayer()->getPosition()->getX();
		$pk->y = $this->getPlayer()->getPosition()->getY();
		$pk->z = $this->getPlayer()->getPosition()->getZ();
		$pk->yaw = 0;
		$pk->pitch = 0;
		$pk->flags = 0;
		$this->putRawPacket($pk);

		foreach($this->getPlayer()->getUsedChunks() as $index => $d){//reset chunks
			World::getXZ($index, $chunkX, $chunkZ);
			$ref = new \ReflectionMethod($this->getPlayer(), "unloadChunk");
			$ref->setAccessible(true);
			$ref->invoke($this->getPlayer(), $chunkX, $chunkZ);
		}
	}

	public function addToSendBuffer(ClientboundPacket $packet) : void{
		$packets = $this->plugin->getTranslator()->serverToInterface($this, $packet);
		if($packets !== null){
			/** @var int $target */
			if(is_array($packets)){
				foreach($packets as $packet){
					$this->putRawPacket($packet);
				}
			}else{
				$this->putRawPacket($packets);
			}
		}
	}

	public function notifyTerrainReady() : void{
		$this->getLogger()->debug("forcing spawn");
		$pk = new PlayerPositionAndLookPacket();
		$pos = $this->getPlayer()->getLocation();
		$pk->x = $pos->x;
		$pk->y = $pos->y;
		$pk->z = $pos->z;
		$pk->yaw = $pos->yaw;
		$pk->pitch = $pos->pitch;
		$pk->flags = 0;
		$this->putRawPacket($pk);
		$method = (new \ReflectionClass($this))->getParentClass()->getMethod("onClientSpawnResponse");
		$method->setAccessible(true);
		$method->invoke($this);
	}

	public function syncViewAreaRadius(int $distance) : void{
		$pk = new UpdateViewDistancePacket();
		$pk->viewDistance = $distance * 2;
		$this->putRawPacket($pk);
	}

	public function syncViewAreaCenterPoint(Vector3 $newPos, int $viewDistance) : void{
		$pk = new UpdateViewPositionPacket();
		$pk->chunkX = $newPos->getX() >> 4;
		$pk->chunkZ = $newPos->getZ() >> 4;
		$this->putRawPacket($pk);

		$pk = new UpdateViewDistancePacket();
		$pk->viewDistance = $viewDistance * 2;
		$this->putRawPacket($pk);
	}

	public function syncPlayerSpawnPoint(Position $newSpawn) : void{
		$pk = new SpawnPositionPacket();
		$pk->x = $newSpawn->x;
		$pk->y = $newSpawn->y;
		$pk->z = $newSpawn->z;
		$this->putRawPacket($pk);
	}

	public function syncGameMode(GameMode $mode, bool $isRollback = false) : void{
		$pk = new ChangeGameStatePacket();
		$pk->reason = 3;
		$pk->value = match($mode->getEnglishName()){
			GameMode::SURVIVAL()->getEnglishName() => 0,
			GameMode::CREATIVE()->getEnglishName() => 1,
			GameMode::ADVENTURE()->getEnglishName() => 2,
			GameMode::SPECTATOR()->getEnglishName() => 3
		};
		$this->putRawPacket($pk);
		if($this->getPlayer() !== null){
			$this->syncAbilities($this->getPlayer());
		}
	}

	public function syncAbilities(Player $for) : void{
		$pk = new PlayerAbilitiesPacket();
		$pk->flyingSpeed = 0.05;
		$pk->viewModifierField = 0.1;
		$pk->canFly = $for->getAllowFlight();
		$pk->damageDisabled = $for->isCreative();
		$pk->isFlying = false;
		$pk->isCreative = $for->isCreative();
		$this->putRawPacket($pk);
	}

	/*public function syncAvailableCommands() : void{
		$buffer = "";
		$commands = Server::getInstance()->getCommandMap()->getCommands();
		$commandData = [];
		foreach($commands as $name => $command){
			if(isset($commandData[$command->getName()]) || !$command->testPermissionSilent($this->getPlayer())){
				continue;
			}
			$commandData[] = $command;
		}
		$commandCount = count($commandData);
		$buffer .= Binary::writeVarInt($commandCount * 2 + 1);
		$buffer .= Binary::writeByte(0);
		$buffer .= Binary::writeVarInt($commandCount);
		for($i = 1; $i <= $commandCount * 2; $i++){
			$buffer .= Binary::writeVarInt($i++);
		}
		$i = 1;
		foreach($commandData as $command){
			$buffer .= Binary::writeByte(1 | 0x04);
			$buffer .= Binary::writeVarInt(1);
			$buffer .= Binary::writeVarInt($i + 1);
			$buffer .= Binary::writeVarInt(strlen($command->getName())) . $command->getName();
			$i++;

			$buffer .= Binary::writeByte(2 | 0x04 | 0x10);
			$buffer .= Binary::writeVarInt(1);
			$buffer .= Binary::writeVarInt($i);
			$buffer .= Binary::writeVarInt(strlen("arg")) . "arg";
			$buffer .= Binary::writeVarInt(strlen("brigadier:string")) . "brigadier:string";
			$buffer .= Binary::writeVarInt(0);
			$buffer .= Binary::writeVarInt(strlen("minecraft:ask_server")) . "minecraft:ask_server";
			$i++;
		}
		$buffer .= Binary::writeVarInt(0);
		$this->putRawPacket(OutboundPacket::DECLARE_COMMANDS_PACKET, $buffer);
	}*/

	public function onRawChatMessage(string $message, bool $system = false) : void{
		$pk = new ChatMessagePacket();
		$pk->message = BigBrother::toJSON($message);
		$pk->position = $system ? 1 : 0;
		$pk->sender = str_repeat("\x00", 16);
		$this->putRawPacket($pk);
	}

	public function onTranslatedChatMessage(string $key, array $parameters) : void{
		$pk = new ChatMessagePacket();
		$pk->message = BigBrother::toJSON($key, TextPacket::TYPE_TRANSLATION, $parameters);
		$pk->position = 0;
		$pk->sender = str_repeat("\x00", 16);
		$this->putRawPacket($pk);
	}

	public function onJukeboxPopup(string $key, array $parameters) : void{

	}

	public function onPopup(string $message) : void{
		$pk = new ChatMessagePacket();
		$pk->message = BigBrother::toJSON($message, TextPacket::TYPE_POPUP, []);
		$pk->position = 2;
		$pk->sender = str_repeat("\x00", 16);
		$this->putRawPacket($pk);
	}

	public function onTip(string $message) : void{
		$this->onPopup($message);
	}

	public function startUsingChunk(int $chunkX, int $chunkZ, Closure $onCompletion) : void{
		$blockEntities = [];
		foreach($this->getPlayer()->getWorld()->getChunk($chunkX, $chunkZ)->getTiles() as $tile){
			if($tile instanceof Spawnable){
				$blockEntities[] = clone $tile->getSpawnCompound();
			}
		}
		$chunk = new DesktopChunk($this, $chunkX, $chunkZ);
		$pk = new UpdateLightPacket();
		$pk->chunkX = $chunkX;
		$pk->chunkZ = $chunkZ;
		$pk->skyLightMask = $chunk->getSkyLightBitMask();
		$pk->blockLightMask = $chunk->getBlockLightBitMask();
		$pk->emptySkyLightMask = ~$chunk->getSkyLightBitMask();
		$pk->emptyBlockLightMask = ~$chunk->getBlockLightBitMask();
		$pk->skyLight = $chunk->getSkyLight();
		$pk->blockLight = $chunk->getBlockLight();
		$this->putRawPacket($pk);

		$pk = new ChunkDataPacket();
		$pk->chunkX = $chunkX;
		$pk->chunkZ = $chunkZ;
		$pk->isFullChunk = $chunk->isFullChunk();
		$pk->primaryBitMask = $chunk->getChunkBitMask();
		$pk->heightMaps = $chunk->getHeightMaps();
		$pk->biomes = $chunk->getBiomes();
		$pk->data = $chunk->getChunkData();
		$pk->blockEntities = $blockEntities;
		$this->putRawPacket($pk);
		if($this->getPlayer() !== null){
			foreach(ItemFrameBlockEntity::getItemFramesInChunk($this->getPlayer()->getWorld(), $chunkX, $chunkZ) as $frame){
				$frame->spawnTo($this);
			}
		}
		parent::startUsingChunk($chunkX, $chunkZ, $onCompletion);
	}

	public function syncWorldTime(int $worldTime) : void{
		$pk = new TimeUpdatePacket();
		$pk->worldAge = $worldTime;
		$pk->dayTime = $worldTime;
		$this->putRawPacket($pk);
	}

	public function syncWorldDifficulty(int $worldDifficulty) : void{
		$pk = new ServerDifficultyPacket();
		$pk->difficulty = $worldDifficulty;
		$this->putRawPacket($pk);
	}

	public function bigBrother_getProperties() : array{
		return $this->bigBrother_properties;
	}

	public function bigBrother_processAuthentication(EncryptionResponsePacket $packet) : void{
		$this->secret = $this->plugin->decryptBinary($packet->sharedSecret);//todo
		$token = $this->plugin->decryptBinary($packet->verifyToken);//todo
		$this->plugin->getInterface()->enableEncryption($this, $this->secret);
		if($token !== $this->checkToken){
			$this->disconnect("Invalid check token");
		}else{
			$username = $this->username;
			$hash = Binary::sha1("" . $this->secret . $this->plugin->getASN1PublicKey());

			Server::getInstance()->getAsyncPool()->submitTask(new class($this, $username, $hash) extends AsyncTask{
				public function __construct(DesktopNetworkSession $networkSession, private string $username, private string $hash){
					$this->storeLocal("bigbrother:networksession", $networkSession);
				}

				public function onRun() : void{
					$query = http_build_query([
						"username" => $this->username,
						"serverId" => $this->hash
					]);

					$response = Internet::getURL("https://sessionserver.mojang.com/session/minecraft/hasJoined?" . $query, 5, [], $err);
					if($response->getCode() !== 200){
						$this->publishProgress("InternetException: failed to fetch session data for '$this->username'; status={$response?->getCode()}; err=$err; response_header=" . json_encode($response?->getHeaders() ?? []));
						$this->setResult(false);
						return;
					}

					$this->setResult(json_decode($response->getBody(), true));
				}

				public function onProgressUpdate($progress) : void{
					Server::getInstance()->getLogger()->error($progress);
				}

				public function onCompletion() : void{
					$result = $this->getResult();
					/** @var DesktopNetworkSession $networkSession */
					$networkSession = self::fetchLocal("bigbrother:networksession");
					if(is_array($result) && isset($result["id"])){
						$networkSession->bigBrother_authenticate($result["id"], $result["properties"]);
					}else{
						$networkSession->disconnect("User not premium", true);
					}
				}
			});
		}
	}

	public function bigBrother_authenticate(string $uuid, ?array $onlineModeData = null) : void{
		if($this->status === 0){
			$this->uuid = $uuid;
			$this->formattedUUID = Uuid::fromString($this->uuid)->getBytes();

			$this->plugin->getInterface()->setCompression($this);

			$pk = new LoginSuccessPacket();

			$pk->uuid = $this->formattedUUID;
			$pk->name = $this->username;

			$this->putRawPacket($pk);

			$this->status = 1;

			if($onlineModeData !== null){
				$this->bigBrother_properties = $onlineModeData;
			}

			$model = false;
			$skinImage = "";
			$capeImage = "";
			foreach($this->bigBrother_properties as $property){
				if($property["name"] === "textures"){
					$textures = json_decode(base64_decode($property["value"]), true);

					if(isset($textures["textures"]["SKIN"])){
						if(isset($textures["textures"]["SKIN"]["metadata"]["model"])){
							$model = true;
						}

						$skinImage = file_get_contents($textures["textures"]["SKIN"]["url"]);
					}else{
						/*
						 * Detect whether the player has the “Alex?” or “Steve?”
						 * Ref) https://github.com/mapcrafter/mapcrafter-playermarkers/blob/c583dd9157a041a3c9ec5c68244f73b8d01ac37a/playermarkers/player.php#L8-L19
						 */
						if((bool) (array_reduce(str_split($uuid, 8), function($acm, $val){
								return $acm ^ hexdec($val);
							}, 0) % 2)){
							$skinImage = file_get_contents("http://assets.mojang.com/SkinTemplates/alex.png");
							$model = true;
						}else{
							$skinImage = file_get_contents("http://assets.mojang.com/SkinTemplates/steve.png");
						}
					}

					if(isset($textures["textures"]["CAPE"])){
						$capeImage = file_get_contents($textures["textures"]["CAPE"]["url"]);
					}
				}
			}
			if($model){
				$SkinId = $this->formattedUUID . "_CustomSlim";
				$SkinResourcePatch = base64_encode(json_encode(["geometry" => ["default" => "geometry.humanoid.customSlim"]]));
			}else{
				$SkinId = $this->formattedUUID . "_Custom";
				$SkinResourcePatch = base64_encode(json_encode(["geometry" => ["default" => "geometry.humanoid.custom"]]));
			}

			$skin = new SkinImage($skinImage);
			$SkinData = $skin->getSkinImageData(true);
			$skinSize = $this->getSkinImageSize(strlen($skin->getRawSkinImageData(true)));
			$SkinImageHeight = $skinSize[0];
			$SkinImageWidth = $skinSize[1];

			$cape = new SkinImage($capeImage);
			$CapeData = $cape->getSkinImageData();
			$capeSize = $this->getSkinImageSize(strlen($cape->getRawSkinImageData()));
			$CapeImageHeight = $capeSize[0];
			$CapeImageWidth = $capeSize[1];
			$skin = new Skin($SkinId, base64_decode($SkinData), base64_decode($CapeData));
			$this->plugin->addJavaPlayer($this->uuid, (string) mt_rand(2 * (10 ** 15), (3 * (10 ** 15)) - 1), $this->username, $skin, $this);
		}
	}

	private function getSkinImageSize(int $skinImageLength) : array{
		return match($skinImageLength){
			64 * 32 * 4 => [64, 32],
			64 * 64 * 4 => [64, 64],
			128 * 64 * 4 => [128, 64],
			128 * 128 * 4 => [128, 128],
			default => [0, 0]
		};

	}

	public function addEntityList(int $eid, string $entityType) : void{
		if(!isset($this->entityList[$eid])){
			$this->entityList[$eid] = $entityType;
		}
	}

	public function bigBrother_getEntityList(int $eid) : string{
		if(isset($this->entityList[$eid])){
			return $this->entityList[$eid];
		}
		return "generic";
	}

	public function removeEntityList(int $eid) : void{
		if(isset($this->entityList[$eid])){
			unset($this->entityList[$eid]);
		}
	}

	public function bigBrother_handleAuthentication(string $username, bool $onlineMode = false) : void{
		if($this->status === 0){
			$this->username = $username;
			if($onlineMode){
				$pk = new EncryptionRequestPacket();
				$pk->serverID = "";
				$pk->publicKey = $this->plugin->getASN1PublicKey();
				$pk->verifyToken = $this->checkToken = str_repeat("\x00", 4);
				$this->putRawPacket($pk);
			}else{
				if(!is_null(($info = $this->plugin->getProfileCache($username)))){
					var_dump($info);
					$this->bigBrother_authenticate($info["id"], $info["properties"]);
				}else{
					Server::getInstance()->getAsyncPool()->submitTask(new class($this->plugin, $this, $username) extends AsyncTask{
						public function __construct(BigBrother $plugin, DesktopNetworkSession $networkSession, private string $username){
							$this->storeLocal("bigbrother:plugin", $plugin);
							$this->storeLocal("bigbrother:networksession", $networkSession);
						}

						public function onRun() : void{
							$response = Internet::getURL("https://api.mojang.com/users/profiles/minecraft/" . $this->username, 10, [], $err);
							if($response->getCode() === 204){
								$this->publishProgress("UserNotFound: failed to fetch profile for '$this->username'; status={$response->getCode()}; err=$err; response_header=" . json_encode($response->getHeaders()));
								$this->setResult([
									"id" => str_replace("-", "", Uuid::uuid4()->toString()),
									"name" => $this->username,
									"properties" => []
								]);
								return;
							}

							if($response->getCode() !== 200){
								$this->publishProgress("InternetException: failed to fetch profile for '$this->username'; status={$response->getCode()}; err=$err; response_header=" . json_encode($response->getHeaders()));
								$this->setResult(false);
								return;
							}

							$profile = json_decode($response->getBody(), true);
							if(!is_array($profile)){
								$this->publishProgress("UnknownError: failed to parse profile for '$this->username'; status={$response->getCode()}; response={$response->getBody()}; response_header=" . json_encode($response->getHeaders()));
								$this->setResult(false);
								return;
							}

							$uuid = $profile["id"];
							$response = Internet::getURL("https://sessionserver.mojang.com/session/minecraft/profile/" . $uuid, 3, [], $err);
							if($response->getCode() !== 200){
								$this->publishProgress("InternetException: failed to fetch profile info for '$this->username'; status={$response->getCode()}; err=$err; response_header=" . json_encode($response->getHeaders()));
								$this->setResult(false);
								return;
							}

							$info = json_decode($response->getBody(), true);
							if($info === null || !isset($info["id"])){
								$this->publishProgress("UnknownError: failed to parse profile info for '$this->username'; status={$response->getCode()}; response={$response->getBody()}; response_header=" . json_encode($response->getHeaders()));
								$this->setResult(false);
								return;
							}

							$this->setResult($info);
						}

						public function onProgressUpdate($progress) : void{
							Server::getInstance()->getLogger()->error($progress);
						}

						public function onCompletion() : void{
							$info = $this->getResult();
							if(is_array($info)){
								/** @var BigBrother $plugin */
								$plugin = $this->fetchLocal("bigbrother:plugin");
								/** @var DesktopNetworkSession $networkSession */
								$networkSession = $this->fetchLocal("bigbrother:networksession");

								$plugin->setProfileCache($this->username, $info);
								$networkSession->bigBrother_authenticate($info["id"], $info["properties"]);
							}
						}

					});
				}
			}
		}
	}

	public function setClientSettings(array $clientSettings) : DesktopNetworkSession{
		$this->clientSettings = $clientSettings;
		return $this;
	}

	public function getClientSettings() : array{
		return $this->clientSettings;
	}

	public function getPlugin() : BigBrother{
		return $this->plugin;
	}
}

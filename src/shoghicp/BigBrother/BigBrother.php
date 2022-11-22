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

use phpseclib\Crypt\AES;
use phpseclib\Crypt\RSA;
use pocketmine\block\BaseSign;
use pocketmine\entity\Skin;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\Listener;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ProtocolInfo as Info;
use pocketmine\network\mcpe\protocol\ResourcePackClientResponsePacket;
use pocketmine\network\mcpe\protocol\TextPacket;
use pocketmine\player\Player;
use pocketmine\player\XboxLivePlayerInfo;
use pocketmine\plugin\DisablePluginException;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Utils;
use Ramsey\Uuid\Uuid;
use shoghicp\BigBrother\network\DesktopNetworkSession;
use shoghicp\BigBrother\network\protocol\Play\Server\KeepAlivePacket;
use shoghicp\BigBrother\network\protocol\Play\Server\OpenSignEditorPacket;
use shoghicp\BigBrother\network\ProtocolInterface;
use shoghicp\BigBrother\network\ServerManager;
use shoghicp\BigBrother\network\Translator;
use shoghicp\BigBrother\utils\ColorUtils;
use shoghicp\BigBrother\utils\ConvertUtils;
use function array_map;
use function array_pop;
use function array_shift;
use function assert;
use function base64_decode;
use function chdir;
use function constant;
use function copy;
use function count;
use function exec;
use function explode;
use function getcwd;
use function implode;
use function is_array;
use function is_dir;
use function is_file;
use function join;
use function json_decode;
use function json_encode;
use function microtime;
use function mt_rand;
use function ob_end_clean;
use function ob_get_contents;
use function ob_start;
use function php_uname;
use function phpinfo;
use function preg_match_all;
use function str_replace;
use function stream_context_create;
use function stream_get_contents;
use function strpos;
use function substr;

class BigBrother extends PluginBase implements Listener{

	private ProtocolInterface $interface;
	protected RSA $rsa;
	protected string $privateKey;
	protected string $publicKey;
	protected bool $onlineMode;
	protected Translator $translator;
	protected string $desktopPrefix;
	protected array $profileCache = [];
	protected string $dimensionCodec;
	protected string $dimension;
	/** @var DesktopPlayer[] */
	private array $javaPlayers = [];

	protected function onEnable() : void{
		if(Info::CURRENT_PROTOCOL < Info::PROTOCOL_1_19_20 || Info::CURRENT_PROTOCOL > Info::PROTOCOL_1_19_40){
			$this->getLogger()->critical("Couldn't find a protocol translator for #" . Info::CURRENT_PROTOCOL . ", disabling plugin");
			throw new DisablePluginException();
		}

		ConvertUtils::init();

		$this->saveDefaultConfig();
		$this->getConfig()->setDefaults([
			"interface" => "0.0.0.0",
			"port" => 25565,
			"network-compression-threshold" => 256,
			"motd" => "§bPocketMine-MP server using §6§lBigBrother§r§b plugin\n§aConnect to Minecraft: PE servers from PC clients",
			"online-mode" => true,
			"desktop-prefix" => "PC_"
		]);
		if($this->getConfig()->hasChanged()){
			$this->saveConfig();
		}
		$this->saveResource("blockStateMapping.json", true);
		$this->saveResource("color_index.dat", true);
		$this->saveResource("openssl.cnf", false);
		$this->saveResource("server-icon.png", false);

		ColorUtils::loadColorIndex($this->getDataFolder() . "color_index.dat");
		ConvertUtils::loadBlockStateIndex($this->getDataFolder() . "blockStateMapping.json");

		$this->dimensionCodec = stream_get_contents($this->getResource("dimensionCodec.dat"));
		$this->dimension = stream_get_contents($this->getResource("dimension.dat"));

		$this->getLogger()->info("OS: " . php_uname());
		$this->getLogger()->info("PHP version: " . PHP_VERSION);

		$this->getLogger()->info("PMMP Server version: " . $this->getServer()->getVersion());
		$this->getLogger()->info("PMMP API version: " . $this->getServer()->getApiVersion());

		if(\Phar::running() === "" && is_dir($this->getFile() . ".git")){
			$cwd = getcwd();
			chdir($this->getFile());
			@exec("git describe --tags --always --dirty", $revision, $value);
			if($value === 0){
				$this->getLogger()->info("BigBrother revision: " . $revision[0]);
			}
			chdir($cwd);
		}elseif(($resource = $this->getResource("revision")) && ($revision = stream_get_contents($resource))){
			$this->getLogger()->info("BigBrother.phar; revision: " . $revision);
		}

		if(!$this->setupComposer()){
			$this->getLogger()->critical("Composer autoloader not found");
			throw new DisablePluginException();
		}

		$aes = new AES(AES::MODE_CFB8);
		switch($aes->getEngine()){
			case AES::ENGINE_OPENSSL:
				$this->getLogger()->info("Use openssl as AES encryption engine.");
				break;
			case AES::ENGINE_MCRYPT:
				$this->getLogger()->warning("Use obsolete mcrypt for AES encryption. Try to install openssl extension instead!!");
				break;
			case AES::ENGINE_INTERNAL:
				$this->getLogger()->warning("Use phpseclib internal engine for AES encryption, this may impact on performance. To improve them, try to install openssl extension.");
				break;
		}

		$this->rsa = new RSA();
		switch(constant("CRYPT_RSA_MODE")){
			case RSA::MODE_OPENSSL:
				$this->rsa->configFile = $this->getDataFolder() . "openssl.cnf";
				$this->getLogger()->info("Use openssl as RSA encryption engine.");
				break;
			case RSA::MODE_INTERNAL:
				$this->getLogger()->info("Use phpseclib internal engine for RSA encryption.");
				break;
		}

		if($aes->getEngine() === AES::ENGINE_OPENSSL || constant("CRYPT_RSA_MODE") === RSA::MODE_OPENSSL){
			ob_start();
			@phpinfo();
			preg_match_all('#OpenSSL (Header|Library) Version => (.*)#im', ob_get_contents() ?? "", $matches);
			ob_end_clean();

			foreach(array_map(null, $matches[1], $matches[2]) as $version){
				$this->getLogger()->info("OpenSSL " . $version[0] . " version: " . $version[1]);
			}
		}

		$this->onlineMode = (bool) ($this->getConfig()->get("online-mode") | $this->getConfig()->get("xbox-auth"));
		if($this->onlineMode){
			$this->getLogger()->info("Server is being started in the background");
			$this->getLogger()->info("Generating keypair");
			$this->rsa->setPrivateKeyFormat(RSA::PRIVATE_FORMAT_PKCS1);
			$this->rsa->setPublicKeyFormat(RSA::PUBLIC_FORMAT_PKCS8);
			$this->rsa->setEncryptionMode(RSA::ENCRYPTION_PKCS1);
			$keys = $this->rsa->createKey();//1024 bits
			$this->privateKey = $keys["privatekey"];
			$this->publicKey = $keys["publickey"];
			$this->rsa->loadKey($this->privateKey);
		}

		$this->desktopPrefix = $this->getConfig()->get("desktop-prefix", "PC_");

		$this->getLogger()->info("Starting Minecraft: PC server on " . ($this->getIp() === "0.0.0.0" ? "*" : $this->getIp()) . ":" . $this->getPort() . " version " . ServerManager::VERSION);

		$this->getServer()->getPluginManager()->registerEvents($this, $this);

		$this->translator = new Translator();
		$this->interface = new ProtocolInterface($this, $this->getServer(), $this->translator, (int) $this->getConfig()->get("network-compression-threshold"));
		$this->getServer()->getNetwork()->registerInterface($this->interface);
	}

	public function getIp() : string{
		return (string) $this->getConfig()->get("interface");
	}

	public function getPort() : int{
		return (int) $this->getConfig()->get("port");
	}

	public function getMotd() : string{
		return (string) $this->getConfig()->get("motd");
	}

	public function isOnlineMode() : bool{
		return $this->onlineMode;
	}

	public function getDesktopPrefix() : string{
		return $this->desktopPrefix;
	}

	public function getInterface() : ProtocolInterface{
		return $this->interface;
	}

	public function getTranslator() : Translator{
		return $this->translator;
	}

	/**
	 * @return string ASN1 Public Key
	 */
	public function getASN1PublicKey() : string{
		$key = explode("\n", $this->publicKey);
		array_pop($key);
		array_shift($key);
		return base64_decode(implode(array_map("trim", $key)));
	}

	/**
	 * @param string $cipher cipher text
	 *
	 * @return string plain text
	 */
	public function decryptBinary(string $cipher) : string{
		return $this->rsa->decrypt($cipher);
	}

	public function getProfileCache(string $username, int $timeout = 60) : ?array{
		if(isset($this->profileCache[$username]) && (microtime(true) - $this->profileCache[$username]["timestamp"] < $timeout)){
			return $this->profileCache[$username]["profile"];
		}else{
			unset($this->profileCache[$username]);
			return null;
		}
	}

	public function setProfileCache(string $username, array $profile) : void{
		$this->profileCache[$username] = [
			"timestamp" => microtime(true),
			"profile" => $profile
		];
	}

	/**
	 * Return string of Compound Tag
	 * @return string
	 */
	public function getDimensionCodec() : string{
		return $this->dimensionCodec;
	}

	/**
	 * Return string of Compound Tag
	 * @return string
	 */
	public function getDimension() : string{
		return $this->dimension;
	}

	/** @priority MONITOR */
	public function onPlace(BlockPlaceEvent $event) : void{
		$player = $this->getJavaPlayer($event->getPlayer());
		if($player instanceof DesktopPlayer){
			$block = $event->getBlock();
			if($block instanceof BaseSign){
				$pk = new OpenSignEditorPacket();
				$pos = $block->getPosition();
				$pk->x = $pos->x;
				$pk->y = $pos->y;
				$pk->z = $pos->z;
				$player->getNetworkSession()->putRawPacket($pk);
			}
		}
	}

	public function onBreak(BlockBreakEvent $event) : void{
		if($this->isJavaPlayer($event->getPlayer())){
			$event->setInstaBreak(true);//ItemFrame and other blocks
		}
	}

	private function setupComposer() : bool{
		$base = $this->getFile();
		$data = $this->getDataFolder();
		$setup = $data . 'composer-setup.php';
		$composer = $data . 'composer.phar';
		$autoload = $base . 'vendor/autoload.php';

		if(\Phar::running() === "" && !is_file($autoload)){
			$this->getLogger()->info("Trying to setup composer...");

			//Fix ssl operation failed
			//https://stackoverflow.com/questions/26148701/file-get-contents-ssl-operation-failed-with-code-1-failed-to-enable-crypto

			$arrContextOptions = [
				"ssl" => [
					"verify_peer" => false,
					"verify_peer_name" => false,
				],
			];
			copy('https://getcomposer.org/installer', $setup, stream_context_create($arrContextOptions));
			exec(join(' ', [PHP_BINARY, $setup, '--install-dir', $data]));

			$this->getLogger()->info("Trying to install composer dependencies...");
			exec(join(' ', [PHP_BINARY, $composer, 'install', '-d', $base, '--no-dev', '-o']));
		}

		if(is_file($autoload)){
			$this->getLogger()->info("Registering Composer autoloader...");
			__require($autoload);
			return true;
		}else{
			return false;
		}
	}

	public static function toJSON(string $message, int $type = TextPacket::TYPE_CHAT, ?array $parameters = []) : string{
		$result = json_decode(BigBrother::toJSONInternal($message), true);

		switch($type){
			case TextPacket::TYPE_TRANSLATION:
				unset($result["text"]);
				$message = TextFormat::clean($message);

				if(substr($message, 0, 1) === "["){//chat.type.admin
					$result["translate"] = "chat.type.admin";
					$result["color"] = "gray";
					$result["italic"] = true;
					unset($result["extra"]);

					$result["with"][] = ["text" => substr($message, 1, strpos($message, ":") - 1)];

					$result["with"][] = ["translate" => substr(substr($message, strpos($message, ":") + 2), 0, -1)];

					$with = &$result["with"][1];
				}else{
					$result["translate"] = str_replace("%", "", $message);

					$with = &$result;
				}

				foreach($parameters as $parameter){
					if(strpos($parameter, "%") !== false){
						$with["with"][] = ["translate" => str_replace("%", "", $parameter)];
					}else{
						$with["with"][] = ["text" => $parameter];
					}
				}
				break;
			case TextPacket::TYPE_POPUP:
			case TextPacket::TYPE_TIP://Just to be sure
				if(isset($result["text"])){
					$result["text"] = str_replace("\n", "", $message);
				}

				if(isset($result["extra"])){
					unset($result["extra"]);
				}
				break;
		}

		if(isset($result["extra"])){
			if(count($result["extra"]) === 0){
				unset($result["extra"]);
			}
		}

		return json_encode($result, JSON_UNESCAPED_SLASHES);
	}

	/**
	 * Returns an JSON-formatted string with colors/markup
	 *
	 * @param string|string[] $string
	 *
	 * @return string
	 * @internal
	 */
	public static function toJSONInternal(string|array $string) : string{
		if(!is_array($string)){
			$string = TextFormat::tokenize($string);
		}
		$newString = [];
		$pointer =& $newString;
		$color = "white";
		$bold = false;
		$italic = false;
		$underlined = false;
		$strikethrough = false;
		$obfuscated = false;
		$index = 0;

		foreach($string as $token){
			if(isset($pointer["text"])){
				if(!isset($newString["extra"])){
					$newString["extra"] = [];
				}
				$newString["extra"][$index] = [];
				$pointer =& $newString["extra"][$index];
				if($color !== "white"){
					$pointer["color"] = $color;
				}
				if($bold){
					$pointer["bold"] = true;
				}
				if($italic){
					$pointer["italic"] = true;
				}
				if($underlined){
					$pointer["underlined"] = true;
				}
				if($strikethrough){
					$pointer["strikethrough"] = true;
				}
				if($obfuscated){
					$pointer["obfuscated"] = true;
				}
				++$index;
			}
			switch($token){
				case TextFormat::BOLD:
					if(!$bold){
						$pointer["bold"] = true;
						$bold = true;
					}
					break;
				case TextFormat::OBFUSCATED:
					if(!$obfuscated){
						$pointer["obfuscated"] = true;
						$obfuscated = true;
					}
					break;
				case TextFormat::ITALIC:
					if(!$italic){
						$pointer["italic"] = true;
						$italic = true;
					}
					break;
				case TextFormat::UNDERLINE:
					if(!$underlined){
						$pointer["underlined"] = true;
						$underlined = true;
					}
					break;
				case TextFormat::STRIKETHROUGH:
					if(!$strikethrough){
						$pointer["strikethrough"] = true;
						$strikethrough = true;
					}
					break;
				case TextFormat::RESET:
					if($color !== "white"){
						$pointer["color"] = "white";
						$color = "white";
					}
					if($bold){
						$pointer["bold"] = false;
						$bold = false;
					}
					if($italic){
						$pointer["italic"] = false;
						$italic = false;
					}
					if($underlined){
						$pointer["underlined"] = false;
						$underlined = false;
					}
					if($strikethrough){
						$pointer["strikethrough"] = false;
						$strikethrough = false;
					}
					if($obfuscated){
						$pointer["obfuscated"] = false;
						$obfuscated = false;
					}
					break;

				//Colors
				case TextFormat::BLACK:
					$pointer["color"] = "black";
					$color = "black";
					break;
				case TextFormat::DARK_BLUE:
					$pointer["color"] = "dark_blue";
					$color = "dark_blue";
					break;
				case TextFormat::DARK_GREEN:
					$pointer["color"] = "dark_green";
					$color = "dark_green";
					break;
				case TextFormat::DARK_AQUA:
					$pointer["color"] = "dark_aqua";
					$color = "dark_aqua";
					break;
				case TextFormat::DARK_RED:
					$pointer["color"] = "dark_red";
					$color = "dark_red";
					break;
				case TextFormat::DARK_PURPLE:
					$pointer["color"] = "dark_purple";
					$color = "dark_purple";
					break;
				case TextFormat::GOLD:
					$pointer["color"] = "gold";
					$color = "gold";
					break;
				case TextFormat::GRAY:
					$pointer["color"] = "gray";
					$color = "gray";
					break;
				case TextFormat::DARK_GRAY:
					$pointer["color"] = "dark_gray";
					$color = "dark_gray";
					break;
				case TextFormat::BLUE:
					$pointer["color"] = "blue";
					$color = "blue";
					break;
				case TextFormat::GREEN:
					$pointer["color"] = "green";
					$color = "green";
					break;
				case TextFormat::AQUA:
					$pointer["color"] = "aqua";
					$color = "aqua";
					break;
				case TextFormat::RED:
					$pointer["color"] = "red";
					$color = "red";
					break;
				case TextFormat::LIGHT_PURPLE:
					$pointer["color"] = "light_purple";
					$color = "light_purple";
					break;
				case TextFormat::YELLOW:
					$pointer["color"] = "yellow";
					$color = "yellow";
					break;
				case TextFormat::WHITE:
					$pointer["color"] = "white";
					$color = "white";
					break;
				default:
					$pointer["text"] = $token;
					break;
			}
		}

		if(isset($newString["extra"])){
			foreach($newString["extra"] as $k => $d){
				if(!isset($d["text"])){
					unset($newString["extra"][$k]);
				}
			}
		}

		return Utils::assumeNotFalse(json_encode($newString, JSON_UNESCAPED_SLASHES));
	}

	public function getJavaPlayerList() : array{
		return $this->javaPlayers;
	}

	public function getJavaPlayer(Player $player) : ?DesktopPlayer{
		return $this->javaPlayers[$player->getUniqueId()->getBytes()] ?? null;
	}

	public function addJavaPlayer(string $uuid, string $xuid, string $username, Skin $skin, DesktopNetworkSession $session) : Player{
		$rp = new \ReflectionProperty(NetworkSession::class, "info");
		$rp->setAccessible(true);
		$rp->setValue($session, new XboxLivePlayerInfo($xuid, $username, Uuid::fromString($uuid), $skin, "en_US", []));

		$rp = new \ReflectionMethod(NetworkSession::class, "onServerLoginSuccess");
		$rp->setAccessible(true);
		$rp->invoke($session);

		$session->handleDataPacketNoDecode(ResourcePackClientResponsePacket::create(ResourcePackClientResponsePacket::STATUS_COMPLETED, []));

		$session->getPlayer()->setViewDistance(4);

		$pk = new KeepAlivePacket();
		$pk->keepAliveId = mt_rand();
		$session->putRawPacket($pk);

		$player = $session->getPlayer();
		assert($player !== null);
		$this->javaPlayers[$player->getUniqueId()->getBytes()] = new DesktopPlayer($session, $this);

		if(!$player->isAlive()){
			$player->respawn();
		}
		return $player;
	}

	public function removePlayer(Player $player, bool $disconnect = true) : void{
		if(!$this->isJavaPlayer($player)){
			throw new \InvalidArgumentException("Invalid Player supplied, expected a java player, got " . $player->getName());
		}

		unset($this->javaPlayers[$player->getUniqueId()->getBytes()]);

		if($disconnect){
			$player->disconnect("disconnected");
		}
	}

	public function isJavaPlayer(Player $player) : bool{
		return isset($this->javaPlayers[$player->getUniqueId()->getBytes()]);
	}
}

/**
 * Scope isolated require.
 *
 * prevents access to $this/self from included file
 * @param string $file
 * @return void
 * @phpstan-return void|mixed
 */
function __require(string $file){
	return require $file;
}

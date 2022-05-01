<?php

declare(strict_types=1);

namespace ErikPDev\LimitCreative;

use AttachableLogger;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityItemPickupEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerBlockPickEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerGameModeChangeEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\item\StringToItemParser;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\plugin\PluginLogger;
use pocketmine\Server;
use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;

class LimitCreative extends PluginBase implements Listener {

	private static DataConnector $database;
	private static PluginLogger|AttachableLogger $logger;
	private array $blacklist;

	protected function onEnable(): void {

		$this->saveResource("config.yml");

		Server::getInstance()->getPluginManager()->registerEvents($this, $this);

		foreach ($this->getConfig()->getNested("blacklisted-blocks") as $blockItem) {

			if(is_numeric($blockItem)){
				$this->blacklist[] = (int) $blockItem;
				continue;
			}

			$itemBlock = StringToItemParser::getInstance()->parse($blockItem);

			if ($itemBlock == null) {
				$this->getLogger()->critical("Unknown Block Name at config, skipping it's id.");
				continue;
			}

			$this->blacklist[] = $itemBlock->getId();

		}

		$this->registerQuitEvent();
		$this->registerGamemodeChangeEvent();
		$this->registerInteractEvent();
		$this->registerBlockPickUpEvent();
		$this->registerEntityDamageByEntityEvent();
		$this->registerPlayerDeathEvent();

		if ($this->getConfig()->get("allowDropItems", false) == false)
			$this->registerDropItemEvent();

		if ($this->getConfig()->get("allowPickupItems", false) == false)
			$this->registerPickUpItemEvent();

		self::$database = libasynql::create(
			$this,
			array(
				"type" => "sqlite",
				"sqlite" => array(
					"file" => "inventories.sqlite"
				),
				"worker-limit" => 1
			),
			[
				"sqlite" => "sqlite.sql"
			]
		);

		self::$database->executeGeneric("limitcreative.init");

		self::$logger = $this->getLogger();

	}

	private function registerQuitEvent() {

		$playerQuit = function (PlayerQuitEvent $event) {

			if ($this->getConfig()->get("clearInventory", true) == true && $event->getPlayer()->isCreative() == true && !self::canBypass($event->getPlayer()))
				self::clearInventory($event->getPlayer());

			self::saveInventory($event->getPlayer(), $event->getPlayer()->getGamemode()->getAliases()[0]);

		};

		Server::getInstance()->getPluginManager()->registerEvent(PlayerQuitEvent::class, $playerQuit, 0, $this);

	}

	public static function canBypass(Player $player): bool {

		if ($player->hasPermission("limitcreative.bypass"))
			return true;
		return false;

	}

	public static function clearInventory(Player $player) {

		$player->getOffHandInventory()->clearAll();
		$player->getCursorInventory()->clearAll();
		$player->getArmorInventory()->clearAll();
		$player->getEnderInventory()->clearAll();
		$player->getInventory()->clearAll();

	}

	public static function saveInventory(Player $player, $gamemode) {

		$queryName = match ($gamemode) {
			"creative" => "limitcreative.setCreativeInventory",
			"survival" => "limitcreative.setSurvivalInventory",
			"adventure" => "limitcreative.setAdventureInventory",
			"spectator" => "limitcreative.setSpectatorInventory",
			default => null,
		};

		if ($queryName == null)
			return;

		self::$database->executeInsert($queryName, [
			"UUID" => $player->getUniqueId()->toString(),
			"inventory" => serialize(array(
				"inventory" => new Items($player->getInventory()->getContents()),
				"handOffInventory" => new Items($player->getOffHandInventory()->getContents()),
				"armorInventory" => new Items($player->getArmorInventory()->getContents()),
				"enderInventory" => new Items($player->getEnderInventory()->getContents())
			))
		]);


	}

	private function registerGamemodeChangeEvent() {

		$gamemodeChange = function (PlayerGameModeChangeEvent $event) {

			if(self::canBypass($event->getPlayer())) return;

			if ($this->getConfig()->get("separateInventories", true) == false) {
				if ($this->getConfig()->get("clearInventory", true) == true)
					self::clearInventory($event->getPlayer());
				return;
			}

			$player = $event->getPlayer();
			$gamemode = $event->getNewGamemode()->getAliases()[0];

			self::saveInventory($player, $player->getGamemode()->getAliases()[0]);
			self::clearInventory($event->getPlayer());
			self::loadInventory($player, $gamemode);

		};

		Server::getInstance()->getPluginManager()->registerEvent(PlayerGameModeChangeEvent::class, $gamemodeChange, 0, $this);

	}

	public static function loadInventory(Player $player, $gamemode) {

		$queryName = match ($gamemode) {
			"creative" => "limitcreative.getCreativeInventory",
			"survival" => "limitcreative.getSurvivalInventory",
			"adventure" => "limitcreative.getAdventureInventory",
			"spectator" => "limitcreative.getSpectatorInventory",
			default => null,
		};

		if ($queryName == null)
			return;

		$logger = self::$logger;

		self::$database->executeSelect($queryName, ["UUID" => $player->getUniqueId()->toString()],
			function ($data) use ($gamemode, $player) {

				if (count($data) == 0)
					return;

				if ($data[0][$gamemode] == "")
					return;

				/** @var array $inventories */
				$inventories = unserialize($data[0][$gamemode]);

				$player->getInventory()->setContents($inventories["inventory"]->getItems());
				$player->getOffHandInventory()->setContents($inventories["handOffInventory"]->getItems());
				$player->getArmorInventory()->setContents($inventories["armorInventory"]->getItems());
				$player->getEnderInventory()->setContents($inventories["enderInventory"]->getItems());

			},
			function ($error) use ($logger) { // Error is untested.
				$logger->critical($error);
			}
		);

	}

	private function registerInteractEvent() {

		$interact = function (PlayerInteractEvent $event) {

			if (!$event->getPlayer()->isCreative()) return;
			if (self::canBypass($event->getPlayer())) return;

			if (!in_array($event->getBlock()->getId(), $this->blacklist)) return;

			$event->cancel();

		};

		Server::getInstance()->getPluginManager()->registerEvent(PlayerInteractEvent::class, $interact, 0, $this);

	}

	private function registerBlockPickUpEvent() {

		$blockPickUp = function (PlayerBlockPickEvent $event) {

			if (!$event->getPlayer()->isCreative()) return;
			if (self::canBypass($event->getPlayer())) return;

			$event->cancel();

		};

		Server::getInstance()->getPluginManager()->registerEvent(PlayerBlockPickEvent::class, $blockPickUp, 0, $this);

	}

	private function registerEntityDamageByEntityEvent() {

		$entityDamage = function (EntityDamageByEntityEvent $event) {

			$entity = $event->getEntity();
			if (!$entity instanceof Player) return;
			if (!$entity->isCreative()) return;
			if (self::canBypass($entity)) return;
			$event->cancel();

		};

		Server::getInstance()->getPluginManager()->registerEvent(EntityDamageByEntityEvent::class, $entityDamage, 0, $this);

	}

	private function registerPlayerDeathEvent() {

		$playerDeath = function (PlayerDeathEvent $event) {

			if (!$event->getPlayer()->isCreative()) return;
			if (self::canBypass($event->getPlayer())) return;

			if ($this->getConfig()->get("clearInventory", true) == true)
				self::clearInventory($event->getPlayer());
			self::clearInventories($event->getPlayer());

		};

		Server::getInstance()->getPluginManager()->registerEvent(PlayerDeathEvent::class, $playerDeath, 0, $this);

	}

	public static function clearInventories(Player $player) {

		self::$database->executeInsert("limitcreative.clearInventories", ["UUID" => $player->getUniqueId()->toString()]);

	}

	private function registerDropItemEvent() {

		$dropItem = function (PlayerDropItemEvent $event) {

			if (!$event->getPlayer()->isCreative()) return;
			if (self::canBypass($event->getPlayer())) return;

			$event->cancel();

		};

		Server::getInstance()->getPluginManager()->registerEvent(PlayerDropItemEvent::class, $dropItem, 0, $this);

	}

	private function registerPickUpItemEvent() {

		$itemPickUp = function (EntityItemPickupEvent $event) {

			$entity = $event->getEntity();
			if (!$entity instanceof Player) return;
			if (!$entity->isCreative()) return;
			if (self::canBypass($entity)) return;

			$event->cancel();

		};

		Server::getInstance()->getPluginManager()->registerEvent(EntityItemPickupEvent::class, $itemPickUp, 0, $this);

	}

	protected function onDisable(): void {

		if (isset(self::$database))
			self::$database->close();

	}

}

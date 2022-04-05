<?php

declare(strict_types=1);

namespace ErikPDev\LimitCreative;

use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityItemPickupEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerBlockPickEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerGameModeChangeEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\item\StringToItemParser;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;

class LimitCreative extends PluginBase implements Listener {

	private static array $inventories = array();
	private array $blacklist;

	protected function onEnable(): void {

		$this->saveResource("config.yml");

		Server::getInstance()->getPluginManager()->registerEvents($this, $this);

		foreach ($this->getConfig()->getNested("blacklisted-blocks") as $blockItem) {

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

		if ($this->getConfig()->get("allowDropItems", false) == false)
			$this->registerDropItemEvent();

		if ($this->getConfig()->get("allowPickupItems", false) == false)
			$this->registerPickUpItemEvent();

	}

	private function registerQuitEvent() {

		$playerQuit = function (PlayerQuitEvent $event) {

			if ($this->getConfig()->get("clearInventory", true) == true && $event->getPlayer()->isCreative() == true && !self::canBypass($event->getPlayer()))
				self::clearInventory($event->getPlayer());

			if (isset(self::$inventories[$event->getPlayer()->getName()])) return;
			unset(self::$inventories[$event->getPlayer()->getName()]);

		};

		Server::getInstance()->getPluginManager()->registerEvent(PlayerQuitEvent::class, $playerQuit, 0, $this);

	}

	private function registerGamemodeChangeEvent() {

		$gamemodeChange = function (PlayerGameModeChangeEvent $event) {

			if ($this->getConfig()->get("separateInventories", true) == false) {
				if ($this->getConfig()->get("clearInventory", true) == true)
					self::clearInventory($event->getPlayer());
				return;
			}

			$player = $event->getPlayer();
			$gamemode = (int)$event->getNewGamemode()->getAliases()[2];

			self::saveInventory($player, (int)$player->getGamemode()->getAliases()[2]);
			self::clearInventory($event->getPlayer());
			self::loadInventory($player, $gamemode);

		};

		Server::getInstance()->getPluginManager()->registerEvent(PlayerGameModeChangeEvent::class, $gamemodeChange, 0, $this);

	}

	private function registerDropItemEvent() {

		$dropItem = function (PlayerDropItemEvent $event) {

			if (!$event->getPlayer()->isCreative()) return;
			if(self::canBypass($event->getPlayer())) return;

			$event->cancel();

		};

		Server::getInstance()->getPluginManager()->registerEvent(PlayerDropItemEvent::class, $dropItem, 0, $this);

	}

	private function registerBlockPickUpEvent() {

		$blockPickUp = function (PlayerBlockPickEvent $event) {

			if (!$event->getPlayer()->isCreative()) return;
			if(self::canBypass($event->getPlayer())) return;

			$event->cancel();

		};

		Server::getInstance()->getPluginManager()->registerEvent(PlayerBlockPickEvent::class, $blockPickUp, 0, $this);

	}

	private function registerPickUpItemEvent() {

		$itemPickUp = function (EntityItemPickupEvent $event) {

			$entity = $event->getEntity();
			if (!$entity instanceof Player) return;
			if (!$entity->isCreative()) return;
			if(self::canBypass($entity)) return;

			$event->cancel();

		};

		Server::getInstance()->getPluginManager()->registerEvent(EntityItemPickupEvent::class, $itemPickUp, 0, $this);

	}

	private function registerInteractEvent() {

		$interact = function (PlayerInteractEvent $event) {

			if (!$event->getPlayer()->isCreative()) return;
			if(self::canBypass($event->getPlayer())) return;

			if (!in_array($event->getBlock()->getId(), $this->blacklist)) return;

			$event->cancel();

		};

		$blockPlace = function (BlockPlaceEvent $event) {

			if (!$event->getPlayer()->isCreative()) return;
			if(self::canBypass($event->getPlayer())) return;

			if (!in_array($event->getBlock()->getId(), $this->blacklist)) return;

			$event->cancel();

		};

		Server::getInstance()->getPluginManager()->registerEvent(PlayerInteractEvent::class, $interact, 0, $this);
		Server::getInstance()->getPluginManager()->registerEvent(BlockPlaceEvent::class, $blockPlace, 0, $this);

	}

	private function registerEntityDamageByEntityEvent(){

		$entityDamage = function (EntityDamageByEntityEvent $event){

			$entity = $event->getEntity();
			if(!$entity instanceof Player) return;
			if(!$entity->isCreative()) return;
			if(self::canBypass($entity)) return;
			$event->cancel();

		};

		Server::getInstance()->getPluginManager()->registerEvent(EntityDamageByEntityEvent::class, $entityDamage, 0, $this);

	}

	/*
	 * The below functions can be used outside this plugin.
	 */

	public static function canBypass(Player $player): bool {

		if($player->hasPermission("limitcreative.bypass"))
			return true;
		return false;

	}

	public static function clearInventory(Player $player) {

		$player->getOffHandInventory()->clearAll();
		$player->getCursorInventory()->clearAll();
		$player->getArmorInventory()->clearAll();
		$player->getEnderInventory()->clearAll();
		$player->getInventory()->clearAll();
		$player->getEffects()->clear();

	}

	public static function saveInventory(Player $player, int $gamemode) {

		self::$inventories[$player->getName()][$gamemode] = array(
			"inventory" => $player->getInventory()->getContents(),
			"handOffInventory" => $player->getOffHandInventory()->getContents(),
			"armorInventory" => $player->getArmorInventory()->getContents(),
			"enderInventory" => $player->getEnderInventory()->getContents(),
			"effects" => $player->getEffects()->all()
		);

	}

	public static function loadInventory(Player $player, int $gamemode) {

		if (!isset(self::$inventories[$player->getName()][$gamemode])) return;

		$inventories = self::$inventories[$player->getName()][$gamemode];

		$player->getInventory()->setContents($inventories["inventory"]);
		$player->getOffHandInventory()->setContents($inventories["handOffInventory"]);
		$player->getArmorInventory()->setContents($inventories["armorInventory"]);
		$player->getEnderInventory()->setContents($inventories["enderInventory"]);

		foreach ($inventories["effects"] as $effect) {

			$player->getEffects()->add($effect);

		}

	}

}

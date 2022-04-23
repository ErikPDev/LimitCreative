<?php

namespace ErikPDev\LimitCreative;

use pocketmine\item\Item;

class Items {

	private array $items = [];

	public function __construct($items) {

		$this->addItems($items);

	}

	public function addItems(array $items) {

		/** @var Item $item */
		foreach ($items as $item) {

			$this->items[] = $item->jsonSerialize();

		}

	}

	public function getItems(): array {

		$items = [];

		foreach ($this->items as $item) {

			$items[] = Item::jsonDeserialize($item);

		}

		return $items;

	}

}
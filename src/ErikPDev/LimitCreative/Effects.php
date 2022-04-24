<?php

namespace ErikPDev\LimitCreative;

use pocketmine\entity\effect\EffectInstance;

class Effects {

	private array $effects = [];

	public function __construct($effects) {

		$this->addEffects($effects);

	}

	public function addEffects(array $effects) {

		/** @var EffectInstance $effect */
		foreach ($effects as $effect) {

			$this->effects[] = array(
				"effectType" => $effect->getType(),
				"duration" => $effect->getDuration(),
				"amplifier" => $effect->getAmplifier(),
				"visible" => $effect->isVisible(),
				"ambient" => $effect->isAmbient(),
				"overrideColor" => $effect->getColor()
			);

		}

	}

	public function getEffects(): array {

		$effects = [];

		foreach ($this->effects as $effect) {

			$effects[] = new EffectInstance($effect["effectType"], $effect["duration"], $effect["amplifier"], $effect["visible"], $effect["ambient"], $effect["overrrideColor"]);

		}

		return $effects;

	}

}
<?php

declare(strict_types=1);

namespace shoghicp\BigBrother\network\protocol\Play\Server;

use shoghicp\BigBrother\network\OutboundPacket;

class ClientboundSetDisplayObjectivePacket extends OutboundPacket{

	const POSITION_PLAYER_LIST = 0;
    const POSITION_SIDEBAR = 1;
    const POSITION_BELOW_NAME = 2;

	/** @var int */
	public $position;
	/** @var string */
	public $name;

	public function pid(): int{
		return self::SET_DISPLAY_OBJECTIVE_PACKET;
	}

	protected function encode(): void{
		$this->putByte($this->position);
		$this->putString($this->name);
	}
}

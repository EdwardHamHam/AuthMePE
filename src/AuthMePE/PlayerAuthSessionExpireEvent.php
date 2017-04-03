<?php

namespace AuthMePE;

use pocketmine\Player;

use AuthMePE\AuthMePE;
use AuthMePE\BaseEvent;

class PlayerAuthSessionExpireEvent extends BaseEvent{
	public static $handlerList = null;
	
	private $player;
	
	public function __construct(AuthMePE $plugin, Player $player){
		$this->player = $player;
		parent::__construct($plugin);
	}
	
	public function getPlayer(){
		return $this->player;
	}
}

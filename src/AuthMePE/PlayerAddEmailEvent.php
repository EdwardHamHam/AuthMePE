<?php

namespace AuthMePE;

use pocketmine\Player;
use pocketmine\event\Cancellable;

use AuthMePE\AuthMePE;
use AuthMePE\BaseEvent;

class PlayerAddEmailEvent extends BaseEvent implements Cancellable{
	public static $handlerList = null;
	
	private $player;
	
	public function __construct(AuthMePE $plugin, Player $player, $email){
		$this->player = $player;
		$this->email = $email;
		parent::__construct($plugin);
	}
	
	public function getPlayer(){
		return $this->player;
	}
	
	public function getEmail(){
		return $this->email;
	}
}

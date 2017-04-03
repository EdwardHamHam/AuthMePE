<?php

namespace AuthMePE;

use pocketmine\Player;
use pocketmine\event\Cancellable;

use AuthMePE\AuthMePE;
use AuthMePE\BaseEvent;

class PlayerAuthEvent extends BaseEvent implements Cancellable{
	public static $handlerList = null;
	
	private $player;
	private $method;
	
	const PASSWORD = 0;
	const IP = 1;
	const PERMISSION = 2;
	const SESSION = 3;
	const COMMAND = 4;
	
	public function __construct(AuthMePE $plugin, Player $player, $method){
		$this->player = $player;
		$this->method = $method;
		parent::__construct($plugin);
	}
	
	public function getPlayer(){
		return $this->player;
	}
	
	public function getMethod(){
		return $this->method;
	}
	
	public function getIp(){
		return $this->player->getAddress();
	}
}

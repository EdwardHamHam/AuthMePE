<?php

namespace AuthMePE;

use pocketmine\scheduler\PluginTask;
use pocketmine\utils\TextFormat;

use AuthMePE\AuthMePE;
use AuthMePE\PlayerLoginTimeoutEvent;

class Task2 extends PluginTask{
	public $plugin;
	
	public function __construct(AuthMePE $plugin, $player){
		$this->plugin = $plugin;
		$this->player = $player;
		parent::__construct($plugin);
	}
	
	public function onRun($tick){
			if(!$this->plugin->isLoggedIn($this->player) || !$this->plugin->isRegistered($this->player)){
				$this->plugin->getServer()->getPluginManager()->callEvent(new PlayerLoginTimeoutEvent($this->plugin, $this->player));
				$this->player->kick(TextFormat::RED."Time out!");
				$this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
			}
	}
}
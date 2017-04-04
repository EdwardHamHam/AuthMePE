<?php

namespace AuthMePE;

use pocketmine\scheduler\PluginTask;
use pocketmine\Player;

use AuthMePE\AuthMePE;

class SessionTask extends PluginTask{
	public $plugin;
	public $player;
	
	public function __construct(AuthMePE $plugin, $player){
		$this->plugin = $plugin;
		$this->player = $player;
		parent::__construct($plugin);
	}
	
	public function onRun($tick){
		$this->plugin->closeSession($this->player);
		$this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
	}
}
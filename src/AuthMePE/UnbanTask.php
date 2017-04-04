<?php

namespace AuthMePE;

use pocketmine\scheduler\PluginTask;
use pocketmine\Player;

use AuthMePE\AuthMePE;

class UnbanTask extends PluginTask{
	public $plugin;
	public $player;
	
	public function __construct(AuthMePE $plugin, $player){
		$this->plugin = $plugin;
		$this->player = $player;
		parent::__construct($plugin);
	}
	
	public function onRun($tick){
		$this->plugin->unban($this->player->getName());
		$this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
	}
}
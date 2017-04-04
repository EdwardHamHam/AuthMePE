<?php

namespace AuthMePE;

use pocketmine\scheduler\PluginTask;

use AuthMePE\AuthMePE;

class TokenDeleteTask extends PluginTask{
	public $plugin;
	
	public function __construct(AuthMePE $plugin){
		$this->plugin = $plugin;
		parent::__construct($plugin);
	}
	
	public function onRun($tick){
		if($this->plugin->getToken() !== null){
		  $this->plugin->delToken();
		  $this->plugin->getLogger()->info("Last generated token has expired!  Please generate a new one!");
		}
		$this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
	}
}

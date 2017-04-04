<?php

namespace AuthMePE;

use pocketmine\scheduler\PluginTask;
use AuthMePE\AuthMePE;
use pocketmine\utils\TextFormat;
use pocketmine\level\sound\PopSound;

class Task extends PluginTask{
	public $plugin;
	
	public function __construct(AuthMePE $plugin){
		$this->plugin = $plugin;
		parent::__construct($plugin);
	}
	
	public function onRun($tick){
		foreach($this->plugin->getServer()->getOnlinePlayers() as $p){
			if($this->plugin->isRegistered($p) && !$this->plugin->isLoggedIn($p)){
				$p->sendMessage("Please type your password in chat to login.");
				$p->sendPopup(TextFormat::GOLD."Welcome ".TextFormat::AQUA.$p->getName().TextFormat::GREEN."\nPlease login to play!");
				$p->getLevel()->addSound(new PopSound($p), $this->plugin->getServer()->getOnlinePlayers());
			}
		}
	}
}
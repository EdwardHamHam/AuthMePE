<?php

namespace AuthMePE;

use pocketmine\scheduler\PluginTask;
use pocketmine\Player;
use pocketmine\level\sound\ClickSound;
use pocketmine\level\sound\LaunchSound;

use AuthMePE\AuthMePE;

class SoundTask extends PluginTask{
	public $plugin;
	public $player;
	public $type;
	
	public function __construct(AuthMePE $plugin, $player, $type){
		$this->plugin = $plugin;
		$this->player = $player;
		$this->type = $type;
		parent::__construct($plugin);
	}
	
	public function onRun($tick){
		switch($this->type){
			case 1:
			  $this->player->getLevel()->addSound(new ClickSound($this->player), $this->player->getServer()->getOnlinePlayers());
			break;
			case 2:
			  $this->player->getLevel()->addSound(new LaunchSound($this->player), $this->player->getServer()->getOnlinePlayers());
			break;
		}
	}
}
<?php

namespace AuthMePE;

use pocketmine\event\plugin\PluginEvent;
use AuthMePE\AuthMePE;

abstract class BaseEvent extends PluginEvent{
	
	public function __construct(AuthMePE $plugin){
		$this->plugin = $plugin;
		parent::__construct($plugin);
	}
}
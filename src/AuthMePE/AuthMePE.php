<?php

namespace AuthMePE;

use pocketmine\plugin\PluginBase;
use pocketmine\Player;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\inventory\InventoryPickupItemEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\scheduler\ServerScheduler;
use pocketmine\command\CommandSender;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\command\Command;
use pocketmine\level\Level;
use pocketmine\level\sound\BatSound;
use pocketmine\level\sound\PopSound;
use pocketmine\level\sound\LaunchSound;
use pocketmine\level\Position;
use pocketmine\math\Vector3;

use AuthMePE\Task;
use AuthMePE\Task2;
use AuthMePE\SessionTask;
use AuthMePE\SoundTask;
use AuthMePE\UnbanTask;

use AuthMePE\BaseEvent;

use AuthMePE\PlayerAuthEvent;
use AuthMePE\PlayerLogoutEvent;
use AuthMePE\PlayerRegisterEvent;
use AuthMePE\PlayerUnregisterEvent;
use AuthMePE\PlayerChangePasswordEvent;
use AuthMePE\PlayerAddEmailEvent;
use AuthMePE\PlayerLoginTimeoutEvent;
use AuthMePE\PlayerAuthSessionStartEvent;
use AuthMePE\PlayerAuthSessionExpireEvent;

use specter\network\SpecterPlayer;

class AuthMePE extends PluginBase implements Listener{
	
	private $login = array();
	private $session = array();
	private $bans = array();
	
	private $specter = false;
	
	const VERSION = "0.1.1";
	
	public function onEnable(){
		$sa = $this->getServer()->getPluginManager()->getPlugin("SimpleAuth");
		if($sa !== null){
			$this->getLogger()->notice("SimpleAuth has been disabled as it's a conflict plugin");
			$this->getServer()->getPluginManager()->disablePlugin($this);
		}
		if(!is_dir($this->getPluginDir())){
			@mkdir($this->getServer()->getDataPath()."plugins/hoyinm14mc_plugins");
			mkdir($this->getPluginDir());
		}
	  $this->cfg = new Config($this->getPluginDir()."config.yml", Config::YAML, array());
	  $this->reloadConfigFile();
		if(!is_dir($this->getPluginDir()."data")){
			mkdir($this->getPluginDir()."data");
		}
		$this->data = new Config($this->getPluginDir()."data/data.yml", Config::YAML, array());
		$this->ip = new Config($this->getPluginDir()."data/ip.yml", Config::YAML);
		$this->specter = false; //Force false
		$sp = $this->getServer()->getPluginManager()->getPlugin("Specter");
		if($sp !== null){
			$this->getServer()->getLogger()->info("Loaded with Specter!");
			$this->specter = true;
		}
		$this->getServer()->getScheduler()->scheduleRepeatingTask(new Task($this), 20 * 3);
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		if($this->getServer()->getPluginManager()->isPluginEnabled($this) !== true){
		  $this->getLogger()->notice("Server will be shutdown due to security reason as AuthMePE is disabled!");
		  $this->getServer()->shutdown();
		}
		$this->getLogger()->info(TextFormat::GREEN."Loaded Successfully!");
	}
	
	public function getPluginDir(){
		return $this->getServer()->getDataPath()."plugins/hoyinm14mc_plugins/AuthMePE/";
	}
	
	public function configFile(){
		return $this->cfg;
	}
	
	public function reloadConfigFile(){
		 $c = $this->cfg->getAll();
		 if(!isset($c["tries-allowed-to-enter-password"])){
		   $c["tries-allowed-to-enter-password"] = 5;
		 }
		 if(!isset($c["time-unban-after-tries-ban-minutes"])){
		   $c["time-unban-after-tries-ban-minutes"] = 5;
		 }
		 if(!isset($c["force-spawn"])){
		 $c["force-spawn"] = false;
		 }
	  if(!isset($c["login-timeout"])){
	  	$c["login-timeout"] = 30;
	  }
	  if(!isset($c["min-password-length"])){
	  	$c["min-password-length"] = 6;
	  }
	  if(!isset($c["sessions"])){
	  	$c["sessions"]["enabled"] = true;
	  	$c["sessions"]["session-login-available-minutes"] = 10;
	  }
	  if(!isset($c["email"])){
	  	$c["email"]["remind-players-add-email"] = true;
	  }
	  $this->cfg->setAll($c);
	  $this->cfg->save();
	  if(!is_numeric($this->cfg->get("login-timeout"))){
	  	$this->getLogger()->error("'login-timeout'/'min-password-length'/'session-login-available-minutes' in ".$this->getPluginDir()."config.yml must be numeric!");
	  	$this->getServer()->getPluginManager()->disablePlugin($this);
	  }
	}
	
	public function onDisable(){
		foreach($this->getLoggedIn() as $p){
			$this->logout($p);
		}
	}
	
	//HAHA high security~
	private function salt($pw){
		return sha1(md5($this->salt2($pw).$pw.$this->salt2($pw)));
	}
	private function salt2($word){
		return hash('sha256', $word);
	}
	
	private function sendCommandUsage(Player $player, $usage){
	  $player->sendMessage("§r§fUsage: §6".$usage);
	}
	
	public function isLoggedIn(Player $player){
		return in_array($player->getName(), $this->login);
	}
	
	public function isRegistered(Player $player){
		$t = $this->data->getAll();
		return isset($t[$player->getName()]["ip"]);
	}
	
	public function auth(Player $player, $method){	
		$this->getServer()->getPluginManager()->callEvent($event = new PlayerAuthEvent($this, $player, $method));
		if($event->isCancelled()){
			return false;
		}
		
		$this->getLogger()->info("Player ".$player->getName()." has logged in.");
		
		$c = $this->configFile()->getAll();
		$t = $this->data->getAll();
		if($c["email"]["remind-players-add-email"] !== false && !isset($t[$player->getName()]["email"])){
			$player->sendMessage("§dYou have not added your email!\nAdd it by using command §6/chgemail <email>");
		}
		
		$this->login[$player->getName()] = $player->getName();
		
		if($event->getMethod() == 0){
			//Do these things for what?
			//Bacause sound can't be played when keyboard is opened
			$player->setHealth($player->getHealth() - 0.1);
			$player->setHealth($player->getHealth() + 1);
			$this->getServer()->getScheduler()->scheduleDelayedTask(new SoundTask($this, $player, 1), 7);
			return false;
		}
		$player->getLevel()->addSound(new BatSound($player), $this->getServer()->getOnlinePlayers());
	}
	
	public function login(Player $player, $password){
		$t = $this->data->getAll();
		$c = $this->configFile()->getAll();
		if(md5($password.$this->salt($password)) != $t[$player->getName()]["password"]){
		  
			$player->sendMessage(TextFormat::RED."Wrong password!");
			$times = $t[$player->getName()]["times"];
			$left = $c["tries-allowed-to-enter-password"] - $times;
			if($times < $c["tries-allowed-to-enter-password"]){
			  $player->sendMessage("§eYou have §l§c".$left." §r§etries left!");
			  $t[$player->getName()]["times"] = $times + 1;
			  $this->data->setAll($t);
			  $this->data->save();
			}else{
			  $player->kick("§4Max amount of tries reached!");
			  $t[$player->getName()]["times"] = 0;
			  $this->data->setAll($t);
			  $this->data->save();
			  $this->ban($player->getName());
			  $c = $this->configFile()->getAll();
			  $this->getServer()->getScheduler()->scheduleDelayedTask(new UnbanTask($this, $player), $c["time-unban-after-tries-ban-minutes"] * 1200);
			}
			
			return false;
		}
		
		$this->auth($player, 0);
		$player->sendMessage(TextFormat::GREEN."You are now logged in.");
	}
	
	public function logout(Player $player){
		
		$this->getServer()->getPluginManager()->callEvent($event = new PlayerLogoutEvent($this, $player));
		
		if($event->isCancelled()){
			return false;
		}
		
		if(!$this->isLoggedIn($player)){
			$player->sendMessage(TextFormat::YELLOW."You are not logged in!");
			return false;
		}
		
		 $player->setHealth($player->getHealth() - 1);
		 $player->setHealth($player->getHealth() + 1);
		 $this->getServer()->getScheduler()->scheduleDelayedTask(new SoundTask($this, $player, 2), 7);
		 
		 $this->getLogger()->info("Player ".$player->getName()." has logged out.");
		
		unset($this->login[$player->getName()]);
	}
	
	public function register(Player $player, $pw1){
		$this->getServer()->getPluginManager()->callEvent($event = new PlayerRegisterEvent($this, $player));
		if($event->isCancelled()){
			$player->sendMessage("§cError during register!");
			return false;
		}
		$t = $this->data->getAll();
		$t[$player->getName()]["password"] = md5($pw1.$this->salt($pw1));
		$t[$player->getName()]["times"] = 0;
		$this->data->setAll($t);
		$this->data->save();
	}
	
	public function isSessionAvailable(Player $player){
		return in_array($player->getName(), $this->session);
	}
	
	public function startSession(Player $player, $minutes=10){
		$this->getServer()->getPluginManager()->callEvent($event = new PlayerAuthSessionStartEvent($this, $player));
		
		if($event->isCancelled()){
			return false;
		}
		
		$this->session[$player->getName()] = $player->getName();
		$this->getServer()->getScheduler()->scheduleDelayedTask(new SessionTask($this, $player), $minutes*1200);
	}
	
	public function closeSession(Player $player){
		$this->getServer()->getPluginManager()->callEvent(new PlayerAuthSessionExpireEvent($this, $player));
		
		unset($this->session[$player->getName()]);
		$player->sendPopup("§7Auth Session Expired!");
	}
	
	public function ban($name){
	  $this->bans[$name] = $name;
	}
	
	public function unban($name){
	  unset($this->bans[$name]);
	}
	
	public function isBanned($name){
	  return in_array($name, $this->bans);
	}
	
	public function getPlayerEmail($name){
		$t = $this->data->getAll();
	  return $t[$name]["email"];
	}
	
	public function onPlayerCommand(PlayerCommandPreprocessEvent $event){
		$t = $this->data->getAll();
		if(!$this->isLoggedIn($event->getPlayer())){
			if($this->isRegistered($event->getPlayer())){
				$m = $event->getMessage();
				if($m{0} == "/"){
					$event->getPlayer()->sendTip("§cYou are not allowed to execute commands now!");
					$event->getPlayer()->sendMessage("§fPlease login by typing your password into chat!");
					$event->setCancelled(true);
				}else{
			  	$this->login($event->getPlayer(), $event->getMessage());
			  }
				$event->setCancelled(true);
			}else{
				if(!isset($t[$event->getPlayer()->getName()]["password"])){
					if(strlen($event->getMessage()) < $this->configFile()->get("min-password-length")){
			      $event->getPlayer()->sendMessage(TextFormat::RED."The password is not long enough!");
			    }else{
     			$this->register($event->getPlayer(), $event->getMessage());
					  $event->getPlayer()->sendMessage(TextFormat::YELLOW."Type your password again to confirm.");
     		}
					$event->setCancelled(true);
				}
				if(!isset($t[$event->getPlayer()->getName()]["confirm"]) && isset($t[$event->getPlayer()->getName()]["password"])){
					$t[$event->getPlayer()->getName()]["confirm"] = $event->getMessage();
					$this->data->setAll($t);
					$this->data->save();
					if(md5($event->getMessage().$this->salt($event->getMessage())) != $t[$event->getPlayer()->getName()]["password"]){
						$event->getPlayer()->sendMessage(TextFormat::YELLOW."Confirm password ".TextFormat::RED."INCORRECT".TextFormat::YELLOW."!\n".TextFormat::WHITE."Please type your password in chat to start register.");
						$event->setCancelled(true);
						unset($t[$event->getPlayer()->getName()]);
						$this->data->setAll($t);
						$this->data->save();
					}else{
						$event->getPlayer()->sendMessage(TextFormat::WHITE."Confirm password ".TextFormat::GREEN."CORRECT".TextFormat::YELLOW."!\n".TextFormat::WHITE."Your password is '".TextFormat::AQUA.TextFormat::BOLD.$event->getMessage().TextFormat::WHITE.TextFormat::RESET."'");
						$event->setCancelled(true);
					}
				}
				if(!$this->isRegistered($event->getPlayer()) && isset($t[$event->getPlayer()->getName()]["confirm"]) && isset($t[$event->getPlayer()->getName()]["password"])){
					if($event->getMessage() != "yes" && $event->getMessage() != "no"){
					   $event->getPlayer()->sendMessage(TextFormat::YELLOW."If you want to login with your every last joined ip everytime, type '".TextFormat::WHITE."yes".TextFormat::YELLOW."'. Else, type '".TextFormat::WHITE."no".TextFormat::YELLOW."'");
					   $event->setCancelled(true);
					}else{
						 $t[$event->getPlayer()->getName()]["ip"] = $event->getMessage();
						 unset($t[$event->getPlayer()->getName()]["confirm"]);
						 $this->ip->set($event->getPlayer()->getName(), $event->getPlayer()->getAddress());
						 $this->data->setAll($t);
						 $this->data->save();
						 $event->getPlayer()->sendMessage(TextFormat::GREEN."You are now registered!\n".TextFormat::YELLOW."Type your password in chat to login.");
						 $time = $this->configFile()->get("login-timeout");
						 $this->getServer()->getScheduler()->scheduleDelayedTask(new Task2($this, $event->getPlayer()), ($time * 20));
						 $event->setCancelled(true);
					}
				}
			}
		}else{
			$pw = $t[$event->getPlayer()->getName()]["password"];
			$event->getMessage();
			if(!empty($event->getMessage())){
			  if(strpos(md5($event->getMessage().$this->salt($event->getMessage())), $pw) !== false && $msg{0} != "/"){
				  $event->getPlayer()->sendMessage("Do not tell your password to other people!");
				  $event->setCancelled(true);
		  	}
			}
		}
	}
	
	public function onJoin(PlayerJoinEvent $event){
		$t = $this->data->getAll();
		$c = $this->configFile()->getAll();
		if($this->isBanned($event->getPlayer()->getName()) !== false){
		  $event->getPlayer()->kick("§4Due to security issue, \n§4this account has been blocked temporary.  \n§4Please try again later.\n\n\n\n§6-AuthMePE Authentication System");
		}
		if($c["force-spawn"] === true){
			$event->getPlayer()->teleportImmediate($this->getServer()->getDefaultLevel()->getSafeSpawn());
		}
		if($this->specter !== false){
			if($event->getPlayer() instanceof SpecterPlayer){
				$this->login[$event->getPlayer()->getName()] = $event->getPlayer()->getName();
			}
		}
		if($this->isRegistered($event->getPlayer())){
			if($this->isSessionAvailable($event->getPlayer()) && $event->getPlayer()->getAddress() == $this->ip->get($event->getPlayer()->getName())){
				 $this->auth($event->getPlayer(), 3);
				 $event->getPlayer()->sendMessage("§6Session Available!\n§aYou are now logged in.");
			}else if($t[$event->getPlayer()->getName()]["ip"] == "yes"){
				if($event->getPlayer()->getAddress() == $this->ip->get($event->getPlayer()->getName())){
					$this->auth($event->getPlayer(), 1);
					$event->getPlayer()->sendMessage("§2We remember you by your §6IP §2address!\n".TextFormat::GREEN."You are now logged in.");
				}else{
					$event->getPlayer()->sendMessage(TextFormat::WHITE."Please type your password in chat to login.");
					$this->ip->set($event->getPlayer()->getName(), $event->getPlayer()->getAddress());
				  $this->ip->save();
					$event->getPlayer()->sendPopup(TextFormat::GOLD."Welcome ".TextFormat::AQUA.$event->getPlayer()->getName().TextFormat::GREEN."\nPlease login to play!");
					$this->getServer()->getScheduler()->scheduleDelayedTask(new Task2($this, $event->getPlayer()), (15 * 20));
				}
			}else if($event->getPlayer()->hasPermission("authmepe.login.bypass")){
					$this->auth($event->getPlayer(), 2);
					$event->getPlayer()->sendMessage("§6You logged in with permission!\n§aYou are now logged in.");
			}else{
				$event->getPlayer()->sendMessage(TextFormat::WHITE."Please type your password in chat to login.");
				$this->getServer()->getScheduler()->scheduleDelayedTask(new Task2($this, $event->getPlayer()), (30 * 20));
				$this->ip->set($event->getPlayer()->getName(), $event->getPlayer()->getAddress());
				$this->ip->save();
				$event->getPlayer()->sendPopup(TextFormat::GOLD."Welcome ".TextFormat::AQUA.$event->getPlayer()->getName().TextFormat::GREEN."\nPlease login to play!");
			}
		}else{
			$event->getPlayer()->sendMessage("Please type your password in chat to start register.");
		}
	}
	
	public function onPlayerMove(PlayerMoveEvent $event){
		$t = $this->data->getAll();
		if(!$this->isLoggedIn($event->getPlayer())){
			if($this->isRegistered($event->getPlayer())){
				$event->setCancelled(true);
			}else if(isset($t[$event->getPlayer()->getName()]["password"]) && !isset($t[$event->getPlayer()->getName()]["confirm"])){
				$event->getPlayer()->sendMessage("Please type your email into chat!");
				$event->setCancelled(true);
			}else if(!$this->isRegistered($event->getPlayer()) && isset($t[$event->getPlayer()->getName()]["confirm"])){
				$event->getPlayer()->sendMessage("Please type yes/no into chat!");
				$event->setCancelled(true);
			}else if(!isset($t[$event->getPlayer()->getName()])){
				$event->getPlayer()->sendMessage("Please type your new password into chat to register.");
				$event->setCancelled(true);
			}
		}
	}
	
	public function onQuit(PlayerQuitEvent $event){
		$t = $this->data->getAll();
		$c = $this->configFile()->getAll();
		if($this->isLoggedIn($event->getPlayer())){
			$this->logout($event->getPlayer());			
			if($c["sessions"]["enabled"] !== false){
				$this->startSession($event->getPlayer(), $c["sessions"]["session-login-available-minutes"]);
			}
		}
		if(!$this->isRegistered($event->getPlayer()) && isset($t[$event->getPlayer()->getName()])){
			unset($t[$event->getPlayer()->getName()]);
			$this->data->setAll($t);
			$this->data->save();
		}
	}
	
	//COMMANDS
	public function onCommand(CommandSender $issuer, Command $cmd, $label, array $args){
		switch($cmd->getName()){
			case "authme":
			  if($issuer->hasPermission("authmepe.command.authme")){
			  	if(isset($args[0])){
			  		switch($args[0]){
			  			case "version":
			  			  $issuer->sendMessage("You're using AuthMePE ported from AuthMe_Reloaded for Bukkit");
			  			  $issuer->sendMessage("Author: CyberCube-HK Team & hoyinm");
			  			  $issuer->sendMessage("Version: ".$this::VERSION);
			  			  return true;
			  			break;
			  			case "reload":
			  			  $this->getServer()->broadcastMessage("§bAuthMePE§d> §eReload starts!");
			  			  $this->getServer()->broadcastMessage("§7Reloading configuration..");
			  			  $this->configFile()->reload();
			  			  $this->getServer()->broadcastMessage("§7Reloading data files..");
			  			  $this->data->reload();
			  			  $this->ip->reload();
			  			  //$this->bans->reload();
			  			  $this->getServer()->broadcastMessage("§7Checking configuration..");
			  			  $this->reloadConfigFile();
			  			  $this->getServer()->broadcastMessage("§bAuthMePE§d> §aReload Complete!");
			  			  return true;
			  			break;
			  			case "login":
			  			case "l":
			  			  if(isset($args[1])){
			  			  	 $target = $this->getServer()->getPlayer($args[1]);
			  			  	 if($target !== null){
			  			  	 	 if($this->isLoggedIn($target) !== true){
			  			  	 	   $this->auth($target, 4);
			  			  	 	   $target->sendMessage("§4You have been logged in by admin!\n§aYou are now logged in.");
			  			  	 	   $issuer->sendMessage("§aTarget is now logged in.");
			  			  	 	   return true;
			  			  	 	 }else{
			  			  	 	 	 $issuer->sendMessage("§b".$target->getName()." is already logged in!");
			  			  	 	 	 return true;
			  			  	 	 }
			  			  	 }else{
			  			  	 	 $issuer->sendMessage("§cInvalid target!");
			  			  	 	 return true;
			  			  	 }
			  			  }else{
			  			  	$this->sendCommandUsage($issuer, "/authme login <player>");
			  			  	return true;
			  			  }
			  			break;
			  			case "changepass":
			  			case "changepassword":
			  			  if(isset($args[1]) && isset($args[2])){
			  			  	$target = $args[1];
			  			  	$t = $this->data->getAll();
			  			  	if(isset($t[$target])){
			  			  		$t[$target]["password"] = md5($args[2].$this->salt($args[2])) ;
			  			  		$this->data->setAll($t);
			  			  		$this->data->save();
			  			  		$issuer->sendMessage("§aYou changed §d".$target."§a's password to §b§l".$args[2]);
			  			  		if($this->isLoggedIn($this->getServer()->getPlayer($target))){
			  			  			$this->logout($this->getServer()->getPlayer($target));
			  			  			$this->getServer()->getPlayer($target)->sendMessage("§4Your password had been changed by admin!");
			  			  		}
			  			  		return true;
			  			  	}else{
			  			  		$issuer->sendMessage("$target is not registered!");
			  			  		return true;
			  			  	}
			  			  }else{
			  			    $this->sendCommandUsage($issuer, "/authme changepass <player> <password>");
			  			  	return true;
			  			  }
			  			break;
			  			case "register":
			  			  if(isset($args[2])){
			  			    $target = $args[1];
			  			    $password = $args[2];
			  			    $t = $this->data->getAll();
			  			    if(!isset($t[$target])){
			  			      $t[$target]["password"] = $args[2];
			  			      $t[$target]["ip"] = "no";
			  			      $t[$target]["times"] = 0;
			  			      $issuer->sendMessage("§aYou helped §b".$target." §ato register!");
			  			      return true;
			  			    }else{
			  			      $issuer->sendMessage("§cUser alreasy exists!");
			  			      return true;
			  			    }
			  			  }else{
			  			    $this->sendCommandUsage($issuer, "/authme register <player> <password>");
			  			    return true;
			  			  }
			  			break;
			  			case "unregister":
			  			  if(isset($args[1])){
			  			  	$target = $args[1];
			  			  	$t = $this->data->getAll();
			  			  	if(isset($t[$target])){
			  			  		unset($t[$target]);
			  			  		$this->data->setAll($t);
			  			  		$this->data->save();
			  			  		$issuer->sendMessage("§dYou removed §c".$target."§d's account!");
			  			  		if($this->getServer()->getPlayer($target) !== null && $this->isLoggedIn($this->getServer()->getPlayer($target))){
			  			  			$this->logout($this->getServer()->getPlayer($target));
			  			  			$this->getServer()->getPlayer($target)->kick("\n§4You have been unregistered by admin.\n§aRe-join server to register!");
			  			  			return true;
			  			  		}
			  			  	}else{
			  			  		$issuer->sendMessage("§cPlayer §b".$target." §cis not registered!");
			  			  		return true;
			  			  	}
			  			  }else{
			  			    $this->sendCommandUsage($issuer, "/authme unregister <player>");
			  			  	return true;
			  			  }
			  			break;
			  			case "setemail":
			  			case "chgemail":
			  			case "email":
			  			  if(isset($args[2])){
			  			  	 if(strpos($args[1], "@") !== false){
			  			  	 	 $target = $args[2];
			  			  	 	 $t = $this->data->getAll();
			  			  	 	 if(isset($t[$target])){
			  			  	 	   $t[$target]["email"] = $args[1];
			  			  	 	   $this->data->setAll($t);
			  			  	 	   $this->data->save();
			  			  	 	   $issuer->sendMessage("§aYou changed §6".$target."§a's email address!\n§aNew address: §6".$args[1]);
			  			  	 	   return true;
			  			  	 	 }else{
			  			  	 	 	  $issuer->sendMessage("§cNo record of player §2$target");
			  			  	 	 	  return true;
			  			  	 	 }
			  			  	 }else{
			  			  	 	  $issuer->sendMessage("§cPlease enter a valid email address!");
			  			  	 	  return true;
			  			  	 }
			  			  }else{
			  			    $this->sendCommandUsage($issuer, "/authme chgemail <email> <player>");
			  			  	return true;
			  			  }
			  			break;
			  			case "getemail":
			  			  if(isset($args[1])){
			  			  	$target = $args[1];
			  			  	$t = $this->data->getAll();
			  			  	if(isset($t[$target])){
			  			  		if(isset($t[$target]["email"])){
			  			  			$email = $this->getPlayerEmail($target);
			  			  			$issuer->sendMessage("Player §a".$target."§r§f's email address:\n§b".$email);
			  			  			return true;
			  			  		}else{
			  			  		   $issuer->sendMessage("§cCould not find §b".$target."§c's email address!");
			  			  		   return true;
			  			  		}
			  			  	}else{
			  			  	   $issuer->sendMessage("§cNo record of player §2$target");
			  			  	   return true;
			  			  	}
			  			  }else{
			  			    $this->sendCommandUsage($issuer, "/authme getemail <player>");
			  			     return true;
			  			  }
			  			break;
			  			case "help":
			  			case "h":
			  			  if(isset($args[1])){
			  			  	 switch($args[1]){
			  			  	 	  case 1:
			  			  	 	    $issuer->sendMessage("§e-------§bAuthMePE §1- §cAdmin Cmd§e-------");
			  			  	 	    $this->sendCommandUsage($issuer, "/authme reload");
			  			  	 	    $this->sendCommandUsage($issuer, "/authme login <player>");
			  			  	 	    $this->sendCommandUsage($issuer, "/authme changepass <player> <password>");
			  			  	 	    $this->sendCommandUsage($issuer, "/authme register <player> <password>");
			  			  	 	    $this->sendCommandUsage($issuer, "/authme unregister <player>");
			  			  	 	    $this->sendCommandUsage($issuer, "/authme version");
			  			  	 	    $this->sendCommandUsage($issuer, "/authme chgemail <email> <player>");
			  			  	 	    $this->sendCommandUsage($issuer, "/authme getemail <player>");
			  			  	 	    $issuer->sendMessage("§e----------------------------------");
			  			  	 	    return true;
			  			  	 	  break;
			  			  	 }
			  			  }else{
			  			    $this->getServer()->dispatchCommand($issuer, "authme help 1");
			  			     return true;
			  			  }
			  			break;
			  		}
			  	}else{
			  		$this->sendCommandUsage($issuer, "/authme help");
			  		return true;
			  	}
			  }else{
			  	$issuer->sendMessage("§cYou don't have permission for this!");
			  	return true;
			  }
			break;
			case "unregister":
			  if($issuer->hasPermission("authmepe.command.unregister")){
			  	if($issuer instanceof Player){
			  		$this->getServer()->getPluginManager()->callEvent($event = new PlayerUnregisterEvent($this, $issuer));
		       if($event->isCancelled()){
		       	$issuer->sendMessage("§cError during unregister!");
		       }else{
			  		  $t = $this->data->getAll();
			  		  unset($t[$issuer->getName()]);
			  		  $this->data->setAll($t);
			  		  $this->data->save();
			  		  $issuer->sendMessage("You successfully unregistered!");
			  		  $issuer->kick(TextFormat::GREEN."Re-join server to register.");
			  		  return true;
			  		}
			  	}else{
			  		$issuer->sendMessage("Please run this command in-game!");
			  		return true;
			  	}
			  }else{
			  	 $issuer->sendMessage("You have no permission for this!");
			  	 return true;
			  }
			break;
			case "changepass":
			  $t = $this->data->getAll();
			  if($issuer->hasPermission("authmepe.command.changepass")){
			  	if($issuer instanceof Player){
			  		if(count($args) == 3){
			  			if(md5($args[0].$this->salt($args[0])) == $t[$issuer->getName()]["password"]){
			  				if($args[1] == $args[2]){
			  					$this->getServer()->getPluginManager()->callEvent($event = new PlayerChangePasswordEvent($this, $issuer));
		            if($event->isCancelled()){
		       	      $issuer->sendMessage("§cError during changing password!");
			             return false;
		            }
			  					$t[$issuer->getName()]["password"] = md5($args[1].$this->salt($args[1]));
			  					$this->data->setAll($t);
			  					$this->data->save();
			  					$issuer->sendMessage(TextFormat::GREEN."Password changed to ".TextFormat::AQUA.TextFormat::BOLD.$args[1]);
			  					return true;
			  				}else{
			  					$issuer->sendMessage(TextFormat::RED."Confirm password INCORRECT");
			  					return true;
			  				}
			  			}else{
			  				$issuer->sendMessage(TextFormat::RED."Old password INCORRECT!");
			  				return true;
			  			}
			  		}else{
			  			$this->sendCommandUsage($issuer, "/changepass <old> <new> <new>");
			  			return true;
			  		}
			  	}else{
			  		$issuer->sendMessage("Please run this command in-game!");
			  		return true;
			  	}
			  }else{
			  	 $issuer->sendMessage("You have no permission for this!");
			  	 return true;
			  }
			break;
			case "chgemail":
			  if($issuer->hasPermission("authmepe.command.chgemail")){
			  	if($issuer instanceof Player){
			  		if(isset($args[0])){
			  		  $this->getServer()->getPluginManager()->callEvent($event = new PlayerAddEmailEvent($this, $issuer, $args[0]));
			  		  if($event->isCancelled() !== true){
			  		  	if(strpos($args[0], "@") !== false){
			  		  		$t = $this->data->getAll();
			  		     $t[$issuer->getName()]["email"] = $args[0];
			  		     $this->data->setAll($t);
			  		     $this->data->save();
			  		     $issuer->sendMessage("§aEmail changed successfully!\n§dAddress: §b".$args[0]);
			  		     return true;
			  		  	}else{
			  		  		$issuer->sendMessage("§cInvalid email!");
			  		  		return true;
			  		  	}
			  		  }
			  		}else{
			  			$this->sendCommandUsage($issuer, "/chgemail <email>");
			  			return true;
			  		}
			  	}else{
			  		$issuer->sendMessage("Please run this command in-game!");
			  		return true;
			  	}
			  }else{
			  	 $issuer->sendMessage("You have no permission for this!");
			  	 return true;
			  }
			break;
			case "logout":
			  if($issuer->hasPermission("authmepe.command.logout")){
			  	if($issuer instanceof Player){
			  		$t = $this->ip->getAll();
			  		$this->logout($issuer);
			  	  unset($t[$issuer->getName()]);
			  		$issuer->sendMessage("§aYou logged out successfully!");
			  		$this->ip->setAll($t);
			  		$this->ip->save();
			  		return true;
			  	}else{
			  		$issuer->sendMessage("Please run this command in-game!");
			  		return true;
			  	}
			  }else{
			  	 $issuer->sendMessage("You have no permission for this!");
			  	 return true;
			  }
			break;
		}
	}
	
	public function onDamage(EntityDamageEvent $event){
		if($event->getEntity() instanceof Player && !$this->isLoggedIn($event->getEntity())){
			$event->setCancelled(true);
		}
	}
	
	public function onBlockBreak(BlockBreakEvent $event){
		 $t = $this->data->getAll();
		if(!$this->isLoggedIn($event->getPlayer())){
			if($this->isRegistered($event->getPlayer())){
				$event->setCancelled(true);
			}else if(isset($t[$event->getPlayer()->getName()]["password"]) && !isset($t[$event->getPlayer()->getName()]["confirm"])){
				$event->getPlayer()->sendMessage("Type your password again to confirm!");
				$event->setCancelled(true);
			}else if(!$this->isRegistered($event->getPlayer()) && isset($t[$event->getPlayer()->getName()]["confirm"])){
				$event->getPlayer()->sendMessage("Please type yes/no into chat!");
				$event->setCancelled(true);
			}else if(!isset($t[$event->getPlayer()->getName()])){
				$event->getPlayer()->sendMessage("Please type your new password into chat to register.");
				$event->setCancelled(true);
			}
		}
	}
	
	public function onBlockPlace(BlockPlaceEvent $event){
		 $t = $this->data->getAll();
		if(!$this->isLoggedIn($event->getPlayer())){
			if($this->isRegistered($event->getPlayer())){
				$event->setCancelled(true);
			}else if(isset($t[$event->getPlayer()->getName()]["password"]) && !isset($t[$event->getPlayer()->getName()]["confirm"])){
				$event->getPlayer()->sendMessage("Type your password again to confirm!");
				$event->setCancelled(true);
			}else if(!$this->isRegistered($event->getPlayer()) && isset($t[$event->getPlayer()->getName()]["confirm"])){
				$event->getPlayer()->sendMessage("Please type yes/no into chat!");
				$event->setCancelled(true);
			}else if(!isset($t[$event->getPlayer()->getName()])){
				$event->getPlayer()->sendMessage("Please type your new password into chat to register.");
				$event->setCancelled(true);
			}
		}
	}
	
	public function onPlayerInteract(PlayerInteractEvent $event){
		 $t = $this->data->getAll();
		if(!$this->isLoggedIn($event->getPlayer())){
			if($this->isRegistered($event->getPlayer())){
				$event->setCancelled(true);
			}else if(isset($t[$event->getPlayer()->getName()]["password"]) && !isset($t[$event->getPlayer()->getName()]["confirm"])){
				$event->getPlayer()->sendMessage("Type your password again to confirm!");
				$event->setCancelled(true);
			}else if(!$this->isRegistered($event->getPlayer()) && isset($t[$event->getPlayer()->getName()]["confirm"])){
				$event->getPlayer()->sendMessage("Please type yes/no into chat!");
				$event->setCancelled(true);
			}else if(!isset($t[$event->getPlayer()->getName()])){
				$event->getPlayer()->sendMessage("Please type your new password into chat to register.");
				$event->setCancelled(true);
			}
		}
	}
	
	public function onPickupItem(InventoryPickupItemEvent $event){
		 $t = $this->data->getAll();
		if(!$this->isLoggedIn($event-> getInventory()->getHolder() )){
			if($this->isRegistered($event-> getInventory()->getHolder() )){
				$event->setCancelled(true);
			}else if(isset($t[$event-> getInventory()->getHolder() ->getName()]["password"]) && !isset($t[$event-> getInventory()->getHolder() ->getName()]["confirm"])){
				$event-> getInventory()->getHolder() ->sendMessage("Type your password again to confirm!");
				$event->setCancelled(true);
			}else if(!$this->isRegistered($event-> getInventory()->getHolder() ) && isset($t[$event-> getInventory()->getHolder() ->getName()]["confirm"])){
				$event-> getInventory()->getHolder() ->sendMessage("Please type yes/no into chat!");
				$event->setCancelled(true);
			}else if(!isset($t[$event-> getInventory()->getHolder() ->getName()])){
				$event-> getInventory()->getHolder() ->sendMessage("Please type your new password into chat to register.");
				$event->setCancelled(true);
			}
		}
	}
	
	public function getLoggedIn(){
		return $this->login;
	}
	
}

/* OTHER
 * ██████  █      ███   █████ █████
 * █       █     █   █  █     █     
 * █       █     █████  █████ █████
 * █       █     █   █      █     █ 
 * ██████  █████ █   █  █████ █████
 */

namespace AuthMePE;

use pocketmine\event\plugin\PluginEvent;
use AuthMePE\AuthMePE;

abstract class BaseEvent extends PluginEvent{
	
	public function __construct(AuthMePE $plugin){
		$this->plugin = $plugin;
		parent::__construct($plugin);
	}
}

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

namespace AuthMePE;

use pocketmine\event\Cancellable;
use pocketmine\Player;

use AuthMePE\AuthMePE;
use AuthMePE\BaseEvent;

class PlayerLogoutEvent extends BaseEvent implements Cancellable{
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

namespace AuthMePE;

use pocketmine\Player;
use pocketmine\event\Cancellable;

use AuthMePE\AuthMePE;
use AuthMePE\BaseEvent;

class PlayerRegisterEvent extends BaseEvent implements Cancellable{
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

namespace AuthMePE;

use pocketmine\Player;
use pocketmine\event\Cancellable;

use AuthMePE\AuthMePE;
use AuthMePE\BaseEvent;

class PlayerUnregisterEvent extends BaseEvent implements Cancellable{
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

namespace AuthMePE;

use pocketmine\Player;
use pocketmine\event\Cancellable;

use AuthMePE\AuthMePE;
use AuthMePE\BaseEvent;

class PlayerChangePasswordEvent extends BaseEvent implements Cancellable{
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

namespace AuthMePE;

use pocketmine\Player;

use AuthMePE\AuthMePE;
use AuthMePE\BaseEvent;

class PlayerLoginTimeoutEvent extends BaseEvent{
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

namespace AuthMePE;

use pocketmine\Player;
use pocketmine\event\Cancellable;

use AuthMePE\AuthMePE;
use AuthMePE\BaseEvent;

class PlayerAuthSessionStartEvent extends BaseEvent implements Cancellable{
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

namespace AuthMePE;

use pocketmine\scheduler\PluginTask;
use pocketmine\Player;
use pocketmine\level\sound\BatSound;
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
			  $this->player->getLevel()->addSound(new BatSound($this->player), $this->player->getServer()->getOnlinePlayers());
			break;
			case 2:
			  $this->player->getLevel()->addSound(new LaunchSound($this->player), $this->player->getServer()->getOnlinePlayers());
			break;
		}
	}
}

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
?>

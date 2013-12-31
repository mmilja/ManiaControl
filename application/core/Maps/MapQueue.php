<?php

namespace ManiaControl\Maps;
use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Callbacks\CallbackManager;
use ManiaControl\Commands\CommandListener;
use ManiaControl\Formatter;
use ManiaControl\ManiaControl;
use ManiaControl\Players\Player;

/**
 * MapQueue Class
 *
 * @author steeffeen & kremsy
 */
class MapQueue implements CallbackListener, CommandListener {
	/**
	 * Constants
	 */
	const CB_MAPQUEUE_CHANGED =  'MapQueue.MapQueueBoxChanged';

	const SETTING_SKIP_MAP_ON_LEAVE = 'Skip Map when the requester leaves';
	const SETTING_SKIP_MAPQUEUE_ADMIN = 'Skip Map when admin leaves';

	const ADMIN_COMMAND_CLEAR_MAPQUEUE = 'clearmapqueue';
	const ADMIN_COMMAND_CLEAR_JUKEBOX = 'clearjukebox';
	/**
	 * Private properties
	 */
	private $maniaControl = null;
	private $queuedMaps = array();
	private $nextMap = null;

	/**
	 * Create a new server MapQueue
	 *
	 * @param ManiaControl $maniaControl
	 */
	public function __construct(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;

		$this->maniaControl->callbackManager->registerCallbackListener(CallbackManager::CB_MC_ENDMAP, $this,'endMap');

		// Init settings
		$this->maniaControl->settingManager->initSetting($this, self::SETTING_SKIP_MAP_ON_LEAVE, true);
		$this->maniaControl->settingManager->initSetting($this, self::SETTING_SKIP_MAPQUEUE_ADMIN, false);

		//Register Admin Commands
		$this->maniaControl->commandManager->registerCommandListener(self::ADMIN_COMMAND_CLEAR_JUKEBOX, $this, 'command_ClearMapQueue', true);
		$this->maniaControl->commandManager->registerCommandListener(self::ADMIN_COMMAND_CLEAR_MAPQUEUE, $this, 'command_ClearMapQueue', true);
	}

	/**
	 * Clears the map-queue via admin command clearmap queue
	 * @param array  $chat
	 * @param Player $player
	 */
	public function command_ClearMapQueue(array $chat, Player $admin){
		$title = $this->maniaControl->authenticationManager->getAuthLevelName($admin->authLevel);

		//Destroy map - queue list
		$this->queuedMaps = array();

		$this->maniaControl->chat->sendInformation($title . ' $<' . $admin->nickname . '$> cleared the Queued-Map list!');
		$this->maniaControl->log($title .' ' . Formatter::stripCodes($admin->nickname) . ' cleared the Queued-Map list!');

		// Trigger callback
		$this->maniaControl->callbackManager->triggerCallback(self::CB_MAPQUEUE_CHANGED, array('clear'));
	}

	/**
	 * Adds a Map to the map-queue
	 * @param $login
	 * @param $uid
	 */
	public function addMapToMapQueue($login, $uid){ //TODO if from MX other message
		$player = $this->maniaControl->playerManager->getPlayer($login);

		//Check if the map is already juked
		if(array_key_exists($uid, $this->queuedMaps)){
			$this->maniaControl->chat->sendError('Map is already in the Map-Queue', $login);
			return;
		}

		//TODO recently maps not able to add to queue-amps setting, and management

		$map = $this->maniaControl->mapManager->getMapByUid($uid);

		$this->queuedMaps[$uid] = array($player, $map);

		$this->maniaControl->chat->sendInformation('$<' . $player->nickname . '$> added $<' . $map->name . '$> to the Map Queue');

		// Trigger callback
		$this->maniaControl->callbackManager->triggerCallback(self::CB_MAPQUEUE_CHANGED, array('add', $this->queuedMaps[$uid]));

	}

	/**
	 * Revmoes a Map from the Map queue
	 * @param $login
	 * @param $uid
	 */
	public function removeFromMapQueue($login, $uid){
		unset($this->queuedMaps[$uid]);
	}


	/**
	 * Called on endmap
	 * @param array $callback
	 */
	public function endMap(array $callback){
		$this->nextMap = null;
		if($this->maniaControl->settingManager->getSetting($this, self::SETTING_SKIP_MAP_ON_LEAVE) == TRUE){

			//Skip Map if requester has left
			foreach($this->queuedMaps as $queuedMap){
				$player = $queuedMap[0];

				//found player, so play this map
				if($this->maniaControl->playerManager->getPlayer($player->login) != null){
					break;
				}

				if($this->maniaControl->settingManager->getSetting($this, self::SETTING_SKIP_MAPQUEUE_ADMIN) == FALSE){
					//Check if the queuer is a admin
					if($player->authLevel > 0){
						break;
					}
				}

				// Trigger callback
				$this->maniaControl->callbackManager->triggerCallback(self::CB_MAPQUEUE_CHANGED, array('skip', $queuedMap[0]));

				//Player not found, so remove the map from the mapqueue
				array_shift($this->queuedMaps);

				$this->maniaControl->chat->sendInformation('Requested Map skipped because $<' . $player->nickname . '$> left!');
			}
		}

		$this->nextMap = array_shift($this->queuedMaps);

		//Check if Map Queue is empty
		if($this->nextMap == null)
			return;
		$map = $this->nextMap[1];


		$success = $this->maniaControl->client->query('ChooseNextMap', $map->fileName);
		if (!$success) {
			trigger_error('[' . $this->maniaControl->client->getErrorCode() . '] ChooseNextMap - ' . $this->maniaControl->client->getErrorCode(), E_USER_WARNING);
			return;
		}
	}

	/**
	 * Returns the next Map if the next map is a queuedmap or null if it's not
	 * @return null
	 */
	public function getNextMap(){
		return $this->nextMap;
	}
	/**
	 * Returns a list with the indexes of the queued maps
	 * @return array
	 */
	public function getQueuedMapsRanking(){
		$i = 1;
		$queuedMaps = array();
		foreach($this->queuedMaps as $map){
			$map = $map[1];
			$queuedMaps[$map->uid] = $i;
			$i++;
		}
		return $queuedMaps;
	}

	/**
	 * Dummy Function for testing
	 */
	public function printAllMaps(){
		foreach($this->queuedMaps as $map){
			$map = $map[1];
			var_dump($map->name);
		}
	}

} 
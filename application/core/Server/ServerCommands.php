<?php

namespace ManiaControl\Server;

use FML\Controls\Quads\Quad_Icons128x32_1;
use ManiaControl\Admin\AuthenticationManager;
use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Callbacks\CallbackManager;
use ManiaControl\Commands\CommandListener;
use ManiaControl\ManiaControl;
use ManiaControl\Manialinks\ManialinkPageAnswerListener;
use ManiaControl\Players\Player;

/**
 * Class offering various commands related to the dedicated server
 *
 * @author steeffeen & kremsy
 */
class ServerCommands implements CallbackListener, CommandListener, ManialinkPageAnswerListener {
	/**
	 * Constants
	 */
	const ACTION_SET_PAUSE = 'ServerCommands.SetPause';

	/**
	 * Private properties
	 */
	private $maniaControl = null;
	private $serverShutdownTime = -1;
	private $serverShutdownEmpty = false;

	/**
	 * Create a new server commands instance
	 *
	 * @param ManiaControl $maniaControl
	 */
	public function __construct(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;

		// Register for callbacks
		$this->maniaControl->callbackManager->registerCallbackListener(CallbackManager::CB_MC_5_SECOND, $this, 'each5Seconds');

		// Register for commands
		$this->maniaControl->commandManager->registerCommandListener('setpwd', $this, 'command_SetPwd', true);
		$this->maniaControl->commandManager->registerCommandListener('setservername', $this, 'command_SetServerName', true);
		$this->maniaControl->commandManager->registerCommandListener('setmaxplayers', $this, 'command_SetMaxPlayers', true);
		$this->maniaControl->commandManager->registerCommandListener('setmaxspectators', $this, 'command_SetMaxSpectators', true);
		$this->maniaControl->commandManager->registerCommandListener('setspecpwd', $this, 'command_SetSpecPwd', true);
		$this->maniaControl->commandManager->registerCommandListener('shutdownserver', $this, 'command_ShutdownServer', true);
		$this->maniaControl->commandManager->registerCommandListener('systeminfo', $this, 'command_SystemInfo', true);
		$this->maniaControl->commandManager->registerCommandListener('hideserver', $this, 'command_HideServer', true);
		$this->maniaControl->commandManager->registerCommandListener('showserver', $this, 'command_ShowServer', true);
		$this->maniaControl->commandManager->registerCommandListener('enablemapdownload', $this, 'command_EnableMapDownload', true);
		$this->maniaControl->commandManager->registerCommandListener('disablemapdownload', $this, 'command_DisableMapDownload', true);
		$this->maniaControl->commandManager->registerCommandListener('enablehorns', $this, 'command_EnableHorns', true);
		$this->maniaControl->commandManager->registerCommandListener('disablehorns', $this, 'command_DisableHorns', true);


		$this->maniaControl->manialinkManager->registerManialinkPageAnswerListener(self::ACTION_SET_PAUSE, $this, 'setPause');

		//TODO correct class?
		// Set Pause
		$itemQuad = new Quad_Icons128x32_1(); //TODO check if mode supports it
		$itemQuad->setSubStyle($itemQuad::SUBSTYLE_ManiaLinkSwitch);
		$itemQuad->setAction(self::ACTION_SET_PAUSE);
		$this->maniaControl->actionsMenu->addAdminMenuItem($itemQuad, 1, 'Pauses the current game.');

	}

	/**
	 * Breaks the current game
	 *
	 * @param array $callback
	 */
	public function setPause(array $callback) {
		$this->maniaControl->client->query('SendModeScriptCommands', array('Command_ForceWarmUp' => True));
		$success = $this->maniaControl->client->getResponse();
		if(!$success) {
			$this->maniaControl->chat->sendError("Error while setting the pause");
		}
	}

	/**
	 * Check stuff each 5 seconds
	 *
	 * @param array $callback
	 * @return bool
	 */
	public function each5Seconds(array $callback) {
		// Empty shutdown
		if($this->serverShutdownEmpty) {
			$players = $this->maniaControl->playerManager->getPlayers();
			if(count($players) <= 0) {
				$this->shutdownServer('empty');
			}
		}

		// Delayed shutdown
		if($this->serverShutdownTime > 0) {
			if(time() >= $this->serverShutdownTime) {
				$this->shutdownServer('delayed');
			}
		}
	}

	/**
	 * Handle //systeminfo command
	 *
	 * @param array  $chat
	 * @param Player $player
	 */
	public function command_SystemInfo(array $chat, Player $player) {
		if(!$this->maniaControl->authenticationManager->checkRight($player, AuthenticationManager::AUTH_LEVEL_SUPERADMIN)) {
			$this->maniaControl->authenticationManager->sendNotAllowed($player);
			return;
		}
		$systemInfo = $this->maniaControl->server->getSystemInfo();
		$message    = 'SystemInfo: ip=' . $systemInfo['PublishedIp'] . ', port=' . $systemInfo['Port'] . ', p2pPort=' . $systemInfo['P2PPort'] . ', title=' . $systemInfo['TitleId'] . ', login=' . $systemInfo['ServerLogin'] . '.';
		$this->maniaControl->chat->sendInformation($message, $player->login);
	}

	/**
	 * Handle //shutdownserver command
	 *
	 * @param array  $chat
	 * @param Player $player
	 */
	public function command_ShutdownServer(array $chat, Player $player) {
		if(!$this->maniaControl->authenticationManager->checkRight($player, AuthenticationManager::AUTH_LEVEL_SUPERADMIN)) {
			$this->maniaControl->authenticationManager->sendNotAllowed($player);
			return;
		}
		// Check for delayed shutdown
		$params = explode(' ', $chat[1][2]);
		if(count($params) >= 2) {
			$param = $params[1];
			if($param == 'empty') {
				$this->serverShutdownEmpty = !$this->serverShutdownEmpty;
				if($this->serverShutdownEmpty) {
					$this->maniaControl->chat->sendInformation("The server will shutdown as soon as it's empty!", $player->login);
					return;
				}
				$this->maniaControl->chat->sendInformation("Empty-shutdown cancelled!", $player->login);
				return;
			}
			$delay = (int)$param;
			if($delay <= 0) {
				// Cancel shutdown
				$this->serverShutdownTime = -1;
				$this->maniaControl->chat->sendInformation("Delayed shutdown cancelled!", $player->login);
				return;
			}
			// Trigger delayed shutdown
			$this->serverShutdownTime = time() + $delay * 60.;
			$this->maniaControl->chat->sendInformation("The server will shut down in {$delay} minutes!", $player->login);
			return;
		}
		$this->shutdownServer($player->login);
	}

	/**
	 * Handle //setservername command
	 *
	 * @param array  $chat
	 * @param Player $player
	 */
	public function command_SetServerName(array $chat, Player $player) {
		if(!$this->maniaControl->authenticationManager->checkRight($player, AuthenticationManager::AUTH_LEVEL_ADMIN)) {
			$this->maniaControl->authenticationManager->sendNotAllowed($player);
			return;
		}
		$params = explode(' ', $chat[1][2], 2);
		if(count($params) < 2) {
			$this->maniaControl->chat->sendUsageInfo('Usage example: //setservername ManiaPlanet Server', $player->login);
			return;
		}
		$serverName = $params[1];
		if(!$this->maniaControl->client->query('SetServerName', $serverName)) {
			$this->maniaControl->chat->sendError('Error occurred: ' . $this->maniaControl->getClientErrorText(), $player->login);
			return;
		}
		$this->maniaControl->chat->sendSuccess("Server name changed to: '{$serverName}'!", $player->login);
	}

	/**
	 * Handle //setpwd command
	 *
	 * @param array  $chatCallback
	 * @param Player $player
	 */
	public function command_SetPwd(array $chatCallback, Player $player) {
		if(!$this->maniaControl->authenticationManager->checkRight($player, AuthenticationManager::AUTH_LEVEL_ADMIN)) {
			$this->maniaControl->authenticationManager->sendNotAllowed($player);
			return;
		}
		$messageParts   = explode(' ', $chatCallback[1][2], 2);
		$password       = '';
		$successMessage = 'Password removed!';
		if(isset($messageParts[1])) {
			$password       = $messageParts[1];
			$successMessage = "Password changed to: '{$password}'!";
		}
		$success = $this->maniaControl->client->query('SetServerPassword', $password);
		if(!$success) {
			$this->maniaControl->chat->sendError('Error occurred: ' . $this->maniaControl->getClientErrorText(), $player->login);
			return;
		}
		$this->maniaControl->chat->sendSuccess($successMessage, $player->login);
	}

	/**
	 * Handle //setspecpwd command
	 *
	 * @param array  $chatCallback
	 * @param Player $player
	 */
	public function command_SetSpecPwd(array $chatCallback, Player $player) {
		if(!$this->maniaControl->authenticationManager->checkRight($player, AuthenticationManager::AUTH_LEVEL_ADMIN)) {
			$this->maniaControl->authenticationManager->sendNotAllowed($player);
			return;
		}
		$messageParts   = explode(' ', $chatCallback[1][2], 2);
		$password       = '';
		$successMessage = 'Spectator password removed!';
		if(isset($messageParts[1])) {
			$password       = $messageParts[1];
			$successMessage = "Spectator password changed to: '{$password}'!";
		}
		$success = $this->maniaControl->client->query('SetServerPasswordForSpectator', $password);
		if(!$success) {
			$this->maniaControl->chat->sendError('Error occurred: ' . $this->maniaControl->getClientErrorText(), $player->login);
			return;
		}
		$this->maniaControl->chat->sendSuccess($successMessage, $player->login);
	}

	/**
	 * Handle //setmaxplayers command
	 *
	 * @param array  $chatCallback
	 * @param Player $player
	 */
	public function command_SetMaxPlayers(array $chatCallback, Player $player) {
		if(!$this->maniaControl->authenticationManager->checkRight($player, AuthenticationManager::AUTH_LEVEL_ADMIN)) {
			$this->maniaControl->authenticationManager->sendNotAllowed($player);
			return;
		}
		$messageParts = explode(' ', $chatCallback[1][2], 2);
		if(!isset($messageParts[1])) {
			$this->maniaControl->chat->sendUsageInfo('Usage example: //setmaxplayers 16', $player->login);
			return;
		}
		$amount = $messageParts[1];
		if(!is_numeric($amount)) {
			$this->maniaControl->chat->sendUsageInfo('Usage example: //setmaxplayers 16', $player->login);
			return;
		}
		$amount = (int)$amount;
		if($amount < 0) {
			$amount = 0;
		}
		$success = $this->maniaControl->client->query('SetMaxPlayers', $amount);
		if(!$success) {
			$this->maniaControl->chat->sendError('Error occurred: ' . $this->maniaControl->getClientErrorText(), $player->login);
			return;
		}
		$this->maniaControl->chat->sendSuccess("Changed max players to: {$amount}", $player->login);
	}

	/**
	 * Handle //setmaxspectators command
	 *
	 * @param array  $chatCallback
	 * @param Player $player
	 */
	public function command_SetMaxSpectators(array $chatCallback, Player $player) {
		if(!$this->maniaControl->authenticationManager->checkRight($player, AuthenticationManager::AUTH_LEVEL_ADMIN)) {
			$this->maniaControl->authenticationManager->sendNotAllowed($player);
			return;
		}
		$messageParts = explode(' ', $chatCallback[1][2], 2);
		if(!isset($messageParts[1])) {
			$this->maniaControl->chat->sendUsageInfo('Usage example: //setmaxspectators 16', $player->login);
			return;
		}
		$amount = $messageParts[1];
		if(!is_numeric($amount)) {
			$this->maniaControl->chat->sendUsageInfo('Usage example: //setmaxspectators 16', $player->login);
			return;
		}
		$amount = (int)$amount;
		if($amount < 0) {
			$amount = 0;
		}
		$success = $this->maniaControl->client->query('SetMaxSpectators', $amount);
		if(!$success) {
			$this->maniaControl->chat->sendError('Error occurred: ' . $this->maniaControl->getClientErrorText(), $player->login);
			return;
		}
		$this->maniaControl->chat->sendSuccess("Changed max spectators to: {$amount}", $player->login);
	}

	/**
	 * Handle //hideserver command
	 *
	 * @param array  $chatCallback
	 * @param Player $player
	 */
	public function command_HideServer(array $chatCallback, Player $player) {
		if(!$this->maniaControl->authenticationManager->checkRight($player, AuthenticationManager::AUTH_LEVEL_ADMIN)) {
			$this->maniaControl->authenticationManager->sendNotAllowed($player);
			return;
		}
		$success = $this->maniaControl->client->query('SetHideServer', 1);
		if(!$success) {
			$this->maniaControl->chat->sendError('Error occurred: ' . $this->maniaControl->getClientErrorText(), $player->login);
			return;
		}
		$this->maniaControl->chat->sendSuccess('Server is now hidden!', $player->login);
	}

	/**
	 * Handle //showserver command
	 *
	 * @param array  $chatCallback
	 * @param Player $player
	 */
	public function command_ShowServer(array $chatCallback, Player $player) {
		if(!$this->maniaControl->authenticationManager->checkRight($player, AuthenticationManager::AUTH_LEVEL_ADMIN)) {
			$this->maniaControl->authenticationManager->sendNotAllowed($player);
			return;
		}
		$success = $this->maniaControl->client->query('SetHideServer', 0);
		if(!$success) {
			$this->maniaControl->chat->sendError('Error occurred: ' . $this->maniaControl->getClientErrorText(), $player->login);
			return;
		}
		$this->maniaControl->chat->sendSuccess('Server is now visible!', $player->login);
	}

	/**
	 * Handle //enablemapdownload command
	 *
	 * @param array  $chatCallback
	 * @param Player $player
	 */
	public function command_EnableMapDownload(array $chatCallback, Player $player) {
		if(!$this->maniaControl->authenticationManager->checkRight($player, AuthenticationManager::AUTH_LEVEL_ADMIN)) {
			$this->maniaControl->authenticationManager->sendNotAllowed($player);
			return;
		}
		$success = $this->maniaControl->client->query('AllowMapDownload', true);
		if(!$success) {
			$this->maniaControl->chat->sendError('Error occurred: ' . $this->maniaControl->getClientErrorText(), $player->login);
			return;
		}
		$this->maniaControl->chat->sendSuccess('Map Download is now enabled!', $player->login);
	}

	/**
	 * Handle //disablemapdownload command
	 *
	 * @param array  $chatCallback
	 * @param Player $player
	 */
	public function command_DisableMapDownload(array $chatCallback, Player $player) {
		if(!$this->maniaControl->authenticationManager->checkRight($player, AuthenticationManager::AUTH_LEVEL_ADMIN)) {
			$this->maniaControl->authenticationManager->sendNotAllowed($player);
			return;
		}
		$success = $this->maniaControl->client->query('AllowMapDownload', false);
		if(!$success) {
			$this->maniaControl->chat->sendError('Error occurred: ' . $this->maniaControl->getClientErrorText(), $player->login);
			return;
		}
		$this->maniaControl->chat->sendSuccess('Map Download is now disabled!', $player->login);
	}

	/**
	 * Handle //enablehorns command
	 *
	 * @param array  $chatCallback
	 * @param Player $player
	 */
	public function command_EnableHorns(array $chatCallback, Player $player) {
		if(!$this->maniaControl->authenticationManager->checkRight($player, AuthenticationManager::AUTH_LEVEL_MODERATOR)) {
			$this->maniaControl->authenticationManager->sendNotAllowed($player);
			return;
		}
		$success = $this->maniaControl->client->query('DisableHorns', false);
		if(!$success) {
			$this->maniaControl->chat->sendError('Error occurred: ' . $this->maniaControl->getClientErrorText(), $player->login);
			return;
		}
		$this->maniaControl->chat->sendSuccess('Horns enabled!', $player->login);
	}

	/**
	 * Handle //disablehorns command
	 *
	 * @param array  $chatCallback
	 * @param Player $player
	 */
	public function command_DisableHorns(array $chatCallback, Player $player) {
		if(!$this->maniaControl->authenticationManager->checkRight($player, AuthenticationManager::AUTH_LEVEL_MODERATOR)) {
			$this->maniaControl->authenticationManager->sendNotAllowed($player);
			return;
		}
		$success = $this->maniaControl->client->query('DisableHorns', true);
		if(!$success) {
			$this->maniaControl->chat->sendError('Error occurred: ' . $this->maniaControl->getClientErrorText(), $player->login);
			return;
		}
		$this->maniaControl->chat->sendSuccess('Horns disabled!', $player->login);
	}

	/**
	 * Perform server shutdown
	 *
	 * @param string $login
	 * @return bool
	 */
	private function shutdownServer($login = '#') {
		if(!$this->maniaControl->client->query('StopServer')) {
			trigger_error("Server shutdown command from '{login}' failed. " . $this->maniaControl->getClientErrorText());
			return false;
		}
		$this->maniaControl->quit("Server shutdown requested by '{$login}'");
		return true;
	}
}

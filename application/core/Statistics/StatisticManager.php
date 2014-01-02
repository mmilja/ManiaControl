<?php

namespace ManiaControl\Statistics;

use ManiaControl\ManiaControl;
use ManiaControl\Players\Player;

require_once __DIR__ . '/StatisticCollector.php';

/**
 * Statistic Manager Class
 *
 * @author steeffeen & kremsy
 */
//TODO db reference between player index and statsitics playerId
//TODO db reference between metadata statId and statistics statId
class StatisticManager {
	/**
	 * Constants
	 */
	const TABLE_STATMETADATA = 'mc_statmetadata';
	const TABLE_STATISTICS   = 'mc_statistics';

	const STAT_TYPE_INT = '0';

	/**
	 * Public Properties
	 */
	public $statisticCollector = null;

	/**
	 * Private Properties
	 */
	private $maniaControl = null;
	private $stats = array();

	/**
	 * Construct player manager
	 *
	 * @param \ManiaControl\ManiaControl $maniaControl
	 */
	public function __construct(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;
		$this->initTables();

		$this->statisticCollector = new StatisticCollector($maniaControl);

		//Store Stats MetaData
		$this->storeStatMetaData();
	}

	/**
	 * Get the value of an statistic
	 *
	 * @param      $statName
	 * @param      $playerId
	 * @param bool $serverLogin
	 * @return int
	 */
	public function getStatisticData($statName, $playerId, $serverLogin = false) {
		$mysqli = $this->maniaControl->database->mysqli;
		$statId = $this->getStatId($statName);

		if($statId == null) {
			return -1;
		}

		if(!$serverLogin) {
			$query = "SELECT SUM(value) as value FROM `" . self::TABLE_STATISTICS . "` WHERE `statId` = " . $statId . " AND `playerId` = " . $playerId . ";";
		} else {
			$query = "SELECT value FROM `" . self::TABLE_STATISTICS . "` WHERE `statId` = " . $statId . " AND `playerId` = " . $playerId . " AND `serverLogin` = '" . $serverLogin . "';";
		}

		$result = $mysqli->query($query);
		if(!$result) {
			trigger_error($mysqli->error);
		}

		$row = $result->fetch_object();

		$result->close();
		return $row->value;
	}

	/**
	 * Store Stats Meta Data from the Database
	 */
	private function storeStatMetaData() {
		$mysqli = $this->maniaControl->database->mysqli;

		$query  = "SELECT * FROM `" . self::TABLE_STATMETADATA . "`;";
		$result = $mysqli->query($query);
		if(!$result) {
			trigger_error($mysqli->error);
		}

		while($row = $result->fetch_object()) {
			$this->stats[$row->name] = $row;
		}

		$result->close();
	}

	/**
	 *    Returns the Stats Id
	 *
	 * @param $statName
	 * @return int
	 */
	private function getStatId($statName) {
		if(isset($this->stats[$statName])) {
			$stat = $this->stats[$statName];
			return (int)$stat->index;
		} else {
			return null;
		}
	}

	/**
	 * Inserts a Stat into the database
	 *
	 * @param        $statName
	 * @param Player $player
	 * @param string $serverLogin
	 * @param        $value , value to Add
	 * @param string $statType
	 * @return bool
	 */
	public function insertStat($statName, $player, $serverLogin = '', $value, $statType = self::STAT_TYPE_INT) {
		$statId = $this->getStatId($statName);

		if($player == null) {
			return false;
		}

		if($statId == null) {
			return false;
		}

		if($player->isFakePlayer()) {
			return true;
		}

		if($serverLogin == '') {
			$serverLogin = $this->maniaControl->server->getLogin();
		}


		$mysqli = $this->maniaControl->database->mysqli;

		$query = "INSERT INTO `" . self::TABLE_STATISTICS . "` (
					`serverLogin`,
					`playerId`,
					`statId`,
					`value`
				) VALUES (
				?, ?, ?, ?
				) ON DUPLICATE KEY UPDATE
				`value` = `value` + VALUES(`value`);";

		$statement = $mysqli->prepare($query);
		if($mysqli->error) {
			trigger_error($mysqli->error);
			return false;
		}
		$statement->bind_param('siii', $serverLogin, $player->index, $statId, $value);
		$statement->execute();
		if($statement->error) {
			trigger_error($statement->error);
			$statement->close();
			return false;
		}

		$statement->close();
		return true;
	}

	/**
	 * Increments a Statistic by one
	 *
	 * @param                              $statName
	 * @param \ManiaControl\Players\Player $player
	 * @param string                       $serverLogin
	 * @internal param \ManiaControl\Players\Player $playerId
	 * @return bool
	 */
	public function incrementStat($statName, $player, $serverLogin = '') {
		return $this->insertStat($statName, $player, $serverLogin, 1);
	}

	/**
	 * Defines a Stat
	 *
	 * @param string $statName
	 * @param string $statDescription
	 * @return bool
	 */
	public function defineStatMetaData($statName, $statDescription = '') {
		$mysqli    = $this->maniaControl->database->mysqli;
		$query     = "INSERT IGNORE INTO `" . self::TABLE_STATMETADATA . "` (
					`name`,
					`description`
				) VALUES (
					?, ?
				);";
		$statement = $mysqli->prepare($query);
		if($mysqli->error) {
			trigger_error($mysqli->error);
			return false;
		}
		$statement->bind_param('ss', $statName, $statDescription);
		$statement->execute();
		if($statement->error) {
			trigger_error($statement->error);
			$statement->close();
			return false;
		}

		$statement->close();

		return true;
	}

	/**
	 * Initialize necessary database tables
	 *
	 * @return bool
	 */
	private function initTables() {
		$mysqli = $this->maniaControl->database->mysqli;
		$query  = "CREATE TABLE IF NOT EXISTS `" . self::TABLE_STATMETADATA . "` (
				`index` int(11) NOT NULL AUTO_INCREMENT,
				`name` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
				`description` varchar(150) COLLATE utf8_unicode_ci,
				PRIMARY KEY (`index`),
				UNIQUE KEY `name` (`name`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Statistics Meta Data' AUTO_INCREMENT=1;";

		$statement = $mysqli->prepare($query);
		if($mysqli->error) {
			trigger_error($mysqli->error, E_USER_ERROR);

			return false;
		}
		$statement->execute();
		if($statement->error) {
			trigger_error($statement->error, E_USER_ERROR);

			return false;
		}
		$statement->close();

		$query = "CREATE TABLE IF NOT EXISTS `" . self::TABLE_STATISTICS . "` (
				`index` int(11) NOT NULL AUTO_INCREMENT,
				`serverLogin` varchar(50) NOT NULL,
				`playerId` int(11) NOT NULL,
				`statId` int(11) NOT NULL,
				`value` int(20) COLLATE utf8_unicode_ci NOT NULL,
				PRIMARY KEY (`index`),
				UNIQUE KEY `unique` (`statId`,`playerId`,`serverLogin`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Statistics' AUTO_INCREMENT=1;";

		$statement = $mysqli->prepare($query);
		if($mysqli->error) {
			trigger_error($mysqli->error, E_USER_ERROR);

			return false;
		}
		$statement->execute();
		if($statement->error) {
			trigger_error($statement->error, E_USER_ERROR);

			return false;
		}
		$statement->close();

		return true;
	}
} 
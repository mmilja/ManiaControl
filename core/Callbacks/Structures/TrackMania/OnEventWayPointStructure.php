<?php

namespace ManiaControl\Callbacks\Structures\TrackMania;


use ManiaControl\Callbacks\Models\RecordCallback;
use ManiaControl\Callbacks\Structures\Common\BasePlayerTimeStructure;
use ManiaControl\ManiaControl;
use ManiaControl\Utils\Formatter;

/**
 * Structure Class for the Default Event Structure Callback
 *
 * @api
 * @author    ManiaControl Team <mail@maniacontrol.com>
 * @copyright 2014-2017 ManiaControl Team
 * @license   http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class OnEventWayPointStructure extends BasePlayerTimeStructure {
	private $racetime;
	private $laptime;
	private $stuntsscore;
	private $checkpointinrace;
	private $checkpointinlap;
	private $isendrace;
	private $isendlap;
	private $blockid;
	private $speed;
	private $distance;

	/**
	 * OnEventWayPointStructure constructor.
	 *
	 * @param \ManiaControl\ManiaControl $maniaControl
	 * @param                            $data
	 */
	public function __construct(ManiaControl $maniaControl, $data) {
		parent::__construct($maniaControl, $data);

		$this->racetime         = (int) $this->getPlainJsonObject()->racetime;
		$this->laptime          = (int) $this->getPlainJsonObject()->laptime;
		$this->stuntsscore      = $this->getPlainJsonObject()->stuntsscore;
		$this->checkpointinrace = (int) $this->getPlainJsonObject()->checkpointinrace;
		$this->checkpointinlap  = (int) $this->getPlainJsonObject()->checkpointinlap;
		$this->isendrace        = $this->getPlainJsonObject()->isendrace;
		$this->isendlap         = $this->getPlainJsonObject()->isendlap;
		$this->blockid          = $this->getPlainJsonObject()->blockid;
		$this->speed            = $this->getPlainJsonObject()->speed;
		$this->distance         = $this->getPlainJsonObject()->distance;

		// Build callback
		$wayPointCallback              = new RecordCallback();
		$wayPointCallback->rawCallback = $data;
		$wayPointCallback->setPlayer($this->getPlayer());
		$wayPointCallback->blockId       = $this->blockid;
		$wayPointCallback->time          = $this->racetime;
		$wayPointCallback->checkpoint    = $this->checkpointinrace;
		$wayPointCallback->isEndRace     = Formatter::parseBoolean($this->isendrace);
		$wayPointCallback->lapTime       = $this->laptime;
		$wayPointCallback->lapCheckpoint = $this->checkpointinlap;
		$wayPointCallback->lap           = 0;
		$wayPointCallback->isEndLap      = Formatter::parseBoolean($this->isendlap);
		if ($wayPointCallback->checkpoint > 0) {
			$currentMap            = $this->maniaControl->getMapManager()->getCurrentMap();
			$wayPointCallback->lap += $wayPointCallback->checkpoint / $currentMap->nbCheckpoints;
		}
		if ($wayPointCallback->isEndRace) {
			$wayPointCallback->name = $wayPointCallback::FINISH;
		} else if ($wayPointCallback->isEndLap) {
			$wayPointCallback->name = $wayPointCallback::LAPFINISH;
		} else {
			$wayPointCallback->name = $wayPointCallback::CHECKPOINT;
		}
		$this->maniaControl->getCallbackManager()->triggerCallback($wayPointCallback);
	}
}
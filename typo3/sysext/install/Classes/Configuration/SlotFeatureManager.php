<?php
namespace TYPO3\CMS\Install\Configuration;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013 Christian Kuhn <lolli@schwarzbu.ch>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use TYPO3\CMS\Core\Utility\GeneralUtility;

class SlotFeatureManager {

	/**
	 * @var \TYPO3\CMS\Extbase\Object\ObjectManager
	 * @inject
	 */
	protected $objectManager = NULL;

	protected $slotRegistry = array(
		'Charset' => 'TYPO3\CMS\Install\Configuration\Charset\Slot',
	);

	public function getAll() {
		$slotFeatures = array();
		foreach ($this->slotRegistry as $slotName => $slotClass) {
			$slotInstance = $this->objectManager->get($slotClass);
			$slotInstance->initializeFeatures();
			$slotFeatures[$slotName] = $slotInstance;
		}
		return $slotFeatures;
	}

	public function activateSlotFeature($slotName, $featureName) {
		$slots = $this->getAll();
		$slot = $slots[$slotName];
		$features = $slot->getFeatures();
		$feature = $features[$featureName];
		$feature->activate();
	}
}
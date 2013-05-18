<?php
namespace TYPO3\CMS\Install\Action;

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
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Render system environment check
 */
class SystemEnvironment extends AbstractAction {

	/**
	 * Handle this menu action
	 *
	 * @return string Rendered html
	 */
	public function handle() {
		$html = '<h3>System environment check</h3>';

			/** @var $statusCheck \TYPO3\CMS\Install\SystemEnvironment\Check */
		$statusCheck = GeneralUtility::makeInstance('TYPO3\\CMS\\Install\\SystemEnvironment\\Check');
		$statusObjects = $statusCheck->getStatus();

			/** @var $statusUtility \TYPO3\CMS\Install\Status\StatusUtility */
		$statusUtility = GeneralUtility::makeInstance('TYPO3\\CMS\\Install\\Status\\StatusUtility');
		$sortedStatusObjects = $statusUtility->sortBySeverity($statusObjects);
		foreach ($sortedStatusObjects as $statusObjectsOfOneSeverity) {
			$html .= $statusUtility->renderStatusObjectsAsHtml($statusObjectsOfOneSeverity);
		}

		return $html;
	}
}
?>

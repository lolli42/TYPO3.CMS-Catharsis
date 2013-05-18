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
 * Handle folder structure
 */
class FolderStructure extends AbstractAction {

	public function handle() {
		/** @var $folderStructureFactory \TYPO3\CMS\Install\FolderStructure\DefaultFactory */
		$folderStructureFactory = GeneralUtility::makeInstance('TYPO3\\CMS\\Install\\FolderStructure\\DefaultFactory');
		/** @var $structureFacade \TYPO3\CMS\Install\FolderStructure\StructureFacade */
		$structureFacade = $folderStructureFactory->getStructure();

		$fixStatusObjects = array();
		if (isset($GLOBALS['_POST']['folderStructure']['fix'])) {
			$fixStatusObjects = $structureFacade->fix();
		}

		$html = array();
		$html[] = '<h3>File and folder status below ' . PATH_site . '</h3>';

		/** @var $statusUtility \TYPO3\CMS\Install\Status\StatusUtility */
		$statusUtility = GeneralUtility::makeInstance('TYPO3\\CMS\\Install\\Status\\StatusUtility');
		if (count($fixStatusObjects) > 0) {
			$html[] = '<h4>Fix action results:</h4>';
			$html[] = $statusUtility->renderStatusObjectsAsHtml($fixStatusObjects);
			$html[] = '<hr />';
		}

		/** @var $statusUtility \TYPO3\CMS\Install\Status\StatusUtility */
		$statusUtility = GeneralUtility::makeInstance('TYPO3\\CMS\\Install\\Status\\StatusUtility');
		$currentStatusObjects = $structureFacade->getStatus();
		$unfixableStatus = $statusUtility->filterBySeverity($currentStatusObjects, 'error');
		$fixableStatus = $statusUtility->filterBySeverity($currentStatusObjects, 'warning');
		$okStatus = $statusUtility->filterBySeverity($currentStatusObjects, 'ok');

		if (count($fixableStatus) > 0) {
			$html[] = '<form action="index.php?TYPO3_INSTALL[type]=folderStructure" method="post">';
			$html[] = '<button type="submit" name="folderStructure[fix]">';
			$html[] = 'Fix errors <span class="t3-install-form-button-icon-positive">&nbsp;</span>';
			$html[] = '</button>';
			$html[] = '</form>';
			$html[] = '<hr />';
		}

		if (count($unfixableStatus)) {
			$html[] = '<h4>These problems are not fixable:</h4>';
			$html[] = $statusUtility->renderStatusObjectsAsHtml($unfixableStatus);
		}
		if (count($fixableStatus)) {
			$html[] = '<h4>These problems are fixable:</h4>';
			$html[] = $statusUtility->renderStatusObjectsAsHtml($fixableStatus);
		}
		if (count($okStatus)) {
			$html[] = '<h4>These structures are ok:</h4>';
			$html[] = $statusUtility->renderStatusObjectsAsHtml($okStatus);
		}

		return implode(LF, $html);
	}
}
?>

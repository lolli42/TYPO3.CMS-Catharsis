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
 * Handle update wizard
 */
class UpdateWizard extends AbstractAction {

	public function handle() {
		$formValues = GeneralUtility::_GP('TYPO3_INSTALL');

		// call wizard
		$action = $formValues['database_type'] ? $formValues['database_type'] : 'checkForUpdate';
		$this->updateWizard_parts($action);
	}

	/**
	 * Implements the steps for the update wizard
	 *
	 * @param string $action Which should be done.
	 * @return void
	 */
	protected function updateWizard_parts($action) {
		$formValues = GeneralUtility::_GP('TYPO3_INSTALL');
		$content = '';
		$updateItems = array();
		// Get the template file
		$templateFile = @file_get_contents((PATH_site . $this->templateFilePath . 'UpdateWizardParts.html'));
		$title = '';
		switch ($action) {
			case 'getUserInput':
				$title = 'Step 2 - Configuration of updates';
				$getUserInputSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($templateFile, '###GETUSERINPUT###');
				$markers = array(
					'introduction' => 'The following updates will be performed:',
					'showDatabaseQueries' => 'Show database queries performed',
					'performUpdates' => 'Perform updates!',
					'action' => 'index.php?TYPO3_INSTALL[type]=update',
				);
				// update methods might need to get custom data
				$updatesAvailableSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($getUserInputSubpart, '###UPDATESAVAILABLE###');
				$updateItems = array();
				foreach ($formValues['update'] as $identifier => $tmp) {
					$updateMarkers = array();
					$className = $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/install']['update'][$identifier];
					$tmpObj = $this->getUpgradeObjInstance($className, $identifier);
					$updateMarkers['identifier'] = $identifier;
					$updateMarkers['title'] = $tmpObj->getTitle();
					if (method_exists($tmpObj, 'getUserInput')) {
						$updateMarkers['identifierMethod'] = $tmpObj->getUserInput('TYPO3_INSTALL[update][' . $identifier . ']');
					}
					$updateItems[] = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($updatesAvailableSubpart, $updateMarkers, '###|###', TRUE, TRUE);
				}
				$updatesAvailableSubpart = implode(LF, $updateItems);
				$content = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($getUserInputSubpart, '###UPDATESAVAILABLE###', $updatesAvailableSubpart);
				$content = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($content, $markers, '###|###', TRUE, FALSE);
				break;
			case 'performUpdate':
				// third step - perform update
				$title = 'Step 3 - Perform updates';
				$performUpdateSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($templateFile, '###PERFORMUPDATE###');
				$updateItemsSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($performUpdateSubpart, '###UPDATEITEMS###');
				$checkUserInputSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($updateItemsSubpart, '###CHECKUSERINPUT###');
				$updatePerformedSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($updateItemsSubpart, '###UPDATEPERFORMED###');
				$noPerformUpdateSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($updateItemsSubpart, '###NOPERFORMUPDATE###');
				$databaseQueriesSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($updatePerformedSubpart, '###DATABASEQUERIES###');
				$customOutputSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($updatePerformedSubpart, '###CUSTOMOUTPUT###');
				if (!$formValues['update']['extList']) {
					break;
				}
				$tmpObj = NULL;
				$this->getDatabase()->store_lastBuiltQuery = TRUE;
				foreach ($formValues['update']['extList'] as $identifier) {
					$className = $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/install']['update'][$identifier];
					$tmpObj = $this->getUpgradeObjInstance($className, $identifier);
					$updateItemsMarkers['identifier'] = $identifier;
					$updateItemsMarkers['title'] = $tmpObj->getTitle();
					// check user input if testing method is available
					$customOutput = '';
					$checkUserInput = '';
					$updatePerformed = '';
					$noPerformUpdate = '';
					if (method_exists($tmpObj, 'checkUserInput') && !$tmpObj->checkUserInput($customOutput)) {
						$userInputMarkers = array(
							'customOutput' => $customOutput ? $customOutput : 'Something went wrong',
							'goBack' => 'Go back to update configuration'
						);
						$checkUserInput = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($checkUserInputSubpart, $userInputMarkers, '###|###', TRUE, FALSE);
					} else {
						if (method_exists($tmpObj, 'performUpdate')) {
							$customOutput = '';
							$dbQueries = array();
							$databaseQueries = array();
							if ($tmpObj->performUpdate($dbQueries, $customOutput)) {
								$performUpdateMarkers['updateStatus'] = 'Update successful!';
							} else {
								$performUpdateMarkers['updateStatus'] = 'Update FAILED!';
							}
							if ($formValues['update']['showDatabaseQueries']) {
								$content .= '<br />' . implode('<br />', $dbQueries);
								foreach ($dbQueries as $query) {
									$databaseQueryMarkers['query'] = $query;
									$databaseQueries[] = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($databaseQueriesSubpart, $databaseQueryMarkers, '###|###', TRUE, FALSE);
								}
							}
							$customOutputItem = '';
							if (strlen($customOutput)) {
								$content .= '<br />' . $customOutput;
								$customOutputMarkers['custom'] = $customOutput;
								$customOutputItem = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($customOutputSubpart, $customOutputMarkers, '###|###', TRUE, FALSE);
							}
							$updatePerformed = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($updatePerformedSubpart, '###DATABASEQUERIES###', implode(LF, $databaseQueries));
							$updatePerformed = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($updatePerformed, '###CUSTOMOUTPUT###', $customOutputItem);
							$updatePerformed = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($updatePerformed, $performUpdateMarkers, '###|###', TRUE, FALSE);
						} else {
							$noPerformUpdateMarkers['noUpdateMethod'] = 'No update method available!';
							$noPerformUpdate = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($noPerformUpdateSubpart, $noPerformUpdateMarkers, '###|###', TRUE, FALSE);
						}
					}
					$updateItem = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($updateItemsSubpart, '###CHECKUSERINPUT###', $checkUserInput);
					$updateItem = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($updateItem, '###UPDATEPERFORMED###', $updatePerformed);
					$updateItem = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($updateItem, '###NOPERFORMUPDATE###', $noPerformUpdate);
					$updateItem = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($updateItem, '###UPDATEITEMS###', implode(LF, $updateItems));
					$updateItems[] = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($updateItem, $updateItemsMarkers, '###|###', TRUE, FALSE);
				}
				$content = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($performUpdateSubpart, '###UPDATEITEMS###', implode(LF, $updateItems));
				$this->getDatabase()->store_lastBuiltQuery = FALSE;
				// also render the link to the next update wizard, if available
				$nextUpdateWizard = $this->getNextUpdadeWizardInstance($tmpObj);
				if ($nextUpdateWizard) {
					$content = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($content, array('NEXTIDENTIFIER' => $nextUpdateWizard->getIdentifier()), '###|###', TRUE, FALSE);
				} else {
					// no next wizard, also hide the button to the next update wizard
					$content = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($content, '###NEXTUPDATEWIZARD###', '');
				}
				break;
		}
		$this->message('Upgrade Wizard', $title, $content);
	}

	/**
	 * Creates instance of an upgrade object, setting the pObj, versionNumber and pObj
	 *
	 * @param string $className The class name
	 * @param string $identifier The identifier of upgrade object - needed to fetch user input
	 * @return object Newly instantiated upgrade object
	 */
	protected function getUpgradeObjInstance($className, $identifier) {
		$formValues = GeneralUtility::_GP('TYPO3_INSTALL');
		$tmpObj = GeneralUtility::getUserObj($className);
		$tmpObj->setIdentifier($identifier);
		$tmpObj->versionNumber = \TYPO3\CMS\Core\Utility\VersionNumberUtility::convertVersionNumberToInteger(TYPO3_version);
		$tmpObj->pObj = $this;
		$tmpObj->userInput = $formValues['update'][$identifier];
		return $tmpObj;
	}

	/**
	 * Returns the next upgrade wizard object.
	 *
	 * Used to show the link/button to the next upgrade wizard
	 *
	 * @param object $currentObj current Upgrade Wizard Object
	 * @return mixed Upgrade Wizard instance or FALSE
	 */
	protected function getNextUpdadeWizardInstance($currentObj) {
		$isPreviousRecord = TRUE;
		foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/install']['update'] as $identifier => $className) {
			// first, find the current update wizard, and then start validating the next ones
			if ($currentObj->getIdentifier() == $identifier) {
				$isPreviousRecord = FALSE;
				continue;
			}
			if (!$isPreviousRecord) {
				$nextUpdateWizard = $this->getUpgradeObjInstance($className, $identifier);
				if ($nextUpdateWizard->shouldRenderWizard()) {
					return $nextUpdateWizard;
				}
			}
		}
		return FALSE;
	}
}
?>

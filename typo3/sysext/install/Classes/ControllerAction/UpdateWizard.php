<?php
namespace TYPO3\CMS\Install\ControllerAction;

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
 * Handle upgrade wizards
 */
class UpdateWizard extends AbstractAction implements ActionInterface {

	/**
	 * @var array<\TYPO3\CMS\Install\Status\StatusInterface>
	 */
	protected $actionMessages = array();

	/**
	 * Handle this action
	 *
	 * @return string content
	 */
	public function handle() {
		$this->initialize();

		// ext_localconf, db and ext_tables must be loaded for the upgrade wizards
		$this->loadExtLocalconfDatabaseAndExtTables();

		// Perform silent cache framework table upgrades
		$this->silentCacheFrameworkTableSchemaMigration();

		$actionMessages = array();

		// Show possible updates
		$this->updateList();

		$this->view->assign('actionMessages', $actionMessages);

		return $this->view->render();
	}

	/**
	 * List of available updates
	 *
	 * @return void
	 */
	protected function updateList() {
		if (empty($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/install']['update'])) {
			/** @var $message \TYPO3\CMS\Install\Status\StatusInterface */
			$message = $this->objectManager->get('TYPO3\\CMS\\Install\\Status\\OkStatus');
			$message->setTitle('No update wizards registered');
			$this->actionMessages[] = $message;
			return;
		}

		$availableUpdates = array();
		foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/install']['update'] as $identifier => $className) {
			$updateObject = $this->getUpgradeObjectInstance($className, $identifier);
			if ($updateObject->shouldRenderWizard()) {
				// $explanation is changed by reference in upgrade objects!
				$explanation = '';
				$updateObject->checkForUpdate($explanation);
				$availableUpdates[$identifier] = array(
					'identifier' => $identifier,
					'title' => $updateObject->getTitle(),
					'explanation' => $explanation,
					'renderNext' => FALSE,
				);
				// There are upgrade wizards that only show text and don't want to be executed
				if ($updateObject->shouldRenderNextButton()) {
					$availableUpdates[$identifier]['renderNext'] = TRUE;
				}
			}
		}

		$this->view->assign('availableUpdates', $availableUpdates);
	}

	/**
	 * Creates instance of an upgrade object, setting the pObj, versionNumber and pObj
	 *
	 * @param string $className The class name
	 * @param string $identifier The identifier of upgrade object - needed to fetch user input
	 * @return object Newly instantiated upgrade object
	 */
	protected function getUpgradeObjectInstance($className, $identifier) {
		$formValues = $this->postValues;
		$updateObject = GeneralUtility::getUserObj($className);
		$updateObject->setIdentifier($identifier);
		$updateObject->versionNumber = \TYPO3\CMS\Core\Utility\VersionNumberUtility::convertVersionNumberToInteger(TYPO3_version);
		$updateObject->pObj = $this;
		$updateObject->userInput = $formValues['update'][$identifier];
		return $updateObject;
	}

	/**
	 * Force creation / update of caching framework tables that are needed by some update wizards
	 *
	 * @TODO: This might (?) be a 'silent upgrade' task in the step installer, problem is that it needs ext_* loaded
	 * @TODO: See also the other remarks on this topic in the abstract class, this whole area needs improvements
	 * @return void
	 */
	protected function silentCacheFrameworkTableSchemaMigration() {
		/** @var $sqlHandler \TYPO3\CMS\Install\Sql\SchemaMigrator */
		$sqlHandler = GeneralUtility::makeInstance('TYPO3\\CMS\\Install\\Sql\\SchemaMigrator');

		// Forces creation / update of caching framework tables that are needed by some update wizards
		$cacheTablesConfiguration = implode(
			LF,
			$sqlHandler->getStatementArray($this->getCachingFrameworkRequiredDatabaseSchema(), 1, '^CREATE TABLE ')
		);
		$neededTableDefinition = $sqlHandler->getFieldDefinitions_fileContent($cacheTablesConfiguration);
		$currentTableDefinition = $sqlHandler->getFieldDefinitions_database();
		$updateTableDefinition = $sqlHandler->getDatabaseExtra($neededTableDefinition, $currentTableDefinition);
		$updateStatements = $sqlHandler->getUpdateSuggestions($updateTableDefinition);
		if (isset($updateStatements['create_table']) && count($updateStatements['create_table']) > 0) {
			$sqlHandler->performUpdateQueries($updateStatements['create_table'], $updateStatements['create_table']);
		}
		if (isset($updateStatements['add']) && count($updateStatements['add']) > 0) {
			$sqlHandler->performUpdateQueries($updateStatements['add'], $updateStatements['add']);
		}
		if (isset($updateStatements['change']) && count($updateStatements['change']) > 0) {
			$sqlHandler->performUpdateQueries($updateStatements['change'], $updateStatements['change']);
		}
	}
}

?>
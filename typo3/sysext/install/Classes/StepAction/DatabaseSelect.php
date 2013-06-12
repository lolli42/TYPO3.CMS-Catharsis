<?php
namespace TYPO3\CMS\Install\StepAction;

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

/**
 * Database select step.
 * This step is only rendered if database is mysql. With dbal,
 * database name is submitted by previous step already.
 */
class DatabaseSelect extends AbstractStep implements StepInterface {

	/**
	 * @var \TYPO3\CMS\Core\Database\DatabaseConnection
	 */
	protected $databaseConnection = NULL;

	/**
	 * Default constructor
	 */
	public function __construct() {
		$this->databaseConnection = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Database\\DatabaseConnection');
		$this->databaseConnection->setDatabaseUsername($GLOBALS['TYPO3_CONF_VARS']['DB']['username']);
		$this->databaseConnection->setDatabasePassword($GLOBALS['TYPO3_CONF_VARS']['DB']['password']);
		$this->databaseConnection->setDatabaseHost($GLOBALS['TYPO3_CONF_VARS']['DB']['host']);
		$this->databaseConnection->setDatabasePort($GLOBALS['TYPO3_CONF_VARS']['DB']['port']);
		$this->databaseConnection->sql_pconnect();
	}

	/**
	 * Create database if needed, save selected db name in configuration
	 *
	 * @return array<\TYPO3\CMS\Install\Status\StatusInterface>
	 */
	public function execute() {
		$result = array();

		$localConfigurationPathValuePairs = array();
		if ($GLOBALS['_POST']['databaseSelect']['type'] === 'new') {
			$newDatabaseName = $GLOBALS['_POST']['databaseSelect']['new'];
			if (strlen($newDatabaseName) <= 50) {
				$createDatabaseResult = $this->databaseConnection->admin_query('CREATE DATABASE ' . $newDatabaseName . ' CHARACTER SET utf8');
				if ($createDatabaseResult) {
					$localConfigurationPathValuePairs['DB/database'] = $newDatabaseName;
				} else {
					/** @var $errorStatus \TYPO3\CMS\Install\Status\ErrorStatus */
					$errorStatus = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Install\\Status\\ErrorStatus');
					$errorStatus->setTitle('Unable to create database');
					$errorStatus->setMessage(
						'Database with name ' . $newDatabaseName . ' could not be created.' .
						' Your database user probably has no sufficient permissions to do so. Please choose an existing (empty)' .
						' database or contact administration.'
					);
					$result[] = $errorStatus;
				}
			} else {
				/** @var $errorStatus \TYPO3\CMS\Install\Status\ErrorStatus */
				$errorStatus = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Install\\Status\\ErrorStatus');
				$errorStatus->setTitle('Database name not valid');
				$errorStatus->setMessage('Given database name must be shorter than fifty characters.');
				$result[] = $errorStatus;
			}
		} elseif ($GLOBALS['_POST']['databaseSelect']['type'] === 'existing') {
			$localConfigurationPathValuePairs['DB/database'] = $GLOBALS['_POST']['databaseSelect']['existing'];
		}

		if (!empty($localConfigurationPathValuePairs)) {
			/** @var $configurationManager \TYPO3\CMS\Core\Configuration\ConfigurationManager */
			$configurationManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Configuration\\ConfigurationManager');
			$configurationManager->setLocalConfigurationValuesByPathValuePairs($localConfigurationPathValuePairs);
			$this->reloadConfiguration();
		}

		return $result;
	}

	/**
	 * Step needs to be executed if database connection is no successful.
	 *
	 * @return boolean
	 */
	public function needsExecution() {
		$result = TRUE;
		if (strlen($GLOBALS['TYPO3_CONF_VARS']['DB']['database']) > 0) {
			$this->databaseConnection->setDatabaseName($GLOBALS['TYPO3_CONF_VARS']['DB']['database']);
			try {
				$selectResult = $this->databaseConnection->sql_select_db();
				if ($selectResult === TRUE) {
					$result = FALSE;
				}
			} catch (\RuntimeException $e) {
			}
		}
		return $result;
	}

	/**
	 * Render this step
	 *
	 * @return string
	 */
	public function render() {
		$this->reloadConfiguration();

		$html = array();

		$html[] = '<h3>Select database</h3>';
		$html[] = 'You have two options:';
		$html[] = '<form method="post" id="stepInstaller-databaseSelect" action="StepInstaller.php">';
		$html[] = '<input type="hidden" value="databaseSelect" name="executeStep" />';
		$html[] = '<fieldset>';
		$html[] = '<ul>';
		$html[] = '<li>';
		$html[] = '<input id="t3-install-form-db-select-type-new" type="radio" name="databaseSelect[type]" value="new" checked="checked" class="radio">';
		$html[] = '<div>';
		$html[] = '<label>Create a new database (recommended):</label>';
		$html[] = '<p>Enter a name for your TYPO3 database.</p>';
		$html[] = '<input class="t3-install-form-input-text" type="text" name="databaseSelect[new]" checked="checked" onfocus="document.getElementById(\'t3-install-form-db-select-type-new\').checked=true;">';
		$html[] = '</div>';
		$html[] = '</li>';
		$html[] = '<li>';
		$html[] = '<input type="radio" name="databaseSelect[type]" id="t3-install-form-db-select-type-existing" value="existing" class="radio">';
		$html[] = '<div>';
		$html[] = '<label>Select an EMPTY existing database:</label>';
		$html[] = '<p>Any tables used by TYPO3 will be overwritten.</p>';
		$html[] = '<select name="databaseSelect[existing]" onfocus="document.getElementById(\'t3-install-form-db-select-type-existing\').checked=true;">';
		$html[] = '<option value="">Select database</option>';
		$databaseList = $this->getDatabaseList();
		foreach ($databaseList as $databaseSelect) {
			$html[] = '<option value="' . $databaseSelect . '">' . $databaseSelect . '</option>';
		}
		$html[] = '</select>';
		$html[] = '</div>';
		$html[] = '</li>';
		$html[] = '</ul>';
		$html[] = '</fieldset>';
		$html[] = '<button type="submit">';
		$html[] = 'Continue';
		$html[] = '<span class="t3-install-form-button-icon-positive">&nbsp;</span>';
		$html[] = '</button>';
		$html[] = '</form>';

		return implode(CR, $html);
	}

	/**
	 * Returns list of available databases (with access-check based on username/password)
	 *
	 * @return array List of available databases
	 */
	protected function getDatabaseList() {
		$databaseArray = $this->databaseConnection->admin_get_dbs();
		// Remove mysql organizational tables from database list
		$reservedDatabaseNames = array('mysql', 'information_schema', 'performance_schema');
		$allPossibleDatabases = array_diff($databaseArray, $reservedDatabaseNames);
		$databasesWithoutTables = array();
		foreach ($allPossibleDatabases as $database) {
			$this->databaseConnection->setDatabaseName($database);
			$this->databaseConnection->sql_select_db();
			$existingTables = $this->databaseConnection->admin_get_tables();
			if (count($existingTables) === 0) {
				$databasesWithoutTables[] = $database;
			}
		}
		return $databasesWithoutTables;
	}
}
?>
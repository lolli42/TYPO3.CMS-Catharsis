<?php
namespace TYPO3\CMS\Install\StepInstaller\Step;

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
 * Populate base tables, insert admin user, set install tool password
 */
class DatabaseData implements StepInterface {

	/**
	 * @var \TYPO3\CMS\Core\Database\DatabaseConnection
	 */
	protected $databaseConnection = NULL;

	/**
	 * Default constructor
	 */
	public function __construct() {
		\TYPO3\CMS\Core\Core\Bootstrap::getInstance()
			->startOutputBuffering()
			->loadConfigurationAndInitialize();
		$GLOBALS['TYPO3_LOADED_EXT'] = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::loadTypo3LoadedExtensionInformation(FALSE);

		if ($this->isDbalEnabled()) {
			require(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('dbal') . 'ext_localconf.php');
			$GLOBALS['typo3CacheManager']->setCacheConfigurations($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']);
		}

		$this->databaseConnection = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Database\\DatabaseConnection');
		$this->databaseConnection->setDatabaseUsername($GLOBALS['TYPO3_CONF_VARS']['DB']['username']);
		$this->databaseConnection->setDatabasePassword($GLOBALS['TYPO3_CONF_VARS']['DB']['password']);
		$this->databaseConnection->setDatabaseHost($GLOBALS['TYPO3_CONF_VARS']['DB']['host']);
		$this->databaseConnection->setDatabasePort($GLOBALS['TYPO3_CONF_VARS']['DB']['port']);
		$this->databaseConnection->setDatabaseName($GLOBALS['TYPO3_CONF_VARS']['DB']['database']);
		$this->databaseConnection->connectDB();
	}

	/**
	 * Create database if needed, save name.
	 *
	 * @return array<\TYPO3\CMS\Install\Status\StatusInterface>
	 */
	public function execute() {
		var_dump($_POST);
		$result = array();

		// Get sql string of all core extensions for base table layout and records
		$sql = '';
		$loadedExtensions = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::getLoadedExtensionListArray();
		foreach ($loadedExtensions as $extension) {
			// The installer steps are called on every install tool request. But this step should only be
			// executed if it is a fresh install. We need ext_tables.sql and ext_tables_static.sql content
			// of core extensions only here and ensure this by loading those files only from core extensions.
			$extensionPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($extension);
			if (\TYPO3\CMS\Core\Utility\GeneralUtility::isFirstPartOfStr($extensionPath, PATH_site . 'typo3/sysext/')) {
				if (file_exists($extensionPath . 'ext_tables.sql')) {
					$sql .= LF . file_get_contents($extensionPath . 'ext_tables.sql');
				}
				if (file_exists($extensionPath . 'ext_tables_static+adt.sql')) {
					$sql .= LF . file_get_contents($extensionPath . 'ext_tables_static+adt.sql');
				}
			}
		}
		// Add table definitions of core cache tables
		$sql .= \TYPO3\CMS\Core\Cache\Cache::getDatabaseTableDefinitions();

		// Sql handler helps to merge the sql string to full create table statements and insert records
		/** @var $sqlHandler \TYPO3\CMS\Install\Sql\SchemaMigrator */
		$sqlHandler = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Install\\Sql\\SchemaMigrator');
		$statements = $sqlHandler->getStatementArray($sql, TRUE);
		list($createStatementsArray, $insertCount) = $sqlHandler->getCreateTables($statements, TRUE);
		foreach ($createStatementsArray as $statement) {
			$queryResult = $this->databaseConnection->admin_query($statement);
			if ($queryResult === FALSE) {
				/** @var $errorStatus \TYPO3\CMS\Install\Status\ErrorStatus */
				$errorStatus = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Install\\Status\\ErrorStatus');
				$errorStatus->setTitle('Error creating table');
				$errorStatus->setMessage('Sql statement failed with error message ' . $this->databaseConnection->sql_error());
				$result[] = $errorStatus;
			}
		}
		$insertRecordTables = array_keys($insertCount);
		foreach ($insertRecordTables as $tableName) {
			$insert = $sqlHandler->getTableInsertStatements($statements, $tableName);
			foreach ($insert as $sql) {
				$queryResult = $this->databaseConnection->admin_query($sql);
				if ($queryResult === FALSE) {
					/** @var $errorStatus \TYPO3\CMS\Install\Status\ErrorStatus */
					$errorStatus = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Install\\Status\\ErrorStatus');
					$errorStatus->setTitle('Error inserting record to table');
					$errorStatus->setMessage('Sql statement failed with error message ' . $this->databaseConnection->sql_error());
					$result[] = $errorStatus;
				}
			}
		}

		return $result;
	}

	/**
	 * Step needs to be executed if database connection is no successful.
	 *
	 * @return boolean
	 */
	public function needsExecution() {
		// do, if there are no tables yet
		// do, if there is no admin user yet
		return TRUE;
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

		$html[] = '<h3>Create user and import base data</h3>';
		$html[] = '<p>Import basic database structure and create a backend user with administrator privileges.';
		$html[] = ' The password can be used to additionally log in to the install tool.</p>';
		$html[] = '<form method="post" action="StepInstaller.php">';
		$html[] = '<input type="hidden" value="databaseData" name="executeStep" />';
		$html[] = '<button type="submit">';
		$html[] = 'Continue';
		$html[] = '<span class="t3-install-form-button-icon-positive">&nbsp;</span>';
		$html[] = '</button>';
		$html[] = '</form>';

		return implode(CR, $html);
	}

	/**
	 * Return TRUE if dbal and adodb extension is loaded
	 *
	 * @return boolean TRUE if dbal and adodb is loaded
	 */
	protected function isDbalEnabled() {
		if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('adodb')
			&& \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('dbal')
		) {
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * Re-populate TYPO3_CONF_VARS in case they were changed during execution
	 *
	 * @return void
	 */
	protected function reloadConfiguration() {
		// Load LocalConfiguration / AdditionalConfiguration again to force fresh values
		// in TYPO3_CONF_VARS in case they were written in execute()
		/** @var $configurationManager \TYPO3\CMS\Core\Configuration\ConfigurationManager */
		$configurationManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Configuration\\ConfigurationManager');
		$configurationManager->exportConfiguration();
	}
}
?>
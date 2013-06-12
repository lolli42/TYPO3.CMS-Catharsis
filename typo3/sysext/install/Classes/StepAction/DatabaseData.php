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
 * Populate base tables, insert admin user, set install tool password
 */
class DatabaseData extends AbstractStepAction implements StepActionInterface {

	/**
	 * @var \TYPO3\CMS\Core\Database\DatabaseConnection
	 */
	protected $databaseConnection = NULL;

	/**
	 * Default constructor
	 */
	public function __construct() {
		$this->reloadConfiguration();

		\TYPO3\CMS\Core\Core\Bootstrap::getInstance()
			->startOutputBuffering()
			->loadConfigurationAndInitialize();
		$GLOBALS['TYPO3_LOADED_EXT'] = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::loadTypo3LoadedExtensionInformation(FALSE);

		$this->databaseConnection = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Database\\DatabaseConnection');
		$this->databaseConnection->setDatabaseUsername($GLOBALS['TYPO3_CONF_VARS']['DB']['username']);
		$this->databaseConnection->setDatabasePassword($GLOBALS['TYPO3_CONF_VARS']['DB']['password']);
		$this->databaseConnection->setDatabaseHost($GLOBALS['TYPO3_CONF_VARS']['DB']['host']);
		$this->databaseConnection->setDatabasePort($GLOBALS['TYPO3_CONF_VARS']['DB']['port']);
		$this->databaseConnection->setDatabaseName($GLOBALS['TYPO3_CONF_VARS']['DB']['database']);
		$this->databaseConnection->connectDB();
	}

	/**
	 * Import tables and data, create admin user, create install tool password
	 *
	 * @return array<\TYPO3\CMS\Install\Status\StatusInterface>
	 */
	public function execute() {
		$result = array();

		// Check password and return early if not good enough
		$password = $GLOBALS['_POST']['databaseData']['password'];
		if (strlen($password) < 8) {
			$errorStatus = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Install\\Status\\ErrorStatus');
			$errorStatus->setTitle('Administrator password not good enough!');
			$errorStatus->setMessage(
				'You are setting an important password here! It gives an attacker full control over your instance if cracked.' .
				' It should be strong (include lower and upper case characters, special characters and numbers) and must be at least eight characters long.'
			);
			$result[] = $errorStatus;
			return $result;
		}

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
		/** @var $sqlHandler \TYPO3\CMS\Install\Service\SqlSchemaMigrationService */
		$sqlHandler = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Install\\Service\\SqlSchemaMigrationService');
		$statements = $sqlHandler->getStatementArray($sql, TRUE);
		list($createStatementsArray, $insertCount) = $sqlHandler->getCreateTables($statements, TRUE);
		foreach ($createStatementsArray as $table => $statement) {
			// Drop the table if it exists already
			$queryResult = $this->databaseConnection->admin_query('DROP TABLE IF EXISTS ' . $table);
			if ($queryResult === FALSE) {
				/** @var $errorStatus \TYPO3\CMS\Install\Status\ErrorStatus */
				$errorStatus = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Install\\Status\\ErrorStatus');
				$errorStatus->setTitle('Error dropping table');
				$errorStatus->setMessage('Sql statement failed with error message ' . $this->databaseConnection->sql_error());
				$result[] = $errorStatus;
			}
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

		// Insert admin user
		// Password is simple md5 here for now, will be updated by saltedpasswords on first login
		// @TODO: Handle saltedpasswords in installer and store password salted in the first place
		$adminUserFields = array(
			'username' => 'admin',
			'password' => md5($password),
			'admin' => 1,
			'tstamp' => $GLOBALS['EXEC_TIME'],
			'crdate' => $GLOBALS['EXEC_TIME']
		);
		$this->databaseConnection->exec_INSERTquery('be_users', $adminUserFields);

		// Set password as install tool password
		/** @var $configurationManager \TYPO3\CMS\Core\Configuration\ConfigurationManager */
		$configurationManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Configuration\\ConfigurationManager');
		$configurationManager->setLocalConfigurationValueByPath('BE/installToolPassword', md5($password));

		return $result;
	}

	/**
	 * Step needs to be executed if there are no tables in database
	 *
	 * @return boolean
	 */
	public function needsExecution() {
		$result = FALSE;
		$existingTables = $this->databaseConnection->admin_get_tables();
		if (count($existingTables) === 0) {
			$result = TRUE;
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
		$html[] = '<script type="text/javascript" src="../sysext/install/Resources/Public/Javascript/passwordStrength.js"></script>';

		$html[] = '<h3>Create user and import base data</h3>';
		$html[] = '<p>Import basic database structure and create a backend administrator user.';
		$html[] = ' The password can be used to log in to the install tool and to the TYPO3 CMS backend with username "admin".</p>';
		$html[] = '<p>The table import will drop possibly existing tables!</p>';
		$html[] = '<form method="post" action="StepInstaller.php">';
		$html[] = '<input type="hidden" value="databaseData" name="executeStep" />';

		$html[] = '<fieldset class="t3-install-form-label-width-7">';
		$html[] = '<ol>';

		$html[] = '<li>';
		$html[] = '<label for="password">Password</label>';
		$html[] = '<input class="t3-install-form-input-text" name="databaseData[password]" id="password" onkeyup="return passwordChanged();" type="password" />';
		$html[] = '</li>';

		$html[] = '<li>';
		$html[] = '<label for="show-password">Show password</label>';
		$html[] = '<input type="checkbox" id="show-password" onchange="if (this.checked==true) { document.getElementById(\'password\').type=\'text\'; } else { document.getElementById(\'password\').type=\'password\'; }">';
		$html[] = '</li>';

		// @TODO: Add sitename setting

		$html[] = '</ol>';
		$html[] = '</fieldset>';
		$html[] = '<button type="submit">';
		$html[] = 'Continue';
		$html[] = '<span class="t3-install-form-button-icon-positive">&nbsp;</span>';
		$html[] = '</button>';
		$html[] = '</form>';

		return implode(CR, $html);
	}
}
?>
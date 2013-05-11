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
 * Database connect step:
 * - Needs execution if database credentials are not set or fail to connect
 * - Renders fields for database connection fields
 * - Sets database credentials in LocalConfiguration
 * - Loads / unloads ext:dbal and ext:adodb if requested
 */
class DatabaseConnect implements StepInterface {

	/**
	 * Default constructor
	 */
	public function __construct() {
		\TYPO3\CMS\Core\Core\Bootstrap::getInstance()
			->startOutputBuffering()
			->loadConfigurationAndInitialize();
	}

	/**
	 * Execute database step:
	 * - Load / unload dbal & adodb
	 * - Set database connect credentials in LocalConfiguration
	 *
	 * @return array<\TYPO3\CMS\Install\Status\StatusInterface>
	 */
	public function execute() {
		$result = array();

		/** @var $configurationManager \TYPO3\CMS\Core\Configuration\ConfigurationManager */
		$configurationManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Configuration\\ConfigurationManager');

		if (isset($GLOBALS['_POST']['databaseConnect']['loadDbal'])) {
			$result[] = $this->executeLoadDbalExtension();
		} elseif (isset($GLOBALS['_POST']['databaseConnect']['unloadDbal'])) {
			$result[] = $this->executeUnloadDbalExtension();
		} elseif (isset($GLOBALS['_POST']['databaseConnect']['setDbalDriver'])) {
			$driver = $GLOBALS['_POST']['databaseConnect']['setDbalDriver'];
			switch ($driver) {
				case 'mssql':
				case 'odbc_mssql':
					$driverConfig = array(
						'useNameQuote' => TRUE,
						'quoteClob' => FALSE,
					);
					break;
				case 'oci8':
					$driverConfig = array(
						'driverOptions' => array(
							'connectSID' => '',
						),
					);
					break;
			}
			$config = array(
				'_DEFAULT' => array(
					'type' => 'adodb',
					'config' => array(
						'driver' => $driver,
					)
				)
			);
			if (isset($driverConfig)) {
				$config['_DEFAULT']['config'] = array_merge($config['_DEFAULT']['config'], $driverConfig);
			}
			$configurationManager->setLocalConfigurationValueByPath('EXTCONF/dbal/handlerCfg', $config);
			$this->reloadConfiguration();
		} else {
			$localConfigurationPathValuePairs = array();

			if ($this->isDbalEnabled()) {
				$config = $configurationManager->getConfigurationValueByPath('EXTCONF/dbal/handlerCfg');
				$driver = $config['_DEFAULT']['config']['driver'];
				if ($driver === 'oci8') {
					$configurationManager['_DEFAULT']['config']['driverOptions']['connectSID']
						= $GLOBALS['_POST']['databaseConnection']['type'] === 'sid' ? TRUE : FALSE;
				}
			}

			if (isset($GLOBALS['_POST']['databaseConnect']['username'])) {
				$value = $GLOBALS['_POST']['databaseConnect']['username'];
				if (strlen($value) <= 50) {
					$localConfigurationPathValuePairs['DB/username'] = $value;
				} else {
					/** @var $errorStatus \TYPO3\CMS\Install\Status\ErrorStatus */
					$errorStatus = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Install\\Status\\ErrorStatus');
					$errorStatus->setTitle('Database username not valid');
					$errorStatus->setMessage('Given username must be shorter than fifty characters.');
					$result[] = $errorStatus;
				}
			}

			if (isset($GLOBALS['_POST']['databaseConnect']['password'])) {
				$value = $GLOBALS['_POST']['databaseConnect']['password'];
				if (strlen($value) <= 50) {
					$localConfigurationPathValuePairs['DB/password'] = $value;
				} else {
					/** @var $errorStatus \TYPO3\CMS\Install\Status\ErrorStatus */
					$errorStatus = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Install\\Status\\ErrorStatus');
					$errorStatus->setTitle('Database password not valid');
					$errorStatus->setMessage('Given password must be shorter than fifty characters.');
					$result[] = $errorStatus;
				}
			}

			if (isset($GLOBALS['_POST']['databaseConnect']['host'])) {
				$value = $GLOBALS['_POST']['databaseConnect']['host'];
				if (preg_match('/^[a-zA-Z0-9_\\.-]+(:.+)?$/', $value) && strlen($value) <= 50) {
					$localConfigurationPathValuePairs['DB/host'] = $value;
				} else {
					/** @var $errorStatus \TYPO3\CMS\Install\Status\ErrorStatus */
					$errorStatus = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Install\\Status\\ErrorStatus');
					$errorStatus->setTitle('Database host not valid');
					$errorStatus->setMessage('Given host is not alphanumeric (a-z, A-Z, 0-9 or _-.:) or longer than fifty characters.');
					$result[] = $errorStatus;
				}
			}

			if (isset($GLOBALS['_POST']['databaseConnect']['port'])) {
				$value = $GLOBALS['_POST']['databaseConnect']['port'];
				if (preg_match('/^[0-9]+(:.+)?$/', $value) && $value > 0 && $value <= 65535) {
					$localConfigurationPathValuePairs['DB/port'] = (int)$value;
				} else {
					/** @var $errorStatus \TYPO3\CMS\Install\Status\ErrorStatus */
					$errorStatus = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Install\\Status\\ErrorStatus');
					$errorStatus->setTitle('Database port not valid');
					$errorStatus->setMessage('Given port is not numeric or within range 1 to 65535');
					$result[] = $errorStatus;
				}
			}

			if (isset($GLOBALS['_POST']['databaseConnect']['database'])) {
				$value = $GLOBALS['_POST']['databaseConnect']['database'];
				if (strlen($value) <= 50) {
					$localConfigurationPathValuePairs['DB/database'] = $value;
				} else {
					/** @var $errorStatus \TYPO3\CMS\Install\Status\ErrorStatus */
					$errorStatus = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Install\\Status\\ErrorStatus');
					$errorStatus->setTitle('Database name not valid');
					$errorStatus->setMessage('Given database name must be shorter than fifty characters.');
					$result[] = $errorStatus;
				}
			}

			if (!empty($localConfigurationPathValuePairs)) {
				$configurationManager->setLocalConfigurationValuesByPathValuePairs($localConfigurationPathValuePairs);
				$this->reloadConfiguration();
			}

			// After setting new credentials, test again and create an error message if connect is not successful
			if (!$this->isConnectSuccessful()) {
				/** @var $errorStatus \TYPO3\CMS\Install\Status\ErrorStatus */
				$errorStatus = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Install\\Status\\ErrorStatus');
				$errorStatus->setTitle('Database connect not successful');
				$errorStatus->setMessage('Connecting the database with given settings failed. Please check.');
				$result[] = $errorStatus;
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
		if ($this->isConnectSuccessful()) {
			return FALSE;
		} else {
			return TRUE;
		}
	}

	/**
	 * Render this step
	 *
	 * @return string
	 */
	public function render() {
		$this->reloadConfiguration();

		$html = array();

		$html[] = '<h3>Connect to your database host</h3>';
		$html[] = '<p>If you have not already created a username and password to access the database, please do so now. This can be done using tools provided by your host.</p>';

		if ($this->isDbalEnabled()) {
			$html[] = $this->renderDbalDriverSelection();
			if ($this->getSelectedDbalDriver()) {
				$html[] = $this->renderConnectDetailsHeader();
				$html[] = $this->renderConnectDetailFieldsForSpecificDbalDriver();
				$html[] = $this->renderConnectDetailsFooter();
			}
			$html[] = $this->renderUnloadDbal();
		} else {
			$html[] = $this->renderConnectDetailsHeader();
			$html[] = $this->renderConnectDetailsUsername();
			$html[] = $this->renderConnectDetailsPassword();
			$html[] = $this->renderConnectDetailsHost();
			$html[] = $this->renderConnectDetailsPort();
			$html[] = $this->renderConnectDetailsFooter();
			$html[] = $this->renderLoadDbal();
		}

		return implode(CR, $html);
	}

	/**
	 * Form header of connect detail information
	 *
	 * @return string
	 */
	protected function renderConnectDetailsHeader() {
		$html = array();
		$html[] = '<form method="post" action="StepInstaller.php">';
		$html[] = '<fieldset class="t3-install-form-label-width-7">';
		$html[] = '<ol>';
		return implode(LF, $html);
	}

	/**
	 * Footer of connect details information
	 *
	 * @return string
	 */
	protected function renderConnectDetailsFooter() {
		$html = array();
		$html[] = '</ol>';
		$html[] = '</fieldset>';
		$html[] = '<input type="hidden" value="databaseConnect" name="executeStep" />';
		$html[] = '<button type="submit">';
		$html[] = 'Continue';
		$html[] = '<span class="t3-install-form-button-icon-positive">&nbsp;</span>';
		$html[] = '</button>';
		$html[] = '</form>';
		$html[] = '<hr />';
		return implode(LF, $html);
	}

	/**
	 * Render fields required for successful connect based on dbal driver selection
	 *
	 * @return string
	 */
	protected function renderConnectDetailFieldsForSpecificDbalDriver() {
		$html = array();
		$driver = $this->getSelectedDbalDriver();
		switch($driver) {
			case 'mssql':
			case 'odbc_mssql':
			case 'postgres':
				$html[] = $this->renderConnectDetailsUsername();
				$html[] = $this->renderConnectDetailsPassword();
				$html[] = $this->renderConnectDetailsHost();
				$html[] = $this->renderConnectDetailsPort();
				$html[] = $this->renderConnectDetailsDatabase();
				break;
			case 'oci8':
				$html[] = $this->renderConnectDetailsUsername();
				$html[] = $this->renderConnectDetailsPassword();
				$html[] = $this->renderConnectDetailsHost();
				$html[] = $this->renderConnectDetailsPort();
				$html[] = $this->renderConnectDetailsDatabase();
				$html[] = $this->renderConnectDetailsOracleSidConnect();
				break;
		}
		return implode(LF, $html);
	}

	/**
	 * Render connect username and label
	 *
	 * @return string
	 */
	protected function renderConnectDetailsUsername() {
		$html = array();
		$html[] = '<li>';
		$html[] = '<label>Username</label>';
		$username = isset($GLOBALS['TYPO3_CONF_VARS']['DB']['username']) ? htmlspecialchars($GLOBALS['TYPO3_CONF_VARS']['DB']['username']) : '';
		$html[] = '<input class="t3-install-form-input-text" type="text" value="' . $username . '" name="databaseConnect[username]">';
		$html[] = '</li>';
		return implode(LF, $html);
	}

	/**
	 * Render connect password and label
	 *
	 * @return string
	 */
	protected function renderConnectDetailsPassword() {
		$html = array();
		$html[] = '<li>';
		$html[] = '<label>Password</label>';
		$password = isset($GLOBALS['TYPO3_CONF_VARS']['DB']['password']) ? htmlspecialchars($GLOBALS['TYPO3_CONF_VARS']['DB']['password']) : '';
		$html[] = '<input class="t3-install-form-input-text" type="password" value="' . $password . '" name="databaseConnect[password]">';
		$html[] = '</li>';
		return implode(LF, $html);
	}

	/**
	 * Render connect host and label
	 *
	 * @return string
	 */
	protected function renderConnectDetailsHost() {
		$html = array();
		$html[] = '<li>';
		$html[] = '<label>Host</label>';
		$host = $this->getConfiguredHost() ? htmlspecialchars($this->getConfiguredHost()): '127.0.0.1';
		$html[] = '<input class="t3-install-form-input-text" type="text" value="' . $host . '" name="databaseConnect[host]">';
		$html[] = '</li>';
		return implode(LF, $html);
	}

	/**
	 * Render connect port and label
	 *
	 * @return string
	 */
	protected function renderConnectDetailsPort() {
		$html = array();
		$html[] = '<li>';
		$html[] = '<label>Port</label>';
		$configuredPort = $this->getConfiguredPort();
		if (!$configuredPort) {
			if ($this->isDbalEnabled()) {
				$driver = $this->getSelectedDbalDriver();
				switch ($driver) {
					case 'postgres':
						$port = 5432;
						break;
					case 'mssql':
					case 'odbc_mssql':
						$port = 1433;
						break;
					case 'oci8':
						$port = 1521;
						break;
					default:
						$port = 3306;
				}
			} else {
				$port = 3306;
			}
		} else {
			$port = $configuredPort;
		}
		$port = htmlspecialchars($port);
		$html[] = '<input class="t3-install-form-input-text" type="text" value="' . $port . '" name="databaseConnect[port]">';
		$html[] = '</li>';
		return implode(LF, $html);
	}

	/**
	 * Render connect database and label
	 *
	 * @return string
	 */
	protected function renderConnectDetailsDatabase() {
		$html = array();
		$html[] = '<li>';
		$html[] = '<label>Database</label>';
		$database = isset($GLOBALS['TYPO3_CONF_VARS']['DB']['database']) ? $GLOBALS['TYPO3_CONF_VARS']['DB']['database'] : '';
		$html[] = '<input class="t3-install-form-input-text" type="text" value="' . $database . '" name="databaseConnect[database]">';
		$html[] = '</li>';
		return implode(LF, $html);
	}

	/**
	 * Render connect database oracle SID option (dbal with oci8)
	 *
	 * @return string
	 */
	protected function renderConnectDetailsOracleSidConnect() {
		$html = array();
		$html[] = '<li>';
		$html[] = '<label>Database</label>';
		$type = isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['dbal']['handlerCfg']['_DEFAULT']['config']['driverOptions']['connectSID'])
			? $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['dbal']['handlerCfg']['_DEFAULT']['config']['driverOptions']['connectSID']
			: '';
		$html[] = '<select id="t3-install-form-type" name="databaseConnection[type]">';
		$sidSelected = $type === TRUE ? ' selected="selected"' : '';
		$html[] = '<option value="servicename">Service name</option>';
		$html[] = '<option' . $sidSelected . ' value="sid">SID</option>';
		$html[] = '</select>';
		$html[] = '</li>';
		return implode(LF, $html);
	}

	/**
	 * Render load dbal button
	 *
	 * @return string
	 */
	protected function renderLoadDbal() {
		$html = array();
		$html[] = 'TYPO3 CMS native database implementation is based on mysql. A database abstraction layer' .
			' allows to run TYPO3 CMS on different database engines like postgres. This is used rather seldom' .
			' and some core parts and extensions do not fully support this. Your TYPO3 CMS experience might suffer' .
			' if you chose to install the system on anything different than mysql.';
		$html[] = '<form method="post" action="StepInstaller.php">';
		$html[] = '<input type="hidden" value="databaseConnect" name="executeStep" />';
		$html[] = '<input type="hidden" value="1" name="databaseConnect[loadDbal]" />';
		$html[] = '<button type="submit">';
		$html[] = 'I do not use mysql';
		$html[] = '<span class="t3-install-form-button-icon-negative">&nbsp;</span>';
		$html[] = '</button>';
		$html[] = '</form>';
		return implode(LF, $html);
	}

	/**
	 * Render unload dbal button
	 *
	 * @return string
	 */
	protected function renderUnloadDbal() {
		$html = array();
		$html[] = '<form method="post" action="StepInstaller.php">';
		$html[] = '<input type="hidden" value="databaseConnect" name="executeStep" />';
		$html[] = '<input type="hidden" value="1" name="databaseConnect[unloadDbal]" />';
		$html[] = '<button type="submit">';
		$html[] = 'I use native mysql';
		$html[] = '<span class="t3-install-form-button-icon-positive">&nbsp;</span>';
		$html[] = '</button>';
		$html[] = '</form>';
		return implode(LF, $html);
	}

	/**
	 * Test connection with given credentials
	 *
	 * @return boolean TRUE if connect was successful
	 */
	protected function isConnectSuccessful() {
		/** @var $databaseConnection \TYPO3\CMS\Core\Database\DatabaseConnection */
		$databaseConnection = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Database\\DatabaseConnection');

		if ($this->isDbalEnabled()) {
			if (isset($GLOBALS['TYPO3_CONF_VARS']['DB']['database'])) {
				$databaseConnection->setDatabaseName($GLOBALS['TYPO3_CONF_VARS']['DB']['database']);
			}
		}

		$username = isset($GLOBALS['TYPO3_CONF_VARS']['DB']['username']) ? $GLOBALS['TYPO3_CONF_VARS']['DB']['username'] : '';
		$databaseConnection->setDatabaseUsername($username);
		$password = isset($GLOBALS['TYPO3_CONF_VARS']['DB']['password']) ? $GLOBALS['TYPO3_CONF_VARS']['DB']['password'] : '';
		$databaseConnection->setDatabasePassword($password);
		$databaseConnection->setDatabaseHost($this->getConfiguredHost());
		$databaseConnection->setDatabasePort($this->getConfiguredPort());

		if ($this->isDbalEnabled()) {
			// Set additional connect information based on dbal driver
			$databaseName = isset($GLOBALS['TYPO3_CONF_VARS']['DB']['database']) ? $GLOBALS['TYPO3_CONF_VARS']['DB']['database'] : '';
			$databaseConnection->setDatabaseName($databaseName);
		}

		$result = FALSE;
		if (@$databaseConnection->sql_pconnect()) {
			$result = TRUE;
		}
		return $result;
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
	 * Render dbal driver select drop down, called if dbal is installed.
	 *
	 * @return string
	 */
	protected function renderDbalDriverSelection() {
		$html = array();
		$html[] = '<form method="post" action="StepInstaller.php">';
		$html[] = '<input type="hidden" value="databaseConnect" name="executeStep" />';
		$html[] = '<fieldset class="t3-install-form-label-width-7">';
		$html[] = '<ol>';
		$html[] = '<li>';
		$html[] = '<label>Driver</label>';
		$html[] = '<select name="databaseConnect[setDbalDriver]" onChange="this.form.submit();">';
		$html[] = $this->renderDbalDriverSelectOptions();
		$html[] = '</select>';
		$html[] = '</li>';
		$html[] = '</ol>';
		$html[] = '</fieldset>';
		$html[] = '</form>';
		$html[] = '<hr />';
		return implode(LF, $html);
	}

	/**
	 * Render dbal driver select options
	 *
	 * @return string
	 */
	protected function renderDbalDriverSelectOptions() {
		$html = array();
		$availableDrivers = $this->getAvailableDbalDrivers();
		$selectedDriver = $this->getSelectedDbalDriver();
		if ($selectedDriver === '') {
			$html[] = '<option></option>';
		}
		foreach($availableDrivers as $abstractionLayer => $drivers) {
			$html[] = '<optgroup label="' . $abstractionLayer . '">';
			foreach ($drivers as $driver => $label) {
				$selected = $driver === $selectedDriver ? ' selected="selected' : '';
				$html[] = '<option' . $selected . ' value="' . $driver . '">';
				$html[] = $label;
				$html[] = '</option>';
			}
			$html[] = '</optgroup>';
		}
		return implode(LF, $html);
	}

	/**
	 * Returns a list of database drivers that are available on current server.
	 *
	 * @return array
	 */
	protected function getAvailableDbalDrivers() {
		$supportedDrivers = $this->getSupportedDbalDrivers();
		$availableDrivers = array();
		foreach ($supportedDrivers as $abstractionLayer => $drivers) {
			foreach ($drivers as $driver => $info) {
				if (isset($info['combine']) && $info['combine'] === 'OR') {
					$isAvailable = FALSE;
				} else {
					$isAvailable = TRUE;
				}
				// Loop through each PHP module dependency to ensure it is loaded
				foreach ($info['extensions'] as $extension) {
					if (isset($info['combine']) && $info['combine'] === 'OR') {
						$isAvailable |= extension_loaded($extension);
					} else {
						$isAvailable &= extension_loaded($extension);
					}
				}
				if ($isAvailable) {
					if (!isset($availableDrivers[$abstractionLayer])) {
						$availableDrivers[$abstractionLayer] = array();
					}
					$availableDrivers[$abstractionLayer][$driver] = $info['label'];
				}
			}
		}
		return $availableDrivers;
	}

	/**
	 * Returns a list of DBAL supported database drivers, with a
	 * user-friendly name and any PHP module dependency.
	 *
	 * @return array
	 */
	protected function getSupportedDbalDrivers() {
		$supportedDrivers = array(
			'Native' => array(
				'mssql' => array(
					'label' => 'Microsoft SQL Server',
					'extensions' => array('mssql')
				),
				'oci8' => array(
					'label' => 'Oracle OCI8',
					'extensions' => array('oci8')
				),
				'postgres' => array(
					'label' => 'PostgreSQL',
					'extensions' => array('pgsql')
				)
			),
			'ODBC' => array(
				'odbc_mssql' => array(
					'label' => 'Microsoft SQL Server',
					'extensions' => array('odbc', 'mssql')
				)
			)
		);
		return $supportedDrivers;
	}

	/**
	 * Get selected dbal driver if any
	 *
	 * @return string Dbal driver or empty string if not yet selected
	 */
	protected function getSelectedDbalDriver() {
		if (isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['dbal']['handlerCfg']['_DEFAULT']['config']['driver'])) {
			return $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['dbal']['handlerCfg']['_DEFAULT']['config']['driver'];
		}
		return '';
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

		if ($this->isDbalEnabled()) {
			require(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('dbal') . 'ext_localconf.php');
			$GLOBALS['typo3CacheManager']->setCacheConfigurations($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']);
		}
	}

	/**
	 * Adds dbal and adodb to list of loaded extensions
	 *
	 * @return \TYPO3\CMS\Install\Status\StatusInterface
	 */
	protected function executeLoadDbalExtension() {
		if (!\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('adodb')) {
			\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::loadExtension('adodb');
		}
		if (!\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('dbal')) {
			\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::loadExtension('dbal');
		}
		/** @var $errorStatus \TYPO3\CMS\Install\Status\ErrorStatus */
		$okStatus = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Install\\Status\\WarningStatus');
		$okStatus->setTitle('Loaded database abstraction layer');
		return $okStatus;
	}

	/**
	 * Remove dbal and adodb from list of loaded extensions
	 *
	 * @return \TYPO3\CMS\Install\Status\StatusInterface
	 */
	protected function executeUnloadDbalExtension() {
		if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('adodb')) {
			\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::unloadExtension('adodb');
		}
		if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('dbal')) {
			\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::unloadExtension('dbal');
		}
		/** @var $errorStatus \TYPO3\CMS\Install\Status\ErrorStatus */
		$okStatus = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Install\\Status\\WarningStatus');
		$okStatus->setTitle('Removed database abstraction layer');
		return $okStatus;
	}

	/**
	 * Returns configured host with port split off if given
	 *
	 * @return string
	 */
	protected function getConfiguredHost() {
		$host = isset($GLOBALS['TYPO3_CONF_VARS']['DB']['host']) ? $GLOBALS['TYPO3_CONF_VARS']['DB']['host'] : '';
		$port = isset($GLOBALS['TYPO3_CONF_VARS']['DB']['port']) ? $GLOBALS['TYPO3_CONF_VARS']['DB']['port'] : '';
		if (strlen($port) < 1 && strpos($host, ':') > 0) {
			list($host) = explode(':', $host);
		}
		return $host;
	}

	/**
	 * Returns configured port. Gets port from host value if port is not yet set.
	 *
	 * @return integer
	 */
	protected function getConfiguredPort() {
		$host = isset($GLOBALS['TYPO3_CONF_VARS']['DB']['host']) ? $GLOBALS['TYPO3_CONF_VARS']['DB']['host'] : '';
		$port = isset($GLOBALS['TYPO3_CONF_VARS']['DB']['port']) ? $GLOBALS['TYPO3_CONF_VARS']['DB']['port'] : '';
		if (!strlen($port) > 0 && strpos($host, ':') > 0) {
			$hostPortArray = explode(':', $host);
			$port = $hostPortArray[1];
		}
		return (int)$port;
	}
}
?>
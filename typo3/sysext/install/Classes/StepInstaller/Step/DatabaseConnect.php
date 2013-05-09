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
 * - Loads ext:dbal and ext:adodb if requested
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
	 * - Set database connect credentials in LocalConfiguration
	 *
	 * @return array<\TYPO3\CMS\Install\Status\StatusInterface>
	 */
	public function execute() {
		$result = array();
		$localConfigurationPathValuePairs = array();

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

		if (!empty($localConfigurationPathValuePairs)) {
			$configurationManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Configuration\\ConfigurationManager');
			$configurationManager->setLocalConfigurationValuesByPathValuePairs($localConfigurationPathValuePairs);

			// After setting new credentials, we test again and create an error message if connect is not successful
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
	 * Step needs to be executed if LocalConfiguration file does not exist.
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
		$html = array();
		$html[] = '<h3>Connect to your database host</h3>';
		$html[] = '<p>If you have not already created a username and password to access the database, please do so now. This can be done using tools provided by your host.</p>';

		$html[] = '<form method="post" action="StepInstaller.php">';
		$html[] = '<fieldset class="t3-install-form-label-width-7">';
		$html[] = '<ol>';

		$html[] = '<li>';
		$html[] = '<label for="t3-install-form-username">Username</label>';
		$username = isset($GLOBALS['TYPO3_CONF_VARS']['DB']['username']) ? htmlspecialchars($GLOBALS['TYPO3_CONF_VARS']['DB']['username']) : '';
		$html[] = '<input id="t3-install-form-username" class="t3-install-form-input-text" type="text" value="' . $username . '" name="databaseConnect[username]">';
		$html[] = '</li>';

		$html[] = '<li>';
		$html[] = '<label for="t3-install-form-password">Password</label>';
		$password = isset($GLOBALS['TYPO3_CONF_VARS']['DB']['username']) ? htmlspecialchars($GLOBALS['TYPO3_CONF_VARS']['DB']['password']) : '';
		$html[] = '<input id="t3-install-form-password" class="t3-install-form-input-text" type="password" value="' . $password . '" name="databaseConnect[password]">';
		$html[] = '</li>';

		$html[] = '<li>';
		$html[] = '<label for="t3-install-form-host">Host</label>';
		$host = $this->getConfiguredHost() ? htmlspecialchars($this->getConfiguredHost()): '127.0.0.1';
		$html[] = '<input id="t3-install-form-host" class="t3-install-form-input-text" type="text" value="' . $host . '" name="databaseConnect[host]">';
		$html[] = '</li>';

		$html[] = '<li>';
		$html[] = '<label for="t3-install-form-port">Port</label>';
		$port = $this->getConfiguredPort() ? htmlspecialchars($this->getConfiguredPort()): 3306;
		$html[] = '<input id="t3-install-form-port" class="t3-install-form-input-text" type="text" value="' . $port . '" name="databaseConnect[port]">';
		$html[] = '</li>';

		$html[] = '</ol>';
		$html[] = '</fieldset>';

		$html[] = '<input type="hidden" value="databaseConnect" name="executeStep" />';
		$html[] = '<button type="submit">';
		$html[] = 'Continue';
		$html[] = '<span class="t3-install-form-button-icon-positive">&nbsp;</span>';
		$html[] = '</button>';

		$html[] = '</form>';

		return implode(CR, $html);
	}

	/**
	 * Test connection with given credentials
	 *
	 * @return boolean TRUE if connect was successful
	 */
	protected function isConnectSuccessful() {
		// Load LocalConfiguration / AdditionalConfiguration again to force fresh values
		// in TYPO3_CONF_VARS in case they were written in execute()
		/** @var $configurationManager \TYPO3\CMS\Core\Configuration\ConfigurationManager */
		$configurationManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Configuration\\ConfigurationManager');
		$configurationManager->exportConfiguration();

		/** @var $databaseConnection \TYPO3\CMS\Core\Database\DatabaseConnection */
		$databaseConnection = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Database\\DatabaseConnection');

		$username = isset($GLOBALS['TYPO3_CONF_VARS']['DB']['username']) ? $GLOBALS['TYPO3_CONF_VARS']['DB']['username'] : '';
		$databaseConnection->setDatabaseUsername($username);
		$password = isset($GLOBALS['TYPO3_CONF_VARS']['DB']['password']) ? $GLOBALS['TYPO3_CONF_VARS']['DB']['password'] : '';
		$databaseConnection->setDatabasePassword($password);
		$databaseConnection->setDatabaseHost($this->getConfiguredHost());
		$databaseConnection->setDatabasePort($this->getConfiguredPort());

		$result = FALSE;
		if (@$databaseConnection->sql_pconnect()) {
			$result = TRUE;
		}
		return $result;
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
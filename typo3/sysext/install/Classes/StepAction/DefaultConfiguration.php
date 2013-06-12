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
 * Set production defaults
 */
class DefaultConfiguration extends AbstractStep implements StepInterface {

	/**
	 * @var \TYPO3\CMS\Core\Database\DatabaseConnection
	 */
	protected $databaseConnection = NULL;

	/**
	 * Default constructor
	 */
	public function __construct() {
		$this->reloadConfiguration();

		// @TODO: See, if we need db in this step at all ...
		$this->databaseConnection = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Database\\DatabaseConnection');
		$this->databaseConnection->setDatabaseUsername($GLOBALS['TYPO3_CONF_VARS']['DB']['username']);
		$this->databaseConnection->setDatabasePassword($GLOBALS['TYPO3_CONF_VARS']['DB']['password']);
		$this->databaseConnection->setDatabaseHost($GLOBALS['TYPO3_CONF_VARS']['DB']['host']);
		$this->databaseConnection->setDatabasePort($GLOBALS['TYPO3_CONF_VARS']['DB']['port']);
		$this->databaseConnection->setDatabaseName($GLOBALS['TYPO3_CONF_VARS']['DB']['database']);
		$this->databaseConnection->connectDB();
	}

	/**
	 * Set defaults of auto configuration, mark installation as completed
	 *
	 * @return array<\TYPO3\CMS\Install\Status\StatusInterface>
	 */
	public function execute() {
		$result = array();

		/** @var $configurationManager \TYPO3\CMS\Core\Configuration\ConfigurationManager */
		$configurationManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Configuration\\ConfigurationManager');
		$configurationManager->setLocalConfigurationValueByPath('SYS/isInitialInstallationInProgress', FALSE);

		// @TODO: remove enable_install_tool file, destroy install tool session

		\TYPO3\CMS\Core\Utility\HttpUtility::redirect('../index.php', \TYPO3\CMS\Core\Utility\HttpUtility::HTTP_STATUS_307);
	}

	/**
	 * Step needs to be executed if 'isInitialInstallationInProgress' is set to TRUE in LocalConfiguration
	 *
	 * @return boolean
	 */
	public function needsExecution() {
		$result = FALSE;
		if (isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['isInitialInstallationInProgress'])
			&& $GLOBALS['TYPO3_CONF_VARS']['SYS']['isInitialInstallationInProgress'] === TRUE
		) {
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
		$html = array();

		$html[] = '<h3>Auto configuration and login</h3>';
		$html[] = '<p>Installation done! This last step will set some configuration values based on your';
		$html[] = ' system environment and redirects to the TYPO3 CMS backend ready for you to log in';
		$html[] = ' with user "admin" and your previously set password.</p>';

		$html[] = '<form method="post" action="StepInstaller.php">';
		$html[] = '<input type="hidden" value="defaultConfiguration" name="executeStep" />';

		$html[] = '<button type="submit">';
		$html[] = 'Continue';
		$html[] = '<span class="t3-install-form-button-icon-positive">&nbsp;</span>';
		$html[] = '</button>';
		$html[] = '</form>';

		return implode(CR, $html);
	}
}
?>
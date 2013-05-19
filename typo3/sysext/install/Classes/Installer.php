<?php
namespace TYPO3\CMS\Install;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 1999-2013 Kasper Skårhøj (kasperYYYY@typo3.com)
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
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
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
 * Install Tool module
 *
 * @author 	Kasper Skårhøj <kasperYYYY@typo3.com>
 * @author 	Ingmar Schlecht <ingmar@typo3.org>
 */
class Installer {

	/**
	 * @var string Path to templates
	 */
	protected $templateFilePath = 'typo3/sysext/install/Resources/Private/Templates/';

	/**
	 * @var string Main template
	 */
	protected $template;

	/**
	 * @var array Used to set (error)messages from the executing functions like mail-sending, writing Localconf and such
	 */
	protected $messages = array();

	/**
	 * @var array List of error messages
	 */
	protected $errorMessages = array();

	/**
	 * @var string The url that calls this script
	 */
	protected $action = '';

	/**
	 * @var string The url that calls this script
	 */
	protected $scriptSelf = 'index.php';

	/**
	 * @var array In constructor: is set to global GET/POST var TYPO3_INSTALL
	 */
	protected $INSTALL = array();

	/**
	 * @var boolean This is set, if the password check was ok. The function init() will exit if this is not set
	 */
	protected $passwordOK = 0;

	/**
	 * @var array Used to gather the message information.
	 */
	protected $sections = array();

	/**
	 * @var boolean This is set if some error occured that will definitely prevent TYpo3 from running.
	 */
	protected $fatalError = 0;

	/**
	 * @var \TYPO3\CMS\Install\Session Session handling object
	 */
	protected $session = NULL;

	/**
	 * @var array List of menu items
	 */
	protected $menuitems = array(
		'config' => 'Basic Configuration',
		'systemEnvironment' => 'System environment',
		'folderStructure' => 'Folder structure',
		'database' => 'Database Analyser',
		'update' => 'Upgrade Wizard',
		'images' => 'Image Processing',
		'extConfig' => 'All Configuration',
		'cleanup' => 'Clean up',
		'about' => 'About',
		'logout' => 'Logout from Install Tool'
	);

	/**
	 * Constructor
	 */
	public function __construct() {
		if (!$GLOBALS['TYPO3_CONF_VARS']['BE']['installToolPassword']) {
			$this->outputErrorAndExit('Install Tool deactivated.<br />
				You must enable it by setting a password in typo3conf/LocalConfiguration.php. If you insert the value below at array position \'BE\' \'installToolPassword\', the password will be \'joh316\':<br /><br />
				\'bacb98acf97e0b6112b1d1b650b84971\'', 'Fatal error');
		}

		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header('Expires: 0');
		header('Cache-Control: no-cache, must-revalidate');
		header('Pragma: no-cache');

		// ****************************
		// Initializing incoming vars.
		// ****************************
		$this->INSTALL = GeneralUtility::_GP('TYPO3_INSTALL');

		$this->redirect_url = GeneralUtility::sanitizeLocalUrl(GeneralUtility::_GP('redirect_url'));
		$this->INSTALL['type'] = '';
		if ($_GET['TYPO3_INSTALL']['type']) {
			$allowedTypes = array(
				'config',
				'database',
				'update',
				'images',
				'extConfig',
				'cleanup',
				'systemEnvironment',
				'folderStructure',
				'typo3conf_edit',
				'about',
				'logout'
			);
			if (in_array($_GET['TYPO3_INSTALL']['type'], $allowedTypes)) {
				$this->INSTALL['type'] = $_GET['TYPO3_INSTALL']['type'];
			}
		}
		if (!$this->INSTALL['type'] || !isset($this->menuitems[$this->INSTALL['type']])) {
			$this->INSTALL['type'] = 'about';
		}
		$this->action = $this->scriptSelf . '?TYPO3_INSTALL[type]=' . $this->INSTALL['type'];
		try {
			$this->session = GeneralUtility::makeInstance('tx_install_session');
		} catch (\Exception $exception) {
			$this->outputErrorAndExit($exception->getMessage());
		}
		// *******************
		// Check authorization
		// *******************
		if (!$this->session->hasSession()) {
			$this->session->startSession();
		}
		if ($this->session->isAuthorized() || $this->checkPassword()) {
			$this->passwordOK = 1;
			$this->session->refreshSession();
			$enableInstallToolFile = PATH_typo3conf . 'ENABLE_INSTALL_TOOL';
			if (is_file($enableInstallToolFile)) {
				// Extend the age of the ENABLE_INSTALL_TOOL file by one hour
				@touch($enableInstallToolFile);
			}
			if ($this->redirect_url) {
				\TYPO3\CMS\Core\Utility\HttpUtility::redirect($this->redirect_url);
			}

			/** @var $formProtection \TYPO3\CMS\Core\FormProtection\InstallToolFormProtection */
			$formProtection = \TYPO3\CMS\Core\FormProtection\FormProtectionFactory::get(
				'TYPO3\\CMS\\Core\\FormProtection\\InstallToolFormProtection'
			);
			$formProtection->injectInstallTool($this);

		} else {
			$this->loginForm();
		}
	}

	/**
	 * Returns TRUE if submitted password is ok.
	 *
	 * If password is ok, set session as "authorized".
	 *
	 * @return boolean TRUE if the submitted password was ok and session was
	 */
	protected function checkPassword() {
		$p = GeneralUtility::_GP('password');
		if ($p && md5($p) === $GLOBALS['TYPO3_CONF_VARS']['BE']['installToolPassword']) {
			$this->session->setAuthorized();
			// Sending warning email
			$wEmail = $GLOBALS['TYPO3_CONF_VARS']['BE']['warning_email_addr'];
			if ($wEmail) {
				$subject = 'Install Tool Login at "' . $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] . '"';
				$email_body = 'There has been an Install Tool login at TYPO3 site "' . $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] . '" (' . GeneralUtility::getIndpEnv('HTTP_HOST') . ') from remote address "' . GeneralUtility::getIndpEnv('REMOTE_ADDR') . '" (' . GeneralUtility::getIndpEnv('REMOTE_HOST') . ')';
				mail($wEmail, $subject, $email_body, 'From: TYPO3 Install Tool WARNING <>');
			}
			return TRUE;
		} else {
			// Bad password, send warning:
			if ($p) {
				$wEmail = $GLOBALS['TYPO3_CONF_VARS']['BE']['warning_email_addr'];
				if ($wEmail) {
					$subject = 'Install Tool Login ATTEMPT at \'' . $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] . '\'';
					$email_body = 'There has been an Install Tool login attempt at TYPO3 site \'' . $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] . '\' (' . GeneralUtility::getIndpEnv('HTTP_HOST') . ').
The MD5 hash of the last 5 characters of the password tried was \'' . substr(md5($p), -5) . '\'
REMOTE_ADDR was \'' . GeneralUtility::getIndpEnv('REMOTE_ADDR') . '\' (' . GeneralUtility::getIndpEnv('REMOTE_HOST') . ')';
					mail($wEmail, $subject, $email_body, 'From: TYPO3 Install Tool WARNING <>');
				}
			}
			return FALSE;
		}
	}

	/**
	 * Create the HTML for the login form
	 *
	 * Reads and fills the template.
	 * Substitutes subparts when wrong password has been given
	 * or the session has expired
	 *
	 * @return void
	 */
	protected function loginForm() {
		$password = GeneralUtility::_GP('password');
		$redirect_url = $this->redirect_url ? $this->redirect_url : $this->action;
		// Get the template file
		$templateFile = @file_get_contents((PATH_site . $this->templateFilePath . 'LoginForm.html'));
		// Get the template part from the file
		$template = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($templateFile, '###TEMPLATE###');
		// Password has been given, but this form is rendered again.
		// This means the given password was wrong
		$wrongPasswordSubPart = '';
		if (!empty($password)) {
			// Get the subpart for the wrong password
			$wrongPasswordSubPart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($template, '###WRONGPASSWORD###');
			// Define the markers content
			$wrongPasswordMarkers = array(
				'passwordMessage' => 'The password you just tried has this md5-value:',
				'password' => md5($password)
			);
			// Fill the markers in the subpart
			$wrongPasswordSubPart = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($wrongPasswordSubPart, $wrongPasswordMarkers, '###|###', TRUE, TRUE);
		}
		// Session has expired
		$sessionExpiredSubPart = '';
		if (!$this->session->isAuthorized() && $this->session->isExpired()) {
			// Get the subpart for the expired session message
			$sessionExpiredSubPart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($template, '###SESSIONEXPIRED###');
			// Define the markers content
			$sessionExpiredMarkers = array(
				'message' => 'Your Install Tool session has expired'
			);
			// Fill the markers in the subpart
			$sessionExpiredSubPart = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($sessionExpiredSubPart, $sessionExpiredMarkers, '###|###', TRUE, TRUE);
		}
		// Substitute the subpart for the expired session in the template
		$template = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($template, '###SESSIONEXPIRED###', $sessionExpiredSubPart);
		// Substitute the subpart for the wrong password in the template
		$template = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($template, '###WRONGPASSWORD###', $wrongPasswordSubPart);
		// Define the markers content
		$markers = array(
			'siteName' => 'Site: ' . htmlspecialchars($GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename']),
			'headTitle' => 'Login to TYPO3 ' . TYPO3_version . ' Install Tool',
			'redirectUrl' => htmlspecialchars($redirect_url),
			'enterPassword' => 'Password',
			'login' => 'Login',
			'message' => '
				<p class="typo3-message message-information">
					The Install Tool Password is <em>not</em> the admin password
					of TYPO3.
					<br />
					The default password is <em>joh316</em>. Be sure to change it!
					<br /><br />
					If you don\'t know the current password, you can set a new
					one by setting the value of
					$TYPO3_CONF_VARS[\'BE\'][\'installToolPassword\'] in
					typo3conf/LocalConfiguration.php to the md5() hash value of the
					password you desire.
				</p>
			'
		);
		// Fill the markers in the template
		$content = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($template, $markers, '###|###', TRUE, TRUE);
		// Send content to the page wrapper function
		$this->output($this->outputWrapper($content));
	}

	/**
	 * Calling function that checks system, IM, GD, dirs, database
	 * and lets you alter localconf.php
	 *
	 * This method is called from init.php to start the Install Tool.
	 *
	 * @return void
	 */
	public function init() {
		// Must be called after inclusion of init.php (or from init.php)
		if (!defined('PATH_typo3')) {
			die;
		}
		if (!$this->passwordOK) {
			die;
		}

		switch ($this->INSTALL['type']) {
		case 'images':
			/** @var $actionObject \TYPO3\CMS\Install\Action\AbstractAction */
			$actionObject = GeneralUtility::makeInstance('TYPO3\\CMS\\Install\\Action\\ImageProcessing');
			$actionObject->handle();
			$this->sections = array_merge($this->sections, $actionObject->getSections());
			$this->errorMessages = array_merge($this->errorMessages, $actionObject->getErrorMessages());
			$this->output($this->outputWrapper($this->printAll()));
			break;
		case 'database':
			/** @var $actionObject \TYPO3\CMS\Install\Action\AbstractAction */
			$actionObject = GeneralUtility::makeInstance('TYPO3\\CMS\\Install\\Action\\DatabaseAnalyzer');
			$actionObject->handle();
			$this->sections = array_merge($this->sections, $actionObject->getSections());
			$this->errorMessages = array_merge($this->errorMessages, $actionObject->getErrorMessages());
			$this->output($this->outputWrapper($this->printAll()));
			break;
		case 'update':
			$this->updateWizard();
			break;
		case 'config':
			/** @var $actionObject \TYPO3\CMS\Install\Action\AbstractAction */
			$actionObject = GeneralUtility::makeInstance('TYPO3\\CMS\\Install\\Action\\BasicConfiguration');
			$actionObject->handle();
			$this->sections = array_merge($this->sections, $actionObject->getSections());
			$this->errorMessages = array_merge($this->errorMessages, $actionObject->getErrorMessages());
			$this->output($this->outputWrapper($this->printAll()));
			break;
		case 'extConfig':
			/** @var $actionObject \TYPO3\CMS\Install\Action\AbstractAction */
			$actionObject = GeneralUtility::makeInstance('TYPO3\\CMS\\Install\\Action\\AllConfiguration');
			$content = $actionObject->handle();
			$this->output($this->outputWrapper($content));
			break;
		case 'cleanup':
			/** @var $actionObject \TYPO3\CMS\Install\Action\AbstractAction */
			$actionObject = GeneralUtility::makeInstance('TYPO3\\CMS\\Install\\Action\\CleanupManager');
			$actionObject->handle();
			$this->sections = array_merge($this->sections, $actionObject->getSections());
			$this->output($this->outputWrapper($this->printAll()));
			break;
		case 'systemEnvironment':
			/** @var $actionObject \TYPO3\CMS\Install\Action\AbstractAction */
			$actionObject = GeneralUtility::makeInstance('TYPO3\\CMS\\Install\\Action\\SystemEnvironment');
			$output = $actionObject->handle();
			$this->output($this->outputWrapper($output));
			break;
		case 'folderStructure':
			/** @var $actionObject \TYPO3\CMS\Install\Action\AbstractAction */
			$actionObject = GeneralUtility::makeInstance('TYPO3\\CMS\\Install\\Action\\FolderStructure');
			$output = $actionObject->handle();
			$this->output($this->outputWrapper($output));
			break;
		case 'logout':
			$enableInstallToolFile = PATH_site . 'typo3conf/ENABLE_INSTALL_TOOL';
			if (is_file($enableInstallToolFile) && trim(file_get_contents($enableInstallToolFile)) !== 'KEEP_FILE') {
				unlink(PATH_typo3conf . 'ENABLE_INSTALL_TOOL');
			}

			/** @var $formProtection \TYPO3\CMS\Core\FormProtection\InstallToolFormProtection */
			$formProtection = \TYPO3\CMS\Core\FormProtection\FormProtectionFactory::get(
				'TYPO3\\CMS\\Core\\FormProtection\\InstallToolFormProtection'
			);
			$formProtection->clean();

			$this->session->destroySession();
			\TYPO3\CMS\Core\Utility\HttpUtility::redirect($this->scriptSelf);
			break;
		default:
			/** @var $actionObject \TYPO3\CMS\Install\Action\AbstractAction */
			$actionObject = GeneralUtility::makeInstance('TYPO3\\CMS\\Install\\Action\\About');
			$actionObject->handle();
			$this->sections = array_merge($this->sections, $actionObject->getSections());
			$this->errorMessages = array_merge($this->errorMessages, $actionObject->getErrorMessages());
			$this->output($this->outputWrapper($this->printAll()));
			break;
		}
	}

	/**
	 * Generates update wizard
	 *
	 * @return void
	 */
	protected function updateWizard() {
		/** @var $sqlHandler \TYPO3\CMS\Install\Sql\SchemaMigrator */
		$sqlHandler = GeneralUtility::makeInstance('TYPO3\\CMS\\Install\\Sql\\SchemaMigrator');

		\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::removeCacheFiles();
		// Forces creation / update of caching framework tables that are needed by some update wizards
		$cacheTablesConfiguration = implode(LF, $sqlHandler->getStatementArray(\TYPO3\CMS\Core\Cache\Cache::getDatabaseTableDefinitions(), 1, '^CREATE TABLE '));
		$neededTableDefinition = $sqlHandler->getFieldDefinitions_fileContent($cacheTablesConfiguration);
		$currentTableDefinition = $sqlHandler->getFieldDefinitions_database();
		$updateTableDefenition = $sqlHandler->getDatabaseExtra($neededTableDefinition, $currentTableDefinition);
		$updateStatements = $sqlHandler->getUpdateSuggestions($updateTableDefenition);
		if (isset($updateStatements['create_table']) && count($updateStatements['create_table']) > 0) {
			$sqlHandler->performUpdateQueries($updateStatements['create_table'], $updateStatements['create_table']);
		}
		if (isset($updateStatements['add']) && count($updateStatements['add']) > 0) {
			$sqlHandler->performUpdateQueries($updateStatements['add'], $updateStatements['add']);
		}
		if (isset($updateStatements['change']) && count($updateStatements['change']) > 0) {
			$sqlHandler->performUpdateQueries($updateStatements['change'], $updateStatements['change']);
		}
		// call wizard
		$action = $this->INSTALL['database_type'] ? $this->INSTALL['database_type'] : 'checkForUpdate';
		$this->updateWizard_parts($action);
		$this->output($this->outputWrapper($this->printAll()));
	}

	/**
	 * Implements the steps for the update wizard
	 *
	 * @param string $action Which should be done.
	 * @return void
	 */
	protected function updateWizard_parts($action) {
		$content = '';
		$updateItems = array();
		// Get the template file
		$templateFile = @file_get_contents((PATH_site . $this->templateFilePath . 'UpdateWizardParts.html'));
		$title = '';
		switch ($action) {
		case 'checkForUpdate':
			// Get the subpart for check for update
			$checkForUpdateSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($templateFile, '###CHECKFORUPDATE###');
			$title = 'Step 1 - Introduction';
			if (!$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/install']['update']) {
				$updatesAvailableSubpart = '
						<p>
							<strong>
								No updates registered!
							</strong>
						</p>
					';
			} else {
				// step through list of updates, and check if update is needed and if yes, output an explanation
				$updatesAvailableSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($checkForUpdateSubpart, '###UPDATESAVAILABLE###');
				$updateWizardBoxesSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($updatesAvailableSubpart, '###UPDATEWIZARDBOXES###');
				$singleUpdateWizardBoxSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($updateWizardBoxesSubpart, '###SINGLEUPDATEWIZARDBOX###');
				$singleUpdate = array();
				foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/install']['update'] as $identifier => $className) {
					$tmpObj = $this->getUpgradeObjInstance($className, $identifier);
					if ($tmpObj->shouldRenderWizard()) {
						$explanation = '';
						$tmpObj->checkForUpdate($explanation);
						$updateMarkers = array(
							'next' => '<button type="submit" name="TYPO3_INSTALL[update][###IDENTIFIER###]">
						Next
						<span class="t3-install-form-button-icon-positive">&nbsp;</span>
					</button>',
							'identifier' => $identifier,
							'title' => $tmpObj->getTitle(),
							'explanation' => $explanation
						);
						// only display the message, no button
						if (!$tmpObj->shouldRenderNextButton()) {
							$updateMarkers['next'] = '';
						}
						$singleUpdate[] = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($singleUpdateWizardBoxSubpart, $updateMarkers, '###|###', TRUE, FALSE);
					}
				}
				if (!empty($singleUpdate)) {
					$updateWizardBoxesSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($updateWizardBoxesSubpart, '###SINGLEUPDATEWIZARDBOX###', implode(LF, $singleUpdate));
					$updateWizardBoxesMarkers = array(
						'action' => $this->action
					);
					$updateWizardBoxesSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($updateWizardBoxesSubpart, $updateWizardBoxesMarkers, '###|###', TRUE, FALSE);
				} else {
					$updateWizardBoxesSubpart = '
							<p>
								<strong>
									No updates to perform!
								</strong>
							</p>
						';
				}
				$updatesAvailableSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($updatesAvailableSubpart, '###UPDATEWIZARDBOXES###', $updateWizardBoxesSubpart);
				$updatesAvailableMarkers = array(
					'finalStep' => 'Final Step',
					'finalStepExplanation' => '
								<p>
									When all updates are done you should check
									your database for required updates.
									<br />
									Perform
									<strong>
										COMPARE DATABASE
									</strong>
									as often until no more changes are required.
									<br />
									<br />
								</p>
							',
					'compareDatabase' => 'COMPARE DATABASE'
				);
				$updatesAvailableSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($updatesAvailableSubpart, $updatesAvailableMarkers, '###|###', TRUE, FALSE);
			}
			$content = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($checkForUpdateSubpart, '###UPDATESAVAILABLE###', $updatesAvailableSubpart);
			break;
		case 'getUserInput':
			$title = 'Step 2 - Configuration of updates';
			$getUserInputSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($templateFile, '###GETUSERINPUT###');
			$markers = array(
				'introduction' => 'The following updates will be performed:',
				'showDatabaseQueries' => 'Show database queries performed',
				'performUpdates' => 'Perform updates!',
				'action' => $this->action
			);
			// update methods might need to get custom data
			$updatesAvailableSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($getUserInputSubpart, '###UPDATESAVAILABLE###');
			$updateItems = array();
			foreach ($this->INSTALL['update'] as $identifier => $tmp) {
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
			$content = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($content, '###UPDATESAVAILABLE###', $updatesAvailableSubpart);
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
			if (!$this->INSTALL['update']['extList']) {
				break;
			}
			$tmpObj = NULL;
			$this->getDatabase()->store_lastBuiltQuery = TRUE;
			foreach ($this->INSTALL['update']['extList'] as $identifier) {
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
						if ($this->INSTALL['update']['showDatabaseQueries']) {
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
		$tmpObj = GeneralUtility::getUserObj($className);
		$tmpObj->setIdentifier($identifier);
		$tmpObj->versionNumber = \TYPO3\CMS\Core\Utility\VersionNumberUtility::convertVersionNumberToInteger(TYPO3_version);
		$tmpObj->pObj = $this;
		$tmpObj->userInput = $this->INSTALL['update'][$identifier];
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

	/**********************
	 *
	 * GENERAL FUNCTIONS
	 *
	 **********************/
	/**
	 * Setting a message in the message-log and sets the fatalError flag if error type is 3.
	 *
	 * @param string $head Section header
	 * @param string $short_string A short description
	 * @param string $long_string A long (more detailed) description
	 * @param integer $type -1=OK sign, 0=message, 1=notification, 2=warning, 3=error
	 * @return void
	 */
	protected function message($head, $short_string = '', $long_string = '', $type = 0) {
		if ($type == 3) {
			$this->fatalError = 1;
		}
		$long_string = trim($long_string);
		$this->printSection($head, $short_string, $long_string, $type);
	}

	/**
	 * This "prints" a section with a message to the ->sections array
	 *
	 * @param string $head Section header
	 * @param string $short_string A short description
	 * @param string $long_string A long (more detailed) description
	 * @param integer $type -1=OK sign, 0=message, 1=notification, 2=warning , 3=error
	 * @return void
	 */
	protected function printSection($head, $short_string, $long_string, $type) {
		// Get the template file
		$templateFile = @file_get_contents((PATH_site . $this->templateFilePath . 'PrintSection.html'));
		// Get the template part from the file
		$template = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($templateFile, '###TEMPLATE###');
		$messageType = '';
		switch ($type) {
		case 3:
			$messageType = 'message-error';
			break;
		case 2:
			$messageType = 'message-warning';
			break;
		case 1:
			$messageType = 'message-notice';
			break;
		case 0:
			$messageType = 'message-information';
			break;
		case -1:
			$messageType = 'message-ok';
			break;
		}
		if (!trim($short_string)) {
			$content = '';
		} else {
			$longStringSubpart = '';
			if (trim($long_string)) {
				// Get the subpart for the long string
				$longStringSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($template, '###LONGSTRINGAVAILABLE###');
			}
			// Substitute the subpart for the long string
			$content = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($template, '###LONGSTRINGAVAILABLE###', $longStringSubpart);
			// Define the markers content
			$markers = array(
				'messageType' => $messageType,
				'shortString' => $short_string,
				'longString' => $long_string
			);
			// Fill the markers
			$content = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($content, $markers, '###|###', TRUE, FALSE);
		}
		$this->sections[$head][] = $content;
	}

	/**
	 * This prints all the messages in the ->section array
	 *
	 * @return string HTML of all the messages
	 */
	protected function printAll() {
		// Get the template file
		$templateFile = @file_get_contents((PATH_site . $this->templateFilePath . 'PrintAll.html'));
		// Get the template part from the file
		$template = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($templateFile, '###TEMPLATE###');
		$sections = array();
		foreach ($this->sections as $header => $valArray) {
			// Get the subpart for sections
			$sectionSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($template, '###SECTIONS###');
			// Define the markers content
			$sectionMarkers = array(
				'header' => $header . ':',
				'sectionContent' => implode(LF, $valArray)
			);
			// Fill the markers in the subpart
			$sections[] = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($sectionSubpart, $sectionMarkers, '###|###', TRUE, FALSE);
		}
		// Substitute the subpart for the sections
		$content = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($template, '###SECTIONS###', implode(LF, $sections));
		return $content;
	}

	/**
	 * This wraps and returns the main content of the page into proper html-code.
	 *
	 * @param string $content The page content
	 * @return string The full HTML page
	 */
	protected function outputWrapper($content) {
		// Get the template file
		if (!$this->passwordOK) {
			$this->template = @file_get_contents((PATH_site . $this->templateFilePath . 'Install_login.html'));
		} else {
			$this->template = @file_get_contents((PATH_site . $this->templateFilePath . 'Install.html'));
		}
		$javascript = array();
		$javascript[] = '<script type="text/javascript" src="' . GeneralUtility::createVersionNumberedFilename('../contrib/jquery/jquery-1.9.1.min.js') . '"></script>';
		$javascript[] = '<script type="text/javascript" src="' . GeneralUtility::createVersionNumberedFilename('../sysext/install/Resources/Public/Javascript/install.js') . '"></script>';

		$stylesheets = array();
		$stylesheets[] = '<link rel="stylesheet" type="text/css" href="' . GeneralUtility::createVersionNumberedFilename('../sysext/install/Resources/Public/Stylesheets/reset.css') . '" />';
		$stylesheets[] = '<link rel="stylesheet" type="text/css" href="' . GeneralUtility::createVersionNumberedFilename('../sysext/install/Resources/Public/Stylesheets/general.css') . '" />';

		// Get the browser info
		$browserInfo = \TYPO3\CMS\Core\Utility\ClientUtility::getBrowserInfo(GeneralUtility::getIndpEnv('HTTP_USER_AGENT'));
		// Add the stylesheet for Internet Explorer
		if ($browserInfo['browser'] === 'msie') {
			// IE7
			if (intval($browserInfo['version']) === 7) {
				$stylesheets[] = '<link rel="stylesheet" type="text/css" href="' . GeneralUtility::createVersionNumberedFilename('../sysext/install/Resources/Public/Stylesheets/ie7.css') . '" />';
			}
		}
		// Include the stylesheets based on screen
		if ($this->passwordOK) {
			$stylesheets[] = '<link rel="stylesheet" type="text/css" href="' . GeneralUtility::createVersionNumberedFilename('../sysext/install/Resources/Public/Stylesheets/install.css') . '" />';
		} else {
			$stylesheets[] = '<link rel="stylesheet" type="text/css" href="' . GeneralUtility::createVersionNumberedFilename('../sysext/install/Resources/Public/Stylesheets/install.css') . '" />';
			$stylesheets[] = '<link rel="stylesheet" type="text/css" href="' . GeneralUtility::createVersionNumberedFilename('../sysext/install/Resources/Public/Stylesheets/install_login.css') . '" />';
		}
		$markers = array();
		// Define the markers content
		$markers['headTitle'] = '
			TYPO3 ' . TYPO3_version . '
			Install Tool on site: ' . htmlspecialchars($GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename']) . '
		';
		$markers['title'] = 'TYPO3 ' . TYPO3_version;
		$markers['javascript'] = implode(LF, $javascript);
		$markers['stylesheets'] = implode(LF, $stylesheets);
		$markers['llErrors'] = 'The following errors occured';
		$markers['copyright'] = $this->copyright();
		$markers['charset'] = 'utf-8';
		$markers['backendUrl'] = '../index.php';
		$markers['backend'] = 'Backend admin';
		$markers['frontendUrl'] = '../../index.php';
		$markers['frontend'] = 'Frontend website';
		$markers['metaCharset'] = 'Content-Type" content="text/html; charset=';
		$markers['metaCharset'] .= 'utf-8';

		// Add the error messages
		$errorMessagesSubPart = '';
		if (!empty($this->errorMessages)) {
			// Get the subpart for all error messages
			$errorMessagesSubPart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($this->template, '###ERRORMESSAGES###');
			// Get the subpart for a single error message
			$errorMessageSubPart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($errorMessagesSubPart, '###MESSAGES###');
			$errors = array();
			foreach ($this->errorMessages as $errorMessage) {
				// Define the markers content
				$errorMessageMarkers = array(
					'message' => $errorMessage
				);
				// Fill the markers in the subpart
				$errors[] = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($errorMessageSubPart, $errorMessageMarkers, '###|###', TRUE, FALSE);
			}
			// Substitute the subpart for a single message
			$errorMessagesSubPart = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($errorMessagesSubPart, '###MESSAGES###', implode(LF, $errors));
		}

		// Version subpart is only allowed when password is ok
		$versionSubPart = '';
		if ($this->passwordOK) {
			// Get the subpart for the version
			$versionSubPart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($this->template, '###VERSIONSUBPART###');
			// Define the markers content
			$versionSubPartMarkers['version'] = 'Version: ' . TYPO3_version;
			// Fill the markers in the subpart
			$versionSubPart = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($versionSubPart, $versionSubPartMarkers, '###|###', TRUE, FALSE);
		}

		// Substitute the version subpart
		$this->template = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($this->template, '###VERSIONSUBPART###', $versionSubPart);
		// Substitute the menu subpart
		$this->template = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($this->template, '###MENU###', $this->menu());
		// Substitute the error messages subpart
		$this->template = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($this->template, '###ERRORMESSAGES###', $errorMessagesSubPart);
		// Substitute the content subpart
		$this->template = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($this->template, '###CONTENT###', $content);
		// Fill the markers
		$this->template = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($this->template, $markers, '###|###', TRUE, FALSE);
		return $this->template;
	}

	/**
	 * Outputs an error and dies.
	 * Should be used by all errors that occur before even starting the install tool process.
	 *
	 * @param string $content The content of the error
	 * @param string $title The title of the page
	 * @return void
	 */
	protected function outputErrorAndExit($content, $title = 'Install Tool error') {
		// Define the stylesheet
		$stylesheet = '<link rel="stylesheet" type="text/css" href="' . '../stylesheets/install/install.css" />';
		$javascript = '<script type="text/javascript" src="' . '../contrib/jquery/jquery-1.9.1.min.js"></script>' . LF;
		$javascript .= '<script type="text/javascript" src="' . '../sysext/install/Resources/Public/Javascript/install.js"></script>';
		// Get the template file
		$template = @file_get_contents(PATH_site . 'typo3/sysext/install/Resources/Private/Templates/Notice.html');
		// Define the markers content
		$markers = array(
			'styleSheet' => $stylesheet,
			'javascript' => $javascript,
			'title' => $title,
			'content' => $content
		);
		// Fill the markers
		$content = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($template, $markers, '###|###', 1, 1);
		// Output the warning message and exit
		header('Content-Type: text/html; charset=utf-8');
		header('Cache-Control: no-cache, must-revalidate');
		header('Pragma: no-cache');
		echo $content;
		die;
	}

	/**
	 * Sends the page to the client.
	 *
	 * @param string $content The HTML page
	 * @return void
	 */
	protected function output($content) {
		header('Content-Type: text/html; charset=utf-8');
		echo $content;
	}

	/**
	 * Generates the main menu
	 *
	 * @return string HTML
	 */
	protected function menu() {
		if (!$this->passwordOK) {
			return '';
		}
		$items = array();
		// Get the subpart for the main menu
		$menuSubPart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($this->template, '###MENU###');
		// Get the subpart for each single menu item
		$menuItemSubPart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($this->template, '###MENUITEM###');
		foreach ($this->menuitems as $k => $v) {
			// Define the markers content
			$markers = array(
				'class' => $this->INSTALL['type'] == $k ? 'class="act"' : '',
				'id' => 't3-install-menu-' . $k,
				'url' => htmlspecialchars($this->scriptSelf . '?TYPO3_INSTALL[type]=' . $k),
				'item' => $v
			);
			// Fill the markers in the subpart
			$items[] = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($menuItemSubPart, $markers, '###|###', TRUE, FALSE);
		}
		// Substitute the subpart for the single menu items
		$menuSubPart = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($menuSubPart, '###MENUITEM###', implode(LF, $items));
		return $menuSubPart;
	}

	/**
	 * Generate HTML for the copyright
	 *
	 * @return string HTML of the copyright
	 */
	protected function copyright() {
		$content = '
			<p>
				<strong>TYPO3 CMS.</strong> Copyright &copy; 1998-' . date('Y') . '
				Kasper Sk&#229;rh&#248;j. Extensions are copyright of their respective
				owners. Go to <a href="' . TYPO3_URL_GENERAL . '">' . TYPO3_URL_GENERAL . '</a>
				for details. TYPO3 comes with ABSOLUTELY NO WARRANTY;
				<a href="' . TYPO3_URL_LICENSE . '">click</a> for details.
				This is free software, and you are welcome to redistribute it
				under certain conditions; <a href="' . TYPO3_URL_LICENSE . '">click</a>
				for details. Obstructing the appearance of this notice is prohibited by law.
			</p>
			<p>
				<a href="' . TYPO3_URL_DONATE . '"><strong>Donate</strong></a> |
				<a href="' . TYPO3_URL_ORG . '">TYPO3.org</a>
			</p>
		';
		return $content;
	}

	/**
	 * Returns a newly created TYPO3 encryption key with a given length.
	 *
	 * @param integer $keyLength Desired key length
	 * @return string The encryption key
	 */
	protected function createEncryptionKey($keyLength = 96) {
		$bytes = GeneralUtility::generateRandomBytes($keyLength);
		return substr(bin2hex($bytes), -96);
	}

	/**
	 * Adds an error message that should be displayed.
	 * This is used by form protection and must be public!
	 *
	 * @param string $messageText
	 * @throws \InvalidArgumentException
	 * @return void
	 */
	public function addErrorMessage($messageText) {
		if ($messageText == '') {
			throw new \InvalidArgumentException('$messageText must not be empty.', 1294587483);
		}
		$this->errorMessages[] = $messageText;
	}

	/**
	 * Get database connection
	 *
	 * @return \TYPO3\CMS\Core\Database\DatabaseConnection
	 */
	protected function getDatabase() {
		return $GLOBALS['TYPO3_DB'];
	}
}
?>
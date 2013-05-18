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
	 * @todo Define visibility
	 */
	public $templateFilePath = 'typo3/sysext/install/Resources/Private/Templates/';

	/**
	 * @todo Define visibility
	 */
	public $template;

	/**
	 * @todo Define visibility
	 */
	public $javascript;

	/**
	 * @todo Define visibility
	 */
	public $stylesheets;

	/**
	 * @todo Define visibility
	 */
	public $markers = array();

	/**
	 * Used to set (error)messages from the executing functions like mail-sending, writing Localconf and such
	 *
	 * @var array
	 */
	protected $messages = array();

	/**
	 * @todo Define visibility
	 */
	public $errorMessages = array();

	/**
	 * @todo Define visibility
	 */
	public $mailMessage = '';

	/**
	 * @todo Define visibility
	 * The url that calls this script
	 */
	public $action = '';

	/**
	 * @todo Define visibility
	 * The url that calls this script
	 */
	public $scriptSelf = 'index.php';

	/**
	 * @todo Define visibility
	 */
	public $updateIdentity = 'TYPO3 Install Tool';

	/**
	 * @todo Define visibility
	 */
	public $headerStyle = '';

	/**
	 * @todo Define visibility
	 */
	public $setAllCheckBoxesByDefault = 0;

	/**
	 * @todo Define visibility
	 * In constructor: is set to global GET/POST var TYPO3_INSTALL
	 */
	public $INSTALL = array();

	/**
	 * @todo Define visibility
	 * If set, lzw capabilities of the available ImageMagick installs are check by actually writing a gif-file and comparing size
	 */
	public $checkIMlzw = 0;

	/**
	 * If set, ImageMagick is checked.
	 * @todo Define visibility
	 */
	public $checkIM = 0;

	/**
	 * If set, the image Magick commands are always outputted in the image processing checker
	 * @todo Define visibility
	 */
	public $dumpImCommands = 1;

	/**
	 * @todo Define visibility
	 * This is set, if the password check was ok. The function init() will exit if this is not set
	 */
	public $passwordOK = 0;

	/**
	 * @todo Define visibility
	 * Used to gather the message information.
	 */
	public $sections = array();

	/**
	 * @todo Define visibility
	 * This is set if some error occured that will definitely prevent TYpo3 from running.
	 */
	public $fatalError = 0;

	/**
	 * @todo Define visibility
	 */
	public $sendNoCacheHeaders = 1;

	/**
	 * @todo Define visibility
	 */
	public $config_array = array(
		// Flags are set in this array if the options are available and checked ok.
		'dir_typo3temp' => 0,
		'dir_temp' => 0,
		'im_versions' => array(),
		'im' => 0,
	);

	/**
	 * @todo Define visibility
	 */
	public $typo3temp_path = '';

	/**
	 * Session handling object
	 *
	 * @var \TYPO3\CMS\Install\Session
	 */
	protected $session = NULL;

	/**
	 * @todo Define visibility
	 */
	public $menuitems = array(
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
	 * Backpath (used for icons etc.)
	 *
	 * @var string
	 */
	protected $backPath = '../';

	/**
	 * @var \TYPO3\CMS\Install\Sql\SchemaMigrator Instance of SQL handler
	 */
	protected $sqlHandler = NULL;

	/**
	 * Prefix for checkbox fields when updating database.
	 *
	 * @var string
	 */
	protected $dbUpdateCheckboxPrefix = 'TYPO3_INSTALL[database_update]';

	protected $databaseUpdateErrorMessages = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->sqlHandler = GeneralUtility::makeInstance('TYPO3\\CMS\\Install\\Sql\\SchemaMigrator');

		if (!$GLOBALS['TYPO3_CONF_VARS']['BE']['installToolPassword']) {
			$this->outputErrorAndExit('Install Tool deactivated.<br />
				You must enable it by setting a password in typo3conf/LocalConfiguration.php. If you insert the value below at array position \'BE\' \'installToolPassword\', the password will be \'joh316\':<br /><br />
				\'bacb98acf97e0b6112b1d1b650b84971\'', 'Fatal error');
		}
		if ($this->sendNoCacheHeaders) {
			header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
			header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
			header('Expires: 0');
			header('Cache-Control: no-cache, must-revalidate');
			header('Pragma: no-cache');
		}
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
		$this->typo3temp_path = PATH_site . 'typo3temp/';
		if (!is_dir($this->typo3temp_path) || !is_writeable($this->typo3temp_path)) {
			$this->outputErrorAndExit('Install Tool needs to write to typo3temp/. Make sure this directory is writeable by your webserver: ' . htmlspecialchars($this->typo3temp_path), 'Fatal error');
		}
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
	 * @todo Define visibility
	 */
	public function checkPassword() {
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
	 * @todo Define visibility
	 */
	public function loginForm() {
		$password = GeneralUtility::_GP('password');
		$redirect_url = $this->redirect_url ? $this->redirect_url : $this->action;
		// Get the template file
		$templateFile = @file_get_contents((PATH_site . $this->templateFilePath . 'LoginForm.html'));
		// Get the template part from the file
		$template = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($templateFile, '###TEMPLATE###');
		// Password has been given, but this form is rendered again.
		// This means the given password was wrong
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
	 * @todo Define visibility
	 */
	public function init() {
		// Must be called after inclusion of init.php (or from init.php)
		if (!defined('PATH_typo3')) {
			die;
		}
		if (!$this->passwordOK) {
			die;
		}

		// Setting stuff...
		$this->check_mail();
		$this->setupGeneral();
		$this->generateConfigForm();
		if (count($this->messages)) {
			\TYPO3\CMS\Core\Utility\DebugUtility::debug($this->messages);
		}

		// Menu...
		switch ($this->INSTALL['type']) {
		case 'images':
			$this->checkIM = 1;
			$this->checkTheConfig();
			$this->checkTheImageProcessing();
			break;
		case 'database':
			$this->checkTheConfig();
			$this->checkTheDatabase();
			break;
		case 'update':
			$this->updateWizard();
			break;
		case 'config':
			$this->checkIM = 1;
			$this->message('About configuration', 'How to configure TYPO3', $this->generallyAboutConfiguration());
			$isPhpCgi = PHP_SAPI == 'fpm-fcgi' || PHP_SAPI == 'cgi' || PHP_SAPI == 'isapi' || PHP_SAPI == 'cgi-fcgi';
			$this->message('System Information', 'Your system has the following configuration', '
						<dl id="systemInformation">
							<dt>OS detected:</dt>
							<dd>' . (TYPO3_OS == 'WIN' ? 'WIN' : 'UNIX') . '</dd>
							<dt>CGI detected:</dt>
							<dd>' . ($isPhpCgi ? 'YES' : 'NO') . '</dd>
							<dt>PATH_thisScript:</dt>
							<dd>' . PATH_thisScript . '</dd>
						</dl>
					');
			$this->checkTheConfig();
			$ext = 'Write configuration';
			if ($this->fatalError) {
				$this->message($ext, 'Fatal error encountered!', '
						<p>
							Somewhere above a fatal configuration
							problem is encountered.
							Please make sure that you\'ve fixed this
							error before you submit the configuration.
							TYPO3 will not run if this problem is not
							fixed!
							<br />
							You should also check all warnings that may
							appear.
						</p>
					', 2);
			}
			$this->message($ext, 'Very Important: Changing Image Processing settings', '
					<p>
						When you change the settings for Image Processing
						you <em>must</em> take into account
						that <em>old images</em> may still be in typo3temp/
						folder and prevent new files from being generated!
						<br />
						This is especially important to know, if you\'re
						trying to set up image processing for the very first
						time.
						<br />
						The problem is solved by <a href="' . htmlspecialchars($this->setScriptName('cleanup')) . '">clearing the typo3temp/ folder</a>.
						Also make sure to clear the cache_pages table.
					</p>
				', 1, 1);
			$this->message($ext, 'Very Important: Changing Encryption Key setting', '
					<p>
						When you change the setting for the Encryption Key
						you <em>must</em> take into account that a change to
						this value might invalidate temporary information,
						URLs etc.
						<br />
						The problem is solved by <a href="' . htmlspecialchars($this->setScriptName('cleanup')) . '">clearing the typo3temp/ folder</a>.
						Also make sure to clear the cache_pages table.
					</p>
				', 1, 1);
			$this->message($ext, 'Update configuration', '
					<p>
						This form updates the configuration with the
						suggested values you see below. The values are based
						on the analysis above.
						<br />
						You can change the values in case you have
						alternatives to the suggested defaults.
						<br />
						By this final step you will configure TYPO3 for
						immediate use provided that you have no fatal errors
						left above.
					</p>' . $this->setupGeneral('get_form') . '
				', 0, 1);
			$this->output($this->outputWrapper($this->printAll()));
			break;
		case 'extConfig':
			$this->generateConfigForm('get_form');
			// Get the template file
			$templateFile = @file_get_contents((PATH_site . $this->templateFilePath . 'InitExtConfig.html'));
			// Get the template part from the file
			$template = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($templateFile, '###TEMPLATE###');
			// Define the markers content
			$markers = array(
				'action' => $this->action,
				'content' => $this->printAll(),
				'write' => 'Write configuration',
				'notice' => 'NOTICE:',
				'explanation' => '
						By clicking this button, the configuration is updated
						with new values for the parameters listed above!
					'
			);
			// Fill the markers in the template
			$content = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($template, $markers, '###|###', TRUE, FALSE);
			// Send content to the page wrapper function
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
	 * Calling the functions that checks the system
	 *
	 * @return void
	 * @todo Define visibility
	 */
	public function checkTheConfig() {
		// Order important:
		$this->checkConfiguration();
		$this->checkExtensions();
		if (TYPO3_OS == 'WIN') {
			$paths = array($GLOBALS['TYPO3_CONF_VARS']['GFX']['im_path_lzw'], $GLOBALS['TYPO3_CONF_VARS']['GFX']['im_path'], 'c:\\php\\imagemagick\\', 'c:\\php\\GraphicsMagick\\', 'c:\\apache\\ImageMagick\\', 'c:\\apache\\GraphicsMagick\\');
			if (!isset($_SERVER['PATH'])) {
				$serverPath = array_change_key_case($_SERVER, CASE_UPPER);
				$paths = array_merge($paths, explode(';', $serverPath['PATH']));
			} else {
				$paths = array_merge($paths, explode(';', $_SERVER['PATH']));
			}
		} else {
			$paths = array($GLOBALS['TYPO3_CONF_VARS']['GFX']['im_path_lzw'], $GLOBALS['TYPO3_CONF_VARS']['GFX']['im_path'], '/usr/local/bin/', '/usr/bin/', '/usr/X11R6/bin/', '/opt/local/bin/');
			$paths = array_merge($paths, explode(':', $_SERVER['PATH']));
		}
		$paths = array_unique($paths);
		asort($paths);
		if ($this->INSTALL['checkIM']['lzw']) {
			$this->checkIMlzw = 1;
		}
		if ($this->INSTALL['checkIM']['path']) {
			$paths[] = trim($this->INSTALL['checkIM']['path']);
		}
		if ($this->checkIM) {
			$this->checkImageMagick($paths);
		}
	}

	/*******************************
	 *
	 * CONFIGURATION FORM
	 *
	 ********************************/
	/**
	 * Creating the form for editing the TYPO3_CONF_VARS options.
	 *
	 * @param string $type If get_form, display form, otherwise checks and store in localconf.php
	 * @return void
	 * @todo Define visibility
	 */
	public function generateConfigForm($type = '') {
		$default_config_content = GeneralUtility::getUrl(
			GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Configuration\\ConfigurationManager')->getDefaultConfigurationFileLocation()
		);
		$commentArr = $this->getDefaultConfigArrayComments($default_config_content);
		switch ($type) {
		case 'get_form':
			// Get the template file
			$templateFile = @file_get_contents((PATH_site . $this->templateFilePath . 'GenerateConfigForm.html'));
			// Get the template part from the file
			$template = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($templateFile, '###TEMPLATE###');
			foreach ($GLOBALS['TYPO3_CONF_VARS'] as $k => $va) {
				$ext = '[' . $k . ']';
				$this->message($ext, '$TYPO3_CONF_VARS[\'' . $k . '\']', $commentArr[0][$k], 1);
				foreach ($va as $vk => $value) {
					if (isset($GLOBALS['TYPO3_CONF_VARS_extensionAdded'][$k][$vk])) {
						// Don't allow editing stuff which is added by extensions
						// Make sure we fix potentially duplicated entries from older setups
						$potentialValue = str_replace(array('\'.chr(10).\'', '\' . LF . \''), array(LF, LF), $value);
						while (preg_match('/' . preg_quote($GLOBALS['TYPO3_CONF_VARS_extensionAdded'][$k][$vk], '/') . '$/', '', $potentialValue)) {
							$potentialValue = preg_replace('/' . preg_quote($GLOBALS['TYPO3_CONF_VARS_extensionAdded'][$k][$vk], '/') . '$/', '', $potentialValue);
						}
						$value = $potentialValue;
					}
					$textAreaSubpart = '';
					$booleanSubpart = '';
					$textLineSubpart = '';
					$description = trim($commentArr[1][$k][$vk]);
					$isTextarea = preg_match('/^(<.*?>)?string \\(textarea\\)/i', $description) ? TRUE : FALSE;
					$doNotRender = preg_match('/^(<.*?>)?string \\(exclude\\)/i', $description) ? TRUE : FALSE;
					if (!is_array($value) && !$doNotRender && (!preg_match('/[' . LF . CR . ']/', $value) || $isTextarea)) {
						$k2 = '[' . $vk . ']';
						if ($isTextarea) {
							// Get the subpart for a textarea
							$textAreaSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($template, '###TEXTAREA###');
							// Define the markers content
							$textAreaMarkers = array(
								'id' => $k . '-' . $vk,
								'name' => 'TYPO3_INSTALL[extConfig][' . $k . '][' . $vk . ']',
								'value' => htmlspecialchars(str_replace(array('\'.chr(10).\'', '\' . LF . \''), array(LF, LF), $value))
							);
							// Fill the markers in the subpart
							$textAreaSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($textAreaSubpart, $textAreaMarkers, '###|###', TRUE, FALSE);
						} elseif (preg_match('/^(<.*?>)?boolean/i', $description)) {
							// Get the subpart for a checkbox
							$booleanSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($template, '###BOOLEAN###');
							// Define the markers content
							$booleanMarkers = array(
								'id' => $k . '-' . $vk,
								'name' => 'TYPO3_INSTALL[extConfig][' . $k . '][' . $vk . ']',
								'value' => $value && strcmp($value, '0') ? $value : 1,
								'checked' => $value ? 'checked="checked"' : ''
							);
							// Fill the markers in the subpart
							$booleanSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($booleanSubpart, $booleanMarkers, '###|###', TRUE, FALSE);
						} else {
							// Get the subpart for an input text field
							$textLineSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($template, '###TEXTLINE###');
							// Define the markers content
							$textLineMarkers = array(
								'id' => $k . '-' . $vk,
								'name' => 'TYPO3_INSTALL[extConfig][' . $k . '][' . $vk . ']',
								'value' => htmlspecialchars($value)
							);
							// Fill the markers in the subpart
							$textLineSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($textLineSubpart, $textLineMarkers, '###|###', TRUE, FALSE);
						}
						// Substitute the subpart for a textarea
						$content = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($template, '###TEXTAREA###', $textAreaSubpart);
						// Substitute the subpart for a checkbox
						$content = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($content, '###BOOLEAN###', $booleanSubpart);
						// Substitute the subpart for an input text field
						$content = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($content, '###TEXTLINE###', $textLineSubpart);
						// Define the markers content
						$markers = array(
							'description' => $description,
							'key' => '[' . $k . '][' . $vk . ']',
							'label' => htmlspecialchars(GeneralUtility::fixed_lgd_cs($value, 40))
						);
						// Fill the markers
						$content = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($content, $markers, '###|###', TRUE, FALSE);
						// Add the content to the message array
						$this->message($ext, $k2, $content);
					}
				}
			}
			break;
		default:
			if (is_array($this->INSTALL['extConfig'])) {
				$configurationPathValuePairs = array();
				foreach ($this->INSTALL['extConfig'] as $k => $va) {
					if (is_array($GLOBALS['TYPO3_CONF_VARS'][$k])) {
						foreach ($va as $vk => $value) {
							if (isset($GLOBALS['TYPO3_CONF_VARS'][$k][$vk])) {
								$description = trim($commentArr[1][$k][$vk]);
								if (preg_match('/^string \\(textarea\\)/i', $description)) {
									// Force Unix linebreaks in textareas
									$value = str_replace(CR, '', $value);
									// Preserve linebreaks
									$value = str_replace(LF, '\' . LF . \'', $value);
								}
								if (preg_match('/^boolean/i', $description)) {
									// When submitting settings in the Install Tool, values that default to "FALSE" or "TRUE"
									// in EXT:core/Configuration/DefaultConfiguration.php will be sent as "0" resp. "1".
									// Therefore, reset the values to their boolean equivalent.
									if ($GLOBALS['TYPO3_CONF_VARS'][$k][$vk] === FALSE && $value === '0') {
										$value = FALSE;
									} elseif ($GLOBALS['TYPO3_CONF_VARS'][$k][$vk] === TRUE && $value === '1') {
										$value = TRUE;
									}
								}
								if (strcmp($GLOBALS['TYPO3_CONF_VARS'][$k][$vk], $value)) {
									$configurationPathValuePairs['"' . $k . '"' . '/' . '"' . $vk . '"'] = $value;
								}
							}
						}
					}
				}
				$this->setLocalConfigurationValues($configurationPathValuePairs);
			}
			break;
		}
	}

	/**
	 * Make an array of the comments in the EXT:core/Configuration/DefaultConfiguration.php file
	 *
	 * @param string $string The contents of the EXT:core/Configuration/DefaultConfiguration.php file
	 * @param array $mainArray
	 * @param array $commentArray
	 * @return array
	 * @todo Define visibility
	 */
	public function getDefaultConfigArrayComments($string, $mainArray = array(), $commentArray = array()) {
		$lines = explode(LF, $string);
		$in = 0;
		$mainKey = '';
		foreach ($lines as $lc) {
			$lc = trim($lc);
			if ($in) {
				if (!strcmp($lc, ');')) {
					$in = 0;
				} else {
					if (preg_match('/["\']([[:alnum:]_-]*)["\'][[:space:]]*=>(.*)/i', $lc, $reg)) {
						preg_match('/,[\\t\\s]*\\/\\/(.*)/i', $reg[2], $creg);
						$theComment = trim($creg[1]);
						if (substr(strtolower(trim($reg[2])), 0, 5) == 'array' && !strcmp($reg[1], strtoupper($reg[1]))) {
							$mainKey = trim($reg[1]);
							$mainArray[$mainKey] = $theComment;
						} elseif ($mainKey) {
							$commentArray[$mainKey][$reg[1]] = $theComment;
						}
					}
				}
			}
			if (!strcmp($lc, 'return array(')) {
				$in = 1;
			}
		}
		return array($mainArray, $commentArray);
	}

	/*******************************
	 *
	 * CHECK CONFIGURATION FUNCTIONS
	 *
	 *******************************/
	/**
	 * Checking php.ini configuration and set appropriate messages and flags.
	 *
	 * @return void
	 * @todo Define visibility
	 */
	public function checkConfiguration() {
		$ext = 'php.ini configuration tests';
		$this->message($ext);
		$this->message($ext, 'Mail test', $this->check_mail('get_form'), -1);
	}

	/**
	 * Check if PHP function mail() works
	 *
	 * @param string $cmd If "get_form" then a formfield for the mail-address is shown. If not, it's checked if "check_mail" was in the INSTALL array and if so a test mail is sent to the recipient given.
	 * @return string The mail form if it is requested with get_form
	 * @todo Define visibility
	 */
	public function check_mail($cmd = '') {
		switch ($cmd) {
		case 'get_form':
			$out = '
					<p id="checkMailForm">
						You can check the functionality by entering your email
						address here and press the button. You should then
						receive a testmail from "typo3installtool@example.org".
					</p>
				';
			// Get the template file
			$templateFile = @file_get_contents((PATH_site . $this->templateFilePath . 'CheckMail.html'));
			// Get the template part from the file
			$template = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($templateFile, '###TEMPLATE###');
			if (!empty($this->mailMessage)) {
				// Get the subpart for the mail is sent message
				$mailSentSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($template, '###MAILSENT###');
			}
			$template = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($template, '###MAILSENT###', $mailSentSubpart);
			// Define the markers content
			$markers = array(
				'message' => $this->mailMessage,
				'enterEmail' => 'Enter the email address',
				'actionUrl' => $this->action . '#checkMailForm',
				'submit' => 'Send test mail'
			);
			// Fill the markers
			$out .= \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($template, $markers, '###|###', TRUE, TRUE);
			break;
		default:
			if (trim($this->INSTALL['check_mail'])) {
				$subject = 'TEST SUBJECT';
				$email = trim($this->INSTALL['check_mail']);
				/** @var $mailMessage \TYPO3\CMS\Core\Mail\MailMessage */
				$mailMessage = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Mail\\MailMessage');
				$mailMessage->addTo($email)->addFrom('typo3installtool@example.org', 'TYPO3 Install Tool')->setSubject($subject)->setBody('<html><body>HTML TEST CONTENT</body></html>');
				$mailMessage->addPart('TEST CONTENT');
				$mailMessage->send();
				$this->mailMessage = 'Mail was sent to: ' . $email;
			}
			break;
		}
		return $out;
	}

	/**
	 * Checking php extensions, specifically GDLib and Freetype
	 *
	 * @return void
	 * @todo Define visibility
	 */
	public function checkExtensions() {
		if (GeneralUtility::_GP('testingTrueTypeSupport')) {
			$this->checkTrueTypeSupport();
		}
		$ext = 'GDLib';
		$this->message($ext);
		$this->message($ext, 'FreeType quick-test (as GIF)', '
			<p>
				<img src="' . htmlspecialchars((GeneralUtility::getIndpEnv('REQUEST_URI') . '&testingTrueTypeSupport=1')) . '" alt="" />
				<br />
				If the text is exceeding the image borders you are
				using Freetype 2 and need to set
				TYPO3_CONF_VARS[GFX][TTFdpi]=96.
			</p>
		', -1);
	}

	/**
	 * Checking for existing ImageMagick installs.
	 *
	 * This tries to find available ImageMagick installations and tries to find the version numbers by executing "convert" without parameters. If the ->checkIMlzw is set, LZW capabilities of the IM installs are check also.
	 *
	 * @param array $paths Possible ImageMagick paths
	 * @return void
	 * @todo Define visibility
	 */
	public function checkImageMagick($paths) {
		$ext = 'Check Image Magick';
		$this->message($ext);
		// Get the template file
		$templateFile = @file_get_contents((PATH_site . $this->templateFilePath . 'CheckImageMagick.html'));
		$paths = array_unique($paths);
		$programs = explode(',', 'gm,convert,combine,composite,identify');
		$isExt = TYPO3_OS == 'WIN' ? '.exe' : '';
		$this->config_array['im_combine_filename'] = 'combine';
		foreach ($paths as $k => $v) {
			if (!preg_match('/[\\/]$/', $v)) {
				$v .= '/';
			}
			foreach ($programs as $filename) {
				if (ini_get('open_basedir') || file_exists($v) && @is_file(($v . $filename . $isExt))) {
					$version = $this->_checkImageMagick_getVersion($filename, $v);
					if ($version > 0) {
						// Assume GraphicsMagick
						if ($filename == 'gm') {
							$index[$v]['gm'] = $version;
							// No need to check for "identify" etc.
							continue;
						} else {
							// Assume ImageMagick
							$index[$v][$filename] = $version;
						}
					}
				}
			}
			if (count($index[$v]) >= 3 || $index[$v]['gm']) {
				$this->config_array['im'] = 1;
			}
			if ($index[$v]['gm'] || !$index[$v]['composite'] && $index[$v]['combine']) {
				$this->config_array['im_combine_filename'] = 'combine';
			} elseif ($index[$v]['composite'] && !$index[$v]['combine']) {
				$this->config_array['im_combine_filename'] = 'composite';
			}
			if (isset($index[$v]['convert']) && $this->checkIMlzw) {
				$index[$v]['gif_capability'] = '' . $this->_checkImageMagickGifCapability($v);
			}
		}
		$this->config_array['im_versions'] = $index;
		if (!$this->config_array['im']) {
			$this->message($ext, 'No ImageMagick installation available', '
				<p>
					It seems that there is no adequate ImageMagick installation
					available at the checked locations (' . implode(', ', $paths) . ')
					<br />
					An \'adequate\' installation for requires \'convert\',
					\'combine\'/\'composite\' and \'identify\' to be available
				</p>
			', 2);
		} else {
			// Get the subpart for the ImageMagick versions
			$theCode = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($templateFile, '###VERSIONS###');
			// Get the subpart for each ImageMagick version
			$rowsSubPart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($theCode, '###ROWS###');
			$rows = array();
			foreach ($this->config_array['im_versions'] as $p => $v) {
				$ka = array();
				reset($v);
				while (list($ka[]) = each($v)) {

				}
				// Define the markers content
				$rowsMarkers = array(
					'file' => $p,
					'type' => implode('<br />', $ka),
					'version' => implode('<br />', $v)
				);
				// Fill the markers in the subpart
				$rows[] = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($rowsSubPart, $rowsMarkers, '###|###', TRUE, FALSE);
			}
			// Substitute the subpart for the ImageMagick versions
			$theCode = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($theCode, '###ROWS###', implode(LF, $rows));
			// Add the content to the message array
			$this->message($ext, 'Available ImageMagick/GraphicsMagick installations:', $theCode, -1);
		}
		// Get the template file
		$formSubPart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($templateFile, '###FORM###');
		// Define the markers content
		$formMarkers = array(
			'actionUrl' => $this->action,
			'lzwChecked' => $this->INSTALL['checkIM']['lzw'] ? 'checked="checked"' : '',
			'lzwLabel' => 'Check LZW capabilities.',
			'checkPath' => 'Check this path for ImageMagick installation:',
			'imageMagickPath' => htmlspecialchars($this->INSTALL['checkIM']['path']),
			'comment' => '(Eg. "D:\\wwwroot\\im537\\ImageMagick\\" for Windows or "/usr/bin/" for Unix)',
			'send' => 'Send'
		);
		// Fill the markers
		$formSubPart = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($formSubPart, $formMarkers, '###|###', TRUE, FALSE);
		// Add the content to the message array
		$this->message($ext, 'Search for ImageMagick:', $formSubPart, 0);
	}

	/**
	 * Checking GIF-compression capabilities of ImageMagick install
	 *
	 * @param string $path Path of ImageMagick installation
	 * @return string Type of compression
	 * @todo Define visibility
	 */
	public function _checkImageMagickGifCapability($path) {
		if ($this->config_array['dir_typo3temp']) {
			$tempPath = $this->typo3temp_path;
			$uniqueName = md5(uniqid(microtime()));
			$dest = $tempPath . $uniqueName . '.gif';
			$src = $this->backPath . 'gfx/typo3logo.gif';
			if (@is_file($src) && !strstr($src, ' ') && !strstr($dest, ' ')) {
				$cmd = GeneralUtility::imageMagickCommand('convert', $src . ' ' . $dest, $path);
				\TYPO3\CMS\Core\Utility\CommandUtility::exec($cmd);
			} else {
				die('No typo3/gfx/typo3logo.gif file!');
			}
			$out = '';
			if (@is_file($dest)) {
				$new_info = @getimagesize($dest);
				clearstatcache();
				$new_size = filesize($dest);
				$src_info = @getimagesize($src);
				clearstatcache();
				$src_size = @filesize($src);
				if ($new_info[0] != $src_info[0] || $new_info[1] != $src_info[1] || !$new_size || !$src_size) {
					$out = 'error';
				} else {
					// NONE-LZW ratio was 5.5 in test
					if ($new_size / $src_size > 4) {
						$out = 'NONE';
					} elseif ($new_size / $src_size > 1.5) {
						$out = 'RLE';
					} else {
						$out = 'LZW';
					}
				}
				unlink($dest);
			}
			return $out;
		}
	}

	/**
	 * Extracts the version number for ImageMagick
	 *
	 * @param string $file The program name to execute in order to find out the version number
	 * @param string $path Path for the above program
	 * @return string Version number of the found ImageMagick instance
	 * @todo Define visibility
	 */
	public function _checkImageMagick_getVersion($file, $path) {
		// Temporarily override some settings
		$im_version = $GLOBALS['TYPO3_CONF_VARS']['GFX']['im_version_5'];
		$combine_filename = $GLOBALS['TYPO3_CONF_VARS']['GFX']['im_combine_filename'];
		if ($file == 'gm') {
			$GLOBALS['TYPO3_CONF_VARS']['GFX']['im_version_5'] = 'gm';
			// Work-around, preventing execution of "gm gm"
			$file = 'identify';
			// Work-around - GM doesn't like to be executed without any arguments
			$parameters = '-version';
		} else {
			$GLOBALS['TYPO3_CONF_VARS']['GFX']['im_version_5'] = 'im5';
			// Override the combine_filename setting
			if ($file == 'combine' || $file == 'composite') {
				$GLOBALS['TYPO3_CONF_VARS']['GFX']['im_combine_filename'] = $file;
			}
		}
		$cmd = GeneralUtility::imageMagickCommand($file, $parameters, $path);
		$retVal = FALSE;
		\TYPO3\CMS\Core\Utility\CommandUtility::exec($cmd, $retVal);
		$string = $retVal[0];
		list(, $ver) = explode('Magick', $string);
		list($ver) = explode(' ', trim($ver));
		// Restore the values
		$GLOBALS['TYPO3_CONF_VARS']['GFX']['im_version_5'] = $im_version;
		$GLOBALS['TYPO3_CONF_VARS']['GFX']['im_combine_filename'] = $combine_filename;
		return trim($ver);
	}

	/**
	 * Prints form for updating localconf.php or updates localconf.php depending on $cmd
	 *
	 * @param string $cmd If "get_form" it outputs the form. Default is to write "localconf.php" based on input in ->INSTALL[localconf.php] array and flag ->setLocalconf
	 * @return string Form HTML
	 * @todo Define visibility
	 */
	public function setupGeneral($cmd = '') {
		switch ($cmd) {
			case 'get_form':
				$out = $this->renderGeneral();
				break;
			default:
				$this->transferChosenConfigurationValuesToConfigurationFile();
				$out = '';
				break;
			}
		return $out;
	}

	/**
	 * Render general tab
	 *
	 * @return string Rendered view
	 */
	protected function renderGeneral() {
		// Get the template file
		$templateFile = @file_get_contents((PATH_site . $this->templateFilePath . 'SetupGeneral.html'));
		// Get the template part from the file
		$form = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($templateFile, '###TEMPLATE###');
		// Get the subpart for all modes
		$allModesSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($form, '###ALLMODES###');
		// Define the markers content
		$formMarkers['actionUrl'] = $this->action;
		$username = TYPO3_db_username;
		// Define the markers content
		$allModesMarkers = array(
			'labelUsername' => 'Username:',
			'username' => htmlspecialchars($username),
			'labelPassword' => 'Password:',
			'password' => htmlspecialchars(TYPO3_db_password),
			'labelHost' => 'Host:',
			'host' => htmlspecialchars(TYPO3_db_host),
			'labelDatabase' => 'Database:',
			'labelCreateDatabase' => 'Create database?'
		);
		// Get the subpart for the database list
		$databasesSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($allModesSubpart, '###DATABASELIST###');

		// Get the subpart when database is available
		$databaseAvailableSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($databasesSubpart, '###DATABASEAVAILABLE###');
		// Get the subpart for each database table
		$databaseItemSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($databaseAvailableSubpart, '###DATABASEITEM###');
		$dbArr = $this->getDatabaseList();
		$dbIncluded = 0;
		$databaseItems = array();
		foreach ($dbArr as $dbname) {
			// Define the markers content
			$databaseItemMarkers = array(
				'databaseSelected' => '',
				'databaseName' => htmlspecialchars($dbname),
				'databaseValue' => htmlspecialchars($dbname)
			);
			if ($dbname == TYPO3_db) {
				$databaseItemMarkers['databaseSelected'] = 'selected="selected"';
			}
			// Fill the markers in the subpart
			$databaseItems[] = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($databaseItemSubpart, $databaseItemMarkers, '###|###', TRUE, FALSE);
			if ($dbname == TYPO3_db) {
				$dbIncluded = 1;
			}
		}
		if (!$dbIncluded && TYPO3_db) {
			$databaseItemMarkers['databaseName'] = htmlspecialchars(TYPO3_db);
			$databaseItemMarkers['databaseSelected'] = 'selected="selected"';
			$databaseItemMarkers['databaseValue'] = htmlspecialchars(TYPO3_db) . ' (NO ACCESS!)';
			// Fill the markers in the subpart
			$databaseItems[] = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($databaseItemSubpart, $databaseItemMarkers, '###|###', TRUE, FALSE);
		}
		// Substitute the subpart for the database tables
		$databaseAvailableSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($databaseAvailableSubpart, '###DATABASEITEM###', implode(LF, $databaseItems));

		// Substitute the subpart when database is available
		$databasesSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($databasesSubpart, '###DATABASEAVAILABLE###', $databaseAvailableSubpart);
		// Substitute the subpart for the databases
		$allModesSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($allModesSubpart, '###DATABASELIST###', $databasesSubpart);
		// Fill the markers in the subpart for all modes
		$allModesSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($allModesSubpart, $allModesMarkers, '###|###', TRUE, FALSE);
		// Substitute the subpart for all modes
		$form = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($form, '###ALLMODES###', $allModesSubpart);

		// Get the subpart for the regular mode
		$regularModeSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($form, '###REGULARMODE###');
		// Define the markers content
		$regularModeMarkers = array(
			'labelSiteName' => 'Site name:',
			'siteName' => htmlspecialchars($GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename']),
			'labelEncryptionKey' => 'Encryption key:',
			'encryptionKey' => htmlspecialchars($GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']),
			'labelGenerateRandomKey' => 'Generate random key'
		);
		// Other
		$fA = $this->setupGeneralCalculate();
		$regularModeMarkers['labelCurrentValueIs'] = 'current value is';
		// Disable exec function
		if (is_array($fA['disable_exec_function'])) {
			// Get the subpart for the disable exec function
			$disableExecFunctionSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($regularModeSubpart, '###DISABLEEXECFUNCTIONSUBPART###');
			$regularModeMarkers['labelDisableExecFunction'] = '[BE][disable_exec_function]=';
			$regularModeMarkers['strongDisableExecFunction'] = (int) current($fA['disable_exec_function']);
			$regularModeMarkers['defaultDisableExecFunction'] = (int) $GLOBALS['TYPO3_CONF_VARS']['BE']['disable_exec_function'];
			$regularModeMarkers['disableExecFunction'] = (int) current($fA['disable_exec_function']);
			// Fill the markers in the subpart
			$disableExecFunctionSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($disableExecFunctionSubpart, $regularModeMarkers, '###|###', TRUE, FALSE);
		}
		// Substitute the subpart for the disable exec function
		$regularModeSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($regularModeSubpart, '###DISABLEEXECFUNCTIONSUBPART###', $disableExecFunctionSubpart);
		// GDlib
		if (is_array($fA['gdlib'])) {
			// Get the subpart for the disable gd lib
			$gdLibSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($regularModeSubpart, '###DISABLEGDLIB###');
			$regularModeMarkers['labelGdLib'] = '[GFX][gdlib]=';
			$regularModeMarkers['strongGdLib'] = (int) current($fA['gdlib']);
			$regularModeMarkers['defaultGdLib'] = (int) $GLOBALS['TYPO3_CONF_VARS']['GFX']['gdlib'];
			$regularModeMarkers['gdLib'] = (int) current($fA['gdlib']);
			// Fill the markers in the subpart
			$gdLibSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($gdLibSubpart, $regularModeMarkers, '###|###', TRUE, FALSE);
		}
		// Substitute the subpart for the disable gdlib
		$regularModeSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($regularModeSubpart, '###DISABLEGDLIB###', $gdLibSubpart);
		// GDlib PNG
		if (is_array($fA['gdlib_png']) && $GLOBALS['TYPO3_CONF_VARS']['GFX']['gdlib']) {
			// Get the subpart for the gdlib png
			$gdLibPngSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($regularModeSubpart, '###GDLIBPNGSUBPART###');
			// Get the subpart for the dropdown options
			$gdLibPngOptionSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($gdLibPngSubpart, '###GDLIBPNGOPTION###');
			$gdLibPngLabels = $this->setLabelValueArray($fA['gdlib_png'], 2);
			reset($gdLibPngLabels);
			$regularModeMarkers['labelGdLibPng'] = '[GFX][gdlib_png]=';
			$regularModeMarkers['strongGdLibPng'] = (string) current($gdLibPngLabels);
			$regularModeMarkers['defaultGdLibPng'] = (int) $GLOBALS['TYPO3_CONF_VARS']['GFX']['gdlib_png'];
			$gdLibPngOptions = array();
			foreach ($gdLibPngLabels as $k => $v) {
				list($cleanV) = explode('|', $fA['gdlib_png'][$k]);
				$gdLibPngMarker['value'] = htmlspecialchars($fA['gdlib_png'][$k]);
				$gdLibPngMarker['data'] = htmlspecialchars($v);
				if (!strcmp($GLOBALS['TYPO3_CONF_VARS']['GFX']['gdlib_png'], $cleanV)) {
					$gdLibPngMarker['selected'] = 'selected="selected"';
				}
				// Fill the markers in the subpart
				$gdLibPngOptions[] = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($gdLibPngOptionSubpart, $gdLibPngMarker, '###|###', TRUE, FALSE);
			}
			// Substitute the subpart for the dropdown options
			$gdLibPngSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($gdLibPngSubpart, '###GDLIBPNGOPTION###', implode(LF, $gdLibPngOptions));
			// Fill the markers in the subpart
			$gdLibPngSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($gdLibPngSubpart, $regularModeMarkers, '###|###', TRUE, FALSE);
		}
		// Substitute the subpart for the gdlib png
		$regularModeSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($regularModeSubpart, '###GDLIBPNGSUBPART###', $gdLibPngSubpart);
		// ImageMagick
		if (is_array($fA['im'])) {
			// Get the subpart for ImageMagick
			$imageMagickSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($regularModeSubpart, '###IMAGEMAGICKSUBPART###');
			// Define the markers content
			$regularModeMarkers['labelImageMagick'] = '[GFX][im]=';
			$regularModeMarkers['strongImageMagick'] = (string) current($fA['im']);
			$regularModeMarkers['defaultImageMagick'] = (int) $GLOBALS['TYPO3_CONF_VARS']['GFX']['im'];
			$regularModeMarkers['imageMagick'] = (int) current($fA['im']);
			// Fill the markers in the subpart
			$imageMagickSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($imageMagickSubpart, $regularModeMarkers, '###|###', TRUE, FALSE);
			// IM Combine Filename
			// Get the subpart for ImageMagick Combine filename
			$imCombineFileNameSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($regularModeSubpart, '###IMCOMBINEFILENAMESUBPART###');
			// Define the markers content
			$regularModeMarkers['labelImCombineFilename'] = '[GFX][im_combine_filename]';
			$regularModeMarkers['strongImCombineFilename'] = htmlspecialchars((string) current($fA['im_combine_filename']));
			$regularModeMarkers['defaultImCombineFilename'] = htmlspecialchars((string) $GLOBALS['TYPO3_CONF_VARS']['GFX']['im_combine_filename']);
			$regularModeMarkers['imCombineFilename'] = htmlspecialchars((string) ($fA['im_combine_filename'] ? current($fA['im_combine_filename']) : 'combine'));
			// Fill the markers in the subpart
			$imCombineFileNameSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($imCombineFileNameSubpart, $regularModeMarkers, '###|###', TRUE, FALSE);
			// IM Version 5
			// Get the subpart for ImageMagick Version 5
			$imVersion5Subpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($regularModeSubpart, '###IMVERSION5SUBPART###');
			// Define the markers content
			$regularModeMarkers['labelImVersion5'] = '[GFX][im_version_5]=';
			$regularModeMarkers['strongImVersion5'] = htmlspecialchars((string) current($fA['im_version_5']));
			$regularModeMarkers['defaultImVersion5'] = htmlspecialchars((string) $GLOBALS['TYPO3_CONF_VARS']['GFX']['im_version_5']);
			$regularModeMarkers['imVersion5'] = htmlspecialchars((string) ($fA['im_version_5'] ? current($fA['im_version_5']) : ''));
			// Fill the markers in the subpart
			$imVersion5Subpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($imVersion5Subpart, $regularModeMarkers, '###|###', TRUE, FALSE);
			if ($GLOBALS['TYPO3_CONF_VARS']['GFX']['im']) {
				// IM Path
				if (is_array($fA['im_path'])) {
					// Get the subpart for ImageMagick path
					$imPathSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($regularModeSubpart, '###IMPATHSUBPART###');
					$labelImPath = $this->setLabelValueArray($fA['im_path'], 1);
					reset($labelImPath);
					$imPath = $this->setLabelValueArray($fA['im_path'], 0);
					reset($imPath);
					// Define the markers content
					$regularModeMarkers['labelImPath'] = '[GFX][im_path]=';
					$regularModeMarkers['strongImPath'] = htmlspecialchars((string) current($labelImPath));
					$regularModeMarkers['defaultImPath'] = htmlspecialchars((string) $GLOBALS['TYPO3_CONF_VARS']['GFX']['im_path']);
					$regularModeMarkers['ImPath'] = htmlspecialchars((string) current($imPath));
					// Fill the markers in the subpart
					$imPathSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($imPathSubpart, $regularModeMarkers, '###|###', TRUE, FALSE);
				}
				// IM Path LZW
				if (is_array($fA['im_path_lzw'])) {
					// Get the subpart for ImageMagick lzw path
					$imPathLzwSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($regularModeSubpart, '###IMPATHLZWSUBPART###');
					// Get the subpart for ImageMagick lzw path dropdown options
					$imPathOptionSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($regularModeSubpart, '###IMPATHLZWOPTION###');
					$labelImPathLzw = $this->setLabelValueArray($fA['im_path_lzw'], 1);
					reset($labelImPathLzw);
					$imPathLzw = $this->setLabelValueArray($fA['im_path_lzw'], 0);
					reset($imPathLzw);
					// Define the markers content
					$regularModeMarkers['labelImPathLzw'] = '[GFX][im_path_lzw]=';
					$regularModeMarkers['strongImPathLzw'] = htmlspecialchars((string) current($labelImPathLzw));
					$regularModeMarkers['defaultImPathLzw'] = htmlspecialchars((string) $GLOBALS['TYPO3_CONF_VARS']['GFX']['im_path_lzw']);
					$regularModeMarkers['ImPathLzw'] = htmlspecialchars((string) current($imPathLzw));
					$imPathLzwOptions = array();
					foreach ($labelImPathLzw as $k => $v) {
						list($cleanV) = explode('|', $fA['im_path_lzw'][$k]);
						// Define the markers content
						$imPathLzwMarker = array(
							'value' => htmlspecialchars($fA['im_path_lzw'][$k]),
							'data' => htmlspecialchars($v)
						);
						if (!strcmp($GLOBALS['TYPO3_CONF_VARS']['GFX']['im_path_lzw'], $cleanV)) {
							$imPathLzwMarker['selected'] = 'selected="selected"';
						}
						// Fill the markers in the subpart
						$imPathLzwOptions[] = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($imPathOptionSubpart, $imPathLzwMarker, '###|###', TRUE, FALSE);
					}
					// Substitute the subpart for ImageMagick lzw path dropdown options
					$imPathLzwSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($imPathLzwSubpart, '###IMPATHLZWOPTION###', implode(LF, $imPathLzwOptions));
					// Fill the markers in the subpart
					$imPathLzwSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($imPathLzwSubpart, $regularModeMarkers, '###|###', TRUE, FALSE);
				}
			}
		}
		// Substitute the subpart for ImageMagick
		$regularModeSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($regularModeSubpart, '###IMAGEMAGICKSUBPART###', $imageMagickSubpart);
		// Substitute the subpart for ImageMagick Combine filename
		$regularModeSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($regularModeSubpart, '###IMCOMBINEFILENAMESUBPART###', $imCombineFileNameSubpart);
		// Substitute the subpart for ImageMagick Version 5
		$regularModeSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($regularModeSubpart, '###IMVERSION5SUBPART###', $imVersion5Subpart);
		// Substitute the subpart for ImageMagick path
		$regularModeSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($regularModeSubpart, '###IMPATHSUBPART###', $imPathSubpart);
		// Substitute the subpart for ImageMagick lzw path
		$regularModeSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($regularModeSubpart, '###IMPATHLZWSUBPART###', $imPathLzwSubpart);
		// TrueType Font dpi
		// Get the subpart for TrueType dpi
		$ttfDpiSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($regularModeSubpart, '###TTFDPISUBPART###');
		// Define the markers content
		$regularModeMarkers['labelTtfDpi'] = '[GFX][TTFdpi]=';
		$regularModeMarkers['ttfDpi'] = htmlspecialchars($GLOBALS['TYPO3_CONF_VARS']['GFX']['TTFdpi']);
		// Fill the markers in the subpart
		$ttfDpiSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($ttfDpiSubpart, $regularModeMarkers, '###|###', TRUE, FALSE);
		// Substitute the subpart for TrueType dpi
		$regularModeSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($regularModeSubpart, '###TTFDPISUBPART###', $ttfDpiSubpart);
		// Fill the markers in the regular mode subpart
		$regularModeSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($regularModeSubpart, $regularModeMarkers, '###|###', TRUE, FALSE);

		$formMarkers['labelUpdateLocalConf'] = 'Update configuration';
		$formMarkers['labelNotice'] = 'NOTICE:';
		$formMarkers['labelCommentUpdateLocalConf'] = 'By clicking this button, the configuration is updated with new values for the parameters listed above!';
		// Substitute the subpart for regular mode
		$form = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($form, '###REGULARMODE###', $regularModeSubpart);
		// Fill the markers
		return \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($form, $formMarkers, '###|###', TRUE, FALSE);
	}

	/**
	 * Transfer data from $this->INSTALL to LocalConfiguration
	 *
	 * @return void
	 */
	protected function transferChosenConfigurationValuesToConfigurationFile() {
		$localConfigurationPathValuePairs = array();
		if (is_array($this->INSTALL['Database'])) {
			// New database?
			if (trim($this->INSTALL['Database']['NEW_DATABASE_NAME'])) {
				$newDatabaseName = trim($this->INSTALL['Database']['NEW_DATABASE_NAME']);
					// Hyphen is not allowed in unquoted database names (at least for MySQL databases)
				if (!preg_match('/[^[:alnum:]_]/', $newDatabaseName)) {
					if ($result = $this->getDatabase()->sql_pconnect()) {
						if ($this->getDatabase()->admin_query('CREATE DATABASE ' . $newDatabaseName . ' CHARACTER SET utf8')) {
							$this->INSTALL['Database']['typo_db'] = $newDatabaseName;
							$this->messages[] = 'Database \'' . $newDatabaseName . '\' created';
						} else {
							$this->errorMessages[] = '
									Could not create database \'' . $newDatabaseName . '\' (...not created)
								';
						}
					} else {
						$this->errorMessages[] = '
								Could not connect to database when creating
								database \'' . $newDatabaseName . '\' (...not
								created)
							';
					}
				} else {
					$this->errorMessages[] = '
							The NEW database name \'' . $newDatabaseName . '\' was
							not alphanumeric, a-zA-Z0-9_ (...not created)
						';
				}
			}
			foreach ($this->INSTALL['Database'] as $key => $value) {
				switch ((string) $key) {
				case 'typo_db_username':
					if (strlen($value) <= 50) {
						if (strcmp(TYPO3_db_username, $value)) {
							$localConfigurationPathValuePairs['DB/username'] = $value;
						}
					} else {
						$this->errorMessages[] = '
									Username \'' . $value . '\' was longer
									than 50 chars (...not saved)
								';
					}
					break;
				case 'typo_db_password':
					if (strlen($value) <= 50) {
						if (strcmp(TYPO3_db_password, $value)) {
							$localConfigurationPathValuePairs['DB/password'] = $value;
						}
					} else {
						$this->errorMessages[] = '
									Password was longer than 50 chars (...not saved)
								';
					}
					break;
				case 'typo_db_host':
					if (preg_match('/^[a-zA-Z0-9_\\.-]+(:.+)?$/', $value) && strlen($value) <= 50) {
						if (strcmp(TYPO3_db_host, $value)) {
							$localConfigurationPathValuePairs['DB/host'] = $value;
						}
					} else {
						$this->errorMessages[] = '
									Host \'' . $value . '\' was not
									alphanumeric (a-z, A-Z, 0-9 or _-.), or
									longer than 50 chars (...not saved)
								';
					}
					break;
				case 'typo_db':
					if (strlen($value) <= 50) {
						if (strcmp(TYPO3_db, $value)) {
							$localConfigurationPathValuePairs['DB/database'] = $value;
						}
					} else {
						$this->errorMessages[] = '
									Database name \'' . $value . '\' was
									longer than 50 chars (...not saved)
								';
					}
					break;
				}
			}
		}
		if (is_array($this->INSTALL['LocalConfiguration'])) {
			foreach ($this->INSTALL['LocalConfiguration'] as $key => $value) {
				switch ((string) $key) {
				case 'disable_exec_function':
					if (strcmp(GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Configuration\\ConfigurationManager')->getConfigurationValueByPath('BE/disable_exec_function'), $value)) {
						$localConfigurationPathValuePairs['BE/disable_exec_function'] = $value ? 1 : 0;
					}
					break;
				case 'sitename':
					if (strcmp(GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Configuration\\ConfigurationManager')->getConfigurationValueByPath('SYS/sitename'), $value)) {
						$localConfigurationPathValuePairs['SYS/sitename'] = $value;
					}
					break;
				case 'encryptionKey':
					if (strcmp(GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Configuration\\ConfigurationManager')->getConfigurationValueByPath('SYS/encryptionKey'), $value)) {
						$localConfigurationPathValuePairs['SYS/encryptionKey'] = $value;
						// The session object in this request must use the new encryption key to write to the right session folder
						$GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = $value;
					}
					break;
				case 'compat_version':
					if (strcmp(GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Configuration\\ConfigurationManager')->getConfigurationValueByPath('SYS/compat_version'), $value)) {
						$localConfigurationPathValuePairs['SYS/compat_version'] = $value;
					}
					break;
				case 'im_combine_filename':
					if (strcmp(GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Configuration\\ConfigurationManager')->getConfigurationValueByPath('GFX/im_combine_filename'), $value)) {
						$localConfigurationPathValuePairs['GFX/im_combine_filename'] = $value;
					}
					break;
				case 'gdlib':

				case 'gdlib_png':

				case 'im':
					if (strcmp(GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Configuration\\ConfigurationManager')->getConfigurationValueByPath('GFX/' . $key), $value)) {
						$localConfigurationPathValuePairs['GFX/' . $key] = $value ? 1 : 0;
					}
					break;
				case 'im_path':
					list($value, $version) = explode('|', $value);
					if (strcmp(GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Configuration\\ConfigurationManager')->getConfigurationValueByPath('GFX/' . $key), $value)) {
						$localConfigurationPathValuePairs['GFX/' . $key] = $value;
					}
					if (doubleval($version) > 0 && doubleval($version) < 4) {
						// Assume GraphicsMagick
						$value_ext = 'gm';
					} else {
						// Assume ImageMagick 6.x
						$value_ext = 'im6';
					}
					if (strcmp(strtolower(GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Configuration\\ConfigurationManager')->getConfigurationValueByPath('GFX/im_version_5')), $value_ext)) {
						$localConfigurationPathValuePairs['GFX/im_version_5'] = $value_ext;
					}
					break;
				case 'im_path_lzw':
					list($value) = explode('|', $value);
					if (strcmp(GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Configuration\\ConfigurationManager')->getConfigurationValueByPath('GFX/' . $key), $value)) {
						$localConfigurationPathValuePairs['GFX/' . $key] = $value;
					}
					break;
				case 'TTFdpi':
					if (strcmp(GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Configuration\\ConfigurationManager')->getConfigurationValueByPath('GFX/TTFdpi'), $value)) {
						$localConfigurationPathValuePairs['GFX/TTFdpi'] = $value;
					}
					break;
				}
			}
		}
		if (!empty($localConfigurationPathValuePairs)) {
			$this->setLocalConfigurationValues($localConfigurationPathValuePairs);
		}
	}

	/**
	 * Set new configuration values in LocalConfiguration.php
	 *
	 * @param array $pathValuePairs
	 * @return void
	 */
	protected function setLocalConfigurationValues(array $pathValuePairs) {
		// Get the template file
		$templateFile = @file_get_contents((PATH_site . $this->templateFilePath . 'WriteToLocalConfControl.html'));
		if (GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Configuration\\ConfigurationManager')->setLocalConfigurationValuesByPathValuePairs($pathValuePairs)) {
			// Get the template part from the file
			$template = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($templateFile, '###CONTINUE###');
			// Get the subpart for messages
			$messagesSubPart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($template, '###MESSAGES###');
			$messages = array();
			foreach ($this->messages as $message) {
				// Define the markers content
				$messagesMarkers['message'] = $message;
				// Fill the markers in the subpart
				$messages[] = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($messagesSubPart, $messagesMarkers, '###|###', TRUE, FALSE);
			}
			// Substitute the subpart for messages
			$content = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($template, '###MESSAGES###', implode(LF, $messages));
			// Define the markers content
			$markers = array(
				'header' => 'Writing configuration',
				'action' => $this->action,
				'label' => 'Click to continue...'
			);
			// Fill the markers
			$content = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($content, $markers, '###|###', TRUE, FALSE);
			$this->output($this->outputWrapper($content));
		} else {
			// Get the template part from the file
			$template = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($templateFile, '###NOCHANGE###');
			// Define the markers content
			$markers = array(
				'header' => 'Writing configuration',
				'message' => 'No values were changed, so nothing is updated!',
				'action' => $this->action,
				'label' => 'Click to continue...'
			);
			// Fill the markers
			$content = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($template, $markers, '###|###', TRUE, FALSE);
			$this->output($this->outputWrapper($content));
		}
	}

	/**
	 * Writes or returns lines from localconf.php
	 *
	 * @param array $lines Array of lines to write back to localconf.php. Possibly
	 * @param boolean $showOutput If TRUE then print what has been done.
	 * @return void
	 * @deprecated with 6.0, will be removed two versions later
	 * @todo Define visibility
	 */
	public function writeToLocalconf_control($lines = '', $showOutput = TRUE) {
		GeneralUtility::logDeprecatedFunction();
	}

	/**
	 * This appends something to value in the input array based on $type. Private.
	 *
	 * @param array $arr
	 * @param integer $type
	 * @return array
	 * @todo Define visibility
	 */
	public function setLabelValueArray($arr, $type) {
		foreach ($arr as $k => $v) {
			if ($this->config_array['im_versions'][$v]['gm']) {
				$program = 'gm';
			} else {
				$program = 'convert';
			}
			switch ($type) {
			case 0:
				$arr[$k] .= '|' . $this->config_array['im_versions'][$v][$program];
				break;
			case 1:
				if ($this->config_array['im_versions'][$v][$program]) {
					$arr[$k] .= ' (' . $this->config_array['im_versions'][$v][$program];
					$arr[$k] .= $this->config_array['im_versions'][$v]['gif_capability'] ? ', ' . $this->config_array['im_versions'][$v]['gif_capability'] : '';
					$arr[$k] .= ')';
				} else {
					$arr[$k] .= '';
				}
				break;
			case 2:
				$arr[$k] .= ' (' . ($v == 1 ? 'PNG' : 'GIF') . ')';
				break;
			}
		}
		return $arr;
	}

	/**
	 * Returns the list of available databases (with access-check based on username/password)
	 *
	 * @return array List of available databases
	 */
	public function getDatabaseList() {
		$dbArr = array();
		if ($result = $this->getDatabase()->sql_pconnect()) {
			$dbArr = $this->getDatabase()->admin_get_dbs();
		}
		// remove some database names that MySQL uses internally from the list of choosable DB names
		$reservedDatabaseNames = array('mysql', 'information_schema');
		$databaseList = array_diff($dbArr, $reservedDatabaseNames);
		return $databaseList;
	}

	/**
	 * Calculates the suggested setup that should be written to typo3conf/LocalConfiguration.php
	 *
	 * if PNG/GIF/GD
	 * - disable gdlib if nothing
	 * - select png/gif if only one of them is available, else PNG/GIF selector, defaulting to GIF
	 * - im_path (default to 4.2.9, preferable with LZW)		im_ver5-flag is set based on im_path being 4.2.9 or 5+
	 * - im_path_lzw (default to LZW version, pref. 4.2.9)
	 *
	 * @return array Suggested setup
	 * @todo Define visibility
	 */
	public function setupGeneralCalculate() {
		$formArray['disable_exec_function'] = array(0);
		$formArray['im_path'] = array('');
		$formArray['im_path_lzw'] = array('');
		$formArray['im_combine_filename'] = array('');
		$formArray['im_version_5'] = array('');
		$formArray['im'] = array(1);
		$formArray['gdlib'] = array(1);
		$formArray['gdlib_png'] = array(0, 1);
		if ($this->config_array['im']) {
			$formArray['im'] = array(1);
			$found = ($LZW_found = 0);
			$totalArr = array();
			foreach ($this->config_array['im_versions'] as $path => $dat) {
				if (count($dat) >= 3) {
					if (doubleval($dat['convert']) < 5) {
						$formArray['im_version_5'] = array(0);
						if ($dat['gif_capability'] == 'LZW') {
							$formArray['im_path'] = array($path);
							$found = 2;
						} elseif ($found < 2) {
							$formArray['im_path'] = array($path);
							$found = 1;
						}
					} elseif (doubleval($dat['convert']) >= 6) {
						$formArray['im_version_5'] = array('im6');
						if ($dat['gif_capability'] == 'LZW') {
							$formArray['im_path'] = array($path);
							$found = 2;
						} elseif ($found < 2) {
							$formArray['im_path'] = array($path);
							$found = 1;
						}
					}
				} elseif ($dat['gm']) {
					$formArray['im_version_5'] = array('gm');
					if ($dat['gif_capability'] == 'LZW') {
						$formArray['im_path'] = array($path);
						$found = 2;
					} elseif ($found < 2) {
						$formArray['im_path'] = array($path);
						$found = 1;
					}
				}
				if ($dat['gif_capability'] == 'LZW') {
					if (doubleval($dat['convert']) < 5 || !$LZW_found) {
						$formArray['im_path_lzw'] = array($path);
						$LZW_found = 1;
					}
				} elseif ($dat['gif_capability'] == 'RLE' && !$LZW_found) {
					$formArray['im_path_lzw'] = array($path);
				}
				$totalArr[] = $path;
			}
			$formArray['im_path'] = array_unique(array_merge($formArray['im_path'], $totalArr));
			$formArray['im_path_lzw'] = array_unique(array_merge($formArray['im_path_lzw'], $totalArr));
			$formArray['im_combine_filename'] = array($this->config_array['im_combine_filename']);
		} else {
			$formArray['im'] = array(0);
		}
		return $formArray;
	}

	/**
	 * Returns TRUE if TTF lib is installed.
	 *
	 * @return void
	 */
	public function checkTrueTypeSupport() {
		$im = @imagecreate(300, 50);
		imagecolorallocate($im, 255, 255, 55);
		$text_color = imagecolorallocate($im, 233, 14, 91);
		@imagettftext(
			$im,
			GeneralUtility::freetypeDpiComp(20),
			0,
			10,
			20,
			$text_color,
			\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('core') . 'Resources/Private/Font/vera.ttf',
			'Testing Truetype support'
		);
		header('Content-type: image/gif');
		imagegif($im);
		die;
	}

	/**
	 * Returns general information about configuration of TYPO3.
	 *
	 * @return string HTML with the general information
	 * @todo Define visibility
	 */
	public function generallyAboutConfiguration() {
		return '
		<p>
			Local configuration is done by overriding default values in the
			included file, typo3conf/LocalConfiguration.php. In this file you enter the
			database information along with values in the global array
			TYPO3_CONF_VARS.
			<br />
			The options in the TYPO3_CONF_VARS array and how to use it for your
			own purposes is discussed in the base configuration file,
			EXT:core/Configuration/DefaultConfiguration.php. This file sets up the default values and
			subsequently includes the LocalConfiguration.php file in which you can then
			override values.
			<br />
			See this page for <a href="' . TYPO3_URL_SYSTEMREQUIREMENTS . '">more
			information about system requirements.</a>
		</p>';
	}

	/**********************
	 *
	 * IMAGE processing
	 *
	 **********************/
	/**
	 * jesus.TIF:	IBM/LZW
	 * jesus.GIF:	Save for web, 32 colors
	 * jesus.JPG:	Save for web, 30 quality
	 * jesus.PNG:	Save for web, PNG-24
	 * jesus.tga	24 bit TGA file
	 * jesus.pcx
	 * jesus.bmp	24 bit BMP file
	 * jesus_ps6.PDF:	PDF w/layers and vector data
	 * typo3logo.ai:	Illustrator 8 file
	 * pdf_from_imagemagick.PDF	PDF-file made by Acrobat Distiller from InDesign PS-file
	 *
	 *
	 * Imagemagick
	 * - Read formats
	 * - Write png, gif, jpg
	 * - compare gif size
	 * - scaling (by stdgraphic)
	 * - combining (by stdgraphic)
	 *
	 * GDlib:
	 * - create from:....
	 * - ttf text
	 *
	 * From TypoScript: (GD only, GD+IM, IM)
	 *
	 * @return void
	 * @todo Define visibility
	 */
	public function checkTheImageProcessing() {
		$this->message('Image Processing', 'What is it?', '
			<p>
				TYPO3 is known for its ability to process images on the server.
				<br />
				In the backend interface (TBE) thumbnails are automatically
				generated (by ImageMagick in thumbs.php) as well as icons, menu
				items and pane tabs (by GDLib).
				<br />
				In the TypoScript enabled frontend all kinds of graphical
				elements are processed. Typically images are scaled down to fit
				the pages (by ImageMagick) and menu items, graphical headers and
				such are generated automatically (by GDLib + ImageMagick).
				<br />
				In addition TYPO3 is able to handle many file formats (thanks to
				ImageMagick), for example TIF, BMP, PCX, TGA, AI and PDF in
				addition to the standard web formats; JPG, GIF, PNG.
			</p>
			<p>
				In order to do this, TYPO3 uses two sets of tools:
			</p>
			<p>
				<strong>ImageMagick / GraphicsMagick:</strong>
				<br />
				For conversion of non-web formats to webformats, combining
				images with alpha-masks, performing image-effects like blurring
				and sharpening.
				<br />
				ImageMagick is a collection of external programs on the server
				called by the exec() function in PHP. TYPO3 uses three of these,
				namely \'convert\' (converting fileformats, scaling, effects),
				\'combine\'/\'composite\' (combining images with masks) and
				\'identify\' (returns image information).
				GraphicsMagick is an alternative to ImageMagick and can be enabled
				by setting [GFX][im_version_5] to \'gm\'. This is recommended and
				enabled by default.
				<br />
				Because ImageMagick and Graphicsmagick are external programs, a
				requirement must be met: The programs must be installed on the
				server and working.
				<br />
				ImageMagick is available for both Windows and Unix. The current
				version is 6+.
				<br />
				ImageMagick homepage is at <a href="http://www.imagemagick.org/">http://www.imagemagick.org/</a>
			</p>
			<p>
				<strong>GDLib:</strong>
				<br />
				For drawing boxes and rendering text on images with truetype
				fonts. Also used for icons, menuitems and generally the
				TypoScript GIFBUILDER object is based on GDlib, but extensively
				utilizing ImageMagick to process intermediate results.
				<br />
				GDLib is accessed through internal functions in PHP, you\'ll need a version
				of PHP with GDLib compiled in. Also in order to use TrueType
				fonts with GDLib you\'ll need FreeType compiled in as well.
				<br />
			</p>
			<p>
				You can disable all image processing options in TYPO3
				([GFX][image_processing]=0), but that would seriously disable
				TYPO3.
			</p>
		');
		$this->message('Image Processing', 'Verifying the image processing capabilities of your server', '
			<p>
				This page performs image processing and displays the result.
				It\'s a thorough check that everything you\'ve configured is
				working correctly.
				<br />
				It\'s quite simple to verify your installation; Just look down
				the page, the images in pairs should look like each other. If
				some images are not alike, something is wrong. You may also
				notice warnings and errors if this tool found signs of any
				problems.
			</p>
			<p>
				The image to the right is the reference image (how it should be)
				and to the left the image made by your server.
				<br />
				The reference images are made with the classic ImageMagick
				install based on the 4.2.9 RPM and 5.2.3 RPM. If the version 5
				flag is set, the reference images are made by the 5.2.3 RPM.
			</p>
			<p>
				This test will work only if your ImageMagick/GDLib configuration
				allows it to. The typo3temp/ folder must be writable for all the
				temporary image files. They are all prefixed \'install_\' so
				they are easy to recognize and delete afterwards.
			</p>
		');
		$im_path = $GLOBALS['TYPO3_CONF_VARS']['GFX']['im_path'];
		if ($GLOBALS['TYPO3_CONF_VARS']['GFX']['im_version_5'] == 'gm') {
			$im_path_version = $this->config_array['im_versions'][$im_path]['gm'];
		} else {
			$im_path_version = $this->config_array['im_versions'][$im_path]['convert'];
		}
		$im_path_lzw = $GLOBALS['TYPO3_CONF_VARS']['GFX']['im_path_lzw'];
		$im_path_lzw_version = $this->config_array['im_versions'][$im_path_lzw]['convert'];
		$msg = '
			<dl id="t3-install-imageprocessingim">
				<dt>
					ImageMagick enabled:
				</dt>
				<dd>
					' . htmlspecialchars($GLOBALS['TYPO3_CONF_VARS']['GFX']['im']) . '
				</dd>
				<dt>
					ImageMagick path:
				</dt>
				<dd>
					' . htmlspecialchars($im_path) . ' <span>(' . htmlspecialchars($im_path_version) . ')</span>
				</dd>
				<dt>
					ImageMagick path/LZW:
				</dt>
				<dd>
					' . htmlspecialchars($im_path_lzw) . ' <span>(' . htmlspecialchars($im_path_lzw_version) . ')</span>
				</dd>
				<dt>
					Version 5/GraphicsMagick flag:
				</dt>
				<dd>
					' . ($GLOBALS['TYPO3_CONF_VARS']['GFX']['im_version_5'] ? htmlspecialchars($GLOBALS['TYPO3_CONF_VARS']['GFX']['im_version_5']) : '&nbsp;') . '
				</dd>
			</dl>
			<dl id="t3-install-imageprocessingother">
				<dt>
					GDLib enabled:
				</dt>
				<dd>
					' . ($GLOBALS['TYPO3_CONF_VARS']['GFX']['gdlib'] ? htmlspecialchars($GLOBALS['TYPO3_CONF_VARS']['GFX']['gdlib']) : '&nbsp;') . '
				</dd>
				<dt>
					GDLib using PNG:
				</dt>
				<dd>
					' . ($GLOBALS['TYPO3_CONF_VARS']['GFX']['gdlib_png'] ? htmlspecialchars($GLOBALS['TYPO3_CONF_VARS']['GFX']['gdlib_png']) : '&nbsp;') . '
				</dd>
				<dt>
					IM5 effects enabled:
				</dt>
				<dd>
					' . htmlspecialchars($GLOBALS['TYPO3_CONF_VARS']['GFX']['im_v5effects']) . '
					<span>(Blurring/Sharpening with IM 5+)</span>
				</dd>
				<dt>
					Freetype DPI:
				</dt>
				<dd>
					' . htmlspecialchars($GLOBALS['TYPO3_CONF_VARS']['GFX']['TTFdpi']) . '
					<span>(Should be 96 for Freetype 2)</span>
				</dd>
				<dt>
					Mask invert:
				</dt>
				<dd>
					' . htmlspecialchars($GLOBALS['TYPO3_CONF_VARS']['GFX']['im_imvMaskState']) . '
					<span>(Should be set for some IM versions approx. 5.4+)</span>
				</dd>
			</dl>
			<dl id="t3-install-imageprocessingfileformats">
				<dt>
					File Formats:
				</dt>
				<dd>
					' . htmlspecialchars($GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext']) . '
				</dd>
			</dl>
		';
		// Various checks to detect IM/GM version mismatches
		$mismatch = FALSE;
		switch (strtolower($GLOBALS['TYPO3_CONF_VARS']['GFX']['im_version_5'])) {
		case 'gm':
			if (doubleval($im_path_version) >= 2) {
				$mismatch = TRUE;
			}
			break;
		default:
			if (($GLOBALS['TYPO3_CONF_VARS']['GFX']['im_version_5'] ? TRUE : FALSE) != doubleval($im_path_version) >= 6) {
				$mismatch = TRUE;
			}
			break;
		}
		if ($mismatch) {
			$msg .= '
				<p>
					Warning: Mismatch between the version of ImageMagick' . ' (' . htmlspecialchars($im_path_version) . ') and the configuration of ' . '[GFX][im_version_5] (' . htmlspecialchars($GLOBALS['TYPO3_CONF_VARS']['GFX']['im_version_5']) . ')
				</p>
			';
			$etype = 2;
		} else {
			$etype = 1;
		}
		if ($GLOBALS['TYPO3_CONF_VARS']['GFX']['im_version_5'] == 'gm') {
			$msg = str_replace('ImageMagick', 'GraphicsMagick', $msg);
		}
		$this->message('Image Processing', 'Current configuration', $msg, $etype);
		if (!$GLOBALS['TYPO3_CONF_VARS']['GFX']['image_processing']) {
			$this->message('Image Processing', 'Image Processing disabled!', '
				<p>
					Image Processing is disabled by the config flag
					[GFX][image_processing] set to FALSE (zero)
				</p>
			', 2);
			$this->output($this->outputWrapper($this->printAll()));
			return;
		}
		$msg = '
			<p>
				<a id="testmenu"></a>
				Click each of these links in turn to test a topic.
				<strong>
					Please be aware that each test may take several seconds!
				</strong>:
			</p>
		' . $this->imagemenu();
		$this->message('Image Processing', 'Testmenu', $msg, '');
		$parseStart = GeneralUtility::milliseconds();
		$imageProc = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Imaging\\GraphicalFunctions');
		$imageProc->init();
		$imageProc->tempPath = $this->typo3temp_path;
		$imageProc->dontCheckForExistingTempFile = 1;
		$imageProc->filenamePrefix = 'install_';
		$imageProc->dontCompress = 1;
		$imageProc->alternativeOutputKey = 'TYPO3_INSTALL_SCRIPT';
		$imageProc->noFramePrepended = $GLOBALS['TYPO3_CONF_VARS']['GFX']['im_noFramePrepended'];
		// Very temporary!!!
		$imageProc->dontUnlinkTempFiles = 0;
		$imActive = $this->config_array['im'] && $im_path;
		$gdActive = $GLOBALS['TYPO3_CONF_VARS']['GFX']['gdlib'];
		switch ($this->INSTALL['images_type']) {
		case 'read':
			$headCode = 'Reading and converting images';
			$this->message($headCode, 'Supported file formats', '
					<p>
						This verifies that your ImageMagick installation is able
						to read the nine default file formats; JPG, GIF, PNG,
						TIF, BMP, PCX, TGA, PDF, AI. The tool \'identify\' will
						be used to read the  pixeldimensions of non-web formats.
						The tool \'convert\' is used to read the image and write
						a temporary JPG-file.
					</p>
					<p>
						In case the images appear remarkably darker than the reference images,
						try to set [TYPO3_CONF_VARS][GFX][colorspace] = sRGB.
					</p>
				');
			if ($imActive) {
				// Reading formats - writing JPG
				$extArr = explode(',', 'jpg,gif,png,tif,bmp,pcx,tga');
				foreach ($extArr as $ext) {
					if ($this->isExtensionEnabled($ext, $headCode, 'Read ' . strtoupper($ext))) {
						$imageProc->IM_commands = array();
						$theFile = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('install') . 'imgs/jesus.' . $ext;
						if (!@is_file($theFile)) {
							die('Error: ' . $theFile . ' was not a file');
						}
						$imageProc->imageMagickConvert_forceFileNameBody = 'read_' . $ext;
						$fileInfo = $imageProc->imageMagickConvert($theFile, 'jpg', '', '', '', '', array(), TRUE);
						$result = $this->displayTwinImage($fileInfo[3], $imageProc->IM_commands);
						$this->message($headCode, 'Read ' . strtoupper($ext), $result[0], $result[1]);
					}
				}
				if ($this->isExtensionEnabled('pdf', $headCode, 'Read PDF')) {
					$imageProc->IM_commands = array();
					$theFile = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('install') . 'imgs/pdf_from_imagemagick.pdf';
					if (!@is_file($theFile)) {
						die('Error: ' . $theFile . ' was not a file');
					}
					$imageProc->imageMagickConvert_forceFileNameBody = 'read_pdf';
					$fileInfo = $imageProc->imageMagickConvert($theFile, 'jpg', '170', '', '', '', array(), TRUE);
					$result = $this->displayTwinImage($fileInfo[3], $imageProc->IM_commands);
					$this->message($headCode, 'Read PDF', $result[0], $result[1]);
				}
				if ($this->isExtensionEnabled('ai', $headCode, 'Read AI')) {
					$imageProc->IM_commands = array();
					$theFile = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('install') . 'imgs/typo3logotype.ai';
					if (!@is_file($theFile)) {
						die('Error: ' . $theFile . ' was not a file');
					}
					$imageProc->imageMagickConvert_forceFileNameBody = 'read_ai';
					$fileInfo = $imageProc->imageMagickConvert($theFile, 'jpg', '170', '', '', '', array(), TRUE);
					$result = $this->displayTwinImage($fileInfo[3], $imageProc->IM_commands);
					$this->message($headCode, 'Read AI', $result[0], $result[1]);
				}
			} else {
				$this->message($headCode, 'Test skipped', '
						<p>
							Use of ImageMagick has been disabled in the
							configuration.
							<br />
							Refer to section \'Basic Configuration\' to change
							or review you configuration settings
						</p>
					', 2);
			}
			break;
		case 'write':
			// Writingformats - writing JPG
			$headCode = 'Writing images';
			$this->message($headCode, 'Writing GIF and PNG', '
					<p>
						This verifies that ImageMagick is able to write GIF and
						PNG files.
						<br />
						The GIF-file is attempted compressed with LZW by the
						TYPO3\\CMS\\Core\\Utility\\GeneralUtility::gif_compress() function.
					</p>
				');
			if ($imActive) {
				// Writing GIF
				$imageProc->IM_commands = array();
				$theFile = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('install') . 'imgs/jesus.gif';
				if (!@is_file($theFile)) {
					die('Error: ' . $theFile . ' was not a file');
				}
				$imageProc->imageMagickConvert_forceFileNameBody = 'write_gif';
				$fileInfo = $imageProc->imageMagickConvert($theFile, 'gif', '', '', '', '', array(), TRUE);
				if ($GLOBALS['TYPO3_CONF_VARS']['GFX']['gif_compress']) {
					clearstatcache();
					$prevSize = GeneralUtility::formatSize(@filesize($fileInfo[3]));
					$returnCode = GeneralUtility::gif_compress($fileInfo[3], '');
					clearstatcache();
					$curSize = GeneralUtility::formatSize(@filesize($fileInfo[3]));
					$note = array('Note on gif_compress() function:', 'The \'gif_compress\' method used was \'' . $returnCode . '\'.<br />Previous filesize: ' . $prevSize . '. Current filesize:' . $curSize);
				} else {
					$note = array('Note on gif_compress() function:', '<em>Not used! Disabled by [GFX][gif_compress]</em>');
				}
				$result = $this->displayTwinImage($fileInfo[3], $imageProc->IM_commands, $note);
				$this->message($headCode, 'Write GIF', $result[0], $result[1]);
				// Writing PNG
				$imageProc->IM_commands = array();
				$theFile = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('install') . 'imgs/jesus.gif';
				$imageProc->imageMagickConvert_forceFileNameBody = 'write_png';
				$fileInfo = $imageProc->imageMagickConvert($theFile, 'png', '', '', '', '', array(), TRUE);
				$result = $this->displayTwinImage($fileInfo[3], $imageProc->IM_commands);
				$this->message($headCode, 'Write PNG', $result[0], $result[1]);
			} else {
				$this->message($headCode, 'Test skipped', '
						<p>
							Use of ImageMagick has been disabled in the
							configuration.
							<br />
							Refer to section \'Basic Configuration\' to change
							or review you configuration settings
						</p>
					', 2);
			}
			break;
		case 'scaling':
			// Scaling
			$headCode = 'Scaling images';
			$this->message($headCode, 'Scaling transparent images', '
					<p>
						This shows how ImageMagick reacts when scaling
						transparent GIF and PNG files.
					</p>
				');
			if ($imActive) {
				// Scaling transparent image
				$imageProc->IM_commands = array();
				$theFile = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('install') . 'imgs/jesus2_transp.gif';
				if (!@is_file($theFile)) {
					die('Error: ' . $theFile . ' was not a file');
				}
				$imageProc->imageMagickConvert_forceFileNameBody = 'scale_gif';
				$fileInfo = $imageProc->imageMagickConvert($theFile, 'gif', '150', '', '', '', array(), TRUE);
				if ($GLOBALS['TYPO3_CONF_VARS']['GFX']['gif_compress']) {
					clearstatcache();
					$prevSize = GeneralUtility::formatSize(@filesize($fileInfo[3]));
					$returnCode = GeneralUtility::gif_compress($fileInfo[3], '');
					clearstatcache();
					$curSize = GeneralUtility::formatSize(@filesize($fileInfo[3]));
					$note = array('Note on gif_compress() function:', 'The \'gif_compress\' method used was \'' . $returnCode . '\'.<br />Previous filesize: ' . $prevSize . '. Current filesize:' . $curSize);
				} else {
					$note = array('Note on gif_compress() function:', '<em>Not used! Disabled by [GFX][gif_compress]</em>');
				}
				$result = $this->displayTwinImage($fileInfo[3], $imageProc->IM_commands, $note);
				$this->message($headCode, 'GIF to GIF, 150 pixels wide', $result[0], $result[1]);
				$imageProc->IM_commands = array();
				$theFile = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('install') . 'imgs/jesus2_transp.png';
				if (!@is_file($theFile)) {
					die('Error: ' . $theFile . ' was not a file');
				}
				$imageProc->imageMagickConvert_forceFileNameBody = 'scale_png';
				$fileInfo = $imageProc->imageMagickConvert($theFile, 'png', '150', '', '', '', array(), TRUE);
				$result = $this->displayTwinImage($fileInfo[3], $imageProc->IM_commands);
				$this->message($headCode, 'PNG to PNG, 150 pixels wide', $result[0], $result[1]);
				$imageProc->IM_commands = array();
				$theFile = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('install') . 'imgs/jesus2_transp.gif';
				if (!@is_file($theFile)) {
					die('Error: ' . $theFile . ' was not a file');
				}
				$imageProc->imageMagickConvert_forceFileNameBody = 'scale_jpg';
				$fileInfo = $imageProc->imageMagickConvert($theFile, 'jpg', '150', '', '', '', array(), TRUE);
				$result = $this->displayTwinImage($fileInfo[3], $imageProc->IM_commands);
				$this->message($headCode, 'GIF to JPG, 150 pixels wide', $result[0], $result[1]);
			} else {
				$this->message($headCode, 'Test skipped', '
						<p>
							Use of ImageMagick has been disabled in the
							configuration.
							<br />
							Refer to section \'Basic Configuration\' to change
							or review you configuration settings
						</p>
					', 2);
			}
			break;
		case 'combining':
			// Combine
			$headCode = 'Combining images';
			$this->message($headCode, 'Combining images', '
					<p>
						This verifies that the ImageMagick tool,
						\'combine\'/\'composite\', is able to combine two images
						through a grayscale mask.
						<br />
						If the masking seems to work but inverted, that just
						means you\'ll have to make sure the invert flag is set
						(some combination of im_negate_mask/im_imvMaskState)
					</p>
				');
			if ($imActive) {
				$imageProc->IM_commands = array();
				$input = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('install') . 'imgs/greenback.gif';
				$overlay = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('install') . 'imgs/jesus.jpg';
				$mask = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('install') . 'imgs/blackwhite_mask.gif';
				if (!@is_file($input)) {
					die('Error: ' . $input . ' was not a file');
				}
				if (!@is_file($overlay)) {
					die('Error: ' . $overlay . ' was not a file');
				}
				if (!@is_file($mask)) {
					die('Error: ' . $mask . ' was not a file');
				}
				$output = $imageProc->tempPath . $imageProc->filenamePrefix . GeneralUtility::shortMD5(($imageProc->alternativeOutputKey . 'combine1')) . '.jpg';
				$imageProc->combineExec($input, $overlay, $mask, $output, TRUE);
				$fileInfo = $imageProc->getImageDimensions($output);
				$result = $this->displayTwinImage($fileInfo[3], $imageProc->IM_commands);
				$this->message($headCode, 'Combine using a GIF mask with only black and white', $result[0], $result[1]);
				// Combine
				$imageProc->IM_commands = array();
				$input = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('install') . 'imgs/combine_back.jpg';
				$overlay = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('install') . 'imgs/jesus.jpg';
				$mask = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('install') . 'imgs/combine_mask.jpg';
				if (!@is_file($input)) {
					die('Error: ' . $input . ' was not a file');
				}
				if (!@is_file($overlay)) {
					die('Error: ' . $overlay . ' was not a file');
				}
				if (!@is_file($mask)) {
					die('Error: ' . $mask . ' was not a file');
				}
				$output = $imageProc->tempPath . $imageProc->filenamePrefix . GeneralUtility::shortMD5(($imageProc->alternativeOutputKey . 'combine2')) . '.jpg';
				$imageProc->combineExec($input, $overlay, $mask, $output, TRUE);
				$fileInfo = $imageProc->getImageDimensions($output);
				$result = $this->displayTwinImage($fileInfo[3], $imageProc->IM_commands);
				$this->message($headCode, 'Combine using a JPG mask with graylevels', $result[0], $result[1]);
			} else {
				$this->message($headCode, 'Test skipped', '
						<p>
							Use of ImageMagick has been disabled in the
							configuration.
							<br />
							Refer to section \'Basic Configuration\' to change
							or review you configuration settings
						</p>
					', 2);
			}
			break;
		case 'gdlib':
			// GDLibrary
			$headCode = 'GDLib';
			$this->message($headCode, 'Testing GDLib', '
					<p>
						This verifies that the GDLib installation works properly.
					</p>
				');
			if ($gdActive) {
				// GD with box
				$imageProc->IM_commands = array();
				$im = imagecreatetruecolor(170, 136);
				$Bcolor = ImageColorAllocate($im, 0, 0, 0);
				ImageFilledRectangle($im, 0, 0, 170, 136, $Bcolor);
				$workArea = array(0, 0, 170, 136);
				$conf = array(
					'dimensions' => '10,50,150,36',
					'color' => 'olive'
				);
				$imageProc->makeBox($im, $conf, $workArea);
				$output = $imageProc->tempPath . $imageProc->filenamePrefix . GeneralUtility::shortMD5('GDbox') . '.' . $imageProc->gifExtension;
				$imageProc->ImageWrite($im, $output);
				$fileInfo = $imageProc->getImageDimensions($output);
				$result = $this->displayTwinImage($fileInfo[3], $imageProc->IM_commands);
				$this->message($headCode, 'Create simple image', $result[0], $result[1]);
				// GD from image with box
				$imageProc->IM_commands = array();
				$input = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('install') . 'imgs/jesus.' . $imageProc->gifExtension;
				if (!@is_file($input)) {
					die('Error: ' . $input . ' was not a file');
				}
				$im = $imageProc->imageCreateFromFile($input);
				$workArea = array(0, 0, 170, 136);
				$conf = array();
				$conf['dimensions'] = '10,50,150,36';
				$conf['color'] = 'olive';
				$imageProc->makeBox($im, $conf, $workArea);
				$output = $imageProc->tempPath . $imageProc->filenamePrefix . GeneralUtility::shortMD5('GDfromImage+box') . '.' . $imageProc->gifExtension;
				$imageProc->ImageWrite($im, $output);
				$fileInfo = $imageProc->getImageDimensions($output);
				$GDWithBox_filesize = @filesize($output);
				$result = $this->displayTwinImage($fileInfo[3], $imageProc->IM_commands);
				$this->message($headCode, 'Create image from file', $result[0], $result[1]);
				// GD with text
				$imageProc->IM_commands = array();
				$im = imagecreatetruecolor(170, 136);
				$Bcolor = ImageColorAllocate($im, 128, 128, 150);
				ImageFilledRectangle($im, 0, 0, 170, 136, $Bcolor);
				$workArea = array(0, 0, 170, 136);
				$conf = array(
					'iterations' => 1,
					'angle' => 0,
					'antiAlias' => 1,
					'text' => 'HELLO WORLD',
					'fontColor' => '#003366',
					'fontSize' => 18,
					'fontFile' => \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('core') . 'Resources/Private/Font/vera.ttf',
					'offset' => '17,40'
				);
				$conf['BBOX'] = $imageProc->calcBBox($conf);
				$imageProc->makeText($im, $conf, $workArea);
				$output = $imageProc->tempPath . $imageProc->filenamePrefix . GeneralUtility::shortMD5('GDwithText') . '.' . $imageProc->gifExtension;
				$imageProc->ImageWrite($im, $output);
				$fileInfo = $imageProc->getImageDimensions($output);
				$result = $this->displayTwinImage($fileInfo[3], $imageProc->IM_commands);
				$this->message($headCode, 'Render text with TrueType font', $result[0], $result[1]);
				if ($imActive) {
					// extension: GD with text, niceText
					$conf['offset'] = '17,65';
					$conf['niceText'] = 1;
					$imageProc->makeText($im, $conf, $workArea);
					$output = $imageProc->tempPath . $imageProc->filenamePrefix . GeneralUtility::shortMD5('GDwithText-niceText') . '.' . $imageProc->gifExtension;
					$imageProc->ImageWrite($im, $output);
					$fileInfo = $imageProc->getImageDimensions($output);
					$result = $this->displayTwinImage($fileInfo[3], $imageProc->IM_commands, array('Note on \'niceText\':', '\'niceText\' is a concept that tries to improve the antialiasing of the rendered type by actually rendering the textstring in double size on a black/white mask, downscaling the mask and masking the text onto the image through this mask. This involves ImageMagick \'combine\'/\'composite\' and \'convert\'.'));
					$this->message($headCode, 'Render text with TrueType font using \'niceText\' option', '
							<p>
								(If the image has another background color than
								the image above (eg. dark background color with
								light text) then you will have to set
								TYPO3_CONF_VARS[GFX][im_imvMaskState]=1)
							</p>
						' . $result[0], $result[1]);
				} else {
					$this->message($headCode, 'Render text with TrueType font using \'niceText\' option', '
							<p>
								<strong>Test is skipped!</strong>
							</p>
							<p>
								Use of ImageMagick has been disabled in the
								configuration. ImageMagick is needed to generate
								text with the niceText option.
								<br />
								Refer to section \'Basic Configuration\' to
								change or review you configuration settings
							</p>
						', 2);
				}
				if ($imActive) {
					// extension: GD with text, niceText AND shadow
					$conf['offset'] = '17,90';
					$conf['niceText'] = 1;
					$conf['shadow.'] = array(
						'offset' => '2,2',
						'blur' => $imageProc->V5_EFFECTS ? '20' : '90',
						'opacity' => '50',
						'color' => 'black'
					);
					$imageProc->makeShadow($im, $conf['shadow.'], $workArea, $conf);
					$imageProc->makeText($im, $conf, $workArea);
					$output = $imageProc->tempPath . $imageProc->filenamePrefix . GeneralUtility::shortMD5('GDwithText-niceText-shadow') . '.' . $imageProc->gifExtension;
					$imageProc->ImageWrite($im, $output);
					$fileInfo = $imageProc->getImageDimensions($output);
					$result = $this->displayTwinImage($fileInfo[3], $imageProc->IM_commands, array('Note on drop shadows:', 'Drop shadows are done by using ImageMagick to blur a mask through which the drop shadow is generated. The blurring of the mask only works in ImageMagick 4.2.9 and <em>not</em> ImageMagick 5 - which is why you may see a hard and not soft shadow.'));
					$this->message($headCode, 'Render \'niceText\' with a shadow under', '
							<p>
								(This test makes sense only if the above test
								had a correct output. But if so, you may not see
								a soft dropshadow from the third text string as
								you should. In that case you are most likely
								using ImageMagick 5 and should set the flag
								TYPO3_CONF_VARS[GFX][im_v5effects]. However this
								may cost server performance!
							</p>
						' . $result[0], $result[1]);
				} else {
					$this->message($headCode, 'Render \'niceText\' with a shadow under', '
							<p>
								<strong>Test is skipped!</strong>
							</p>
							<p>
								Use of ImageMagick has been disabled in the
								configuration. ImageMagick is needed to generate
								shadows.
								<br />
								Refer to section \'Basic Configuration\' to
								change or review you configuration settings
							</p>
						', 2);
				}
				if ($imageProc->gifExtension == 'gif') {
					$buffer = 20;
					$assess = 'This assessment is based on the filesize from \'Create image from file\' test, which were ' . $GDWithBox_filesize . ' bytes';
					$goodNews = 'If the image was LZW compressed you would expect to have a size of less than 9000 bytes. If you open the image with Photoshop and saves it from Photoshop, you\'ll a filesize like that.<br />The good news is (hopefully) that your [GFX][im_path_lzw] path is correctly set so the gif_compress() function will take care of the compression for you!';
					if ($GDWithBox_filesize < 8784 + $buffer) {
						$msg = '
								<p>
									<strong>
										Your GDLib appears to have LZW compression!
									</strong>
									<br />
									This assessment is based on the filesize
									from \'Create image from file\' test, which
									were ' . $GDWithBox_filesize . ' bytes.
									<br />
									This is a real advantage for you because you
									don\'t need to use ImageMagick for LZW
									compressing. In order to make sure that
									GDLib is used,
									<strong>
										please set the config option
										[GFX][im_path_lzw] to an empty string!
									</strong>
									<br />
									When you disable the use of ImageMagick for
									LZW compressing, you\'ll see that the
									gif_compress() function has a return code of
									\'GD\' (for GDLib) instead of \'IM\' (for
									ImageMagick)
								</p>
							';
					} elseif ($GDWithBox_filesize > 19000) {
						$msg = '
								<p>
									<strong>
										Your GDLib appears to have no
										compression at all!
									</strong>
									<br />
									' . $assess . '
									<br />
									' . $goodNews . '
								</p>
							';
					} else {
						$msg = '
								<p>
									Your GDLib appears to have RLE compression
									<br />
									' . $assess . '
									<br />
									' . $goodNews . '
								</p>
							';
					}
					$this->message($headCode, 'GIF compressing in GDLib', '
						' . $msg . '
						', 1);
				}
			} else {
				$this->message($headCode, 'Test skipped', '
						<p>
							Use of GDLib has been disabled in the configuration.
							<br />
							Refer to section \'Basic Configuration\' to change
							or review you configuration settings
						</p>
					', 2);
			}
			break;
		}
		if ($this->INSTALL['images_type']) {
			// End info
			if ($this->fatalError) {
				$this->message('Info', 'Errors', '
					<p>
						It seems that you had some fatal errors in this test.
						Please make sure that your ImageMagick and GDLib
						settings are correct. Refer to the
						\'Basic Configuration\' section for more information and
						debugging of your settings.
					</p>
				');
			}
			$parseMS = GeneralUtility::milliseconds() - $parseStart;
			$this->message('Info', 'Parsetime', '
				<p>
					' . $parseMS . ' ms
				</p>
			');
		}
		$this->output($this->outputWrapper($this->printAll()));
	}

	/**
	 * Check if image file extension is enabled
	 * Adds error message to the message array
	 *
	 * @param string $ext The image file extension
	 * @param string $headCode The header for the message
	 * @param string $short The short description for the message
	 * @return boolean TRUE if extension is enabled
	 * @todo Define visibility
	 */
	public function isExtensionEnabled($ext, $headCode, $short) {
		if (!GeneralUtility::inList($GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'], $ext)) {
			$this->message($headCode, $short, '
				<p>
					Skipped - extension not in the list of allowed extensions
					([GFX][imagefile_ext]).
				</p>
			', 1);
		} else {
			return 1;
		}
	}

	/**
	 * Generate the HTML after reading and converting images
	 * Displays the verification and the converted image if succeeded
	 * Adds error messages if needed
	 *
	 * @param string $imageFile The file name of the converted image
	 * @param array $IMcommands The ImageMagick commands used
	 * @param string $note Additional note for image operation
	 * @return array Contains content and highest error level
	 * @todo Define visibility
	 */
	public function displayTwinImage($imageFile, $IMcommands = array(), $note = '') {
		// Get the template file
		$templateFile = @file_get_contents((PATH_site . $this->templateFilePath . 'DisplayTwinImage.html'));
		// Get the template part from the file
		$template = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($templateFile, '###TEMPLATE###');
		$content = '';
		$errorLevels = array(-1);
		if ($imageFile) {
			// Get the subpart for the images
			$imageSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($template, '###IMAGE###');
			$verifyFile = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('install') . 'verify_imgs/' . basename($imageFile);
			$destImg = @getImageSize($imageFile);
			$destImgCode = '<img src="' . $this->backPath . '../' . substr($imageFile, strlen(PATH_site)) . '" ' . $destImg[3] . '>';
			$verifyImg = @getImageSize($verifyFile);
			$verifyImgCode = '<img src="' . $this->backPath . \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath('install') . 'verify_imgs/' . basename($verifyFile) . '" ' . $verifyImg[3] . '>';
			clearstatcache();
			$destImg['filesize'] = @filesize($imageFile);
			clearstatcache();
			$verifyImg['filesize'] = @filesize($verifyFile);
			// Define the markers content
			$imageMarkers = array(
				'destWidth' => $destImg[0],
				'destHeight' => $destImg[1],
				'destUrl' => $this->backPath . '../' . substr($imageFile, strlen(PATH_site)),
				'verifyWidth' => $verifyImg[0],
				'verifyHeight' => $verifyImg[1],
				'verifyUrl' => $this->backPath . \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath('install') . 'verify_imgs/' . basename($verifyFile),
				'yourServer' => 'Your server:',
				'yourServerInformation' => GeneralUtility::formatSize($destImg['filesize']) . ', ' . $destImg[0] . 'x' . $destImg[1] . ' pixels',
				'reference' => 'Reference:',
				'referenceInformation' => GeneralUtility::formatSize($verifyImg['filesize']) . ', ' . $verifyImg[0] . 'x' . $verifyImg[1] . ' pixels'
			);
			if ($destImg[0] != $verifyImg[0] || $destImg[1] != $verifyImg[1]) {
				// Get the subpart for the different pixel dimensions message
				$differentPixelDimensionsSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($imageSubpart, '###DIFFERENTPIXELDIMENSIONS###');
				// Define the markers content
				$differentPixelDimensionsMarkers = array(
					'message' => 'Pixel dimension are not equal!'
				);
				// Fill the markers in the subpart
				$differentPixelDimensionsSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($differentPixelDimensionsSubpart, $differentPixelDimensionsMarkers, '###|###', TRUE, FALSE);
				$errorLevels[] = 2;
			}
			// Substitute the subpart for different pixel dimensions message
			$imageSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($imageSubpart, '###DIFFERENTPIXELDIMENSIONS###', $differentPixelDimensionsSubpart);
			if ($note) {
				// Get the subpart for the note
				$noteSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($imageSubpart, '###NOTE###');
				// Define the markers content
				$noteMarkers = array(
					'message' => $note[0],
					'label' => $note[1]
				);
				// Fill the markers in the subpart
				$noteSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($noteSubpart, $noteMarkers, '###|###', TRUE, FALSE);
			}
			// Substitute the subpart for the note
			$imageSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($imageSubpart, '###NOTE###', $noteSubpart);
			if ($this->dumpImCommands && count($IMcommands)) {
				$commands = $this->formatImCmds($IMcommands);
				// Get the subpart for the ImageMagick commands
				$imCommandsSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($imageSubpart, '###IMCOMMANDS###');
				// Define the markers content
				$imCommandsMarkers = array(
					'message' => 'ImageMagick commands executed:',
					'rows' => \TYPO3\CMS\Core\Utility\MathUtility::forceIntegerInRange(count($commands), 2, 10),
					'commands' => htmlspecialchars(implode(LF, $commands))
				);
				// Fill the markers in the subpart
				$imCommandsSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($imCommandsSubpart, $imCommandsMarkers, '###|###', TRUE, FALSE);
			}
			// Substitute the subpart for the ImageMagick commands
			$imageSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($imageSubpart, '###IMCOMMANDS###', $imCommandsSubpart);
			// Fill the markers
			$imageSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($imageSubpart, $imageMarkers, '###|###', TRUE, FALSE);
		} else {
			// Get the subpart when no image has been generated
			$noImageSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($template, '###NOIMAGE###');
			$commands = $this->formatImCmds($IMcommands);
			if (count($commands)) {
				// Get the subpart for the ImageMagick commands
				$commandsSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($noImageSubpart, '###COMMANDSAVAILABLE###');
				// Define the markers content
				$commandsMarkers = array(
					'rows' => \TYPO3\CMS\Core\Utility\MathUtility::forceIntegerInRange(count($commands), 2, 10),
					'commands' => htmlspecialchars(implode(LF, $commands))
				);
				// Fill the markers in the subpart
				$commandsSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($commandsSubpart, $commandsMarkers, '###|###', TRUE, FALSE);
			}
			// Substitute the subpart for the ImageMagick commands
			$noImageSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($noImageSubpart, '###COMMANDSAVAILABLE###', $commandsSubpart);
			// Define the markers content
			$noImageMarkers = array(
				'message' => 'There was no result from the ImageMagick operation',
				'label' => 'Below there\'s a dump of the ImageMagick commands executed:'
			);
			// Fill the markers
			$noImageSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($noImageSubpart, $noImageMarkers, '###|###', TRUE, FALSE);
			$errorLevels[] = 3;
		}
		// Substitute the subpart when image has been generated
		$content = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($template, '###IMAGE###', $imageSubpart);
		// Substitute the subpart when no image has been generated
		$content = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($content, '###NOIMAGE###', $noImageSubpart);
		return array($content, max($errorLevels));
	}

	/**
	 * Format ImageMagick commands for use in HTML
	 *
	 * @param array $arr The ImageMagick commands
	 * @return string The formatted commands
	 * @todo Define visibility
	 */
	public function formatImCmds($arr) {
		$out = array();
		if (is_array($arr)) {
			foreach ($arr as $k => $v) {
				$out[] = $v[1];
				if ($v[2]) {
					$out[] = '   RETURNED: ' . $v[2];
				}
			}
		}
		return $out;
	}

	/**
	 * Generate the menu for the test menu in 'image processing'
	 *
	 * @return string The HTML for the test menu
	 * @todo Define visibility
	 */
	public function imagemenu() {
		// Get the template file
		$template = @file_get_contents((PATH_site . $this->templateFilePath . 'ImageMenu.html'));
		// Get the subpart for the menu
		$menuSubPart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($template, '###MENU###');
		// Get the subpart for the single item in the menu
		$menuItemSubPart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($menuSubPart, '###MENUITEM###');
		$menuitems = array(
			'read' => 'Reading image formats',
			'write' => 'Writing GIF and PNG',
			'scaling' => 'Scaling images',
			'combining' => 'Combining images',
			'gdlib' => 'GD library functions'
		);
		$c = 0;
		$items = array();
		foreach ($menuitems as $k => $v) {
			// Define the markers content
			$markers = array(
				'backgroundColor' => $this->INSTALL['images_type'] == $k ? 'activeMenu' : 'generalTableBackground',
				'url' => htmlspecialchars($this->action . '&TYPO3_INSTALL[images_type]=' . $k . '#imageMenu'),
				'item' => $v
			);
			// Fill the markers in the subpart
			$items[] = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($menuItemSubPart, $markers, '###|###', TRUE, FALSE);
		}
		// Substitute the subpart for the single item in the menu
		$menuSubPart = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($menuSubPart, '###MENUITEM###', implode(LF, $items));
		return $menuSubPart;
	}

	/**********************
	 *
	 * DATABASE analysing
	 *
	 **********************/
	/**
	 * The Database Analyzer
	 *
	 * @return void
	 * @todo Define visibility
	 */
	public function checkTheDatabase() {
		// Getting current tables
		$whichTables = $this->sqlHandler->getListOfTables();
		$headCode = 'Database Analyser';
		$this->message($headCode, 'What is it?', '
			<p>
				In this section you can get an overview of your currently
				selected database compared to sql-files. You can also import
				sql-data directly into the database or upgrade tables from
				earlier versions of TYPO3.
			</p>
		', 0);
		$this->message($headCode, 'Connected to SQL database successfully', '
			<dl id="t3-install-databaseconnected">
				<dt>
					Username:
				</dt>
				<dd>
					' . htmlspecialchars(TYPO3_db_username) . '
				</dd>
				<dt>
					Host:
				</dt>
				<dd>
					' . htmlspecialchars(TYPO3_db_host) . '
				</dd>
			</dl>
		', -1, 1);
		$this->message($headCode, 'Database', '
			<p>
				<strong>' . htmlspecialchars(TYPO3_db) . '</strong> is selected as database.
				<br />
				Has <strong>' . count($whichTables) . '</strong> tables.
			</p>
		', -1, 1);
		// Menu
		$sql_files = array_merge(GeneralUtility::getFilesInDir(PATH_typo3conf, 'sql', 1, 1), array());
		$action_type = $this->INSTALL['database_type'];
		$actionParts = explode('|', $action_type);
		if (count($actionParts) < 2) {
			$action_type = '';
		}
		// Get the template file
		$templateFile = @file_get_contents((PATH_site . $this->templateFilePath . 'CheckTheDatabaseMenu.html'));
		// Get the template part from the file
		$menu = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($templateFile, '###MENU###');
		$menuMarkers = array(
			'action' => $this->action,
			'updateRequiredTables' => 'Update required tables',
			'compare' => 'COMPARE',
			'noticeCmpFileCurrent' => $action_type == 'cmpFile|CURRENT_TABLES' ? ' class="notice"' : '',
			'dumpStaticData' => 'Dump static data',
			'import' => 'IMPORT',
			'noticeImportCurrent' => $action_type == 'import|CURRENT_STATIC' ? ' class="notice"' : '',
			'noticeCmpTca' => $action_type == 'cmpTCA|' ? ' class="notice"' : '',
			'noticeAdminUser' => $action_type == 'adminUser|' ? ' class="notice"' : '',
			'createAdminUser' => 'Create "admin" user',
			'noticeUc' => $action_type == 'UC|' ? ' class="notice"' : '',
			'resetUserPreferences' => 'Reset user preferences',
			'noticeCache' => $action_type == 'cache|' ? ' class="notice"' : '',
			'clearTables' => 'Clear tables'
		);
		// Get the subpart for extra SQL
		$extraSql = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($menu, '###EXTRASQL###');
		$directJump = '';
		$extraSqlFiles = array();
		foreach ($sql_files as $k => $file) {
			$fShortName = substr($file, strlen(PATH_site));
			$spec1 = ($spec2 = '');
			// Define the markers content
			$extraSqlMarkers = array(
				'fileShortName' => $fShortName,
				'fileSize' => GeneralUtility::formatSize(filesize($file)),
				'noticeCmpFile' => $action_type == 'cmpFile|' . $file ? ' class="notice"' : '',
				'file' => rawurlencode($file),
				'noticeImport' => $action_type == 'import|' . $file ? ' class="notice"' : '',
				'specs' => $spec1 . $spec2,
				'noticeView' => $action_type == 'view|' . $file ? ' class="notice"' : '',
				'view' => 'VIEW'
			);
			// Fill the markers in the subpart
			$extraSqlFiles[] = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($extraSql, $extraSqlMarkers, '###|###', TRUE, FALSE);
		}
		// Substitute the subpart for extra SQL
		$menu = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($menu, '###EXTRASQL###', implode(LF, $extraSqlFiles));
		// Fill the markers
		$menu = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($menu, $menuMarkers, '###|###', TRUE, FALSE);
		if ($directJump) {
			if (!$action_type) {
				$this->message($headCode, 'Menu', '
					<script language="javascript" type="text/javascript">
						window.location.href = "' . $directJump . '";
					</script>', 0, 1);
			}
		} else {
			$this->message($headCode, 'Menu', '
				<p>
					From this menu you can select which of the available SQL
					files you want to either compare or import/merge with the
					existing database.
				</p>
				<dl id="t3-install-checkthedatabaseexplanation">
					<dt>
						COMPARE:
					</dt>
					<dd>
						Compares the tables and fields of the current database
						and the selected file. It also offers to \'update\' the
						difference found.
					</dd>
					<dt>
						IMPORT:
					</dt>
					<dd>
						Imports the SQL-dump file into the current database. You
						can either dump the raw file or choose which tables to
						import. In any case, you\'ll see a new screen where you
						must confirm the operation.
					</dd>
					<dt>
						VIEW:
					</dt>
					<dd>
						Shows the content of the SQL-file, limiting characters
						on a single line to a reader-friendly amount.
					</dd>
				</dl>
				<p>
					The SQL-files are selected from typo3conf/ (here you can put
					your own) The SQL-files should be made by the <em>mysqldump</em>
					tool or at least be formatted like that tool would do.
				</p>
			' . $menu, 0, 1);
		}
		if ($action_type) {
			switch ($actionParts[0]) {
			case 'cmpFile':
				$tblFileContent = '';
				$hookObjects = array();
				// Load TCA first
				$this->includeTCA();
				if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/install/mod/class.tx_install.php']['checkTheDatabase'])) {
					foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/install/mod/class.tx_install.php']['checkTheDatabase'] as $classData) {
						/** @var $hookObject Tx_Install_Interfaces_CheckTheDatabaseHook * */
						$hookObject = GeneralUtility::getUserObj($classData);
						if (!$hookObject instanceof \TYPO3\CMS\Install\CheckTheDatabaseHookInterface) {
							throw new \UnexpectedValueException('$hookObject must implement interface TYPO3\\CMS\\Install\\CheckTheDatabaseHookInterface', 1315554770);
						}
						$hookObjects[] = $hookObject;
					}
				}
				if (!strcmp($actionParts[1], 'CURRENT_TABLES')) {
					$tblFileContent = '';
					foreach ($GLOBALS['TYPO3_LOADED_EXT'] as $extKey => $loadedExtConf) {
						if (is_array($loadedExtConf) && $loadedExtConf['ext_tables.sql']) {
							$extensionSqlContent = GeneralUtility::getUrl($loadedExtConf['ext_tables.sql']);
							$tblFileContent .= LF . LF . LF . LF . $extensionSqlContent;
							foreach ($hookObjects as $hookObject) {
								/** @var $hookObject Tx_Install_Interfaces_CheckTheDatabaseHook * */
								$appendableTableDefinitions = $hookObject->appendExtensionTableDefinitions($extKey, $loadedExtConf, $extensionSqlContent, $this->sqlHandler, $this);
								if ($appendableTableDefinitions) {
									$tblFileContent .= $appendableTableDefinitions;
									break;
								}
							}
						}
					}
				} elseif (@is_file($actionParts[1])) {
					$tblFileContent = GeneralUtility::getUrl($actionParts[1]);
				}
				foreach ($hookObjects as $hookObject) {
					/** @var $hookObject Tx_Install_Interfaces_CheckTheDatabaseHook * */
					$appendableTableDefinitions = $hookObject->appendGlobalTableDefinitions($tblFileContent, $this->sqlHandler, $this);
					if ($appendableTableDefinitions) {
						$tblFileContent .= $appendableTableDefinitions;
						break;
					}
				}
				// Add SQL content coming from the caching framework
				$tblFileContent .= \TYPO3\CMS\Core\Cache\Cache::getDatabaseTableDefinitions();
				// Add SQL content coming from the category registry
				$tblFileContent .= \TYPO3\CMS\Core\Category\CategoryRegistry::getInstance()->getDatabaseTableDefinitions();
				if ($tblFileContent) {
					$fileContent = implode(LF, $this->sqlHandler->getStatementArray($tblFileContent, 1, '^CREATE TABLE '));
					$FDfile = $this->sqlHandler->getFieldDefinitions_fileContent($fileContent);
					if (!count($FDfile)) {
						die('Error: There were no \'CREATE TABLE\' definitions in the provided file');
					}
					// Updating database...
					if (is_array($this->INSTALL['database_update'])) {
						$FDdb = $this->sqlHandler->getFieldDefinitions_database();
						$diff = $this->sqlHandler->getDatabaseExtra($FDfile, $FDdb);
						$update_statements = $this->sqlHandler->getUpdateSuggestions($diff);
						$diff = $this->sqlHandler->getDatabaseExtra($FDdb, $FDfile);
						$remove_statements = $this->sqlHandler->getUpdateSuggestions($diff, 'remove');
						$results = array();
						$results[] = $this->sqlHandler->performUpdateQueries($update_statements['clear_table'], $this->INSTALL['database_update']);
						$results[] = $this->sqlHandler->performUpdateQueries($update_statements['add'], $this->INSTALL['database_update']);
						$results[] = $this->sqlHandler->performUpdateQueries($update_statements['change'], $this->INSTALL['database_update']);
						$results[] = $this->sqlHandler->performUpdateQueries($remove_statements['change'], $this->INSTALL['database_update']);
						$results[] = $this->sqlHandler->performUpdateQueries($remove_statements['drop'], $this->INSTALL['database_update']);
						$results[] = $this->sqlHandler->performUpdateQueries($update_statements['create_table'], $this->INSTALL['database_update']);
						$results[] = $this->sqlHandler->performUpdateQueries($remove_statements['change_table'], $this->INSTALL['database_update']);
						$results[] = $this->sqlHandler->performUpdateQueries($remove_statements['drop_table'], $this->INSTALL['database_update']);
						$this->databaseUpdateErrorMessages = array();
						foreach ($results as $resultSet) {
							if (is_array($resultSet)) {
								foreach ($resultSet as $key => $errorMessage) {
									$this->databaseUpdateErrorMessages[$key] = $errorMessage;
								}
							}
						}
					}
					// Init again / first time depending...
					$FDdb = $this->sqlHandler->getFieldDefinitions_database();
					$diff = $this->sqlHandler->getDatabaseExtra($FDfile, $FDdb);
					$update_statements = $this->sqlHandler->getUpdateSuggestions($diff);
					$diff = $this->sqlHandler->getDatabaseExtra($FDdb, $FDfile);
					$remove_statements = $this->sqlHandler->getUpdateSuggestions($diff, 'remove');
					$tLabel = 'Update database tables and fields';
					if ($remove_statements || $update_statements) {
						$formContent = $this->generateUpdateDatabaseForm('get_form', $update_statements, $remove_statements, $action_type);
						$this->message($tLabel, 'Table and field definitions should be updated', '
								<p>
									There seems to be a number of differences
									between the database and the selected
									SQL-file.
									<br />
									Please select which statements you want to
									execute in order to update your database:
								</p>
							' . $formContent, 2);
					} else {
						$this->generateUpdateDatabaseForm('get_form', $update_statements, $remove_statements, $action_type);
						$this->message($tLabel, 'Table and field definitions are OK.', '
								<p>
									The tables and fields in the current
									database corresponds perfectly to the
									database in the selected SQL-file.
								</p>
							', -1);
					}
				}
				break;
			case 'import':
				$tblFileContent = '';
				if (preg_match('/^CURRENT_/', $actionParts[1])) {
					if (!strcmp($actionParts[1], 'CURRENT_TABLES') || !strcmp($actionParts[1], 'CURRENT_TABLES+STATIC')) {
						$tblFileContent = '';
						foreach ($GLOBALS['TYPO3_LOADED_EXT'] as $loadedExtConf) {
							if (is_array($loadedExtConf) && $loadedExtConf['ext_tables.sql']) {
								$tblFileContent .= LF . LF . LF . LF . GeneralUtility::getUrl($loadedExtConf['ext_tables.sql']);
							}
						}
					}
					if (!strcmp($actionParts[1], 'CURRENT_STATIC') || !strcmp($actionParts[1], 'CURRENT_TABLES+STATIC')) {
						foreach ($GLOBALS['TYPO3_LOADED_EXT'] as $loadedExtConf) {
							if (is_array($loadedExtConf) && $loadedExtConf['ext_tables_static+adt.sql']) {
								$tblFileContent .= LF . LF . LF . LF . GeneralUtility::getUrl($loadedExtConf['ext_tables_static+adt.sql']);
							}
						}
					}
					$tblFileContent .= LF . LF . LF . LF . \TYPO3\CMS\Core\Cache\Cache::getDatabaseTableDefinitions();
				} elseif (@is_file($actionParts[1])) {
					$tblFileContent = GeneralUtility::getUrl($actionParts[1]);
				}
				if ($tblFileContent) {
					$tLabel = 'Import SQL dump';
					// Getting statement array from
					$statements = $this->sqlHandler->getStatementArray($tblFileContent, 1);
					list($statements_table, $insertCount) = $this->sqlHandler->getCreateTables($statements, 1);
					// Updating database...
					if ($this->INSTALL['database_import_all']) {
						$r = 0;
						foreach ($statements as $k => $v) {
							$res = $this->getDatabase()->admin_query($v);
							$r++;
						}
						// Make a database comparison because some tables that are defined twice have
						// not been created at this point. This applies to the "pages.*"
						// fields defined in sysext/cms/ext_tables.sql for example.
						$fileContent = implode(LF, $this->sqlHandler->getStatementArray($tblFileContent, 1, '^CREATE TABLE '));
						$FDfile = $this->sqlHandler->getFieldDefinitions_fileContent($fileContent);
						$FDdb = $this->sqlHandler->getFieldDefinitions_database();
						$diff = $this->sqlHandler->getDatabaseExtra($FDfile, $FDdb);
						$update_statements = $this->sqlHandler->getUpdateSuggestions($diff);
						if (is_array($update_statements['add'])) {
							foreach ($update_statements['add'] as $statement) {
								$res = $this->getDatabase()->admin_query($statement);
							}
						}

						$this->message($tLabel, 'Imported ALL', '
								<p>
									Queries: ' . $r . '
								</p>
							', 1, 1);
					} elseif (is_array($this->INSTALL['database_import'])) {
						// Traverse the tables
						foreach ($this->INSTALL['database_import'] as $table => $md5str) {
							if ($md5str == md5($statements_table[$table])) {
								$res = $this->getDatabase()->admin_query('DROP TABLE IF EXISTS ' . $table);
								$res = $this->getDatabase()->admin_query($statements_table[$table]);
								if ($insertCount[$table]) {
									$statements_insert = $this->sqlHandler->getTableInsertStatements($statements, $table);
									foreach ($statements_insert as $k => $v) {
										$res = $this->getDatabase()->admin_query($v);
									}
								}
								$this->message($tLabel, 'Imported \'' . $table . '\'', '
										<p>
											Rows: ' . $insertCount[$table] . '
										</p>
									', 1, 1);
							}
						}
					}
				}
				break;
			case 'view':
				if (@is_file($actionParts[1])) {
					$tLabel = 'Import SQL dump';
					// Getting statement array from
					$fileContent = GeneralUtility::getUrl($actionParts[1]);
					$statements = $this->sqlHandler->getStatementArray($fileContent, 1);
					$maxL = 1000;
					$strLen = strlen($fileContent);
					$maxlen = 200 + ($maxL - \TYPO3\CMS\Core\Utility\MathUtility::forceIntegerInRange(($strLen - 20000) / 100, 0, $maxL));
					if (count($statements)) {
						$out = '';
						foreach ($statements as $statement) {
							$out .= '<p>' . nl2br(htmlspecialchars(GeneralUtility::fixed_lgd_cs($statement, $maxlen))) . '</p>';
						}
					}
					$this->message($tLabel, 'Content of ' . basename($actionParts[1]), $out, 1);
				}
				break;
			case 'adminUser':
				if ($whichTables['be_users']) {
					if (is_array($this->INSTALL['database_adminUser'])) {
						$username = preg_replace('/[^\\da-z._-]/i', '', trim($this->INSTALL['database_adminUser']['username']));
						$pass = trim($this->INSTALL['database_adminUser']['password']);
						$pass2 = trim($this->INSTALL['database_adminUser']['password2']);
						if ($username && $pass && $pass2) {
							if ($pass != $pass2) {
								$this->message($headCode, 'Passwords are not equal!', '
										<p>
											The passwords entered twice are not
											equal.
										</p>
									', 2, 1);
							} else {
								$res = $this->getDatabase()->exec_SELECTquery('uid', 'be_users', 'username=' . $this->getDatabase()->fullQuoteStr($username, 'be_users'));
								if (!$this->getDatabase()->sql_num_rows($res)) {
									$insertFields = array(
										'username' => $username,
										'password' => md5($pass),
										'admin' => 1,
										'uc' => '',
										'fileoper_perms' => 0,
										'tstamp' => $GLOBALS['EXEC_TIME'],
										'crdate' => $GLOBALS['EXEC_TIME']
									);
									$result = $this->getDatabase()->exec_INSERTquery('be_users', $insertFields);
									if ($result) {
										$this->message($headCode, 'User created', '
												<p>
													Username:
													<strong>' . htmlspecialchars($username) . '
													</strong>
												</p>
											', 1, 1);
									} else {
										$this->message($headCode, 'User not created', '
												<p>
													Error:
													<strong>' . htmlspecialchars($this->getDatabase()->sql_error()) . '
													</strong>
												</p>
											', 3, 1);
									}
								} else {
									$this->message($headCode, 'Username not unique!', '
											<p>
												The username,
												<strong>' . htmlspecialchars($username) . '
												</strong>
												, was not unique.
											</p>
										', 2, 1);
								}
							}
						} else {
							$this->message($headCode, 'Missing data!', '
									<p>
										Not all required form fields have been
										filled.
									</p>
								', 2, 1);
						}
					}
					// Get the template file
					$templateFile = @file_get_contents((PATH_site . $this->templateFilePath . 'CheckTheDatabaseAdminUser.html'));
					// Get the template part from the file
					$content = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($templateFile, '###TEMPLATE###');
					// Define the markers content
					$contentMarkers = array(
						'userName' => 'username - unique, no space, lowercase',
						'password' => 'password',
						'repeatPassword' => 'password (repeated)'
					);
					// Fill the markers
					$content = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($content, $contentMarkers, '###|###', TRUE, FALSE);
					$form = $this->getUpdateDbFormWrap($action_type, $content);
					$this->message($headCode, 'Create admin user', '
							<p>
								Enter username and password for a new admin
								user.
								<br />
								You should use this function only if there are
								no admin users in the database, for instance if
								this is a blank database.
								<br />
								After you\'ve created the user, log in and add
								the rest of the user information, like email and
								real name.
							</p>
						' . $form, 0, 1);
				} else {
					$this->message($headCode, 'Required table not in database', '
							<p>
								\'be_users\' must be a table in the database!
							</p>
						', 3, 1);
				}
				break;
			case 'UC':
				if ($whichTables['be_users']) {
					if (!strcmp($this->INSTALL['database_UC'], 1)) {
						$this->getDatabase()->exec_UPDATEquery('be_users', '', array('uc' => ''));
						$this->message($headCode, 'Clearing be_users.uc', '
								<p>
									Done.
								</p>
							', 1);
					}
					// Get the template file
					$templateFile = @file_get_contents((PATH_site . $this->templateFilePath . 'CheckTheDatabaseUc.html'));
					// Get the template part from the file
					$content = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($templateFile, '###TEMPLATE###');
					// Define the markers content
					$contentMarkers = array(
						'clearBeUsers' => 'Clear be_users preferences ("uc" field)'
					);
					// Fill the markers
					$content = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($content, $contentMarkers, '###|###', TRUE, FALSE);
					$form = $this->getUpdateDbFormWrap($action_type, $content);
					$this->message($headCode, 'Clear user preferences', '
							<p>
								If you press this button all backend users from
								the tables be_users will have their user
								preferences cleared (field \'uc\' set to an
								empty string).
								<br />
								This may come in handy in rare cases where that
								configuration may be corrupt.
								<br />
								Clearing this will clear all user settings from
								the \'Setup\' module.
							</p>
						' . $form);
				} else {
					$this->message($headCode, 'Required table not in database', '
							<p>
								\'be_users\' must be a table in the database!
							</p>
						', 3);
				}
				break;
			case 'cache':
				$tableListArr = explode(',', 'cache_pages,cache_pagesection,cache_hash,cache_imagesizes,--div--,sys_log,sys_history,--div--,be_sessions,fe_sessions,fe_session_data' . (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('indexed_search') ? ',--div--,index_words,index_rel,index_phash,index_grlist,index_section,index_fulltext' : '') . (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('tt_products') ? ',--div--,sys_products_orders,sys_products_orders_mm_tt_products' : '') . (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('direct_mail') ? ',--div--,sys_dmail_maillog' : ''));
				if (is_array($this->INSTALL['database_clearcache'])) {
					$qList = array();
					// Get the template file
					$templateFile = @file_get_contents((PATH_site . $this->templateFilePath . 'CheckTheDatabaseCache.html'));
					// Get the subpart for emptied tables
					$emptiedTablesSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($templateFile, '###EMPTIEDTABLES###');
					// Get the subpart for table
					$tableSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($emptiedTablesSubpart, '###TABLE###');
					foreach ($tableListArr as $table) {
						if ($table != '--div--') {
							$table_c = TYPO3_OS == 'WIN' ? strtolower($table) : $table;
							if ($this->INSTALL['database_clearcache'][$table] && $whichTables[$table_c]) {
								$this->getDatabase()->exec_TRUNCATEquery($table);
								// Define the markers content
								$emptiedTablesMarkers = array(
									'tableName' => $table
								);
								// Fill the markers in the subpart
								$qList[] = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($tableSubpart, $emptiedTablesMarkers, '###|###', TRUE, FALSE);
							}
						}
					}
					// Substitute the subpart for table
					$emptiedTablesSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($emptiedTablesSubpart, '###TABLE###', implode(LF, $qList));
					if (count($qList)) {
						$this->message($headCode, 'Clearing cache', '
								<p>
									The following tables were emptied:
								</p>
							' . $emptiedTablesSubpart, 1);
					}
				}
				// Count entries and make checkboxes
				$labelArr = array(
					'cache_pages' => 'Pages',
					'cache_pagesection' => 'TS template related information',
					'cache_hash' => 'Multipurpose md5-hash cache',
					'cache_imagesizes' => 'Cached image sizes',
					'sys_log' => 'Backend action logging',
					'sys_history' => 'Addendum to the sys_log which tracks ALL changes to content through TCE. May become huge by time. Is used for rollback (undo) and the WorkFlow engine.',
					'be_sessions' => 'Backend User sessions',
					'fe_sessions' => 'Frontend User sessions',
					'fe_session_data' => 'Frontend User sessions data',
					'sys_dmail_maillog' => 'Direct Mail log',
					'sys_products_orders' => 'tt_product orders',
					'sys_products_orders_mm_tt_products' => 'relations between tt_products and sys_products_orders'
				);
				$countEntries = array();
				reset($tableListArr);
				// Get the template file
				$templateFile = @file_get_contents((PATH_site . $this->templateFilePath . 'CheckTheDatabaseCache.html'));
				// Get the subpart for table list
				$tableListSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($templateFile, '###TABLELIST###');
				// Get the subpart for the group separator
				$groupSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($tableListSubpart, '###GROUP###');
				// Get the subpart for a single table
				$singleTableSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($tableListSubpart, '###SINGLETABLE###');
				$checkBoxes = array();
				foreach ($tableListArr as $table) {
					if ($table != '--div--') {
						$table_c = TYPO3_OS == 'WIN' ? strtolower($table) : $table;
						if ($whichTables[$table_c]) {
							$countEntries[$table] = $this->getDatabase()->exec_SELECTcountRows('*', $table);
							// Checkboxes:
							if ($this->INSTALL['database_clearcache'][$table] || $_GET['PRESET']['database_clearcache'][$table]) {
								$checked = 'checked="checked"';
							} else {
								$checked = '';
							}
							// Define the markers content
							$singleTableMarkers = array(
								'table' => $table,
								'checked' => $checked,
								'count' => '(' . $countEntries[$table] . ' rows)',
								'label' => $labelArr[$table]
							);
							// Fill the markers in the subpart
							$checkBoxes[] = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($singleTableSubpart, $singleTableMarkers, '###|###', TRUE, FALSE);
						}
					} else {
						$checkBoxes[] = $groupSubpart;
					}
				}
				// Substitute the subpart for the single tables
				$content = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($tableListSubpart, '###SINGLETABLE###', implode(LF, $checkBoxes));
				// Substitute the subpart for the group separator
				$content = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($content, '###GROUP###', '');
				$form = $this->getUpdateDbFormWrap($action_type, $content);
				$this->message($headCode, 'Clear out selected tables', '
						<p>
							Pressing this button will delete all records from
							the selected tables.
						</p>
					' . $form);
				break;
			}
		}
		$this->output($this->outputWrapper($this->printAll()));
	}

	/**
	 * Dispatches updates that shall be executed
	 * during initialization of a fresh TYPO3 instance.
	 *
	 * @return void
	 */
	public function dispatchInitializeUpdates() {
		/** @var $dispatcher \TYPO3\CMS\Install\Service\UpdateDispatcherService */
		$dispatcher = GeneralUtility::makeInstance('TYPO3\CMS\Install\Service\UpdateDispatcherService', $this);
		$dispatcher->dispatchInitializeUpdates();
	}

	/**
	 * Generates update wizard
	 *
	 * @return void
	 * @todo Define visibility
	 */
	public function updateWizard() {
		\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::removeCacheFiles();
		// Forces creation / update of caching framework tables that are needed by some update wizards
		$cacheTablesConfiguration = implode(LF, $this->sqlHandler->getStatementArray(\TYPO3\CMS\Core\Cache\Cache::getDatabaseTableDefinitions(), 1, '^CREATE TABLE '));
		$neededTableDefinition = $this->sqlHandler->getFieldDefinitions_fileContent($cacheTablesConfiguration);
		$currentTableDefinition = $this->sqlHandler->getFieldDefinitions_database();
		$updateTableDefenition = $this->sqlHandler->getDatabaseExtra($neededTableDefinition, $currentTableDefinition);
		$updateStatements = $this->sqlHandler->getUpdateSuggestions($updateTableDefenition);
		if (isset($updateStatements['create_table']) && count($updateStatements['create_table']) > 0) {
			$this->sqlHandler->performUpdateQueries($updateStatements['create_table'], $updateStatements['create_table']);
		}
		if (isset($updateStatements['add']) && count($updateStatements['add']) > 0) {
			$this->sqlHandler->performUpdateQueries($updateStatements['add'], $updateStatements['add']);
		}
		if (isset($updateStatements['change']) && count($updateStatements['change']) > 0) {
			$this->sqlHandler->performUpdateQueries($updateStatements['change'], $updateStatements['change']);
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
	 * @todo Define visibility
	 */
	public function updateWizard_parts($action) {
		$content = '';
		$updateItems = array();
		// Get the template file
		$templateFile = @file_get_contents((PATH_site . $this->templateFilePath . 'UpdateWizardParts.html'));
		switch ($action) {
		case 'checkForUpdate':
			// Get the subpart for check for update
			$checkForUpdateSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($templateFile, '###CHECKFORUPDATE###');
			$title = 'Step 1 - Introduction';
			$updateWizardBoxes = '';
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
			if (!$this->INSTALL['update']) {
				$noUpdatesAvailableSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($getUserInputSubpart, '###NOUPDATESAVAILABLE###');
				$noUpdateMarkers['noUpdates'] = 'No updates selected!';
				$noUpdatesAvailableSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($noUpdatesAvailableSubpart, $noUpdateMarkers, '###|###', TRUE, FALSE);
				break;
			} else {
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
			}
			$content = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($getUserInputSubpart, '###NOUPDATESAVAILABLE###', $noUpdatesAvailableSubpart);
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
			$this->getDatabase()->store_lastBuiltQuery = TRUE;
			foreach ($this->INSTALL['update']['extList'] as $identifier) {
				$className = $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/install']['update'][$identifier];
				$tmpObj = $this->getUpgradeObjInstance($className, $identifier);
				$updateItemsMarkers['identifier'] = $identifier;
				$updateItemsMarkers['title'] = $tmpObj->getTitle();
				// check user input if testing method is available
				if (method_exists($tmpObj, 'checkUserInput') && !$tmpObj->checkUserInput($customOutput)) {
					$customOutput = '';
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
	 * @todo Define visibility
	 */
	public function getUpgradeObjInstance($className, $identifier) {
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
	 * @param 	object	$currentObj		current Upgrade Wizard Object
	 * @return 	mixed	Upgrade Wizard instance or FALSE
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

	/**
	 * Check if at lease one backend admin user has been created
	 *
	 * @return integer Amount of backend users in the database
	 * @todo Define visibility
	 */
	public function isBackendAdminUser() {
		return $this->getDatabase()->exec_SELECTcountRows('uid', 'be_users', 'admin=1');
	}

	/**
	 * Generate the contents for the form for 'Database Analyzer'
	 * when the 'COMPARE' still contains errors
	 *
	 * @param string $type get_form if the form needs to be generated
	 * @param array $arr_update The tables/fields which needs an update
	 * @param array $arr_remove The tables/fields which needs to be removed
	 * @param string $action_type The action type
	 * @return string HTML for the form
	 * @todo Define visibility
	 */
	public function generateUpdateDatabaseForm($type, $arr_update, $arr_remove, $action_type) {
		$content = '';
		switch ($type) {
		case 'get_form':
			$content .= $this->generateUpdateDatabaseForm_checkboxes($arr_update['clear_table'], 'Clear tables (use with care!)', FALSE, TRUE);
			$content .= $this->generateUpdateDatabaseForm_checkboxes($arr_update['add'], 'Add fields');
			$content .= $this->generateUpdateDatabaseForm_checkboxes($arr_update['change'], 'Changing fields', \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('dbal') ? 0 : 1, 0, $arr_update['change_currentValue']);
			$content .= $this->generateUpdateDatabaseForm_checkboxes($arr_remove['change'], 'Remove unused fields (rename with prefix)', $this->setAllCheckBoxesByDefault, 1);
			$content .= $this->generateUpdateDatabaseForm_checkboxes($arr_remove['drop'], 'Drop fields (really!)', $this->setAllCheckBoxesByDefault);
			$content .= $this->generateUpdateDatabaseForm_checkboxes($arr_update['create_table'], 'Add tables');
			$content .= $this->generateUpdateDatabaseForm_checkboxes($arr_remove['change_table'], 'Removing tables (rename with prefix)', $this->setAllCheckBoxesByDefault, 1, $arr_remove['tables_count'], 1);
			$content .= $this->generateUpdateDatabaseForm_checkboxes($arr_remove['drop_table'], 'Drop tables (really!)', $this->setAllCheckBoxesByDefault, 0, $arr_remove['tables_count'], 1);
			$content = $this->getUpdateDbFormWrap($action_type, $content);
			break;
		default:
			break;
		}
		return $content;
	}

	/**
	 * Form wrap for 'Database Analyzer'
	 * when the 'COMPARE' still contains errors
	 *
	 * @param string $action_type The action type
	 * @param string $content The form content
	 * @param string $label The submit button label
	 * @return string HTML of the form
	 * @todo Define visibility
	 */
	public function getUpdateDbFormWrap($action_type, $content, $label = 'Write to database') {
		// Get the template file
		$templateFile = @file_get_contents((PATH_site . $this->templateFilePath . 'GetUpdateDbFormWrap.html'));
		// Get the template part from the file
		$form = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($templateFile, '###TEMPLATE###');
		// Define the markers content
		$formMarkers = array(
			'action' => $this->action,
			'actionType' => htmlspecialchars($action_type),
			'content' => $content,
			'label' => $label
		);
		// Fill the markers
		$form = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($form, $formMarkers, '###|###', TRUE, FALSE);
		return $form;
	}

	/**
	 * Includes TCA
	 *
	 * @return void
	 * @todo Define visibility
	 */
	public function includeTCA() {
		\TYPO3\CMS\Core\Core\Bootstrap::getInstance()->loadExtensionTables(FALSE);
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
	 * @todo Define visibility
	 */
	public function message($head, $short_string = '', $long_string = '', $type = 0) {
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
	 * @todo Define visibility
	 */
	public function printSection($head, $short_string, $long_string, $type) {
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
	 * @todo Define visibility
	 */
	public function printAll() {
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
	 * @todo Define visibility
	 */
	public function outputWrapper($content) {
		// Get the template file
		if (!$this->passwordOK) {
			$this->template = @file_get_contents((PATH_site . $this->templateFilePath . 'Install_login.html'));
		} else {
			$this->template = @file_get_contents((PATH_site . $this->templateFilePath . 'Install.html'));
		}
		// Add jQuery to javascript array for output
		$this->javascript[] = '<script type="text/javascript" src="' . GeneralUtility::createVersionNumberedFilename('../contrib/jquery/jquery-1.9.1.min.js') . '"></script>';
		// Add JS functions for output
		$this->javascript[] = '<script type="text/javascript" src="' . GeneralUtility::createVersionNumberedFilename('../sysext/install/Resources/Public/Javascript/install.js') . '"></script>';
		// Include the default stylesheets
		$this->stylesheets[] = '<link rel="stylesheet" type="text/css" href="' . GeneralUtility::createVersionNumberedFilename(($this->backPath . 'sysext/install/Resources/Public/Stylesheets/reset.css')) . '" />';
		$this->stylesheets[] = '<link rel="stylesheet" type="text/css" href="' . GeneralUtility::createVersionNumberedFilename(($this->backPath . 'sysext/install/Resources/Public/Stylesheets/general.css')) . '" />';
		// Get the browser info
		$browserInfo = \TYPO3\CMS\Core\Utility\ClientUtility::getBrowserInfo(GeneralUtility::getIndpEnv('HTTP_USER_AGENT'));
		// Add the stylesheet for Internet Explorer
		if ($browserInfo['browser'] === 'msie') {
			// IE7
			if (intval($browserInfo['version']) === 7) {
				$this->stylesheets[] = '<link rel="stylesheet" type="text/css" href="' . GeneralUtility::createVersionNumberedFilename(($this->backPath . 'sysext/install/Resources/Public/Stylesheets/ie7.css')) . '" />';
			}
		}
		// Include the stylesheets based on screen
		if ($this->passwordOK) {
			$this->stylesheets[] = '<link rel="stylesheet" type="text/css" href="' . GeneralUtility::createVersionNumberedFilename(($this->backPath . 'sysext/install/Resources/Public/Stylesheets/install.css')) . '" />';
		} else {
			$this->stylesheets[] = '<link rel="stylesheet" type="text/css" href="' . GeneralUtility::createVersionNumberedFilename(($this->backPath . 'sysext/install/Resources/Public/Stylesheets/install.css')) . '" />';
			$this->stylesheets[] = '<link rel="stylesheet" type="text/css" href="' . GeneralUtility::createVersionNumberedFilename(($this->backPath . 'sysext/install/Resources/Public/Stylesheets/install_login.css')) . '" />';
		}
		// Define the markers content
		$this->markers['headTitle'] = '
			TYPO3 ' . TYPO3_version . '
			Install Tool on site: ' . htmlspecialchars($GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename']) . '
		';
		$this->markers['title'] = 'TYPO3 ' . TYPO3_version;
		$this->markers['javascript'] = implode(LF, $this->javascript);
		$this->markers['stylesheets'] = implode(LF, $this->stylesheets);
		$this->markers['llErrors'] = 'The following errors occured';
		$this->markers['copyright'] = $this->copyright();
		$this->markers['charset'] = 'utf-8';
		$this->markers['backendUrl'] = '../index.php';
		$this->markers['backend'] = 'Backend admin';
		$this->markers['frontendUrl'] = '../../index.php';
		$this->markers['frontend'] = 'Frontend website';
		$this->markers['metaCharset'] = 'Content-Type" content="text/html; charset=';
		$this->markers['metaCharset'] .= 'utf-8';
		// Add the error messages
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
		$this->template = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($this->template, $this->markers, '###|###', TRUE, FALSE);
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
	 * @todo Define visibility
	 */
	public function output($content) {
		header('Content-Type: text/html; charset=utf-8');
		echo $content;
	}

	/**
	 * Generates the main menu
	 *
	 * @return string HTML of the main menu
	 * @todo Define visibility
	 */
	public function menu() {
		if (!$this->passwordOK) {
			return;
		}
		$c = 0;
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
	 * @todo Define visibility
	 */
	public function copyright() {
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
	 * Make the url of the script according to type, step
	 *
	 * @param string $type The type
	 * @return string The url
	 * @todo Define visibility
	 */
	public function setScriptName($type) {
		$value = $this->scriptSelf . '?TYPO3_INSTALL[type]=' . $type;
		return $value;
	}

	/**
	 * Creates a table which checkboxes for updating database.
	 *
	 * @param array $arr Array of statements (key / value pairs where key is used for the checkboxes)
	 * @param string $label Label for the table.
	 * @param boolean $checked If set, then checkboxes are set by default.
	 * @param boolean $iconDis If set, then icons are shown.
	 * @param array $currentValue Array of "current values" for each key/value pair in $arr. Shown if given.
	 * @param boolean $cVfullMsg If set, will show the prefix "Current value" if $currentValue is given.
	 * @return string HTML table with checkboxes for update. Must be wrapped in a form.
	 * @todo Define visibility
	 */
	public function generateUpdateDatabaseForm_checkboxes($arr, $label, $checked = 1, $iconDis = 0, $currentValue = array(), $cVfullMsg = 0) {
		$out = array();
		$tableId = uniqid('table');
		$templateMarkers = array();
		if (is_array($arr)) {
			// Get the template file
			$templateFile = @file_get_contents((PATH_site . $this->templateFilePath . 'GenerateUpdateDatabaseFormCheckboxes.html'));
			// Get the template part from the file
			$content = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($templateFile, '###TEMPLATE###');
			// Define the markers content
			$templateMarkers = array(
				'label' => $label,
				'tableId' => $tableId
			);
			// Select/Deselect All
			if (count($arr) > 1) {
				// Get the subpart for multiple tables
				$multipleTablesSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($content, '###MULTIPLETABLES###');
				// Define the markers content
				$multipleTablesMarkers = array(
					'label' => $label,
					'tableId' => $tableId,
					'checked' => $checked ? ' checked="checked"' : '',
					'selectAllId' => 't3-install-' . $tableId . '-checkbox',
					'selectDeselectAll' => 'select/deselect all'
				);
				// Fill the markers in the subpart
				$multipleTablesSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($multipleTablesSubpart, $multipleTablesMarkers, '###|###', TRUE, FALSE);
			}
			// Substitute the subpart for multiple tables
			$content = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($content, '###MULTIPLETABLES###', $multipleTablesSubpart);
			// Rows
			foreach ($arr as $key => $string) {
				// Get the subpart for rows
				$rowsSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($content, '###ROWS###');
				$currentSubpart = '';
				$ico = '';
				$warnings = array();
				// Define the markers content
				$rowsMarkers = array(
					'checkboxId' => 't3-install-db-' . $key,
					'name' => $this->dbUpdateCheckboxPrefix . '[' . $key . ']',
					'checked' => $checked ? 'checked="checked"' : '',
					'string' => htmlspecialchars($string)
				);
				if ($iconDis) {
					$iconMarkers['backPath'] = $this->backPath;
					if (preg_match('/^TRUNCATE/i', $string)) {
						$iconMarkers['iconText'] = '';
						$warnings['clear_table_info'] = 'Clearing the table is sometimes necessary when adding new keys. In case of cache_* tables this should not hurt at all. However, use it with care.';
					} elseif (stristr($string, ' user_')) {
						$iconMarkers['iconText'] = '(USER)';
					} elseif (stristr($string, ' app_')) {
						$iconMarkers['iconText'] = '(APP)';
					} elseif (stristr($string, ' ttx_') || stristr($string, ' tx_')) {
						$iconMarkers['iconText'] = '(EXT)';
					}
					if (!empty($iconMarkers)) {
						// Get the subpart for icons
						$iconSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($content, '###ICONAVAILABLE###');
						// Fill the markers in the subpart
						$iconSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($iconSubpart, $iconMarkers, '###|###', TRUE, TRUE);
					}
				}
				// Substitute the subpart for icons
				$rowsSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($rowsSubpart, '###ICONAVAILABLE###', $iconSubpart);
				if (isset($currentValue[$key])) {
					// Get the subpart for current
					$currentSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($rowsSubpart, '###CURRENT###');
					// Define the markers content
					$currentMarkers = array(
						'message' => !$cVfullMsg ? 'Current value:' : '',
						'value' => $currentValue[$key]
					);
					// Fill the markers in the subpart
					$currentSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($currentSubpart, $currentMarkers, '###|###', TRUE, FALSE);
				}
				// Substitute the subpart for current
				$rowsSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($rowsSubpart, '###CURRENT###', $currentSubpart);
				$errorSubpart = '';
				if (isset($this->databaseUpdateErrorMessages[$key])) {
					// Get the subpart for current
					$errorSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($rowsSubpart, '###ERROR###');
					// Define the markers content
					$currentMarkers = array(
						'errorMessage' => $this->databaseUpdateErrorMessages[$key]
					);
					// Fill the markers in the subpart
					$errorSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($errorSubpart, $currentMarkers, '###|###', TRUE, FALSE);
				}
				// Substitute the subpart for error messages
				$rowsSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($rowsSubpart, '###ERROR###', $errorSubpart);
				// Fill the markers in the subpart
				$rowsSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($rowsSubpart, $rowsMarkers, '###|###', TRUE, FALSE);
				$rows[] = $rowsSubpart;
			}
			// Substitute the subpart for rows
			$content = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($content, '###ROWS###', implode(LF, $rows));
			if (count($warnings)) {
				// Get the subpart for warnings
				$warningsSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($content, '###WARNINGS###');
				$warningItems = array();
				foreach ($warnings as $warning) {
					// Get the subpart for single warning items
					$warningItemSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($warningsSubpart, '###WARNINGITEM###');
					// Define the markers content
					$warningItemMarker['warning'] = $warning;
					// Fill the markers in the subpart
					$warningItems[] = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($warningItemSubpart, $warningItemMarker, '###|###', TRUE, FALSE);
				}
				// Substitute the subpart for single warning items
				$warningsSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($warningsSubpart, '###WARNINGITEM###', implode(LF, $warningItems));
			}
			// Substitute the subpart for warnings
			$content = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($content, '###WARNINGS###', $warningsSubpart);
		}
		// Fill the markers
		$content = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($content, $templateMarkers, '###|###', TRUE, FALSE);
		return $content;
	}

	/**
	 * Returns a newly created TYPO3 encryption key with a given length.
	 *
	 * @param integer $keyLength Desired key length
	 * @TODO: Implement in new step installer
	 * @return string The encryption key
	 */
	public function createEncryptionKey($keyLength = 96) {
		$bytes = GeneralUtility::generateRandomBytes($keyLength);
		return substr(bin2hex($bytes), -96);
	}

	/**
	 * Adds an error message that should be displayed.
	 *
	 * @param string $messageText
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
	public function getDatabase() {
		return $GLOBALS['TYPO3_DB'];
	}
}
?>
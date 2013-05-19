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
	 * Constructor
	 */
	public function __construct() {
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
//		$this->setupGeneral();
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
		foreach ($paths as $v) {
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
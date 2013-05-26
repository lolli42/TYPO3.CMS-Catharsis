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
		'database' => 'Database Analyser',
		'update' => 'Upgrade Wizard',
		'logout' => 'Logout from Install Tool'
	);

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->INSTALL = GeneralUtility::_GP('TYPO3_INSTALL');

		$this->redirect_url = GeneralUtility::sanitizeLocalUrl(GeneralUtility::_GP('redirect_url'));
		$this->INSTALL['type'] = '';
		if ($_GET['TYPO3_INSTALL']['type']) {
			$allowedTypes = array(
				'database',
				'update',
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
			$this->session = GeneralUtility::makeInstance('TYPO3\\CMS\\Install\\Session');
		} catch (\Exception $exception) {
			$this->outputErrorAndExit($exception->getMessage());
		}
		// *******************
		// Check authorization
		// *******************
		if (!$this->session->hasSession()) {
			$this->session->startSession();
		}
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
		case 'database':
			/** @var $actionObject \TYPO3\CMS\Install\Action\AbstractAction */
			$actionObject = GeneralUtility::makeInstance('TYPO3\\CMS\\Install\\Action\\DatabaseAnalyzer');
			$actionObject->handle();
			$this->sections = array_merge($this->sections, $actionObject->getSections());
			$this->errorMessages = array_merge($this->errorMessages, $actionObject->getErrorMessages());
			$this->output($this->outputWrapper($this->printAll()));
			break;
		case 'update':
			/** @var $actionObject \TYPO3\CMS\Install\Action\AbstractAction */
			$actionObject = GeneralUtility::makeInstance('TYPO3\\CMS\\Install\\Action\\UpdateWizard');
			$actionObject->handle();
			$this->sections = array_merge($this->sections, $actionObject->getSections());
			$this->errorMessages = array_merge($this->errorMessages, $actionObject->getErrorMessages());
			$this->output($this->outputWrapper($this->printAll()));
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
			$this->output($this->outputWrapper($this->printAll()));
			break;
		}
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
		$markers['copyright'] = '';
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
}
?>
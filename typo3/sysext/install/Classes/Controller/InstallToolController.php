<?php
namespace TYPO3\CMS\Install\Controller;

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
 * Install tool controller, dispatcher class of the install tool.
 *
 * Handles install tool session, login and login form rendering,
 * calls actions that need authentication and handles form tokens.
 */
class InstallToolController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController {

	/**
	 * @var \TYPO3\CMS\Extbase\Object\ObjectManager
	 */
	protected $objectManager = NULL;

	/**
	 * @var array List of valid action names that need authentication
	 */
	protected $authenticationActions = array(
		'welcome',
		'importantActions',
		'systemEnvironment',
		'folderStructure',
		'testSetup',
		'allConfiguration',
	);

	/**
	 * Main dispatch method
	 *
	 * @return void
	 */
	public function dispatch() {
		$this->earlyExitIfInstallToolPasswordIsNotSetOrEmpty();
		$this->loadBaseExtensions();

		/** @var \TYPO3\CMS\Extbase\Object\ObjectManager $objectManager */
		$objectManager = GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager');
		$this->objectManager = $objectManager;

		/** @var \TYPO3\CMS\Install\Session $session */
		$session = $this->objectManager->get('TYPO3\\CMS\\Install\\Session');

		if (!$session->hasSession()) {
			$session->startSession();
		}

		$content = '';
		$action = $this->getAction();
		$postValues = $this->getPostValues();
		if ($action === 'logout') {
			$enableInstallToolFile = PATH_site . 'typo3conf/ENABLE_INSTALL_TOOL';
			if (is_file($enableInstallToolFile) && trim(file_get_contents($enableInstallToolFile)) !== 'KEEP_FILE') {
				unlink(PATH_typo3conf . 'ENABLE_INSTALL_TOOL');
			}
			/** @var $formProtection \TYPO3\CMS\Core\FormProtection\InstallToolFormProtection */
			$formProtection = \TYPO3\CMS\Core\FormProtection\FormProtectionFactory::get(
				'TYPO3\\CMS\\Core\\FormProtection\\InstallToolFormProtection'
			);
			$formProtection->clean();
			$session->destroySession();
			\TYPO3\CMS\Install\InstallBootstrap::checkEnabledInstallToolOrDie();
		} elseif (!$this->isTokenValid()) {
			// If form protection token is invalid, destroy session start new and redirect to loginForm
			$session->resetSession();
			$session->startSession();
			/** @var $message \TYPO3\CMS\Install\Status\ErrorStatus */
			$message = $this->objectManager->get('TYPO3\\CMS\\Install\\Status\\ErrorStatus');
			$message->setTitle('Invalid form token');
			$message->setMessage(
				'The form protection token was invalid. You have been logged out, please login and try again.'
			);
			$content = $this->loginForm($message);
		} elseif ($session->isExpired()) {
			// Session expired, log out user, start new session, show login form
			$session->resetSession();
			$session->startSession();
			/** @var $message \TYPO3\CMS\Install\Status\ErrorStatus */
			$message = $this->objectManager->get('TYPO3\\CMS\\Install\\Status\\ErrorStatus');
			$message->setTitle('Session expired');
			$message->setMessage(
				'Your Install Tool session has expired. You have been logged out, please login and try again.'
			);
			$content = $this->loginForm($message);
		} elseif ($action === 'login') {
			if (isset($postValues['password'])
				&& md5($postValues['password']) === $GLOBALS['TYPO3_CONF_VARS']['BE']['installToolPassword']
			) {
				$session->setAuthorized();
				$this->sendLoginSuccessfulMail();
				$content = $this->dispatchAuthenticationActions('welcome');
			} else {
				/** @var $message \TYPO3\CMS\Install\Status\ErrorStatus */
				$message = $this->objectManager->get('TYPO3\\CMS\\Install\\Status\\ErrorStatus');
				$message->setTitle('Login failed');
				$message->setMessage('Given password does not match the install tool login password.');
				$this->sendLoginFailedMail();
				$content = $this->loginForm($message);
			}
		} elseif ($session->isAuthorized()) {
			$session->refreshSession();
			// Extend the age of the ENABLE_INSTALL_TOOL file by one hour
			$enableInstallToolFile = PATH_typo3conf . 'ENABLE_INSTALL_TOOL';
			if (is_file($enableInstallToolFile)) {
				@touch($enableInstallToolFile);
			}
			$content = $this->dispatchAuthenticationActions();
		} else {
			$content = $this->loginForm();
		}

		$this->output($content);
	}

	/**
	 * Show login form
	 *
	 * @param \TYPO3\CMS\Install\Status\StatusInterface $message Optional status message from controller
	 * @return string Rendered HTML
	 */
	protected function loginForm(\TYPO3\CMS\Install\Status\StatusInterface $message = NULL) {
		/** @var \TYPO3\CMS\Install\ControllerAction\LoginForm $controllerAction */
		$controllerAction = $this->objectManager->get('TYPO3\\CMS\\Install\\ControllerAction\\LoginForm');
		$controllerAction->setAction('login');
		$controllerAction->setToken($this->generateTokenForAction('login'));
		$controllerAction->setPostValues($this->getPostValues());
		if ($message) {
			$controllerAction->setMessage($message);
		}
		$content = $controllerAction->handle();
		return $content;
	}

	/**
	 * Call an action that needs authentication
	 *
	 * @param string $action Action to call, only set to welcome specifically after successful login
	 * @throws \TYPO3\CMS\Install\Exception
	 * @return string Rendered content
	 */
	protected function dispatchAuthenticationActions($action = NULL) {
		if (!$action) {
			$action = $this->getAction();
			if ($action === '') {
				$action = 'welcome';
			}
		}
		if (!in_array($action, $this->authenticationActions)) {
			throw new \TYPO3\CMS\Install\Exception(
				$action . ' is not a valid authenticated action',
				1369345838
			);
		}
		$actionClass = ucfirst($action);
		/** @var \TYPO3\CMS\Install\ControllerAction\ActionInterface $controllerAction */
		$controllerAction = $this->objectManager->get('TYPO3\\CMS\\Install\\ControllerAction\\' . $actionClass);
		if (!($controllerAction instanceof \TYPO3\CMS\Install\ControllerAction\ActionInterface)) {
			throw new \TYPO3\CMS\Install\Exception(
				$action . ' does non implement ActionInterface',
				1369474308
			);
		}
		$controllerAction->setAction($action);
		$controllerAction->setToken($this->generateTokenForAction($action));
		$controllerAction->setPostValues($this->getPostValues());
		$content = $controllerAction->handle();
		return $content;
	}

	/**
	 * If install tool login mail is set, send a mail for a successful login.
	 * This is currently straight ahead code and could be improved.
	 *
	 * @return void
	 */
	protected function sendLoginSuccessfulMail() {
		$warningEmailAddress = $GLOBALS['TYPO3_CONF_VARS']['BE']['warning_email_addr'];
		if ($warningEmailAddress) {
			$subject = 'Install Tool Login at \'' . $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] . '\'';
			$body =
				'There has been an Install Tool login at TYPO3 site'
				 . ' \'' . $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] . '\''
				. ' (' . GeneralUtility::getIndpEnv('HTTP_HOST') . ')'
				. ' from remote address \'' . GeneralUtility::getIndpEnv('REMOTE_ADDR') . '\''
				. ' (' . GeneralUtility::getIndpEnv('REMOTE_HOST') . ')';
			mail($warningEmailAddress, $subject, $body, 'From: TYPO3 Install Tool WARNING <>');
		}
	}

	/**
	 * If install tool login mail is set, send a mail for a failed login.
	 * This is currently straight ahead code and could be improved.
	 *
	 * @return void
	 */
	protected function sendLoginFailedMail() {
		$formValues = GeneralUtility::_GP('install');
		$warningEmailAddress = $GLOBALS['TYPO3_CONF_VARS']['BE']['warning_email_addr'];
		if ($warningEmailAddress) {
			$subject = 'Install Tool Login ATTEMPT at \'' . $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] . '\'';
			$body =
				'There has been an Install Tool login attempt at TYPO3 site'
				. ' \'' . $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] . '\''
				. ' (' . GeneralUtility::getIndpEnv('HTTP_HOST') . ')'
				. ' The MD5 hash of the last 5 characters of the password tried was \'' . substr(md5($formValues['password']), -5) . '\''
				. ' remote addres was \'' . GeneralUtility::getIndpEnv('REMOTE_ADDR') . '\''
				. ' (' . GeneralUtility::getIndpEnv('REMOTE_HOST') . ')';
			mail($warningEmailAddress, $subject, $body, 'From: TYPO3 Install Tool WARNING <>');
		}
	}

	/**
	 * Require dbal ext_localconf if extension is loaded
	 * Required extbase + fluid ext_localconf
	 * Set caching to null, we do not want dbal, fluid or extbase to cache anything
	 *
	 * @return void
	 */
	protected function loadBaseExtensions() {
		if ($this->isDbalEnabled()) {
			require(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('dbal') . 'ext_localconf.php');
			$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['dbal']['backend']
				= 'TYPO3\\CMS\\Core\\Cache\\Backend\\NullBackend';
		}
		require(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('extbase') . 'ext_localconf.php');
		require(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('fluid') . 'ext_localconf.php');

		$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['extbase_datamapfactory_datamap']['backend']
			= 'TYPO3\\CMS\\Core\\Cache\\Backend\\NullBackend';
		$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['extbase_object']['backend']
			= 'TYPO3\\CMS\\Core\\Cache\\Backend\\NullBackend';
		$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['extbase_reflection']['backend']
			= 'TYPO3\\CMS\\Core\\Cache\\Backend\\NullBackend';
		$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['extbase_typo3dbbackend_tablecolumns']['backend']
			= 'TYPO3\\CMS\\Core\\Cache\\Backend\\NullBackend';
		$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['fluid_template']['backend']
			= 'TYPO3\\CMS\\Core\\Cache\\Backend\\NullBackend';

		/** @var $cacheManager \TYPO3\CMS\Core\Cache\CacheManager */
		$cacheManager = $GLOBALS['typo3CacheManager'];
		$cacheManager->setCacheConfigurations($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']);
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
	 * Exit out if there is no install tool password set in LocalConfiguration
	 *
	 * @throws \TYPO3\CMS\Install\Exception
	 * @return void
	 */
	protected function earlyExitIfInstallToolPasswordIsNotSetOrEmpty() {
		if (!isset($GLOBALS['TYPO3_CONF_VARS']['BE']['installToolPassword'])
			|| strlen($GLOBALS['TYPO3_CONF_VARS']['BE']['installToolPassword']) === 0
		) {
			throw new \TYPO3\CMS\Install\Exception(
				'installToolPassword is empty or not set',
				1369165360
			);
		}
	}

	/**
	 * Output content
	 *
	 * @param string $content Content to output
	 */
	protected function output($content = '') {
		header('Content-Type: text/html; charset=utf-8');
		header('Cache-Control: no-cache, must-revalidate');
		header('Pragma: no-cache');
		echo $content;
		die;
	}

	/**
	 * Generate token for specific action
	 *
	 * @param string $action Action name
	 * @return string Form protection token
	 * @throws \TYPO3\CMS\Install\Exception
	 */
	protected function generateTokenForAction($action = NULL) {
		if (!$action) {
			$action = $this->getAction();
		}
		if ($action === '') {
			throw new \TYPO3\CMS\Install\Exception(
				'Token must have a valid action name',
				1369326592
			);
		}
		/** @var $formProtection \TYPO3\CMS\Core\FormProtection\InstallToolFormProtection */
		$formProtection = \TYPO3\CMS\Core\FormProtection\FormProtectionFactory::get(
			'TYPO3\\CMS\\Core\\FormProtection\\InstallToolFormProtection'
		);
		return $formProtection->generateToken('installTool', $action);
	}

	/**
	 * Use form protection API to find out if protected POST forms are ok.
	 *
	 * @throws \TYPO3\CMS\Install\Exception
	 * @return boolean TRUE if token is valid or not needed, FALSE if token validation failed
	 */
	protected function isTokenValid() {
		$postValues = $this->getPostValues();
		$result = FALSE;
		if (count($postValues) > 0) {
			// A token must be given as soon as there is POST data
			if (isset($postValues['token'])) {
				/** @var $formProtection \TYPO3\CMS\Core\FormProtection\InstallToolFormProtection */
				$formProtection = \TYPO3\CMS\Core\FormProtection\FormProtectionFactory::get(
					'TYPO3\\CMS\\Core\\FormProtection\\InstallToolFormProtection'
				);
				$action = $this->getAction();
				if ($action === '') {
					throw new \TYPO3\CMS\Install\Exception(
						'Token can be checked for valid actions only',
						1369326593
					);
				}
				$result = $formProtection->validateToken($postValues['token'], 'installTool', $action);
			}
		} else {
			$result = TRUE;
		}
		return $result;
	}

	/**
	 * Get POST form values of install tool
	 *
	 * @return array
	 */
	protected function getPostValues() {
		$postValues = GeneralUtility::_POST('install');
		if (!is_array($postValues)) {
			$postValues = array();
		}
		return $postValues;
	}

	/**
	 * Retrieve parameter from GET or POST and sanitize
	 *
	 * @throws \TYPO3\CMS\Install\Exception
	 * @return string Empty string if no action is given or sanitized action string
	 */
	protected function getAction() {
		$formValues = GeneralUtility::_GP('install');
		$action = '';
		if (isset($formValues['action'])) {
			$action = $formValues['action'];
		}
		if ($action !== ''
			&& $action !== 'login'
			&& $action !== 'loginForm'
			&& $action !== 'logout'
			&& !in_array($action, $this->authenticationActions)
		) {
			throw new \TYPO3\CMS\Install\Exception(
				'Invalid action ' . $action,
				1369325619
			);
		}
		return $action;
	}
}

?>
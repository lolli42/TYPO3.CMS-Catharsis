<?php
namespace TYPO3\CMS\Install\ControllerAction;

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

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Handle important actions
 */
class ImportantActions extends AbstractAction implements ActionInterface {

	/**
	 * Handle this action
	 *
	 * @return string content
	 */
	public function handle() {
		$this->initialize();

		if (isset($this->postValues['set']['changeEncryptionKey'])) {
			$this->setNewEncryptionKeyAndLogOut();
		}

		$actionMessages = array();
		if (isset($this->postValues['set']['changeInstallToolPassword'])) {
			$actionMessages[] = $this->changeInstallToolPassword();
		}
		if (isset($this->postValues['set']['changeSiteName'])) {
			$actionMessages[] = $this->changeSiteName();
		}
		if (isset($this->postValues['set']['createAdministrator'])) {
			$actionMessages[] = $this->createAdministrator();
		}
		if (isset($this->postValues['set']['databaseAnalyzerExecute'])) {
			$actionMessages[] = $this->databaseAnalyzerExecute();
		}
		if (isset($this->postValues['set']['databaseAnalyzerAnalyze'])) {
			$actionMessages[] = $this->databaseAnalyzerAnalyze();
		}

		$this->view->assign('actionMessages', $actionMessages);

		$operatingSystem = TYPO3_OS == 'WIN' ? 'Windows' : 'Unix';
		$cgiDetected = (PHP_SAPI == 'fpm-fcgi' || PHP_SAPI == 'cgi' || PHP_SAPI == 'isapi' || PHP_SAPI == 'cgi-fcgi')
			? TRUE
			: FALSE;

		$this->view
			->assign('operatingSystem', $operatingSystem)
			->assign('cgiDetected', $cgiDetected)
			->assign('databaseName', $GLOBALS['TYPO3_CONF_VARS']['DB']['database'])
			->assign('databaseUsername', $GLOBALS['TYPO3_CONF_VARS']['DB']['username'])
			->assign('databaseHost', $GLOBALS['TYPO3_CONF_VARS']['DB']['host'])
			->assign('databasePort', $GLOBALS['TYPO3_CONF_VARS']['DB']['port'])
			->assign('databaseNumberOfTables', count($this->getDatabase()->admin_get_tables()));

		return $this->view->render();
	}


	/**
	 * Set new password if requested
	 *
	 * @return \TYPO3\CMS\Install\Status\StatusInterface
	 */
	protected function changeInstallToolPassword() {
		$values = $this->postValues['values'];
		if ($values['newInstallToolPassword'] !== $values['newInstallToolPasswordCheck']) {
			/** @var $message \TYPO3\CMS\Install\Status\StatusInterface */
			$message = $this->objectManager->get('TYPO3\\CMS\\Install\\Status\\ErrorStatus');
			$message->setTitle('Install tool password not changed');
			$message->setMessage('Given passwords do not match.');
		} elseif (strlen($values['newInstallToolPassword']) < 8) {
			/** @var $message \TYPO3\CMS\Install\Status\StatusInterface */
			$message = $this->objectManager->get('TYPO3\\CMS\\Install\\Status\\ErrorStatus');
			$message->setTitle('Install tool password not changed');
			$message->setMessage('Given passwords must be a least eight characters long.');
		} else {
			/** @var \TYPO3\CMS\Core\Configuration\ConfigurationManager $configurationManager */
			$configurationManager = $this->objectManager->get('TYPO3\\CMS\\Core\\Configuration\\ConfigurationManager');
			$configurationManager->setLocalConfigurationValueByPath('BE/installToolPassword', md5($values['newInstallToolPassword']));
			/** @var $message \TYPO3\CMS\Install\Status\StatusInterface */
			$message = $this->objectManager->get('TYPO3\\CMS\\Install\\Status\\OkStatus');
			$message->setTitle('Install tool password changed');
		}
		return $message;
	}

	/**
	 * Set new site name
	 *
	 * @return \TYPO3\CMS\Install\Status\StatusInterface
	 */
	protected function changeSiteName() {
		$values = $this->postValues['values'];
		if (isset($values['newSiteName']) && strlen($values['newSiteName']) > 0) {
			/** @var \TYPO3\CMS\Core\Configuration\ConfigurationManager $configurationManager */
			$configurationManager = $this->objectManager->get('TYPO3\\CMS\\Core\\Configuration\\ConfigurationManager');
			$configurationManager->setLocalConfigurationValueByPath('SYS/sitename', $values['newSiteName']);
			/** @var $message \TYPO3\CMS\Install\Status\StatusInterface */
			$message = $this->objectManager->get('TYPO3\\CMS\\Install\\Status\\OkStatus');
			$message->setTitle('Site name changed');
			$this->view->assign('siteName', $values['newSiteName']);
		} else {
			/** @var $message \TYPO3\CMS\Install\Status\StatusInterface */
			$message = $this->objectManager->get('TYPO3\\CMS\\Install\\Status\\ErrorStatus');
			$message->setTitle('Site name not changed');
			$message->setMessage('Site name must be at least one character long.');
		}
		return $message;
	}

	/**
	 * Set new encryption key
	 *
	 * @return void
	 */
	protected function setNewEncryptionKeyAndLogOut() {
		$newKey = \TYPO3\CMS\Core\Utility\GeneralUtility::getRandomHexString(96);
		/** @var \TYPO3\CMS\Core\Configuration\ConfigurationManager $configurationManager */
		$configurationManager = $this->objectManager->get('TYPO3\\CMS\\Core\\Configuration\\ConfigurationManager');
		$configurationManager->setLocalConfigurationValueByPath('SYS/encryptionKey', $newKey);
		/** @var $formProtection \TYPO3\CMS\Core\FormProtection\InstallToolFormProtection */
		$formProtection = \TYPO3\CMS\Core\FormProtection\FormProtectionFactory::get(
			'TYPO3\\CMS\\Core\\FormProtection\\InstallToolFormProtection'
		);
		$formProtection->clean();
		/** @var \TYPO3\CMS\Install\Session $session */
		$session = $this->objectManager->get('TYPO3\\CMS\\Install\\Session');
		$session->destroySession();
		\TYPO3\CMS\Core\Utility\HttpUtility::redirect('StepInstaller.php?install[context]=' . $this->getContext());
	}

	/**
	 * Create administrator user
	 *
	 * @return \TYPO3\CMS\Install\Status\StatusInterface
	 */
	protected function createAdministrator() {
		$values = $this->postValues['values'];
		$username = preg_replace('/[^\\da-z._]/i', '', trim($values['newUserUsername']));
		$password = $values['newUserPassword'];
		$passwordCheck = $values['newUserPasswordCheck'];

		if (strlen($username) < 1) {
			/** @var $message \TYPO3\CMS\Install\Status\StatusInterface */
			$message = $this->objectManager->get('TYPO3\\CMS\\Install\\Status\\ErrorStatus');
			$message->setTitle('Administrator user not created');
			$message->setMessage('No valid username given.');
		} elseif ($password !== $passwordCheck) {
			/** @var $message \TYPO3\CMS\Install\Status\StatusInterface */
			$message = $this->objectManager->get('TYPO3\\CMS\\Install\\Status\\ErrorStatus');
			$message->setTitle('Administrator user not created');
			$message->setMessage('Passwords do not match.');
		} elseif (strlen($password) < 8) {
			/** @var $message \TYPO3\CMS\Install\Status\StatusInterface */
			$message = $this->objectManager->get('TYPO3\\CMS\\Install\\Status\\ErrorStatus');
			$message->setTitle('Administrator user not created');
			$message->setMessage('Password must be at least eight characters long.');
		} else {
			$database = $this->getDatabase();
			$userExists = $database->exec_SELECTcountRows(
				'uid',
				'be_users',
				'username=' . $database->fullQuoteStr($username, 'be_users')
			);
			if ($userExists) {
				/** @var $message \TYPO3\CMS\Install\Status\StatusInterface */
				$message = $this->objectManager->get('TYPO3\\CMS\\Install\\Status\\ErrorStatus');
				$message->setTitle('Administrator user not created');
				$message->setMessage('A user with username ' . $username . ' exists already.');
			} else {
				// @TODO: Handle saltedpasswords in installer and store password salted in the first place
				$adminUserFields = array(
					'username' => $username,
					'password' => md5($password),
					'admin' => 1,
					'tstamp' => $GLOBALS['EXEC_TIME'],
					'crdate' => $GLOBALS['EXEC_TIME']
				);
				$database->exec_INSERTquery('be_users', $adminUserFields);
				/** @var $message \TYPO3\CMS\Install\Status\StatusInterface */
				$message = $this->objectManager->get('TYPO3\\CMS\\Install\\Status\\OkStatus');
				$message->setTitle('Administrator created');
			}
		}

		return $message;
	}

	/**
	 * Execute database migration
	 *
	 * @return \TYPO3\CMS\Install\Status\StatusInterface
	 */
	protected function databaseAnalyzerExecute() {
		/** @var $message \TYPO3\CMS\Install\Status\StatusInterface */
		$message = $this->objectManager->get('TYPO3\\CMS\\Install\\Status\\OkStatus');
		$message->setTitle('executed');
		return $message;
	}

	/**
	 * "Compare" action of analyzer
	 *
	 * @return \TYPO3\CMS\Install\Status\StatusInterface
	 */
	protected function databaseAnalyzerAnalyze() {
		$this->view->assign('databaseAnalyzerData', 'foo');
		/** @var $message \TYPO3\CMS\Install\Status\StatusInterface */
		$message = $this->objectManager->get('TYPO3\\CMS\\Install\\Status\\OkStatus');
		$message->setTitle('analyzed');
		return $message;
	}
}
?>

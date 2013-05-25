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
 * Test various system setup settings
 */
class TestSetup extends AbstractAction implements ActionInterface {

	/**
	 * Handle this action
	 *
	 * @return string content
	 */
	public function handle() {
		$this->initialize();

//debug($this->postValues);

		$actionMessages = array();
		if (isset($this->postValues['set']['checkMail'])) {
			$actionMessages[] = $this->sendTestMail();
		}

		$this->view->assign('actionMessages', $actionMessages);

		return $this->view->render();
	}

	/**
	 * Send a test mail to specified email address
	 *
	 * @return \TYPO3\CMS\Install\Status\StatusInterface
	 */
	protected function sendTestMail() {
		if (
			!isset($this->postValues['values']['testEmailRecipient'])
			|| !GeneralUtility::validEmail($this->postValues['values']['testEmailRecipient'])
		) {
			/** @var $message \TYPO3\CMS\Install\Status\StatusInterface */
			$message = $this->objectManager->get('TYPO3\\CMS\\Install\\Status\\ErrorStatus');
			$message->setTitle('Mail not sent');
			$message->setMessage('Given address is not a valid email address.');
		} else {
			$recipient = $this->postValues['values']['testEmailRecipient'];
			$mailMessage = $this->objectManager->get('TYPO3\\CMS\\Core\\Mail\\MailMessage');
			$mailMessage
				->addTo($recipient)
				->addFrom('typo3installtool@example.org', 'TYPO3 CMS install tool')
				->setSubject('Test TYPO3 CMS mail delivery')
				->setBody('<html><body>html test content</body></html>')
				->addPart('TEST CONTENT')
				->send();
			$message = $this->objectManager->get('TYPO3\\CMS\\Install\\Status\\OkStatus');
			$message->setTitle('Test mail sent');
			$message->setMessage('Recipient: ' . $recipient);
		}
		return $message;
	}
}
?>

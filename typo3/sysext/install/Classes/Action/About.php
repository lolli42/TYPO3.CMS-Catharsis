<?php
namespace TYPO3\CMS\Install\Action;

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
 * Handle about
 */
class About extends AbstractAction {

	public function handle() {
		$this->changePassword();

		$changeInstallToolPasswordForm = $this->alterPasswordForm();
		$this->message(
			'About',
			'Warning - very important!',
			'
				<p>
					<strong>An unsecured Install Tool presents a security risk.</strong>
					Minimize the risk with the following actions:
				</p>
				<ul>
					<li>
						Change the Install Tool password.
					</li>
					<li>
						Delete the ENABLE_INSTALL_TOOL file in the /typo3conf folder. This can be done
						manually or through User tools &gt; User settings in the backend.
					</li>
					<li>
						For additional security, the /typo3/install/ folder can be
						renamed, deleted, or password protected with a .htaccess file.
					</li>
				</ul>
			' . $changeInstallToolPasswordForm,
			2
		);
		$this->message(
			'About',
			'Using this script',
			''
		);
	}

	/**
	 * Generates the form to alter the password of the Install Tool
	 *
	 * @return string HTML of the form
	 */
	protected function alterPasswordForm() {
		// Get the template file
		$templateFile = @file_get_contents((PATH_site . $this->templateFilePath . 'AlterPasswordForm.html'));
		// Get the template part from the file
		$template = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($templateFile, '###TEMPLATE###');

		/** @var $formProtection \TYPO3\CMS\Core\FormProtection\InstallToolFormProtection */
		$formProtection = \TYPO3\CMS\Core\FormProtection\FormProtectionFactory::get(
			'TYPO3\\CMS\\Core\\FormProtection\\InstallToolFormProtection'
		);
		$formToken = $formProtection->generateToken('installToolPassword', 'change');

		// Define the markers content
		$markers = array(
			'action' => 'index.php?TYPO3_INSTALL[type]=about',
			'enterPassword' => 'Enter new password:',
			'enterAgain' => 'Enter again:',
			'submit' => 'Set new password',
			'formToken' => $formToken
		);
		// Fill the markers
		$content = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($template, $markers, '###|###', TRUE, FALSE);
		return $content;
	}

	/**
	 * Set new password if requested
	 *
	 * @return void
	 */
	protected function changePassword() {
		$formValues = GeneralUtility::_GP('about');
		if (isset($formValues['installToolPassword']) && isset($formValues['installToolPassword_check'])) {
			if (!isset($formValues['formToken'])) {
				$this->errorMessages[] = 'Required security form token not found.';
			} else {
				/** @var $formProtection \TYPO3\CMS\Core\FormProtection\InstallToolFormProtection */
				$formProtection = \TYPO3\CMS\Core\FormProtection\FormProtectionFactory::get(
					'TYPO3\\CMS\\Core\\FormProtection\\InstallToolFormProtection'
				);
				$isTokenValid = $formProtection->validateToken($formValues['formToken'], 'installToolPassword', 'change');
				if ($isTokenValid) {
					if (
						$formValues['installToolPassword'] === $formValues['installToolPassword_check']
						&& strlen($formValues['installToolPassword']) > 1
					) {
						/** @var $configurationManager \TYPO3\CMS\Core\Configuration\ConfigurationManager */
						$configurationManager = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Configuration\\ConfigurationManager');
						$configurationManager->setLocalConfigurationValueByPath('BE/installToolPassword', md5($formValues['installToolPassword']));
					} else {
						$this->errorMessages[] = 'The two passwords did not match or they are not long enough! The password was not changed.';
					}
				}
			}
		}
	}

}
?>

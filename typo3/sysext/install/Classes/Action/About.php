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
			'
				<p>
					There are three primary steps for you to take:
				</p>
				<p>
					<strong>1: Basic Configuration</strong>
					<br />
					In this step your PHP-configuration is checked. If
					there are any settings that will prevent TYPO3 from
					running correctly you\'ll get warnings and errors
					with a description of the problem.
					<br />
					You\'ll have to enter a database username, password
					and hostname. Then you can choose to create a new
					database or select an existing one.
					<br />
					Finally the image processing settings are entered
					and verified and you can choose to let the script
					update the configuration with the suggested settings.
				</p>
				<p>
					<strong>2: Database Analyser</strong>
					<br />
					In this step you can either install a new database
					or update the database from any previous TYPO3
					version.
					<br />
					You can also get an overview of extra/missing
					fields/tables in the database compared to a raw
					sql-file.
					<br />
					The database is also verified against your
					configuration ($TCA) and you can
					even see suggestions to entries in $TCA or new
					fields in the database.
				</p>
				<p>
					<strong>3: Upgrade Wizard</strong>
					<br />
					Here you will find update methods taking care of
					changes to the TYPO3 core which are not backwards
					compatible.
					<br />
					It is recommended to run this wizard after every
					update to make sure everything will still work
					flawlessly.
				</p>
				<p>
					<strong>4: Image Processing</strong>
					<br />
					This step is a visual guide to verify your
					configuration of the image processing software.
					<br />
					You\'ll be presented to a list of images that should
					all match in pairs. If some irregularity appears,
					you\'ll get a warning. Thus you\'re able to track an
					error before you\'ll discover it on your website.
				</p>
				<p>
					<strong>5: All Configuration</strong>
					<br />
					This gives you access to any of the configuration
					options in the TYPO3_CONF_VARS array. Every option
					is also presented with a comment explaining what it
					does.
				</p>
				<p>
					<strong>6: Cleanup</strong>
					<br />
					Here you can clean up the temporary files in typo3temp/
					folder and the tables used for caching of data in
					your database.
				</p>
			'
		);

		$headCode = 'Header legend';
		$this->message(
			$headCode,
			'Notice!',
			'
				<p>
					Indicates that something is important to be aware
					of.
					<br />
					This does <em>not</em> indicate an error.
				</p>
			',
			1
		);
		$this->message(
			$headCode,
			'Just information',
			'
				<p>
					This is a simple message with some information about
					something.
				</p>
			'
		);
		$this->message(
			$headCode,
			'Check was successful',
			'
				<p>
					Indicates that something was checked and returned an
					expected result.
				</p>
			',
			-1
		);
		$this->message(
			$headCode,
			'Warning!',
			'
				<p>
					Indicates that something may very well cause trouble
					and you should definitely look into it before
					proceeding.
					<br />
					This indicates a <em>potential</em> error.
				</p>
			',
			2
		);
		$this->message(
			$headCode,
			'Error!',
			'
				<p>
					Indicates that something is definitely wrong and
					that TYPO3 will most likely not perform as expected
					if this problem is not solved.
					<br />
					This indicates an actual error.
				</p>
			',
			3
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

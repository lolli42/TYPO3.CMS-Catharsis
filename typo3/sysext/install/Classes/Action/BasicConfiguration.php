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
 * Basic configuration
 */
class BasicConfiguration extends AbstractAction {

	/**
	 * @var string Mail send message
	 */
	protected $mailMessage = '';


	public function handle() {
		$this->transferChosenConfigurationValuesToConfigurationFile();
		$this->check_mail();
		$this->message(
			'About configuration',
			'How to configure TYPO3',
			''
		);

		$isPhpCgi = PHP_SAPI == 'fpm-fcgi' || PHP_SAPI == 'cgi' || PHP_SAPI == 'isapi' || PHP_SAPI == 'cgi-fcgi';
		$this->message(
			'System Information',
			'Your system has the following configuration',
			'
				<dl id="systemInformation">
					<dt>OS detected:</dt>
					<dd>' . (TYPO3_OS == 'WIN' ? 'WIN' : 'UNIX') . '</dd>
					<dt>CGI detected:</dt>
					<dd>' . ($isPhpCgi ? 'YES' : 'NO') . '</dd>
					<dt>PATH_thisScript:</dt>
					<dd>' . PATH_thisScript . '</dd>
				</dl>
			'
		);

		$this->checkConfiguration();
		$this->checkExtensions();

		$ext = 'Write configuration';

		$this->message(
			$ext,
			'Very Important: Changing Encryption Key setting',
			'
				<p>
					When you change the setting for the Encryption Key
					you <em>must</em> take into account that a change to
					this value might invalidate temporary information,
					URLs etc.
					<br />
					The problem is solved by <a href="' . htmlspecialchars('index.php?TYPO3_INSTALL[type]=cleanup') . '">clearing the typo3temp/ folder</a>.
					Also make sure to clear the cache_pages table.
				</p>
			',
			1,
			1
		);
		$this->message(
			$ext,
			'Update configuration',
			'
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
				</p>
			' . $this->renderGeneral(),
			0,
			1
		);
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
		// Define the markers content
		$formMarkers['actionUrl'] = 'index.php?TYPO3_INSTALL[type]=config';

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
		$formValues = GeneralUtility::_GP('config');
		$configurationManager = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Configuration\\ConfigurationManager');
		$localConfigurationPathValuePairs = array();
		if (isset($formValues['sitename'])) {
			if (strcmp($configurationManager->getConfigurationValueByPath('SYS/sitename'), $formValues['sitename'])) {
				$localConfigurationPathValuePairs['SYS/sitename'] = $formValues['sitename'];
			}
		}
		if (isset($formValues['encryptionKey'])) {
			if (strcmp($configurationManager->getConfigurationValueByPath('SYS/encryptionKey'), $formValues['encryptionKey'])) {
				$localConfigurationPathValuePairs['SYS/encryptionKey'] = $formValues['encryptionKey'];
				// The session object in this request must use the new encryption key to write to the right session folder
				$GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = $formValues['encryptionKey'];
			}
		}
		if (!empty($localConfigurationPathValuePairs)) {
			$configurationManager->setLocalConfigurationValuesByPathValuePairs($localConfigurationPathValuePairs);
		}
	}

	/**
	 * Checking php extensions, specifically GDLib and Freetype
	 *
	 * @return void
	 */
	protected function checkExtensions() {
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
	 * Returns TRUE if TTF lib is installed.
	 *
	 * @return void
	 */
	protected function checkTrueTypeSupport() {
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
	 * Checking php.ini configuration and set appropriate messages and flags.
	 *
	 * @return void
	 */
	protected function checkConfiguration() {
		$ext = 'php.ini configuration tests';
		$this->message($ext);
		$this->message($ext, 'Mail test', $this->check_mail('get_form'), -1);
	}

	/**
	 * Check if PHP function mail() works
	 *
	 * @param string $cmd If "get_form" then a formfield for the mail-address is shown. If not, it's checked if "check_mail" was in the INSTALL array and if so a test mail is sent to the recipient given.
	 * @return string The mail form if it is requested with get_form
	 */
	protected function check_mail($cmd = '') {
		$out = '';
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
				$mailSentSubpart = '';
				if (!empty($this->mailMessage)) {
					// Get the subpart for the mail is sent message
					$mailSentSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($template, '###MAILSENT###');
				}
				$template = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($template, '###MAILSENT###', $mailSentSubpart);
				// Define the markers content
				$markers = array(
					'message' => $this->mailMessage,
					'enterEmail' => 'Enter the email address',
					'actionUrl' => 'index.php?TYPO3_INSTALL[type]=config#checkMailForm',
					'submit' => 'Send test mail'
				);
				// Fill the markers
				$out .= \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($template, $markers, '###|###', TRUE, TRUE);
				break;
			default:
				$formValues = GeneralUtility::_GP('config');
				if (trim($formValues['check_mail'])) {
					$subject = 'TEST SUBJECT';
					$email = trim($formValues['check_mail']);
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


}
?>

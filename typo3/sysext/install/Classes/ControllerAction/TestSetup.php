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
	 * @var string Absolute path to image folder
	 */
	protected $imageBasePath = '';

	/**
	 * Handle this action
	 *
	 * @return string content
	 */
	public function handle() {
		$this->initialize();

		$this->imageBasePath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('install') . 'Resources/Public/Images/';

		$actionMessages = array();
		if (isset($this->postValues['set']['testMail'])) {
			$actionMessages[] = $this->sendTestMail();
		}
		if (isset($this->postValues['set']['testTrueTypeFontDpi'])) {
			$this->view->assign('trueTypeFontDpiTested', TRUE);
			$actionMessages[] = $this->createTrueTypeFontDpiTestImage();
		}

		if (isset($this->postValues['set']['testConvertImageFormatsToJpg'])) {
			$this->view->assign('convertImageFormatsToJpgTested', TRUE);
			if ($this->isImageMagickEnabledAndConfigured()) {
				$actionMessages[] = $this->convertImageFormatsToJpg();
			} else {
				/** @var \TYPO3\CMS\Install\Status\StatusInterface $message */
				$message = $this->objectManager->get('TYPO3\\CMS\\Install\\Status\\ErrorStatus');
				$message->setTitle('Test image formats tests not executed');
				$message->setMessage('Image handling is disabled or not configured.');
				$actionMessages[] = $message;
			}
		}

		$this->view->assign('actionMessages', $actionMessages);

		$this->view->assign('imageConfiguration', $this->getImageConfiguration());

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

	/**
	 * Create true type font test image
	 *
	 * @return \TYPO3\CMS\Install\Status\StatusInterface
	 */
	protected function createTrueTypeFontDpiTestImage() {
		$image = @imagecreate(300, 50);
		imagecolorallocate($image, 255, 255, 55);
		$textColor = imagecolorallocate($image, 233, 14, 91);
		@imagettftext(
			$image,
			GeneralUtility::freetypeDpiComp(20),
			0,
			10,
			20,
			$textColor,
			\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('core') . 'Resources/Private/Font/vera.ttf',
			'Testing true type support'
		);
		imagegif($image, PATH_site . 'typo3temp/installTool-createTrueTypeFontDpiTestImage.gif');
		$message = $this->objectManager->get('TYPO3\\CMS\\Install\\Status\\OkStatus');
		$message->setTitle('Created true type font dpi test image');
		return $message;
	}

	/**
	 * Create jpg from various image formats using IM / GM
	 *
	 * @return array Test results
	 */
	protected function convertImageFormatsToJpg() {
		$this->setUpDatabaseConnectionMock();
		$imageProcessor = $this->initializeImageProcessor();

		$inputFormatsToTest = array(
			'jpg',
			'gif',
			'png',
			'tif',
			'bmp',
			'pcx',
			'tga',
			'pdf',
			'ai',
		);

		$testResults = array();
		$parseTimeStart = GeneralUtility::milliseconds();
		foreach ($inputFormatsToTest as $formatToTest) {
			$result = array();
			if (!GeneralUtility::inList($GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'], $formatToTest)) {
				/** @var \TYPO3\CMS\Install\Status\StatusInterface $message */
				$message = $this->objectManager->get('TYPO3\\CMS\\Install\\Status\\WarningStatus');
				$message->setTitle('Skipped test');
				$message->setMessage(
					'Handling format ' . $formatToTest . ' must be'
					. ' enabled in TYPO3_CONF_VARS[\'GFX\'][\'imagefile_ext\']'
				);
				$result['error'] = $message;
			} else {
				$imageProcessor->IM_commands = array();

				$inputFile = $this->imageBasePath . 'TestInput/Test.' . $formatToTest;
				$imageProcessor->imageMagickConvert_forceFileNameBody = 'read-' . $formatToTest;
				$imResult = $imageProcessor->imageMagickConvert($inputFile, 'jpg', '170', '', '', '', array(), TRUE);


				$result['format'] = $formatToTest;
				$result['outputFile'] = $imResult[3];
				$result['referenceFile'] = $this->imageBasePath . 'TestReference/Read-' . $formatToTest . '.jpg';
				$result['command'] = $imageProcessor->IM_commands;
			}
			$testResults[] = $result;
		}
		$parseTimeEnd = GeneralUtility::milliseconds();
		$parseTime = $parseTimeEnd - $parseTimeStart;

		$this->view->assign('testResults', $testResults);

		/** @var \TYPO3\CMS\Install\Status\StatusInterface $message */
		$message = $this->objectManager->get('TYPO3\\CMS\\Install\\Status\\OkStatus');
		$message->setTitle('Executed test image formats tests');
		$message->setMessage('Parse time: ' . $parseTime . ' ms');

		return $message;
	}

	/**
	 * Gather image configuration overview
	 *
	 * @return array Result array
	 */
	protected function getImageConfiguration() {
		$result = array();
		$result['imageMagickOrGraphicsMagick'] = $GLOBALS['TYPO3_CONF_VARS']['GFX']['im_version_5'] === 'gm' ? 'gm' : 'im';
		$result['imageMagickEnabled'] =  $GLOBALS['TYPO3_CONF_VARS']['GFX']['im'];
		$result['imageMagickPath'] = $GLOBALS['TYPO3_CONF_VARS']['GFX']['im_path'];
		$result['imageMagickVersion'] = $this->determineImageMagickVersion();
		$result['imageMagick5Effecs'] = $GLOBALS['TYPO3_CONF_VARS']['GFX']['im_v5effects'];
		$result['imageMagickMaskInvert'] = $GLOBALS['TYPO3_CONF_VARS']['GFX']['im_imvMaskState'];
		$result['gdlibEnabled'] = $GLOBALS['TYPO3_CONF_VARS']['GFX']['gdlib'];
		$result['gdlibPng'] = $GLOBALS['TYPO3_CONF_VARS']['GFX']['gdlib_png'];
		$result['freeTypeDpi'] = $GLOBALS['TYPO3_CONF_VARS']['GFX']['TTFdpi'];
		$result['fileFormats'] = $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'];
		return $result;
	}

	/**
	 * Initialize image processor
	 *
	 * @return \TYPO3\CMS\Core\Imaging\GraphicalFunctions Initialized image processor
	 */
	protected function initializeImageProcessor() {
		/** @var \TYPO3\CMS\Core\Imaging\GraphicalFunctions $imageProcessor */
		$imageProcessor = $this->objectManager->get('TYPO3\\CMS\\Core\\Imaging\\GraphicalFunctions');
		$imageProcessor->init();
		$imageProcessor->tempPath = PATH_site . 'typo3temp/';
		$imageProcessor->dontCheckForExistingTempFile = 1;
		$imageProcessor->enable_typo3temp_db_tracking = 0;
		$imageProcessor->filenamePrefix = 'installTool-';
		$imageProcessor->dontCompress = 1;
		$imageProcessor->alternativeOutputKey = 'typo3InstallTest';
		$imageProcessor->noFramePrepended = $GLOBALS['TYPO3_CONF_VARS']['GFX']['im_noFramePrepended'];
		return $imageProcessor;
	}

	/**
	 * Find out if ImageMagick or GraphicsMagick is enabled and set up
	 *
	 * @return bool TRUE if enabled and path is set
	 */
	protected function isImageMagickEnabledAndConfigured() {
		$enabled = $GLOBALS['TYPO3_CONF_VARS']['GFX']['im'];
		$path = $GLOBALS['TYPO3_CONF_VARS']['GFX']['im_path'];
		return $enabled && $path;
	}

	/**
	 * Determine ImageMagick / GraphicsMagick version
	 *
	 * @return string Version
	 */
	protected function determineImageMagickVersion() {
		$command = \TYPO3\CMS\Core\Utility\CommandUtility::imageMagickCommand('identify', '-version');
		\TYPO3\CMS\Core\Utility\CommandUtility::exec($command, $result);
		$string = $result[0];
		list(, $version) = explode('Magick', $string);
		list($version) = explode(' ', trim($version));
		return trim($version);
	}

	/**
	 * Instantiate a dummy instance for $GLOBALS['TYPO3_DB'] to
	 * prevent real database calls
	 *
	 * @return void
	 */
	protected function setUpDatabaseConnectionMock() {
		$database = $this->objectManager->get('TYPO3\\CMS\\Install\\Database\\DatabaseConnectionMock');
		$GLOBALS['TYPO3_DB'] = $database;
	}
}
?>

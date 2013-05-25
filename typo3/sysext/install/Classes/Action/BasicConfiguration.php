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
		$this->checkExtensions();
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
}
?>

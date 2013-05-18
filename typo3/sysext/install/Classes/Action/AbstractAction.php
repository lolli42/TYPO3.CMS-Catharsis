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
 * General purpose action helper methods
 */
abstract class AbstractAction {

	/**
	 * @var string Path to template folder relative to PATH_site
	 */
	protected $templateFilePath = 'typo3/sysext/install/Resources/Private/Templates/';

	/**
	 * @var array List of error messages created by this action
	 */
	protected $errorMessages = array();

	/**
	 * @var array Content sections
	 */
	protected $sections = array();

	/**
	 * Get rendered content sections
	 *
	 * @return array Content sections
	 */
	public function getSections() {
		return $this->sections;
	}

	/**
	 * Get error messages
	 *
	 * @return array Error messages
	 */
	public function getErrorMessages() {
		return $this->errorMessages;
	}

	/**
	 * Handle this step
	 *
	 * @return mixed
	 */
	abstract public function handle();

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
	protected function message($head, $short_string = '', $long_string = '', $type = 0) {
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
	protected function printSection($head, $short_string, $long_string, $type) {
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
}
?>

<?php
namespace TYPO3\CMS\Install\SystemEnvironment;

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

/**
 * Check system environment status
 *
 * @author Christian Kuhn <lolli@schwarzbu.ch>
 */
class Check {

	/**
	 * Constructor
	 */
	public function __construct() {
		require(__DIR__ . '/StatusInterface.php');
		require(__DIR__ . '/AbstractStatus.php');
		require(__DIR__ . '/NoticeStatus.php');
		require(__DIR__ . '/InfoStatus.php');
		require(__DIR__ . '/OkStatus.php');
		require(__DIR__ . '/WarningStatus.php');
		require(__DIR__ . '/ErrorStatus.php');
	}

	/**
	 * Get all status information as array with status objects
	 *
	 * @return array<\TYPO3\CMS\Install\SystemEnvironment\StatusInterface>
	 */
	public function getStatus() {
		$statusArray = array();
		$statusArray[] = $this->checkCurrentDirectoryIsInIncludePath();
		$statusArray[] = $this->checkFileUploadEnabled();
		return $statusArray;
	}

	/**
	 * Checks if current directory (.) is in PHP include path
	 *
	 * @return ErrorStatus|OkStatus
	 */
	protected function checkCurrentDirectoryIsInIncludePath() {
		$includePath = ini_get('include_path');
		$delimiter = PHP_OS === 'WIN' ? ';' : ':';
		$pathArray = $this->trimExplode($delimiter, $includePath);
		if (!in_array(',', $pathArray)) {
			$status = new ErrorStatus();
			$status->setTitle('Current directory (./) is not in include path');
			$status->setMessage(
				' include_path=' . ini_get('include_path') .
				' Normally the current path, \'.\', is included in the' .
				' include_path of PHP. Although TYPO3 does not rely on this,' .
				' it is an unusual setting that may introduce problems for' .
				' some extensions.'
			);
		} else {
			$status = new OkStatus();
			$status->setTitle('Current directory (./) is in include path.');
		}
		return $status;
	}

	/**
	 * Check if file uploads are enabled in PHP
	 *
	 * @return ErrorStatus|OkStatus
	 */
	protected function checkFileUploadEnabled() {
		if (!ini_get('file_uploads')) {
			$status = new ErrorStatus();
			$status->setTitle('File uploads not allowed');
			$status->setMessage(
				'file_uploads=' . ini_get('file_uploads') .
				' TYPO3 uses the ability to upload files from the browser in various cases.' .
				' As long as this flag is disabled, you\'ll not be able to upload files.' .
				' But it doesn\'t end here, because not only are files not accepted by' .
				' the server - ALL content in the forms are discarded and therefore' .
				' nothing at all will be editable if you don\'t set this flag!' .
				' However if you cannot enable fileupload for some reason alternatively' .
				' you change the default form encoding value with \\$TYPO3_CONF_VARS[SYS][form_enctype].'
			);
		} else {
			$status = new OkStatus();
			$status->setTitle('File uploads allowed');
		}
		return $status;
	}

	/**
	 * Helper method to explode a string by delimeter and throw away empty values.
	 *
	 * @param string $delimiter Delimiter string to explode with
	 * @param string $string The string to explode
	 * @param boolean $removeEmptyValues If set, all empty values will be removed in output
	 * @return array Exploded values
	 */
	protected function trimExplode($delimiter, $string, $removeEmptyValues = FALSE) {
		$explodedValues = explode($delimiter, $string);
		$result = array_map('trim', $explodedValues);
		if ($removeEmptyValues) {
			$temp = array();
			foreach ($result as $value) {
				if ($value !== '') {
					$temp[] = $value;
				}
			}
			$result = $temp;
		}
		return $result;
	}

}
?>
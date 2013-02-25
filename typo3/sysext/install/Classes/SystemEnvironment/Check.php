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
		$statusArray[] = $this->checkMaximumFileUploadSize();
		$statusArray[] = $this->checkPostUploadSizeIsHigherOrEqualMaximumFileUploadSize();
		$statusArray[] = $this->checkMemorySettings();
		$statusArray[] = $this->checkPhpVersion();
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
				' include_path=' . implode(' ', $pathArray) .
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
	 * Check maximum file upload size against default value of 10MB
	 *
	 * @return ErrorStatus|OkStatus
	 */
	protected function checkMaximumFileUploadSize() {
		$maximumUploadFilesize = $this->getBytesFromSizeMeasurement(ini_get('upload_max_filesize'));
		if ($maximumUploadFilesize < 1024 * 1024 * 10) {
			$status = new ErrorStatus();
			$status->setTitle('Maximum upload filesize too small');
			$status->setMessage(
				'upload_max_filesize=' . ini_get('upload_max_filesize') .
				' By default TYPO3 supports uploading, copying and moving' .
				' files of sizes up to 10MB (You can alter the TYPO3 defaults' .
				' by the config option TYPO3_CONF_VARS[BE][maxFileSize]).' .
				' Your current value is below this, so at this point, PHP sets' .
				' the limits for uploaded filesizes and not TYPO3.'
			);
		} else {
			$status = new OkStatus();
			$status->setTitle('Maximum file upload size is higher or equal to 10MB');
		}
		return $status;
	}

	/**
	 * Check maximum post upload size correlates with maximum file upload
	 *
	 * @return ErrorStatus|OkStatus
	 */
	protected function checkPostUploadSizeIsHigherOrEqualMaximumFileUploadSize() {
		$maximumUploadFilesize = $this->getBytesFromSizeMeasurement(ini_get('upload_max_filesize'));
		$maximumPostSize = $this->getBytesFromSizeMeasurement(ini_get('post_max_size'));
		if ($maximumPostSize < $maximumUploadFilesize) {
			$status = new ErrorStatus();
			$status->setTitle('Maximum size for POST requests is smaller than max. upload filesize');
			$status->setMessage(
				'upload_max_filesize=' . ini_get('upload_max_filesize') .
				', post_max_size=' . ini_get('post_max_size') .
				' You have defined a maximum size for file uploads which' .
				' exceeds the allowed size for POST requests. Therefore the' .
				' file uploads can not be larger than ' . ini_get('post_max_size')
			);
		} else {
			$status = new OkStatus();
			$status->setTitle('Maximum post upload size correlates with maximum upload file size');
		}
		return $status;
	}

	/**
	 * Check memory settings
	 *
	 * @return ErrorStatus|OkStatus
	 */
	protected function checkMemorySettings() {
		$memoryLimit = $this->getBytesFromSizeMeasurement(ini_get('memory_limit'));
		if ($memoryLimit <= 0) {
			$status = new WarningStatus();
			$status->setTitle('Unlimited memory limit!');
			$status->setMessage(
				'Your webserver is configured to not limit PHP memory usage at all. This is a risk' .
				' and should be avoided in production setup. In general it\'s best practice to limit this' .
				' in the configuration of your webserver. To be safe, ask the system administrator of the' .
				' webserver to raise the limit to something over 64MB'
			);
		} elseif ($memoryLimit < 1024 * 1024 * 32) {
			$status = new ErrorStatus();
			$status->setTitle('Memory limit below 32MB');
			$status->setMessage(
				'memory_limit=' . ini_get('memory_limit') .
				' Your system is configured to enforce a memory limit of PHP scripts lower than 32MB.' .
				' There is nothing else to do than raise the limit. To be safe, ask the system' .
				' administrator of the webserver to raise the limit to 64MB.'
			);
		} elseif ($memoryLimit < 1024 * 1024 * 32) {
			$status = new WarningStatus();
			$status->setTitle('Memory limit below 64MB');
			$status->setMessage(
				'memory_limit=' . ini_get('memory_limit') .
				' Your system is configured to enforce a memory limit of PHP scripts lower than 64MB.' .
				' A slim TYPO3 instance without many extensions will probably work, but you should ' .
				' monitor your system for exhausted messages, especially if using the backend. ' .
				' To be on the safe side, it would be better to raise the PHP memory limit to 64MB or more.'
			);
		} else {
			$status = new OkStatus();
			$status->setTitle('Memory limit equal 64MB or more');
		}
		return $status;
	}

	/**
	 * Check minimum PHP version
	 *
	 * @return ErrorStatus|OkStatus
	 */
	protected function checkPhpVersion() {
		$minimumPhpVersion = '5.3.0';
		$recommendedPhpVersion = '5.3.7';
		$currentPhpVersion = phpversion();
		if (version_compare($currentPhpVersion, $minimumPhpVersion) < 0) {
			$status = new ErrorStatus();
			$status->setTitle('PHP version too low');
			$status->setMessage(
				'Your PHP version ' . $currentPhpVersion . ' is too old. TYPO3 CMS does not run' .
				' with this version. Update to at least PHP ' . $recommendedPhpVersion
			);
		} elseif (version_compare($currentPhpVersion, $recommendedPhpVersion) < 0) {
			$status = new WarningStatus();
			$status->setTitle('PHP version below recommended version');
			$status->setMessage(
				'Your PHP version ' . $currentPhpVersion . ' is below the recommended version' .
				' ' . $recommendedPhpVersion . '. TYPO3 CMS will mostly run with your PHP' .
				' version, but it is not officially supported. Expect some problem with,' .
				' monitor your system for errors and look out for an upgrade, soon.'
			);
		} else {
			$status = new OkStatus();
			$status->setTitle('PHP version is fine');
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

	/**
	 * Helper method to get the bytes value from a measurement string like "100k".
	 *
	 * @param string $measurement The measurement (e.g. "100k")
	 * @return integer The bytes value (e.g. 102400)
	 */
	protected function getBytesFromSizeMeasurement($measurement) {
		$bytes = doubleval($measurement);
		if (stripos($measurement, 'G')) {
			$bytes *= 1024 * 1024 * 1024;
		} elseif (stripos($measurement, 'M')) {
			$bytes *= 1024 * 1024;
		} elseif (stripos($measurement, 'K')) {
			$bytes *= 1024;
		}
		return $bytes;
	}


}
?>
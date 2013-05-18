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
 * Handle php information
 */
class PhpInformation extends AbstractAction {

	public function handle() {
		$headCode = 'PHP information';

		/** @var $sVar array */
		$sVar = GeneralUtility::getIndpEnv('_ARRAY');
		$sVar['CONST: PHP_OS'] = PHP_OS;
		$sVar['CONST: TYPO3_OS'] = TYPO3_OS;
		$sVar['CONST: PATH_thisScript'] = PATH_thisScript;
		$sVar['CONST: php_sapi_name()'] = PHP_SAPI;
		$sVar['OTHER: TYPO3_VERSION'] = TYPO3_version;
		$sVar['OTHER: PHP_VERSION'] = phpversion();
		$sVar['imagecreatefromgif()'] = function_exists('imagecreatefromgif');
		$sVar['imagecreatefrompng()'] = function_exists('imagecreatefrompng');
		$sVar['imagecreatefromjpeg()'] = function_exists('imagecreatefromjpeg');
		$sVar['imagegif()'] = function_exists('imagegif');
		$sVar['imagepng()'] = function_exists('imagepng');
		$sVar['imagejpeg()'] = function_exists('imagejpeg');
		$sVar['imagettftext()'] = function_exists('imagettftext');
		$sVar['OTHER: IMAGE_TYPES'] = function_exists('imagetypes') ? imagetypes() : 0;
		$gE_keys = explode(',', 'SERVER_PORT,SERVER_SOFTWARE,GATEWAY_INTERFACE,SCRIPT_NAME,PATH_TRANSLATED');
		foreach ($gE_keys as $k) {
			$sVar['SERVER: ' . $k] = $_SERVER[$k];
		}
		$gE_keys = explode(',', 'image_processing,gdlib,gdlib_png,im,im_path,im_path_lzw,im_version_5,im_negate_mask,im_imvMaskState,im_combine_filename');
		foreach ($gE_keys as $k) {
			$sVar['T3CV_GFX: ' . $k] = $GLOBALS['TYPO3_CONF_VARS']['GFX'][$k];
		}
		$debugInfo = array(
			'### DEBUG SYSTEM INFORMATION - START ###'
		);
		foreach ($sVar as $kkk => $vvv) {
			$debugInfo[] = str_pad(substr($kkk, 0, 20), 20) . ': ' . $vvv;
		}
		$debugInfo[] = '### DEBUG SYSTEM INFORMATION - END ###';
		// Get the template file
		$templateFile = @file_get_contents((PATH_site . $this->templateFilePath . 'PhpInformation.html'));
		// Get the template part from the file
		$template = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($templateFile, '###TEMPLATE###');
		// Define the markers content
		$markers = array(
			'explanation' => 'Please copy/paste the information from this text field into an email or bug-report as "Debug System Information" whenever you wish to get support or report problems. This information helps others to check if your system has some obvious misconfiguration and you\'ll get your help faster!',
			'debugInfo' => GeneralUtility::formatForTextarea(implode(LF, $debugInfo))
		);
		// Fill the markers
		$content = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($template, $markers, '###|###', TRUE, FALSE);

		// Add the content to the message array
		$this->message($headCode, 'DEBUG information', $content);

		// Start with various server information
		$getEnvArray = array();
		$gE_keys = explode(',', 'QUERY_STRING,HTTP_ACCEPT,HTTP_ACCEPT_ENCODING,HTTP_ACCEPT_LANGUAGE,HTTP_CONNECTION,HTTP_COOKIE,HTTP_HOST,HTTP_USER_AGENT,REMOTE_ADDR,REMOTE_HOST,REMOTE_PORT,SERVER_ADDR,SERVER_ADMIN,SERVER_NAME,SERVER_PORT,SERVER_SIGNATURE,SERVER_SOFTWARE,GATEWAY_INTERFACE,SERVER_PROTOCOL,REQUEST_METHOD,SCRIPT_NAME,PATH_TRANSLATED,HTTP_REFERER,PATH_INFO');
		foreach ($gE_keys as $k) {
			$getEnvArray[$k] = getenv($k);
		}
		$this->message($headCode, 'TYPO3\\CMS\\Core\\Utility\\GeneralUtility::getIndpEnv()', $this->viewArray(GeneralUtility::getIndpEnv('_ARRAY')));
		$this->message($headCode, 'getenv()', $this->viewArray($getEnvArray));
		$this->message($headCode, '_ENV', $this->viewArray($_ENV));
		$this->message($headCode, '_SERVER', $this->viewArray($_SERVER));
		$this->message($headCode, '_COOKIE', $this->viewArray($_COOKIE));
		$this->message($headCode, '_GET', $this->viewArray($_GET));

		// Start with the phpinfo() part
		ob_start();
		phpinfo();
		$contents = explode('<body>', ob_get_contents());
		ob_end_clean();
		$contents = explode('</body>', $contents[1]);
		// Do code cleaning: phpinfo() is not XHTML1.1 compliant
		$phpinfo = str_replace('<font', '<span', $contents[0]);
		$phpinfo = str_replace('</font', '</span', $phpinfo);
		$phpinfo = str_replace('<img border="0"', '<img', $phpinfo);
		$phpinfo = str_replace('<a name=', '<a id=', $phpinfo);

		// Add phpinfo() to the message array
		$this->message($headCode, 'phpinfo()', '
			<div class="phpinfo">
				' . $phpinfo . '
			</div>
		');
	}

	/**
	 * Returns HTML-code, which is a visual representation of a multidimensional array
	 * Returns FALSE if $array_in is not an array
	 *
	 * @param mixed $incomingValue Array to view
	 * @return string HTML output
	 */
	protected function viewArray($incomingValue) {
		$content = '';
		// Get the template file
		$templateFile = @file_get_contents((PATH_site . $this->templateFilePath . 'ViewArray.html'));
		if (is_array($incomingValue) && !empty($incomingValue)) {
			// Get the template part from the file
			$content = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($templateFile, '###TEMPLATE###');
			// Get the subpart for a single item
			$itemSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($content, '###ITEM###');
			$items = array();
			foreach ($incomingValue as $key => $value) {
				if (is_array($value)) {
					$description = $this->viewArray($value);
				} elseif (is_object($value)) {
					$description = get_class($value);
					if (method_exists($value, '__toString')) {
						$description .= ': ' . (string) $value;
					}
				} else {
					if (gettype($value) == 'object') {
						$description = 'Unknown object';
					} else {
						$description = htmlspecialchars((string) $value);
					}
				}
				// Define the markers content
				$itemMarkers = array(
					'key' => htmlspecialchars((string) $key),
					'description' => !empty($description) ? $description : '&nbsp;'
				);
				// Fill the markers in the subpart
				$items[] = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($itemSubpart, $itemMarkers, '###|###', TRUE, FALSE);
			}
			// Substitute the subpart for single item
			$content = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($content, '###ITEM###', implode(LF, $items));
		}
		return $content;
	}
}
?>

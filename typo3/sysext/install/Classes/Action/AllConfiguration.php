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
 * Handle all configuration
 */
class AllConfiguration extends AbstractAction {

	/**
	 * @var \TYPO3\CMS\Core\Configuration\ConfigurationManager
	 */
	protected $configurationManager = NULL;

	/**
	 * @var array Array with comments extracted from DefaultConfiguration.php
	 */
	protected $commentArray = array();

	/**
	 * Handle all configuration
	 *
	 * @return string Rendered configuration
	 */
	public function handle() {
		$this->configurationManager = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Configuration\\ConfigurationManager');

		$default_config_content = GeneralUtility::getUrl($this->configurationManager->getDefaultConfigurationFileLocation());
		$this->commentArray = $this->getDefaultConfigArrayComments($default_config_content);

		$contentFromUpdate = $this->updateLocalConfigurationValues();
		if (strlen($contentFromUpdate)) {
			return $contentFromUpdate;
		} else {
			return $this->renderConfigurationForm();
		}
	}

	/**
	 * Render configuration form
	 *
	 * @return string Rendered main form
	 */
	public function renderConfigurationForm() {
		$content = array();
		$content[] = '<h3>Change configuration values</h3>';
		$content[] = '<form action="index.php?TYPO3_INSTALL[type]=extConfig" method="post">';

		$templateFile = @file_get_contents((PATH_site . $this->templateFilePath . 'GenerateConfigForm.html'));

		$template = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($templateFile, '###TEMPLATE###');
		/** @var $statusUtility \TYPO3\CMS\Install\Status\StatusUtility */
		$statusUtility = GeneralUtility::makeInstance('TYPO3\\CMS\\Install\\Status\\StatusUtility');
		foreach ($GLOBALS['TYPO3_CONF_VARS'] as $section => $va) {

			/** @var $status \TYPO3\CMS\Install\Status\StatusInterface */
			$status = GeneralUtility::makeInstance('TYPO3\\CMS\\Install\\Status\\WarningStatus');
			$status->setTitle('Section: $TYPO3_CONF_VARS[\'' . $section . '\']');
			$content[] = $statusUtility->renderStatusObjectsAsHtml(array($status));

			foreach ($va as $vk => $value) {
				if (isset($GLOBALS['TYPO3_CONF_VARS_extensionAdded'][$section][$vk])) {
					// Don't allow editing stuff which is added by extensions
					// Make sure we fix potentially duplicated entries from older setups
					$potentialValue = str_replace(array('\'.chr(10).\'', '\' . LF . \''), array(LF, LF), $value);
					while (preg_match('/' . preg_quote($GLOBALS['TYPO3_CONF_VARS_extensionAdded'][$section][$vk], '/') . '$/', '', $potentialValue)) {
						$potentialValue = preg_replace('/' . preg_quote($GLOBALS['TYPO3_CONF_VARS_extensionAdded'][$section][$vk], '/') . '$/', '', $potentialValue);
					}
					$value = $potentialValue;
				}
				$textAreaSubpart = '';
				$booleanSubpart = '';
				$textLineSubpart = '';
				$description = trim($this->commentArray[1][$section][$vk]);
				$isTextarea = preg_match('/^(<.*?>)?string \\(textarea\\)/i', $description) ? TRUE : FALSE;
				$doNotRender = preg_match('/^(<.*?>)?string \\(exclude\\)/i', $description) ? TRUE : FALSE;
				if (!is_array($value) && !$doNotRender && (!preg_match('/[' . LF . CR . ']/', $value) || $isTextarea)) {
					if ($isTextarea) {
						// Get the subpart for a textarea
						$textAreaSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($template, '###TEXTAREA###');
						// Define the markers content
						$textAreaMarkers = array(
							'id' => $section . '-' . $vk,
							'name' => 'allConfiguration[' . $section . '][' . $vk . ']',
							'value' => htmlspecialchars(str_replace(array('\'.chr(10).\'', '\' . LF . \''), array(LF, LF), $value))
						);
						// Fill the markers in the subpart
						$textAreaSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($textAreaSubpart, $textAreaMarkers, '###|###', TRUE, FALSE);
					} elseif (preg_match('/^(<.*?>)?boolean/i', $description)) {
						// Get the subpart for a checkbox
						$booleanSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($template, '###BOOLEAN###');
						// Define the markers content
						$booleanMarkers = array(
							'id' => $section . '-' . $vk,
							'name' => 'allConfiguration[' . $section . '][' . $vk . ']',
							'value' => $value && strcmp($value, '0') ? $value : 1,
							'checked' => $value ? 'checked="checked"' : ''
						);
						// Fill the markers in the subpart
						$booleanSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($booleanSubpart, $booleanMarkers, '###|###', TRUE, FALSE);
					} else {
						// Get the subpart for an input text field
						$textLineSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($template, '###TEXTLINE###');
						// Define the markers content
						$textLineMarkers = array(
							'id' => $section . '-' . $vk,
							'name' => 'allConfiguration[' . $section . '][' . $vk . ']',
							'value' => htmlspecialchars($value)
						);
						// Fill the markers in the subpart
						$textLineSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($textLineSubpart, $textLineMarkers, '###|###', TRUE, FALSE);
					}
					// Substitute the subpart for a textarea
					$innerContent = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($template, '###TEXTAREA###', $textAreaSubpart);
					// Substitute the subpart for a checkbox
					$innerContent = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($innerContent, '###BOOLEAN###', $booleanSubpart);
					// Substitute the subpart for an input text field
					$innerContent = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($innerContent, '###TEXTLINE###', $textLineSubpart);
					// Define the markers content
					$markers = array(
						'description' => $description,
						'key' => '[' . $section . '][' . $vk . ']',
						'label' => htmlspecialchars(GeneralUtility::fixed_lgd_cs($value, 40))
					);

					/** @var $message \TYPO3\CMS\Install\Status\StatusInterface */
					$status = GeneralUtility::makeInstance('TYPO3\\CMS\\Install\\Status\\OkStatus');
					$status->setTitle('[' . $vk . ']');
					$content[] = $statusUtility->renderStatusObjectsAsHtml(array($status));

					// Fill the markers
					$content[] = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($innerContent, $markers, '###|###', TRUE, FALSE);
				}
			}
			$content[] = '<hr />';
		}

		$content[] = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($templateFile, '###WRITEBUTTON###');

		$content[] = '</form>';
		return implode(LF, $content);
	}

	/**
	 * Store changed values in LocalConfiguraion
	 *
	 * @return string Rendered status messages of changed values
	 */
	protected function updateLocalConfigurationValues() {
		$content = '';
		$formValues = GeneralUtility::_GP('allConfiguration');
		if (is_array($formValues)) {
			$statusObjects = array();
			$configurationPathValuePairs = array();
			foreach ($formValues as $section => $valueArray) {
				if (is_array($GLOBALS['TYPO3_CONF_VARS'][$section])) {
					foreach ($valueArray as $valueKey => $value) {
						if (isset($GLOBALS['TYPO3_CONF_VARS'][$section][$valueKey])) {
							$description = trim($this->commentArray[1][$section][$valueKey]);
							if (preg_match('/^string \\(textarea\\)/i', $description)) {
								// Force Unix linebreaks in textareas
								$value = str_replace(CR, '', $value);
								// Preserve linebreaks
								$value = str_replace(LF, '\' . LF . \'', $value);
							}
							if (preg_match('/^boolean/i', $description)) {
								// When submitting settings in the Install Tool, values that default to "FALSE" or "TRUE"
								// in EXT:core/Configuration/DefaultConfiguration.php will be sent as "0" resp. "1".
								// Therefore, reset the values to their boolean equivalent.
								if ($GLOBALS['TYPO3_CONF_VARS'][$section][$valueKey] === FALSE && $value === '0') {
									$value = FALSE;
								} elseif ($GLOBALS['TYPO3_CONF_VARS'][$section][$valueKey] === TRUE && $value === '1') {
									$value = TRUE;
								}
							}
							if (strcmp($GLOBALS['TYPO3_CONF_VARS'][$section][$valueKey], $value)) {
								$configurationPathValuePairs[$section . '/' . $valueKey] = $value;

								/** @var $message \TYPO3\CMS\Install\Status\StatusInterface */
								$status = GeneralUtility::makeInstance('TYPO3\\CMS\\Install\\Status\\OkStatus');
								$status->setTitle('$TYPO3_CONF_VARS[\'' . $section . '\'][\'' . $valueKey . '\']');
								$status->setMessage('New value = ' . $value);
								$statusObjects[] = $status;
							}
						}
					}
				}
			}
			if (count($statusObjects)) {
				/** @var $statusUtility \TYPO3\CMS\Install\Status\StatusUtility */
				$statusUtility = GeneralUtility::makeInstance('TYPO3\\CMS\\Install\\Status\\StatusUtility');
				$content = '<h3>Changed configuration values</h3>';
				$content .= $statusUtility->renderStatusObjectsAsHtml($statusObjects);
				$content .= '<hr />';
				$content .= '<form action="index.php?TYPO3_INSTALL[type]=extConfig" method="post">';
				$content .= '<fieldset class="t3-install-form-submit">';
				$content .= '<ol>';
				$content .= '<li>';
				$content .= '<button type="submit">Continue<span class="t3-install-form-button-icon-positive">&nbsp;</span></button>';
				$content .= '</li>';
				$content .= '</ol>';
				$content .= '</fieldset>';
				$content .= '</form>';
				$this->configurationManager->setLocalConfigurationValuesByPathValuePairs($configurationPathValuePairs);
			}
		}
		return $content;
	}

	/**
	 * Make an array of the comments in the EXT:core/Configuration/DefaultConfiguration.php file
	 *
	 * @param string $string The contents of the EXT:core/Configuration/DefaultConfiguration.php file
	 * @param array $mainArray
	 * @param array $commentArray
	 * @return array
	 */
	protected function getDefaultConfigArrayComments($string, $mainArray = array(), $commentArray = array()) {
		$lines = explode(LF, $string);
		$in = 0;
		$mainKey = '';
		foreach ($lines as $lc) {
			$lc = trim($lc);
			if ($in) {
				if (!strcmp($lc, ');')) {
					$in = 0;
				} else {
					if (preg_match('/["\']([[:alnum:]_-]*)["\'][[:space:]]*=>(.*)/i', $lc, $reg)) {
						preg_match('/,[\\t\\s]*\\/\\/(.*)/i', $reg[2], $creg);
						$theComment = trim($creg[1]);
						if (substr(strtolower(trim($reg[2])), 0, 5) == 'array' && !strcmp($reg[1], strtoupper($reg[1]))) {
							$mainKey = trim($reg[1]);
							$mainArray[$mainKey] = $theComment;
						} elseif ($mainKey) {
							$commentArray[$mainKey][$reg[1]] = $theComment;
						}
					}
				}
			}
			if (!strcmp($lc, 'return array(')) {
				$in = 1;
			}
		}
		return array($mainArray, $commentArray);
	}
}
?>

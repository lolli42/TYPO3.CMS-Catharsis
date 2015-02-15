<?php
namespace TYPO3\CMS\Backend\Form\Utility;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Backend\Module\ModuleLoader;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Backend\Utility\IconUtility;
use TYPO3\CMS\Lang\LanguageService;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\DatabaseConnection;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageQueue;
use TYPO3\CMS\Core\Messaging\FlashMessageService;

/**
 * This is a static, internal and intermediate helper class for various
 * FormEngine related tasks.
 *
 * This class was introduced to help disentangling FormEngine and
 * its sub classes. It MUST NOT be used in other extensions and will
 * change or vanish without further notice.
 *
 * @internal
 * @todo: These helpers are target to be dropped if further FormEngine refactoring is done
 */
class FormEngineUtility {

	/**
	 * Returns TSconfig for given table and row
	 *
	 * @param string $table The table name
	 * @param array $row The table row - Must at least contain the "uid" value, even if "NEW..." string.
	 *                   The "pid" field is important as well, negative values will be interpreted as pointing to a record from the same table.
	 * @param string $field Optionally specify the field name as well. In that case the TSconfig for this field is returned.
	 * @return mixed The TSconfig values - probably in an array
	 * @internal
	 */
	static public function getTSconfigForTableRow($table, $row, $field = '') {
		static $cache;
		if (is_null($cache)) {
			$cache = array();
		}
		$cacheIdentifier = $table . ':' . $row['uid'];
		if (!isset($cache[$cacheIdentifier])) {
			$cache[$cacheIdentifier] = BackendUtility::getTCEFORM_TSconfig($table, $row);
		}
		if ($field) {
			return $cache[$cacheIdentifier][$field];
		}
		return $cache[$cacheIdentifier];
	}

	/**
	 * Get icon (for example for selector boxes)
	 *
	 * @param string $icon Icon reference
	 * @return array Array with two values; the icon file reference, the icon file information array (getimagesize())
	 * @internal
	 */
	static public function getIcon($icon) {
		$selIconInfo = FALSE;
		if (substr($icon, 0, 4) == 'EXT:') {
			$file = GeneralUtility::getFileAbsFileName($icon);
			if ($file) {
				$file = PathUtility::stripPathSitePrefix($file);
				$selIconFile = '../' . $file;
				$selIconInfo = @getimagesize((PATH_site . $file));
			} else {
				$selIconFile = '';
			}
		} elseif (substr($icon, 0, 3) == '../') {
			$selIconFile = GeneralUtility::resolveBackPath($icon);
			if (is_file(PATH_site . GeneralUtility::resolveBackPath(substr($icon, 3)))) {
				$selIconInfo = getimagesize((PATH_site . GeneralUtility::resolveBackPath(substr($icon, 3))));
			}
		} elseif (substr($icon, 0, 4) == 'ext/' || substr($icon, 0, 7) == 'sysext/') {
			$selIconFile = $icon;
			if (is_file(PATH_typo3 . $icon)) {
				$selIconInfo = getimagesize(PATH_typo3 . $icon);
			}
		} else {
			$selIconFile = IconUtility::skinImg('', 'gfx/' . $icon, '', 1);
			$iconPath = $selIconFile;
			if (is_file(PATH_typo3 . $iconPath)) {
				$selIconInfo = getimagesize(PATH_typo3 . $iconPath);
			}
		}
		if ($selIconInfo === FALSE) {
			// Unset to empty string if icon is not available
			$selIconFile = '';
		}
		return array($selIconFile, $selIconInfo);
	}

	/**
	 * Renders the $icon, supports a filename for skinImg or sprite-icon-name
	 *
	 * @param string $icon The icon passed, could be a file-reference or a sprite Icon name
	 * @param string $alt Alt attribute of the icon returned
	 * @param string $title Title attribute of the icon return
	 * @return string A tag representing to show the asked icon
	 * @internal
	 */
	static public function getIconHtml($icon, $alt = '', $title = '') {
		$iconArray = static::getIcon($icon);
		if (!empty($iconArray[0]) && is_file(GeneralUtility::resolveBackPath(PATH_typo3 . PATH_typo3_mod . $iconArray[0]))) {
			return '<img src="' . $iconArray[0] . '" alt="' . $alt . '" ' . ($title ? 'title="' . $title . '"' : '') . ' />';
		} else {
			return IconUtility::getSpriteIcon($icon, array('alt' => $alt, 'title' => $title));
		}
	}

	/**
	 * Initialize item array (for checkbox, selectorbox, radio buttons)
	 * Will resolve the label value.
	 *
	 * @param array $fieldValue The "columns" array for the field (from TCA)
	 * @return array An array of arrays with three elements; label, value, icon
	 * @internal
	 */
	static public function initItemArray($fieldValue) {
		$languageService = static::getLanguageService();
		$items = array();
		if (is_array($fieldValue['config']['items'])) {
			foreach ($fieldValue['config']['items'] as $itemValue) {
				$items[] = array($languageService->sL($itemValue[0]), $itemValue[1], $itemValue[2]);
			}
		}
		return $items;
	}

	/**
	 * Merges items into an item-array, optionally with an icon
	 * example:
	 * TCEFORM.pages.doktype.addItems.13 = My Label
	 * TCEFORM.pages.doktype.addItems.13.icon = EXT:t3skin/icons/gfx/i/pages.gif
	 *
	 * @param array $items The existing item array
	 * @param array $iArray An array of items to add. NOTICE: The keys are mapped to values, and the values and mapped to be labels. No possibility of adding an icon.
	 * @return array The updated $item array
	 * @internal
	 */
	static public function addItems($items, $iArray) {
		$languageService = static::getLanguageService();
		if (is_array($iArray)) {
			foreach ($iArray as $value => $label) {
				// if the label is an array (that means it is a subelement
				// like "34.icon = mylabel.png", skip it (see its usage below)
				if (is_array($label)) {
					continue;
				}
				// check if the value "34 = mylabel" also has a "34.icon = myimage.png"
				if (isset($iArray[$value . '.']) && $iArray[$value . '.']['icon']) {
					$icon = $iArray[$value . '.']['icon'];
				} else {
					$icon = '';
				}
				$items[] = array($languageService->sL($label), $value, $icon);
			}
		}
		return $items;
	}

	/**
	 * Add selector box items of more exotic kinds.
	 *
	 * @param array $items The array of items (label,value,icon)
	 * @param array $fieldValue The "columns" array for the field (from TCA)
	 * @param array $TSconfig TSconfig for the table/row
	 * @param string $field The fieldname
	 * @return array The $items array modified.
	 * @internal
	 */
	static public function addSelectOptionsToItemArray($items, $fieldValue, $TSconfig, $field) {
		$languageService = static::getLanguageService();

		// Values from foreign tables:
		if ($fieldValue['config']['foreign_table']) {
			$items = static::foreignTable($items, $fieldValue, $TSconfig, $field);
			if ($fieldValue['config']['neg_foreign_table']) {
				$items = static::foreignTable($items, $fieldValue, $TSconfig, $field, 1);
			}
		}

		// Values from a file folder:
		if ($fieldValue['config']['fileFolder']) {
			$fileFolder = GeneralUtility::getFileAbsFileName($fieldValue['config']['fileFolder']);
			if (@is_dir($fileFolder)) {
				// Configurations:
				$extList = $fieldValue['config']['fileFolder_extList'];
				$recursivityLevels = isset($fieldValue['config']['fileFolder_recursions'])
					? MathUtility::forceIntegerInRange($fieldValue['config']['fileFolder_recursions'], 0, 99)
					: 99;
				// Get files:
				$fileFolder = rtrim($fileFolder, '/') . '/';
				$fileArr = GeneralUtility::getAllFilesAndFoldersInPath(array(), $fileFolder, $extList, 0, $recursivityLevels);
				$fileArr = GeneralUtility::removePrefixPathFromList($fileArr, $fileFolder);
				foreach ($fileArr as $fileRef) {
					$fI = pathinfo($fileRef);
					$icon = GeneralUtility::inList('gif,png,jpeg,jpg', strtolower($fI['extension']))
						? '../' . PathUtility::stripPathSitePrefix($fileFolder) . $fileRef
						: '';
					$items[] = array(
						$fileRef,
						$fileRef,
						$icon
					);
				}
			}
		}

		// If 'special' is configured:
		if ($fieldValue['config']['special']) {
			switch ($fieldValue['config']['special']) {
				case 'tables':
					foreach ($GLOBALS['TCA'] as $theTableNames => $_) {
						if (!$GLOBALS['TCA'][$theTableNames]['ctrl']['adminOnly']) {
							// Icon:
							$icon = IconUtility::mapRecordTypeToSpriteIconName($theTableNames, array());
							// Add help text
							$helpText = array();
							$languageService->loadSingleTableDescription($theTableNames);
							$helpTextArray = $GLOBALS['TCA_DESCR'][$theTableNames]['columns'][''];
							if (!empty($helpTextArray['description'])) {
								$helpText['description'] = $helpTextArray['description'];
							}
							// Item configuration:
							$items[] = array(
								$languageService->sL($GLOBALS['TCA'][$theTableNames]['ctrl']['title']),
								$theTableNames,
								$icon,
								$helpText
							);
						}
					}
					break;
				case 'pagetypes':
					$theTypes = $GLOBALS['TCA']['pages']['columns']['doktype']['config']['items'];
					foreach ($theTypes as $theTypeArrays) {
						// Icon:
						$icon = 'empty-emtpy';
						if ($theTypeArrays[1] != '--div--') {
							$icon = IconUtility::mapRecordTypeToSpriteIconName('pages', array('doktype' => $theTypeArrays[1]));
						}
						// Item configuration:
						$items[] = array(
							$languageService->sL($theTypeArrays[0]),
							$theTypeArrays[1],
							$icon
						);
					}
					break;
				case 'exclude':
					$theTypes = BackendUtility::getExcludeFields();
					foreach ($theTypes as $theTypeArrays) {
						list($theTable, $theFullField) = explode(':', $theTypeArrays[1]);
						// If the field comes from a FlexForm, the syntax is more complex
						$theFieldParts = explode(';', $theFullField);
						$theField = array_pop($theFieldParts);
						// Add header if not yet set for table:
						if (!array_key_exists($theTable, $items)) {
							$icon = IconUtility::mapRecordTypeToSpriteIconName($theTable, array());
							$items[$theTable] = array(
								$languageService->sL($GLOBALS['TCA'][$theTable]['ctrl']['title']),
								'--div--',
								$icon
							);
						}
						// Add help text
						$helpText = array();
						$languageService->loadSingleTableDescription($theTable);
						$helpTextArray = $GLOBALS['TCA_DESCR'][$theTable]['columns'][$theFullField];
						if (!empty($helpTextArray['description'])) {
							$helpText['description'] = $helpTextArray['description'];
						}
						// Item configuration:
						$items[] = array(
							rtrim($languageService->sL($GLOBALS['TCA'][$theTable]['columns'][$theField]['label']), ':') . ' (' . $theField . ')',
							$theTypeArrays[1],
							'empty-empty',
							$helpText
						);
					}
					break;
				case 'explicitValues':
					$theTypes = BackendUtility::getExplicitAuthFieldValues();
					// Icons:
					$icons = array(
						'ALLOW' => 'status-status-permission-granted',
						'DENY' => 'status-status-permission-denied'
					);
					// Traverse types:
					foreach ($theTypes as $tableFieldKey => $theTypeArrays) {
						if (is_array($theTypeArrays['items'])) {
							// Add header:
							$items[] = array(
								$theTypeArrays['tableFieldLabel'],
								'--div--'
							);
							// Traverse options for this field:
							foreach ($theTypeArrays['items'] as $itemValue => $itemContent) {
								// Add item to be selected:
								$items[] = array(
									'[' . $itemContent[2] . '] ' . $itemContent[1],
									$tableFieldKey . ':' . preg_replace('/[:|,]/', '', $itemValue) . ':' . $itemContent[0],
									$icons[$itemContent[0]]
								);
							}
						}
					}
					break;
				case 'languages':
					$items = array_merge($items, BackendUtility::getSystemLanguages());
					break;
				case 'custom':
					// Initialize:
					$customOptions = $GLOBALS['TYPO3_CONF_VARS']['BE']['customPermOptions'];
					if (is_array($customOptions)) {
						foreach ($customOptions as $coKey => $coValue) {
							if (is_array($coValue['items'])) {
								// Add header:
								$items[] = array(
									$languageService->sL($coValue['header']),
									'--div--'
								);
								// Traverse items:
								foreach ($coValue['items'] as $itemKey => $itemCfg) {
									// Icon:
									if ($itemCfg[1]) {
										list($icon) = FormEngineUtility::getIcon($itemCfg[1]);
									} else {
										$icon = 'empty-empty';
									}
									// Add help text
									$helpText = array();
									if (!empty($itemCfg[2])) {
										$helpText['description'] = $languageService->sL($itemCfg[2]);
									}
									// Add item to be selected:
									$items[] = array(
										$languageService->sL($itemCfg[0]),
										$coKey . ':' . preg_replace('/[:|,]/', '', $itemKey),
										$icon,
										$helpText
									);
								}
							}
						}
					}
					break;
				case 'modListGroup':

				case 'modListUser':
					$loadModules = GeneralUtility::makeInstance(ModuleLoader::class);
					$loadModules->load($GLOBALS['TBE_MODULES']);
					$modList = $fieldValue['config']['special'] == 'modListUser' ? $loadModules->modListUser : $loadModules->modListGroup;
					if (is_array($modList)) {
						foreach ($modList as $theMod) {
							// Icon:
							$icon = $languageService->moduleLabels['tabs_images'][$theMod . '_tab'];
							if ($icon) {
								$icon = '../' . PathUtility::stripPathSitePrefix($icon);
							}
							// Add help text
							$helpText = array(
								'title' => $languageService->moduleLabels['labels'][$theMod . '_tablabel'],
								'description' => $languageService->moduleLabels['labels'][$theMod . '_tabdescr']
							);

							$label = '';
							// Add label for main module:
							$pp = explode('_', $theMod);
							if (count($pp) > 1) {
								$label .= $languageService->moduleLabels['tabs'][($pp[0] . '_tab')] . '>';
							}
							// Add modules own label now:
							$label .= $languageService->moduleLabels['tabs'][$theMod . '_tab'];

							// Item configuration:
							$items[] = array($label, $theMod, $icon, $helpText);
						}
					}
					break;
			}
		}

		return $items;
	}

	/**
	 * Adds records from a foreign table (for selector boxes). Helper for addSelectOptionsToItemArray()
	 *
	 * @param array $items The array of items (label,value,icon)
	 * @param array $fieldValue The 'columns' array for the field (from TCA)
	 * @param array $TSconfig TSconfig for the table/row
	 * @param string $field The fieldname
	 * @param bool $pFFlag If set, then we are fetching the 'neg_' foreign tables.
	 * @return array The $items array modified.
	 */
	static protected function foreignTable($items, $fieldValue, $TSconfig, $field, $pFFlag = FALSE) {
		$languageService = static::getLanguageService();
		$db = static::getDatabaseConnection();

		// Init:
		$pF = $pFFlag ? 'neg_' : '';
		$f_table = $fieldValue['config'][$pF . 'foreign_table'];
		$uidPre = $pFFlag ? '-' : '';
		// Exec query:
		$res = BackendUtility::exec_foreign_table_where_query($fieldValue, $field, $TSconfig, $pF);
		// Perform error test
		if ($db->sql_error()) {
			$msg = htmlspecialchars($db->sql_error());
			$msg .= '<br />' . LF;
			$msg .= $languageService->sL('LLL:EXT:lang/locallang_core.xlf:error.database_schema_mismatch');
			$msgTitle = $languageService->sL('LLL:EXT:lang/locallang_core.xlf:error.database_schema_mismatch_title');
			/** @var $flashMessage FlashMessage */
			$flashMessage = GeneralUtility::makeInstance(FlashMessage::class, $msg, $msgTitle, FlashMessage::ERROR, TRUE);
			/** @var $flashMessageService FlashMessageService */
			$flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
			/** @var $defaultFlashMessageQueue FlashMessageQueue */
			$defaultFlashMessageQueue = $flashMessageService->getMessageQueueByIdentifier();
			$defaultFlashMessageQueue->enqueue($flashMessage);
			return array();
		}
		// Get label prefix.
		$lPrefix = $languageService->sL($fieldValue['config'][$pF . 'foreign_table_prefix']);
		// Get icon field + path if any:
		$iField = $GLOBALS['TCA'][$f_table]['ctrl']['selicon_field'];
		$iPath = trim($GLOBALS['TCA'][$f_table]['ctrl']['selicon_field_path']);
		// Traverse the selected rows to add them:
		while ($row = $db->sql_fetch_assoc($res)) {
			BackendUtility::workspaceOL($f_table, $row);
			if (is_array($row)) {
				// Prepare the icon if available:
				if ($iField && $iPath && $row[$iField]) {
					$iParts = GeneralUtility::trimExplode(',', $row[$iField], TRUE);
					$icon = '../' . $iPath . '/' . trim($iParts[0]);
				} elseif (GeneralUtility::inList('singlebox,checkbox', $fieldValue['config']['renderMode'])) {
					$icon = IconUtility::mapRecordTypeToSpriteIconName($f_table, $row);
				} else {
					$icon = '';
				}
				// Add the item:
				$items[] = array(
					$lPrefix . htmlspecialchars(BackendUtility::getRecordTitle($f_table, $row)),
					$uidPre . $row['uid'],
					$icon
				);
			}
		}
		$db->sql_free_result($res);
		return $items;
	}

	/**
	 * @return LanguageService
	 */
	static protected function  getLanguageService() {
		return $GLOBALS['LANG'];
	}

	/**
	 * @return DatabaseConnection
	 */
	static protected function getDatabaseConnection() {
		return $GLOBALS['TYPO3_DB'];
	}

}
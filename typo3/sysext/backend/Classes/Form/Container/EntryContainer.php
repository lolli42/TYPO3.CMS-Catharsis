<?php
namespace TYPO3\CMS\Backend\Form\Container;

use TYPO3\CMS\Lang\LanguageService;
use TYPO3\CMS\Backend\Configuration\TranslationConfigurationProvider;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Backend\Form\Utility\FormEngineUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

class EntryContainer extends AbstractContainer {

	/**
	 * Array where records in the default language is stored. (processed by transferdata)
	 *
	 * @var array
	 */
	protected $defaultLanguageData = array();

	/**
	 * Array where records in the default language is stored (raw without any processing. used for making diff).
	 * This is the unserialized content of configured TCA ['ctrl']['transOrigDiffSourceField'] field, typically l18n_diffsource
	 *
	 * @var array
	 */
	protected $defaultLanguageDataDiff = array();

	/**
	 * Contains row data of "additional" language overlays
	 * array(
	 *   $table:$uid => array(
	 *     $additionalPreviewLanguageUid => $rowData
	 *   )
	 * )
	 *
	 * @var array
	 */
	protected $additionalPreviewLanguageData = array();


	public function render() {
		$table = $this->globalOptions['table'];
		$row = $this->globalOptions['databaseRow'];

		if (!$GLOBALS['TCA'][$table]) {
			return '';
		}

		$languageService = $this->getLanguageService();

		// Load the description content for the table if requested
		if ($GLOBALS['TCA'][$table]['interface']['always_description']) {
			$languageService->loadSingleTableDescription($table);
		}

		// If this is a localized record, stuff data from original record to local registry, will then be given to child elements
		$this->registerDefaultLanguageData($table, $row);

		// Current type value of the record.
		$recordTypeValue = $this->getRecordTypeValue($table, $row);

		// List of items to be rendered
		$itemList = '';
		if ($GLOBALS['TCA'][$table]['types'][$recordTypeValue]) {
			$itemList = $GLOBALS['TCA'][$table]['types'][$recordTypeValue]['showitem'];
			// @todo: overruleTypesArray is needed for InlineElements
			//if (is_array($overruleTypesArray) && isset($overruleTypesArray[$typeNum]['showitem'])) {
			//	$itemList = $overruleTypesArray[$typeNum]['showitem'];
			//}
		}

		$fieldsArray = GeneralUtility::trimExplode(',', $itemList, TRUE);
		// Add fields and remove excluded fields
		$fieldsArray = $this->mergeFieldsWithAddedFields($fieldsArray, $this->getFieldsToAdd($table, $row, $recordTypeValue), $table);
		$excludeElements = $this->getExcludeElements($table, $row, $recordTypeValue);
		$fieldsArray = $this->removeExcludeElementsFromFieldArray($fieldsArray, $excludeElements);

		// Streamline the fields array
		// First, make sure there is always a --div-- definition for the first element
		if (substr($fieldsArray[0], 0, 7) !== '--div--') {
			array_unshift($fieldsArray, '--div--;LLL:EXT:lang/locallang_core.xlf:labels.generalTab');
		}
		// If first tab has no label definition, add "general" label
		$firstTabHasLabel = count(GeneralUtility::trimExplode(';',  $fieldsArray[0])) > 1 ? TRUE : FALSE;
		if (!$firstTabHasLabel) {
			$fieldsArray[0] = '--div--;LLL:EXT:lang/locallang_core.xlf:labels.generalTab';
		}
		// If there are at least two --div-- definitions, inner container will be a TabContainer, else a NoTabContainer
		$tabCount = 0;
		foreach ($fieldsArray as $field) {
			if (substr($field, 0, 7) === '--div--') {
				$tabCount++;
			}
		}
		$hasTabs = TRUE;
		if ($tabCount < 2) {
			// Remove first tab definition again if there is only one tab defined
			array_shift($fieldsArray);
			$hasTabs = FALSE;
		}

		$options = $this->globalOptions;
		$options['fieldsArray'] = $fieldsArray;
		// Palettes may contain elements that should be excluded, resolved in PaletteContainer
		$options['excludeElements'] = $excludeElements;
		$options['defaultLanguageData'] = $this->defaultLanguageData;
		$options['defaultLanguageDataDiff'] = $this->defaultLanguageDataDiff;
		$options['additionalPreviewLanguageData'] = $this->additionalPreviewLanguageData;

		if ($hasTabs) {
			/** @var EntryContainer $TabsContainer */
			$container = GeneralUtility::makeInstance(TabsContainer::class);
			$container->setGlobalOptions($options);
			$resultArray = $container->render();
		} else {
			/** @var EntryContainer $NoTabsContainer */
			$container = GeneralUtility::makeInstance(NoTabsContainer::class);
			$container->setGlobalOptions($options);
			$resultArray = $container->render();
		}

		return $resultArray;
	}

	/**
	 * Will register data from original language records if the current record is a translation of another.
	 * The original data is shown with the edited record in the form.
	 * The information also includes possibly diff-views of what changed in the original record.
	 * Function called from outside (see alt_doc.php + quick edit) before rendering a form for a record
	 *
	 * @param string $table Table name of the record being edited
	 * @param array $rec Record array of the record being edited
	 * @return void
	 */
	protected function registerDefaultLanguageData($table, $rec) {
		// @todo: early return here if the arrays are already filled?

		// Add default language:
		if (
			$GLOBALS['TCA'][$table]['ctrl']['languageField'] && $rec[$GLOBALS['TCA'][$table]['ctrl']['languageField']] > 0
			&& $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField']
			&& (int)$rec[$GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField']] > 0
		) {
			$lookUpTable = $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerTable']
				? $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerTable']
				: $table;
			// Get data formatted:
			$this->defaultLanguageData[$table . ':' . $rec['uid']] = BackendUtility::getRecordWSOL(
				$lookUpTable,
				(int)$rec[$GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField']]
			);
			// Get data for diff:
			if ($GLOBALS['TCA'][$table]['ctrl']['transOrigDiffSourceField']) {
				$this->defaultLanguageDataDiff[$table . ':' . $rec['uid']] = unserialize($rec[$GLOBALS['TCA'][$table]['ctrl']['transOrigDiffSourceField']]);
			}
			// If there are additional preview languages, load information for them also:
			foreach ($this->globalOptions['additionalPreviewLanguages'] as $prL) {
				/** @var $t8Tools TranslationConfigurationProvider */
				$t8Tools = GeneralUtility::makeInstance(TranslationConfigurationProvider::class);
				$tInfo = $t8Tools->translationInfo($lookUpTable, (int)$rec[$GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField']], $prL['uid']);
				if (is_array($tInfo['translations']) && is_array($tInfo['translations'][$prL['uid']])) {
					$this->additionalPreviewLanguageData[$table . ':' . $rec['uid']][$prL['uid']] = BackendUtility::getRecordWSOL($table, (int)$tInfo['translations'][$prL['uid']]['uid']);
				}
			}
		}
	}

	/**
	 * Calculate and return the current type value of a record
	 *
	 * @param string $table The table name. MUST be in $GLOBALS['TCA']
	 * @param array $row The row from the table, should contain at least the "type" field, if applicable.
	 * @return string Return the "type" value for this record, ready to pick a "types" configuration from the $GLOBALS['TCA'] array.
	 * @throws \RuntimeException
	 */
	protected function getRecordTypeValue($table, $row) {
		$typeNum = 0;
		$field = $GLOBALS['TCA'][$table]['ctrl']['type'];
		if ($field) {
			if (strpos($field, ':') !== FALSE) {
				list($pointerField, $foreignTypeField) = explode(':', $field);
				$fieldConfig = $GLOBALS['TCA'][$table]['columns'][$pointerField]['config'];
				$relationType = $fieldConfig['type'];
				if ($relationType === 'select') {
					$foreignUid = $row[$pointerField];
					$foreignTable = $fieldConfig['foreign_table'];
				} elseif ($relationType === 'group') {
					$values = FormEngineUtility::extractValuesOnlyFromValueLabelList($row[$pointerField]);
					list(, $foreignUid) = GeneralUtility::revExplode('_', $values[0], 2);
					$allowedTables = explode(',', $fieldConfig['allowed']);
					// Always take the first configured table.
					$foreignTable = $allowedTables[0];
				} else {
					throw new \RuntimeException('TCA Foreign field pointer fields are only allowed to be used with group or select field types.', 1325861239);
				}
				if ($foreignUid) {
					$foreignRow = BackendUtility::getRecord($foreignTable, $foreignUid, $foreignTypeField);
					$this->registerDefaultLanguageData($foreignTable, $foreignRow);
					if ($foreignRow[$foreignTypeField]) {
						$foreignTypeFieldConfig = $GLOBALS['TCA'][$table]['columns'][$field];
						$typeNum = $this->overrideTypeWithValueFromDefaultLanguageRecord($foreignTable, $foreignRow, $foreignTypeField, $foreignTypeFieldConfig);
					}
				}
			} else {
				$typeFieldConfig = $GLOBALS['TCA'][$table]['columns'][$field];
				$typeNum = $this->overrideTypeWithValueFromDefaultLanguageRecord($table, $row, $field, $typeFieldConfig);
			}
		}
		if (empty($typeNum)) {
			// If that value is an empty string, set it to "0" (zero)
			$typeNum = 0;
		}
		// If current typeNum doesn't exist, set it to 0 (or to 1 for historical reasons, if 0 doesn't exist)
		if (!$GLOBALS['TCA'][$table]['types'][$typeNum]) {
			$typeNum = $GLOBALS['TCA'][$table]['types']['0'] ? 0 : 1;
		}
		// Force to string. Necessary for eg '-1' to be recognized as a type value.
		$typeNum = (string)$typeNum;
		return $typeNum;
	}

	/**
	 * The requested field value will be overridden with the data from the default
	 * language if the field is configured accordingly.
	 *
	 * @param string $table Table name of the record being edited
	 * @param array $row Record array of the record being edited in current language
	 * @param string $field Field name represented by $item
	 * @param array $fieldConf Content of $PA['fieldConf']
	 * @return string Unprocessed field value merged with default language data if needed
	 */
	protected function overrideTypeWithValueFromDefaultLanguageRecord($table, $row, $field, $fieldConf) {
		$value = $row[$field];
		if (is_array($this->defaultLanguageData[$table . ':' . $row['uid']])) {
			// @todo: Is this a bug? Currently the field from default lang is picked in mergeIfNotBlank mode if the
			// @todo: default value is not empty, but imho it should only be picked if the language overlay record *is* empty?!
			if (
				$fieldConf['l10n_mode'] == 'exclude'
				|| $fieldConf['l10n_mode'] == 'mergeIfNotBlank' && trim($this->defaultLanguageData[$table . ':' . $row['uid']][$field]) !== ''
			) {
				$value = $this->defaultLanguageData[$table . ':' . $row['uid']][$field];
			}
		}
		return $value;
	}

	/**
	 * Producing an array of field names NOT to display in the form,
	 * based on settings from subtype_value_field, bitmask_excludelist_bits etc.
	 * Notice, this list is in NO way related to the "excludeField" flag
	 *
	 * @param string $table Table name, MUST be in $GLOBALS['TCA']
	 * @param array $row A record from table.
	 * @param string $typeNum A "type" pointer value, probably the one calculated based on the record array.
	 * @return array Array with fieldnames as values. The fieldnames are those which should NOT be displayed "anyways
	 */
	protected function getExcludeElements($table, $row, $typeNum) {
		$excludeElements = array();
		// If a subtype field is defined for the type
		if ($GLOBALS['TCA'][$table]['types'][$typeNum]['subtype_value_field']) {
			$subTypeField = $GLOBALS['TCA'][$table]['types'][$typeNum]['subtype_value_field'];
			if (trim($GLOBALS['TCA'][$table]['types'][$typeNum]['subtypes_excludelist'][$row[$subTypeField]])) {
				$excludeElements = GeneralUtility::trimExplode(',', $GLOBALS['TCA'][$table]['types'][$typeNum]['subtypes_excludelist'][$row[$subTypeField]], TRUE);
			}
		}
		// If a bitmask-value field has been configured, then find possible fields to exclude based on that:
		if ($GLOBALS['TCA'][$table]['types'][$typeNum]['bitmask_value_field']) {
			$subTypeField = $GLOBALS['TCA'][$table]['types'][$typeNum]['bitmask_value_field'];
			$sTValue = MathUtility::forceIntegerInRange($row[$subTypeField], 0);
			if (is_array($GLOBALS['TCA'][$table]['types'][$typeNum]['bitmask_excludelist_bits'])) {
				foreach ($GLOBALS['TCA'][$table]['types'][$typeNum]['bitmask_excludelist_bits'] as $bitKey => $eList) {
					$bit = substr($bitKey, 1);
					if (MathUtility::canBeInterpretedAsInteger($bit)) {
						$bit = MathUtility::forceIntegerInRange($bit, 0, 30);
						if ($bitKey[0] === '-' && !($sTValue & pow(2, $bit)) || $bitKey[0] === '+' && $sTValue & pow(2, $bit)) {
							$excludeElements = array_merge($excludeElements, GeneralUtility::trimExplode(',', $eList, TRUE));
						}
					}
				}
			}
		}
		return $excludeElements;
	}

	/**
	 * Finds possible field to add to the form, based on subtype fields.
	 *
	 * @param string $table Table name, MUST be in $GLOBALS['TCA']
	 * @param array $row A record from table.
	 * @param string $typeNum A "type" pointer value, probably the one calculated based on the record array.
	 * @return array An array containing two values: 1) Another array containing field names to add and 2) the subtype value field.
	 */
	protected function getFieldsToAdd($table, $row, $typeNum) {
		$addElements = array();
		$subTypeField = '';
		if ($GLOBALS['TCA'][$table]['types'][$typeNum]['subtype_value_field']) {
			$subTypeField = $GLOBALS['TCA'][$table]['types'][$typeNum]['subtype_value_field'];
			if (trim($GLOBALS['TCA'][$table]['types'][$typeNum]['subtypes_addlist'][$row[$subTypeField]])) {
				$addElements = GeneralUtility::trimExplode(',', $GLOBALS['TCA'][$table]['types'][$typeNum]['subtypes_addlist'][$row[$subTypeField]], TRUE);
			}
		}
		return array($addElements, $subTypeField);
	}

	/**
	 * Merges the current [types][showitem] array with the array of fields to add for the current subtype field of the "type" value.
	 *
	 * @param array $fields A [types][showitem] list of fields, exploded by ",
	 * @param array $fieldsToAdd The output from getFieldsToAdd()
	 * @param string $table The table name, if we want to consider it's palettes when positioning the new elements
	 * @return array Return the modified $fields array.
	 */
	protected function mergeFieldsWithAddedFields($fields, $fieldsToAdd, $table = '') {
		if (count($fieldsToAdd[0])) {
			$c = 0;
			$found = FALSE;
			foreach ($fields as $fieldInfo) {
				list($fieldName, $label, $paletteName) = GeneralUtility::trimExplode(';', $fieldInfo);
				if ($fieldName === $fieldsToAdd[1]) {
					$found = TRUE;
				} elseif ($fieldName === '--palette--' && $paletteName && $table !== '') {
					// Look inside the palette
					if (is_array($GLOBALS['TCA'][$table]['palettes'][$paletteName])) {
						$itemList = $GLOBALS['TCA'][$table]['palettes'][$paletteName]['showitem'];
						if ($itemList) {
							$paletteFields = GeneralUtility::trimExplode(',', $itemList, TRUE);
							foreach ($paletteFields as $info) {
								$fieldParts = GeneralUtility::trimExplode(';', $info);
								$theField = $fieldParts[0];
								if ($theField === $fieldsToAdd[1]) {
									$found = TRUE;
									break 1;
								}
							}
						}
					}
				}
				if ($found) {
					array_splice($fields, $c + 1, 0, $fieldsToAdd[0]);
					break;
				}
				$c++;
			}
		}
		return $fields;
	}

	/**
	 * @return LanguageService
	 */
	protected function getLanguageService() {
		return $GLOBALS['LANG'];
	}

}

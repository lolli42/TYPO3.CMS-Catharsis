<?php
namespace TYPO3\CMS\Backend\Form\Container;

use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;
use TYPO3\CMS\Backend\Form\ElementConditionMatcher;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Backend\Form\Utility\FormEngineUtility;
use TYPO3\CMS\Lang\LanguageService;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\Utility\IconUtility;
use TYPO3\CMS\Backend\Template\DocumentTemplate;
use TYPO3\CMS\Core\Utility\DiffUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

class SingleFieldContainer extends AbstractContainer {

	/**
	 * Returns the form HTML code for a database table field.
	 *
	 * @param string $table The table name
	 * @param string $field The field name
	 * @param array $row The record to edit from the database table.
	 * @param string $altName Alternative field name label to show.
	 * @param bool $palette Set this if the field is on a palette (in top frame), otherwise not. (if set, field will render as a hidden field).
	 * @param string $extra The "extra" options from "Part 4" of the field configurations found in the "types" "showitem" list. Typically parsed by $this->getSpecConfFromString() in order to get the options as an associative array.
	 * @param int $pal The palette pointer.
	 * @return mixed String (normal) or array (palettes)
	 */
//	public function getSingleField($table, $field, $row, $altName = '', $palette = FALSE, $extra = '', $pal = 0) {
	public function render() {
		// Hook: getSingleField_preProcess
//		foreach ($this->hookObjectsSingleField as $hookObj) {
//			if (method_exists($hookObj, 'getSingleField_preProcess')) {
//				$hookObj->getSingleField_preProcess($table, $fieldName, $row, $altName, $palette, $extra, $pal, $this);
//			}
//		}

		$content = $this->singleField();

		// Hook: getSingleField_postProcess
//		foreach ($this->hookObjectsSingleField as $hookObj) {
//			if (method_exists($hookObj, 'getSingleField_postProcess')) {
//				$hookObj->getSingleField_postProcess($table, $fieldName, $row, $out, $parameterArray, $this);
//			}
//		}

		return $content;
	}

	protected function singleField() {
		$backendUser = $this->getBackendUserAuthentication();
		$languageService = $this->getLanguageService();

		$table = $this->globalOptions['table'];
		$row = $this->globalOptions['databaseRow'];
		$fieldName = $this->globalOptions['fieldName'];

		if (!is_array($GLOBALS['TCA'][$table]['columns'][$fieldName])) {
			return '';
		}

		$parameterArray = array();
		$parameterArray['altName'] = $this->globalOptions['fieldLabel'];
		$parameterArray['extra'] = $this->globalOptions['fieldExtra'];
		$parameterArray['fieldConf'] = $GLOBALS['TCA'][$table]['columns'][$fieldName];

		// A couple of early returns in case the field should not be rendered
		// Check if this field is configured and editable according to exclude fields and other configuration
		if (
			$parameterArray['fieldConf']['exclude'] && !$backendUser->check('non_exclude_fields', $table . ':' . $fieldName)
			|| $parameterArray['fieldConf']['config']['type'] === 'passthrough'
			|| !$backendUser->isRTE() && $parameterArray['fieldConf']['config']['showIfRTE']
			|| $GLOBALS['TCA'][$table]['ctrl']['languageField'] && !$parameterArray['fieldConf']['l10n_display'] && $parameterArray['fieldConf']['l10n_mode'] === 'exclude' && ($row[$GLOBALS['TCA'][$table]['ctrl']['languageField']] > 0)
			|| $GLOBALS['TCA'][$table]['ctrl']['languageField'] && $this->globalOptions['localizationMode'] && $this->globalOptions['localizationMode'] !== $parameterArray['fieldConf']['l10n_cat']
		) {
			return '';
		}
//		if ($this->inline->skipField($table, $fieldName, $row, $parameterArray['fieldConf']['config'])) {
//			return '';
//		}
		// Evaluate display condition
		if ($parameterArray['fieldConf']['displayCond'] && is_array($row)) {
			// @todo: isn't $row = array() safe somewhere above already?
			/** @var $elementConditionMatcher ElementConditionMatcher */
			$elementConditionMatcher = GeneralUtility::makeInstance(ElementConditionMatcher::class);
			if (!$elementConditionMatcher->match($parameterArray['fieldConf']['displayCond'], $row)) {
				return '';
			}
		}
		// Fetching the TSconfig for the current table/field. This includes the $row which means that
		$parameterArray['fieldTSConfig'] = FormEngineUtility::getTSconfigForTableRow($table, $row, $fieldName);
		if ($parameterArray['fieldTSConfig']['disabled']) {
			return '';
		}

		$content = '';

		// Override fieldConf by fieldTSconfig:
		$parameterArray['fieldConf']['config'] = FormEngineUtility::overrideFieldConf($parameterArray['fieldConf']['config'], $parameterArray['fieldTSConfig']);
		$parameterArray['itemFormElName'] = $this->globalOptions['prependFormFieldNames'] . '[' . $table . '][' . $row['uid'] . '][' . $fieldName . ']';
		// Form field name, in case of file uploads
		$parameterArray['itemFormElName_file'] = $this->globalOptions['prependFormFieldNames_file'] . '[' . $table . '][' . $row['uid'] . '][' . $fieldName . ']';
		// Form field name, to activate elements
		// If the "eval" list contains "null", elements can be deactivated which results in storing NULL to database
		$parameterArray['itemFormElNameActive'] = $this->globalOptions['prependFormFieldNamesActive'] . '[' . $table . '][' . $row['uid'] . '][' . $fieldName . ']';
		$parameterArray['itemFormElID'] = $this->globalOptions['prependFormFieldNames'] . '_' . $table . '_' . $row['uid'] . '_' . $fieldName;

		// The value to show in the form field.
		$parameterArray['itemFormElValue'] = $row[$fieldName];
		// Set field to read-only if configured for translated records to show default language content as readonly
		if ($parameterArray['fieldConf']['l10n_display']
			&& GeneralUtility::inList($parameterArray['fieldConf']['l10n_display'], 'defaultAsReadonly')
			&& $row[$GLOBALS['TCA'][$table]['ctrl']['languageField']] > 0
		) {
			$parameterArray['fieldConf']['config']['readOnly'] = TRUE;
			$parameterArray['itemFormElValue'] = $this->globalOptions['defaultLanguageData'][$table . ':' . $row['uid']][$fieldName];
		}

		if (strpos($GLOBALS['TCA'][$table]['ctrl']['type'], ':') === FALSE) {
			$typeField = $GLOBALS['TCA'][$table]['ctrl']['type'];
		} else {
			$typeField = substr($GLOBALS['TCA'][$table]['ctrl']['type'], 0, strpos($GLOBALS['TCA'][$table]['ctrl']['type'], ':'));
		}
		// Create a JavaScript code line which will ask the user to save/update the form due to changing the element.
		// This is used for eg. "type" fields and others configured with "requestUpdate"
		if (
			!empty($GLOBALS['TCA'][$table]['ctrl']['type'])
			&& $fieldName === $typeField
			|| !empty($GLOBALS['TCA'][$table]['ctrl']['requestUpdate'])
			&& GeneralUtility::inList(str_replace(' ', '', $GLOBALS['TCA'][$table]['ctrl']['requestUpdate']), $fieldName)
		) {
			if ($backendUser->jsConfirmation(JsConfirmation::TYPE_CHANGE)) {
				$alertMsgOnChange = 'if (confirm(TBE_EDITOR.labels.onChangeAlert) && TBE_EDITOR.checkSubmit(-1)){ TBE_EDITOR.submitForm() };';
			} else {
				$alertMsgOnChange = 'if (TBE_EDITOR.checkSubmit(-1)){ TBE_EDITOR.submitForm() };';
			}
		} else {
			$alertMsgOnChange = '';
		}

		// Render as a hidden field?
		// @todo
		$this->hiddenFieldListArr = array();
		if (in_array($fieldName, $this->hiddenFieldListArr)) {
//			$this->hiddenFieldAccum[] = '<input type="hidden" name="' . $parameterArray['itemFormElName'] . '" value="' . htmlspecialchars($parameterArray['itemFormElValue']) . '" />';
		} else {
			// Render as a normal field:
			$parameterArray['label'] = $parameterArray['altName'] ?: $parameterArray['fieldConf']['label'];
			$parameterArray['label'] = $parameterArray['fieldTSConfig']['label'] ?: $parameterArray['label'];
			$parameterArray['label'] = $parameterArray['fieldTSConfig']['label.'][$languageService->lang] ?: $parameterArray['label'];
			$parameterArray['label'] = $languageService->sL($parameterArray['label']);
			$label = htmlspecialchars($parameterArray['label'], ENT_COMPAT, 'UTF-8', FALSE);
			// JavaScript code for event handlers:
			$parameterArray['fieldChangeFunc'] = array();
			$parameterArray['fieldChangeFunc']['TBE_EDITOR_fieldChanged'] = 'TBE_EDITOR.fieldChanged(\'' . $table . '\',\'' . $row['uid'] . '\',\'' . $fieldName . '\',\'' . $parameterArray['itemFormElName'] . '\');';
			$parameterArray['fieldChangeFunc']['alert'] = $alertMsgOnChange;

			// If this is the child of an inline type and it is the field creating the label
//			if ($this->inline->isInlineChildAndLabelField($table, $fieldName)) {
//				$inlineObjectId = implode(InlineElement::Structure_Separator, array(
//					$this->inline->inlineNames['object'],
//					$table,
//					$row['uid']
//				));
//				$parameterArray['fieldChangeFunc']['inline'] = 'inline.handleChangedField(\'' . $parameterArray['itemFormElName'] . '\',\'' . $inlineObjectId . '\');';
//			}

			// Based on the type of the item, call a render function:
			$options = $this->globalOptions;
			$item = $this->getSingleField_SW($table, $fieldName, $row, $parameterArray, $options);
			// Add language + diff
			$renderLanguageDiff = TRUE;
			if ($parameterArray['fieldConf']['l10n_display'] && (GeneralUtility::inList($parameterArray['fieldConf']['l10n_display'], 'hideDiff')
					|| GeneralUtility::inList($parameterArray['fieldConf']['l10n_display'], 'defaultAsReadonly'))
			) {
				$renderLanguageDiff = FALSE;
			}
			if ($renderLanguageDiff) {
				$item = $this->renderDefaultLanguageContent($table, $fieldName, $row, $item);
				$item = $this->renderDefaultLanguageDiff($table, $fieldName, $row, $item);
			}

/**
			if (isset($parameterArray['fieldConf']['config']['mode']) && $parameterArray['fieldConf']['config']['mode'] == 'useOrOverridePlaceholder') {
				$placeholder = $this->getPlaceholderValue($table, $fieldName, $parameterArray['fieldConf']['config'], $row);
				$onChange = 'typo3form.fieldTogglePlaceholder(' . GeneralUtility::quoteJSvalue($parameterArray['itemFormElName']) . ', !this.checked)';
				$checked = $parameterArray['itemFormElValue'] === NULL ? '' : ' checked="checked"';

				$this->additionalJS_post[] = 'typo3form.fieldTogglePlaceholder('
					. GeneralUtility::quoteJSvalue($parameterArray['itemFormElName']) . ', ' . ($checked ? 'false' : 'true') . ');';

				$noneElement = GeneralUtility::makeInstance(NoneElement::class, $this);
				$noneElementConfiguration = $parameterArray;
				$noneElementConfiguration['itemFormElValue'] = GeneralUtility::fixed_lgd_cs($placeholder, 30);
				$noneElementHtml = $noneElement->render('', '', '', $noneElementConfiguration);

				$item = '
				<input type="hidden" name="' . htmlspecialchars($parameterArray['itemFormElNameActive']) . '" value="0" />
				<div class="checkbox">
					<label>
						<input type="checkbox" name="' . htmlspecialchars($parameterArray['itemFormElNameActive']) . '" value="1" id="tce-forms-textfield-use-override-' . $fieldName . '-' . $row['uid'] . '" onchange="' . htmlspecialchars($onChange) . '"' . $checked . ' />
						' . sprintf($languageService->sL('LLL:EXT:lang/locallang_core.xlf:labels.placeholder.override'), BackendUtility::getRecordTitlePrep($placeholder, 20)) . '
					</label>
				</div>
				<div class="t3js-formengine-placeholder-placeholder">
					' . $noneElementHtml . '
				</div>
				<div class="t3js-formengine-placeholder-formfield">' . $item . '</div>';
			}
 */

			// Wrap the label with help text
			$parameterArray['label'] = ($label = BackendUtility::wrapInHelp($table, $fieldName, $label));
			// Create output value:
			if ($parameterArray['fieldConf']['config']['type'] == 'user' && $parameterArray['fieldConf']['config']['noTableWrapping']) {
				$content = $item;
			} elseif ($this->globalOptions['isInPalette']) {
				// Array:
				$content = array(
					'NAME' => $label,
					'ID' => $row['uid'],
					'FIELD' => $fieldName,
					'TABLE' => $table,
					'ITEM' => $item,
					'ITEM_DISABLED' => ($this->isDisabledNullValueField($table, $fieldName, $row, $parameterArray) ? ' disabled' : ''),
					'ITEM_NULLVALUE' => $this->renderNullValueWidget($table, $fieldName, $row, $parameterArray),
				);
			} else {
				$content = '
				<fieldset class="form-section">
					<!-- getSingleField -->
					<div class="form-group t3js-formengine-palette-field">
						<label class="t3js-formengine-label">
							' . $label . '
							<img name="req_' . $table . '_' . $row['uid'] . '_' . $fieldName . '" src="clear.gif" class="t3js-formengine-field-required" alt="" />
						</label>
						<div class="t3js-formengine-field-item ' . ($this->isDisabledNullValueField($table, $fieldName, $row, $parameterArray) ? ' disabled' : '') . '">
							<div class="t3-form-field-disable"></div>
							' . $this->renderNullValueWidget($table, $fieldName, $row, $parameterArray) . '
							' . $item . '
						</div>
					</div>
				</fieldset>
			';
			}

		}

		return $content;
	}


	/**
	 * Rendering a single item for the form
	 *
	 * @param string $table Table name of record
	 * @param string $field Fieldname to render
	 * @param array $row The record
	 * @param array $PA Parameters array containing a lot of stuff. Value by Reference!
	 * @param array $options Option array
	 * @return string Returns the item as HTML code to insert
	 */
	protected function getSingleField_SW($table, $field, $row, &$PA, array $options) {
		// Hook: getSingleField_beforeRender
/**
		foreach ($this->hookObjectsSingleField as $hookObject) {
			if (method_exists($hookObject, 'getSingleField_beforeRender')) {
				$hookObject->getSingleField_beforeRender($table, $field, $row, $PA);
			}
		}
*/
		$type = $PA['fieldConf']['config']['type'];
		if ($type === 'inline') {
//			$item = $this->inline->getSingleField_typeInline($table, $field, $row, $PA);
		} else {
			$typeClassNameMapping = array(
				'input' => 'InputElement',
				'text' => 'TextElement',
				'check' => 'CheckboxElement',
				'radio' => 'RadioElement',
				'select' => 'SelectElement',
				'group' => 'GroupElement',
				'none' => 'NoneElement',
				'user' => 'UserElement',
//				'flex' => 'FlexElement',
				'unknown' => 'UnknownElement',
			);
			if (!isset($typeClassNameMapping[$type])) {
				$type = 'unknown';
			}
			$formElement = GeneralUtility::makeInstance('TYPO3\\CMS\\Backend\\Form\\Element\\' . $typeClassNameMapping[$type], $this);
			if ($formElement instanceof AbstractFormElement) {
				$formElement->setGlobalOptions($options);
			}
			$item = $formElement->render($table, $field, $row, $PA);
		}
		return $item;
	}



	/**
	 * Determines whether the current field value is considered as NULL value.
	 * Using NULL values is enabled by using 'null' in the 'eval' TCA definition.
	 *
	 * @param string $table Name of the table
	 * @param string $field Name of the field
	 * @param array $row Accordant data
	 * @param array $PA Parameters array with rendering instructions
	 * @return bool
	 */
	protected function isDisabledNullValueField($table, $field, array $row, array $PA) {
		$result = FALSE;
		$config = $PA['fieldConf']['config'];
		if ($PA['itemFormElValue'] === NULL && !empty($config['eval'])
			&& GeneralUtility::inList($config['eval'], 'null')
			&& (empty($config['mode']) || $config['mode'] !== 'useOrOverridePlaceholder')
		) {
			$result = TRUE;
		}
		return $result;
	}

	/**
	 * Renders a view widget to handle and activate NULL values.
	 * The widget is enabled by using 'null' in the 'eval' TCA definition.
	 *
	 * @param string $table Name of the table
	 * @param string $field Name of the field
	 * @param array $row Accordant data of the record row
	 * @param array $PA Parameters array with rendering instructions
	 * @return string Widget (if any).
	 */
	protected function renderNullValueWidget($table, $field, array $row, array $PA) {
		$widget = '';
		$config = $PA['fieldConf']['config'];
		if (
			!empty($config['eval']) && GeneralUtility::inList($config['eval'], 'null')
			&& (empty($config['mode']) || $config['mode'] !== 'useOrOverridePlaceholder')
		) {
			$checked = $PA['itemFormElValue'] === NULL ? '' : ' checked="checked"';
			$onChange = htmlspecialchars(
				'typo3form.fieldSetNull(\'' . $PA['itemFormElName'] . '\', !this.checked)'
			);
			$widget = '
				<div class="checkbox">
					<label>
						<input type="hidden" name="' . $PA['itemFormElNameActive'] . '" value="0" />
						<input type="checkbox" name="' . $PA['itemFormElNameActive'] . '" value="1" onchange="' . $onChange . '"' . $checked . ' /> &nbsp;
					</label>
				</div>';
		}
		return $widget;
	}

	/**
	 * Renders the display of default language record content around current field.
	 * Will render content if any is found in the internal array, $this->defaultLanguageData,
	 * depending on registerDefaultLanguageData() being called prior to this.
	 *
	 * @param string $table Table name of the record being edited
	 * @param string $field Field name represented by $item
	 * @param array $row Record array of the record being edited
	 * @param string $item HTML of the form field. This is what we add the content to.
	 * @return string Item string returned again, possibly with the original value added to.
	 */
	protected function renderDefaultLanguageContent($table, $field, $row, $item) {
		if (is_array($this->globalOptions['defaultLanguageData'][$table . ':' . $row['uid']])) {
			$defaultLanguageValue = BackendUtility::getProcessedValue(
				$table,
				$field,
				$this->globalOptions['defaultLanguageData'][$table . ':' . $row['uid']][$field],
				0,
				1,
				FALSE,
				$this->globalOptions['defaultLanguageData'][$table . ':' . $row['uid']]['uid']
			);
			$fieldConfig = $GLOBALS['TCA'][$table]['columns'][$field];
			// Don't show content if it's for IRRE child records:
			if ($fieldConfig['config']['type'] != 'inline') {
				if ($defaultLanguageValue !== '') {
					$item .= '<div class="t3-form-original-language">' . FormEngineUtility::getLanguageIcon($table, $row, 0)
						. $this->getMergeBehaviourIcon($fieldConfig['l10n_mode'])
						. $this->previewFieldValue($defaultLanguageValue, $fieldConfig, $field) . '</div>';
				}
				$additionalPreviewLanguages = $this->globalOptions['additionalPreviewLanguages'];
				foreach ($additionalPreviewLanguages as $previewLanguage) {
					$defaultLanguageValue = BackendUtility::getProcessedValue(
						$table,
						$field,
						$this->globalOptions['additionalPreviewLanguageData'][$table . ':' . $row['uid']][$previewLanguage['uid']][$field],
						0,
						1
					);
					if ($defaultLanguageValue !== '') {
						$item .= '<div class="t3-form-original-language">'
							. FormEngineUtility::getLanguageIcon($table, $row, ('v' . $previewLanguage['ISOcode']))
							. $this->getMergeBehaviourIcon($fieldConfig['l10n_mode'])
							. $this->previewFieldValue($defaultLanguageValue, $fieldConfig, $field) . '</div>';
					}
				}
			}
		}
		return $item;
	}

	/**
	 * Renders an icon to indicate the way the translation and the original is merged (if this is relevant).
	 *
	 * If a field is defined as 'mergeIfNotBlank' this is useful information for an editor. He/she can leave the field blank and
	 * the original value will be used. Without this hint editors are likely to copy the contents even if it is not necessary.
	 *
	 * @param string $l10nMode Localization mode from TCA
	 * @return string
	 */
	protected function getMergeBehaviourIcon($l10nMode) {
		$icon = '';
		if ($l10nMode === 'mergeIfNotBlank') {
			$icon = IconUtility::getSpriteIcon(
				'actions-edit-merge-localization',
				array('title' => $this->getLanguageService()->sL('LLL:EXT:lang/locallang_misc.xlf:localizeMergeIfNotBlank'))
			);
		}
		return $icon;
	}

	/**
	 * Rendering preview output of a field value which is not shown as a form field but just outputted.
	 *
	 * @param string $value The value to output
	 * @param array $config Configuration for field.
	 * @param string $field Name of field.
	 * @return string HTML formatted output
	 */
	protected function previewFieldValue($value, $config, $field = '') {
		if ($config['config']['type'] === 'group' && ($config['config']['internal_type'] === 'file' || $config['config']['internal_type'] === 'file_reference')) {
			// Ignore upload folder if internal_type is file_reference
			if ($config['config']['internal_type'] === 'file_reference') {
				$config['config']['uploadfolder'] = '';
			}
			$show_thumbs = TRUE;
			$table = 'tt_content';
			// Making the array of file items:
			$itemArray = GeneralUtility::trimExplode(',', $value, TRUE);
			// Showing thumbnails:
			$thumbnail = '';
			if ($show_thumbs) {
				$imgs = array();
				foreach ($itemArray as $imgRead) {
					$imgP = explode('|', $imgRead);
					$imgPath = rawurldecode($imgP[0]);
					$rowCopy = array();
					$rowCopy[$field] = $imgPath;
					// Icon + click menu:
					$absFilePath = GeneralUtility::getFileAbsFileName($config['config']['uploadfolder'] ? $config['config']['uploadfolder'] . '/' . $imgPath : $imgPath);
					$fileInformation = pathinfo($imgPath);
					$fileIcon = IconUtility::getSpriteIconForFile(
						$imgPath,
						array(
							'title' => htmlspecialchars($fileInformation['basename'] . ($absFilePath && @is_file($absFilePath) ? ' (' . GeneralUtility::formatSize(filesize($absFilePath)) . 'bytes)' : ' - FILE NOT FOUND!'))
						)
					);
					$imgs[] =
						'<span class="text-nowrap">' .
						BackendUtility::thumbCode(
							$rowCopy,
							$table,
							$field,
							'',
							'thumbs.php',
							$config['config']['uploadfolder'], 0, ' align="middle"'
						) .
						($absFilePath ? $this->getControllerDocumentTemplate()->wrapClickMenuOnIcon($fileIcon, $absFilePath, 0, 1, '', '+copy,info,edit,view') : $fileIcon) .
						$imgPath .
						'</span>';
				}
				$thumbnail = implode('<br />', $imgs);
			}
			return $thumbnail;
		} else {
			return nl2br(htmlspecialchars($value));
		}
	}

	/**
	 * Renders the diff-view of default language record content compared with what the record was originally translated from.
	 * Will render content if any is found in the internal array, $this->defaultLanguageData,
	 * depending on registerDefaultLanguageData() being called prior to this.
	 *
	 * @param string $table Table name of the record being edited
	 * @param string $field Field name represented by $item
	 * @param array $row Record array of the record being edited
	 * @param string  $item HTML of the form field. This is what we add the content to.
	 * @return string Item string returned again, possibly with the original value added to.
	 */
	protected function renderDefaultLanguageDiff($table, $field, $row, $item) {
		if (is_array($this->globalOptions['defaultLanguageDataDiff'][$table . ':' . $row['uid']])) {
			// Initialize:
			$dLVal = array(
				'old' => $this->globalOptions['defaultLanguageDataDiff'][$table . ':' . $row['uid']],
				'new' => $this->globalOptions['defaultLanguageData'][$table . ':' . $row['uid']]
			);
			// There must be diff-data:
			if (isset($dLVal['old'][$field])) {
				if ((string)$dLVal['old'][$field] !== (string)$dLVal['new'][$field]) {
					// Create diff-result:
					$t3lib_diff_Obj = GeneralUtility::makeInstance(DiffUtility::class);
					$diffres = $t3lib_diff_Obj->makeDiffDisplay(
						BackendUtility::getProcessedValue($table, $field, $dLVal['old'][$field], 0, 1),
						BackendUtility::getProcessedValue($table, $field, $dLVal['new'][$field], 0, 1)
					);
					$item .= '<div class="t3-form-original-language-diff">
						<div class="t3-form-original-language-diffheader">' .
							htmlspecialchars($this->getLanguageService()->sL('LLL:EXT:lang/locallang_core.xlf:labels.changeInOrig')) .
						'</div>
						<div class="t3-form-original-language-diffcontent">' . $diffres . '</div>
					</div>';
				}
			}
		}
		return $item;
	}

	/**
	 * @return BackendUserAuthentication
	 */
	protected function getBackendUserAuthentication() {
		return $GLOBALS['BE_USER'];
	}

	/**
	 * @return LanguageService
	 */
	protected function getLanguageService() {
		return $GLOBALS['LANG'];
	}

	/**
	 * @return DocumentTemplate
	 */
	protected function getControllerDocumentTemplate() {
		return $GLOBALS['SOBE']->doc;
	}

}
<?php
namespace TYPO3\CMS\Backend\Form\Element;

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

use TYPO3\CMS\Backend\Form\FormEngine;
use TYPO3\CMS\Backend\Form\DataPreprocessor;
use TYPO3\CMS\Backend\Template\DocumentTemplate;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\DatabaseConnection;
use TYPO3\CMS\Lang\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Backend\Form\Utility\FormEngineUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\Utility\IconUtility;
use TYPO3\CMS\Backend\Form\Wizard\SuggestWizard;
use TYPO3\CMS\Backend\Form\Wizard\ValueSliderWizard;

/**
 * Base class for form elements of FormEngine
 */
abstract class AbstractFormElement {

	/**
	 * @var FormEngine
	 */
	protected $formEngine;

	/**
	 * A list of global options given from FormEngine to child elements
	 *
	 * @var array
	 */
	protected $globalOptions = array();

	/**
	 * Default width value for a couple of elements like text
	 *
	 * @var int
	 */
	protected $defaultInputWidth = 30;

	/**
	 * Minimum width value for a couple of elements like text
	 *
	 * @var int
	 */
	protected $minimumInputWidth = 10;

	/**
	 * Maximum width value for a couple of elements like text
	 *
	 * @var int
	 */
	protected $maxInputWidth = 50;

	/**
	 * Constructor function, setting the FormEngine
	 *
	 * @param FormEngine $formEngine
	 */
	public function __construct(FormEngine $formEngine) {
		$this->formEngine = $formEngine;
	}

	/**
	 * Set global options from parent FormEngine instance
	 *
	 * @param array $globalOptions Global options like 'readonly' for all elements
	 * @return AbstractFormElement
	 */
	public function setGlobalOptions(array $globalOptions) {
		$this->globalOptions = $globalOptions;
		return $this;
	}

	/**
	 * Handler for Flex Forms
	 *
	 * @param string $table The table name of the record
	 * @param string $field The field name which this element is supposed to edit
	 * @param array $row The record data array where the value(s) for the field can be found
	 * @param array $additionalInformation An array with additional configuration options.
	 * @return string The HTML code for the TCEform field
	 */
	abstract public function render($table, $field, $row, &$additionalInformation);

	/**
	 * @return bool TRUE if field is set to read only
	 */
	protected function isGlobalReadonly() {
		return isset($this->globalOptions['renderReadonly']) ? $this->globalOptions['renderReadonly'] : FALSE;
	}

	/**
	 * @return bool TRUE if wizards are disabled on a global level
	 */
	protected function isWizardsDisabled() {
		return isset($this->globalOptions['disabledWizards']) ? $this->globalOptions['disableWizards'] : FALSE;
	}

	/**
	 * @return string URL to return to this entry script
	 */
	protected function getReturnUrl() {
		return isset($this->globalOptions['returnUrl']) ? $this->globalOptions['returnUrl'] : '';
	}

	/**
	 * Returns the max width in pixels for a elements like input and text
	 *
	 * @param int $size The abstract size value (1-48)
	 * @return int Maximum width in pixels
	 */
	protected function formMaxWidth($size = 48) {
		$compensationForLargeDocuments = 1.33;
		$compensationForFormFields = 12;

		$size = round($size * $compensationForLargeDocuments);
		return ceil($size * $compensationForFormFields);
	}

	/**
	 * Rendering wizards for form fields.
	 *
	 * @param array $itemKinds Array with the real item in the first value, and an alternative item in the second value.
	 * @param array $wizConf The "wizard" key from the config array for the field (from TCA)
	 * @param string $table Table name
	 * @param array $row The record array
	 * @param string $field The field name
	 * @param array $PA Additional configuration array.
	 * @param string $itemName The field name
	 * @param array $specConf Special configuration if available.
	 * @param bool $RTE Whether the RTE could have been loaded.
	 * @return string The new item value.
	 */
	protected function renderWizards($itemKinds, $wizConf, $table, $row, $field, $PA, $itemName, $specConf, $RTE = FALSE) {
		// Return not changed main item directly if wizards are disabled
		if (!is_array($wizConf) || $this->isWizardsDisabled()) {
			return $itemKinds[0];
		}

		$languageService = $this->getLanguageService();

		$fieldChangeFunc = $PA['fieldChangeFunc'];
		$item = $itemKinds[0];
		$fName = '[' . $table . '][' . $row['uid'] . '][' . $field . ']';
		$md5ID = 'ID' . GeneralUtility::shortmd5($itemName);
		$fieldConfig = $PA['fieldConf']['config'];
		$prefixOfFormElName = 'data[' . $table . '][' . $row['uid'] . '][' . $field . ']';
		$flexFormPath = '';
		if (GeneralUtility::isFirstPartOfStr($PA['itemFormElName'], $prefixOfFormElName)) {
			$flexFormPath = str_replace('][', '/', substr($PA['itemFormElName'], strlen($prefixOfFormElName) + 1, -1));
		}

		// Manipulate the field name (to be the TRUE form field name) and remove
		// a suffix-value if the item is a selector box with renderMode "singlebox":
		$listFlag = '_list';
		if ($PA['fieldConf']['config']['form_type'] == 'select') {
			// Single select situation:
			if ($PA['fieldConf']['config']['maxitems'] <= 1) {
				$listFlag = '';
			} elseif ($PA['fieldConf']['config']['renderMode'] == 'singlebox') {
				$itemName .= '[]';
				$listFlag = '';
			}
		}

		// Contains wizard identifiers enabled for this record type, see "special configuration" docs
		$wizardsEnabledByType = $specConf['wizards']['parameters'];

		$buttonWizards = array();
		$otherWizards = array();
		foreach ($wizConf as $wizardIdentifier => $wizardConfiguration) {
			// If an identifier starts with "_", this is a configuration option like _POSITION and not a wizard
			if ($wizardIdentifier[0] === '_') {
				continue;
			}

			// Sanitize wizard type
			$wizardConfiguration['type'] = (string)$wizardConfiguration['type'];

			// Wizards can be shown based on selected "type" of record. If this is the case, the wizard configuration
			// is set to enableByTypeConfig = 1, and the wizardIdentifier is found in $wizardsEnabledByType
			$wizardIsEnabled = TRUE;
			if (
				isset($wizardConfiguration['enableByTypeConfig'])
				&& (bool)$wizardConfiguration['enableByTypeConfig']
				&& (!is_array($wizardsEnabledByType) || !in_array($wizardIdentifier, $wizardsEnabledByType))
			) {
				$wizardIsEnabled = FALSE;
			}
			// Disable if wizard is for RTE fields only and the handled field is no RTE field or RTE can not be loaded
			if (isset($wizardConfiguration['RTEonly']) && (bool)$wizardConfiguration['RTEonly'] && !$RTE) {
				$wizardIsEnabled = FALSE;
			}
			// Disable if wizard is for not-new records only and we're handling a new record
			if (isset($wizardConfiguration['notNewRecords']) && $wizardConfiguration['notNewRecords'] && !MathUtility::canBeInterpretedAsInteger($row['uid'])) {
				$wizardIsEnabled = FALSE;
			}
			// Wizard types script, colorbox and popup must contain a module name configuration
			if (!isset($wizardConfiguration['module']['name']) && in_array($wizardConfiguration['type'], array('script', 'colorbox', 'popup'), TRUE)) {
				$wizardIsEnabled = FALSE;
			}

			if (!$wizardIsEnabled) {
				continue;
			}

			// Title / icon:
			$iTitle = htmlspecialchars($languageService->sL($wizardConfiguration['title']));
			if (isset($wizardConfiguration['icon'])) {
				$icon = FormEngineUtility::getIconHtml($wizardConfiguration['icon'], $iTitle, $iTitle);
			} else {
				$icon = $iTitle;
			}

			switch ($wizardConfiguration['type']) {
				case 'userFunc':
					$params = array();
					$params['fieldConfig'] = $fieldConfig;
					$params['params'] = $wizardConfiguration['params'];
					$params['exampleImg'] = $wizardConfiguration['exampleImg'];
					$params['table'] = $table;
					$params['uid'] = $row['uid'];
					$params['pid'] = $row['pid'];
					$params['field'] = $field;
					$params['flexFormPath'] = $flexFormPath;
					$params['md5ID'] = $md5ID;
					$params['returnUrl'] = $this->getReturnUrl();

					$params['formName'] = 'editform';
					$params['itemName'] = $itemName;
					$params['hmac'] = GeneralUtility::hmac($params['formName'] . $params['itemName'], 'wizard_js');
					$params['fieldChangeFunc'] = $fieldChangeFunc;
					$params['fieldChangeFuncHash'] = GeneralUtility::hmac(serialize($fieldChangeFunc));

					$params['item'] = &$item;
					$params['icon'] = $icon;
					$params['iTitle'] = $iTitle;
					$params['wConf'] = $wizardConfiguration;
					$params['row'] = $row;
					$formEngineDummy = new FormEngine;
					$otherWizards[] = GeneralUtility::callUserFunction($wizardConfiguration['userFunc'], $params, $formEngineDummy);
					break;

				case 'script':
					$params = array();
					// Including the full fieldConfig from TCA may produce too long an URL
					if ($wizardIdentifier != 'RTE') {
						$params['fieldConfig'] = $fieldConfig;
					}
					$params['params'] = $wizardConfiguration['params'];
					$params['exampleImg'] = $wizardConfiguration['exampleImg'];
					$params['table'] = $table;
					$params['uid'] = $row['uid'];
					$params['pid'] = $row['pid'];
					$params['field'] = $field;
					$params['flexFormPath'] = $flexFormPath;
					$params['md5ID'] = $md5ID;
					$params['returnUrl'] = $this->getReturnUrl();

					// Resolving script filename and setting URL.
					$urlParameters = array();
					if (isset($wizardConfiguration['module']['urlParameters']) && is_array($wizardConfiguration['module']['urlParameters'])) {
						$urlParameters = $wizardConfiguration['module']['urlParameters'];
					}
					$wScript = BackendUtility::getModuleUrl($wizardConfiguration['module']['name'], $urlParameters, '');
					$url = $wScript . (strstr($wScript, '?') ? '' : '?') . GeneralUtility::implodeArrayForUrl('', array('P' => $params));
					$buttonWizards[] =
						'<a class="btn btn-default" href="' . htmlspecialchars($url) . '" onclick="this.blur(); return !TBE_EDITOR.isFormChanged();">'
							. $icon .
						'</a>';
					break;

				case 'popup':
					$params = array();
					$params['fieldConfig'] = $fieldConfig;
					$params['params'] = $wizardConfiguration['params'];
					$params['exampleImg'] = $wizardConfiguration['exampleImg'];
					$params['table'] = $table;
					$params['uid'] = $row['uid'];
					$params['pid'] = $row['pid'];
					$params['field'] = $field;
					$params['flexFormPath'] = $flexFormPath;
					$params['md5ID'] = $md5ID;
					$params['returnUrl'] = $this->getReturnUrl();

					$params['formName'] = 'editform';
					$params['itemName'] = $itemName;
					$params['hmac'] = GeneralUtility::hmac($params['formName'] . $params['itemName'], 'wizard_js');
					$params['fieldChangeFunc'] = $fieldChangeFunc;
					$params['fieldChangeFuncHash'] = GeneralUtility::hmac(serialize($fieldChangeFunc));

					// Resolving script filename and setting URL.
					$urlParameters = array();
					if (isset($wizardConfiguration['module']['urlParameters']) && is_array($wizardConfiguration['module']['urlParameters'])) {
						$urlParameters = $wizardConfiguration['module']['urlParameters'];
					}
					$wScript = BackendUtility::getModuleUrl($wizardConfiguration['module']['name'], $urlParameters, '');
					$url = $wScript . (strstr($wScript, '?') ? '' : '?') . GeneralUtility::implodeArrayForUrl('', array('P' => $params));

					$onlyIfSelectedJS = '';
					if (isset($wizardConfiguration['popup_onlyOpenIfSelected']) && $wizardConfiguration['popup_onlyOpenIfSelected']) {
						$notSelectedText = $languageService->sL('LLL:EXT:lang/locallang_core.xlf:mess.noSelItemForEdit');
						$onlyIfSelectedJS =
							'if (!TBE_EDITOR.curSelected(\'' . $itemName . $listFlag . '\')){' .
								'alert(' . GeneralUtility::quoteJSvalue($notSelectedText) . ');' .
								'return false; .
							}';
					}
					$aOnClick =
						'this.blur();' .
						$onlyIfSelectedJS .
						'vHWin=window.open(' .
							'\'' . $url  . '\'+\'&P[currentValue]=\'+TBE_EDITOR.rawurlencode(' .
								'document.editform[\'' . $itemName . '\'].value,200' .
							')' .
							'+\'&P[currentSelectedValues]=\'+TBE_EDITOR.curSelected(\'' . $itemName . $listFlag . '\'),' .
							'\'popUp' . $md5ID . '\',' .
							'\'' . $wizardConfiguration['JSopenParams'] . '\'' .
						');' .
						'vHWin.focus();' .
						'return false;';

					$buttonWizards[] =
						'<a class="btn btn-default" href="#" onclick="' . htmlspecialchars($aOnClick) . '">' .
							$icon .
						'</a>';
					break;

				case 'colorbox':
					$params = array();
					$params['fieldConfig'] = $fieldConfig;
					$params['params'] = $wizardConfiguration['params'];
					$params['exampleImg'] = $wizardConfiguration['exampleImg'];
					$params['table'] = $table;
					$params['uid'] = $row['uid'];
					$params['pid'] = $row['pid'];
					$params['field'] = $field;
					$params['flexFormPath'] = $flexFormPath;
					$params['md5ID'] = $md5ID;
					$params['returnUrl'] = $this->getReturnUrl();

					$params['formName'] = 'editform';
					$params['itemName'] = $itemName;
					$params['hmac'] = GeneralUtility::hmac($params['formName'] . $params['itemName'], 'wizard_js');
					$params['fieldChangeFunc'] = $fieldChangeFunc;
					$params['fieldChangeFuncHash'] = GeneralUtility::hmac(serialize($fieldChangeFunc));

					// Resolving script filename and setting URL.
					$urlParameters = array();
					if (isset($wizardConfiguration['module']['urlParameters']) && is_array($wizardConfiguration['module']['urlParameters'])) {
						$urlParameters = $wizardConfiguration['module']['urlParameters'];
					}
					$wScript = BackendUtility::getModuleUrl($wizardConfiguration['module']['name'], $urlParameters, '');
					$url = $wScript . (strstr($wScript, '?') ? '' : '?') . GeneralUtility::implodeArrayForUrl('', array('P' => $params));

					$aOnClick =
						'this.blur();' .
						'vHWin=window.open(' .
							'\'' . $url  . '\'+\'&P[currentValue]=\'+TBE_EDITOR.rawurlencode(' .
							'document.editform[\'' . $itemName . '\'].value,200' .
							')' .
							'+\'&P[currentSelectedValues]=\'+TBE_EDITOR.curSelected(\'' . $itemName . $listFlag . '\'),' .
							'\'popUp' . $md5ID . '\',' .
							'\'' . $wizardConfiguration['JSopenParams'] . '\'' .
						');' .
						'vHWin.focus();' .
						'return false;';

					$dim = GeneralUtility::intExplode('x', $wizardConfiguration['dim']);
					$dX = MathUtility::forceIntegerInRange($dim[0], 1, 200, 20);
					$dY = MathUtility::forceIntegerInRange($dim[1], 1, 200, 20);
					$color = $PA['itemFormElValue'] ? ' bgcolor="' . htmlspecialchars($PA['itemFormElValue']) . '"' : '';
					$skinImg = IconUtility::skinImg(
						'',
						$PA['itemFormElValue'] === '' ? 'gfx/colorpicker_empty.png' : 'gfx/colorpicker.png',
						'width="' . $dX . '" height="' . $dY . '"' . BackendUtility::titleAltAttrib(trim($iTitle . ' ' . $PA['itemFormElValue'])) . ' border="0"'
					);
					$otherWizards[] =
						'<table border="0" id="' . $md5ID . '"' . $color . ' style="' . htmlspecialchars($wizardConfiguration['tableStyle']) . '">' .
							'<tr>' .
								'<td>' .
									'<a class="btn btn-default" href="#" onclick="' . htmlspecialchars($aOnClick) . '">' . '<img ' . $skinImg . '>' . '</a>' .
								'</td>' .
							'</tr>' .
						'</table>';
					break;

				case 'slider':
					$params = array();
					$params['fieldConfig'] = $fieldConfig;
					$params['field'] = $field;
					$params['flexFormPath'] = $flexFormPath;
					$params['md5ID'] = $md5ID;
					$params['itemName'] = $itemName;
					$params['fieldChangeFunc'] = $fieldChangeFunc;
					$params['wConf'] = $wizardConfiguration;
					$params['row'] = $row;

					/** @var ValueSliderWizard $wizard */
					$wizard = GeneralUtility::makeInstance(ValueSliderWizard::class);
					$otherWizards[] = $wizard->renderWizard($params);
					break;

				case 'select':
					$fieldValue = array('config' => $wizardConfiguration);
					$TSconfig = FormEngineUtility::getTSconfigForTableRow($table, $row);
					$TSconfig[$field] = $TSconfig[$field]['wizards.'][$wizardIdentifier . '.'];
					$selItems = FormEngineUtility::addSelectOptionsToItemArray(FormEngineUtility::initItemArray($fieldValue), $fieldValue, $TSconfig, $field);
					// Process items by a user function:
					if (!empty($wizardConfiguration['itemsProcFunc'])) {
						$funcConfig = !empty($wizardConfiguration['itemsProcFunc.']) ? $wizardConfiguration['itemsProcFunc.'] : array();
						$dataPreprocessor = GeneralUtility::makeInstance(DataPreprocessor::class);
						$selItems = $dataPreprocessor->procItems($selItems, $funcConfig, $wizardConfiguration, $table, $row, $field);
					}
					$options = array();
					$options[] = '<option>' . $iTitle . '</option>';
					foreach ($selItems as $p) {
						$options[] = '<option value="' . htmlspecialchars($p[1]) . '">' . htmlspecialchars($p[0]) . '</option>';
					}
					if ($wizardConfiguration['mode'] == 'append') {
						$assignValue = 'document.editform[\'' . $itemName . '\'].value=\'\'+this.options[this.selectedIndex].value+document.editform[\'' . $itemName . '\'].value';
					} elseif ($wizardConfiguration['mode'] == 'prepend') {
						$assignValue = 'document.editform[\'' . $itemName . '\'].value+=\'\'+this.options[this.selectedIndex].value';
					} else {
						$assignValue = 'document.editform[\'' . $itemName . '\'].value=this.options[this.selectedIndex].value';
					}
					$otherWizards[] =
						'<select' .
							' id="' . str_replace('.', '', uniqid('tceforms-select-', TRUE)) . '"' .
							' class="form-control tceforms-select tceforms-wizardselect"' .
							' name="_WIZARD' . $fName . '"' .
							' onchange="' . htmlspecialchars($assignValue . ';this.blur();this.selectedIndex=0;' . implode('', $fieldChangeFunc)) . '"'.
						'>' .
							implode('', $options) .
						'</select>';
					break;
				case 'suggest':
					if (!empty($PA['fieldTSConfig']['suggest.']['default.']['hide'])) {
						break;
					}
					/** @var SuggestWizard $suggestWizard */
					$suggestWizard = GeneralUtility::makeInstance(SuggestWizard::class);
					$otherWizards[] = $suggestWizard->renderSuggestSelector($PA['itemFormElName'], $table, $field, $row, $PA);
					break;
			}

			// Hide the real form element?
			if (is_array($wizardConfiguration['hideParent']) || $wizardConfiguration['hideParent']) {
				// Setting the item to a hidden-field.
				$item = $itemKinds[1];
				if (is_array($wizardConfiguration['hideParent'])) {
					// NoneElement does not access formEngine properties, use a dummy for decoupling
					$dummyFormEngine = new FormEngine;
					/** @var NoneElement $noneElement */
					$noneElement = GeneralUtility::makeInstance(NoneElement::class, $dummyFormEngine);
					$elementConfiguration = array(
						'fieldConf' => array(
							'config' => $wizardConfiguration['hideParent'],
						),
						'itemFormElValue' => $PA['itemFormElValue'],
					);
					$item .= $noneElement->render('', '', '', $elementConfiguration);
				}
			}
		}

		// For each rendered wizard, put them together around the item.
		if (!empty($buttonWizards) || !empty($otherWizards)) {
			if ($wizConf['_HIDDENFIELD']) {
				$item = $itemKinds[1];
			}

			$innerContent = '';
			if (!empty($buttonWizards)) {
				$innerContent .= '<div class="btn-group' . ($wizConf['_VERTICAL'] ? ' btn-group-vertical' : '') . '">' . implode('', $buttonWizards) . '</div>';
			}
			$innerContent .= implode(' ', $otherWizards);

			// Position
			$classes = array('form-wizards-wrap');
			if ($wizConf['_POSITION'] === 'left') {
				$classes[] = 'form-wizards-aside';
				$innerContent = '<div class="form-wizards-items">' . $innerContent . '</div><div class="form-wizards-element">' . $item . '</div>';
			} elseif ($wizConf['_POSITION'] === 'top') {
				$classes[] = 'form-wizards-top';
				$innerContent = '<div class="form-wizards-items">' . $innerContent . '</div><div class="form-wizards-element">' . $item . '</div>';
			} elseif ($wizConf['_POSITION'] === 'bottom') {
				$classes[] = 'form-wizards-bottom';
				$innerContent = '<div class="form-wizards-element">' . $item . '</div><div class="form-wizards-items">' . $innerContent . '</div>';
			} else {
				$classes[] = 'form-wizards-aside';
				$innerContent = '<div class="form-wizards-element">' . $item . '</div><div class="form-wizards-items">' . $innerContent . '</div>';
			}
			$item = '
				<div class="' . implode(' ', $classes) . '">
					' . $innerContent . '
				</div>';
		}

		return $item;
	}

	/**
	 * @return LanguageService
	 */
	protected function getLanguageService() {
		return $GLOBALS['LANG'];
	}

	/**
	 * @return BackendUserAuthentication
	 */
	protected function getBackendUserAuthentication() {
		return $GLOBALS['BE_USER'];
	}

	/**
	 * @return DatabaseConnection
	 */
	protected function getDatabaseConnection() {
		return $GLOBALS['TYPO3_DB'];
	}

	/**
	 * @return DocumentTemplate
	 */
	protected function getDocumentTemplate() {
		return $GLOBALS['TBE_TEMPLATE'];
	}

	/**
	 * @return DocumentTemplate
	 */
	protected function getControllerDocumentTemplate() {
		// $GLOBALS['SOBE'] might be any kind of PHP class (controller most of the times)
		// These classes do not inherit from any common class, but they all seem to have a "doc" member
		return $GLOBALS['SOBE']->doc;
	}

}

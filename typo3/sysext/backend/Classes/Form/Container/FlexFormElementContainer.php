<?php
namespace TYPO3\CMS\Backend\Form\Container;

use TYPO3\CMS\Backend\Form\ElementConditionMatcher;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Lang\LanguageService;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Type\Bitmask\JsConfirmation;
use TYPO3\CMS\Backend\Form\NodeFactory;
use TYPO3\CMS\Backend\Form\Utility\FormEngineUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\Form\Container\FlexFormSectionContainer;

class FlexFormElementContainer extends AbstractContainer {

	/**
	 * @return array As defined in initializeResultArray() of AbstractNode
	 */
	public function render() {

		$table = $this->globalOptions['table'];
		$row = $this->globalOptions['databaseRow'];
		$fieldName = $this->globalOptions['fieldName'];
		$flexFormDataStructureArray = $this->globalOptions['flexFormDataStructureArray'];
		$flexFormRowData = $this->globalOptions['flexFormRowData'];
		$flexFormCurrentLanguage = $this->globalOptions['flexFormCurrentLanguage'];
		$flexFormNoEditDefaultLanguage = $this->globalOptions['flexFormNoEditDefaultLanguage'];
		$flexFormFormPrefix = $this->globalOptions['flexFormFormPrefix'];
		$parameterArray = $this->globalOptions['parameterArray'];

		$languageService = $this->getLanguageService();
		$resultArray = $this->initializeResultArray();
		foreach ($flexFormDataStructureArray as $flexFormFieldName => $flexFormFieldArray) {
			if (
				// No item array found at all
				!is_array($flexFormFieldArray)
				// Not a section or container and not a list of single items
				|| (!isset($flexFormFieldArray['type']) && !is_array($flexFormFieldArray['TCEforms']['config']))
			) {
				continue;
			}

			// Section or container
			if ($flexFormFieldArray['type'] === 'array') {
				if (empty($flexFormFieldArray['section'])) {
					$resultArray['html'] = LF . 'Section expected at ' . $flexFormFieldName . ' but not found';
					continue;
				}
debug($flexFormRowData);
				/**
				$options = $this->globalOptions;
				$options['flexFormDataStructureArray'] = $sheetDataStructure['ROOT']['el'];
				$options['flexFormRowData'] = $flexFormRowSheetDataSubPart;
				$options['flexFormFormPrefix'] = '[data][' . $sheetName . '][' . $flexFormCurrentLanguage . ']';
				 */

				$options = $this->globalOptions;
				$options['flexFormDataStructureArray'] = $flexFormFieldArray['el'];
//				$options['flexFormRowData'] =

				/** @var FlexFormSectionContainer $sectionContainer */
				$sectionContainer = GeneralUtility::makeInstance(FlexFormSectionContainer::class);
				$sectionContainerResult = $sectionContainer->setGlobalOptions($options)->render();
//				$resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $sectionContainerResult);
				$resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $this->initializeResultArray());
			} else {
				// Single element
				$vDEFkey = 'vDEF';

				$displayConditionResult = TRUE;
				if (!empty($flexFormFieldArray['TCEforms']['displayCond'])) {
					$conditionData = is_array($flexFormRowData) ? $flexFormRowData : array();
					$conditionData['parentRec'] = $row;
					/** @var $elementConditionMatcher ElementConditionMatcher */
					$elementConditionMatcher = GeneralUtility::makeInstance(ElementConditionMatcher::class);
					$displayConditionResult = $elementConditionMatcher->match($flexFormFieldArray['TCEforms']['displayCond'], $conditionData, $vDEFkey);
				}
				if (!$displayConditionResult) {
					continue;
				}

				// Set up options for single element
				$fakeParameterArray = array(
					'fieldConf' => array(
						'label' => $languageService->sL(trim($flexFormFieldArray['TCEforms']['label'])),
						'config' => $flexFormFieldArray['TCEforms']['config'],
						'defaultExtras' => $flexFormFieldArray['TCEforms']['defaultExtras'],
						'onChange' => $flexFormFieldArray['TCEforms']['onChange'],
					),
				);

				// Force a none field if default language can not be edited
				if ($flexFormNoEditDefaultLanguage && $flexFormCurrentLanguage === 'lDEF') {
					$fakeParameterArray['fieldConf']['config'] = array(
						'type' => 'none',
						'rows' => 2
					);
				}

				$alertMsgOnChange = '';
				if (
					$fakeParameterArray['fieldConf']['onChange'] === 'reload'
					|| !empty($GLOBALS['TCA'][$table]['ctrl']['type']) && $GLOBALS['TCA'][$table]['ctrl']['type'] === $flexFormFieldName
					|| !empty($GLOBALS['TCA'][$table]['ctrl']['requestUpdate']) && GeneralUtility::inList($GLOBALS['TCA'][$table]['ctrl']['requestUpdate'], $flexFormFieldName)
				) {
					if ($this->getBackendUserAuthentication()->jsConfirmation(JsConfirmation::TYPE_CHANGE)) {
						$alertMsgOnChange = 'if (confirm(TBE_EDITOR.labels.onChangeAlert) && TBE_EDITOR.checkSubmit(-1)){ TBE_EDITOR.submitForm() };';
					} else {
						$alertMsgOnChange = 'if(TBE_EDITOR.checkSubmit(-1)){ TBE_EDITOR.submitForm();}';
					}
				}
				$fakeParameterArray['fieldChangeFunc'] = $parameterArray['fieldChangeFunc'];
				if (strlen($alertMsgOnChange)) {
					$fakeParameterArray['fieldChangeFunc']['alert'] = $alertMsgOnChange;
				}

				$fakeParameterArray['onFocus'] = $parameterArray['onFocus'];
				$fakeParameterArray['label'] = $parameterArray['label'];
				$fakeParameterArray['itemFormElName'] = $parameterArray['itemFormElName'] . $flexFormFormPrefix . '[' . $flexFormFieldName . '][' . $vDEFkey . ']';
				$fakeParameterArray['itemFormElName_file'] = $parameterArray['itemFormElName_file'] . $flexFormFormPrefix . '[' . $flexFormFieldName . '][' . $vDEFkey . ']';
				$fakeParameterArray['itemFormElID'] = $fakeParameterArray['itemFormElName'];
				if (isset($flexFormRowData[$flexFormFieldName][$vDEFkey])) {
					$fakeParameterArray['itemFormElValue'] = $flexFormRowData[$flexFormFieldName][$vDEFkey];
				} else {
					$fakeParameterArray['itemFormElValue'] = $fakeParameterArray['fieldConf']['config']['default'];
				}

				$options = $this->globalOptions;
				$options['parameterArray'] = $fakeParameterArray;
				/** @var NodeFactory $nodeFactory */
				$nodeFactory = GeneralUtility::makeInstance(NodeFactory::class);
				$child = $nodeFactory->create($flexFormFieldArray['TCEforms']['config']['type']);
				$childResult = $child->setGlobalOptions($options)->render();

				$theTitle = htmlspecialchars($fakeParameterArray['fieldConf']['label']);
				$defInfo = array();
				if (!$flexFormNoEditDefaultLanguage) {
					$previewLanguages = $this->globalOptions['additionalPreviewLanguages'];
					foreach ($previewLanguages as $previewLanguage) {
						$defInfo[] = '<div class="t3-form-original-language">';
						$defInfo[] = 	FormEngineUtility::getLanguageIcon($table, $row, ('v' . $previewLanguage['ISOcode']));
						$defInfo[] = 	$this->previewFieldValue($flexFormRowData[$flexFormFieldName][('v' . $previewLanguage['ISOcode'])], $fakeParameterArray['fieldConf'], $fieldName);
						$defInfo[] = '</div>';
					}
				}

				$languageIcon = '';
				if ($vDEFkey != 'vDEF') {
					$languageIcon = FormEngineUtility::getLanguageIcon($table, $row, $vDEFkey);
				}

				// Possible line breaks in the label through xml: \n => <br/>, usage of nl2br() not possible, so it's done through str_replace (?!)
				$processedTitle = str_replace('\\n', '<br />', $theTitle);
				// @todo: Similar to the processing within SingleElementContainer ... use it from there?!
				$html = array();
				$html[] = '<div class="form-section">';
				$html[] = 	'<div class="form-group t3js-formengine-palette-field">';
				$html[] = 		'<label class="t3js-formengine-label">';
				$html[] = 			$languageIcon;
				$html[] = 			BackendUtility::wrapInHelp($parameterArray['_cshKey'], $fieldName, $processedTitle);
				$html[] = 		'</label>';
				$html[] = 		'<div class="t3js-formengine-field-item">';
				$html[] = 			$childResult['html'];
				$html[] = 			implode(LF, $defInfo);
				$html[] = 			$this->renderVDEFDiff($flexFormRowData[$flexFormFieldName], $vDEFkey);
				$html[]	= 		'</div>';
				$html[] = 	'</div>';
				$html[] = '</div>';

				$resultArray['html'] .= implode(LF, $html);
				$childResult['html'] = '';
				$resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $childResult);
			}
		}

		return $resultArray;

			/**
			// Traversing fields in structure:
			// The value of each entry must be an array.
			// ********************
			// Making the row:
			// ********************
			// Title of field:
			// <title>LLL:EXT:cms/locallang_ttc.xml:media.sources</title>
			// @todo: title only possible for section / container ?!
			$theTitle = $fieldArray['title'];
			// If there is a title, check for LLL label
			if (strlen($theTitle) > 0) {
				$theTitle = htmlspecialchars(GeneralUtility::fixed_lgd_cs($languageService->sL($theTitle),
					(int)$this->getBackendUserAuthentication()->uc['titleLen']));
			}
			// If it's a "section" or "container":
			if ($fieldArray['type'] == 'array') {
				// Creating IDs for form fields:
				// It's important that the IDs "cascade" - otherwise we can't dynamically expand the flex form
				// because this relies on simple string substitution of the first parts of the id values.
				// This is a suffix used for forms on this level
				$thisId = GeneralUtility::shortMd5(uniqid('id', TRUE));
				// $idPrefix is the prefix for elements on lower levels in the hierarchy and we combine this
				// with the thisId value to form a new ID on this level.
				$idTagPrefix = $idPrefix . '-' . $thisId;
				// If it's a "section" containing other elements:
				if ($fieldArray['section']) {
					// Load script.aculo.us if flexform sections can be moved by drag'n'drop:
					$this->getControllerDocumentTemplate()->getPageRenderer()->loadScriptaculous();
					// Render header of section:
					$output .= '<div class="t3-form-field-label-flexsection"><strong>' . $theTitle . '</strong></div>';
					// Render elements in data array for section:
					$tRows = array();
					if (is_array($editData[$fieldName]['el'])) {
						foreach ($editData[$fieldName]['el'] as $k3 => $v3) {
							$cc = $k3;
							if (is_array($v3)) {
								$theType = key($v3);
								$theDat = $v3[$theType];
								$newSectionEl = $fieldArray['el'][$theType];
								if (is_array($newSectionEl)) {
									$tRows[] = $this->getSingleField_typeFlex_draw(array($theType => $newSectionEl),
										array($theType => $theDat), $table, $field, $row, $PA,
										$formPrefix . '[' . $fieldName . '][el][' . $cc . ']', $level + 1,
										$idTagPrefix, $v3['_TOGGLE']);
								}
							}
						}
					}
					// Now, we generate "templates" for new elements that could be added to this section
					// by traversing all possible types of content inside the section:
					// We have to handle the fact that requiredElements and such may be set during this
					// rendering process and therefore we save and reset the state of some internal variables
					// ... little crude, but works...
					// Preserving internal variables we don't want to change:
					$TEMP_requiredElements = $this->formEngine->requiredElements;
					// Traversing possible types of new content in the section:
					$newElementsLinks = array();
					foreach ($fieldArray['el'] as $nnKey => $nCfg) {
						$additionalJS_post_saved = $this->formEngine->additionalJS_post;
						$this->formEngine->additionalJS_post = array();
						$additionalJS_submit_saved = $this->formEngine->additionalJS_submit;
						$this->formEngine->additionalJS_submit = array();
						$newElementTemplate = $this->getSingleField_typeFlex_draw(array($nnKey => $nCfg),
							array(), $table, $field, $row, $PA,
							$formPrefix . '[' . $fieldName . '][el][' . $idTagPrefix . '-form]', $level + 1,
							$idTagPrefix);
						// Makes a "Add new" link:
						$var = str_replace('.', '', uniqid('idvar', TRUE));
						$replace = 'replace(/' . $idTagPrefix . '-/g,"' . $idTagPrefix . '-"+' . $var . '+"-")';
						$replace .= '.replace(/(tceforms-(datetime|date)field-)/g,"$1" + (new Date()).getTime())';
						$onClickInsert = 'var ' . $var . ' = "' . 'idx"+(new Date()).getTime();'
							// Do not replace $isTagPrefix in setActionStatus() because it needs section id!
							. 'new Insertion.Bottom($("' . $idTagPrefix . '"), ' . json_encode($newElementTemplate)
							. '.' . $replace . '); TYPO3.jQuery("#' . $idTagPrefix . '").t3FormEngineFlexFormElement();'
							. 'eval(unescape("' . rawurlencode(implode(';', $this->formEngine->additionalJS_post)) . '").' . $replace . ');'
							. 'TBE_EDITOR.addActionChecks("submit", unescape("'
							. rawurlencode(implode(';', $this->formEngine->additionalJS_submit)) . '").' . $replace . ');'
							. 'TYPO3.FormEngine.reinitialize();'
							. 'return false;';
						// Kasper's comment (kept for history):
						// Maybe there is a better way to do this than store the HTML for the new element
						// in rawurlencoded format - maybe it even breaks with certain charsets?
						// But for now this works...
						$this->formEngine->additionalJS_post = $additionalJS_post_saved;
						$this->formEngine->additionalJS_submit = $additionalJS_submit_saved;
						$title = '';
						if (isset($nCfg['title'])) {
							$title = $languageService->sL($nCfg['title']);
						}
						$newElementsLinks[] = '<a href="#" onclick="' . htmlspecialchars($onClickInsert) . '">'
							. IconUtility::getSpriteIcon('actions-document-new')
							. htmlspecialchars(GeneralUtility::fixed_lgd_cs($title, 30)) . '</a>';
					}
					// Reverting internal variables we don't want to change:
					$this->formEngine->requiredElements = $TEMP_requiredElements;
					// Adding the sections

					// add the "toggle all" button for the sections
					$toggleAll = $languageService->sL('LLL:EXT:lang/locallang_core.xlf:labels.toggleall', TRUE);
					$output .= '
					<div class="t3-form-field-toggle-flexsection t3-form-flexsection-toggle">
						<a href="#">'. IconUtility::getSpriteIcon('actions-move-right', array('title' => $toggleAll)) . $toggleAll . '</a>
					</div>
					<div id="' . $idTagPrefix . '" class="t3-form-field-container-flexsection t3-flex-container" data-t3-flex-allow-restructure="' . ($mayRestructureFlexforms ? 1 : 0) . '">' . implode('', $tRows) . '</div>';

					// add the "new" link
					if ($mayRestructureFlexforms) {
						$output .= '<div class="t3-form-field-add-flexsection"><strong>'
							. $languageService->sL('LLL:EXT:lang/locallang_core.xlf:labels.addnew', TRUE)
							. ':</strong> ' . implode(' | ', $newElementsLinks) . '</div>';
					}

					$output = '<div class="t3-form-field-container t3-form-flex">' . $output . '</div>';
				} else {
					// It is a container of a single section
					$toggleIconOpenState  =  ($toggleClosed ? 'display: none;' : '');
					$toggleIconCloseState = (!$toggleClosed ? 'display: none;' : '');

					$toggleIcons = IconUtility::getSpriteIcon('actions-move-down', array('class' => 't3-flex-control-toggle-icon-open', 'style' => $toggleIconOpenState));
					$toggleIcons .= IconUtility::getSpriteIcon('actions-move-right', array('class' => 't3-flex-control-toggle-icon-close', 'style' => $toggleIconCloseState));

					// Notice: Creating "new" elements after others seemed to be too difficult to do
					// and since moving new elements created in the bottom is now so easy
					// with drag'n'drop I didn't see the need.
					// Putting together header of a section. Sections can be removed, copied, opened/closed, moved up and down:
					// I didn't know how to make something right-aligned without a table, so I put it in a table.
					// can be made into <div>'s if someone like to.
					// Notice: The fact that I make a "Sortable.create" right onmousedown is that if we
					// initialize this when rendering the form in PHP new and copied elements will not
					// be possible to move as a sortable. But this way a new sortable is initialized every time
					// someone tries to move and it will always work.
					$ctrlHeader = '
						<div class="pull-left">
							<a href="#" class="t3-flex-control-toggle-button">' . $toggleIcons . '</a>
							<span class="t3-record-title">' . $theTitle . '</span>
						</div>';

					if ($mayRestructureFlexforms) {
						$ctrlHeader .= '<div class="pull-right">'
							. IconUtility::getSpriteIcon('actions-move-move', array('title' => 'Drag to Move', 'class' => 't3-js-sortable-handle'))
							. IconUtility::getSpriteIcon('actions-edit-delete', array('title' => 'Delete', 'class' => 't3-delete'))
							. '</div>';
					}

					$ctrlHeader = '<div class="t3-form-field-header-flexsection t3-flex-section-header">' . $ctrlHeader . '</div>';

					$s = GeneralUtility::revExplode('[]', $formPrefix, 2);
					$actionFieldName = '_ACTION_FLEX_FORM' . $PA['itemFormElName'] . $s[0] . '][_ACTION][' . $s[1];
					// Push the container to DynNestedStack as it may be toggled
					$this->formEngine->pushToDynNestedStack('flex', $idTagPrefix);
					// Putting together the container:
					$this->formEngine->additionalJS_delete = array();
					$singleField_typeFlex_draw = $this->getSingleField_typeFlex_draw($fieldArray['el'],
						$editData[$fieldName]['el'], $table, $field, $row, $PA,
						($formPrefix . '[' . $fieldName . '][el]'), ($level + 1), $idTagPrefix);
					$output .= '
						<div id="' . $idTagPrefix . '" class="t3-form-field-container-flexsections t3-flex-section">
							<input class="t3-flex-control t3-flex-control-action" type="hidden" name="' . htmlspecialchars($actionFieldName) . '" value=""/>

							' . $ctrlHeader . '
							<div class="t3-form-field-record-flexsection t3-flex-section-content"'
						. ($toggleClosed ? ' style="display:none;"' : '') . '>' . $singleField_typeFlex_draw . '
							</div>
							<input class="t3-flex-control t3-flex-control-toggle" id="' . $idTagPrefix . '-toggleClosed" type="hidden" name="'
						. htmlspecialchars('data[' . $table . '][' . $row['uid'] . '][' . $field . ']' . $formPrefix . '[_TOGGLE]')
						. '" value="' . ($toggleClosed ? 1 : 0) . '" />
						</div>';
			 // hacked line
					$output = str_replace('###REMOVE###', GeneralUtility::slashJS(htmlspecialchars(implode('', $this->formEngine->additionalJS_delete))), $output);
					// NOTICE: We are saving the toggle-state directly in the flexForm XML and "unauthorized"
					// according to the data structure. It means that flexform XML will report unclean and
					// a cleaning operation will remove the recorded togglestates. This is not a fatal problem.
					// Ideally we should save the toggle states in meta-data but it is much harder to do that.
					// And this implementation was easy to make and with no really harmful impact.
					// Pop the container from DynNestedStack
					$this->formEngine->popFromDynNestedStack('flex', $idTagPrefix);
				}
			*/
	}

	/**
	 * Renders the diff-view of vDEF fields in flex forms
	 *
	 * @param array $vArray Record array of the record being edited
	 * @param string $vDEFkey HTML of the form field. This is what we add the content to.
	 * @return string Item string returned again, possibly with the original value added to.
	 */
	protected function renderVDEFDiff($vArray, $vDEFkey) {
		$item = NULL;
		if (
			$GLOBALS['TYPO3_CONF_VARS']['BE']['flexFormXMLincludeDiffBase'] && isset($vArray[$vDEFkey . '.vDEFbase'])
			&& (string)$vArray[$vDEFkey . '.vDEFbase'] !== (string)$vArray['vDEF']
		) {
			// Create diff-result:
			$t3lib_diff_Obj = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Utility\DiffUtility::class);
			$diffres = $t3lib_diff_Obj->makeDiffDisplay($vArray[$vDEFkey . '.vDEFbase'], $vArray['vDEF']);
			$item = '<div class="typo3-TCEforms-diffBox">' . '<div class="typo3-TCEforms-diffBox-header">'
				. htmlspecialchars($this->getLanguageService()->sL('LLL:EXT:lang/locallang_core.xlf:labels.changeInOrig')) . ':</div>' . $diffres . '</div>';
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

}

<?php
namespace TYPO3\CMS\Backend\Form\Container;

use TYPO3\CMS\Core\Utility\GeneralUtility;

class FlexFormSectionContainer extends AbstractContainer {

	/**
	 * @return array As defined in initializeResultArray() of AbstractNode
	 */
	public function render() {
		$flexFormFieldArray = $this->globalOptions['flexFormDataStructureArray'];
		$flexFormRowData = $this->globalOptions['flexFormRowData'];
		$flexFormFieldIdentifierPrefix = $this->globalOptions['flexFormFieldIdentifierPrefix'];

		debug($flexFormFieldArray);
		debug($flexFormRowData);

		$resultArray = $this->initializeResultArray();
//		$result['html'] = 'aoeu';
//		return $result;

		// Creating IDs for form fields:
		// It's important that the IDs "cascade" - otherwise we can't dynamically expand the flex form
		// because this relies on simple string substitution of the first parts of the id values.
		// Value is initialized in FlexFormElementContainer and appended here.
		$flexFormFieldIdentifierPrefix = $flexFormFieldIdentifierPrefix . '-' . GeneralUtility::shortMd5(uniqid('id', TRUE));

		foreach ($flexFormRowData as $existingSectionContainerCounter => $existingSectionContainerData) {
			// @todo: This relies on the fact that "_TOGGLE" is *below* the real data in the saved array
			if (is_array($existingSectionContainerData)) {
				$existingSectionContainerDataStructureType = key($existingSectionContainerData);
				$existingSectionContainerData = $existingSectionContainerData[$existingSectionContainerDataStructureType];
				$containerDataStructure = $flexFormFieldArray[$existingSectionContainerDataStructureType];
				// There may be cases where a field is still in DB but does not exist is definition
				if (is_array($containerDataStructure)) {
					$options = $this->globalOptions;
					$options['flexFormRowData'] = $existingSectionContainerData['el'];
					$options['flexFormDataStructureArray'] = $containerDataStructure['el'];
					$options['flexFormFieldIdentifierPrefix'] = $flexFormFieldIdentifierPrefix;
					/** @var FlexFormElementContainer $flexFormElementContainer */
					$flexFormElementContainer = GeneralUtility::makeInstance(FlexFormElementContainer::class);
					$flexFormElementContainerResult = $flexFormElementContainer->setGlobalOptions($options)->render();
					$resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $flexFormElementContainerResult);
				}
			}
		}

		return $resultArray;

			/**
		// Render elements in data array for section:
		$tRows = array();

				if (is_array($existingSectionContainerData)) {
					$newSectionEl = $fieldArray['el'][$theType];
					if (is_array($newSectionEl)) {
						$tRows[] = $this->getSingleField_typeFlex_draw(
							array($theType => $newSectionEl),
							array($theType => $theDat),
							$table,
							$field,
							$row,
							$PA,
							$formPrefix . '[' . $fieldName . '][el][' . $existingSectionContainerCounter . ']', $level + 1,
							$idTagPrefix,
							$existingSectionContainerData['_TOGGLE']
						);
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

*/

	}

}

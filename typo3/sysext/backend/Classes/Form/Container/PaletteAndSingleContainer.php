<?php
namespace TYPO3\CMS\Backend\Form\Container;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Lang\LanguageService;
use TYPO3\CMS\Backend\Utility\IconUtility;

class PaletteAndSingleContainer extends AbstractContainer {

	public function render() {
		$languageService = $this->getLanguageService();
		$table = $this->globalOptions['table'];

		/**
		$targetStructure = array(
			0 => array(
				'type' => 'palette',
				'fieldName' => 'palette1',
				'fieldLabel' => 'palette1',
				'elements' => array(
					0 => array(
						'type' => 'single',
						'fieldName' => 'palettenName',
						'fieldLabel' => 'element1',
						'fieldHtml' => 'element1',
					),
					1 => array(
						'type' => 'linebreak',
					),
					2 => array(
						'type' => 'single',
						'fieldName' => 'palettenName',
						'fieldLabel' => 'element2',
						'fieldHtml' => 'element2',
					),
				),
			),
			1 => array( // has 2 as "additional palette"
				'type' => 'single',
				'fieldName' => 'element3',
				'fieldLabel' => 'element3',
				'fieldHtml' => 'element3',
			),
			2 => array( // do only if 1 had result
				'type' => 'palette2',
				'fieldName' => 'palette2',
				'fieldLabel' => '', // label missing because label of 1 is displayed only
				'canNotCollapse' => TRUE, // An "additional palette" can not be collapsed
				'elements' => array(
					0 => array(
						'type' => 'single',
						'fieldName' => 'element4',
						'fieldLabel' => 'element4',
						'fieldHtml' => 'element4',
					),
					1 => array(
						'type' => 'linebreak',
					),
					2 => array(
						'type' => 'single',
						'fieldName' => 'element5',
						'fieldLabel' => 'element5',
						'fieldHtml' => 'element5',
					),
				),
			),
		);
		 */

		// Create an intermediate structure of rendered sub elements and elements nested in palettes
		$targetStructure = array();
		$mainStructureCounter = -1;
		$fieldsArray = $this->globalOptions['fieldsArray'];
		foreach ($fieldsArray as $fieldString) {
			$fieldConfiguration = $this->explodeSingleFieldShowItemConfiguration($fieldString);
			$fieldName = $fieldConfiguration['fieldName'];
			if ($fieldName === '--palette--') {
				$paletteElementArray = $this->createPaletteContentArray($fieldConfiguration['paletteName']);
				if (count($paletteElementArray)) {
					$mainStructureCounter ++;
					$targetStructure[$mainStructureCounter] = array(
						'type' => 'palette',
						'fieldName' => $fieldConfiguration['paletteName'],
						'fieldLabel' => $languageService->sL($fieldConfiguration['fieldLabel']),
						'elements' => $paletteElementArray,
					);
				}
			} else {
				$options = $this->globalOptions;
				$options['fieldName'] = $fieldName;
				// @todo: fieldLabel still needed later?
				$options['fieldLabel'] = $fieldConfiguration['fieldLabel'];
				$options['fieldExtra'] = $fieldConfiguration['fieldExtra'];

				/** @var SingleFieldContainer $singleFieldContainer */
				$singleFieldContainer = GeneralUtility::makeInstance(SingleFieldContainer::class);
				$singleFieldContainer->setGlobalOptions($options);
				$singleFieldContentArray = $singleFieldContainer->render();

				if ($singleFieldContentArray) {
					$mainStructureCounter ++;

					$targetStructure[$mainStructureCounter] = array(
						'type' => 'single',
						'fieldName' => $fieldConfiguration['fieldName'],
						'fieldLabel' => $singleFieldContentArray['label'],
						'fieldHtml' => $singleFieldContentArray['html'],
					);

					// If the third part of a show item field is given, this is a name of a palette that should be rendered
					// below the single field - without palette header and only if single field produced content
					if ($singleFieldContentArray && !empty($fieldConfiguration['paletteName'])) {
						$paletteElementArray = $this->createPaletteContentArray($fieldConfiguration['paletteName']);
						if (count($paletteElementArray)) {
							$mainStructureCounter ++;
							$targetStructure[$mainStructureCounter] = array(
								'type' => 'palette',
								'fieldName' => $fieldConfiguration['paletteName'],
								'fieldLabel' => '', // An "additional palette" has no show label
								'canNotCollapse' => TRUE,
								'elements' => $paletteElementArray,
							);
						}
					}
				}
			}
		}

		$content = array();
		foreach ($targetStructure as $element) {
			if ($element['type'] === 'palette') {
				$paletteName = $element['fieldName'];
				$paletteElementsHtml = $this->renderInnerPaletteContent($element);

				$isHiddenPalette = !empty($GLOBALS['TCA'][$table]['palettes'][$paletteName]['isHiddenPalette']);

				$renderUnCollapseButtonWrapper = TRUE;
				// No button if the palette is hidden
				if ($isHiddenPalette) {
					$renderUnCollapseButtonWrapper = FALSE;
				}
				// No button if palette can not collapse on ctrl level
				if (!empty($GLOBALS['TCA'][$table]['ctrl']['canNotCollapse'])) {
					$renderUnCollapseButtonWrapper = FALSE;
				}
				// No button if palette can not collapse on palette definition level
				if (!empty($GLOBALS['TCA'][$table]['palettes'][$paletteName]['canNotCollapse'])) {
					$renderUnCollapseButtonWrapper = FALSE;
				}
				// No button if palettes are not collapsed - this is the checkbox at the end of the form
				if (!$this->globalOptions['palettesCollapsed']) {
					$renderUnCollapseButtonWrapper = FALSE;
				}
				// No button if palette is set to no collapse on element level - this is the case if palette is an "additional palette" after a casual field
				if (!empty($element['canNotCollapse'])) {
					$renderUnCollapseButtonWrapper = FALSE;
				}

				if ($renderUnCollapseButtonWrapper) {
					$cssId = 'FORMENGINE_' . $this->globalOptions['table'] . '_' . $paletteName . '_' . $this->globalOptions['uid'];
					$paletteElementsHtml = $this->wrapPaletteWithCollapseButton($paletteElementsHtml, $cssId);
				} else {
					$paletteElementsHtml = '<div class="row">' . $paletteElementsHtml . '</div>';
				}

				$content[] = $this->fieldSetWrap($paletteElementsHtml, $isHiddenPalette, $element['fieldLabel']);
			} else {
				$content[] = $this->fieldSetWrap($this->wrapSingleFieldContent($element));
			}
		}

		return implode(LF, $content);
	}

	protected function createPaletteContentArray($paletteName) {
		$table = $this->globalOptions['table'];
		$excludeElements = $this->globalOptions['excludeElements'];

		// palette needs a palette name reference, otherwise it does not make sense to try rendering of it
		if (empty($paletteName) || empty($GLOBALS['TCA'][$table]['palettes'][$paletteName]['showitem'])) {
			return array();
		}

		$resultStructure = array();
		$foundRealElement = FALSE; // Set to true if not only line breaks were rendered
		$fieldsArray = GeneralUtility::trimExplode(',', $GLOBALS['TCA'][$table]['palettes'][$paletteName]['showitem'], TRUE);
		foreach ($fieldsArray as $fieldString) {
			$fieldArray = $this->explodeSingleFieldShowItemConfiguration($fieldString);
			$fieldName = $fieldArray['fieldName'];
			if ($fieldName === '--linebreak--') {
				$resultStructure[] = array(
					'type' => 'linebreak',
				);
			} else {
				if (in_array($fieldName, $excludeElements) || !is_array($GLOBALS['TCA'][$table]['columns'][$fieldName])) {
					continue;
				}
				$options = $this->globalOptions;
				$options['fieldName'] = $fieldName;
				$options['fieldLabel'] = $fieldArray['fieldLabel'];
				$options['fieldExtra'] = $fieldArray['fieldExtra'];

				/** @var SingleFieldContainer $singleFieldContainer */
				$singleFieldContainer = GeneralUtility::makeInstance(SingleFieldContainer::class);
				$singleFieldContainer->setGlobalOptions($options);
				$content = $singleFieldContainer->render();

				if ($content) {
					$foundRealElement = TRUE;
					$resultStructure[] = array(
						'type' => 'single',
						'fieldName' => $fieldName,
						'fieldLabel' => $content['label'],
						'fieldHtml' => $content['html'],
					);
				}
			}
		}

		if ($foundRealElement) {
			return $resultStructure;
		} else {
			return array();
		}
	}

	/**
	 * Renders inner content of single elements of a palette

	 * @param array $elementArray Array of elements
	 * @return string
	 */
	protected function renderInnerPaletteContent(array $elementArray) {
		// Group fields
		$groupedFields = array();
		$row = 0;
		$lastLineWasLinebreak = TRUE;
		foreach ($elementArray['elements'] as $element) {
			if ($element['type'] === 'linebreak') {
				if (!$lastLineWasLinebreak) {
					$row++;
					$groupedFields[$row][] = $element;
					$lastLineWasLinebreak = TRUE;
				}
			} else {
				$lastLineWasLinebreak = FALSE;
				$groupedFields[$row][] = $element;
			}
		}

		$result = array();
		// Process fields
		foreach ($groupedFields as $fields) {
			$numberOfItems = count($fields);
			$colWidth = (int)floor(12 / $numberOfItems);
			// Column class calculation
			$colClass = "col-md-12";
			$colClear = array();
			if ($colWidth == 6) {
				$colClass = "col-sm-6";
				$colClear = array(
					2 => 'visible-sm-block visible-md-block visible-lg-block',
				);
			} elseif ($colWidth === 4) {
				$colClass = "col-sm-4";
				$colClear = array(
					3 => 'visible-sm-block visible-md-block visible-lg-block',
				);
			} elseif ($colWidth === 3) {
				$colClass = "col-sm-6 col-md-3";
				$colClear = array(
					2 => 'visible-sm-block',
					4 => 'visible-md-block visible-lg-block',
				);
			} elseif ($colWidth <= 2) {
				$colClass = "checkbox-column col-sm-6 col-md-3 col-lg-2";
				$colClear = array(
					2 => 'visible-sm-block',
					4 => 'visible-md-block',
					6 => 'visible-lg-block'
				);
			}

			// Render fields
			for ($counter = 0; $counter < $numberOfItems; $counter++) {
				$element = $fields[$counter];
				if ($element['type'] === 'linebreak') {
					if ($counter !== $numberOfItems) {
						$result[] = '<div class="clearfix"></div>';
					}
				} else {
					$result[] = $this->wrapSingleFieldContent($element, array($colClass));

					// Breakpoints
					if ($counter + 1 < $numberOfItems && !empty($colClear)) {
						foreach ($colClear as $rowBreakAfter => $clearClass) {
							if (($counter + 1) % $rowBreakAfter === 0) {
								$result[] = '<div class="clearfix '. $clearClass . '"></div>';
							}
						}
					}
				}
			}
		}

		return implode(LF, $result);
	}

	protected function wrapPaletteWithCollapseButton($elementHtml, $cssId) {
		$content = array();
		$content[] = '<p>';
		$content[] = 	'<button class="btn btn-default" type="button" data-toggle="collapse" data-target="#' . $cssId . '" aria-expanded="false" aria-controls="' . $cssId . '">';
		$content[] = 		IconUtility::getSpriteIcon('actions-system-options-view');
		$content[] = 		htmlspecialchars($this->getLanguageService()->sL('LLL:EXT:lang/locallang_core.xlf:labels.moreOptions'));
		$content[] = 	'</button>';
		$content[] = '</p>';
		$content[] = '<div id="' . $cssId . '" class="form-section-collapse collapse">';
		$content[] = 	'<div class="row">' . $elementHtml . '</div>';
		$content[] = '</div>';
		return implode(LF, $content);
	}

	protected function fieldSetWrap($content, $paletteHidden = FALSE, $label = '') {
		$fieldSetClass = 'form-section';
		if ($paletteHidden) {
			$fieldSetClass = 'hide';
		}

		$result = array();
		$result[] = '<fieldset class="' . $fieldSetClass . '">';

		if (!empty($label)) {
			$result[] = '<h4 class="form-section-headline">' . htmlspecialchars($label) . '</h4>';
		}

		$result[] = $content;
		$result[] = '</fieldset>';
		return implode(LF, $result);
	}

	protected function wrapSingleFieldContent($element, array $additionalPaletteClasses = array()) {
		$paletteFieldClasses = array(
			'form-group',
			't3js-formengine-palette-field',
		);
		foreach ($additionalPaletteClasses as $class) {
			$paletteFieldClasses[] = $class;
		}
		$content = array();
		$content[] = '<div class="' . implode(' ', $paletteFieldClasses) . '">';
		$content[] = 	'<label class="t3js-formengine-label">';
		$content[] = 		$element['fieldLabel']; // @todo: htmlspecialchars?!
		$content[] = 		'<img name="req_' . $this->globalOptions['table'] . '_' . $this->globalOptions['databaseRow']['uid'] . '_' . $element['fieldName'] . '" src="clear.gif" class="t3js-formengine-field-required" alt="" />';
		$content[] = 	'</label>';
// @todo
		$content[] = 	'<div class="t3js-formengine-field-item ' . /** ($this->isDisabledNullValueField($table, $fieldName, $row, $parameterArray) ? ' disabled' : '') .*/ '">';
		$content[] = 		'<div class="t3-form-field-disable"></div>';
// @todo
//		$content[] = 		$this->renderNullValueWidget($table, $fieldName, $row, $parameterArray);
		$content[] = 		$element['fieldHtml'];
		$content[] = 	'</div>';
		$content[] = '</div>';

		return implode(LF, $content);
	}

	/**
	 * @return LanguageService
	 */
	protected function getLanguageService() {
		return $GLOBALS['LANG'];
	}

}
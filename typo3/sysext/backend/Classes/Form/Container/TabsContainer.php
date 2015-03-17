<?php
namespace TYPO3\CMS\Backend\Form\Container;

use TYPO3\CMS\Lang\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Backend\Template\DocumentTemplate;

class TabsContainer extends AbstractContainer {

	public function render() {
		$languageService = $this->getLanguageService();

		// All the fields to handle in a flat list
		$fieldsArray = $this->globalOptions['fieldsArray'];

		// Create a nested array from flat fieldArray list
		$tabsArray = array();
		// First element will be a --div--, so it is safe to start -1 here to trigger 0 as first array index
		$currentTabIndex = -1;
		foreach ($fieldsArray as $fieldString) {
			$fieldArray = $this->explodeSingleFieldShowItemConfiguration($fieldString);
			if ($fieldArray['fieldName'] === '--div--') {
				$currentTabIndex++;
				if (empty($fieldArray['fieldLabel'])) {
					throw new \RuntimeException(
						'A --div-- has no label (--div--;fieldLabel) in showitem of ' . implode(',', $fieldsArray),
						1426454001
					);
				}
				$tabsArray[$currentTabIndex] = array(
					'label' => $languageService->sL($fieldArray['fieldLabel']),
					'elements' => array(),
				);
			} else {
				$tabsArray[$currentTabIndex]['elements'][] = $fieldArray;
			}
		}

		// Iterate over the tabs and compile content in $tabsContent array together with label
		$tabsContent = array();
		foreach ($tabsArray as $tabWithLabelAndElements) {
			$elements = $tabWithLabelAndElements['elements'];

			// Merge elements of this tab into a single list again and hand over to
			// palette and single field container to render this group
			$options = $this->globalOptions;
			$options['fieldsArray'] = array();
			foreach($elements as $element) {
				$options['fieldsArray'][] = implode(';', $element);
			}
			/** @var PaletteAndSingleContainer $paletteAndSingleContainer */
			$paletteAndSingleContainer = GeneralUtility::makeInstance(PaletteAndSingleContainer::class);
			$paletteAndSingleContainer->setGlobalOptions($options);
			$content = $paletteAndSingleContainer->render();

			$tabsContent[] = array(
				'label' => $tabWithLabelAndElements['label'],
				'content' => $content,
			);
		}

		// Feed everything to document template for tab rendering
		$tabId = 'TCEforms:' . $this->globalOptions['table'] . ':' . $this->globalOptions['row']['uid'];
		$docTemplate = $this->getDocumentTemplate();
		return $docTemplate->getDynamicTabMenu($tabsContent, $tabId, 1, FALSE, FALSE);
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
	protected function getDocumentTemplate() {
		$docTemplate = $GLOBALS['TBE_TEMPLATE'];
		if (!is_object($docTemplate)) {
			throw new \RuntimeException('No instance of DocumentTemplate found', 1426459735);
		}
		return $docTemplate;
	}

}

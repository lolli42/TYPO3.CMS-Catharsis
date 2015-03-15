<?php
namespace TYPO3\CMS\Backend\Form\Container;

use TYPO3\CMS\Lang\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Backend\Template\DocumentTemplate;

class TabsContainer extends AbstractContainer {

	public function render() {
		$languageService = $this->getLanguageService();

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
						'A --div-- is missing a label (--div--;fieldLabel) in showitem of ' . implode(',', $fieldsArray),
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

		$tabsContent = array();
		foreach ($tabsArray as $tabWithLabelAndElements) {
			$elements = $tabWithLabelAndElements['elements'];
			$content = array();
			foreach ($elements as $element) {
				$fieldName = $element['fieldName'];
				if ($fieldName === '--palette--') {
					// @todo: implement a palette definition at 'paletteName'
				} else {
					// @todo: same as in NoTabContainer -> method
					$options = $this->globalOptions;
					$options['fieldName'] = $element['fieldName'];
					$options['fieldLabel'] = $element['fieldLabel'];
					$options['fieldExtra'] = $element['fieldExtra'];

					/** @var SingleFieldContainer $singleFieldContainer */
					$singleFieldContainer = GeneralUtility::makeInstance(SingleFieldContainer::class);
					$singleFieldContainer->setGlobalOptions($options);
					$content[] = $singleFieldContainer->render();
				}
			}
			$tabsContent[] = array(
				'label' => $tabWithLabelAndElements['label'],
				'content' => implode(LF, $content),
			);
		}
		$tabId = 'TCEforms:' . $this->globalOptions['table'] . ':' . $this->globalOptions['row']['uid'];
		return $this->renderTabMenu($tabsContent, $tabId);
	}

	/**
	 * Create dynamic tab menu
	 *
	 * @param array $menuItems Items for the tab menu, fed to template::getDynTabMenu()
	 * @param string $identifier ID string for the tab menu
	 * @return string HTML for the menu
	 */
	protected function renderTabMenu($menuItems, $identifier) {
		$docTemplate = $this->getDocumentTemplate();
		// @todo: What it that for?
		$docTemplate->backPath = '';
		return $docTemplate->getDynamicTabMenu($menuItems, $identifier, 1, FALSE, FALSE);
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

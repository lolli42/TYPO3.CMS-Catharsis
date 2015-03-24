<?php
namespace TYPO3\CMS\Backend\Form\Container;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Lang\LanguageService;
use TYPO3\CMS\Backend\Form\FlexFormsHelper;

class FlexFormContainer extends AbstractContainer {

	/**
	 * @return array As defined in initializeResultArray() of AbstractNode
	 */
	public function render() {
		$languageService = $this->getLanguageService();

		$table = $this->globalOptions['table'];
		$row = $this->globalOptions['databaseRow'];
		$field = $this->globalOptions['field'];
		$parameterArray = $this->globalOptions['parameterArray'];

		// Data Structure:
		$flexFormDataStructureArray = BackendUtility::getFlexFormDS($parameterArray['fieldConf']['config'], $row, $table, $field);

		// Early return if no data structure was found at all
		if (!is_array($flexFormDataStructureArray)) {
			$resultArray = $this->initializeResultArray();
			$resultArray['html'] = 'Data Structure ERROR: ' . $flexFormDataStructureArray;
			return $resultArray;
		}

		// Manipulate Flexform DS via TSConfig and group access lists
		if (is_array($flexFormDataStructureArray)) {
			$flexFormHelper = GeneralUtility::makeInstance(FlexFormsHelper::class);
			$flexFormDataStructureArray = $flexFormHelper->modifyFlexFormDS($flexFormDataStructureArray, $table, $field, $row, $parameterArray['fieldConf']);
		}

		// Get data:
		$xmlData = $parameterArray['itemFormElValue'];
		$xmlHeaderAttributes = GeneralUtility::xmlGetHeaderAttribs($xmlData);
		$storeInCharset = strtolower($xmlHeaderAttributes['encoding']);
		if ($storeInCharset) {
			$currentCharset = $languageService->charSet;
			$xmlData = $languageService->csConvObj->conv($xmlData, $storeInCharset, $currentCharset, 1);
		}
		$flexFormRowData = GeneralUtility::xml2array($xmlData);

		// Must be XML parsing error...
		if (!is_array($flexFormRowData)) {
			$flexFormRowData = array();
		} elseif (!isset($flexFormRowData['meta']) || !is_array($flexFormRowData['meta'])) {
			$flexFormRowData['meta'] = array();
		}

		$options = $this->globalOptions;
		$options['flexFormDataStructureArray'] = $flexFormDataStructureArray;
		$options['flexFormRowData'] = $flexFormRowData;
		/** @var FlexFormLanguageContainer $flexFormLanguageContainer */
		$flexFormLanguageContainer = GeneralUtility::makeInstance(FlexFormLanguageContainer::class);
		return $flexFormLanguageContainer->setGlobalOptions($options)->render();
	}

	/**
	 * @return LanguageService
	 */
	protected function getLanguageService() {
		return $GLOBALS['LANG'];
	}

}
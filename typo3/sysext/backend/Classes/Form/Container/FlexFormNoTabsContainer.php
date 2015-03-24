<?php
namespace TYPO3\CMS\Backend\Form\Container;

use TYPO3\CMS\Core\Utility\GeneralUtility;

class FlexFormNoTabsContainer extends AbstractContainer {

	/**
	 * @return array As defined in initializeResultArray() of AbstractNode
	 */
	public function render() {
		$table = $this->globalOptions['table'];
		$row = $this->globalOptions['databaseRow'];
		$fieldName = $this->globalOptions['fieldName']; // field name of the flex form field in DB
		$parameterArray = $this->globalOptions['parameterArray'];
		$flexFormDataStructureArray = $this->globalOptions['flexFormDataStructureArray'];
		$flexFormSheetNameInRowData = 'sDEF';
		$flexFormCurrentLanguage = $this->globalOptions['flexFormCurrentLanguage'];
		$flexFormRowData = $this->globalOptions['flexFormRowData'];
		$flexFormRowDataSubPart = $flexFormRowData['data'][$flexFormSheetNameInRowData][$flexFormCurrentLanguage];
		$resultArray = $this->initializeResultArray();

		// That was taken from GeneralUtility::resolveSheetDefInDS - no idea if it is important
		unset($flexFormDataStructureArray['meta']);

		// Evaluate display condition for this "sheet" if there is one
		$displayConditionResult = TRUE;
		if (!empty($flexFormDataStructureArray['ROOT']['TCEforms']['displayCond'])) {
			$displayConditionDefinition = $flexFormDataStructureArray['ROOT']['TCEforms']['displayCond'];
			$displayConditionResult = $this->evaluateFlexFormDisplayCondition(
				$displayConditionDefinition,
				$flexFormRowDataSubPart
			);
		}
		if (!$displayConditionResult) {
			return $resultArray;
		}

		if (!is_array($flexFormDataStructureArray['ROOT']['el'])) {
			$resultArray['html'] = 'Data Structure ERROR: No [\'ROOT\'][\'el\'] element found in flex form definition.';
			return $resultArray;
		}

		// Assemble key for loading the correct CSH file
		// @todo: what is that good for? That is for the title of single elements ... see FlexFormElementContainer!
		$dsPointerFields = GeneralUtility::trimExplode(',', $GLOBALS['TCA'][$table]['columns'][$fieldName]['config']['ds_pointerField'], TRUE);
		$parameterArray['_cshKey'] = $table . '.' . $fieldName;
		foreach ($dsPointerFields as $key) {
			$parameterArray['_cshKey'] .= '.' . $row[$key];
		}

		$options = $this->globalOptions;
		$options['flexFormDataStructureArray'] = $flexFormDataStructureArray['ROOT']['el'];
		$options['flexFormRowData'] = $flexFormRowDataSubPart;
		$options['flexFormFormPrefix'] = '[data][' . $flexFormSheetNameInRowData . '][' . $flexFormCurrentLanguage . ']';

		/** @var FlexFormElementContainer $flexFormElementContainer */
		$flexFormElementContainer = GeneralUtility::makeInstance(FlexFormElementContainer::class);
		return $flexFormElementContainer->setGlobalOptions($options)->render();
	}

}

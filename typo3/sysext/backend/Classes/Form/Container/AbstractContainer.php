<?php
namespace TYPO3\CMS\Backend\Form\Container;

use TYPO3\CMS\Core\Utility\GeneralUtility;

abstract class AbstractContainer {

	/**
	 * A list of global options given from FormEngine to child elements.
	 *
	 * @var array
	 */
	protected $globalOptions = array();

	/**
	 * Set global options from parent FormEngine instance
	 *
	 * @param array $globalOptions Global options like 'readonly' for all elements
	 * @return AbstractContainer
	 */
	public function setGlobalOptions(array $globalOptions) {
		$this->globalOptions = $globalOptions;
		return $this;
	}

	/**
	 * Return a list without excluded elements.
	 *
	 * @param array $fieldsArray Typically coming from types showitem
	 * @param array $excludeElements Field names to be excluded
	 * @return array $fieldsArray without excluded elements
	 */
	protected function removeExcludeElementsFromFieldArray(array $fieldsArray, array $excludeElements) {
		$newFieldArray = array();
		foreach ($fieldsArray as $fieldString) {
			$fieldArray = $this->explodeSingleFieldShowItemConfiguration($fieldString);
			$fieldName = $fieldArray['fieldName'];
			// It doesn't make sense to exclude palettes and tabs
			if (!in_array($fieldName, $excludeElements) || $fieldName === '--palette--' || $fieldName === '--div--') {
				$newFieldArray[] = $fieldString;
			}
		}
		return $newFieldArray;
	}


	/**
	 * A single field of TCA 'types' 'showitem' can have four semicolon separated configuration options:
	 *   fieldName: Name of the field to be found in TCA 'columns' section
	 *   fieldLabel: An alternative field label
	 *   paletteName: Name of a palette to be found in TCA 'palettes' section that is rendered after this field
	 *   extra: Special configuration options of this field
	 *
	 * @param string $field Semicolon separated field configuration
	 * @return array
	 */
	protected function explodeSingleFieldShowItemConfiguration($field) {
		$fieldArray = GeneralUtility::trimExplode(';', $field, FALSE);
		if (empty($fieldArray[0])) {
			throw new \RuntimeException('Field must not be empty', 1426448465);
		}
		return array(
			'fieldName' => $fieldArray[0],
			'fieldLabel' => $fieldArray[1] ?: NULL,
			'paletteName' => $fieldArray[2] ?: NULL,
			'fieldExtra' => $fieldArray[3] ?: NULL,
		);
	}

}

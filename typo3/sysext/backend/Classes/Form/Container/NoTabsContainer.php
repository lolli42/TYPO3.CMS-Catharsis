<?php
namespace TYPO3\CMS\Backend\Form\Container;

use TYPO3\CMS\Core\Utility\GeneralUtility;

class NoTabsContainer extends AbstractContainer {

	public function render() {
		$content = '';

		$fieldArray = $this->globalOptions['fieldsArray'];

		foreach($fieldArray as $fieldString) {
			$fieldConfiguration = $this->explodeSingleFieldConfiguration($fieldString);
			$fieldName = $fieldConfiguration['fieldName'];

			if ($fieldName === '--palette--') {
				// @todo: implement a palette definition at 'paletteName'
			} else {
				$options = $this->globalOptions;
				$options['fieldName'] = $fieldName;
				$options['fieldLabel'] = $fieldConfiguration['fieldLabel'];
				$options['fieldExtra'] = $fieldConfiguration['fieldExtra'];

				/** @var SingleFieldContainer $singleFieldContainer */
				$singleFieldContainer = GeneralUtility::makeInstance(SingleFieldContainer::class);
				$singleFieldContainer->setGlobalOptions($options);
				$content[] = $singleFieldContainer->render();
				/**
				$content[] = $this->formEngine->getSingleField(
					$this->globalOptions['table'],
					$fieldName,
					$this->globalOptions['databaseRow'],
					$fieldConfiguration['fieldLabel'],
					0,
					$fieldConfiguration['extra'],
					$fieldConfiguration['paletteName']
				);
				 */
			}
		}

		return '<div class="tab-content">' . implode(LF, $content) . '</div>';
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
	protected function explodeSingleFieldConfiguration($field) {
		$fieldArray = GeneralUtility::trimExplode(';', $field, TRUE);
		// @todo: fieldName is required - throw an exception here if not given?
		return array(
			'fieldName' => $fieldArray[0],
			'fieldLabel' => $fieldArray[1] ?: NULL,
			'paletteName' => $fieldArray[2] ?: NULL,
			'fieldExtra' => $fieldArray[3] ?: NULL,
		);
	}
}
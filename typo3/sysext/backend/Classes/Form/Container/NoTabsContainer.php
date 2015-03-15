<?php
namespace TYPO3\CMS\Backend\Form\Container;

use TYPO3\CMS\Core\Utility\GeneralUtility;

class NoTabsContainer extends AbstractContainer {

	public function render() {
		$content = '';

		$fieldsArray = $this->globalOptions['fieldsArray'];
		foreach($fieldsArray as $fieldString) {
			$fieldConfiguration = $this->explodeSingleFieldShowItemConfiguration($fieldString);
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

}
<?php
namespace TYPO3\CMS\Backend\Form\Container;

use TYPO3\CMS\Core\Utility\GeneralUtility;

class PaletteAndSingleContainer extends AbstractContainer {

	public function render() {

		$content = array();
		$fieldsArray = $this->globalOptions['fieldsArray'];
		foreach($fieldsArray as $fieldString) {
			$fieldConfiguration = $this->explodeSingleFieldShowItemConfiguration($fieldString);
			$fieldName = $fieldConfiguration['fieldName'];
			if ($fieldName === '--palette--') {
				// palette needs a palette name reference, otherwise it does not make sense to try rendering of it
				if (empty($fieldConfiguration['paletteName'])) {
					continue;
				}

				$options = $this->globalOptions;
				// @todo: It may be needed to unset() these two in SingleFieldContainer again,
				// @todo: or it may happen that it inherits down to other containers later?
				$options['paletteLabel'] = $fieldConfiguration['fieldLabel'];
				$options['paletteName'] = $fieldConfiguration['paletteName'];

				/** @var PaletteContainer $paletteContainer */
				$paletteContainer = GeneralUtility::makeInstance(PaletteContainer::class);
				$paletteContainer->setGlobalOptions($options);
				$content[] = $paletteContainer->render();
			} else {
				$options = $this->globalOptions;
				$options['fieldName'] = $fieldName;
				$options['fieldLabel'] = $fieldConfiguration['fieldLabel'];
				$options['fieldExtra'] = $fieldConfiguration['fieldExtra'];

				/** @var SingleFieldContainer $singleFieldContainer */
				$singleFieldContainer = GeneralUtility::makeInstance(SingleFieldContainer::class);
				$singleFieldContainer->setGlobalOptions($options);
				$singleFieldContent = $singleFieldContainer->render();

				// If the third part of a showitem field is given, this is a name of a palette that should be rendered
				// below the single field - without palette header and only if single field produced content
				if ($singleFieldContent && !empty($fieldConfiguration['paletteName'])) {
					$options = $this->globalOptions;
					$options['paletteLabel'] = '';
					$options['paletteName'] = $fieldConfiguration['paletteName'];

					/** @var PaletteContainer $paletteContainer */
					$paletteContainer = GeneralUtility::makeInstance(PaletteContainer::class);
					$paletteContainer->setGlobalOptions($options);
					$singleFieldContent = $singleFieldContent . $paletteContainer->render();
				}

				if ($singleFieldContent) {
					$content[] = $singleFieldContent;
				}
			}
		}

		return implode(LF, $content);
	}

}
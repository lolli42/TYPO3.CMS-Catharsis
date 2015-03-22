<?php
namespace TYPO3\CMS\Backend\Form\Container;

use TYPO3\CMS\Core\Utility\GeneralUtility;

class NoTabsContainer extends AbstractContainer {

	public function render() {
		/** @var PaletteAndSingleContainer $paletteAndSingleContainer */
		$paletteAndSingleContainer = GeneralUtility::makeInstance(PaletteAndSingleContainer::class);
		$paletteAndSingleContainer->setGlobalOptions($this->globalOptions);
		$resultArray = $paletteAndSingleContainer->render();
		$resultArray['html'] = '<div class="tab-content">' . $resultArray['html'] . '</div>';
		return $resultArray;
	}

}
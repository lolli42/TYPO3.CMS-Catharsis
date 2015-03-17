<?php
namespace TYPO3\CMS\Backend\Form\Container;

use TYPO3\CMS\Core\Utility\GeneralUtility;

class NoTabsContainer extends AbstractContainer {

	public function render() {
		$content = '';

		/** @var PaletteAndSingleContainer $paletteAndSingleContainer */
		$paletteAndSingleContainer = GeneralUtility::makeInstance(PaletteAndSingleContainer::class);
		$paletteAndSingleContainer->setGlobalOptions($this->globalOptions);
		$content[] = $paletteAndSingleContainer->render();

		return '<div class="tab-content">' . implode(LF, $content) . '</div>';
	}

}
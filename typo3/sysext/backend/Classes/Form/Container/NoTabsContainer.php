<?php
namespace TYPO3\CMS\Backend\Form\Container;

use TYPO3\CMS\Core\Utility\GeneralUtility;

class NoTabsContainer extends AbstractContainer {

	public function render() {
		$content = '';

		/** @var SingleFieldContainer $singleFieldContainer */
		$singleFieldContainer = GeneralUtility::makeInstance(PaletteAndSingleContainer::class);
		$singleFieldContainer->setGlobalOptions($this->globalOptions);
		$content[] = $singleFieldContainer->render();

		return '<div class="tab-content">' . implode(LF, $content) . '</div>';
	}

}
<?php
namespace TYPO3\CMS\Backend\Form\Container;

use TYPO3\CMS\Core\Utility\GeneralUtility;

class FlexFormContainer extends AbstractContainer {

	public function render() {
		return $this->initializeResultArray();
	}

}
<?php
namespace TYPO3\CMS\Backend\Form;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Backend\Form\Container\FlexFormContainer;

/**
 * Create an element object depending on type
 */
class NodeFactory {

	/**
	 * Create an element depending on type
	 *
	 * @param string $type Type identifier
	 * @return AbstractNode
	 */
	public function create($type) {
		// Hook: getSingleField_beforeRender
		/**
		foreach ($this->hookObjectsSingleField as $hookObject) {
		if (method_exists($hookObject, 'getSingleField_beforeRender')) {
		$hookObject->getSingleField_beforeRender($table, $field, $row, $PA);
		}
		}
		 */

		if ($type === 'flex') {
			/** @var FlexFormContainer $flexContainer */
			$resultObject = GeneralUtility::makeInstance(FlexFormContainer::class);
		} elseif ($type === 'inline') {
			// @todo
			$resultObject = GeneralUtility::makeInstance('TYPO3\\CMS\\Backend\\Form\\Element\\UnknownElement');
			$type = 'unknown';
//			$item = $this->inline->getSingleField_typeInline($table, $field, $row, $PA);
		} else {
			$typeClassNameMapping = array(
				'check' => 'CheckboxElement',
				'group' => 'GroupElement',
				'input' => 'InputElement',
				'none' => 'NoneElement',
				'radio' => 'RadioElement',
				'select' => 'SelectElement',
				'text' => 'TextElement',
				'unknown' => 'UnknownElement',
				'user' => 'UserElement',
			);
			if (!isset($typeClassNameMapping[$type])) {
				$type = 'unknown';
			}
			$resultObject = GeneralUtility::makeInstance('TYPO3\\CMS\\Backend\\Form\\Element\\' . $typeClassNameMapping[$type]);
		}
		return $resultObject;
	}

}

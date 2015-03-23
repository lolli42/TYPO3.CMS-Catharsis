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

/**
 * Base class for container and single elements - their abstracts extend from here.
 */
abstract class AbstractNode {

	/**
	 * A list of global options given from parent to child elements
	 *
	 * @var array
	 */
	protected $globalOptions = array();

	/**
	 * Set global options from parent instance
	 *
	 * @param array $globalOptions Global options like 'readonly' for all elements
	 * @return $this
	 */
	public function setGlobalOptions(array $globalOptions) {
		$this->globalOptions = $globalOptions;
		return $this;
	}

	/**
	 * Initialize the array that is returned to parent after calling. This structure
	 * is identical for *all* nodes. Parent will merge the return of a child with its
	 * own stuff and in itself return an array of the same structure.
	 *
	 * @return array
	 */
	protected function initializeResultArray() {
		return array(
			'requiredElements' => array(), // name => value
			'requiredFields' => array(), // value => name
			'requiredAdditional' => array(), // name => array
			'additionalJavaScriptPost' => array(),
			'extJSCODE' => '',
			'html' => '',
		);
	}

	/**
	 * Merge existing data with child return array
	 *
	 * @param array $existing Currently merged array
	 * @param array $childReturn Array returned by child
	 * @return array Result array
	 */
	protected function mergeChildReturnIntoExistingResult(array $existing, array $childReturn) {
		if (!empty($childReturn['html'])) {
			$existing['html'] .= LF . $childReturn['html'];
		}
		if (!empty($childReturn['extJSCODE'])) {
			$existing['extJSCODE'] .= LF . $childReturn['extJSCODE'];
		}
		foreach ($childReturn['requiredElements'] as $name => $value) {
			$existing['requiredElements'][$name] = $value;
		}
		foreach ($childReturn['requiredFields'] as $value => $name) { // Params swapped ?!
			$existing['requiredFields'][$value] = $name;
		}
		foreach ($childReturn['requiredAdditional'] as $name => $subArray) {
			$existing['requiredAdditional'][$name] = $subArray;
		}
		foreach ($childReturn['additionalJavaScriptPost'] as $value) {
			$existing['additionalJavaScriptPost'][] = $value;
		}
		return $existing;
	}

}

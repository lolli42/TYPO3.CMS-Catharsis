<?php
namespace TYPO3\CMS\Backend\Form\Element;

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

use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Generation of TCEform elements where no rendering could be found
 */
class NoneElement extends AbstractFormElement {

	/**
	 * This will render a non-editable display of the content of the field.
	 *
	 * @param string $table The table name of the record
	 * @param string $field The field name which this element is supposed to edit
	 * @param array $row The record data array where the value(s) for the field can be found
	 * @param array $additionalInformation An array with additional configuration options.
	 * @return string The HTML code for the TCEform field
	 */
	public function render($table, $field, $row, &$additionalInformation) {
		$config = $additionalInformation['fieldConf']['config'];
		$itemValue = $additionalInformation['itemFormElValue'];

		if ($config['format']) {
			$itemValue = $this->formEngine->formatValue($config, $itemValue);
		}
		if (!$config['pass_content']) {
			$itemValue = htmlspecialchars($itemValue);
		}

		$rows = (int)$config['rows'];
		// Render as textarea
		if ($rows > 1 || $config['type'] === 'text') {
			if (!$config['pass_content']) {
				$itemValue = nl2br($itemValue);
			}
			$cols = MathUtility::forceIntegerInRange($config['cols'] ?: $this->defaultInputWidth, 5, $this->maxInputWidth);
			$width = $this->formEngine->formMaxWidth($cols);
			$item = '
				<div class="form-control-wrap"' . ($width ? ' style="max-width: ' . $width . 'px"' : '') . '>
					<textarea class="form-control" rows="' . $rows . '" disabled>' . $itemValue . '</textarea>
				</div>';
		} else {
			$cols = $config['cols'] ?: ($config['size'] ?: $this->defaultInputWidth);
			$size = MathUtility::forceIntegerInRange($cols ?: $this->defaultInputWidth, 5, $this->maxInputWidth);
			$width = $this->formEngine->formMaxWidth($size);
			$item = '
				<div class="form-control-wrap"' . ($width ? ' style="max-width: ' . $width . 'px"' : '') . '>
					<input class="form-control" value="'. $itemValue .'" type="text" disabled>
				</div>
				' . ((string)$itemValue !== '' ? '<p class="help-block">' . $itemValue . '</p>' : '');
		}
		return $item;
	}

}

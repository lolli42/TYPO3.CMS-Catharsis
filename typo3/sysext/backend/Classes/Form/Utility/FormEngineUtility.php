<?php
namespace TYPO3\CMS\Backend\Form\Utility;

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
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Backend\Utility\IconUtility;
use TYPO3\CMS\Lang\LanguageService;

/**
 * This is a static, internal and intermediate helper class for various
 * FormEngine related tasks.
 *
 * This class was introduced to help disentangling FormEngine and
 * its sub classes. It MUST NOT be used in other extensions and will
 * change or vanish without further notice.
 *
 * @internal
 * @todo: These helpers are target to be dropped if further FormEngine refactoring is done
 */
class FormEngineUtility {

	/**
	 * Get icon (for example for selector boxes)
	 *
	 * @param string $icon Icon reference
	 * @return array Array with two values; the icon file reference, the icon file information array (getimagesize())
	 * @internal
	 */
	static public function getIcon($icon) {
		$selIconInfo = FALSE;
		if (substr($icon, 0, 4) == 'EXT:') {
			$file = GeneralUtility::getFileAbsFileName($icon);
			if ($file) {
				$file = PathUtility::stripPathSitePrefix($file);
				$selIconFile = '../' . $file;
				$selIconInfo = @getimagesize((PATH_site . $file));
			} else {
				$selIconFile = '';
			}
		} elseif (substr($icon, 0, 3) == '../') {
			$selIconFile = GeneralUtility::resolveBackPath($icon);
			if (is_file(PATH_site . GeneralUtility::resolveBackPath(substr($icon, 3)))) {
				$selIconInfo = getimagesize((PATH_site . GeneralUtility::resolveBackPath(substr($icon, 3))));
			}
		} elseif (substr($icon, 0, 4) == 'ext/' || substr($icon, 0, 7) == 'sysext/') {
			$selIconFile = $icon;
			if (is_file(PATH_typo3 . $icon)) {
				$selIconInfo = getimagesize(PATH_typo3 . $icon);
			}
		} else {
			$selIconFile = IconUtility::skinImg('', 'gfx/' . $icon, '', 1);
			$iconPath = $selIconFile;
			if (is_file(PATH_typo3 . $iconPath)) {
				$selIconInfo = getimagesize(PATH_typo3 . $iconPath);
			}
		}
		if ($selIconInfo === FALSE) {
			// Unset to empty string if icon is not available
			$selIconFile = '';
		}
		return array($selIconFile, $selIconInfo);
	}

	/**
	 * Renders the $icon, supports a filename for skinImg or sprite-icon-name
	 *
	 * @param string $icon The icon passed, could be a file-reference or a sprite Icon name
	 * @param string $alt Alt attribute of the icon returned
	 * @param string $title Title attribute of the icon return
	 * @return string A tag representing to show the asked icon
	 * @internal
	 */
	static public function getIconHtml($icon, $alt = '', $title = '') {
		$iconArray = static::getIcon($icon);
		if (!empty($iconArray[0]) && is_file(GeneralUtility::resolveBackPath(PATH_typo3 . PATH_typo3_mod . $iconArray[0]))) {
			return '<img src="' . $iconArray[0] . '" alt="' . $alt . '" ' . ($title ? 'title="' . $title . '"' : '') . ' />';
		} else {
			return IconUtility::getSpriteIcon($icon, array('alt' => $alt, 'title' => $title));
		}
	}

	/**
	 * Initialize item array (for checkbox, selectorbox, radio buttons)
	 * Will resolve the label value.
	 *
	 * @param array $fieldValue The "columns" array for the field (from TCA)
	 * @return array An array of arrays with three elements; label, value, icon
	 * @internal
	 */
	static public function initItemArray($fieldValue) {
		$languageService = static::getLanguageService();
		$items = array();
		if (is_array($fieldValue['config']['items'])) {
			foreach ($fieldValue['config']['items'] as $itemValue) {
				$items[] = array($languageService->sL($itemValue[0]), $itemValue[1], $itemValue[2]);
			}
		}
		return $items;
	}

	/**
	 * Merges items into an item-array, optionally with an icon
	 * example:
	 * TCEFORM.pages.doktype.addItems.13 = My Label
	 * TCEFORM.pages.doktype.addItems.13.icon = EXT:t3skin/icons/gfx/i/pages.gif
	 *
	 * @param array $items The existing item array
	 * @param array $iArray An array of items to add. NOTICE: The keys are mapped to values, and the values and mapped to be labels. No possibility of adding an icon.
	 * @return array The updated $item array
	 * @internal
	 */
	static public function addItems($items, $iArray) {
		$languageService = static::getLanguageService();
		if (is_array($iArray)) {
			foreach ($iArray as $value => $label) {
				// if the label is an array (that means it is a subelement
				// like "34.icon = mylabel.png", skip it (see its usage below)
				if (is_array($label)) {
					continue;
				}
				// check if the value "34 = mylabel" also has a "34.icon = myimage.png"
				if (isset($iArray[$value . '.']) && $iArray[$value . '.']['icon']) {
					$icon = $iArray[$value . '.']['icon'];
				} else {
					$icon = '';
				}
				$items[] = array($languageService->sL($label), $value, $icon);
			}
		}
		return $items;
	}

	/**
	 * @return LanguageService
	 */
	static protected function  getLanguageService() {
		return $GLOBALS['LANG'];
	}

}
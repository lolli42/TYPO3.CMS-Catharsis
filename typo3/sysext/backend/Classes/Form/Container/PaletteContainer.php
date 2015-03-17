<?php
namespace TYPO3\CMS\Backend\Form\Container;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Lang\LanguageService;
use TYPO3\CMS\Backend\Utility\IconUtility;

class PaletteContainer extends AbstractContainer {

	/**
	 * Creates a palette (collection of secondary options).
	 *
	 * @return string HTML code.
	 */
	public function render() {
		$table = $this->globalOptions['table'];
		$row = $this->globalOptions['databaseRow'];
		$paletteName = $this->globalOptions['paletteName'];
		$paletteLabel = $this->globalOptions['paletteLabel'];
		$excludeElements = $this->globalOptions['excludeElements'];

		$out = '';
		$parts = $this->loadPaletteElements($table, $row, $paletteName, $excludeElements);
		// Put palette together if there are fields in it:
		if (count($parts)) {
			$realFields = 0;
			foreach ($parts as $part) {
				if ($part['NAME'] !== '--linebreak--') {
					$realFields++;
					break;
				}
			}
			if ($realFields > 0) {

				$code = $this->printPalette($parts);
				$collapsed = $this->isPalettesCollapsed($table, $paletteName);
				$isHiddenPalette = !empty($GLOBALS['TCA'][$table]['palettes'][$paletteName]['isHiddenPalette']);

				if ($collapsed && $paletteLabel && !$isHiddenPalette) {
					$code = $this->wrapCollapsiblePalette($code, 'FORMENGINE_' . $table . '_' . $paletteName . '_' . $row['uid'], $collapsed);
				} else {
					$code = '<div class="row">' . $code . '</div>';
				}

				$out = '
					<!-- getPaletteFields -->
					<fieldset class="'. ($isHiddenPalette ? 'hide' : 'form-section') . '">
						' . ($paletteLabel ? '<h4 class="form-section-headline">' . htmlspecialchars($paletteLabel) . '</h4>' : '') . '
						' . $code . '
					</fieldset>';
			}
		}
		return $out;
	}

	/**
	 * Loads the elements of a palette (collection of secondary options) in an array.
	 *
	 * @param string $table The table name
	 * @param array $row The row array
	 * @param string $palette The palette number/pointer
	 * @param array $excludeElements List of elements that should *not* be displayed
	 * @return array The palette elements
	 */
	protected function loadPaletteElements($table, $row, $palette, array $excludeElements = array()) {
		$parts = array();
		// Load the palette TCEform elements
		if ($GLOBALS['TCA'][$table] && is_array($GLOBALS['TCA'][$table]['palettes'][$palette])) {
			$itemList = $GLOBALS['TCA'][$table]['palettes'][$palette]['showitem'];
			if ($itemList) {
				$fields = GeneralUtility::trimExplode(',', $itemList, TRUE);
				foreach ($fields as $info) {
					$fieldParts = GeneralUtility::trimExplode(';', $info);
					$theField = $fieldParts[0];
					if ($theField === '--linebreak--') {
						$parts[]['NAME'] = '--linebreak--';
					} elseif (!in_array($theField, $excludeElements) && $GLOBALS['TCA'][$table]['columns'][$theField]) {

						$options = $this->globalOptions;
						$options['fieldName'] = $theField;
						$options['fieldLabel'] = $fieldParts[1];
						$options['fieldExtra'] = $fieldParts[2];
						$options['isInPalette'] = TRUE;

						/** @var SingleFieldContainer $singleFieldContainer */
						$singleFieldContainer = GeneralUtility::makeInstance(SingleFieldContainer::class);
						$singleFieldContainer->setGlobalOptions($options);
						$content = $singleFieldContainer->render();

						if (is_array($content)) {
							$parts[] = $content;
						}
					}
				}
			}
		}
		return $parts;
	}

	/**
	 * Creates HTML output for a palette
	 *
	 * @param array $palArr The palette array to print
	 * @return string HTML output
	 */
	protected function printPalette($palArr) {
		// GROUP FIELDS
		$groupedFields = array();
		$row = 0;
		$lastLineWasLinebreak = TRUE;
		foreach ($palArr as $field){
			if ($field['NAME'] === '--linebreak--') {
				if (!$lastLineWasLinebreak) {
					$row++;
					$groupedFields[$row][] = $field;
					$row++;
					$lastLineWasLinebreak = TRUE;
				}
			} else {
				$lastLineWasLinebreak = FALSE;
				$groupedFields[$row][] = $field;
			}
		}

		$out = '';
		// PROCESS FIELDS
		foreach ($groupedFields as $fields) {

			$numberOfItems = count($fields);
			$cols = $numberOfItems;
			$colWidth = (int)floor(12 / $cols);

			// COLS
			$colClass = "col-md-12";
			$colClear = array();
			if ($colWidth == 6) {
				$colClass = "col-sm-6";
				$colClear = array(
					2 => 'visible-sm-block visible-md-block visible-lg-block',
				);
			} elseif ($colWidth === 4) {
				$colClass = "col-sm-4";
				$colClear = array(
					3 => 'visible-sm-block visible-md-block visible-lg-block',
				);
			} elseif ($colWidth === 3) {
				$colClass = "col-sm-6 col-md-3";
				$colClear = array(
					2 => 'visible-sm-block',
					4 => 'visible-md-block visible-lg-block',
				);
			} elseif ($colWidth <= 2) {
				$colClass = "checkbox-column col-sm-6 col-md-3 col-lg-2";
				$colClear = array(
					2 => 'visible-sm-block',
					4 => 'visible-md-block',
					6 => 'visible-lg-block'
				);
			}

			// RENDER FIELDS
			for ($counter = 0; $counter < $numberOfItems; $counter++) {
				$content = $fields[$counter];
				if ($content['NAME'] === '--linebreak--') {
					if ($counter !== $numberOfItems) {
						$out .= '<div class="clearfix"></div>';
					}
				} else {

					// ITEM
					$out .= '
						<!-- printPalette -->
						<div class="form-group t3js-formengine-palette-field ' . $colClass . '">
							<label class="t3js-formengine-label">
								' . $content['NAME'] . '
								<img name="req_' . $content['TABLE'] . '_' . $content['ID'] . '_' . $content['FIELD'] . '" src="clear.gif" class="t3js-formengine-field-required" alt="" />
							</label>
							' . $content['ITEM_NULLVALUE'] . '
							<div class="t3js-formengine-field-item ' . $content['ITEM_DISABLED'] . '">
								<div class="t3-form-field-disable"></div>
								' . $content['ITEM'] . '
							</div>
						</div>';

					// BREAKPOINTS
					if ($counter + 1 < $numberOfItems && !empty($colClear)) {
						foreach ($colClear as $rowBreakAfter => $clearClass) {
							if (($counter + 1) % $rowBreakAfter === 0) {
								$out .= '<div class="clearfix '. $clearClass . '"></div>';
							}
						}
					}
				}
			}
		}
		return $out;
	}

	/**
	 * Returns TRUE, if the palette, $palette, is collapsed (not shown, but found in top-frame) for the table.
	 *
	 * @param string $table The table name
	 * @param int $palette The palette pointer/number
	 * @return bool
	 */
	protected function isPalettesCollapsed($table, $palette) {
		if (is_array($GLOBALS['TCA'][$table]['palettes'][$palette]) && $GLOBALS['TCA'][$table]['palettes'][$palette]['isHiddenPalette']) {
			return TRUE;
		}
		if ($GLOBALS['TCA'][$table]['ctrl']['canNotCollapse']) {
			return FALSE;
		}
		if (is_array($GLOBALS['TCA'][$table]['palettes'][$palette]) && $GLOBALS['TCA'][$table]['palettes'][$palette]['canNotCollapse']) {
			return FALSE;
		}
		return $this->globalOptions['palettesCollapsed'];
	}

	/**
	 * Add the id and the style property to the field palette
	 *
	 * @param string $code Palette Code
	 * @param string $id Collapsible ID
	 * @param string $collapsed Collapsed status
	 * @return bool Is collapsed
	 */
	protected function wrapCollapsiblePalette($code, $id, $collapsed) {
		$display = $collapsed ? '' : ' in';
		$id = str_replace('.', '', $id);
		$out = '
			<!-- wrapCollapsiblePalette -->
			<p>
				<button class="btn btn-default" type="button" data-toggle="collapse" data-target="#' . $id . '" aria-expanded="false" aria-controls="' . $id . '">
					' . IconUtility::getSpriteIcon('actions-system-options-view') . '
					' . htmlspecialchars($this->getLanguageService()->sL('LLL:EXT:lang/locallang_core.xlf:labels.moreOptions')) . '
				</button>
			</p>
			<div id="' . $id . '" class="form-section-collapse collapse' . $display . '">
				<div class="row">' . $code . '</div>
			</div>';
		return $out;
	}

	/**
	 * @return LanguageService
	 */
	protected function getLanguageService() {
		return $GLOBALS['LANG'];
	}

}
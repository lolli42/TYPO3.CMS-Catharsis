<?php
namespace TYPO3\CMS\Backend\Form\Container;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Backend\Form\AbstractNode;
use TYPO3\CMS\Backend\Form\ElementConditionMatcher;
use TYPO3\CMS\Backend\Utility\IconUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\Template\DocumentTemplate;

abstract class AbstractContainer extends AbstractNode {

	/**
	 * Return a list without excluded elements.
	 *
	 * @param array $fieldsArray Typically coming from types show item
	 * @param array $excludeElements Field names to be excluded
	 * @return array $fieldsArray without excluded elements
	 */
	protected function removeExcludeElementsFromFieldArray(array $fieldsArray, array $excludeElements) {
		$newFieldArray = array();
		foreach ($fieldsArray as $fieldString) {
			$fieldArray = $this->explodeSingleFieldShowItemConfiguration($fieldString);
			$fieldName = $fieldArray['fieldName'];
			// It doesn't make sense to exclude palettes and tabs
			if (!in_array($fieldName, $excludeElements) || $fieldName === '--palette--' || $fieldName === '--div--') {
				$newFieldArray[] = $fieldString;
			}
		}
		return $newFieldArray;
	}


	/**
	 * A single field of TCA 'types' 'showitem' can have four semicolon separated configuration options:
	 *   fieldName: Name of the field to be found in TCA 'columns' section
	 *   fieldLabel: An alternative field label
	 *   paletteName: Name of a palette to be found in TCA 'palettes' section that is rendered after this field
	 *   extra: Special configuration options of this field
	 *
	 * @param string $field Semicolon separated field configuration
	 * @throws \RuntimeException
	 * @return array
	 */
	protected function explodeSingleFieldShowItemConfiguration($field) {
		$fieldArray = GeneralUtility::trimExplode(';', $field, FALSE);
		if (empty($fieldArray[0])) {
			throw new \RuntimeException('Field must not be empty', 1426448465);
		}
		return array(
			'fieldName' => $fieldArray[0],
			'fieldLabel' => $fieldArray[1] ?: NULL,
			'paletteName' => $fieldArray[2] ?: NULL,
			'fieldExtra' => $fieldArray[3] ?: NULL,
		);
	}

	/**
	 * Evaluate condition of flex forms
	 *
	 * @param string $displayCondition The condition to evaluate
	 * @param array $flexFormData Given data the condition is based on
	 * @return TRUE Condition result
	 */
	protected function evaluateFlexFormDisplayCondition($displayCondition, $flexFormData) {
		$elementConditionMatcher = GeneralUtility::makeInstance(ElementConditionMatcher::class);

		$splitCondition = GeneralUtility::trimExplode(':', $displayCondition);
		$skipCondition = FALSE;
		$fakeRow = array();
		switch ($splitCondition[0]) {
			case 'FIELD':
				// @todo: Not 100% sure if that is correct this way
				list($_sheetName, $fieldName) = GeneralUtility::trimExplode('.', $splitCondition[1]);
				$fieldValue = $flexFormData[$fieldName];
				$splitCondition[1] = $fieldName;
				$dataStructure['ROOT']['TCEforms']['displayCond'] = join(':', $splitCondition);
				$fakeRow = array($fieldName => $fieldValue);
				break;
			case 'HIDE_FOR_NON_ADMINS':

			case 'VERSION':

			case 'HIDE_L10N_SIBLINGS':

			case 'EXT':
				break;
			case 'REC':
				$fakeRow = array('uid' => $this->globalOptions['databaseRow']['uid']);
				break;
			default:
				$skipCondition = TRUE;
		}
		if ($skipCondition) {
			return TRUE;
		} else {
			return $elementConditionMatcher->match($displayCondition, $fakeRow, 'vDEF');
		}
	}

	/**
	 * Rendering preview output of a field value which is not shown as a form field but just outputted.
	 *
	 * @param string $value The value to output
	 * @param array $config Configuration for field.
	 * @param string $field Name of field.
	 * @return string HTML formatted output
	 */
	protected function previewFieldValue($value, $config, $field = '') {
		if ($config['config']['type'] === 'group' && ($config['config']['internal_type'] === 'file' || $config['config']['internal_type'] === 'file_reference')) {
			// Ignore upload folder if internal_type is file_reference
			if ($config['config']['internal_type'] === 'file_reference') {
				$config['config']['uploadfolder'] = '';
			}
			$show_thumbs = TRUE;
			$table = 'tt_content';
			// Making the array of file items:
			$itemArray = GeneralUtility::trimExplode(',', $value, TRUE);
			// Showing thumbnails:
			$thumbnail = '';
			if ($show_thumbs) {
				$imgs = array();
				foreach ($itemArray as $imgRead) {
					$imgP = explode('|', $imgRead);
					$imgPath = rawurldecode($imgP[0]);
					$rowCopy = array();
					$rowCopy[$field] = $imgPath;
					// Icon + click menu:
					$absFilePath = GeneralUtility::getFileAbsFileName($config['config']['uploadfolder'] ? $config['config']['uploadfolder'] . '/' . $imgPath : $imgPath);
					$fileInformation = pathinfo($imgPath);
					$fileIcon = IconUtility::getSpriteIconForFile(
						$imgPath,
						array(
							'title' => htmlspecialchars($fileInformation['basename'] . ($absFilePath && @is_file($absFilePath) ? ' (' . GeneralUtility::formatSize(filesize($absFilePath)) . 'bytes)' : ' - FILE NOT FOUND!'))
						)
					);
					$imgs[] =
						'<span class="text-nowrap">' .
						BackendUtility::thumbCode(
							$rowCopy,
							$table,
							$field,
							'',
							'thumbs.php',
							$config['config']['uploadfolder'], 0, ' align="middle"'
						) .
						($absFilePath ? $this->getControllerDocumentTemplate()->wrapClickMenuOnIcon($fileIcon, $absFilePath, 0, 1, '', '+copy,info,edit,view') : $fileIcon) .
						$imgPath .
						'</span>';
				}
				$thumbnail = implode('<br />', $imgs);
			}
			return $thumbnail;
		} else {
			return nl2br(htmlspecialchars($value));
		}
	}

	/**
	 * @return DocumentTemplate
	 */
	protected function getControllerDocumentTemplate() {
		return $GLOBALS['SOBE']->doc;
	}

}

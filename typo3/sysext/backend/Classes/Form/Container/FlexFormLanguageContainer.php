<?php
namespace TYPO3\CMS\Backend\Form\Container;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\Form\Utility\FormEngineUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\DatabaseConnection;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

class FlexFormLanguageContainer extends AbstractContainer {

	/**
	 * @return array As defined in initializeResultArray() of AbstractNode
	 */
	public function render() {
		$table = $this->globalOptions['table'];
		$row = $this->globalOptions['databaseRow'];
		$parameterArray = $this->globalOptions['parameterArray'];
		$flexFormDataStructureArray = $this->globalOptions['flexFormDataStructureArray'];
		$flexFormRowData = $this->globalOptions['flexFormRowData'];

		// Determine available languages
		$langChildren = $flexFormDataStructureArray['meta']['langChildren'] ? TRUE : FALSE;
		$langDisabled = $flexFormDataStructureArray['meta']['langDisable'] ? TRUE : FALSE;
		$flexFormRowData['meta']['currentLangId'] = array();
		// Look up page language overlays
		$checkPageLanguageOverlay = $this->getBackendUserAuthentication()->getTSConfigVal('options.checkPageLanguageOverlay') ? TRUE : FALSE;
		$pageOverlays = array();
		if ($checkPageLanguageOverlay) {
			$where_clause = 'pid=' . (int)$row['pid'] . BackendUtility::deleteClause('pages_language_overlay')
				. BackendUtility::versioningPlaceholderClause('pages_language_overlay');
			$pageOverlays = $this->getDatabaseConnection()->exec_SELECTgetRows('*', 'pages_language_overlay', $where_clause, '', '', '', 'sys_language_uid');
		}
		$languages = $this->getAvailableLanguages();
		foreach ($languages as $lInfo) {
			if (
				$this->getBackendUserAuthentication()->checkLanguageAccess($lInfo['uid'])
				&& (!$checkPageLanguageOverlay || $lInfo['uid'] <= 0 || is_array($pageOverlays[$lInfo['uid']]))
			) {
				$flexFormRowData['meta']['currentLangId'][] = $lInfo['ISOcode'];
			}
		}
		if (!is_array($flexFormRowData['meta']['currentLangId']) || !count($flexFormRowData['meta']['currentLangId'])) {
			$flexFormRowData['meta']['currentLangId'] = array('DEF');
		}
		$flexFormRowData['meta']['currentLangId'] = array_unique($flexFormRowData['meta']['currentLangId']);
		$flexFormNoEditDefaultLanguage = FALSE;
		if ($langChildren || $langDisabled) {
			$availableLanguages = array('DEF');
		} else {
			if (!in_array('DEF', $flexFormRowData['meta']['currentLangId'])) {
				array_unshift($flexFormRowData['meta']['currentLangId'], 'DEF');
				$flexFormNoEditDefaultLanguage = TRUE;
			}
			$availableLanguages = $flexFormRowData['meta']['currentLangId'];
		}

		// Tabs or no tabs - that's the question
		$hasTabs = FALSE;
		if (is_array($flexFormDataStructureArray['sheets'])) {
			$hasTabs = TRUE;
		}

		$resultArray = $this->initializeResultArray();

		foreach ($availableLanguages as $lKey) {
			// Add language as header
			if (!$langChildren && !$langDisabled) {
				$resultArray['html'] .= LF . '<strong>' . FormEngineUtility::getLanguageIcon($table, $row, ('v' . $lKey)) . $lKey . ':</strong>';
			}

			// Default language "lDEF", other options are "lUK" or whatever country code
			$flexFormCurrentLanguage = 'l' . $lKey;

			$options = $this->globalOptions;
			$options['flexFormCurrentLanguage'] = $flexFormCurrentLanguage;
			$options['flexFormNoEditDefaultLanguage'] = $flexFormNoEditDefaultLanguage;
			if (!$hasTabs) {
				/** @var FlexFormNoTabsContainer $flexFormNoTabsContainer */
				$flexFormNoTabsContainer = GeneralUtility::makeInstance(FlexFormNoTabsContainer::class);
				$flexFormNoTabsResult = $flexFormNoTabsContainer->setGlobalOptions($options)->render();
				$resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $flexFormNoTabsResult);
			} else {
				/** @var FlexFormTabsContainer $flexFormTabsContainer */
				$flexFormTabsContainer = GeneralUtility::makeInstance(FlexFormTabsContainer::class);
				$flexFormTabsContainerResult = $flexFormTabsContainer->setGlobalOptions($options)->render();
				$resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $flexFormTabsContainerResult);
			}
		}

		return $resultArray;
	}

	/**
	 * Returns an array of available languages (to use for FlexForms)
	 *
	 * @return array
	 */
	protected function getAvailableLanguages() {
		$isL = ExtensionManagementUtility::isLoaded('static_info_tables');

		// Find all language records in the system:
		$db = $this->getDatabaseConnection();
		$res = $db->exec_SELECTquery('language_isocode,static_lang_isocode,title,uid', 'sys_language', 'pid=0 AND hidden=0' . BackendUtility::deleteClause('sys_language'), '', 'title');

		// Traverse them:
		$output = array(
			0 => array(
				'uid' => 0,
				'title' => 'Default language',
				'ISOcode' => 'DEF',
			)
		);

		while ($row = $db->sql_fetch_assoc($res)) {
			$output[$row['uid']] = $row;
			if (!empty($row['language_isocode'])) {
				$output[$row['uid']]['ISOcode'] = $row['language_isocode'];
			} elseif ($isL && $row['static_lang_isocode']) {
				GeneralUtility::deprecationLog('Usage of the field "static_lang_isocode" is discouraged, and will stop working with CMS 8. Use the built-in language field "language_isocode" in your sys_language records.');
				$rr = BackendUtility::getRecord('static_languages', $row['static_lang_isocode'], 'lg_iso_2');
				if ($rr['lg_iso_2']) {
					$output[$row['uid']]['ISOcode'] = $rr['lg_iso_2'];
				}
			}
			if (!$output[$row['uid']]['ISOcode']) {
				unset($output[$row['uid']]);
			}
		}
		$db->sql_free_result($res);

		return $output;
	}

	/**
	 * @return BackendUserAuthentication
	 */
	protected function getBackendUserAuthentication() {
		return $GLOBALS['BE_USER'];
	}

	/**
	 * @return DatabaseConnection
	 */
	protected function getDatabaseConnection() {
		return $GLOBALS['TYPO3_DB'];
	}

}

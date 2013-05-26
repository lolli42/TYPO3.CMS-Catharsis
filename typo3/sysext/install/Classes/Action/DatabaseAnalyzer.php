<?php
namespace TYPO3\CMS\Install\Action;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013 Christian Kuhn <lolli@schwarzbu.ch>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The Database Analyzer
 */
class DatabaseAnalyzer extends AbstractAction {

	/**
	 * @var array Error messages by db update
	 */
	protected $databaseUpdateErrorMessages = array();

	/**
	 * Handle database analyzer
	 *
	 * @throws \UnexpectedValueException
	 * @return void
	 */
	public function handle() {
		$formValues = GeneralUtility::_GP('databaseAnalyzer');

		/** @var $sqlHandler \TYPO3\CMS\Install\Sql\SchemaMigrator */
		$sqlHandler = GeneralUtility::makeInstance('TYPO3\\CMS\\Install\\Sql\\SchemaMigrator');

		$headCode = 'Database Analyser';
		$action_type = $formValues['database_type'];
		$actionParts = explode('|', $action_type);
		if (count($actionParts) < 2) {
			$action_type = '';
		}
		// Get the template file
		$templateFile = @file_get_contents((PATH_site . $this->templateFilePath . 'CheckTheDatabaseMenu.html'));
		// Get the template part from the file
		$menu = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($templateFile, '###MENU###');
		$menuMarkers = array(
			'action' => 'index.php?TYPO3_INSTALL[type]=database',
			'updateRequiredTables' => 'Update required tables',
			'compare' => 'COMPARE',
			'noticeCmpFileCurrent' => $action_type == 'cmpFile|CURRENT_TABLES' ? ' class="notice"' : '',
			'dumpStaticData' => 'Dump static data',
			'noticeAdminUser' => $action_type == 'adminUser|' ? ' class="notice"' : '',
			'createAdminUser' => 'Create "admin" user',
			'noticeUc' => $action_type == 'UC|' ? ' class="notice"' : '',
			'resetUserPreferences' => 'Reset user preferences',
			'noticeCache' => $action_type == 'cache|' ? ' class="notice"' : '',
			'clearTables' => 'Clear tables'
		);
		$directJump = '';
		// Fill the markers
		$menu = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($menu, $menuMarkers, '###|###', TRUE, FALSE);
		if ($directJump) {
			if (!$action_type) {
				$this->message($headCode, 'Menu', '
					<script language="javascript" type="text/javascript">
						window.location.href = "' . $directJump . '";
					</script>', 0, 1);
			}
		} else {
			$this->message($headCode, 'Menu', '
				<p>
					From this menu you can select which of the available SQL
					files you want to either compare or import/merge with the
					existing database.
				</p>
				<dl id="t3-install-checkthedatabaseexplanation">
					<dt>
						COMPARE:
					</dt>
					<dd>
						Compares the tables and fields of the current database
						and the selected file. It also offers to \'update\' the
						difference found.
					</dd>
				</dl>
			' . $menu, 0, 1);
		}

		if ($action_type) {
			switch ($actionParts[0]) {
				case 'cmpFile':
					$tblFileContent = '';
					$hookObjects = array();
					// Load TCA first
					\TYPO3\CMS\Core\Core\Bootstrap::getInstance()->loadExtensionTables(FALSE);

					if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/install/mod/class.tx_install.php']['checkTheDatabase'])) {
						foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/install/mod/class.tx_install.php']['checkTheDatabase'] as $classData) {
							/** @var $hookObject \TYPO3\CMS\Install\CheckTheDatabaseHookInterface */
							$hookObject = GeneralUtility::getUserObj($classData);
							if (!$hookObject instanceof \TYPO3\CMS\Install\CheckTheDatabaseHookInterface) {
								throw new \UnexpectedValueException('$hookObject must implement interface TYPO3\\CMS\\Install\\CheckTheDatabaseHookInterface', 1315554770);
							}
							$hookObjects[] = $hookObject;
						}
					}
					if (!strcmp($actionParts[1], 'CURRENT_TABLES')) {
						$tblFileContent = '';
						foreach ($GLOBALS['TYPO3_LOADED_EXT'] as $extKey => $loadedExtConf) {
							if (is_array($loadedExtConf) && $loadedExtConf['ext_tables.sql']) {
								$extensionSqlContent = GeneralUtility::getUrl($loadedExtConf['ext_tables.sql']);
								$tblFileContent .= LF . LF . LF . LF . $extensionSqlContent;
								foreach ($hookObjects as $hookObject) {
									/** @var $hookObject \TYPO3\CMS\Install\CheckTheDatabaseHookInterface */
									$appendableTableDefinitions = $hookObject->appendExtensionTableDefinitions($extKey, $loadedExtConf, $extensionSqlContent, $sqlHandler, $this);
									if ($appendableTableDefinitions) {
										$tblFileContent .= $appendableTableDefinitions;
										break;
									}
								}
							}
						}
					} elseif (@is_file($actionParts[1])) {
						$tblFileContent = GeneralUtility::getUrl($actionParts[1]);
					}
					foreach ($hookObjects as $hookObject) {
						/** @var $hookObject \TYPO3\CMS\Install\CheckTheDatabaseHookInterface */
						$appendableTableDefinitions = $hookObject->appendGlobalTableDefinitions($tblFileContent, $sqlHandler, $this);
						if ($appendableTableDefinitions) {
							$tblFileContent .= $appendableTableDefinitions;
							break;
						}
					}
					// Add SQL content coming from the caching framework
					$tblFileContent .= \TYPO3\CMS\Core\Cache\Cache::getDatabaseTableDefinitions();
					// Add SQL content coming from the category registry
					$tblFileContent .= \TYPO3\CMS\Core\Category\CategoryRegistry::getInstance()->getDatabaseTableDefinitions();
					if ($tblFileContent) {
						$fileContent = implode(LF, $sqlHandler->getStatementArray($tblFileContent, 1, '^CREATE TABLE '));
						$FDfile = $sqlHandler->getFieldDefinitions_fileContent($fileContent);
						if (!count($FDfile)) {
							die('Error: There were no \'CREATE TABLE\' definitions in the provided file');
						}
						// Updating database...
						if (is_array($formValues['database_update'])) {
							$FDdb = $sqlHandler->getFieldDefinitions_database();
							$diff = $sqlHandler->getDatabaseExtra($FDfile, $FDdb);
							$update_statements = $sqlHandler->getUpdateSuggestions($diff);
							$diff = $sqlHandler->getDatabaseExtra($FDdb, $FDfile);
							$remove_statements = $sqlHandler->getUpdateSuggestions($diff, 'remove');
							$results = array();
							$results[] = $sqlHandler->performUpdateQueries($update_statements['clear_table'], $formValues['database_update']);
							$results[] = $sqlHandler->performUpdateQueries($update_statements['add'], $formValues['database_update']);
							$results[] = $sqlHandler->performUpdateQueries($update_statements['change'], $formValues['database_update']);
							$results[] = $sqlHandler->performUpdateQueries($remove_statements['change'], $formValues['database_update']);
							$results[] = $sqlHandler->performUpdateQueries($remove_statements['drop'], $formValues['database_update']);
							$results[] = $sqlHandler->performUpdateQueries($update_statements['create_table'], $formValues['database_update']);
							$results[] = $sqlHandler->performUpdateQueries($remove_statements['change_table'], $formValues['database_update']);
							$results[] = $sqlHandler->performUpdateQueries($remove_statements['drop_table'], $formValues['database_update']);
							$this->databaseUpdateErrorMessages = array();
							foreach ($results as $resultSet) {
								if (is_array($resultSet)) {
									foreach ($resultSet as $key => $errorMessage) {
										$this->databaseUpdateErrorMessages[$key] = $errorMessage;
									}
								}
							}
						}
						// Init again / first time depending...
						$FDdb = $sqlHandler->getFieldDefinitions_database();
						$diff = $sqlHandler->getDatabaseExtra($FDfile, $FDdb);
						$update_statements = $sqlHandler->getUpdateSuggestions($diff);
						$diff = $sqlHandler->getDatabaseExtra($FDdb, $FDfile);
						$remove_statements = $sqlHandler->getUpdateSuggestions($diff, 'remove');
						$tLabel = 'Update database tables and fields';
						if ($remove_statements || $update_statements) {
							$formContent = $this->generateUpdateDatabaseForm('get_form', $update_statements, $remove_statements, $action_type);
							$this->message($tLabel, 'Table and field definitions should be updated', '
								<p>
									There seems to be a number of differences
									between the database and the selected
									SQL-file.
									<br />
									Please select which statements you want to
									execute in order to update your database:
								</p>
							' . $formContent, 2);
						} else {
							$this->generateUpdateDatabaseForm('get_form', $update_statements, $remove_statements, $action_type);
							$this->message($tLabel, 'Table and field definitions are OK.', '
								<p>
									The tables and fields in the current
									database corresponds perfectly to the
									database in the selected SQL-file.
								</p>
							', -1);
						}
					}
					break;
				case 'cache':
					$tableListArr = explode(',', 'cache_pages,cache_pagesection,cache_hash,cache_imagesizes,--div--,sys_log,sys_history,--div--,be_sessions,fe_sessions,fe_session_data' . (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('indexed_search') ? ',--div--,index_words,index_rel,index_phash,index_grlist,index_section,index_fulltext' : '') . (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('tt_products') ? ',--div--,sys_products_orders,sys_products_orders_mm_tt_products' : '') . (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('direct_mail') ? ',--div--,sys_dmail_maillog' : ''));
					if (is_array($formValues['database_clearcache'])) {
						$qList = array();
						// Get the template file
						$templateFile = @file_get_contents((PATH_site . $this->templateFilePath . 'CheckTheDatabaseCache.html'));
						// Get the subpart for emptied tables
						$emptiedTablesSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($templateFile, '###EMPTIEDTABLES###');
						// Get the subpart for table
						$tableSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($emptiedTablesSubpart, '###TABLE###');
						foreach ($tableListArr as $table) {
							if ($table != '--div--') {
								$table_c = TYPO3_OS == 'WIN' ? strtolower($table) : $table;
								if ($formValues['database_clearcache'][$table] && $whichTables[$table_c]) {
									$this->getDatabase()->exec_TRUNCATEquery($table);
									// Define the markers content
									$emptiedTablesMarkers = array(
										'tableName' => $table
									);
									// Fill the markers in the subpart
									$qList[] = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($tableSubpart, $emptiedTablesMarkers, '###|###', TRUE, FALSE);
								}
							}
						}
						// Substitute the subpart for table
						$emptiedTablesSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($emptiedTablesSubpart, '###TABLE###', implode(LF, $qList));
						if (count($qList)) {
							$this->message($headCode, 'Clearing cache', '
								<p>
									The following tables were emptied:
								</p>
							' . $emptiedTablesSubpart, 1);
						}
					}
					// Count entries and make checkboxes
					$labelArr = array(
						'cache_pages' => 'Pages',
						'cache_pagesection' => 'TS template related information',
						'cache_hash' => 'Multipurpose md5-hash cache',
						'cache_imagesizes' => 'Cached image sizes',
						'sys_log' => 'Backend action logging',
						'sys_history' => 'Addendum to the sys_log which tracks ALL changes to content through TCE. May become huge by time. Is used for rollback (undo) and the WorkFlow engine.',
						'be_sessions' => 'Backend User sessions',
						'fe_sessions' => 'Frontend User sessions',
						'fe_session_data' => 'Frontend User sessions data',
						'sys_dmail_maillog' => 'Direct Mail log',
						'sys_products_orders' => 'tt_product orders',
						'sys_products_orders_mm_tt_products' => 'relations between tt_products and sys_products_orders'
					);
					$countEntries = array();
					reset($tableListArr);
					// Get the template file
					$templateFile = @file_get_contents((PATH_site . $this->templateFilePath . 'CheckTheDatabaseCache.html'));
					// Get the subpart for table list
					$tableListSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($templateFile, '###TABLELIST###');
					// Get the subpart for the group separator
					$groupSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($tableListSubpart, '###GROUP###');
					// Get the subpart for a single table
					$singleTableSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($tableListSubpart, '###SINGLETABLE###');
					$checkBoxes = array();
					foreach ($tableListArr as $table) {
						if ($table != '--div--') {
							$table_c = TYPO3_OS == 'WIN' ? strtolower($table) : $table;
							if ($whichTables[$table_c]) {
								$countEntries[$table] = $this->getDatabase()->exec_SELECTcountRows('*', $table);
								// Checkboxes:
								if ($formValues['database_clearcache'][$table]) {
									$checked = 'checked="checked"';
								} else {
									$checked = '';
								}
								// Define the markers content
								$singleTableMarkers = array(
									'table' => $table,
									'checked' => $checked,
									'count' => '(' . $countEntries[$table] . ' rows)',
									'label' => $labelArr[$table]
								);
								// Fill the markers in the subpart
								$checkBoxes[] = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($singleTableSubpart, $singleTableMarkers, '###|###', TRUE, FALSE);
							}
						} else {
							$checkBoxes[] = $groupSubpart;
						}
					}
					// Substitute the subpart for the single tables
					$content = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($tableListSubpart, '###SINGLETABLE###', implode(LF, $checkBoxes));
					// Substitute the subpart for the group separator
					$content = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($content, '###GROUP###', '');
					$form = $this->getUpdateDbFormWrap($action_type, $content);
					$this->message($headCode, 'Clear out selected tables', '
						<p>
							Pressing this button will delete all records from
							the selected tables.
						</p>
					' . $form);
					break;
			}
		}
	}

	/**
	 * Form wrap for 'Database Analyzer'
	 * when the 'COMPARE' still contains errors
	 *
	 * @param string $action_type The action type
	 * @param string $content The form content
	 * @param string $label The submit button label
	 * @return string HTML of the form
	 */
	protected function getUpdateDbFormWrap($action_type, $content, $label = 'Write to database') {
		// Get the template file
		$templateFile = @file_get_contents((PATH_site . $this->templateFilePath . 'GetUpdateDbFormWrap.html'));
		// Get the template part from the file
		$form = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($templateFile, '###TEMPLATE###');
		// Define the markers content
		$formMarkers = array(
			'action' => 'index.php?TYPO3_INSTALL[type]=database',
			'actionType' => htmlspecialchars($action_type),
			'content' => $content,
			'label' => $label
		);
		// Fill the markers
		$form = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($form, $formMarkers, '###|###', TRUE, FALSE);
		return $form;
	}

	/**
	 * Generate the contents for the form for 'Database Analyzer'
	 * when the 'COMPARE' still contains errors
	 *
	 * @param string $type get_form if the form needs to be generated
	 * @param array $arr_update The tables/fields which needs an update
	 * @param array $arr_remove The tables/fields which needs to be removed
	 * @param string $action_type The action type
	 * @return string HTML for the form
	 */
	protected function generateUpdateDatabaseForm($type, $arr_update, $arr_remove, $action_type) {
		$content = '';
		switch ($type) {
			case 'get_form':
				$content .= $this->generateUpdateDatabaseForm_checkboxes($arr_update['clear_table'], 'Clear tables (use with care!)', FALSE, TRUE);
				$content .= $this->generateUpdateDatabaseForm_checkboxes($arr_update['add'], 'Add fields');
				$content .= $this->generateUpdateDatabaseForm_checkboxes($arr_update['change'], 'Changing fields', \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('dbal') ? 0 : 1, 0, $arr_update['change_currentValue']);
				$content .= $this->generateUpdateDatabaseForm_checkboxes($arr_remove['change'], 'Remove unused fields (rename with prefix)', 0, 1);
				$content .= $this->generateUpdateDatabaseForm_checkboxes($arr_remove['drop'], 'Drop fields (really!)', 0);
				$content .= $this->generateUpdateDatabaseForm_checkboxes($arr_update['create_table'], 'Add tables');
				$content .= $this->generateUpdateDatabaseForm_checkboxes($arr_remove['change_table'], 'Removing tables (rename with prefix)', 0, 1, $arr_remove['tables_count'], 1);
				$content .= $this->generateUpdateDatabaseForm_checkboxes($arr_remove['drop_table'], 'Drop tables (really!)', 0, 0, $arr_remove['tables_count'], 1);
				$content = $this->getUpdateDbFormWrap($action_type, $content);
				break;
			default:
				break;
		}
		return $content;
	}

	/**
	 * Creates a table which checkboxes for updating database.
	 *
	 * @param array $arr Array of statements (key / value pairs where key is used for the checkboxes)
	 * @param string $label Label for the table.
	 * @param bool|int $checked If set, then checkboxes are set by default.
	 * @param bool|int $iconDis If set, then icons are shown.
	 * @param array $currentValue Array of "current values" for each key/value pair in $arr. Shown if given.
	 * @param boolean $cVfullMsg If set, will show the prefix "Current value" if $currentValue is given.
	 * @return string HTML table with checkboxes for update. Must be wrapped in a form.
	 */
	protected function generateUpdateDatabaseForm_checkboxes($arr, $label, $checked = 1, $iconDis = 0, $currentValue = array(), $cVfullMsg = 0) {
		$tableId = uniqid('table');
		$templateMarkers = array();
		$content = '';
		if (is_array($arr)) {
			// Get the template file
			$templateFile = @file_get_contents((PATH_site . $this->templateFilePath . 'GenerateUpdateDatabaseFormCheckboxes.html'));
			// Get the template part from the file
			$content = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($templateFile, '###TEMPLATE###');
			// Define the markers content
			$templateMarkers = array(
				'label' => $label,
				'tableId' => $tableId
			);
			// Select/Deselect All
			$multipleTablesSubpart = '';
			if (count($arr) > 1) {
				// Get the subpart for multiple tables
				$multipleTablesSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($content, '###MULTIPLETABLES###');
				// Define the markers content
				$multipleTablesMarkers = array(
					'label' => $label,
					'tableId' => $tableId,
					'checked' => $checked ? ' checked="checked"' : '',
					'selectAllId' => 't3-install-' . $tableId . '-checkbox',
					'selectDeselectAll' => 'select/deselect all'
				);
				// Fill the markers in the subpart
				$multipleTablesSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($multipleTablesSubpart, $multipleTablesMarkers, '###|###', TRUE, FALSE);
			}
			// Substitute the subpart for multiple tables
			$content = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($content, '###MULTIPLETABLES###', $multipleTablesSubpart);

			// Rows
			$rows = array();
			$warnings = array();
			foreach ($arr as $key => $string) {
				// Get the subpart for rows
				$rowsSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($content, '###ROWS###');
				$currentSubpart = '';
				$warnings = array();
				// Define the markers content
				$rowsMarkers = array(
					'checkboxId' => 't3-install-db-' . $key,
					'name' => 'databaseAnalyzer[database_update][' . $key . ']',
					'checked' => $checked ? 'checked="checked"' : '',
					'string' => htmlspecialchars($string)
				);
				$iconSubpart = '';
				if ($iconDis) {
					$iconMarkers['backPath'] = '../';
					if (preg_match('/^TRUNCATE/i', $string)) {
						$iconMarkers['iconText'] = '';
						$warnings['clear_table_info'] = 'Clearing the table is sometimes necessary when adding new keys. In case of cache_* tables this should not hurt at all. However, use it with care.';
					} elseif (stristr($string, ' user_')) {
						$iconMarkers['iconText'] = '(USER)';
					} elseif (stristr($string, ' app_')) {
						$iconMarkers['iconText'] = '(APP)';
					} elseif (stristr($string, ' ttx_') || stristr($string, ' tx_')) {
						$iconMarkers['iconText'] = '(EXT)';
					}
					if (!empty($iconMarkers)) {
						// Get the subpart for icons
						$iconSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($content, '###ICONAVAILABLE###');
						// Fill the markers in the subpart
						$iconSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($iconSubpart, $iconMarkers, '###|###', TRUE, TRUE);
					}
				}
				// Substitute the subpart for icons
				$rowsSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($rowsSubpart, '###ICONAVAILABLE###', $iconSubpart);
				if (isset($currentValue[$key])) {
					// Get the subpart for current
					$currentSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($rowsSubpart, '###CURRENT###');
					// Define the markers content
					$currentMarkers = array(
						'message' => !$cVfullMsg ? 'Current value:' : '',
						'value' => $currentValue[$key]
					);
					// Fill the markers in the subpart
					$currentSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($currentSubpart, $currentMarkers, '###|###', TRUE, FALSE);
				}
				// Substitute the subpart for current
				$rowsSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($rowsSubpart, '###CURRENT###', $currentSubpart);
				$errorSubpart = '';
				if (isset($this->databaseUpdateErrorMessages[$key])) {
					// Get the subpart for current
					$errorSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($rowsSubpart, '###ERROR###');
					// Define the markers content
					$currentMarkers = array(
						'errorMessage' => $this->databaseUpdateErrorMessages[$key]
					);
					// Fill the markers in the subpart
					$errorSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($errorSubpart, $currentMarkers, '###|###', TRUE, FALSE);
				}
				// Substitute the subpart for error messages
				$rowsSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($rowsSubpart, '###ERROR###', $errorSubpart);
				// Fill the markers in the subpart
				$rowsSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($rowsSubpart, $rowsMarkers, '###|###', TRUE, FALSE);
				$rows[] = $rowsSubpart;
			}

			// Substitute the subpart for rows
			$warningsSubpart = '';
			$content = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($content, '###ROWS###', implode(LF, $rows));
			if (count($warnings)) {
				// Get the subpart for warnings
				$warningsSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($content, '###WARNINGS###');
				$warningItems = array();
				foreach ($warnings as $warning) {
					// Get the subpart for single warning items
					$warningItemSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($warningsSubpart, '###WARNINGITEM###');
					// Define the markers content
					$warningItemMarker['warning'] = $warning;
					// Fill the markers in the subpart
					$warningItems[] = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($warningItemSubpart, $warningItemMarker, '###|###', TRUE, FALSE);
				}
				// Substitute the subpart for single warning items
				$warningsSubpart = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($warningsSubpart, '###WARNINGITEM###', implode(LF, $warningItems));
			}
			// Substitute the subpart for warnings
			$content = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($content, '###WARNINGS###', $warningsSubpart);
		}
		// Fill the markers
		$content = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($content, $templateMarkers, '###|###', TRUE, FALSE);
		return $content;
	}

}
?>
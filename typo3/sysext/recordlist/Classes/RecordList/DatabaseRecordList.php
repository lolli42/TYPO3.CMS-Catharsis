<?php
namespace TYPO3\CMS\Recordlist\RecordList;

/**
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

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\Utility\IconUtility;

/**
 * Class for rendering of Web>List module
 *
 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
 */
class DatabaseRecordList extends AbstractDatabaseRecordList {

	// *********
	// External:
	// *********

	/**
	 * Used to indicate which tables (values in the array) that can have a
	 * create-new-record link. If the array is empty, all tables are allowed.
	 *
	 * @var array
	 */
	public $allowedNewTables = array();

	/**
	 * Used to indicate which tables (values in the array) that cannot have a
	 * create-new-record link. If the array is empty, all tables are allowed.
	 *
	 * @var array
	 */
	public $deniedNewTables = array();

	/**
	 * If TRUE, the control panel will contain links to the create-new wizards for
	 * pages and tt_content elements (normally, the link goes to just creatinga new
	 * element without the wizards!).
	 *
	 * @var bool
	 */
	public $newWizards = FALSE;

	/**
	 * If TRUE, will disable the rendering of clipboard + control panels.
	 *
	 * @var bool
	 */
	public $dontShowClipControlPanels = FALSE;

	/**
	 * If TRUE, will show the clipboard in the field list.
	 *
	 * @var bool
	 */
	public $showClipboard = FALSE;

	/**
	 * If TRUE, will DISABLE all control panels in lists. (Takes precedence)
	 *
	 * @var bool
	 */
	public $noControlPanels = FALSE;

	/**
	 * If TRUE, clickmenus will be rendered
	 *
	 * @var bool
	 */
	public $clickMenuEnabled = TRUE;

	/**
	 * Count of record rows in view
	 *
	 * @var int
	 */
	public $totalRowCount;

	/**
	 * Space icon used for alignment
	 *
	 * @var string
	 */
	public $spaceIcon;

	/**
	 * Disable single table view
	 *
	 * @var bool
	 */
	public $disableSingleTableView = FALSE;

	// *********
	// Internal:
	// *********

	/**
	 * Set to the page record (see writeTop())
	 *
	 * @var array
	 */
	public $pageRow = array();

	/**
	 * Used to accumulate CSV lines for CSV export.
	 *
	 * @var array
	 */
	protected $csvLines = array();

	/**
	 * If set, the listing is returned as CSV instead.
	 *
	 * @var bool
	 */
	public $csvOutput = FALSE;

	/**
	 * Clipboard object
	 *
	 * @var \TYPO3\CMS\Backend\Clipboard\Clipboard
	 */
	public $clipObj;

	/**
	 * Tracking names of elements (for clipboard use)
	 *
	 * @var array
	 */
	public $CBnames = array();

	/**
	 * Used to track which elements has duplicates and how many
	 *
	 * @var array
	 */
	public $duplicateStack = array();

	/**
	 * [$tablename][$uid] = number of references to this record
	 *
	 * @var array
	 */
	protected $referenceCount = array();

	/**
	 * Translations of the current record
	 *
	 * @var array
	 */
	public $translations;

	/**
	 * select fields for the query which fetches the translations of the current
	 * record
	 *
	 * @var string
	 */
	public $selFieldList;

	/**
	 * @var array
	 */
	public $pageinfo;

	/**
	 * Create the panel of buttons for submitting the form or otherwise perform
	 * operations.
	 *
	 * @return array All available buttons as an assoc. array
	 */
	public function getButtons() {
		$buttons = array(
			'csh' => '',
			'view' => '',
			'edit' => '',
			'hide_unhide' => '',
			'move' => '',
			'new_record' => '',
			'paste' => '',
			'level_up' => '',
			'cache' => '',
			'reload' => '',
			'shortcut' => '',
			'back' => '',
			'csv' => '',
			'export' => ''
		);
		// Get users permissions for this page record:
		$localCalcPerms = $GLOBALS['BE_USER']->calcPerms($this->pageRow);
		// CSH
		if ((string)$this->id === '') {
			$buttons['csh'] = BackendUtility::cshItem('xMOD_csh_corebe', 'list_module_noId');
		} elseif (!$this->id) {
			$buttons['csh'] = BackendUtility::cshItem('xMOD_csh_corebe', 'list_module_root');
		} else {
			$buttons['csh'] = BackendUtility::cshItem('xMOD_csh_corebe', 'list_module');
		}
		if (isset($this->id)) {
			// View Exclude doktypes 254,255 Configuration:
			// mod.web_list.noViewWithDokTypes = 254,255
			if (isset($GLOBALS['SOBE']->modTSconfig['properties']['noViewWithDokTypes'])) {
				$noViewDokTypes = GeneralUtility::trimExplode(',', $GLOBALS['SOBE']->modTSconfig['properties']['noViewWithDokTypes'], TRUE);
			} else {
				//default exclusion: doktype 254 (folder), 255 (recycler)
				$noViewDokTypes = array(
					\TYPO3\CMS\Frontend\Page\PageRepository::DOKTYPE_SYSFOLDER,
					\TYPO3\CMS\Frontend\Page\PageRepository::DOKTYPE_RECYCLER
				);
			}
			if (!in_array($this->pageRow['doktype'], $noViewDokTypes)) {
				$onClick = htmlspecialchars(BackendUtility::viewOnClick($this->id, $this->backPath, BackendUtility::BEgetRootLine($this->id)));
				$buttons['view'] = '<a href="#" onclick="' . $onClick . '" title="'
					. $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:labels.showPage', TRUE) . '">'
					. IconUtility::getSpriteIcon('actions-document-view') . '</a>';
			}
			// New record
			if (!$GLOBALS['SOBE']->modTSconfig['properties']['noCreateRecordsLink']) {
				$onClick = htmlspecialchars(('return jumpExt(\'' . $this->backPath . 'db_new.php?id=' . $this->id . '\');'));
				$buttons['new_record'] = '<a href="#" onclick="' . $onClick . '" title="'
					. $GLOBALS['LANG']->getLL('newRecordGeneral', TRUE) . '">'
					. IconUtility::getSpriteIcon('actions-document-new') . '</a>';
			}
			// If edit permissions are set, see
			// \TYPO3\CMS\Core\Authentication\BackendUserAuthentication
			if ($localCalcPerms & 2 && !empty($this->id)) {
				// Edit
				$params = '&edit[pages][' . $this->pageRow['uid'] . ']=edit';
				$onClick = htmlspecialchars(BackendUtility::editOnClick($params, $this->backPath, -1));
				$buttons['edit'] = '<a href="#" onclick="' . $onClick . '" title="'
					. $GLOBALS['LANG']->getLL('editPage', TRUE) . '">'
					. IconUtility::getSpriteIcon('actions-page-open') . '</a>';
			}
			// Paste
			if ($localCalcPerms & 8 || $localCalcPerms & 16) {
				$elFromTable = $this->clipObj->elFromTable('');
				if (count($elFromTable)) {
					$onClick = htmlspecialchars(('return ' . $this->clipObj->confirmMsg('pages', $this->pageRow, 'into', $elFromTable)));
					$buttons['paste'] = '<a href="' . htmlspecialchars($this->clipObj->pasteUrl('', $this->id))
						. '" onclick="' . $onClick . '" title="' . $GLOBALS['LANG']->getLL('clip_paste', TRUE) . '">'
						. IconUtility::getSpriteIcon('actions-document-paste-after') . '</a>';
				}
			}
			// Cache
			$buttons['cache'] = '<a href="' . htmlspecialchars(($this->listURL() . '&clear_cache=1')) . '" title="'
				. $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:labels.clear_cache', TRUE) . '">'
				. IconUtility::getSpriteIcon('actions-system-cache-clear') . '</a>';
			if (
				$this->table && (!isset($GLOBALS['SOBE']->modTSconfig['properties']['noExportRecordsLinks'])
				|| (isset($GLOBALS['SOBE']->modTSconfig['properties']['noExportRecordsLinks'])
					&& !$GLOBALS['SOBE']->modTSconfig['properties']['noExportRecordsLinks']))
			) {
				// CSV
				$buttons['csv'] = '<a href="' . htmlspecialchars(($this->listURL() . '&csv=1')) . '" title="'
					. $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:labels.csv', TRUE) . '">'
					. IconUtility::getSpriteIcon('mimetypes-text-csv') . '</a>';
				// Export
				if (ExtensionManagementUtility::isLoaded('impexp')) {
					$url = BackendUtility::getModuleUrl('xMOD_tximpexp', array('tx_impexp[action]' => 'export'));
					$buttons['export'] = '<a href="' . htmlspecialchars($url . '&tx_impexp[list][]='
							. rawurlencode($this->table . ':' . $this->id)) . '" title="'
						. $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:rm.export', TRUE) . '">'
						. IconUtility::getSpriteIcon('actions-document-export-t3d') . '</a>';
				}
			}
			// Reload
			$buttons['reload'] = '<a href="' . htmlspecialchars($this->listURL()) . '" title="'
				. $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:labels.reload', TRUE) . '">'
				. IconUtility::getSpriteIcon('actions-system-refresh') . '</a>';
			// Shortcut
			if ($GLOBALS['BE_USER']->mayMakeShortcut()) {
				$buttons['shortcut'] = $GLOBALS['TBE_TEMPLATE']->makeShortcutIcon(
					'id, imagemode, pointer, table, search_field, search_levels, showLimit, sortField, sortRev',
					implode(',', array_keys($this->MOD_MENU)),
					'web_list'
				);
			}
			// Back
			if ($this->returnUrl) {
				$href = htmlspecialchars(GeneralUtility::linkThisUrl($this->returnUrl, array('id' => $this->id)));
				$buttons['back'] = '<a href="' . $href . '" class="typo3-goBack" title="'
					. $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:labels.goBack', TRUE) . '">'
					. IconUtility::getSpriteIcon('actions-view-go-back') . '</a>';
			}
		}
		return $buttons;
	}

	/**
	 * Creates the listing of records from a single table
	 *
	 * @param string $table Table name
	 * @param int $id Page id
	 * @param string $rowList List of fields to show in the listing. Pseudo fields will be added including the record header.
	 * @throws \UnexpectedValueException
	 * @return string HTML table with the listing for the record.
	 */
	public function getTable($table, $id, $rowList) {
		// Init
		$addWhere = '';
		$titleCol = $GLOBALS['TCA'][$table]['ctrl']['label'];
		$thumbsCol = $GLOBALS['TCA'][$table]['ctrl']['thumbnail'];
		$l10nEnabled = $GLOBALS['TCA'][$table]['ctrl']['languageField']
			&& $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField']
			&& !$GLOBALS['TCA'][$table]['ctrl']['transOrigPointerTable'];
		$tableCollapsed = (bool)$this->tablesCollapsed[$table];
		// prepare space icon
		$this->spaceIcon = IconUtility::getSpriteIcon('empty-empty', array('style' => 'background-position: 0 10px;'));
		// Cleaning rowlist for duplicates and place the $titleCol as the first column always!
		$this->fieldArray = array();
		// title Column
		// Add title column
		$this->fieldArray[] = $titleCol;
		// Control-Panel
		if (!GeneralUtility::inList($rowList, '_CONTROL_')) {
			$this->fieldArray[] = '_CONTROL_';
			$this->fieldArray[] = '_AFTERCONTROL_';
		}
		// Clipboard
		if ($this->showClipboard) {
			$this->fieldArray[] = '_CLIPBOARD_';
		}
		// Ref
		if (!$this->dontShowClipControlPanels) {
			$this->fieldArray[] = '_REF_';
			$this->fieldArray[] = '_AFTERREF_';
		}
		// Path
		if ($this->searchLevels) {
			$this->fieldArray[] = '_PATH_';
		}
		// Localization
		if ($this->localizationView && $l10nEnabled) {
			$this->fieldArray[] = '_LOCALIZATION_';
			$this->fieldArray[] = '_LOCALIZATION_b';
			$addWhere .= ' AND (
				' . $GLOBALS['TCA'][$table]['ctrl']['languageField'] . '<=0
				OR
				' . $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'] . ' = 0
			)';
		}
		// Cleaning up:
		$this->fieldArray = array_unique(array_merge($this->fieldArray, GeneralUtility::trimExplode(',', $rowList, TRUE)));
		if ($this->noControlPanels) {
			$tempArray = array_flip($this->fieldArray);
			unset($tempArray['_CONTROL_']);
			unset($tempArray['_CLIPBOARD_']);
			$this->fieldArray = array_keys($tempArray);
		}
		// Creating the list of fields to include in the SQL query:
		$selectFields = $this->fieldArray;
		$selectFields[] = 'uid';
		$selectFields[] = 'pid';
		// adding column for thumbnails
		if ($thumbsCol) {
			$selectFields[] = $thumbsCol;
		}
		if ($table == 'pages') {
			$selectFields[] = 'module';
			$selectFields[] = 'extendToSubpages';
			$selectFields[] = 'nav_hide';
			$selectFields[] = 'doktype';
			$selectFields[] = 'shortcut';
			$selectFields[] = 'shortcut_mode';
			$selectFields[] = 'mount_pid';
		}
		if (is_array($GLOBALS['TCA'][$table]['ctrl']['enablecolumns'])) {
			$selectFields = array_merge($selectFields, $GLOBALS['TCA'][$table]['ctrl']['enablecolumns']);
		}
		if ($GLOBALS['TCA'][$table]['ctrl']['type']) {
			$selectFields[] = $GLOBALS['TCA'][$table]['ctrl']['type'];
		}
		if ($GLOBALS['TCA'][$table]['ctrl']['typeicon_column']) {
			$selectFields[] = $GLOBALS['TCA'][$table]['ctrl']['typeicon_column'];
		}
		if ($GLOBALS['TCA'][$table]['ctrl']['versioningWS']) {
			$selectFields[] = 't3ver_id';
			$selectFields[] = 't3ver_state';
			$selectFields[] = 't3ver_wsid';
		}
		if ($l10nEnabled) {
			$selectFields[] = $GLOBALS['TCA'][$table]['ctrl']['languageField'];
			$selectFields[] = $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'];
		}
		if ($GLOBALS['TCA'][$table]['ctrl']['label_alt']) {
			$selectFields = array_merge(
				$selectFields,
				GeneralUtility::trimExplode(',', $GLOBALS['TCA'][$table]['ctrl']['label_alt'], TRUE)
			);
		}
		// Unique list!
		$selectFields = array_unique($selectFields);
		$fieldListFields = $this->makeFieldList($table, 1);
		if (empty($fieldListFields) && $GLOBALS['TYPO3_CONF_VARS']['BE']['debug']) {
			$message = sprintf($GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_mod_web_list.xlf:missingTcaColumnsMessage', TRUE), $table, $table);
			$messageTitle = $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_mod_web_list.xlf:missingTcaColumnsMessageTitle', TRUE);
			$flashMessage = GeneralUtility::makeInstance(
				\TYPO3\CMS\Core\Messaging\FlashMessage::class,
				$message,
				$messageTitle,
				\TYPO3\CMS\Core\Messaging\FlashMessage::WARNING,
				TRUE
			);
			/** @var $flashMessageService \TYPO3\CMS\Core\Messaging\FlashMessageService */
			$flashMessageService = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Messaging\FlashMessageService::class);
			/** @var $defaultFlashMessageQueue \TYPO3\CMS\Core\Messaging\FlashMessageQueue */
			$defaultFlashMessageQueue = $flashMessageService->getMessageQueueByIdentifier();
			$defaultFlashMessageQueue->enqueue($flashMessage);
		}
		// Making sure that the fields in the field-list ARE in the field-list from TCA!
		$selectFields = array_intersect($selectFields, $fieldListFields);
		// Implode it into a list of fields for the SQL-statement.
		$selFieldList = implode(',', $selectFields);
		$this->selFieldList = $selFieldList;
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/class.db_list_extra.inc']['getTable'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/class.db_list_extra.inc']['getTable'] as $classData) {
				$hookObject = GeneralUtility::getUserObj($classData);
				if (!$hookObject instanceof \TYPO3\CMS\Backend\RecordList\RecordListGetTableHookInterface) {
					throw new \UnexpectedValueException('$hookObject must implement interface TYPO3\\CMS\\Backend\\RecordList\\RecordListGetTableHookInterface', 1195114460);
				}
				$hookObject->getDBlistQuery($table, $id, $addWhere, $selFieldList, $this);
			}
		}
		// Create the SQL query for selecting the elements in the listing:
		// do not do paging when outputting as CSV
		if ($this->csvOutput) {
			$this->iLimit = 0;
		}
		if ($this->firstElementNumber > 2 && $this->iLimit > 0) {
			// Get the two previous rows for sorting if displaying page > 1
			$this->firstElementNumber = $this->firstElementNumber - 2;
			$this->iLimit = $this->iLimit + 2;
			// (API function from TYPO3\CMS\Recordlist\RecordList\AbstractDatabaseRecordList)
			$queryParts = $this->makeQueryArray($table, $id, $addWhere, $selFieldList);
			$this->firstElementNumber = $this->firstElementNumber + 2;
			$this->iLimit = $this->iLimit - 2;
		} else {
			// (API function from TYPO3\CMS\Recordlist\RecordList\AbstractDatabaseRecordList)
			$queryParts = $this->makeQueryArray($table, $id, $addWhere, $selFieldList);
		}

		// Finding the total amount of records on the page
		// (API function from TYPO3\CMS\Recordlist\RecordList\AbstractDatabaseRecordList)
		$this->setTotalItems($queryParts);

		// Init:
		$dbCount = 0;
		$out = '';
		$result = NULL;
		$listOnlyInSingleTableMode = $this->listOnlyInSingleTableMode && !$this->table;
		// If the count query returned any number of records, we perform the real query,
		// selecting records.
		if ($this->totalItems) {
			// Fetch records only if not in single table mode or if in multi table mode and
			// not collapsed
			if ($listOnlyInSingleTableMode || !$this->table && $tableCollapsed) {
				$dbCount = $this->totalItems;
			} else {
				// Set the showLimit to the number of records when outputting as CSV
				if ($this->csvOutput) {
					$this->showLimit = $this->totalItems;
					$this->iLimit = $this->totalItems;
				}
				$result = $GLOBALS['TYPO3_DB']->exec_SELECT_queryArray($queryParts);
				$dbCount = $GLOBALS['TYPO3_DB']->sql_num_rows($result);
			}
		}
		// If any records was selected, render the list:
		if ($dbCount) {
			// Half line is drawn between tables:
			if (!$listOnlyInSingleTableMode) {
				$theData = array();
				if (!$this->table && !$rowList) {
					$theData[$titleCol] = '<img src="clear.gif" width="'
						. ($GLOBALS['SOBE']->MOD_SETTINGS['bigControlPanel'] ? '230' : '350') . '" height="1" alt="" />';
					if (in_array('_CONTROL_', $this->fieldArray)) {
						$theData['_CONTROL_'] = '';
					}
					if (in_array('_CLIPBOARD_', $this->fieldArray)) {
						$theData['_CLIPBOARD_'] = '';
					}
				}
				$out .= $this->addelement(0, '', $theData, 'class="c-table-row-spacer"', $this->leftMargin);
			}
			$tableTitle = $GLOBALS['LANG']->sL($GLOBALS['TCA'][$table]['ctrl']['title'], TRUE);
			if ($tableTitle === '') {
				$tableTitle = $table;
			}
			// Header line is drawn
			$theData = array();
			if ($this->disableSingleTableView) {
				$theData[$titleCol] = '<span class="c-table">' . BackendUtility::wrapInHelp($table, '', $tableTitle)
					. '</span> (' . $this->totalItems . ')';
			} else {
				$icon = $this->table
					? IconUtility::getSpriteIcon('actions-view-table-collapse', array('title' => $GLOBALS['LANG']->getLL('contractView', TRUE)))
					: IconUtility::getSpriteIcon('actions-view-table-expand', array('title' => $GLOBALS['LANG']->getLL('expandView', TRUE)));
				$theData[$titleCol] = $this->linkWrapTable($table, '<span class="c-table">' . $tableTitle . '</span> (' . $this->totalItems . ') ' . $icon);
			}
			if ($listOnlyInSingleTableMode) {
				$out .= '<h2>' . BackendUtility::wrapInHelp($table, '', $theData[$titleCol]) . '</h2>';
			} else {
				// Render collapse button if in multi table mode
				$collapseIcon = '';
				if (!$this->table) {
					$href = htmlspecialchars(($this->listURL() . '&collapse[' . $table . ']=' . ($tableCollapsed ? '0' : '1')));
					$title = $tableCollapsed
						? $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:labels.expandTable', TRUE)
						: $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:labels.collapseTable', TRUE);
					$icon = $tableCollapsed
						? IconUtility::getSpriteIcon('actions-view-list-expand', array('class' => 'collapseIcon'))
						: IconUtility::getSpriteIcon('actions-view-list-collapse', array('class' => 'collapseIcon'));
					$collapseIcon = '<a href="' . $href . '" title="' . $title . '">' . $icon . '</a>';
				}
				$out .= '<h2>' . $theData[$titleCol] . $collapseIcon . '</h2>';
			}
			// Render table rows only if in multi table view and not collapsed or if in
			// single table view
			$iOut = '';
			if (!$listOnlyInSingleTableMode && (!$tableCollapsed || $this->table)) {
				// Fixing a order table for sortby tables
				$this->currentTable = array();
				$currentIdList = array();
				$doSort = $GLOBALS['TCA'][$table]['ctrl']['sortby'] && !$this->sortField;
				$prevUid = 0;
				$prevPrevUid = 0;
				// Get first two rows and initialize prevPrevUid and prevUid if on page > 1
				if ($this->firstElementNumber > 2 && $this->iLimit > 0) {
					$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result);
					$prevPrevUid = -((int)$row['uid']);
					$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result);
					$prevUid = $row['uid'];
				}
				$accRows = array();
				// Accumulate rows here
				while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result)) {
					if (!$this->isRowListingConditionFulfilled($table, $row)) {
						continue;
					}
					// In offline workspace, look for alternative record:
					BackendUtility::workspaceOL($table, $row, $GLOBALS['BE_USER']->workspace, TRUE);
					if (is_array($row)) {
						$accRows[] = $row;
						$currentIdList[] = $row['uid'];
						if ($doSort) {
							if ($prevUid) {
								$this->currentTable['prev'][$row['uid']] = $prevPrevUid;
								$this->currentTable['next'][$prevUid] = '-' . $row['uid'];
								$this->currentTable['prevUid'][$row['uid']] = $prevUid;
							}
							$prevPrevUid = isset($this->currentTable['prev'][$row['uid']]) ? -$prevUid : $row['pid'];
							$prevUid = $row['uid'];
						}
					}
				}
				$GLOBALS['TYPO3_DB']->sql_free_result($result);
				$this->totalRowCount = count($accRows);
				// CSV initiated
				if ($this->csvOutput) {
					$this->initCSV();
				}
				// Render items:
				$this->CBnames = array();
				$this->duplicateStack = array();
				$this->eCounter = $this->firstElementNumber;
				$cc = 0;
				foreach ($accRows as $row) {
					// Render item row if counter < limit
					if ($cc < $this->iLimit) {
						$cc++;
						$this->translations = FALSE;
						$iOut .= $this->renderListRow($table, $row, $cc, $titleCol, $thumbsCol);
						// If localization view is enabled it means that the selected records are
						// either default or All language and here we will not select translations
						// which point to the main record:
						if ($this->localizationView && $l10nEnabled) {
							// For each available translation, render the record:
							if (is_array($this->translations)) {
								foreach ($this->translations as $lRow) {
									// $lRow isn't always what we want - if record was moved we've to work with the
									// placeholder records otherwise the list is messed up a bit
									if ($row['_MOVE_PLH_uid'] && $row['_MOVE_PLH_pid']) {
										$where = 't3ver_move_id="' . (int)$lRow['uid'] . '" AND pid="' . $row['_MOVE_PLH_pid']
											. '" AND t3ver_wsid=' . $row['t3ver_wsid'] . BackendUtility::deleteClause($table);
										$tmpRow = BackendUtility::getRecordRaw($table, $where, $selFieldList);
										$lRow = is_array($tmpRow) ? $tmpRow : $lRow;
									}
									// In offline workspace, look for alternative record:
									BackendUtility::workspaceOL($table, $lRow, $GLOBALS['BE_USER']->workspace, TRUE);
									if (is_array($lRow) && $GLOBALS['BE_USER']->checkLanguageAccess($lRow[$GLOBALS['TCA'][$table]['ctrl']['languageField']])) {
										$currentIdList[] = $lRow['uid'];
										$iOut .= $this->renderListRow($table, $lRow, $cc, $titleCol, $thumbsCol, 18);
									}
								}
							}
						}
					}
					// Counter of total rows incremented:
					$this->eCounter++;
				}
				// Record navigation is added to the beginning and end of the table if in single
				// table mode
				if ($this->table) {
					$iOut = $this->renderListNavigation('top') . $iOut . $this->renderListNavigation('bottom');
				} else {
					// Show that there are more records than shown
					if ($this->totalItems > $this->itemsLimitPerTable) {
						$countOnFirstPage = $this->totalItems > $this->itemsLimitSingleTable ? $this->itemsLimitSingleTable : $this->totalItems;
						$hasMore = $this->totalItems > $this->itemsLimitSingleTable;
						$colspan = $this->showIcon ? count($this->fieldArray) + 1 : count($this->fieldArray);
						$iOut .= '<tr><td colspan="' . $colspan . '" style="padding:5px;">
								<a href="' . htmlspecialchars(($this->listURL() . '&table=' . rawurlencode($table))) . '">'
							. '<img' . IconUtility::skinImg($this->backPath, 'gfx/pildown.gif', 'width="14" height="14"') . ' alt="" />'
							. ' <i>[1 - ' . $countOnFirstPage . ($hasMore ? '+' : '') . ']</i></a>
								</td></tr>';
					}
				}
				// The header row for the table is now created:
				$out .= $this->renderListHeader($table, $currentIdList);
			}
			// The list of records is added after the header:
			$out .= $iOut;
			// ... and it is all wrapped in a table:
			$out = '



			<!--
				DB listing of elements:	"' . htmlspecialchars($table) . '"
			-->
				<table class="t3-table typo3-dblist' . ($listOnlyInSingleTableMode ? ' typo3-dblist-overview' : '') . '">
					' . $out . '
				</table>';
			// Output csv if...
			// This ends the page with exit.
			if ($this->csvOutput) {
				$this->outputCSV($table);
			}
		}
		// Return content:
		return $out;
	}

	/**
	 * Check if all row listing conditions are fulfilled.
	 *
	 * This function serves as a dummy method to be overriden in extending classes.
	 *
	 * @param string $table Table name
	 * @param array $row Record
	 * @return bool True, if all conditions are fulfilled.
	 */
	protected function isRowListingConditionFulfilled($table, $row) {
		return TRUE;
	}

	/**
	 * Rendering a single row for the list
	 *
	 * @param string $table Table name
	 * @param array $row Current record
	 * @param int $cc Counter, counting for each time an element is rendered (used for alternating colors)
	 * @param string $titleCol Table field (column) where header value is found
	 * @param string $thumbsCol Table field (column) where (possible) thumbnails can be found
	 * @param int $indent Indent from left.
	 * @return string Table row for the element
	 * @access private
	 * @see getTable()
	 */
	public function renderListRow($table, $row, $cc, $titleCol, $thumbsCol, $indent = 0) {
		$iOut = '';
		$id_orig = NULL;
		// If in search mode, make sure the preview will show the correct page
		if (strlen($this->searchString)) {
			$id_orig = $this->id;
			$this->id = $row['pid'];
		}
		if (is_array($row)) {
			// Add special classes for first and last row
			$rowSpecial = '';
			if ($cc == 1 && $indent == 0) {
				$rowSpecial .= ' firstcol';
			}
			if ($cc == $this->totalRowCount || $cc == $this->iLimit) {
				$rowSpecial .= ' lastcol';
			}

			$row_bgColor = ' class="db_list_normal' . $rowSpecial . '"';

			// Overriding with versions background color if any:
			$row_bgColor = $row['_CSSCLASS'] ? ' class="' . $row['_CSSCLASS'] . '"' : $row_bgColor;
			// Incr. counter.
			$this->counter++;
			// The icon with link
			$altText = htmlspecialchars(BackendUtility::getRecordIconAltText($row, $table));
			$iconImg = IconUtility::getSpriteIconForRecord(
				$table,
				$row,
				array('title' => $altText, 'style' => $indent ? ' margin-left: ' . $indent . 'px;' : '')
			);
			$theIcon = $this->clickMenuEnabled ? $GLOBALS['SOBE']->doc->wrapClickMenuOnIcon($iconImg, $table, $row['uid']) : $iconImg;
			// Preparing and getting the data-array
			$theData = array();
			$localizationMarkerClass = '';
			foreach ($this->fieldArray as $fCol) {
				if ($fCol == $titleCol) {
					$recTitle = BackendUtility::getRecordTitle($table, $row, FALSE, TRUE);
					$warning = '';
					// If the record is edit-locked	by another user, we will show a little warning sign:
					if ($lockInfo = BackendUtility::isRecordLocked($table, $row['uid'])) {
						$warning = '<a href="#" onclick="alert('
							. GeneralUtility::quoteJSvalue($lockInfo['msg']) . '); return false;" title="'
							. htmlspecialchars($lockInfo['msg']) . '">'
							. IconUtility::getSpriteIcon('status-warning-in-use') . '</a>';
					}
					$theData[$fCol] = $warning . $this->linkWrapItems($table, $row['uid'], $recTitle, $row);
					// Render thumbnails, if:
					// - a thumbnail column exists
					// - there is content in it
					// - the thumbnail column is visible for the current type
					$type = 0;
					if (isset($GLOBALS['TCA'][$table]['ctrl']['type'])) {
						$typeColumn = $GLOBALS['TCA'][$table]['ctrl']['type'];
						$type = $row[$typeColumn];
					}
					// If current type doesn't exist, set it to 0 (or to 1 for historical reasons,
					// if 0 doesn't exist)
					if (!isset($GLOBALS['TCA'][$table]['types'][$type])) {
						$type = isset($GLOBALS['TCA'][$table]['types'][0]) ? 0 : 1;
					}
					$visibleColumns = $GLOBALS['TCA'][$table]['types'][$type]['showitem'];

					if ($this->thumbs &&
						trim($row[$thumbsCol]) &&
						preg_match('/(^|(.*(;|,)?))' . $thumbsCol . '(((;|,).*)|$)/', $visibleColumns) === 1
					) {
						$theData[$fCol] .= '<br />' . $this->thumbCode($row, $table, $thumbsCol);
					}
					if (
						isset($GLOBALS['TCA'][$table]['ctrl']['languageField'])
						&& $row[$GLOBALS['TCA'][$table]['ctrl']['languageField']] != 0
						&& $row[$GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField']] != 0
					) {
						// It's a translated record with a language parent
						$localizationMarkerClass = ' localization';
					}
				} elseif ($fCol == 'pid') {
					$theData[$fCol] = $row[$fCol];
				} elseif ($fCol == '_PATH_') {
					$theData[$fCol] = $this->recPath($row['pid']);
				} elseif ($fCol == '_REF_') {
					$theData[$fCol] = $this->createReferenceHtml($table, $row['uid']);
				} elseif ($fCol == '_CONTROL_') {
					$theData[$fCol] = $this->makeControl($table, $row);
				} elseif ($fCol == '_AFTERCONTROL_' || $fCol == '_AFTERREF_') {
					$theData[$fCol] = '&nbsp;';
				} elseif ($fCol == '_CLIPBOARD_') {
					$theData[$fCol] = $this->makeClip($table, $row);
				} elseif ($fCol == '_LOCALIZATION_') {
					list($lC1, $lC2) = $this->makeLocalizationPanel($table, $row);
					$theData[$fCol] = $lC1;
					$theData[$fCol . 'b'] = $lC2;
				} elseif ($fCol == '_LOCALIZATION_b') {
					// deliberately empty
				} else {
					$tmpProc = BackendUtility::getProcessedValueExtra($table, $fCol, $row[$fCol], 100, $row['uid']);
					$theData[$fCol] = $this->linkUrlMail(htmlspecialchars($tmpProc), $row[$fCol]);
					if ($this->csvOutput) {
						$row[$fCol] = BackendUtility::getProcessedValueExtra($table, $fCol, $row[$fCol], 0, $row['uid']);
					}
				}
			}
			// Reset the ID if it was overwritten
			if (strlen($this->searchString)) {
				$this->id = $id_orig;
			}
			// Add row to CSV list:
			if ($this->csvOutput) {
				$this->addToCSV($row, $table);
			}
			// Add classes to table cells
			$this->addElement_tdCssClass[$titleCol] = 'col-title' . $localizationMarkerClass;
			if (!$this->dontShowClipControlPanels) {
				$this->addElement_tdCssClass['_CONTROL_'] = 'col-control';
				$this->addElement_tdCssClass['_AFTERCONTROL_'] = 'col-control-space';
				$this->addElement_tdCssClass['_CLIPBOARD_'] = 'col-clipboard';
			}
			$this->addElement_tdCssClass['_PATH_'] = 'col-path';
			$this->addElement_tdCssClass['_LOCALIZATION_'] = 'col-localizationa';
			$this->addElement_tdCssClass['_LOCALIZATION_b'] = 'col-localizationb';
			// Create element in table cells:
			$iOut .= $this->addelement(1, $theIcon, $theData, $row_bgColor);
			// Finally, return table row element:
			return $iOut;
		}
	}

	/**
	 * Gets the number of records referencing the record with the UID $uid in
	 * the table $tableName.
	 *
	 * @param string $tableName
	 * @param int $uid
	 * @return int The number of references to record $uid in table
	 */
	protected function getReferenceCount($tableName, $uid) {
		if (!isset($this->referenceCount[$tableName][$uid])) {
			$where = 'ref_table = ' . $GLOBALS['TYPO3_DB']->fullQuoteStr($tableName, 'sys_refindex')
				. ' AND ref_uid = ' . $uid . ' AND deleted = 0';
			$numberOfReferences = $GLOBALS['TYPO3_DB']->exec_SELECTcountRows('*', 'sys_refindex', $where);
			$this->referenceCount[$tableName][$uid] = $numberOfReferences;
		}
		return $this->referenceCount[$tableName][$uid];
	}

	/**
	 * Rendering the header row for a table
	 *
	 * @param string $table Table name
	 * @param array $currentIdList Array of the currently displayed uids of the table
	 * @throws \UnexpectedValueException
	 * @return string Header table row
	 * @access private
	 * @see getTable()
	 */
	public function renderListHeader($table, $currentIdList) {
		// Init:
		$theData = array();
		$icon = '';
		// Traverse the fields:
		foreach ($this->fieldArray as $fCol) {
			// Calculate users permissions to edit records in the table:
			$permsEdit = $this->calcPerms & ($table == 'pages' ? 2 : 16);
			switch ((string)$fCol) {
				case '_PATH_':
					// Path
					$theData[$fCol] = '<i>[' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:labels._PATH_', TRUE) . ']</i>';
					break;
				case '_REF_':
					// References
					$theData[$fCol] = '<i>[' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_mod_file_list.xlf:c__REF_', TRUE) . ']</i>';
					break;
				case '_LOCALIZATION_':
					// Path
					$theData[$fCol] = '<i>[' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:labels._LOCALIZATION_', TRUE) . ']</i>';
					break;
				case '_LOCALIZATION_b':
					// Path
					$theData[$fCol] = $GLOBALS['LANG']->getLL('Localize', TRUE);
					break;
				case '_CLIPBOARD_':
					// Clipboard:
					$cells = array();
					// If there are elements on the clipboard for this table, then display the
					// "paste into" icon:
					$elFromTable = $this->clipObj->elFromTable($table);
					if (count($elFromTable)) {
						$href = htmlspecialchars($this->clipObj->pasteUrl($table, $this->id));
						$onClick = htmlspecialchars('return ' . $this->clipObj->confirmMsg('pages', $this->pageRow, 'into', $elFromTable));
						$cells['pasteAfter'] = '<a href="' . $href . '" onclick="' . $onClick
							. '" title="' . $GLOBALS['LANG']->getLL('clip_paste', TRUE) . '">'
							. IconUtility::getSpriteIcon('actions-document-paste-after') . '</a>';
					}
					// If the numeric clipboard pads are enabled, display the control icons for that:
					if ($this->clipObj->current != 'normal') {
						// The "select" link:
						$spriteIcon = IconUtility::getSpriteIcon('actions-edit-copy', array('title' => $GLOBALS['LANG']->getLL('clip_selectMarked', TRUE)));
						$cells['copyMarked'] = $this->linkClipboardHeaderIcon($spriteIcon, $table, 'setCB');
						// The "edit marked" link:
						$editIdList = implode(',', $currentIdList);
						$editIdList = '\'+editList(\'' . $table . '\',\'' . $editIdList . '\')+\'';
						$params = '&edit[' . $table . '][' . $editIdList . ']=edit&disHelp=1';
						$onClick = htmlspecialchars(BackendUtility::editOnClick($params, $this->backPath, -1));
						$cells['edit'] = '<a href="#" onclick="' . $onClick . '" title="'
							. $GLOBALS['LANG']->getLL('clip_editMarked', TRUE) . '">'
							. IconUtility::getSpriteIcon('actions-document-open') . '</a>';
						// The "Delete marked" link:
						$cells['delete'] = $this->linkClipboardHeaderIcon(
							IconUtility::getSpriteIcon('actions-edit-delete', array('title' => $GLOBALS['LANG']->getLL('clip_deleteMarked', TRUE))),
							$table,
							'delete',
							sprintf($GLOBALS['LANG']->getLL('clip_deleteMarkedWarning'), $GLOBALS['LANG']->sL($GLOBALS['TCA'][$table]['ctrl']['title']))
						);
						// The "Select all" link:
						$onClick = htmlspecialchars(('checkOffCB(\'' . implode(',', $this->CBnames) . '\', this); return false;'));
						$cells['markAll'] = '<a class="cbcCheckAll" rel="" href="#" onclick="' . $onClick . '" title="'
							. $GLOBALS['LANG']->getLL('clip_markRecords', TRUE) . '">'
							. IconUtility::getSpriteIcon('actions-document-select') . '</a>';
					} else {
						$cells['empty'] = '';
					}
					/**
					 * @hook renderListHeaderActions: Allows to change the clipboard icons of the Web>List table headers
					 * @usage Above each listed table in Web>List a header row is shown.
					 *        This hook allows to modify the icons responsible for the clipboard functions
					 *        (shown above the clipboard checkboxes when a clipboard other than "Normal" is selected),
					 *        or other "Action" functions which perform operations on the listed records.
					*/
					if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/class.db_list_extra.inc']['actions'])) {
						foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/class.db_list_extra.inc']['actions'] as $classData) {
							$hookObject = GeneralUtility::getUserObj($classData);
							if (!$hookObject instanceof \TYPO3\CMS\Recordlist\RecordList\RecordListHookInterface) {
								throw new \UnexpectedValueException('$hookObject must implement interface TYPO3\\CMS\\Recordlist\\RecordList\\RecordListHookInterface', 1195567850);
							}
							$cells = $hookObject->renderListHeaderActions($table, $currentIdList, $cells, $this);
						}
					}
					$theData[$fCol] = implode('', $cells);
					break;
				case '_CONTROL_':
					// Control panel:
					if (!$GLOBALS['TCA'][$table]['ctrl']['readOnly']) {
						// If new records can be created on this page, add links:
						if ($this->calcPerms & ($table === 'pages' ? 8 : 16) && $this->showNewRecLink($table)) {
							$spriteIcon = $table === 'pages'
								? IconUtility::getSpriteIcon('actions-page-new')
								: IconUtility::getSpriteIcon('actions-document-new');
							if ($table === 'tt_content' && $this->newWizards) {
								// If mod.web_list.newContentWiz.overrideWithExtension is set, use that extension's create new content wizard instead:
								$tmpTSc = BackendUtility::getModTSconfig($this->pageinfo['uid'], 'mod.web_list');
								$tmpTSc = $tmpTSc['properties']['newContentWiz.']['overrideWithExtension'];
								$wizardPath = ExtensionManagementUtility::isLoaded($tmpTSc)
									? ExtensionManagementUtility::extRelPath($tmpTSc) . 'mod1/db_new_content_el.php'
									: 'sysext/cms/layout/db_new_content_el.php';
								$newContentWizScriptPath = $this->backPath . $wizardPath;
								$onClick = htmlspecialchars('return jumpExt(\'' . $newContentWizScriptPath . '?id=' . $this->id . '\');');
								$icon = '<a href="#" onclick="' . $onClick . '" title="'
									. $GLOBALS['LANG']->getLL('new', TRUE) . '">' . $spriteIcon . '</a>';
							} elseif ($table == 'pages' && $this->newWizards) {
								$href = htmlspecialchars($this->backPath . 'db_new.php?id=' . $this->id
									. '&pagesOnly=1&returnUrl=' . rawurlencode(GeneralUtility::getIndpEnv('REQUEST_URI')));
								$icon = '<a href="' . $href . '" title="' . $GLOBALS['LANG']->getLL('new', TRUE) . '">'
									. $spriteIcon . '</a>';
							} else {
								$params = '&edit[' . $table . '][' . $this->id . ']=new';
								if ($table == 'pages_language_overlay') {
									$params .= '&overrideVals[pages_language_overlay][doktype]=' . (int)$this->pageRow['doktype'];
								}
								$icon = '<a href="#" onclick="' . htmlspecialchars(BackendUtility::editOnClick($params, $this->backPath, -1))
									. '" title="' . $GLOBALS['LANG']->getLL('new', TRUE) . '">' . $spriteIcon . '</a>';
							}
						}
						// If the table can be edited, add link for editing ALL SHOWN fields for all listed records:
						if ($permsEdit && $this->table && is_array($currentIdList)) {
							$editIdList = implode(',', $currentIdList);
							if ($this->clipNumPane()) {
								$editIdList = '\'+editList(\'' . $table . '\',\'' . $editIdList . '\')+\'';
							}
							$params = '&edit[' . $table . '][' . $editIdList . ']=edit&columnsOnly=' . implode(',', $this->fieldArray) . '&disHelp=1';
							$icon .= '<a href="#" onclick="' . htmlspecialchars(BackendUtility::editOnClick($params, $this->backPath, -1))
								. '" title="' . $GLOBALS['LANG']->getLL('editShownColumns', TRUE) . '">'
								. IconUtility::getSpriteIcon('actions-document-open') . '</a>';
						}
						// Add an empty entry, so column count fits again after moving this into $icon
						$theData[$fCol] = '&nbsp;';
					}
					break;
				case '_AFTERCONTROL_':

				case '_AFTERREF_':
					// space column
					$theData[$fCol] = '&nbsp;';
					break;
				default:
					// Regular fields header:
					$theData[$fCol] = '';

					// Check if $fCol is really a field and get the label and remove the colons
					// at the end
					$sortLabel = BackendUtility::getItemLabel($table, $fCol);
					if ($sortLabel !== NULL) {
						$sortLabel = $GLOBALS['LANG']->sL($sortLabel, TRUE);
						$sortLabel = rtrim(trim($sortLabel), ':');
					} else {
						// No TCA field, only output the $fCol variable with square brackets []
						$sortLabel = htmlspecialchars($fCol);
						$sortLabel = '<i>[' . rtrim(trim($sortLabel), ':') . ']</i>';
					}

					if ($this->table && is_array($currentIdList)) {
						// If the numeric clipboard pads are selected, show duplicate sorting link:
						if ($this->clipNumPane()) {
							$theData[$fCol] .= '<a href="' . htmlspecialchars($this->listURL('', -1) . '&duplicateField=' . $fCol)
								. '" title="' . $GLOBALS['LANG']->getLL('clip_duplicates', TRUE) . '">'
								. IconUtility::getSpriteIcon('actions-document-duplicates-select') . '</a>';
						}
						// If the table can be edited, add link for editing THIS field for all
						// listed records:
						if (!$GLOBALS['TCA'][$table]['ctrl']['readOnly'] && $permsEdit && $GLOBALS['TCA'][$table]['columns'][$fCol]) {
							$editIdList = implode(',', $currentIdList);
							if ($this->clipNumPane()) {
								$editIdList = '\'+editList(\'' . $table . '\',\'' . $editIdList . '\')+\'';
							}
							$params = '&edit[' . $table . '][' . $editIdList . ']=edit&columnsOnly=' . $fCol . '&disHelp=1';
							$iTitle = sprintf($GLOBALS['LANG']->getLL('editThisColumn'), $sortLabel);
							$theData[$fCol] .= '<a href="#" onclick="' . htmlspecialchars(BackendUtility::editOnClick($params, $this->backPath, -1))
								. '" title="' . htmlspecialchars($iTitle) . '">'
								. IconUtility::getSpriteIcon('actions-document-open') . '</a>';
						}
					}
					$theData[$fCol] .= $this->addSortLink($sortLabel, $fCol, $table);
			}
		}
		/**
		 * @hook renderListHeader: Allows to change the contents of columns/cells of the Web>List table headers
		 * @usage Above each listed table in Web>List a header row is shown.
		 *        Containing the labels of all shown fields and additional icons to create new records for this
		 *        table or perform special clipboard tasks like mark and copy all listed records to clipboard, etc.
		 */
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/class.db_list_extra.inc']['actions'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/class.db_list_extra.inc']['actions'] as $classData) {
				$hookObject = GeneralUtility::getUserObj($classData);
				if (!$hookObject instanceof \TYPO3\CMS\Recordlist\RecordList\RecordListHookInterface) {
					throw new \UnexpectedValueException('$hookObject must implement interface TYPO3\\CMS\\Recordlist\\RecordList\\RecordListHookInterface', 1195567855);
				}
				$theData = $hookObject->renderListHeader($table, $currentIdList, $theData, $this);
			}
		}
		// Create and return header table row:
		return '<thead>' . $this->addelement(1, $icon, $theData) . '</thead>';
	}

	/**
	 * Get pointer for first element on the page
	 *
	 * @param int $page Page number starting with 1
	 * @return int Pointer to first element on the page (starting with 0)
	 */
	protected function getPointerForPage($page) {
		return ($page - 1) * $this->iLimit;
	}

	/**
	 * Creates a page browser for tables with many records
	 *
	 * @param string $renderPart Distinguish between 'top' and 'bottom' part of the navigation (above or below the records)
	 * @return string Navigation HTML
	 */
	protected function renderListNavigation($renderPart = 'top') {
		$totalPages = ceil($this->totalItems / $this->iLimit);
		// Show page selector if not all records fit into one page
		if ($totalPages <= 1) {
			return '';
		}
		$listURL = $this->listURL('', $this->table);
		// 1 = first page
		// 0 = first element
		$currentPage = floor($this->firstElementNumber / $this->iLimit) + 1;
		// Compile first, previous, next, last and refresh buttons
		if ($currentPage > 1) {
			$labelFirst = $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_common.xlf:first');
			$labelPrevious = $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_common.xlf:previous');
			$first = '<a href="' . $listURL . '&pointer=' . $this->getPointerForPage(1) . '">'
				. IconUtility::getSpriteIcon('actions-view-paging-first', array('title' => $labelFirst)) . '</a>';
			$previous = '<a href="' . $listURL . '&pointer=' . $this->getPointerForPage($currentPage - 1) . '">'
				. IconUtility::getSpriteIcon('actions-view-paging-previous', array('title' => $labelPrevious)) . '</a>';
		} else {
			$first = IconUtility::getSpriteIcon('actions-view-paging-first-disabled');
			$previous = IconUtility::getSpriteIcon('actions-view-paging-previous-disabled');
		}
		if ($currentPage < $totalPages) {
			$labelNext = $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_common.xlf:next');
			$labelLast = $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_common.xlf:last');
			$next = '<a href="' . $listURL . '&pointer=' . $this->getPointerForPage($currentPage + 1) . '">'
				. IconUtility::getSpriteIcon('actions-view-paging-next', array('title' => $labelNext)) . '</a>';
			$last = '<a href="' . $listURL . '&pointer=' . $this->getPointerForPage($totalPages) . '">'
				. IconUtility::getSpriteIcon('actions-view-paging-last', array('title' => $labelLast)) . '</a>';
		} else {
			$next = IconUtility::getSpriteIcon('actions-view-paging-next-disabled');
			$last = IconUtility::getSpriteIcon('actions-view-paging-last-disabled');
		}
		$reload = '<a href="#" onclick="document.dblistForm.action=\'' . $listURL
			. '&pointer=\'+calculatePointer(document.getElementById(\'jumpPage-' . $renderPart
			. '\').value); document.dblistForm.submit(); return true;" title="'
			. $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_common.xlf:reload', TRUE) . '">'
			. IconUtility::getSpriteIcon('actions-system-refresh') . '</a>';
		if ($renderPart === 'top') {
			// Add js to traverse a page select input to a pointer value
			$content = '
<script type="text/JavaScript">
/*<![CDATA[*/
	function calculatePointer(page) {
		if (page > ' . $totalPages . ') {
			page = ' . $totalPages . ';
		}
		if (page < 1) {
			page = 1;
		}
		return (page - 1) * ' . $this->iLimit . ';
	}
/*]]>*/
</script>
';
		}
		$pageNumberInput = '<span>
			<input type="text" value="' . $currentPage . '" size="3" id="jumpPage-' . $renderPart . '" name="jumpPage-'
			. $renderPart . '" onkeyup="if (event.keyCode == Event.KEY_RETURN) { document.dblistForm.action=\'' . $listURL
			. '&pointer=\'+calculatePointer(this.value); document.dblistForm.submit(); } return true;" />
			</span>';
		$pageIndicatorText = sprintf(
			$GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_mod_web_list.xlf:pageIndicator'),
			$pageNumberInput,
			$totalPages
		);
		$pageIndicator = '<span class="pageIndicator">' . $pageIndicatorText . '</span>';
		if ($this->totalItems > $this->firstElementNumber + $this->iLimit) {
			$lastElementNumber = $this->firstElementNumber + $this->iLimit;
		} else {
			$lastElementNumber = $this->totalItems;
		}
		$rangeIndicator = '<span class="pageIndicator">' . sprintf($GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_mod_web_list.xlf:rangeIndicator'), ($this->firstElementNumber + 1), $lastElementNumber) . '</span>';

		$titleColumn = $this->fieldArray[0];
		$data = array(
			$titleColumn => '<div id="typo3-dblist-pagination">' . $first . $previous . '<span class="bar">&nbsp;</span>'
				. $rangeIndicator . '<span class="bar">&nbsp;</span>' . $pageIndicator . '<span class="bar">&nbsp;</span>'
				. $next . $last . '<span class="bar">&nbsp;</span>' . $reload . '</div>'
		);
		return $this->addElement(1, '', $data);
	}

	/*********************************
	 *
	 * Rendering of various elements
	 *
	 *********************************/

	/**
	 * Creates the control panel for a single record in the listing.
	 *
	 * @param string $table The table
	 * @param array $row The record for which to make the control panel.
	 * @throws \UnexpectedValueException
	 * @return string HTML table with the control panel (unless disabled)
	 */
	public function makeControl($table, $row) {
		if ($this->dontShowClipControlPanels) {
			return '';
		}
		$rowUid = $row['uid'];
		if (ExtensionManagementUtility::isLoaded('version') && isset($row['_ORIG_uid'])) {
			$rowUid = $row['_ORIG_uid'];
		}
		$cells = array();
		// If the listed table is 'pages' we have to request the permission settings for each page:
		$localCalcPerms = 0;
		if ($table == 'pages') {
			$localCalcPerms = $GLOBALS['BE_USER']->calcPerms(BackendUtility::getRecord('pages', $row['uid']));
		}
		// This expresses the edit permissions for this particular element:
		$permsEdit = $table == 'pages' && $localCalcPerms & 2 || $table != 'pages' && $this->calcPerms & 16;
		// "Show" link (only pages and tt_content elements)
		if ($table == 'pages' || $table == 'tt_content') {
			$cells['view'] = '<a href="#" onclick="'
				. htmlspecialchars(
					BackendUtility::viewOnClick(
						($table === 'tt_content' ? $this->id : $row['uid']),
						$this->backPath,
						'',
						($table === 'tt_content' ? '#' . $row['uid'] : '')
					)
				) . '" title="' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:labels.showPage', TRUE) . '">'
				. IconUtility::getSpriteIcon('actions-document-view') . '</a>';
		} elseif (!$this->table) {
			$cells['view'] = $this->spaceIcon;
		}
		// "Edit" link: ( Only if permissions to edit the page-record of the content of the parent page ($this->id)
		if ($permsEdit) {
			$params = '&edit[' . $table . '][' . $row['uid'] . ']=edit';
			$spriteIcon = $GLOBALS['TCA'][$table]['ctrl']['readOnly']
				? IconUtility::getSpriteIcon('actions-document-open-read-only')
				: IconUtility::getSpriteIcon('actions-document-open');
			$cells['edit'] = '<a href="#" onclick="' . htmlspecialchars(BackendUtility::editOnClick($params, $this->backPath, -1))
				. '" title="' . $GLOBALS['LANG']->getLL('edit', TRUE) . '">' . $spriteIcon . '</a>';
		} elseif (!$this->table) {
			$cells['edit'] = $this->spaceIcon;
		}
		// "Move" wizard link for pages/tt_content elements:
		if ($table == 'tt_content' && $permsEdit || $table == 'pages') {
			$onClick = htmlspecialchars('return jumpExt(\'' . $this->backPath . 'move_el.php?table=' . $table . '&uid=' . $row['uid'] . '\');');
			$linkTitleLL = $GLOBALS['LANG']->getLL('move_' . ($table === 'tt_content' ? 'record' : 'page'), TRUE);
			$spriteIcon = $table === 'tt_content'
				? IconUtility::getSpriteIcon('actions-document-move')
				: IconUtility::getSpriteIcon('actions-page-move');
			$cells['move'] = '<a href="#" onclick="' . $onClick . '" title="' . $linkTitleLL . '">' . $spriteIcon . '</a>';
		} elseif (!$this->table) {
			$cells['move'] = $this->spaceIcon;
		}
		// If the extended control panel is enabled OR if we are seeing a single table:
		if ($GLOBALS['SOBE']->MOD_SETTINGS['bigControlPanel'] || $this->table) {
			// "Info": (All records)
			$onClick = htmlspecialchars(('top.launchView(\'' . $table . '\', \'' . $row['uid'] . '\'); return false;'));
			$cells['viewBig'] = '<a href="#" onclick="' . $onClick . '" title="' . $GLOBALS['LANG']->getLL('showInfo', TRUE) . '">'
				. IconUtility::getSpriteIcon('actions-document-info') . '</a>';
			// If the table is NOT a read-only table, then show these links:
			if (!$GLOBALS['TCA'][$table]['ctrl']['readOnly']) {
				// "Revert" link (history/undo)
				$moduleUrl = BackendUtility::getModuleUrl('record_history', array('element' => $table . ':' . $row['uid']));
				$onClick = htmlspecialchars('return jumpExt(' . GeneralUtility::quoteJSvalue($this->backPath . $moduleUrl) . ',\'#latest\');');
				$cells['history'] = '<a href="#" onclick="' . $onClick . '" title="'
					. $GLOBALS['LANG']->getLL('history', TRUE) . '">'
					. IconUtility::getSpriteIcon('actions-document-history-open') . '</a>';
				// Versioning:
				if (ExtensionManagementUtility::isLoaded('version') && !ExtensionManagementUtility::isLoaded('workspaces')) {
					$vers = BackendUtility::selectVersionsOfRecord($table, $row['uid'], 'uid', $GLOBALS['BE_USER']->workspace, FALSE, $row);
					// If table can be versionized.
					if (is_array($vers)) {
						$versionIcon = 'no-version';
						if (count($vers) > 1) {
							$versionIcon = count($vers) - 1;
						}
						$href = htmlspecialchars($this->backPath . BackendUtility::getModuleUrl('web_txversionM1', array(
							'table' => $table, 'uid' => $row['uid']
						)));
						$cells['version'] = '<a href="' . $href . '" title="'
							. $GLOBALS['LANG']->getLL('displayVersions', TRUE) . '">'
							. IconUtility::getSpriteIcon(('status-version-' . $versionIcon)) . '</a>';
					} elseif (!$this->table) {
						$cells['version'] = $this->spaceIcon;
					}
				}
				// "Edit Perms" link:
				if ($table === 'pages' && $GLOBALS['BE_USER']->check('modules', 'web_perm') && ExtensionManagementUtility::isLoaded('perm')) {
					$href = htmlspecialchars((BackendUtility::getModuleUrl('web_perm') . '&id=' . $row['uid'] . '&return_id=' . $row['uid'] . '&edit=1'));
					$cells['perms'] = '<a href="' . $href . '" title="'
						. $GLOBALS['LANG']->getLL('permissions', TRUE) . '">'
						. IconUtility::getSpriteIcon('status-status-locked') . '</a>';
				} elseif (!$this->table && $GLOBALS['BE_USER']->check('modules', 'web_perm')) {
					$cells['perms'] = $this->spaceIcon;
				}
				// "New record after" link (ONLY if the records in the table are sorted by a "sortby"-row
				// or if default values can depend on previous record):
				if ($GLOBALS['TCA'][$table]['ctrl']['sortby'] || $GLOBALS['TCA'][$table]['ctrl']['useColumnsForDefaultValues']) {
					if ($table !== 'pages' && $this->calcPerms & 16 || $table === 'pages' && $this->calcPerms & 8) {
						if ($this->showNewRecLink($table)) {
							$params = '&edit[' . $table . '][' . -($row['_MOVE_PLH'] ? $row['_MOVE_PLH_uid'] : $row['uid']) . ']=new';
							$cells['new'] = '<a href="#" onclick="' . htmlspecialchars(BackendUtility::editOnClick($params, $this->backPath, -1))
								. '" title="' . $GLOBALS['LANG']->getLL('new' . ($table == 'pages ' ? 'Page' : 'Record'), TRUE) . '">'
								. ($table == 'pages' ? IconUtility::getSpriteIcon('actions-page-new') : IconUtility::getSpriteIcon('actions-document-new')) . '</a>';
						}
					}
				} elseif (!$this->table) {
					$cells['new'] = $this->spaceIcon;
				}
				// "Up/Down" links
				if ($permsEdit && $GLOBALS['TCA'][$table]['ctrl']['sortby'] && !$this->sortField && !$this->searchLevels) {
					if (isset($this->currentTable['prev'][$row['uid']])) {
						// Up
						$params = '&cmd[' . $table . '][' . $row['uid'] . '][move]=' . $this->currentTable['prev'][$row['uid']];
						$cells['moveUp'] = '<a href="#" onclick="'
							. htmlspecialchars('return jumpToUrl(\'' . $GLOBALS['SOBE']->doc->issueCommand($params, -1) . '\');')
							. '" title="' . $GLOBALS['LANG']->getLL('moveUp', TRUE) . '">'
							. IconUtility::getSpriteIcon('actions-move-up') . '</a>';
					} else {
						$cells['moveUp'] = $this->spaceIcon;
					}
					if ($this->currentTable['next'][$row['uid']]) {
						// Down
						$params = '&cmd[' . $table . '][' . $row['uid'] . '][move]=' . $this->currentTable['next'][$row['uid']];
						$cells['moveDown'] = '<a href="#" onclick="'
							. htmlspecialchars('return jumpToUrl(\'' . $GLOBALS['SOBE']->doc->issueCommand($params, -1) . '\');')
							. '" title="' . $GLOBALS['LANG']->getLL('moveDown', TRUE) . '">'
							. IconUtility::getSpriteIcon('actions-move-down') . '</a>';
					} else {
						$cells['moveDown'] = $this->spaceIcon;
					}
				} elseif (!$this->table) {
					$cells['moveUp'] = $this->spaceIcon;
					$cells['moveDown'] = $this->spaceIcon;
				}
				// "Hide/Unhide" links:
				$hiddenField = $GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['disabled'];
				if (
					$permsEdit && $hiddenField && $GLOBALS['TCA'][$table]['columns'][$hiddenField]
					&& (!$GLOBALS['TCA'][$table]['columns'][$hiddenField]['exclude']
						|| $GLOBALS['BE_USER']->check('non_exclude_fields', $table . ':' . $hiddenField))
				) {
					if ($row[$hiddenField]) {
						$params = '&data[' . $table . '][' . $rowUid . '][' . $hiddenField . ']=0';
						$cells['hide'] = '<a href="#" onclick="'
							. htmlspecialchars('return jumpToUrl(\'' . $GLOBALS['SOBE']->doc->issueCommand($params, -1) . '\');')
							. '" title="' . $GLOBALS['LANG']->getLL(('unHide' . ($table == 'pages' ? 'Page' : '')), TRUE) . '">'
							. IconUtility::getSpriteIcon('actions-edit-unhide') . '</a>';
					} else {
						$params = '&data[' . $table . '][' . $rowUid . '][' . $hiddenField . ']=1';
						$cells['hide'] = '<a href="#" onclick="'
							. htmlspecialchars('return jumpToUrl(\'' . $GLOBALS['SOBE']->doc->issueCommand($params, -1) . '\');')
							. '" title="' . $GLOBALS['LANG']->getLL(('hide' . ($table == 'pages' ? 'Page' : '')), TRUE) . '">'
							. IconUtility::getSpriteIcon('actions-edit-hide') . '</a>';
					}
				} elseif (!$this->table) {
					$cells['hide'] = $this->spaceIcon;
				}
				// "Delete" link:
				if ($table == 'pages' && $localCalcPerms & 4 || $table != 'pages' && $this->calcPerms & 16) {
					// Check if the record version is in "deleted" state, because that will switch the action to "restore"
					if ($GLOBALS['BE_USER']->workspace > 0 && isset($row['t3ver_state']) && (int)$row['t3ver_state'] === 2) {
						$actionName = 'restore';
						$refCountMsg = '';
					} else {
						$actionName = 'delete';
						$refCountMsg = BackendUtility::referenceCount(
							$table,
							$row['uid'],
							' ' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:labels.referencesToRecord'),
							$this->getReferenceCount($table, $row['uid'])) . BackendUtility::translationCount($table, $row['uid'],
							' ' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:labels.translationsOfRecord')
						);
					}

					$titleOrig = BackendUtility::getRecordTitle($table, $row, FALSE, TRUE);
					$title = GeneralUtility::slashJS(GeneralUtility::fixed_lgd_cs($titleOrig, $this->fixedL), 1);
					$warningText = GeneralUtility::quoteJSvalue(
						$GLOBALS['LANG']->getLL($actionName . 'Warning') . ' "' . $title . '" ' . $refCountMsg
					);

					$params = '&cmd[' . $table . '][' . $row['uid'] . '][delete]=1';
					$onClick = htmlspecialchars('if (confirm(' . $warningText . ')) {jumpToUrl(\''
						. $GLOBALS['SOBE']->doc->issueCommand($params, -1) . '\');} return false;');

					$icon = IconUtility::getSpriteIcon('actions-edit-' . $actionName);
					$linkTitle = $GLOBALS['LANG']->getLL($actionName, TRUE);
					$cells['delete'] = '<a href="#" onclick="' . $onClick . '" title="' . $linkTitle . '">' . $icon . '</a>';
				} elseif (!$this->table) {
					$cells['delete'] = $this->spaceIcon;
				}
				// "Levels" links: Moving pages into new levels...
				if ($permsEdit && $table == 'pages' && !$this->searchLevels) {
					// Up (Paste as the page right after the current parent page)
					if ($this->calcPerms & 8) {
						$params = '&cmd[' . $table . '][' . $row['uid'] . '][move]=' . -$this->id;
						$cells['moveLeft'] = '<a href="#" onclick="'
							. htmlspecialchars('return jumpToUrl(\'' . $GLOBALS['SOBE']->doc->issueCommand($params, -1) . '\');')
							. '" title="' . $GLOBALS['LANG']->getLL('prevLevel', TRUE) . '">'
							. IconUtility::getSpriteIcon('actions-move-left') . '</a>';
					}
					// Down (Paste as subpage to the page right above)
					if ($this->currentTable['prevUid'][$row['uid']]) {
						$localCalcPerms = $GLOBALS['BE_USER']->calcPerms(BackendUtility::getRecord('pages', $this->currentTable['prevUid'][$row['uid']]));
						if ($localCalcPerms & 8) {
							$params = '&cmd[' . $table . '][' . $row['uid'] . '][move]=' . $this->currentTable['prevUid'][$row['uid']];
							$cells['moveRight'] = '<a href="#" onclick="'
								. htmlspecialchars('return jumpToUrl(\'' . $GLOBALS['SOBE']->doc->issueCommand($params, -1) . '\');')
								. '" title="' . $GLOBALS['LANG']->getLL('nextLevel', TRUE) . '">'
								. IconUtility::getSpriteIcon('actions-move-right') . '</a>';
						} else {
							$cells['moveRight'] = $this->spaceIcon;
						}
					} else {
						$cells['moveRight'] = $this->spaceIcon;
					}
				} elseif (!$this->table) {
					$cells['moveLeft'] = $this->spaceIcon;
					$cells['moveRight'] = $this->spaceIcon;
				}
			}
		}
		/**
		 * @hook recStatInfoHooks: Allows to insert HTML before record icons on various places
		 */
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['GLOBAL']['recStatInfoHooks'])) {
			$stat = '';
			$_params = array($table, $row['uid']);
			foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['GLOBAL']['recStatInfoHooks'] as $_funcRef) {
				$stat .= GeneralUtility::callUserFunction($_funcRef, $_params, $this);
			}
			$cells['stat'] = $stat;
		}
		/**
		 * @hook makeControl: Allows to change control icons of records in list-module
		 * @usage This hook method gets passed the current $cells array as third parameter.
		 *        This array contains values for the icons/actions generated for each record in Web>List.
		 *        Each array entry is accessible by an index-key.
		 *        The order of the icons is depending on the order of those array entries.
		 */
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/class.db_list_extra.inc']['actions'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/class.db_list_extra.inc']['actions'] as $classData) {
				$hookObject = GeneralUtility::getUserObj($classData);
				if (!$hookObject instanceof \TYPO3\CMS\Recordlist\RecordList\RecordListHookInterface) {
					throw new \UnexpectedValueException('$hookObject must implement interface TYPO3\\CMS\\Recordlist\\RecordList\\RecordListHookInterface', 1195567840);
				}
				$cells = $hookObject->makeControl($table, $row, $cells, $this);
			}
		}
		// Compile items into a DIV-element:
		return '
											<!-- CONTROL PANEL: ' . $table . ':' . $row['uid'] . ' -->
											<div class="typo3-DBctrl">' . implode('', $cells) . '</div>';
	}

	/**
	 * Creates the clipboard panel for a single record in the listing.
	 *
	 * @param string $table The table
	 * @param array $row The record for which to make the clipboard panel.
	 * @throws \UnexpectedValueException
	 * @return string HTML table with the clipboard panel (unless disabled)
	 */
	public function makeClip($table, $row) {
		// Return blank, if disabled:
		if ($this->dontShowClipControlPanels) {
			return '';
		}
		$cells = array();
		$cells['pasteAfter'] = ($cells['pasteInto'] = $this->spaceIcon);
		//enables to hide the copy, cut and paste icons for localized records - doesn't make much sense to perform these options for them
		$isL10nOverlay = $this->localizationView && $table != 'pages_language_overlay' && $row[$GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField']] != 0;
		// Return blank, if disabled:
		// Whether a numeric clipboard pad is active or the normal pad we will see different content of the panel:
		// For the "Normal" pad:
		if ($this->clipObj->current == 'normal') {
			// Show copy/cut icons:
			$isSel = (string)$this->clipObj->isSelected($table, $row['uid']);
			if ($isL10nOverlay) {
				$cells['copy'] = $this->spaceIcon;
				$cells['cut'] = $this->spaceIcon;
			} else {
				$cells['copy'] = '<a href="#" onclick="'
					. htmlspecialchars('return jumpSelf(\'' . $this->clipObj->selUrlDB($table, $row['uid'], 1, ($isSel == 'copy'), array('returnUrl' => '')) . '\');')
					. '" title="' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:cm.copy', TRUE) . '">'
					. (!$isSel == 'copy' ? IconUtility::getSpriteIcon('actions-edit-copy') : IconUtility::getSpriteIcon('actions-edit-copy-release')) . '</a>';
				$cells['cut'] = '<a href="#" onclick="'
					. htmlspecialchars('return jumpSelf(\'' . $this->clipObj->selUrlDB($table, $row['uid'], 0, ($isSel == 'cut'), array('returnUrl' => '')) . '\');')
					. '" title="' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:cm.cut', TRUE) . '">'
					. (!$isSel == 'cut' ? IconUtility::getSpriteIcon('actions-edit-cut') : IconUtility::getSpriteIcon('actions-edit-cut-release')) . '</a>';
			}
		} else {
			// For the numeric clipboard pads (showing checkboxes where one can select elements on/off)
			// Setting name of the element in ->CBnames array:
			$n = $table . '|' . $row['uid'];
			$this->CBnames[] = $n;
			// Check if the current element is selected and if so, prepare to set the checkbox as selected:
			$checked = $this->clipObj->isSelected($table, $row['uid']) ? ' checked="checked"' : '';
			// If the "duplicateField" value is set then select all elements which are duplicates...
			if ($this->duplicateField && isset($row[$this->duplicateField])) {
				$checked = '';
				if (in_array($row[$this->duplicateField], $this->duplicateStack)) {
					$checked = ' checked="checked"';
				}
				$this->duplicateStack[] = $row[$this->duplicateField];
			}
			// Adding the checkbox to the panel:
			$cells['select'] = $isL10nOverlay
				? $this->spaceIcon
				: '<input type="hidden" name="CBH[' . $n . ']" value="0" /><input type="checkbox"'
					. ' name="CBC[' . $n . ']" value="1" class="smallCheckboxes"' . $checked . ' />';
		}
		// Now, looking for selected elements from the current table:
		$elFromTable = $this->clipObj->elFromTable($table);
		if (count($elFromTable) && $GLOBALS['TCA'][$table]['ctrl']['sortby']) {
			// IF elements are found and they can be individually ordered, then add a "paste after" icon:
			$cells['pasteAfter'] = $isL10nOverlay
				? $this->spaceIcon
				: '<a href="' . htmlspecialchars($this->clipObj->pasteUrl($table, -$row['uid'])) . '" onclick="'
					. htmlspecialchars(('return ' . $this->clipObj->confirmMsg($table, $row, 'after', $elFromTable)))
					. '" title="' . $GLOBALS['LANG']->getLL('clip_pasteAfter', TRUE) . '">'
					. IconUtility::getSpriteIcon('actions-document-paste-after') . '</a>';
		}
		// Now, looking for elements in general:
		$elFromTable = $this->clipObj->elFromTable('');
		if ($table == 'pages' && count($elFromTable)) {
			$cells['pasteInto'] = '<a href="' . htmlspecialchars($this->clipObj->pasteUrl('', $row['uid']))
				. '" onclick="' . htmlspecialchars('return ' . $this->clipObj->confirmMsg($table, $row, 'into', $elFromTable))
				. '" title="' . $GLOBALS['LANG']->getLL('clip_pasteInto', TRUE) . '">'
				. IconUtility::getSpriteIcon('actions-document-paste-into') . '</a>';
		}
		/**
		 * @hook makeClip: Allows to change clip-icons of records in list-module
		 * @usage This hook method gets passed the current $cells array as third parameter.
		 *        This array contains values for the clipboard icons generated for each record in Web>List.
		 *        Each array entry is accessible by an index-key.
		 *        The order of the icons is depending on the order of those array entries.
		 */
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/class.db_list_extra.inc']['actions'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/class.db_list_extra.inc']['actions'] as $classData) {
				$hookObject = GeneralUtility::getUserObj($classData);
				if (!$hookObject instanceof \TYPO3\CMS\Recordlist\RecordList\RecordListHookInterface) {
					throw new \UnexpectedValueException('$hookObject must implement interface TYPO3\\CMS\\Recordlist\\RecordList\\RecordListHookInterface', 1195567845);
				}
				$cells = $hookObject->makeClip($table, $row, $cells, $this);
			}
		}
		// Compile items into a DIV-element:
		return '							<!-- CLIPBOARD PANEL: ' . $table . ':' . $row['uid'] . ' -->
											<div class="typo3-clipCtrl">' . implode('', $cells) . '</div>';
	}

	/**
	 * Creates the HTML for a reference count for the record with the UID $uid
	 * in the table $tableName.
	 *
	 * @param string $tableName
	 * @param int $uid
	 * @return string HTML of reference a link, will be empty if there are no
	 */
	protected function createReferenceHtml($tableName, $uid) {
		$rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'tablename, recuid, field',
			'sys_refindex',
			'ref_table = ' . $GLOBALS['TYPO3_DB']->fullQuoteStr($tableName, 'sys_refindex') .
			' AND ref_uid = ' . $uid . ' AND deleted = 0'
		);
		return $this->generateReferenceToolTip($rows, '\'' . $tableName . '\', \'' . $uid . '\'');
	}

	/**
	 * Creates the localization panel
	 *
	 * @param string $table The table
	 * @param array $row The record for which to make the localization panel.
	 * @return array Array with key 0/1 with content for column 1 and 2
	 */
	public function makeLocalizationPanel($table, $row) {
		$out = array(
			0 => '',
			1 => ''
		);
		// Reset translations
		$this->translations = array();
		$translations = $this->translateTools->translationInfo($table, $row['uid'], 0, $row, $this->selFieldList);
		// Language title and icon:
		$out[0] = $this->languageFlag($row[$GLOBALS['TCA'][$table]['ctrl']['languageField']]);
		if (is_array($translations)) {
			$this->translations = $translations['translations'];
			// Traverse page translations and add icon for each language that does NOT yet exist:
			$lNew = '';
			foreach ($this->pageOverlays as $lUid_OnPage => $lsysRec) {
				if (!isset($translations['translations'][$lUid_OnPage]) && $GLOBALS['BE_USER']->checkLanguageAccess($lUid_OnPage)) {
					$url = substr($this->listURL(), strlen($this->backPath));
					$href = $GLOBALS['SOBE']->doc->issueCommand('&cmd[' . $table . '][' . $row['uid'] . '][localize]='
						. $lUid_OnPage, $url . '&justLocalized=' . rawurlencode($table . ':' . $row['uid'] . ':' . $lUid_OnPage));
					$language = BackendUtility::getRecord('sys_language', $lUid_OnPage, 'title');
					if ($this->languageIconTitles[$lUid_OnPage]['flagIcon']) {
						$lC = IconUtility::getSpriteIcon($this->languageIconTitles[$lUid_OnPage]['flagIcon']);
					} else {
						$lC = $this->languageIconTitles[$lUid_OnPage]['title'];
					}
					$lC = '<a href="' . htmlspecialchars($href) . '" title="'
						. htmlspecialchars($language['title']) . '">' . $lC . '</a> ';
					$lNew .= $lC;
				}
			}
			if ($lNew) {
				$out[1] .= $lNew;
			}
		} elseif ($row['l18n_parent']) {
			$out[0] = '&nbsp;&nbsp;&nbsp;&nbsp;' . $out[0];
		}
		return $out;
	}

	/**
	 * Create the selector box for selecting fields to display from a table:
	 *
	 * @param string $table Table name
	 * @param bool $formFields If TRUE, form-fields will be wrapped around the table.
	 * @return string HTML table with the selector box (name: displayFields['.$table.'][])
	 */
	public function fieldSelectBox($table, $formFields = TRUE) {
		// Init:
		$formElements = array('', '');
		if ($formFields) {
			$formElements = array('<form action="' . htmlspecialchars($this->listURL()) . '" method="post">', '</form>');
		}
		// Load already selected fields, if any:
		$setFields = is_array($this->setFields[$table]) ? $this->setFields[$table] : array();
		// Request fields from table:
		$fields = $this->makeFieldList($table, FALSE, TRUE);
		// Add pseudo "control" fields
		$fields[] = '_PATH_';
		$fields[] = '_REF_';
		$fields[] = '_LOCALIZATION_';
		$fields[] = '_CONTROL_';
		$fields[] = '_CLIPBOARD_';
		// Create an option for each field:
		$opt = array();
		$opt[] = '<option value=""></option>';
		foreach ($fields as $fN) {
			// Field label
			$fL = is_array($GLOBALS['TCA'][$table]['columns'][$fN])
				? rtrim($GLOBALS['LANG']->sL($GLOBALS['TCA'][$table]['columns'][$fN]['label']), ':')
				: '[' . $fN . ']';
			$opt[] = '
											<option value="' . $fN . '"'
				. (in_array($fN, $setFields) ? ' selected="selected"' : '') . '>' . htmlspecialchars($fL) . '</option>';
		}
		// Compile the options into a multiple selector box:
		$lMenu = '
										<select size="'
			. \TYPO3\CMS\Core\Utility\MathUtility::forceIntegerInRange(count($fields) + 1, 3, 20)
			. '" multiple="multiple" name="displayFields[' . $table . '][]">' . implode('', $opt) . '
										</select>
				';
		// Table with the field selector::
		$content = $formElements[0] . '

				<!--
					Field selector for extended table view:
				-->
				<table border="0" cellpadding="0" cellspacing="0" id="typo3-dblist-fieldSelect">
					<tr>
						<td>' . $lMenu . '</td>
						<td><input type="submit" name="search" value="'
			. $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:labels.setFields', TRUE) . '" /></td>
					</tr>
				</table>
			' . $formElements[1];
		return '<div class="db_list-fieldSelect">' . $content . '</div>';
	}

	/*********************************
	 *
	 * Helper functions
	 *
	 *********************************/
	/**
	 * Creates a link around $string. The link contains an onclick action
	 * which submits the script with some clipboard action.
	 * Currently, this is used for setting elements / delete elements.
	 *
	 * @param string $string The HTML content to link (image/text)
	 * @param string $table Table name
	 * @param string $cmd Clipboard command (eg. "setCB" or "delete")
	 * @param string $warning Warning text, if any ("delete" uses this for confirmation)
	 * @return string <a> tag wrapped link.
	 */
	public function linkClipboardHeaderIcon($string, $table, $cmd, $warning = '') {
		$onClickEvent = 'document.dblistForm.cmd.value=\'' . $cmd . '\';document.dblistForm.cmd_table.value=\''
			. $table . '\';document.dblistForm.submit();';
		if ($warning) {
			$onClickEvent = 'if (confirm(' . GeneralUtility::quoteJSvalue($warning) . ')){' . $onClickEvent . '}';
		}
		return '<a href="#" onclick="' . htmlspecialchars(($onClickEvent . 'return false;')) . '">' . $string . '</a>';
	}

	/**
	 * Returns TRUE if a numeric clipboard pad is selected/active
	 *
	 * @return bool
	 */
	public function clipNumPane() {
		return in_Array('_CLIPBOARD_', $this->fieldArray) && $this->clipObj->current != 'normal';
	}

	/**
	 * Creates a sort-by link on the input string ($code).
	 * It will automatically detect if sorting should be ascending or descending depending on $this->sortRev.
	 * Also some fields will not be possible to sort (including if single-table-view is disabled).
	 *
	 * @param string $code The string to link (text)
	 * @param string $field The fieldname represented by the title ($code)
	 * @param string $table Table name
	 * @return string Linked $code variable
	 */
	public function addSortLink($code, $field, $table) {
		// Certain circumstances just return string right away (no links):
		if ($field == '_CONTROL_' || $field == '_LOCALIZATION_' || $field == '_CLIPBOARD_' || $field == '_REF_' || $this->disableSingleTableView) {
			return $code;
		}
		// If "_PATH_" (showing record path) is selected, force sorting by pid field (will at least group the records!)
		if ($field == '_PATH_') {
			$field = 'pid';
		}
		//	 Create the sort link:
		$sortUrl = $this->listURL('', -1, 'sortField,sortRev,table,firstElementNumber') . '&table=' . $table
			. '&sortField=' . $field . '&sortRev=' . ($this->sortRev || $this->sortField != $field ? 0 : 1);
		$sortArrow = $this->sortField === $field
			? IconUtility::getSpriteIcon('status-status-sorting-' . ($this->sortRev ? 'desc' : 'asc'))
			: '';
		// Return linked field:
		return '<a href="' . htmlspecialchars($sortUrl) . '">' . $code . $sortArrow . '</a>';
	}

	/**
	 * Returns the path for a certain pid
	 * The result is cached internally for the session, thus you can call
	 * this function as much as you like without performance problems.
	 *
	 * @param int $pid The page id for which to get the path
	 * @return string The path.
	 */
	public function recPath($pid) {
		if (!isset($this->recPath_cache[$pid])) {
			$this->recPath_cache[$pid] = BackendUtility::getRecordPath($pid, $this->perms_clause, 20);
		}
		return $this->recPath_cache[$pid];
	}

	/**
	 * Returns TRUE if a link for creating new records should be displayed for $table
	 *
	 * @param string $table Table name
	 * @return bool Returns TRUE if a link for creating new records should be displayed for $table
	 * @see \TYPO3\CMS\Backend\Controller\NewRecordController::showNewRecLink
	 */
	public function showNewRecLink($table) {
		// No deny/allow tables are set:
		if (!count($this->allowedNewTables) && !count($this->deniedNewTables)) {
			return TRUE;
		}
		return !in_array($table, $this->deniedNewTables)
			&& (!count($this->allowedNewTables) || in_array($table, $this->allowedNewTables));
	}

	/**
	 * Creates the "&returnUrl" parameter for links - this is used when the script links
	 * to other scripts and passes its own URL with the link so other scripts can return to the listing again.
	 * Uses REQUEST_URI as value.
	 *
	 * @return string
	 */
	public function makeReturnUrl() {
		return '&returnUrl=' . rawurlencode(GeneralUtility::getIndpEnv('REQUEST_URI'));
	}

	/************************************
	 *
	 * CSV related functions
	 *
	 ************************************/
	/**
	 * Initializes internal csvLines array with the header of field names
	 *
	 * @return void
	 */
	protected function initCSV() {
		$this->addHeaderRowToCSV();
	}

	/**
	 * Add header line with field names as CSV line
	 *
	 * @return void
	 */
	protected function addHeaderRowToCSV() {
		// Add header row, control fields will be reduced inside addToCSV()
		$this->addToCSV(array_combine($this->fieldArray, $this->fieldArray));
	}

	/**
	 * Adds selected columns of one table row as CSV line.
	 *
	 * @param array $row Record array, from which the values of fields found in $this->fieldArray will be listed in the CSV output.
	 * @return void
	 */
	protected function addToCSV(array $row = array()) {
		$rowReducedByControlFields = self::removeControlFieldsFromFieldRow($row);
		$rowReducedToSelectedColumns = array_intersect_key($rowReducedByControlFields, array_flip($this->fieldArray));
		$this->setCsvRow($rowReducedToSelectedColumns);
	}

	/**
	 * Remove control fields from row for CSV export
	 *
	 * @param array $row fieldNames => fieldValues
	 * @return array Input array reduces by control fields
	 */
	static protected function removeControlFieldsFromFieldRow(array $row = array()) {
		// Possible control fields in a list row
		$controlFields = array(
			'_PATH_',
			'_REF_',
			'_CONTROL_',
			'_AFTERCONTROL_',
			'_AFTERREF_',
			'_CLIPBOARD_',
			'_LOCALIZATION_',
			'_LOCALIZATION_b'
		);
		return array_diff_key($row, array_flip($controlFields));
	}

	/**
	 * Adds input row of values to the internal csvLines array as a CSV formatted line
	 *
	 * @param array $csvRow Array with values to be listed.
	 * @return void
	 */
	public function setCsvRow($csvRow) {
		$this->csvLines[] = GeneralUtility::csvValues($csvRow);
	}

	/**
	 * Compiles the internal csvLines array to a csv-string and outputs it to the browser.
	 * This function exits!
	 *
	 * @param string $prefix Filename prefix:
	 * @return void EXITS php execution!
	 */
	public function outputCSV($prefix) {
		// Setting filename:
		$filename = $prefix . '_' . date('dmy-Hi') . '.csv';
		// Creating output header:
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename=' . $filename);
		// Cache-Control header is needed here to solve an issue with browser IE and
		// versions lower then 9. See for more information: http://support.microsoft.com/kb/323308
		header("Cache-Control: ''");
		// Printing the content of the CSV lines:
		echo implode(chr(13) . chr(10), $this->csvLines);
		// Exits:
		die;
	}

}

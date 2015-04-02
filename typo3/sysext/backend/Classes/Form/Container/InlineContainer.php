<?php
namespace TYPO3\CMS\Backend\Form\Container;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Versioning\VersionState;
use TYPO3\CMS\Backend\Form\DataPreprocessor;
use TYPO3\CMS\Lang\LanguageService;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Backend\Form\Utility\FormEngineUtility;
use TYPO3\CMS\Backend\Utility\IconUtility;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Messaging\FlashMessage;

class InlineContainer extends AbstractContainer {

	const Structure_Separator = '-';

	const FlexForm_Separator = '---';

	const FlexForm_Substitute = ':';

	const Disposal_AttributeName = 'Disposal_AttributeName';

	const Disposal_AttributeId = 'Disposal_AttributeId';

	/**
	 * Set to TRUE after first call to render(). Used to initialize the other statics
	 *
	 * @var bool
	 */
	protected static $isInitialized = FALSE;

	/**
	 * The first call of an inline type appeared on this page (pid of record).
	 * This is initialized of first inline element that is rendered only and won't change after that again.
	 *
	 * @var int|NULL
	 */
	protected static $inlineFirstPid = NULL;

	/**
	 * "inline" part of backend user->uc(), holding mostly (un)collapse state of inline items
	 *
	 * @var array
	 */
	protected static $inlineView = array();

	/**
	 * Keys: form, object -> hold the name/id for each of them
	 *
	 * @var array
	 */
	protected $inlineNames = array();

	/**
	 * Inline data array used for JSON output
	 *
	 * @var array
	 */
	public $inlineData = array();

	/**
	 * The structure/hierarchy where working in, e.g. cascading inline tables
	 *
	 * @var array
	 */
	protected $inlineStructure = array();

	/**
	 * @return array As defined in initializeResultArray() of AbstractNode
	 */
	public function render() {
		$backendUser = $this->getBackendUserAuthentication();
		$languageService = $this->getLanguageService();

		$table = $this->globalOptions['table'];
		$row = $this->globalOptions['databaseRow'];
		$field = $this->globalOptions['fieldName'];
		$parameterArray = $this->globalOptions['parameterArray'];

		$resultArray = $this->initializeResultArray();
		$html = '';

		// An inline field must have a foreign_table, if not, stop all further inline actions for this field
		if (
			!$parameterArray['fieldConf']['config']['foreign_table']
			|| !is_array($GLOBALS['TCA'][$parameterArray['fieldConf']['config']['foreign_table']])
		) {
			return $resultArray;
		}

		$config = $this->ensureConfiguration($parameterArray['fieldConf']['config']);
		$foreign_table = $config['foreign_table'];

		$language = 0;
		if (BackendUtility::isTableLocalizable($table)) {
			$language = (int)$row[$GLOBALS['TCA'][$table]['ctrl']['languageField']];
		}
		$minItems = MathUtility::forceIntegerInRange($config['minitems'], 0);
		$maxItems = MathUtility::forceIntegerInRange($config['maxitems'], 0);
		if (!$maxItems) {
			$maxItems = 100000;
		}
		$resultArray['requiredElements'][$parameterArray['itemFormElName']] = array(
			$minItems,
			$maxItems,
			'imgName' => $table . '_' . $row['uid'] . '_' . $field
		);

		// @todo: check inlineStructure access - one level missing there now

		// Remember the page id (pid of record) where inline editing started first
		// We need that pid for ajax calls, so that they would know where the action takes place on the page structure
		// @todo: The static construct relies on the fact that FormEngine is not used more than once in two different contexts
		if (!static::$isInitialized) {
			static::$isInitialized = TRUE;
			// If this record is not new, try to fetch the inlineView states
			// @todo Add checking/cleaning for unused tables, records, etc. to save space in uc-field
			if (MathUtility::canBeInterpretedAsInteger($row['uid'])) {
				$inlineView = unserialize($backendUser->uc['inlineView']);
				static::$inlineView = $inlineView[$table][$row['uid']];
			}
			// If the parent is a page, use the uid(!) of the (new?) page as pid for the child records:
			if ($table == 'pages') {
				$liveVersionId = BackendUtility::getLiveVersionIdOfRecord('pages', $row['uid']);
				static::$inlineFirstPid = is_null($liveVersionId) ? $row['uid'] : $liveVersionId;
			} elseif ($row['pid'] < 0) {
				$prevRec = BackendUtility::getRecord($table, abs($row['pid']));
				static::$inlineFirstPid = $prevRec['pid'];
			} else {
				static::$inlineFirstPid = $row['pid'];
			}
		}

		$structure = array(
			'table' => $table,
			'uid' => $row['uid'],
			'field' => $field,
			'config' => $config,
			'localizationMode' => BackendUtility::getInlineLocalizationMode($table, $config),
		);
		// Extract FlexForm parts (if any) from element name, e.g. array('vDEF', 'lDEF', 'FlexField', 'vDEF')
		if (!empty($parameterArray['itemFormElName'])) {
			$flexFormParts = $this->extractFlexFormParts($parameterArray['itemFormElName']);
			if ($flexFormParts !== NULL) {
				$structure['flexform'] = $flexFormParts;
			}
		}
		$this->inlineStructure['stable'] = $structure;
		$structurePath = array(
			$structure,
			self::Disposal_AttributeId,
		);
		$structurePath = implode(self::Structure_Separator, $structurePath);
		$this->inlineNames = array(
			'form' => $this->globalOptions['prependFormFieldNames'] . $this->getStructureItemName($structure, self::Disposal_AttributeName),
			'object' => 'data' . self::Structure_Separator . static::$inlineFirstPid . self::Structure_Separator . $structurePath,
		);
		// e.g. data[<table>][<uid>][<field>]
		$nameForm = $this->inlineNames['form'];
		// e.g. data-<pid>-<table1>-<uid1>-<field1>-<table2>-<uid2>-<field2>
		$nameObject = $this->inlineNames['object'];

		// Get the records related to this inline record
		$relatedRecords = $this->getRelatedRecords($table, $field, $row, $parameterArray, $config);

		// Set the first and last record to the config array
		$relatedRecordsUids = array_keys($relatedRecords['records']);
		$config['inline']['first'] = reset($relatedRecordsUids);
		$config['inline']['last'] = end($relatedRecordsUids);

		$top = $structure;

		$this->inlineData['config'][$nameObject] = array(
			'table' => $foreign_table,
			'md5' => md5($nameObject)
		);
		$this->inlineData['config'][$nameObject . self::Structure_Separator . $foreign_table] = array(
			'min' => $minItems,
			'max' => $maxItems,
			'sortable' => $config['appearance']['useSortable'],
			'top' => array(
				'table' => $top['table'],
				'uid' => $top['uid']
			),
			'context' => array(
				'config' => $config,
				'hmac' => GeneralUtility::hmac(serialize($config)),
			),
		);
		// @todo: this is used in JS ...
		//$this->inlineData['nested'][$nameObject] = $this->formEngine->getDynNestedStack(FALSE, $this->isAjaxCall);

		// If relations are required to be unique, get the uids that have already been used on the foreign side of the relation
		if ($config['foreign_unique']) {
			// If uniqueness *and* selector are set, they should point to the same field - so, get the configuration of one:
			$selConfig = $this->getPossibleRecordsSelectorConfig($config, $config['foreign_unique']);
			// Get the used unique ids:
			$uniqueIds = $this->getUniqueIds($relatedRecords['records'], $config, $selConfig['type'] == 'groupdb');
			$possibleRecords = $this->getPossibleRecords($table, $field, $row, $config, 'foreign_unique');
			$uniqueMax = $config['appearance']['useCombination'] || $possibleRecords === FALSE ? -1 : count($possibleRecords);
			$this->inlineData['unique'][$nameObject . self::Structure_Separator . $foreign_table] = array(
				'max' => $uniqueMax,
				'used' => $uniqueIds,
				'type' => $selConfig['type'],
				'table' => $config['foreign_table'],
				'elTable' => $selConfig['table'],
				// element/record table (one step down in hierarchy)
				'field' => $config['foreign_unique'],
				'selector' => $selConfig['selector'],
				'possible' => $this->getPossibleRecordsFlat($possibleRecords)
			);
		}

		// Render the localization links
		$localizationLinks = '';
		if ($language > 0 && $row[$GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField']] > 0 && MathUtility::canBeInterpretedAsInteger($row['uid'])) {
			// Add the "Localize all records" link before all child records:
			if (isset($config['appearance']['showAllLocalizationLink']) && $config['appearance']['showAllLocalizationLink']) {
				$localizationLinks .= ' ' . $this->getLevelInteractionLink('localize', $nameObject . self::Structure_Separator . $foreign_table, $config);
			}
			// Add the "Synchronize with default language" link before all child records:
			if (isset($config['appearance']['showSynchronizationLink']) && $config['appearance']['showSynchronizationLink']) {
				$localizationLinks .= ' ' . $this->getLevelInteractionLink('synchronize', $nameObject . self::Structure_Separator . $foreign_table, $config);
			}
		}
		// If it's required to select from possible child records (reusable children), add a selector box
		if ($config['foreign_selector'] && $config['appearance']['showPossibleRecordsSelector'] !== FALSE) {
			// If not already set by the foreign_unique, set the possibleRecords here and the uniqueIds to an empty array
			if (!$config['foreign_unique']) {
				$possibleRecords = $this->getPossibleRecords($table, $field, $row, $config);
				$uniqueIds = array();
			}
			$selectorBox = $this->renderPossibleRecordsSelector($possibleRecords, $config, $uniqueIds);
			$html .= $selectorBox . $localizationLinks;
		}
		// Render the level links (create new record):
		$levelLinks = $this->getLevelInteractionLink('newRecord', $nameObject . self::Structure_Separator . $foreign_table, $config);

		// Wrap all inline fields of a record with a <div> (like a container)
		$html .= '<div class="form-group" id="' . $nameObject . '">';
		// Define how to show the "Create new record" link - if there are more than maxitems, hide it
		if ($relatedRecords['count'] >= $maxItems || $uniqueMax > 0 && $relatedRecords['count'] >= $uniqueMax) {
			$config['inline']['inlineNewButtonStyle'] = 'display: none;';
		}
		// Add the level links before all child records:
		if (in_array($config['appearance']['levelLinksPosition'], array('both', 'top'))) {
			$html .= '<div class="form-group">' . $levelLinks . $localizationLinks . '</div>';
		}
		$title = $languageService->sL($parameterArray['fieldConf']['label']);
		$html .= '<div class="panel-group panel-hover" data-title="' . htmlspecialchars($title) . '" id="' . $nameObject . '_records">';

		$relationList = array();
		if (count($relatedRecords['records'])) {
			foreach ($relatedRecords['records'] as $rec) {
				$childArray = $this->renderForeignRecord($row['uid'], $rec, $config);
				$html .= $childArray['html'];
				$childArray['html'] = '';
				$resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $childArray);
				// @todo
				//$html .= $this->renderForeignRecord($row['uid'], $rec, $config);

				if (!isset($rec['__virtual']) || !$rec['__virtual']) {
					$relationList[] = $rec['uid'];
				}
			}
		}
		$html .= '</div>';
		// Add the level links after all child records:
		if (in_array($config['appearance']['levelLinksPosition'], array('both', 'bottom'))) {
			$html .= $levelLinks . $localizationLinks;
		}
		if (is_array($config['customControls'])) {
			$html .= '<div id="' . $nameObject . '_customControls">';
			foreach ($config['customControls'] as $customControlConfig) {
				$parameters = array(
					'table' => $table,
					'field' => $field,
					'row' => $row,
					'nameObject' => $nameObject,
					'nameForm' => $nameForm,
					'config' => $config
				);
				$html .= GeneralUtility::callUserFunction($customControlConfig, $parameters, $this);
			}
			$html .= '</div>';
		}
		// Add Drag&Drop functions for sorting to FormEngine::$additionalJS_post
		if (count($relationList) > 1 && $config['appearance']['useSortable']) {
			$resultArray['additionalJavaScriptPost'][] = 'inline.createDragAndDropSorting("' . $nameObject . '_records' . '");';
		}
		// Publish the uids of the child records in the given order to the browser
		$html .= '<input type="hidden" name="' . $nameForm . '" value="' . implode(',', $relationList) . '" class="inlineRecord" />';
		// Close the wrap for all inline fields (container)
		$html .= '</div>';

		// @todo: foo ..
		// On finishing this section, remove the last item from the structure stack
//		$this->popStructure();
		// If this was the first call to the inline type, restore the values
//		if (!$this->getStructureDepth()) {
			// @todo: aaaaarg, this is "static" per "entry tree", not globally static ... see original
//			unset($this->inlineFirstPid);
//		}

		$resultArray['html'] = $html;
		return $resultArray;
	}

	/**
	 * Render the form-fields of a related (foreign) record.
	 *
	 * @param string $parentUid The uid of the parent (embedding) record (uid or NEW...)
	 * @param array $rec The table record of the child/embedded table (normally post-processed by \TYPO3\CMS\Backend\Form\DataPreprocessor)
	 * @param array $config Content of $PA['fieldConf']['config']
	 * @return string The HTML code for this "foreign record
	 */
	protected function renderForeignRecord($parentUid, $rec, $config = array()) {
		$foreign_table = $config['foreign_table'];
		$foreign_selector = $config['foreign_selector'];

		// Send a mapping information to the browser via JSON:
		// e.g. data[<curTable>][<curId>][<curField>] => data-<pid>-<parentTable>-<parentId>-<parentField>-<curTable>-<curId>-<curField>
		$this->inlineData['map'][$this->inlineNames['form']] = $this->inlineNames['object'];
		// Set this variable if we handle a brand new unsaved record:
		$isNewRecord = !MathUtility::canBeInterpretedAsInteger($rec['uid']);
		// Set this variable if the record is virtual and only show with header and not editable fields:
		$isVirtualRecord = isset($rec['__virtual']) && $rec['__virtual'];
		// If there is a selector field, normalize it:
		if ($foreign_selector) {
			$rec[$foreign_selector] = $this->normalizeUid($rec[$foreign_selector]);
		}
		if (!$this->checkAccess(($isNewRecord ? 'new' : 'edit'), $foreign_table, $rec['uid'])) {
			return FALSE;
		}
		// Get the current naming scheme for DOM name/id attributes:
		$nameObject = $this->inlineNames['object'];
		$appendFormFieldNames = '[' . $foreign_table . '][' . $rec['uid'] . ']';
		$objectId = $nameObject . self::Structure_Separator . $foreign_table . self::Structure_Separator . $rec['uid'];
		$class = '';
		if (!$isVirtualRecord) {
			// Get configuration:
			$collapseAll = isset($config['appearance']['collapseAll']) && $config['appearance']['collapseAll'];
			$expandAll = isset($config['appearance']['collapseAll']) && !$config['appearance']['collapseAll'];
			$ajaxLoad = isset($config['appearance']['ajaxLoad']) && !$config['appearance']['ajaxLoad'] ? FALSE : TRUE;
			if ($isNewRecord) {
				// Show this record expanded or collapsed
				$isExpanded = $expandAll || (!$collapseAll ? 1 : 0);
			} else {
				$isExpanded = $config['renderFieldsOnly'] || !$collapseAll && $this->getExpandedCollapsedState($foreign_table, $rec['uid']) || $expandAll;
			}
			// Render full content ONLY IF this is a AJAX-request, a new record, the record is not collapsed or AJAX-loading is explicitly turned off
			if ($isNewRecord || $isExpanded || !$ajaxLoad) {
				// @todo
//				$combination = $this->renderCombinationTable($rec, $appendFormFieldNames, $config);
				$overruleTypesArray = isset($config['foreign_types']) ? $config['foreign_types'] : array();
				$fields = $this->renderMainFields($foreign_table, $rec, $overruleTypesArray);
				// Replace returnUrl in Wizard-Code, if this is an AJAX call
				$ajaxArguments = GeneralUtility::_GP('ajax');
				if (isset($ajaxArguments[2]) && trim($ajaxArguments[2]) != '') {
					$fields = str_replace('P[returnUrl]=%2F' . rawurlencode(TYPO3_mainDir) . 'ajax.php', 'P[returnUrl]=' . rawurlencode($ajaxArguments[2]), $fields);
				}
			} else {
				$combination = '';
				// This string is the marker for the JS-function to check if the full content has already been loaded
				$fields = '<!--notloaded-->';
			}
			if ($isNewRecord) {
				// Get the top parent table
				$top = $this->getStructureLevel(0);
				$ucFieldName = 'uc[inlineView][' . $top['table'] . '][' . $top['uid'] . ']' . $appendFormFieldNames;
				// Set additional fields for processing for saving
				$fields .= '<input type="hidden" name="' . $this->globalOptions['prependFormFieldNames'] . $appendFormFieldNames . '[pid]" value="' . $rec['pid'] . '"/>';
				$fields .= '<input type="hidden" name="' . $ucFieldName . '" value="' . $isExpanded . '" />';
			} else {
				// Set additional field for processing for saving
				$fields .= '<input type="hidden" name="' . $this->globalOptions['prependCmdFieldNames'] . $appendFormFieldNames . '[delete]" value="1" disabled="disabled" />';
				if (!$isExpanded
					&& !empty($GLOBALS['TCA'][$foreign_table]['ctrl']['enablecolumns']['disabled'])
					&& $ajaxLoad
				) {
					$checked = !empty($rec['hidden']) ? ' checked="checked"' : '';
					$fields .= '<input type="checkbox" name="' . $this->globalOptions['prependFormFieldNames'] . $appendFormFieldNames . '[hidden]_0" value="1"' . $checked . ' />';
					$fields .= '<input type="input" name="' . $this->globalOptions['prependFormFieldNames'] . $appendFormFieldNames . '[hidden]" value="' . $rec['hidden'] . '" />';
				}
			}
			// If this record should be shown collapsed
			if (!$isExpanded) {
				$class = 'panel-collapsed';
			}
		}
		if ($config['renderFieldsOnly']) {
			$out = $fields . $combination;
		} else {
			// Set the record container with data for output
			if ($isVirtualRecord) {
				$class .= ' t3-form-field-container-inline-placeHolder';
			}
			if (isset($rec['hidden']) && (int)$rec['hidden']) {
				$class .= ' t3-form-field-container-inline-hidden';
			}
			$class .= ($isNewRecord ? ' inlineIsNewRecord' : '');
			$out = '
				<div class="panel panel-default panel-condensed ' . trim($class) . '" id="' . $objectId . '_div">
					<div class="panel-heading" data-toggle="formengine-inline" id="' . $objectId . '_header">
						<div class="form-irre-header">
							<div class="form-irre-header-cell form-irre-header-icon">
								<span class="caret"></span>
							</div>
							' . $this->renderForeignRecordHeader($parentUid, $foreign_table, $rec, $config, $isVirtualRecord) . '
						</div>
					</div>
					<div class="panel-collapse" id="' . $objectId . '_fields" data-expandSingle="' . ($config['appearance']['expandSingle'] ? 1 : 0) . '" data-returnURL="' . htmlspecialchars(GeneralUtility::getIndpEnv('REQUEST_URI')) . '">' . $fields . $combination . '</div>
				</div>';
		}
		return $out;
	}

	/**
	 * Render a table with FormEngine, that occurs on a intermediate table but should be editable directly,
	 * so two tables are combined (the intermediate table with attributes and the sub-embedded table).
	 * -> This is a direct embedding over two levels!
	 *
	 * @param array $rec The table record of the child/embedded table (normaly post-processed by \TYPO3\CMS\Backend\Form\DataPreprocessor)
	 * @param string $appendFormFieldNames The [<table>][<uid>] of the parent record (the intermediate table)
	 * @param array $config content of $PA['fieldConf']['config']
	 * @return string A HTML string with <table> tag around.
	 */
	public function renderCombinationTable(&$rec, $appendFormFieldNames, $config = array()) {
		$foreign_table = $config['foreign_table'];
		$foreign_selector = $config['foreign_selector'];
		$out = '';
		if ($foreign_selector && $config['appearance']['useCombination']) {
			$comboConfig = $GLOBALS['TCA'][$foreign_table]['columns'][$foreign_selector]['config'];
			// If record does already exist, load it:
			if ($rec[$foreign_selector] && MathUtility::canBeInterpretedAsInteger($rec[$foreign_selector])) {
				$comboRecord = $this->getRecord($comboConfig['foreign_table'], $rec[$foreign_selector]);
				$isNewRecord = FALSE;
			} else {
				$comboRecord = $this->getNewRecord(static::$inlineFirstPid, $comboConfig['foreign_table']);
				$isNewRecord = TRUE;
			}
			$flashMessage = GeneralUtility::makeInstance(
				FlashMessage::class,
				$this->getLanguageService()->sL('LLL:EXT:lang/locallang_core.xlf:warning.inline_use_combination'),
				'',
				FlashMessage::WARNING
			);
			$out = $flashMessage->render();
			// Get the FormEngine interpretation of the TCA of the child table
			$out .= $this->renderMainFields($comboConfig['foreign_table'], $comboRecord);
			// If this is a new record, add a pid value to store this record and the pointer value for the intermediate table
			if ($isNewRecord) {
				$comboFormFieldName = $this->globalOptions['prependFormFieldNames'] . '[' . $comboConfig['foreign_table'] . '][' . $comboRecord['uid'] . '][pid]';
				$out .= '<input type="hidden" name="' . $comboFormFieldName . '" value="' . $comboRecord['pid'] . '" />';
			}
			// If the foreign_selector field is also responsible for uniqueness, tell the browser the uid of the "other" side of the relation
			if ($isNewRecord || $config['foreign_unique'] == $foreign_selector) {
				$parentFormFieldName = $this->globalOptions['prependFormFieldNames'] . $appendFormFieldNames . '[' . $foreign_selector . ']';
				$out .= '<input type="hidden" name="' . $parentFormFieldName . '" value="' . $comboRecord['uid'] . '" />';
			}
		}
		return $out;
	}

	/**
	 * Adds / adapts some general options of main TCA config
	 *
	 * @param array $config TCA field configuration
	 * @return array Modified configuration
	 */
	protected function ensureConfiguration($config) {
		// Init appearance if not set:
		if (!isset($config['appearance']) || !is_array($config['appearance'])) {
			$config['appearance'] = array();
		}
		// Set the position/appearance of the "Create new record" link:
		if (
			isset($config['foreign_selector'])
			&& $config['foreign_selector']
			&& (!isset($config['appearance']['useCombination']) || !$config['appearance']['useCombination'])
		) {
			$config['appearance']['levelLinksPosition'] = 'none';
		} elseif (
			!isset($config['appearance']['levelLinksPosition'])
			|| !in_array($config['appearance']['levelLinksPosition'], array('top', 'bottom', 'both', 'none'))
		) {
			$config['appearance']['levelLinksPosition'] = 'top';
		}
		// Defines which controls should be shown in header of each record:
		$enabledControls = array(
			'info' => TRUE,
			'new' => TRUE,
			'dragdrop' => TRUE,
			'sort' => TRUE,
			'hide' => TRUE,
			'delete' => TRUE,
			'localize' => TRUE
		);
		if (isset($config['appearance']['enabledControls']) && is_array($config['appearance']['enabledControls'])) {
			$config['appearance']['enabledControls'] = array_merge($enabledControls, $config['appearance']['enabledControls']);
		} else {
			$config['appearance']['enabledControls'] = $enabledControls;
		}
		return $config;
	}

	/**
	 * Extracts FlexForm parts of a form element name like
	 * data[table][uid][field][sDEF][lDEF][FlexForm][vDEF]
	 *
	 * @param string $formElementName The form element name
	 * @return array|NULL
	 */
	protected function extractFlexFormParts($formElementName) {
		$flexFormParts = NULL;

		$matches = array();
		$prefix = preg_quote($this->globalOptions['prependFormFieldNames'], '#');

		if (preg_match('#^' . $prefix . '(?:\[[^]]+\]){3}(\[data\](?:\[[^]]+\]){4,})$#', $formElementName, $matches)) {
			$flexFormParts = GeneralUtility::trimExplode(
				'][',
				trim($matches[1], '[]')
			);
		}

		return $flexFormParts;
	}

	/**
	 * Create a name/id for usage in HTML output of a level of the structure stack to be used in form names.
	 *
	 * @param array $levelData Array of a level of the structure stack (containing the keys table, uid and field)
	 * @param string $disposal How the structure name is used (e.g. as <div id="..."> or <input name="..." />)
	 * @return string The name/id of that level, to be used for HTML output
	 */
	protected function getStructureItemName($levelData, $disposal = self::Disposal_AttributeId) {
		$name = NULL;

		if (is_array($levelData)) {
			$parts = array($levelData['table'], $levelData['uid']);

			if (!empty($levelData['field'])) {
				$parts[] = $levelData['field'];
			}

			// Use in name attributes:
			if ($disposal === self::Disposal_AttributeName) {
				if (!empty($levelData['field']) && !empty($levelData['flexform'])) {
					$parts[] = implode('][', $levelData['flexform']);
				}
				$name = '[' . implode('][', $parts) . ']';
				// Use in object id attributes:
			} else {
				$name = implode(self::Structure_Separator, $parts);

				if (!empty($levelData['field']) && !empty($levelData['flexform'])) {
					array_unshift($levelData['flexform'], $name);
					$name = implode(self::FlexForm_Separator, $levelData['flexform']);
				}
			}
		}

		return $name;
	}

	/**
	 * Get the related records of the embedding item, this could be 1:n, m:n.
	 * Returns an associative array with the keys records and count. 'count' contains only real existing records on the current parent record.
	 *
	 * @param string $table The table name of the record
	 * @param string $field The field name which this element is supposed to edit
	 * @param array $row The record data array where the value(s) for the field can be found
	 * @param array $PA An array with additional configuration options.
	 * @param array $config (Redundant) content of $PA['fieldConf']['config'] (for convenience)
	 * @return array The records related to the parent item as associative array.
	 */
	protected function getRelatedRecords($table, $field, $row, &$PA, $config) {
		$language = 0;
		$pid = $row['pid'];
		$elements = $PA['itemFormElValue'];
		$foreignTable = $config['foreign_table'];
		$localizationMode = BackendUtility::getInlineLocalizationMode($table, $config);
		if ($localizationMode != FALSE) {
			$language = (int)$row[$GLOBALS['TCA'][$table]['ctrl']['languageField']];
			$transOrigPointer = (int)$row[$GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField']];
			$transOrigTable = BackendUtility::getOriginalTranslationTable($table);

			if ($language > 0 && $transOrigPointer) {
				// Localization in mode 'keep', isn't a real localization, but keeps the children of the original parent record:
				if ($localizationMode == 'keep') {
					$transOrigRec = $this->getRecord($transOrigTable, $transOrigPointer);
					$elements = $transOrigRec[$field];
					$pid = $transOrigRec['pid'];
				} elseif ($localizationMode == 'select') {
					$transOrigRec = $this->getRecord($transOrigTable, $transOrigPointer);
					$pid = $transOrigRec['pid'];
					$fieldValue = $transOrigRec[$field];

					// Checks if it is a flexform field
					if ($GLOBALS['TCA'][$table]['columns'][$field]['config']['type'] === 'flex') {
						$flexFormParts = $this->extractFlexFormParts($PA['itemFormElName']);
						$flexData = GeneralUtility::xml2array($fieldValue);
						/** @var  $flexFormTools  \TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools */
						$flexFormTools = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools::class);
						$flexFormFieldValue = $flexFormTools->getArrayValueByPath($flexFormParts, $flexData);

						if ($flexFormFieldValue !== NULL) {
							$fieldValue = $flexFormFieldValue;
						}
					}

					$recordsOriginal = $this->getRelatedRecordsArray($pid, $foreignTable, $fieldValue);
				}
			}
		}
		$records = $this->getRelatedRecordsArray($pid, $foreignTable, $elements);
		$relatedRecords = array('records' => $records, 'count' => count($records));
		// Merge original language with current localization and show differences:
		if (!empty($recordsOriginal)) {
			$options = array(
				'showPossible' => isset($config['appearance']['showPossibleLocalizationRecords']) && $config['appearance']['showPossibleLocalizationRecords'],
				'showRemoved' => isset($config['appearance']['showRemovedLocalizationRecords']) && $config['appearance']['showRemovedLocalizationRecords']
			);
			// Either show records that possibly can localized or removed
			if ($options['showPossible'] || $options['showRemoved']) {
				$relatedRecords['records'] = $this->getLocalizationDifferences($foreignTable, $options, $recordsOriginal, $records);
				// Otherwise simulate localizeChildrenAtParentLocalization behaviour when creating a new record
				// (which has language and translation pointer values set)
			} elseif (!empty($config['behaviour']['localizeChildrenAtParentLocalization']) && !MathUtility::canBeInterpretedAsInteger($row['uid'])) {
				if (!empty($GLOBALS['TCA'][$foreignTable]['ctrl']['transOrigPointerField'])) {
					$foreignLanguageField = $GLOBALS['TCA'][$foreignTable]['ctrl']['languageField'];
				}
				if (!empty($GLOBALS['TCA'][$foreignTable]['ctrl']['transOrigPointerField'])) {
					$foreignTranslationPointerField = $GLOBALS['TCA'][$foreignTable]['ctrl']['transOrigPointerField'];
				}
				// Duplicate child records of default language in form
				foreach ($recordsOriginal as $record) {
					if (!empty($foreignLanguageField)) {
						$record[$foreignLanguageField] = $language;
					}
					if (!empty($foreignTranslationPointerField)) {
						$record[$foreignTranslationPointerField] = $record['uid'];
					}
					$newId = uniqid('NEW', TRUE);
					$record['uid'] = $newId;
					$record['pid'] = static::$inlineFirstPid;
					$relatedRecords['records'][$newId] = $record;
				}
			}
		}
		return $relatedRecords;
	}

	/**
	 * Get a single record row for a TCA table from the database.
	 * \TYPO3\CMS\Backend\Form\DataPreprocessor is used for "upgrading" the
	 * values, especially the relations.
	 *
	 * @param string $table The table to fetch data from (= foreign_table)
	 * @param string $uid The uid of the record to fetch, or the pid if a new record should be created
	 * @param string $cmd The command to perform, empty or 'new'
	 * @return array A record row from the database post-processed by \TYPO3\CMS\Backend\Form\DataPreprocessor
	 */
	protected function getRecord($table, $uid, $cmd = '') {
		$backendUser = $this->getBackendUserAuthentication();
		// Fetch workspace version of a record (if any):
		if ($cmd !== 'new' && $backendUser->workspace !== 0 && BackendUtility::isTableWorkspaceEnabled($table)) {
			$workspaceVersion = BackendUtility::getWorkspaceVersionOfRecord($backendUser->workspace, $table, $uid, 'uid,t3ver_state');
			if ($workspaceVersion !== FALSE) {
				$versionState = VersionState::cast($workspaceVersion['t3ver_state']);
				if ($versionState->equals(VersionState::DELETE_PLACEHOLDER)) {
					return FALSE;
				}
				$uid = $workspaceVersion['uid'];
			}
		}
		/** @var $trData DataPreprocessor */
		$trData = GeneralUtility::makeInstance(DataPreprocessor::class);
		$trData->addRawData = TRUE;
		$trData->lockRecords = 1;
		// If a new record should be created
		$trData->fetchRecord($table, $uid, $cmd === 'new' ? 'new' : '');
		$rec = reset($trData->regTableItems_data);
		return $rec;
	}

	/**
	 * Gets the related records of the embedding item, this could be 1:n, m:n.
	 *
	 * @param string $table The table name of the record
	 * @param string $itemList The list of related child records
	 * @return array The records related to the parent item
	 */
	protected function getRelatedRecordsArray($table, $itemList) {
		$records = array();
		$itemArray = $this->getRelatedRecordsUidArray($itemList);
		// Perform modification of the selected items array:
		foreach ($itemArray as $uid) {
			// Get the records for this uid using \TYPO3\CMS\Backend\Form\DataPreprocessor
			if ($record = $this->getRecord($table, $uid)) {
				$records[$uid] = $record;
			}
		}
		return $records;
	}

	/**
	 * Gets an array with the uids of related records out of a list of items.
	 * This list could contain more information than required. This methods just
	 * extracts the uids.
	 *
	 * @param string $itemList The list of related child records
	 * @return array An array with uids
	 */
	protected function getRelatedRecordsUidArray($itemList) {
		$itemArray = GeneralUtility::trimExplode(',', $itemList, TRUE);
		// Perform modification of the selected items array:
		foreach ($itemArray as $key => &$value) {
			$parts = explode('|', $value, 2);
			$value = $parts[0];
		}
		unset($value);
		return $itemArray;
	}

	/**
	 * Gets the difference between current localized structure and the original language structure.
	 * If there are records which once were localized but don't exist in the original version anymore, the record row is marked with '__remove'.
	 * If there are records which can be localized and exist only in the original version, the record row is marked with '__create' and '__virtual'.
	 *
	 * @param string $table The table name of the parent records
	 * @param array $options Options defining what kind of records to display
	 * @param array $recordsOriginal The uids of the child records of the original language
	 * @param array $recordsLocalization The uids of the child records of the current localization
	 * @return array Merged array of uids of the child records of both versions
	 */
	protected function getLocalizationDifferences($table, array $options, array $recordsOriginal, array $recordsLocalization) {
		$records = array();
		$transOrigPointerField = $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'];
		// Compare original to localized version of the records:
		foreach ($recordsLocalization as $uid => $row) {
			// If the record points to a original translation which doesn't exist anymore, it could be removed:
			if (isset($row[$transOrigPointerField]) && $row[$transOrigPointerField] > 0) {
				$transOrigPointer = $row[$transOrigPointerField];
				if (isset($recordsOriginal[$transOrigPointer])) {
					unset($recordsOriginal[$transOrigPointer]);
				} elseif ($options['showRemoved']) {
					$row['__remove'] = TRUE;
				}
			}
			$records[$uid] = $row;
		}
		// Process the remaining records in the original unlocalized parent:
		if ($options['showPossible']) {
			foreach ($recordsOriginal as $uid => $row) {
				$row['__create'] = TRUE;
				$row['__virtual'] = TRUE;
				$records[$uid] = $row;
			}
		}
		return $records;
	}

	/**
	 * Determine the configuration and the type of a record selector.
	 *
	 * @param array $conf TCA configuration of the parent(!) field
	 * @param string $field Field name
	 * @return array Associative array with the keys 'PA' and 'type', both are FALSE if the selector was not valid.
	 */
	protected function getPossibleRecordsSelectorConfig($conf, $field = '') {
		$foreign_table = $conf['foreign_table'];
		$foreign_selector = $conf['foreign_selector'];
		$PA = FALSE;
		$type = FALSE;
		$table = FALSE;
		$selector = FALSE;
		if ($field) {
			$PA = array();
			$PA['fieldConf'] = $GLOBALS['TCA'][$foreign_table]['columns'][$field];
			if ($PA['fieldConf'] && $conf['foreign_selector_fieldTcaOverride']) {
				ArrayUtility::mergeRecursiveWithOverrule($PA['fieldConf'], $conf['foreign_selector_fieldTcaOverride']);
			}
			$PA['fieldTSConfig'] = FormEngineUtility::getTSconfigForTableRow($foreign_table, array(), $field);
			$config = $PA['fieldConf']['config'];
			// Determine type of Selector:
			$type = $this->getPossibleRecordsSelectorType($config);
			// Return table on this level:
			$table = $type == 'select' ? $config['foreign_table'] : $config['allowed'];
			// Return type of the selector if foreign_selector is defined and points to the same field as in $field:
			if ($foreign_selector && $foreign_selector == $field && $type) {
				$selector = $type;
			}
		}
		return array(
			'PA' => $PA,
			'type' => $type,
			'table' => $table,
			'selector' => $selector
		);
	}

	/**
	 * Determine the type of a record selector, e.g. select or group/db.
	 *
	 * @param array $config TCE configuration of the selector
	 * @return mixed The type of the selector, 'select' or 'groupdb' - FALSE not valid
	 */
	protected function getPossibleRecordsSelectorType($config) {
		$type = FALSE;
		if ($config['type'] == 'select') {
			$type = 'select';
		} elseif ($config['type'] == 'group' && $config['internal_type'] == 'db') {
			$type = 'groupdb';
		}
		return $type;
	}

	/**
	 * Gets the uids of a select/selector that should be unique an have already been used.
	 *
	 * @param array $records All inline records on this level
	 * @param array $conf The TCA field configuration of the inline field to be rendered
	 * @param bool $splitValue For usage with group/db, values come like "tx_table_123|Title%20abc", but we need "tx_table" and "123
	 * @return array The uids, that have been used already and should be used unique
	 */
	protected function getUniqueIds($records, $conf = array(), $splitValue = FALSE) {
		$uniqueIds = array();
		if (isset($conf['foreign_unique']) && $conf['foreign_unique'] && count($records)) {
			foreach ($records as $rec) {
				// Skip virtual records (e.g. shown in localization mode):
				if (!isset($rec['__virtual']) || !$rec['__virtual']) {
					$value = $rec[$conf['foreign_unique']];
					// Split the value and extract the table and uid:
					if ($splitValue) {
						$valueParts = GeneralUtility::trimExplode('|', $value);
						$itemParts = explode('_', $valueParts[0]);
						$value = array(
							'uid' => array_pop($itemParts),
							'table' => implode('_', $itemParts)
						);
					}
					$uniqueIds[$rec['uid']] = $value;
				}
			}
		}
		return $uniqueIds;
	}

	/**
	 * Get possible records.
	 * Copied from FormEngine and modified.
	 *
	 * @param string $table The table name of the record
	 * @param string $field The field name which this element is supposed to edit
	 * @param array $row The record data array where the value(s) for the field can be found
	 * @param array $conf An array with additional configuration options.
	 * @param string $checkForConfField For which field in the foreign_table the possible records should be fetched
	 * @return mixed Array of possible record items; FALSE if type is "group/db", then everything could be "possible
	 */
	protected function getPossibleRecords($table, $field, $row, $conf, $checkForConfField = 'foreign_selector') {
		$backendUser = $this->getBackendUserAuthentication();
		// ctrl configuration from TCA:
		$tcaTableCtrl = $GLOBALS['TCA'][$table]['ctrl'];
		// Field configuration from TCA:
		$foreign_check = $conf[$checkForConfField];
		$foreignConfig = $this->getPossibleRecordsSelectorConfig($conf, $foreign_check);
		$PA = $foreignConfig['PA'];
		$config = $PA['fieldConf']['config'];
		if ($foreignConfig['type'] == 'select') {
			// Getting the selector box items from the system
			$selItems = FormEngineUtility::addSelectOptionsToItemArray(
				FormEngineUtility::initItemArray($PA['fieldConf']),
				$PA['fieldConf'],
				FormEngineUtility::getTSconfigForTableRow($table, $row),
				$field
			);

			// Possibly filter some items:
			$selItems = ArrayUtility::keepItemsInArray(
				$selItems,
				$PA['fieldTSConfig']['keepItems'],
				function ($value) {
					return $value[1];
				}
			);

			// Possibly add some items:
			$selItems = FormEngineUtility::addItems($selItems, $PA['fieldTSConfig']['addItems.']);
			if (isset($config['itemsProcFunc']) && $config['itemsProcFunc']) {
				$dataPreprocessor = GeneralUtility::makeInstance(DataPreprocessor::class);
				$selItems = $dataPreprocessor->procItems($selItems, $PA['fieldTSConfig']['itemsProcFunc.'], $config, $table, $row, $field);
			}
			// Possibly remove some items:
			$removeItems = GeneralUtility::trimExplode(',', $PA['fieldTSConfig']['removeItems'], TRUE);
			foreach ($selItems as $tk => $p) {
				// Checking languages and authMode:
				$languageDeny = $tcaTableCtrl['languageField'] && (string)$tcaTableCtrl['languageField'] === $field && !$backendUser->checkLanguageAccess($p[1]);
				$authModeDeny = $config['type'] == 'select' && $config['authMode'] && !$backendUser->checkAuthMode($table, $field, $p[1], $config['authMode']);
				if (in_array($p[1], $removeItems) || $languageDeny || $authModeDeny) {
					unset($selItems[$tk]);
				} else {
					if (isset($PA['fieldTSConfig']['altLabels.'][$p[1]])) {
						$selItems[$tk][0] = htmlspecialchars($this->getLanguageService()->sL($PA['fieldTSConfig']['altLabels.'][$p[1]]));
					}
					if (isset($PA['fieldTSConfig']['altIcons.'][$p[1]])) {
						$selItems[$tk][2] = $PA['fieldTSConfig']['altIcons.'][$p[1]];
					}
				}
				// Removing doktypes with no access:
				if (($table === 'pages' || $table === 'pages_language_overlay') && $field === 'doktype') {
					if (!($backendUser->isAdmin() || GeneralUtility::inList($backendUser->groupData['pagetypes_select'], $p[1]))) {
						unset($selItems[$tk]);
					}
				}
			}
		} else {
			$selItems = FALSE;
		}
		return $selItems;
	}

	/**
	 * Makes a flat array from the $possibleRecords array.
	 * The key of the flat array is the value of the record,
	 * the value of the flat array is the label of the record.
	 *
	 * @param array $possibleRecords The possibleRecords array (for select fields)
	 * @return mixed A flat array with key=uid, value=label; if $possibleRecords isn't an array, FALSE is returned.
	 */
	protected function getPossibleRecordsFlat($possibleRecords) {
		$flat = FALSE;
		if (is_array($possibleRecords)) {
			$flat = array();
			foreach ($possibleRecords as $record) {
				$flat[$record[1]] = $record[0];
			}
		}
		return $flat;
	}

	/**
	 * Creates the HTML code of a general link to be used on a level of inline children.
	 * The possible keys for the parameter $type are 'newRecord', 'localize' and 'synchronize'.
	 *
	 * @param string $type The link type, values are 'newRecord', 'localize' and 'synchronize'.
	 * @param string $objectPrefix The "path" to the child record to create (e.g. 'data-parentPageId-partenTable-parentUid-parentField-childTable]')
	 * @param array $conf TCA configuration of the parent(!) field
	 * @return string The HTML code of the new link, wrapped in a div
	 */
	protected function getLevelInteractionLink($type, $objectPrefix, $conf = array()) {
		$languageService = $this->getLanguageService();
		$nameObject = $this->inlineNames['object'];
		$attributes = array();
		switch ($type) {
			case 'newRecord':
				$title = $languageService->sL('LLL:EXT:lang/locallang_core.xlf:cm.createnew', TRUE);
				$icon = 'actions-document-new';
				$className = 'typo3-newRecordLink';
				$attributes['class'] = 'btn btn-default inlineNewButton ' . $this->inlineData['config'][$nameObject]['md5'];
				$attributes['onclick'] = 'return inline.createNewRecord(\'' . $objectPrefix . '\')';
				if (!empty($conf['inline']['inlineNewButtonStyle'])) {
					$attributes['style'] = $conf['inline']['inlineNewButtonStyle'];
				}
				if (!empty($conf['appearance']['newRecordLinkAddTitle'])) {
					$title = sprintf(
						$languageService->sL('LLL:EXT:lang/locallang_core.xlf:cm.createnew.link', TRUE),
						$languageService->sL($GLOBALS['TCA'][$conf['foreign_table']]['ctrl']['title'], TRUE)
					);
				} elseif (isset($conf['appearance']['newRecordLinkTitle']) && $conf['appearance']['newRecordLinkTitle'] !== '') {
					$title = $languageService->sL($conf['appearance']['newRecordLinkTitle'], TRUE);
				}
				break;
			case 'localize':
				$title = $languageService->sL('LLL:EXT:lang/locallang_misc.xlf:localizeAllRecords', 1);
				$icon = 'actions-document-localize';
				$className = 'typo3-localizationLink';
				$attributes['class'] = 'btn btn-default';
				$attributes['onclick'] = 'return inline.synchronizeLocalizeRecords(\'' . $objectPrefix . '\', \'localize\')';
				break;
			case 'synchronize':
				$title = $languageService->sL('LLL:EXT:lang/locallang_misc.xlf:synchronizeWithOriginalLanguage', TRUE);
				$icon = 'actions-document-synchronize';
				$className = 'typo3-synchronizationLink';
				$attributes['class'] = 'btn btn-default inlineNewButton ' . $this->inlineData['config'][$nameObject]['md5'];
				$attributes['onclick'] = 'return inline.synchronizeLocalizeRecords(\'' . $objectPrefix . '\', \'synchronize\')';
				break;
			default:
				$title = '';
				$icon = '';
				$className = '';
		}
		// Create the link:
		$icon = $icon ? IconUtility::getSpriteIcon($icon, array('title' => htmlspecialchars($title))) : '';
		$link = $this->wrapWithAnchor($icon . $title, '#', $attributes);
		return '<div' . ($className ? ' class="' . $className . '"' : '') . '>' . $link . '</div>';
	}

	/**
	 * Wraps a text with an anchor and returns the HTML representation.
	 *
	 * @param string $text The text to be wrapped by an anchor
	 * @param string $link  The link to be used in the anchor
	 * @param array $attributes Array of attributes to be used in the anchor
	 * @return string The wrapped text as HTML representation
	 */
	protected function wrapWithAnchor($text, $link, $attributes = array()) {
		$link = trim($link);
		$result = '<a href="' . ($link ?: '#') . '"';
		foreach ($attributes as $key => $value) {
			$result .= ' ' . $key . '="' . htmlspecialchars(trim($value)) . '"';
		}
		$result .= '>' . $text . '</a>';
		return $result;
	}

	/**
	 * Get a selector as used for the select type, to select from all available
	 * records and to create a relation to the embedding record (e.g. like MM).
	 *
	 * @param array $selItems Array of all possible records
	 * @param array $conf TCA configuration of the parent(!) field
	 * @param array $uniqueIds The uids that have already been used and should be unique
	 * @return string A HTML <select> box with all possible records
	 */
	protected function renderPossibleRecordsSelector($selItems, $conf, $uniqueIds = array()) {
		$foreign_selector = $conf['foreign_selector'];
		$selConfig = $this->getPossibleRecordsSelectorConfig($conf, $foreign_selector);
		$item  = '';
		if ($selConfig['type'] == 'select') {
			$item = $this->renderPossibleRecordsSelectorTypeSelect($selItems, $conf, $selConfig['PA'], $uniqueIds);
		} elseif ($selConfig['type'] == 'groupdb') {
			$item = $this->renderPossibleRecordsSelectorTypeGroupDB($conf, $selConfig['PA']);
		}
		return $item;
	}

	/**
	 * Generate a link that opens an element browser in a new window.
	 * For group/db there is no way to use a "selector" like a <select>|</select>-box.
	 *
	 * @param array $conf TCA configuration of the parent(!) field
	 * @param array $PA An array with additional configuration options
	 * @return string A HTML link that opens an element browser in a new window
	 */
	protected function renderPossibleRecordsSelectorTypeGroupDB($conf, &$PA) {
		$backendUser = $this->getBackendUserAuthentication();

		$config = $PA['fieldConf']['config'];
		ArrayUtility::mergeRecursiveWithOverrule($config, $conf);
		$foreign_table = $config['foreign_table'];
		$allowed = $config['allowed'];
		$objectPrefix = $this->inlineNames['object'] . self::Structure_Separator . $foreign_table;
		$mode = 'db';
		$showUpload = FALSE;
		if (!empty($config['appearance']['createNewRelationLinkTitle'])) {
			$createNewRelationText = $this->getLanguageService()->sL($config['appearance']['createNewRelationLinkTitle'], TRUE);
		} else {
			$createNewRelationText = $this->getLanguageService()->sL('LLL:EXT:lang/locallang_core.xlf:cm.createNewRelation', TRUE);
		}
		if (is_array($config['appearance'])) {
			if (isset($config['appearance']['elementBrowserType'])) {
				$mode = $config['appearance']['elementBrowserType'];
			}
			if ($mode === 'file') {
				$showUpload = TRUE;
			}
			if (isset($config['appearance']['fileUploadAllowed'])) {
				$showUpload = (bool)$config['appearance']['fileUploadAllowed'];
			}
			if (isset($config['appearance']['elementBrowserAllowed'])) {
				$allowed = $config['appearance']['elementBrowserAllowed'];
			}
		}
		$browserParams = '|||' . $allowed . '|' . $objectPrefix . '|inline.checkUniqueElement||inline.importElement';
		$onClick = 'setFormValueOpenBrowser(\'' . $mode . '\', \'' . $browserParams . '\'); return false;';

		$item = '
			<a href="#" class="btn btn-default" onclick="' . htmlspecialchars($onClick) . '">
				' . IconUtility::getSpriteIcon('actions-insert-record', array('title' => $createNewRelationText)) . '
				' . $createNewRelationText . '
			</a>';

		$isDirectFileUploadEnabled = (bool)$this->getBackendUserAuthentication()->uc['edit_docModuleUpload'];
		if ($showUpload && $isDirectFileUploadEnabled) {
			$folder = $backendUser->getDefaultUploadFolder();
			if (
				$folder instanceof \TYPO3\CMS\Core\Resource\Folder
				&& $folder->checkActionPermission('add')
			) {
				$maxFileSize = GeneralUtility::getMaxUploadFileSize() * 1024;
				$item .= ' <a href="#" class="btn btn-default t3-drag-uploader"
					style="display:none"
					data-dropzone-target="#' . htmlspecialchars($this->inlineNames['object']) . '"
					data-insert-dropzone-before="1"
					data-file-irre-object="' . htmlspecialchars($objectPrefix) . '"
					data-file-allowed="' . htmlspecialchars($allowed) . '"
					data-target-folder="' . htmlspecialchars($folder->getCombinedIdentifier()) . '"
					data-max-file-size="' . htmlspecialchars($maxFileSize) . '"
					><span class="t3-icon t3-icon-actions t3-icon-actions-edit t3-icon-edit-upload">&nbsp;</span>';
				$item .= $this->getLanguageService()->sL('LLL:EXT:lang/locallang_core.xlf:file_upload.select-and-submit', TRUE);
				$item .= '</a>';
			}
		}

		$item = '<div class="form-control-wrap">' . $item . '</div>';
		$allowedList = '';
		$allowedArray = GeneralUtility::trimExplode(',', $allowed, TRUE);
		$allowedLabel = $this->getLanguageService()->sL('LLL:EXT:lang/locallang_core.xlf:cm.allowedFileExtensions', TRUE);
		foreach ($allowedArray as $allowedItem) {
			$allowedList .= '<span class="label label-success">' . strtoupper($allowedItem) . '</span> ';
		}
		if (!empty($allowedList)) {
			$item .= '<div class="help-block">' . $allowedLabel . '<br>' . $allowedList . '</div>';
		}
		$item = '<div class="form-group">' . $item . '</div>';
		return $item;
	}

	/**
	 * Get a selector as used for the select type, to select from all available
	 * records and to create a relation to the embedding record (e.g. like MM).
	 *
	 * @param array $selItems Array of all possible records
	 * @param array $conf TCA configuration of the parent(!) field
	 * @param array $PA An array with additional configuration options
	 * @param array $uniqueIds The uids that have already been used and should be unique
	 * @return string A HTML <select> box with all possible records
	 */
	protected function renderPossibleRecordsSelectorTypeSelect($selItems, $conf, &$PA, $uniqueIds = array()) {
		$foreign_table = $conf['foreign_table'];
		$foreign_selector = $conf['foreign_selector'];
		$PA = array();
		$PA['fieldConf'] = $GLOBALS['TCA'][$foreign_table]['columns'][$foreign_selector];
		$PA['fieldTSConfig'] = FormEngineUtility::getTSconfigForTableRow($foreign_table, array(), $foreign_selector);
		$config = $PA['fieldConf']['config'];
		$item = '';
		// @todo $disabled is not present - should be read from config?
		$disabled = FALSE;
		if (!$disabled) {
			// Create option tags:
			$opt = array();
			$styleAttrValue = '';
			foreach ($selItems as $p) {
				if ($config['iconsInOptionTags']) {
					$styleAttrValue = FormEngineUtility::optionTagStyle($p[2]);
				}
				if (!in_array($p[1], $uniqueIds)) {
					$opt[] = '<option value="' . htmlspecialchars($p[1]) . '"' . ' style="' . (in_array($p[1], $uniqueIds) ? '' : '') . ($styleAttrValue ? ' style="' . htmlspecialchars($styleAttrValue) : '') . '">' . htmlspecialchars($p[0]) . '</option>';
				}
			}
			// Put together the selector box:
			$selector_itemListStyle = isset($config['itemListStyle']) ? ' style="' . htmlspecialchars($config['itemListStyle']) . '"' : '';
			$size = (int)$conf['size'];
			$size = $conf['autoSizeMax'] ? MathUtility::forceIntegerInRange(count($selItems) + 1, MathUtility::forceIntegerInRange($size, 1), $conf['autoSizeMax']) : $size;
			$onChange = 'return inline.importNewRecord(\'' . $this->inlineNames['object'] . self::Structure_Separator . $conf['foreign_table'] . '\')';
			$item = '
				<select id="' . $this->inlineNames['object'] . self::Structure_Separator . $conf['foreign_table'] . '_selector" class="form-control"' . ($size ? ' size="' . $size . '"' : '') . ' onchange="' . htmlspecialchars($onChange) . '"' . $PA['onFocus'] . $selector_itemListStyle . ($conf['foreign_unique'] ? ' isunique="isunique"' : '') . '>
					' . implode('', $opt) . '
				</select>';

			if ($size <= 1) {
				// Add a "Create new relation" link for adding new relations
				// This is necessary, if the size of the selector is "1" or if
				// there is only one record item in the select-box, that is selected by default
				// The selector-box creates a new relation on using a onChange event (see some line above)
				if (!empty($conf['appearance']['createNewRelationLinkTitle'])) {
					$createNewRelationText = $this->getLanguageService()->sL($conf['appearance']['createNewRelationLinkTitle'], TRUE);
				} else {
					$createNewRelationText = $this->getLanguageService()->sL('LLL:EXT:lang/locallang_core.xlf:cm.createNewRelation', TRUE);
				}
				$item .= '
				<span class="input-group-btn">
					<a href="#" class="btn btn-default" onclick="' . htmlspecialchars($onChange) . '">
						' . IconUtility::getSpriteIcon('actions-document-new', array('title' => $createNewRelationText)) . $createNewRelationText . '
					</a>
				</span>';
			} else {
				$item .= '
				<span class="input-group-btn btn"></span>';
			}

			// Wrap the selector and add a spacer to the bottom
			$nameObject = $this->inlineNames['object'];
			$item = '<div class="input-group form-group ' . $this->inlineData['config'][$nameObject]['md5'] . '">' . $item . '</div>';
		}
		return $item;
	}

	/**
	 * Normalize a relation "uid" published by transferData, like "1|Company%201"
	 *
	 * @param string $string A transferData reference string, containing the uid
	 * @return string The normalized uid
	 */
	protected function normalizeUid($string) {
		$parts = explode('|', $string);
		return $parts[0];
	}

	/**
	 * Checks the page access rights (Code for access check mostly taken from alt_doc.php)
	 * as well as the table access rights of the user.
	 *
	 * @param string $cmd The command that should be performed ('new' or 'edit')
	 * @param string $table The table to check access for
	 * @param string $theUid The record uid of the table
	 * @return bool Returns TRUE is the user has access, or FALSE if not
	 */
	protected function checkAccess($cmd, $table, $theUid) {
		$backendUser = $this->getBackendUserAuthentication();
		// Checking if the user has permissions? (Only working as a precaution, because the final permission check is always down in TCE. But it's good to notify the user on beforehand...)
		// First, resetting flags.
		$hasAccess = 0;
		// Admin users always have access:
		if ($backendUser->isAdmin()) {
			return TRUE;
		}
		// If the command is to create a NEW record...:
		if ($cmd == 'new') {
			// If the pid is numerical, check if it's possible to write to this page:
			if (MathUtility::canBeInterpretedAsInteger(static::$inlineFirstPid)) {
				$calcPRec = BackendUtility::getRecord('pages', static::$inlineFirstPid);
				if (!is_array($calcPRec)) {
					return FALSE;
				}
				// Permissions for the parent page
				$CALC_PERMS = $backendUser->calcPerms($calcPRec);
				// If pages:
				if ($table == 'pages') {
					// Are we allowed to create new subpages?
					$hasAccess = $CALC_PERMS & Permission::PAGE_NEW ? 1 : 0;
				} else {
					// Are we allowed to edit content on this page?
					$hasAccess = $CALC_PERMS & Permission::CONTENT_EDIT ? 1 : 0;
				}
			} else {
				$hasAccess = 1;
			}
		} else {
			// Edit:
			$calcPRec = BackendUtility::getRecord($table, $theUid);
			BackendUtility::fixVersioningPid($table, $calcPRec);
			if (is_array($calcPRec)) {
				// If pages:
				if ($table == 'pages') {
					$CALC_PERMS = $backendUser->calcPerms($calcPRec);
					$hasAccess = $CALC_PERMS & Permission::PAGE_EDIT ? 1 : 0;
				} else {
					// Fetching pid-record first.
					$CALC_PERMS = $backendUser->calcPerms(BackendUtility::getRecord('pages', $calcPRec['pid']));
					$hasAccess = $CALC_PERMS & Permission::CONTENT_EDIT ? 1 : 0;
				}
				// Check internals regarding access:
				if ($hasAccess) {
					$hasAccess = $backendUser->recordEditAccessInternals($table, $calcPRec);
				}
			}
		}
		if (!$backendUser->check('tables_modify', $table)) {
			$hasAccess = 0;
		}
		if (!$hasAccess) {
			$deniedAccessReason = $backendUser->errorMsg;
			if ($deniedAccessReason) {
				debug($deniedAccessReason);
			}
		}
		return $hasAccess ? TRUE : FALSE;
	}

	/**
	 * Checks if a uid of a child table is in the inline view settings.
	 *
	 * @param string $table Name of the child table
	 * @param int $uid uid of the the child record
	 * @return bool TRUE=expand, FALSE=collapse
	 */
	protected function getExpandedCollapsedState($table, $uid) {
		if (isset(static::$inlineView[$table]) && is_array(static::$inlineView[$table])) {
			if (in_array($uid, static::$inlineView[$table]) !== FALSE) {
				return TRUE;
			}
		}
		return FALSE;
	}

	/**
	 * @return BackendUserAuthentication
	 */
	protected function getBackendUserAuthentication() {
		return $GLOBALS['BE_USER'];
	}

	/**
	 * @return LanguageService
	 */
	protected function getLanguageService() {
		return $GLOBALS['LANG'];
	}

}
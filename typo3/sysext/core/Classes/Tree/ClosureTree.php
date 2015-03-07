<?php
namespace TYPO3\CMS\Core\Tree;

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

/**
 * Closure tree
 */
abstract class ClosureTree {
	/**
	 * @var string
	 */
	protected $table;

	/**
	 * @var string
	 */
	protected $closureTable;

	/**
	 * @var int
	 */
	protected $treeRootIdentifier;

	/**
	 * @var array
	 */
	protected $selectFields = array();

	/**
	 * @var array
	 */
	protected $joinTables = array();

	/**
	 * @var string
	 */
	protected $additionalWhere = '';

	/**
	 * @var int
	 */
	protected $maxDepth = 100;

	/**
	 * @var string
	 */
	protected $query;

	/**
	 * @param int $treeRootIdentifier currently this is the UID
	 * @param array $selectFields e.g. array('tablename' => array('field')), t2 = $table, tc1 = $closureTable, or tablename
	 * @param int $depth max level
	 * @param string $additionalWhere
	 * @param array $joinTables e.g. array('tablename' => 'ON t1.uid = tablename.foo')
	 * @return array
	 */
	public function fetchTreeByRoot($treeRootIdentifier, array $selectFields = array(), $depth = 100, $additionalWhere = '', array $joinTables = array()) {
		$this->treeRootIdentifier = (int)$treeRootIdentifier;
		$this->joinTables = $joinTables;
		$this->additionalWhere = $additionalWhere;
		$this->maxDepth = $depth;

		// List of default fields to be fetched
		$defaultSelectFields = array(
			't2' => array( 'uid', 'pid')
		);

		// Merge unique with additional fields
		foreach ($selectFields as $table => $fields) {
			$this->selectFields[$table] = (is_array($defaultSelectFields[$table]))
				? array_unique(array_merge($defaultSelectFields[$table], $fields))
				: array_unique($fields);
		}

		// build tree query
		$this->buildTreeQuery();

		return $this->getResultFromDatabase();
	}

	/**
	 * @param int $treeIdentifier currently this is the UID
	 * @param array $selectFields e.g. array('tablename' => array('field')), t2 = $table, tc1 = $closureTable, or tablename
	 * @param string $additionalWhere
	 * @param array $joinTables e.g. array('tablename' => 'ON t1.uid = tablename.foo')
	 * @return array
	 */
	public function getRootline($treeIdentifier, array $selectFields = array(), $additionalWhere = '', array $joinTables = array()) {
		$this->treeRootIdentifier = (int)$treeIdentifier;
		$this->joinTables = $joinTables;
		$this->additionalWhere = $additionalWhere;

		// List of default fields to be fetched
		$defaultSelectFields = array(
			't2' => array( 'uid', 'pid')
		);

		// Merge unique with additional fields
		foreach ($selectFields as $table => $fields) {
			$this->selectFields[$table] = (is_array($defaultSelectFields[$table]))
				? array_unique(array_merge($defaultSelectFields[$table], $fields))
				: array_unique($fields);
		}

		// build rootline query
		$this->buildRootlineQuery();

		return $this->getResultFromDatabase();
	}

	/**
	 * build database query
	 */
	protected function buildTreeQuery() {
		$selectFields = array();
		foreach ($this->selectFields as $table => $fields) {
			foreach ($fields as $field) {
				$selectFields[] = $this->getDatabaseConnection()->quoteStr($table . '.' . $field, $table);
			}
		}

		$additionalJoins = array();
		foreach ($this->joinTables as $table => $condition) {
			$additionalJoins[] = ' LEFT OUTER JOIN ' . $table . ' ' . $condition;
		}

		$this->query = 'SELECT ' .
			implode(', ', $selectFields) .
			// "rootline" used for sorting, slices arranged by group by below
			', GROUP_CONCAT( LPAD( o.sorting, 5,  \'0\' ) ORDER BY breadcrumb.depth DESC ) AS breadcrumbs' .
			// entry page where uid=
			' FROM ' . $this->table . ' AS t1' .
			// add all sub pages as single rows
			' JOIN ' . $this->closureTable . ' AS tc1 ON ( tc1.ancestor = t1.uid AND tc1.depth <= ' . (int)$this->maxDepth . ')' .
			// join in data fields from pages
			' JOIN ' . $this->table . ' AS t2 ON ( tc1.descendant = t2.uid )' .
			// additional joins
			implode(' ', $additionalJoins) .
			// add rootline rows of every single page
			' JOIN ' . $this->closureTable . ' AS breadcrumb ON (tc1.descendant = breadcrumb.descendant)' .
			// join in sorting field for all rows
			' JOIN ' . $this->table . ' AS o ON breadcrumb.ancestor = o.uid' .
			// build where condition
			' WHERE t1.uid = ' . $this->treeRootIdentifier .
			// add addtional where
			$this->additionalWhere .
			// slices for group_concat above - each slice is a page with its rootline
			' GROUP BY tc1.descendant' .
			// use rootline-sorting string as order
			' ORDER BY breadcrumbs';
	}

	/**
	 * build rootline query
	 */
	protected function buildRootlineQuery() {
		$selectFields = array();
		foreach ($this->selectFields as $table => $fields) {
			foreach ($fields as $field) {
				$selectFields[] = $this->getDatabaseConnection()->quoteStr($table . '.' . $field, $table);
			}
		}

		$additionalJoins = array();
		foreach ($this->joinTables as $table => $condition) {
			$additionalJoins[] = ' LEFT OUTER JOIN ' . $table . ' ' . $condition;
		}

		$this->query = 'SELECT ' .
			implode(', ', $selectFields) .
			' FROM ' . $this->table . ' AS t1' .
			// add all sub pages as single rows
			' JOIN ' . $this->closureTable . ' AS tc1 ON ( tc1.ancestor = t1.uid AND tc1.depth <= ' . (int)$this->maxDepth . ')' .
			// join in data fields from pages
			' JOIN ' . $this->table . ' AS t2 ON ( tc1.ancestor = t2.uid )' .
			// additional joins
			implode(' ', $additionalJoins) .
			// build where condition
			' WHERE tc1.descendant = ' . $this->treeRootIdentifier .
			// add addtional where
			$this->additionalWhere;
	}

	/**
	 * query database with build sql query
	 * @return array
	 */
	protected function getResultFromDatabase() {
		$database = $this->getDatabaseConnection();

		$result = array();
		$res = $database->sql_query($this->query);

		while ($row = $database->sql_fetch_assoc($res)) {
			$result[] = $row;
		}
		return $result;
	}

	/**
	 * @return \TYPO3\CMS\Core\Database\DatabaseConnection
	 */
	protected function getDatabaseConnection() {
		return $GLOBALS['TYPO3_DB'];
	}
}

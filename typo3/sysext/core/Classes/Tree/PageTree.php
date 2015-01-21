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
 * Page tree
 */
class PageTree {

//	protected $

	public function fetchTreeByRoot($rootId, array $additionalPagesFields = array()) {
		// List of pages fields to be fetched
		$defaultPagesFields = array(
			'uid',
			'pid',
		);
		// Merge unique with additional fields
		$pagesFields = array_unique(array_merge($defaultPagesFields, $additionalPagesFields));
		// p2.field1, p2.field2, ...
		array_walk(
			$pagesFields,
			function(&$value, $key) {
				$value = 'p2.' . $value . ', ';
			}
		);

		$database = $this->getDatabaseConnection();
		$res = $database->sql_query(
			'SELECT ' .
				implode('', $pagesFields) . // casual fields from pages: p2.uid, p2.pid, ...
				' GROUP_CONCAT( LPAD( o.sorting, 5,  \'0\' ) ORDER BY breadcrumb.depth DESC ) AS breadcrumbs' . // "rootline" used for sorting, slices arranged by group by below
			' FROM pages AS p1' . // entry page where uid=
			' JOIN pages_closure AS pc1 ON ( pc1.ancestor = p1.uid )' . // add all sub pages as single rows
			' JOIN pages AS p2 ON ( pc1.descendant = p2.uid )' . // join in data fields from pages
			' JOIN pages_closure AS breadcrumb ON (pc1.descendant = breadcrumb.descendant)' . // add rootline rows of every single page
			' JOIN pages AS o ON breadcrumb.ancestor = o.uid' . // join in sorting field for all rows
			' WHERE p1.uid = 12' .
			' GROUP BY pc1.descendant' . // slices for group_concat above - each slice is a page with its rootline
			' ORDER BY breadcrumbs' // use rootline-sorting string as order
		);
		$result = array();
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

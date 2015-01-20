<?php
namespace TYPO3\CMS\Install\Updates;

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
 * Fill pages_closure with data from uid->pid pages information
 */
class PopulatePagesClosureTable extends AbstractDatabaseSchemaUpdate {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();
		$this->title = 'Populate pages closure';
	}

	/**
	 * Checks if an update is needed
	 *
	 * @param string &$description The description for the update
	 * @return bool TRUE if an update is needed, FALSE otherwise
	 */
	public function checkForUpdate(&$description) {
		$description = 'foo';

		// @TODO
		return TRUE;
	}

	/**
	 * Performs the database update.
	 *
	 * @param array &$dbQueries Queries done in this update
	 * @param mixed &$customMessages Custom messages
	 * @return bool TRUE on success, FALSE on error
	 */
	public function performUpdate(array &$dbQueries, &$customMessages) {

		$this->fillClosureTableRecursive();
		return TRUE;
	}

	public function fillClosureTableRecursive($parentId = 0, array $rootline = array()) {
		$database = $this->getDatabaseConnection();
		$childsOfThisParent = $database->exec_SELECTgetRows('uid,pid', 'pages', 'pid=' . (int)$parentId);
		foreach ($childsOfThisParent as $child) {
			$rootlineOfThisChild = $rootline;
			$rootlineOfThisChild[] = $child['uid'];
			$toInsertRows = array();
			$maxDepth = count($rootlineOfThisChild) - 1;
			foreach($rootlineOfThisChild as $depth => $node) {
				$toInsertRows[] = array(
					'ancestor' => $node,
					'descendant' => $child['uid'],
					'depth' => $maxDepth - $depth,
				);
			}
			$database->exec_INSERTmultipleRows(
				'pages_closure',
				array('ancestor', 'descendant', 'depth'),
				$toInsertRows,
				TRUE
			);
			$this->fillClosureTableRecursive($child['uid'], $rootlineOfThisChild);
		}
	}
}

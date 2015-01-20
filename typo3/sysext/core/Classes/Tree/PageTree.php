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

	public function fetchTreeByRoot($rootId, $expand) {
		$database = $this->getDatabaseConnection();
		$res = $database->sql_query(
			'SELECT pages.uid, pages.pid, closure.ancestor FROM pages INNER JOIN pages_closure AS closure ON pages.uid = closure.descendant WHERE closure.ancestor = 14'
		);
		$result = array();
		while ($row = $database->sql_fetch_row($res)) {
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

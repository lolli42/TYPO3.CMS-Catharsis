<?php
namespace TYPO3\CMS\Install\FolderStructure;

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

/**
 * Structure facade, a facade class in front of root node.
 * This is the main API interface to the node structure and should
 * be the only class used from outside.
 *
 * @api
 */
class StructureFacade implements StructureFacadeInterface {

	/**
	 * @var RootNodeInterface The structure to work on
	 */
	protected $structure;

	/**
	 * Constructor sets structure to work on
	 *
	 * @param RootNodeInterface $structure
	 */
	public function __construct(RootNodeInterface $structure) {
		$this->structure = $structure;
	}

	/**
	 * Get status of node tree
	 *
	 * @return array<\TYPO3\CMS\Install\Status\StatusInterface>
	 */
	public function getStatus() {
		return $this->structure->getStatus();
	}

	/**
	 * Get status objects of not fixable nodes
	 *
	 * @return array<\TYPO3\CMS\Install\Status\ErrorStatus>
	 */
	public function getErrorStatus() {
		$orderedStatus = $this->orderStatusBySeverity($this->structure->getStatus());
		return $orderedStatus['error'];
	}

	/**
	 * Get status objects of fixable nodes
	 *
	 * @return array<\TYPO3\CMS\Install\Status\WarningStatus>
	 */
	public function getWarningStatus() {
		$orderedStatus = $this->orderStatusBySeverity($this->structure->getStatus());
		return $orderedStatus['warning'];
	}

	/**
	 * Get status objects of good nodes
	 *
	 * @return array<\TYPO3\CMS\Install\Status\OkStatus>
	 */
	public function getOkStatus() {
		$orderedStatus = $this->orderStatusBySeverity($this->structure->getStatus());
		return $orderedStatus['ok'];
	}

	/**
	 * Fix structure
	 *
	 * @return array<\TYPO3\CMS\Install\Status\StatusInterface>
	 */
	public function fix() {
		return $this->structure->fix();
	}

	/**
	 * Order status objects by severity
	 *
	 * @param array<\TYPO3\CMS\Install\Status\AbstractStatus> $statusObjects
	 * @return array
	 * @throws \TYPO3\CMS\Install\Exception
	 */
	protected function orderStatusBySeverity(array $statusObjects = array()) {
		$orderedStatus = array(
			'error' => array(),
			'warning' => array(),
			'ok' => array(),
			'information' => array(),
			'notice' => array(),
		);
		/** @var $statusObject \TYPO3\CMS\Install\Status\AbstractStatus */
		foreach ($statusObjects as $statusObject) {
			$severityIdentifier = $statusObject->getSeverity();
			if (empty($severityIdentifier) || !is_array($orderedStatus[$severityIdentifier])) {
				throw new \TYPO3\CMS\Install\Exception('Unknown status severity type', 1366539524);
			}
			$orderedStatus[$severityIdentifier][] = $statusObject;
		}
		return $orderedStatus;
	}
}
?>
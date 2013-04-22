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
 * Abstract node implements common methods
 */
abstract class AbstractNode {

	/**
	 * @var string Name
	 */
	protected $name = '';

	/**
	 * @var NULL|string Target permissions for unix, eg. 2770
	 */
	protected $targetPermission = NULL;

	/**
	 * @var NULL|NodeInterface Parent object of this structure node
	 */
	protected $parent = NULL;

	/**
	 * @var array Directories and root may have children, files and link always empty array
	 */
	protected $children = array();

	/**
	 * Get name
	 *
	 * @return string Name
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * Get target permission
	 *
	 * @return string Permission, eg. 2770
	 */
	public function getTargetPermission() {
		return $this->targetPermission;
	}

	/**
	 * Get children
	 *
	 * @return array
	 */
	public function getChildren() {
		return $this->children;
	}

	/**
	 * Get parent
	 *
	 * @return null|NodeInterface
	 */
	public function getParent() {
		return $this->parent;
	}

	/**
	 * Get absolute path of node
	 *
	 * @return string
	 */
	public function getAbsolutePath() {
		return $this->parent->getAbsolutePath() . '/' . $this->name;
	}

	/**
	 * Current node is writable if parent is writable
	 *
	 * @return boolean TRUE if parent is writable
	 */
	public function isWritable() {
		return $this->parent->isWritable();
	}

	/**
	 * Returns TRUE if OS is windows
	 *
	 * @return boolean TRUE on windows
	 */
	protected function isWindowsOs() {
		if (TYPO3_OS === 'WIN') {
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * Cut off PATH_site from given path
	 *
	 * @param string $path Given path
	 * @return string Relative path, but beginning with /
	 * @throws Exception\InvalidArgumentException
	 */
	protected function getRelativePathBelowSiteRoot($path = NULL) {
		if (is_null($path)) {
			$path = $this->getAbsolutePath();
		}
		$pathSiteWithoutTrailingSlash = substr(PATH_site, 0, -1);
		if (strpos($path, $pathSiteWithoutTrailingSlash, 0) !== 0) {
			throw new \TYPO3\CMS\Install\FolderStructure\Exception\InvalidArgumentException(
				'PATH_site is not first part of given path',
				1366398198
			);
		}
		$relativePath = substr($path, strlen($pathSiteWithoutTrailingSlash), strlen($path));
		// Add a forward slash again, so we don't end up with an empty string
		if (strlen($relativePath) === 0) {
			$relativePath = '/';
		}
		return $relativePath;
	}
}
?>
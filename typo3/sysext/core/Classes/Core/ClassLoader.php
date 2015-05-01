<?php
namespace TYPO3\CMS\Core\Core;

/*
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

use TYPO3\CMS\Core\Cache;
use TYPO3\CMS\Core\Locking\Exception\LockCreateException;
use TYPO3\CMS\Core\Locking\LockFactory;
use TYPO3\CMS\Core\Locking\LockingStrategyInterface;
use TYPO3\CMS\Core\Package\PackageInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class Loader implementation which loads .php files found in the classes
 * directory of an object.
 */
class ClassLoader {

	const VALID_CLASSNAME_PATTERN = '/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9\\\\_\x7f-\xff]*$/';

	/**
	 * Loads php files containing classes or interfaces found in the classes directory of
	 * a package and specifically registered classes.
	 *
	 * Caution: This function may be called "recursively" by the spl_autoloader if a class depends on another classes.
	 *
	 * @param string $className Name of the class/interface to load
	 * @return bool
	 */
	public function loadClass($className) {
		if ($className[0] === '\\') {
			$className = substr($className, 1);
		}

		if (!$this->isValidClassName($className)) {
			return FALSE;
		}

		$delimiter = '_';
		// To handle namespaced class names, split the class name at the
		// namespace delimiters.
		if (strpos($className, '\\') !== FALSE) {
			$delimiter = '\\';
		}

		$classNameParts = explode($delimiter, $className, 4);

		// We only handle classes that follow the convention Vendor\Product\Classname or is longer
		// so we won't deal with class names that only have one or two parts
		if (count($classNameParts) <= 2) {
			return FALSE;
		}

		if (isset($classNameParts[0]) && isset($classNameParts[1]) && $classNameParts[0] === 'TYPO3' && $classNameParts[1] === 'CMS') {
			$extensionKey = GeneralUtility::camelCaseToLowerCaseUnderscored($classNameParts[2]);
			$classNameWithoutVendorAndProduct = $classNameParts[3];
		} else {
			$extensionKey = GeneralUtility::camelCaseToLowerCaseUnderscored($classNameParts[1]);
			$classNameWithoutVendorAndProduct = $classNameParts[2];

			if (isset($classNameParts[3])) {
				$classNameWithoutVendorAndProduct .= $delimiter . $classNameParts[3];
			}
		}

		$loadedExtensions = $GLOBALS['TYPO3_LOADED_EXT'];
		if ($extensionKey && isset($loadedExtensions[$extensionKey]['siteRelPath'])) {
			$classesPath = $loadedExtensions[$extensionKey]['siteRelPath'] . '/Classes';
			$classFilePath = $classesPath . strtr($classNameWithoutVendorAndProduct, $delimiter, '/') . '.php';
			if (@file_exists($classFilePath)) {
				require_once $classFilePath;
			}
		}

		return FALSE;
	}

}

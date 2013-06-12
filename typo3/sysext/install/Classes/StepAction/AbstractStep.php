<?php
namespace TYPO3\CMS\Install\StepAction;

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
 * Methods used in multiple steps
 */
class AbstractStep {

	/**
	 * Return TRUE if dbal and adodb extension is loaded
	 *
	 * @return boolean TRUE if dbal and adodb is loaded
	 */
	protected function isDbalEnabled() {
		if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('adodb')
			&& \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('dbal')
		) {
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * Re-populate TYPO3_CONF_VARS in case they were changed during execution
	 *
	 * @return void
	 */
	protected function reloadConfiguration() {
		\TYPO3\CMS\Core\Core\Bootstrap::getInstance()
			->populateLocalConfiguration()
			->setCoreCacheToNullBackend();

		if ($this->isDbalEnabled()) {
			require(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('dbal') . 'ext_localconf.php');
			$GLOBALS['typo3CacheManager']->setCacheConfigurations($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']);
		}
	}
}
?>
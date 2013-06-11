<?php
namespace TYPO3\CMS\Install\ControllerAction;

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

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * General purpose controller action helper methods and bootstrap
 */
abstract class AbstractAction {

	/**
	 * @var \TYPO3\CMS\Extbase\Object\ObjectManager
	 * @inject
	 */
	protected $objectManager = NULL;

	/**
	 * @var \TYPO3\CMS\Install\View\StandaloneView
	 * @inject
	 */
	protected $view = NULL;

	/**
	 * @var string Name of target action, set by controller
	 */
	protected $action = '';

	/**
	 * @var string Form token for CSRF protection
	 */
	protected $token = '';

	/**
	 * @var array Values in $_POST['install']
	 */
	protected $postValues = array();

	/**
	 * Initialize this action
	 *
	 * @return string content
	 */
	protected function initialize() {
		$viewRootPath = GeneralUtility::getFileAbsFileName('EXT:install/Resources/Private/');
		$mainTemplate = ucfirst($this->action);
		$this->view->setTemplatePathAndFilename($viewRootPath . 'Templates/ControllerAction/' . $mainTemplate . '.html');
		$this->view->setLayoutRootPath($viewRootPath . 'Layouts/');
		$this->view->setPartialRootPath($viewRootPath . 'Partials/');
		$this->view
			// time is used in js and css as parameter to force loading of resources
			->assign('time', time())
			->assign('action', $this->action)
			->assign('token', $this->token)
			->assign('context', $this->getContext())
			->assign('typo3Version', TYPO3_version)
			->assign('siteName', $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename']);
	}

	/**
	 * Set form protection token
	 *
	 * @param string $token Form protection token
	 */
	public function setToken($token) {
		$this->token = $token;
	}

	/**
	 * Set action name. This is usually similar to the class name,
	 * only for loginForm, the action is login
	 *
	 * @param string $action Name of target action for forms
	 */
	public function setAction($action) {
		$this->action = $action;
	}

	/**
	 * Set POST form values of install tool
	 *
	 * @param array $postValues
	 */
	public function setPostValues(array $postValues) {
		$this->postValues = $postValues;
	}

	/**
	 * Context determines if the install tool is called within backend or standalone
	 *
	 * @return string Either 'standalone' or 'backend'
	 */
	protected function getContext() {
		$context = 'standalone';
		$formValues = GeneralUtility::_GP('install');
		if (isset($formValues['context'])) {
			$context = $formValues['context'] === 'backend' ? 'backend' : 'standalone';
		}
		return $context;
	}

	/**
	 * Get database instance.
	 * Will be initialized if it does not exist yet.
	 *
	 * @return \TYPO3\CMS\Core\Database\DatabaseConnection
	 */
	protected function getDatabase() {
		static $database;
		if (!is_object($database)) {
			/** @var \TYPO3\CMS\Core\Database\DatabaseConnection $database */
			$database = $this->objectManager->get('TYPO3\\CMS\\Core\\Database\\DatabaseConnection');
			$database->setDatabaseUsername($GLOBALS['TYPO3_CONF_VARS']['DB']['username']);
			$database->setDatabasePassword($GLOBALS['TYPO3_CONF_VARS']['DB']['password']);
			$database->setDatabaseHost($GLOBALS['TYPO3_CONF_VARS']['DB']['host']);
			$database->setDatabasePort($GLOBALS['TYPO3_CONF_VARS']['DB']['port']);
			$database->setDatabaseName($GLOBALS['TYPO3_CONF_VARS']['DB']['database']);
			$database->connectDB();
		}
		return $database;
	}

	/**
	 * Some actions like the database analyzer and the upgrade wizards need additional
	 * bootstrap actions performed.
	 *
	 * Those actions can potentially fatal if some old extension is loaded that triggers
	 * a fatal in ext_localconf or ext_tables code! Use only if really needed.
	 *
	 * @return void
	 */
	protected function loadExtLocalconfDatabaseAndExtTables() {
		\TYPO3\CMS\Core\Core\Bootstrap::getInstance()
			->loadTypo3LoadedExtAndExtLocalconf(FALSE)
			->applyAdditionalConfigurationSettings()
			->initializeTypo3DbGlobal()
			->loadExtensionTables(FALSE);
	}

	/**
	 * Get schema SQL of required cache framework tables.
	 *
	 * This method needs ext_localconf and ext_tables loaded!
	 *
	 * This is a hack, but there was no smarter solution with current cache configuration setup:
	 * InstallToolController sets the extbase caches to NullBackend to ensure the install tool does not
	 * cache anything. The CacheManager gets the required SQL from database backends only, so we need to
	 * temporarily 'fake' the standard db backends for extbase caches so they are respected.
	 *
	 * Additionally, the extbase_object cache is already in use and instantiated, and the CacheManager singleton
	 * does not allow overriding this definition. The only option at the moment is to 'fake' another cache with
	 * a different name, and then substitute this name in the sql content with the real one.
	 *
	 * @TODO: This construct needs to be improved. It does not recognise if some custom ext overwrote the extbase cache config
	 * @TODO: Solve this as soon as cache configuration is separated from ext_localconf / ext_tables
	 * @TODO: It might be possible to reduce this ugly construct by circumventing the 'singleton' of CacheManager by using 'new'
	 *
	 * @return string Cache framework SQL
	 */
	protected function getCachingFrameworkRequiredDatabaseSchema() {
		$cacheConfigurationBackup = $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'];
		$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['extbase_datamapfactory_datamap'] = array();
		$extbaseObjectFakeName = uniqid('extbase_object');
		$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][$extbaseObjectFakeName] = array();
		$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['extbase_reflection'] = array();
		$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['extbase_typo3dbbackend_tablecolumns'] = array();
		/** @var \TYPO3\CMS\Core\Cache\CacheManager $cacheManager */
		$cacheManager = $GLOBALS['typo3CacheManager'];
		$cacheManager->setCacheConfigurations($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']);
		$cacheSqlString = \TYPO3\CMS\Core\Cache\Cache::getDatabaseTableDefinitions();
		$sqlString = str_replace($extbaseObjectFakeName, 'extbase_object', $cacheSqlString);
		$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'] = $cacheConfigurationBackup;
		$cacheManager->setCacheConfigurations($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']);

		return $sqlString;
	}
}
?>

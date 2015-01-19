<?php
defined('TYPO3_MODE') or die();

// Register extension list update task
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\TYPO3\CMS\Extensionmanager\Task\UpdateExtensionListTask::class] = array(
	'extension' => $_EXTKEY,
	'title' => 'LLL:EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang.xlf:task.updateExtensionListTask.name',
	'description' => 'LLL:EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang.xlf:task.updateExtensionListTask.description',
	'additionalFields' => '',
);

if (TYPO3_MODE === 'BE') {
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers'][] = \TYPO3\CMS\Extensionmanager\Command\ExtensionCommandController::class;
	if (!(TYPO3_REQUESTTYPE & TYPO3_REQUESTTYPE_INSTALL)) {
		$signalSlotDispatcher = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\SignalSlot\Dispatcher::class);
		$signalSlotDispatcher->connect(
			\TYPO3\CMS\Extensionmanager\Service\ExtensionManagementService::class,
			'willInstallExtensions',
			\TYPO3\CMS\Core\Package\PackageManager::class,
			'scanAvailablePackages'
		);
		$signalSlotDispatcher->connect(
			\TYPO3\CMS\Extensionmanager\Service\ExtensionManagementService::class,
			'hasInstalledExtensions',
			\TYPO3\CMS\Core\Package\PackageManager::class,
			'updatePackagesForClassLoader'
		);
		$signalSlotDispatcher->connect(
			\TYPO3\CMS\Extensionmanager\Utility\InstallUtility::class,
			'tablesDefinitionIsBeingBuilt',
			\TYPO3\CMS\Core\Cache\Cache::class,
			'addCachingFrameworkRequiredDatabaseSchemaToTablesDefinition'
		);
		$signalSlotDispatcher->connect(
			\TYPO3\CMS\Extensionmanager\Utility\InstallUtility::class,
			'tablesDefinitionIsBeingBuilt',
			\TYPO3\CMS\Core\Category\CategoryRegistry::class,
			'addExtensionCategoryDatabaseSchemaToTablesDefinition'
		);
	}

}

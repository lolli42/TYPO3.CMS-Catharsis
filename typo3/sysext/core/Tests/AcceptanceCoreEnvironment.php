<?php
namespace TYPO3\CMS\Core\Tests;

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

use Codeception\Events;
use Codeception\Event\SuiteEvent;
use Codeception\Extension;
use TYPO3\CMS\Core\Cache\Backend\NullBackend;

class AcceptanceCoreEnvironment extends Extension
{

    /**
     * Core extensions to load.
     *
     * If the test case needs additional core extensions as requirement,
     * they can be noted here and will be added to LocalConfiguration
     * extension list and ext_tables.sql of those extensions will be applied.
     *
     * This property will stay empty in this abstract, so it is possible
     * to just overwrite it in extending classes. Extensions noted here will
     * be loaded for every test of a test case and it is not possible to change
     * the list of loaded extensions between single tests of a test case.
     *
     * A default list of core extensions is always loaded.
     *
     * @see FunctionalTestCaseUtility $defaultActivatedCoreExtensions
     * @var array
     */
    protected $coreExtensionsToLoad = [];

    /**
     * Array of test/fixture extensions paths that should be loaded for a test.
     *
     * This property will stay empty in this abstract, so it is possible
     * to just overwrite it in extending classes. Extensions noted here will
     * be loaded for every test of a test case and it is not possible to change
     * the list of loaded extensions between single tests of a test case.
     *
     * Given path is expected to be relative to your document root, example:
     *
     * array(
     *   'typo3conf/ext/some_extension/Tests/Functional/Fixtures/Extensions/test_extension',
     *   'typo3conf/ext/base_extension',
     * );
     *
     * Extensions in this array are linked to the test instance, loaded
     * and their ext_tables.sql will be applied.
     *
     * @var array
     */
    protected $testExtensionsToLoad = [];

    /**
     * Array of test/fixture folder or file paths that should be linked for a test.
     *
     * This property will stay empty in this abstract, so it is possible
     * to just overwrite it in extending classes. Path noted here will
     * be linked for every test of a test case and it is not possible to change
     * the list of folders between single tests of a test case.
     *
     * array(
     *   'link-source' => 'link-destination'
     * );
     *
     * Given paths are expected to be relative to the test instance root.
     * The array keys are the source paths and the array values are the destination
     * paths, example:
     *
     * array(
     *   'typo3/sysext/impext/Tests/Functional/Fixtures/Folders/fileadmin/user_upload' =>
     *   'fileadmin/user_upload',
     *   'typo3conf/ext/my_own_ext/Tests/Functional/Fixtures/Folders/uploads/tx_myownext' =>
     *   'uploads/tx_myownext'
     * );
     *
     * To be able to link from my_own_ext the extension path needs also to be registered in
     * property $testExtensionsToLoad
     *
     * @var array
     */
    protected $pathsToLinkInTestInstance = [];

    /**
     * This configuration array is merged with TYPO3_CONF_VARS
     * that are set in default configuration and factory configuration
     *
     * @var array
     */
    protected $configurationToUseInTestInstance = [];

    /**
     * Array of folders that should be created inside the test instance document root.
     *
     * This property will stay empty in this abstract, so it is possible
     * to just overwrite it in extending classes. Path noted here will
     * be linked for every test of a test case and it is not possible to change
     * the list of folders between single tests of a test case.
     *
     * Per default the following folder are created
     * /fileadmin
     * /typo3temp
     * /typo3conf
     * /typo3conf/ext
     * /uploads
     *
     * To create additional folders add the paths to this array. Given paths are expected to be
     * relative to the test instance root and have to begin with a slash. Example:
     *
     * array(
     *   'fileadmin/user_upload'
     * );
     *
     * @var array
     */
    protected $additionalFoldersToCreate = [];

    /**
     * Events to listen to
     */
    public static $events = [
        Events::SUITE_BEFORE => 'bootstrapTypo3Environment',
    ];

    public function bootstrapTypo3Environment(SuiteEvent $suiteEvent) {
        $testbase = new Testbase();
        $testbase->enableDisplayErrors();
        $testbase->defineBaseConstants();
        $testbase->defineOriginalRootPath();
        $testbase->createDirectory(ORIGINAL_ROOT . 'typo3temp/var/tests/acceptance');
        $testbase->createDirectory(ORIGINAL_ROOT . 'typo3temp/var/transient');

        $instancePath = ORIGINAL_ROOT . 'typo3temp/var/tests/acceptance';

        $testbase = new Testbase();
        $testbase->defineTypo3ModeBe();
        $testbase->setTypo3TestingContext();
        $testbase->removeOldInstanceIfExists($instancePath);
        // Basic instance directory structure
        $testbase->createDirectory($instancePath . '/fileadmin');
        $testbase->createDirectory($instancePath . '/typo3temp/var/transient');
        $testbase->createDirectory($instancePath . '/typo3temp/assets');
        $testbase->createDirectory($instancePath . '/typo3conf/ext');
        $testbase->createDirectory($instancePath . '/uploads');
        // Additionally requested directories
        foreach ($this->additionalFoldersToCreate as $directory) {
            $testbase->createDirectory($instancePath . '/' . $directory);
        }
        $testbase->createLastRunTextfile($instancePath);
        $testbase->setUpInstanceCoreLinks($instancePath);
        $testbase->linkTestExtensionsToInstance($instancePath, $this->testExtensionsToLoad);
        $testbase->linkPathsInTestInstance($instancePath, $this->pathsToLinkInTestInstance);
        $localConfiguration = $testbase->getOriginalDatabaseSettingsFromEnvironmentOrLocalConfiguration();
        $originalDatabaseName = $localConfiguration['DB']['database'];
        // Append the unique identifier to the base database name to end up with a single database per test case
        $localConfiguration['DB']['database'] = $originalDatabaseName . '_at';
        $testbase->testDatabaseNameIsNotTooLong($originalDatabaseName, $localConfiguration);
        // Set some hard coded base settings for the instance. Those could be overruled by
        // $this->configurationToUseInTestInstance if needed again.
        $localConfiguration['BE']['debug'] = true;
        $localConfiguration['BE']['installToolPassword'] = '$P$notnotnotnotnotnot.validvalidva';
        $localConfiguration['SYS']['isInitialInstallationInProgress'] = false;
        $localConfiguration['SYS']['isInitialDatabaseImportDone'] = true;
        $localConfiguration['SYS']['displayErrors'] = '1';
        $localConfiguration['SYS']['debugExceptionHandler'] = '';
        $localConfiguration['SYS']['trustedHostsPattern'] = '.*';
        $localConfiguration['SYS']['encryptionKey'] = 'iAmInvalid';
        $localConfiguration['SYS']['setDBinit'] = 'SET SESSION sql_mode = \'STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_VALUE_ON_ZERO,NO_ENGINE_SUBSTITUTION,NO_ZERO_DATE,NO_ZERO_IN_DATE,ONLY_FULL_GROUP_BY\';';
        $localConfiguration['SYS']['caching']['cacheConfigurations']['extbase_object']['backend'] = NullBackend::class;
        $testbase->setUpLocalConfiguration($instancePath, $localConfiguration, $this->configurationToUseInTestInstance);
        $defaultCoreExtensionsToLoad = [
            'core',
            'extbase',
            'fluid',
            'extensionmanager',
            'lang',
            'setup',
            'rsaauth',
            'saltedpasswords',
            'backend',
            'belog',
            'install',
            't3skin',
            'frontend',
            'recordlist',
            'sv',
        ];
        $testbase->setUpPackageStates($instancePath, $defaultCoreExtensionsToLoad, $this->coreExtensionsToLoad, $this->testExtensionsToLoad);
        $testbase->setUpBasicTypo3Bootstrap($instancePath);
        $testbase->setUpTestDatabase($localConfiguration['DB']['database'], $originalDatabaseName);
        $testbase->loadExtensionTables();
        $testbase->createDatabaseStructure();

        // Unset a closure or phpunit kicks in with a 'serialization of \Closure is not allowed'
        // Alternative solution:
        // unset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['GLOBAL']['cliKeys']['extbase']);
        $suite = $suiteEvent->getSuite();
        $suite->setBackupGlobals(FALSE);
    }

}

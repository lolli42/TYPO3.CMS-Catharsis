<?php
namespace TYPO3\CMS\Extensionmanager\Tests\Unit\Service;

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

/**
 * Testcase
 *
 */
class ExtensionManagementServiceTest extends \TYPO3\CMS\Core\Tests\UnitTestCase
{
    /**
     * @test
     * @return void
     */
    public function resolveDependenciesCallsDownloadDependenciesIfDownloadKeyExistsInQueue()
    {
        $managementMock = $this->getAccessibleMock(
            \TYPO3\CMS\Extensionmanager\Service\ExtensionManagementService::class,
            array('downloadDependencies', 'uninstallDependenciesToBeUpdated', 'setInExtensionRepository', 'downloadMainExtension', 'isAutomaticInstallationEnabled')
        );
        $managementMock->expects($this->any())->method('downloadMainExtension')->will($this->returnValue(array()));
        $managementMock->expects($this->any())->method('isAutomaticInstallationEnabled')->will($this->returnValue(array(false)));
        $extensionModelMock = $this->getAccessibleMock(\TYPO3\CMS\Extensionmanager\Domain\Model\Extension::class);
        $extensionModelMock->_set('extensionKey', 'foobar');
        $extensionModelMock->_set('version', '1.0.0');
        $dependencyUtilityMock = $this->getAccessibleMock(\TYPO3\CMS\Extensionmanager\Utility\DependencyUtility::class, array('checkDependencies'));
        $dependencyUtilityMock->expects($this->atLeastOnce())->method('checkDependencies');
        $managementMock->_set('dependencyUtility', $dependencyUtilityMock);
        $downloadQueueMock = $this->getAccessibleMock(\TYPO3\CMS\Extensionmanager\Domain\Model\DownloadQueue::class, array('getExtensionQueue', 'addExtensionToInstallQueue'));
        $downloadQueueMock->expects($this->atLeastOnce())->method('getExtensionQueue')->will($this->returnValue(array(
            'download' => array(
                'foo' => $extensionModelMock
            )
        )));
        $managementMock->_set('downloadQueue', $downloadQueueMock);
        $managementMock->expects($this->once())->method('downloadDependencies')->with(array('foo' => $extensionModelMock))->will($this->returnValue(array()));
        $managementMock->_call('installExtension', $extensionModelMock);
    }

    /**
     * @test
     * @return void
     */
    public function resolveDependenciesCallsUpdateAndDownloadDependenciesIfUpdateKeyExistsInQueue()
    {
        $managementMock = $this->getAccessibleMock(
            \TYPO3\CMS\Extensionmanager\Service\ExtensionManagementService::class,
            array('downloadDependencies', 'uninstallDependenciesToBeUpdated', 'setInExtensionRepository', 'downloadMainExtension', 'isAutomaticInstallationEnabled')
        );
        $managementMock->expects($this->any())->method('downloadMainExtension')->will($this->returnValue(array()));
        $managementMock->expects($this->any())->method('isAutomaticInstallationEnabled')->will($this->returnValue(true));
        $extensionModelMock = $this->getAccessibleMock(\TYPO3\CMS\Extensionmanager\Domain\Model\Extension::class);
        $extensionModelMock->_set('extensionKey', 'foobar');
        $extensionModelMock->_set('version', '1.0.0');
        $dependencyUtilityMock = $this->getAccessibleMock(\TYPO3\CMS\Extensionmanager\Utility\DependencyUtility::class, array('checkDependencies'));
        $dependencyUtilityMock->expects($this->atLeastOnce())->method('checkDependencies');
        $managementMock->_set('dependencyUtility', $dependencyUtilityMock);
        $downloadQueueMock = $this->getAccessibleMock(\TYPO3\CMS\Extensionmanager\Domain\Model\DownloadQueue::class, array('getExtensionQueue', 'addExtensionToInstallQueue'));
        $downloadQueueMock->expects($this->atLeastOnce())->method('getExtensionQueue')->will($this->returnValue(array(
            'update' => array(
                'foo' => $extensionModelMock
            )
        )));
        $managementMock->_set('downloadQueue', $downloadQueueMock);
        $managementMock->expects($this->once())->method('downloadDependencies')->with(array('foo' => $extensionModelMock))->will($this->returnValue(array()));
        $managementMock->expects($this->once())->method('uninstallDependenciesToBeUpdated')->with(array('foo' => $extensionModelMock))->will($this->returnValue(array()));
        $managementMock->_call('installExtension', $extensionModelMock);
    }

    /**
     * @test
     * @return void
     */
    public function downloadDependenciesCallsDownloadUtilityDownloadMethod()
    {
        $managementMock = $this->getAccessibleMock(\TYPO3\CMS\Extensionmanager\Service\ExtensionManagementService::class, array(
            'dummy'
        ));

        $extensionModelMock = $this->getAccessibleMock(\TYPO3\CMS\Extensionmanager\Domain\Model\Extension::class, array('getExtensionKey'));
        $extensionModelMock->_set('extensionKey', 'foobar');
        $extensionModelMock->_set('version', '1.0.0');

        $dependencyUtilityMock = $this->getAccessibleMock(\TYPO3\CMS\Extensionmanager\Utility\DependencyUtility::class);
        $dependencyUtilityMock->expects($this->atLeastOnce())->method('checkDependencies');
        $installUtilityMock = $this->getMockBuilder(\TYPO3\CMS\Extensionmanager\Utility\InstallUtility::class)->getMock();
        $installUtilityMock->expects($this->any())->method('enrichExtensionWithDetails')->will($this->returnValue(array()));
        $extensionModelUtilityMock = $this->getMockBuilder(\TYPO3\CMS\Extensionmanager\Utility\ExtensionModelUtility::class)->getMock();
        $extensionModelUtilityMock->expects($this->any())->method('mapExtensionArrayToModel')->will($this->returnValue($extensionModelMock));
        $managementMock->_set('dependencyUtility', $dependencyUtilityMock);
        $managementMock->_set('installUtility', $installUtilityMock);
        $managementMock->_set('extensionModelUtility', $extensionModelUtilityMock);

        $downloadQueue = array(
            $extensionModelMock
        );
        $downloadQueueMock = $this->getAccessibleMock(\TYPO3\CMS\Extensionmanager\Domain\Model\DownloadQueue::class, array('removeExtensionFromQueue', 'addExtensionToInstallQueue'));
        $downloadUtilityMock = $this->getAccessibleMock(\TYPO3\CMS\Extensionmanager\Utility\DownloadUtility::class, array('download'));
        $downloadUtilityMock->expects($this->once())->method('download')->with($extensionModelMock);
        $managementMock->_set('downloadUtility', $downloadUtilityMock);
        $managementMock->_set('downloadQueue', $downloadQueueMock);
        $managementMock->_call('downloadDependencies', $downloadQueue);
    }

    /**
     * @test
     * @return void
     */
    public function downloadDependenciesCallsRemoveExtensionFromQueue()
    {
        $managementMock = $this->getAccessibleMock(\TYPO3\CMS\Extensionmanager\Service\ExtensionManagementService::class, array(
            'dummy'
        ));

        /** @var \TYPO3\CMS\Extensionmanager\Domain\Model\Extension $extensionModelMock */
        $extensionModelMock = $this->getMockBuilder(\TYPO3\CMS\Extensionmanager\Domain\Model\Extension::class)
            ->setMethods(array('getExtensionKey'))
            ->getMock();
        $extensionModelMock->setExtensionKey('foobar');
        $extensionModelMock->setVersion('1.0.0');
        $downloadQueue = array(
            $extensionModelMock
        );

        $dependencyUtilityMock = $this->getAccessibleMock(\TYPO3\CMS\Extensionmanager\Utility\DependencyUtility::class);
        $dependencyUtilityMock->expects($this->atLeastOnce())->method('checkDependencies');
        $installUtilityMock = $this->getMockBuilder(\TYPO3\CMS\Extensionmanager\Utility\InstallUtility::class)->getMock();
        $installUtilityMock->expects($this->any())->method('enrichExtensionWithDetails')->will($this->returnValue(array()));
        $extensionModelUtilityMock = $this->getMockBuilder(\TYPO3\CMS\Extensionmanager\Utility\ExtensionModelUtility::class)->getMock();
        $extensionModelUtilityMock->expects($this->any())->method('mapExtensionArrayToModel')->will($this->returnValue($extensionModelMock));
        $managementMock->_set('dependencyUtility', $dependencyUtilityMock);
        $managementMock->_set('installUtility', $installUtilityMock);
        $managementMock->_set('extensionModelUtility', $extensionModelUtilityMock);

        $downloadQueueMock = $this->getAccessibleMock(\TYPO3\CMS\Extensionmanager\Domain\Model\DownloadQueue::class, array('removeExtensionFromQueue', 'addExtensionToInstallQueue'));
        $downloadUtilityMock = $this->getAccessibleMock(\TYPO3\CMS\Extensionmanager\Utility\DownloadUtility::class, array('download'));
        $downloadQueueMock->expects($this->once())->method('removeExtensionFromQueue')->with($extensionModelMock);
        $managementMock->_set('downloadUtility', $downloadUtilityMock);
        $managementMock->_set('downloadQueue', $downloadQueueMock);
        $managementMock->_call('downloadDependencies', $downloadQueue);
    }

    /**
     * @test
     * @return void
     */
    public function downloadDependenciesReturnsResolvedDependencies()
    {
        $managementMock = $this->getAccessibleMock(\TYPO3\CMS\Extensionmanager\Service\ExtensionManagementService::class, array(
            'dummy'
        ));

        $extensionModelMock = $this->getAccessibleMock(\TYPO3\CMS\Extensionmanager\Domain\Model\Extension::class, array('getExtensionKey'));
        $extensionModelMock->_set('extensionKey', 'foobar');
        $extensionModelMock->_set('version', '1.0.0');
        $downloadQueue = array(
            $extensionModelMock
        );

        $dependencyUtilityMock = $this->getAccessibleMock(\TYPO3\CMS\Extensionmanager\Utility\DependencyUtility::class);
        $dependencyUtilityMock->expects($this->atLeastOnce())->method('checkDependencies');
        $installUtilityMock = $this->getMockBuilder(\TYPO3\CMS\Extensionmanager\Utility\InstallUtility::class)->getMock();
        $installUtilityMock->expects($this->any())->method('enrichExtensionWithDetails')->will($this->returnValue(array()));
        $extensionModelUtilityMock = $this->getMockBuilder(\TYPO3\CMS\Extensionmanager\Utility\ExtensionModelUtility::class)->getMock();
        $extensionModelUtilityMock->expects($this->any())->method('mapExtensionArrayToModel')->will($this->returnValue($extensionModelMock));
        $managementMock->_set('dependencyUtility', $dependencyUtilityMock);
        $managementMock->_set('installUtility', $installUtilityMock);
        $managementMock->_set('extensionModelUtility', $extensionModelUtilityMock);

        $downloadQueueMock = $this->getAccessibleMock(\TYPO3\CMS\Extensionmanager\Domain\Model\DownloadQueue::class, array('removeExtensionFromQueue', 'addExtensionToInstallQueue'));
        $downloadUtilityMock = $this->getAccessibleMock(\TYPO3\CMS\Extensionmanager\Utility\DownloadUtility::class, array('download'));
        $extensionModelMock->expects($this->atLeastOnce())->method('getExtensionKey')->will($this->returnValue('foobar'));
        $managementMock->_set('downloadUtility', $downloadUtilityMock);
        $managementMock->_set('downloadQueue', $downloadQueueMock);
        $resolvedDependencies = $managementMock->_call('downloadDependencies', $downloadQueue);
        $this->assertEquals(array('downloaded' => array('foobar' => $extensionModelMock)), $resolvedDependencies);
    }

    /**
     * @test
     * @return void
     */
    public function uninstallDependenciesToBeUpdatedCallsUninstall()
    {
        $managementMock = $this->getAccessibleMock(\TYPO3\CMS\Extensionmanager\Service\ExtensionManagementService::class, array(
            'dummy'
        ));
        $extensionModelMock = $this->getAccessibleMock(\TYPO3\CMS\Extensionmanager\Domain\Model\Extension::class, array('getExtensionKey'));
        $extensionModelMock->_set('extensionKey', 'foobar');
        $extensionModelMock->_set('version', '1.0.0');
        $extensionModelMock->expects($this->atLeastOnce())->method('getExtensionKey')->will($this->returnValue('foobar'));
        $downloadQueue = array(
            $extensionModelMock
        );
        $installUtility = $this->getAccessibleMock(\TYPO3\CMS\Extensionmanager\Utility\InstallUtility::class, array('uninstall'), array(), '', false);
        $installUtility->expects($this->once())->method('uninstall')->with('foobar');
        $managementMock->_set('installUtility', $installUtility);
        $managementMock->_call('uninstallDependenciesToBeUpdated', $downloadQueue);
    }

    /**
     * @test
     * @return void
     */
    public function uninstallDependenciesToBeUpdatedReturnsResolvedDependencies()
    {
        $managementMock = $this->getAccessibleMock(\TYPO3\CMS\Extensionmanager\Service\ExtensionManagementService::class, array(
            'dummy'
        ));
        $extensionModelMock = $this->getAccessibleMock(\TYPO3\CMS\Extensionmanager\Domain\Model\Extension::class, array('getExtensionKey'));
        $extensionModelMock->_set('extensionKey', 'foobar');
        $extensionModelMock->_set('version', '1.0.0');
        $extensionModelMock->expects($this->atLeastOnce())->method('getExtensionKey')->will($this->returnValue('foobar'));
        $downloadQueue = array(
            $extensionModelMock
        );
        $installUtility = $this->getAccessibleMock(\TYPO3\CMS\Extensionmanager\Utility\InstallUtility::class, array('uninstall'), array(), '', false);
        $managementMock->_set('installUtility', $installUtility);
        $resolvedDependencies = $managementMock->_call('uninstallDependenciesToBeUpdated', $downloadQueue);
        $this->assertEquals(array('updated' => array('foobar' => $extensionModelMock)), $resolvedDependencies);
    }

    /**
     * @test
     * @return void
     */
    public function installDependenciesCallsInstall()
    {
        $managementMock = $this->getAccessibleMock(
            \TYPO3\CMS\Extensionmanager\Service\ExtensionManagementService::class,
            array('emitWillInstallExtensionsSignal', 'emitHasInstalledExtensionSignal')
        );
        /** @var \TYPO3\CMS\Extensionmanager\Domain\Model\Extension $extensionMock */
        $extensionMock = $this->getMockBuilder(\TYPO3\CMS\Extensionmanager\Domain\Model\Extension::class)
            ->setMethods(array('dummy'))
            ->getMock();
        $extensionMock->setExtensionKey('foobar');
        $installQueue = array(
            'foobar' => $extensionMock,
        );
        $installUtility = $this->getAccessibleMock(\TYPO3\CMS\Extensionmanager\Utility\InstallUtility::class, array('install', 'emitWillInstallExtensionsSignal'), array(), '', false);
        $installUtility->expects($this->once())->method('install')->with('foobar');
        $managementMock->_set('installUtility', $installUtility);
        $managementMock->_call('installDependencies', $installQueue);
    }

    /**
     * @test
     * @return void
     */
    public function installDependenciesReturnsResolvedDependencies()
    {
        $managementMock = $this->getAccessibleMock(\TYPO3\CMS\Extensionmanager\Service\ExtensionManagementService::class, array(
            'emitWillInstallExtensionsSignal',
            'emitHasInstalledExtensionSignal'
        ));
        /** @var \TYPO3\CMS\Extensionmanager\Domain\Model\Extension $extensionMock */
        $extensionMock = $this->getMockBuilder(\TYPO3\CMS\Extensionmanager\Domain\Model\Extension::class)
            ->setMethods(array('dummy'))
            ->getMock();
        $extensionMock->setExtensionKey('foobar');
        $installQueue = array(
            'foobar' => $extensionMock,
        );
        $installUtility = $this->getAccessibleMock(\TYPO3\CMS\Extensionmanager\Utility\InstallUtility::class, array('install', 'emitWillInstallExtensionsSignal'), array(), '', false);
        $installUtility->expects($this->once())->method('install')->with('foobar');
        $managementMock->_set('installUtility', $installUtility);
        $resolvedDependencies = $managementMock->_call('installDependencies', $installQueue);
        $this->assertEquals(array(
            'installed' => array(
                'foobar' => 'foobar'
            )
        ), $resolvedDependencies);
    }
}

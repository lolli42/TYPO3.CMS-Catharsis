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
 * Very first install step:
 * - Needs execution if typo3conf/LocalConfiguration.php does not exist
 * - Renders system environment output
 * - Creates folders like typo3temp, see FolderStructure/DefaultFactory for details
 * - Creates typo3conf/LocalConfiguration.php from factory
 */
class EnvironmentAndFolders extends AbstractStepAction implements StepActionInterface {

	/**
	 * Execute environment and folder step:
	 * - Create main folder structure
	 * - Create typo3conf/LocalConfiguration
	 *
	 * @return array<\TYPO3\CMS\Install\Status\StatusInterface>
	 */
	public function execute() {
		/** @var $folderStructureFactory \TYPO3\CMS\Install\FolderStructure\DefaultFactory */
		$folderStructureFactory = new \TYPO3\CMS\Install\FolderStructure\DefaultFactory;
		/** @var $structureFacade \TYPO3\CMS\Install\FolderStructure\StructureFacade */
		$structureFacade = $folderStructureFactory->getStructure();
		$structureFixMessages = $structureFacade->fix();
		$statusUtility = new  \TYPO3\CMS\Install\Status\StatusUtility;
		$errorsFromStructure = $statusUtility->filterBySeverity($structureFixMessages, 'error');

		// Proceed with creating LocalConfiguration only if folder creation did not throw errors
		if (count($errorsFromStructure) < 1) {
			$configurationManager = new \TYPO3\CMS\Core\Configuration\ConfigurationManager;
			$configurationManager->createLocalConfigurationFromFactoryConfiguration();
		}

		return $errorsFromStructure;
	}

	/**
	 * Step needs to be executed if LocalConfiguration file does not exist.
	 *
	 * @return boolean
	 */
	public function needsExecution() {
		if (@is_file(PATH_typo3conf . 'LocalConfiguration.php')) {
			return FALSE;
		} else {
			return TRUE;
		}
	}

	/**
	 * Render this step
	 *
	 * @return string
	 */
	public function render() {
		$html = array();
		$html[] = '<h3>System environment check</h3>';
		$html[] = '<p>TYPO3 is an enterprise content management system that is powerful, yet easy to install</p>';
		$html[] = '<p>In some simple steps you\'ll be ready to add content to your website.';
		$html[] = 'This first step checks your system environment and points out issues.</p>';

		$errorsAndWarningsFromEnvironment = $this->getErrorAndWarningsFromEnvironmentCheck();

		/** @var $folderStructureFactory \TYPO3\CMS\Install\FolderStructure\DefaultFactory */
		$folderStructureFactory = new \TYPO3\CMS\Install\FolderStructure\DefaultFactory;
		/** @var $structureFacade \TYPO3\CMS\Install\FolderStructure\StructureFacade */
		$structureFacade = $folderStructureFactory->getStructure();
		$structureMessages = $structureFacade->getStatus();
		/** @var $statusUtility \TYPO3\CMS\Install\Status\StatusUtility */
		$statusUtility = new  \TYPO3\CMS\Install\Status\StatusUtility;
		$errorsFromStructure = $statusUtility->filterBySeverity($structureMessages, 'error');

		$errorsAndWarnings = $errorsAndWarningsFromEnvironment;
		if (count($errorsFromStructure) > 0) {
			$errorsAndWarnings['error'] = array_merge(
				$errorsFromStructure,
				$errorsAndWarningsFromEnvironment['error']
			);
		}

		$html[] = $this->renderButtonsDependingOnStatusArray($errorsAndWarnings);
		$html[] = $this->renderMessages($errorsAndWarnings);

		return implode(CR, $html);
	}

	/**
	 * Render links depending on status output
	 *
	 * @param array $orderedStatus
	 * @return string
	 */
	protected function renderButtonsDependingOnStatusArray(array $orderedStatus) {
		$html = array();
		$html[] = '<form method="post" action="StepInstaller.php">';

		if (count($orderedStatus['error']) === 0 && count($orderedStatus['warning']) === 0) {
			$html[] = '<form method="post" action="StepInstaller.php">';
			$html[] = '<input type="hidden" value="environmentAndFolders" name="executeStep" />';
			$html[] = '<button type="submit">';
			$html[] = 'System looks good. Continue!';
			$html[] = '<span class="t3-install-form-button-icon-positive">&nbsp;</span>';
			$html[] = '</button>';
			$html[] = '</form>';
		} else {
			$html[] = '<form method="post" action="StepInstaller.php">';
			$html[] = '<button type="submit">';
			$html[] = 'Fixed. Check again!';
			$html[] = '<span class="t3-install-form-button-icon-positive">&nbsp;</span>';
			$html[] = '</button>';
			$html[] = '</form>';

			$html[] = '<form method="post" action="StepInstaller.php">';
			$html[] = '<input type="hidden" value="environmentAndFolders" name="executeStep" />';
			$html[] = '<button type="submit">';
			$html[] = 'I know what I\'m doing, continue!';
			$html[] = '<span class="t3-install-form-button-icon-negative">&nbsp;</span>';
			$html[] = '</button>';
			$html[] = '</form>';
		}

		return implode(CR, $html);
	}

	/**
	 * Execute environment check, return array with error and warnings only
	 *
	 * @return array<\TYPO3\CMS\Install\Status\StatusInterface>
	 */
	protected function getErrorAndWarningsFromEnvironmentCheck() {
		$statusCheck = new \TYPO3\CMS\Install\SystemEnvironment\Check;
		$statusObjects = $statusCheck->getStatus();

		$orderedStatus = array(
			'error' => array(),
			'warning' => array(),
		);

		/** @var $statusObject \TYPO3\CMS\Install\Status\AbstractStatus */
		foreach ($statusObjects as $statusObject) {
			$severityIdentifier = $statusObject->getSeverity();
			if ($severityIdentifier === 'error' || $severityIdentifier === 'warning') {
				$orderedStatus[$severityIdentifier][] = $statusObject;
			}
		}
		return $orderedStatus;
	}

	/**
	 * Render messages by severity
	 *
	 * @param array Array with message severity's
	 * @return string
	 */
	protected function renderMessages(array $orderedStatus) {
		$statusUtility = new \TYPO3\CMS\Install\Status\StatusUtility();

		$html = '';
		foreach ($orderedStatus as $severityIdentifier => $severity) {
			$html .= $statusUtility->renderStatusObjectsAsHtml($severity);
		}

		if (strlen($html) > 0) {
			$html = '<p>Detailed analysis</p>' . $html;
		}
		return $html;
	}
}
?>
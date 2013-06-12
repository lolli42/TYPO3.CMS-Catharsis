<?php
namespace TYPO3\CMS\Install\Controller;

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
 * Controller to handle install steps
 */
class StepController {

	/**
	 * @var array Register and order of install steps
	 */
	protected $steps = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->steps = array(
			'environmentAndFolders' => array(
				'className' => '\\TYPO3\\CMS\\Install\\StepAction\\EnvironmentAndFolders',
			),
			'databaseConnect' => array(
				'className' => '\\TYPO3\\CMS\\Install\\StepAction\\DatabaseConnect',
			),
			'databaseSelect' => array(
				'className' => '\\TYPO3\\CMS\\Install\\StepAction\\DatabaseSelect',
			),
			'databaseData' => array(
				'className' => '\\TYPO3\\CMS\\Install\\StepAction\\DatabaseData',
			),
			'defaultConfiguration' => array(
				'className' => '\\TYPO3\\CMS\\Install\\StepAction\\DefaultConfiguration',
			),
		);
	}

	/**
	 * Index action acts a a dispatcher to different steps
	 *
	 * @throws Exception
	 * @return void
	 */
	public function indexAction() {
		// Execute a step if needed. This usually sets any data of the 'previous' step,
		// and if everything worked out well, the below code will call the 'next' step,
		// if 'previous' step does not return TRUE for needsExecution again. This can
		// happen if for example wrong database credentials were given.
		$executionMessages = array();
		$stepObjects = array();
		if (isset($GLOBALS['_POST']['executeStep'])) {
			$stepName = $GLOBALS['_POST']['executeStep'];
			if (!array_key_exists($stepName, $this->steps)) {
				throw new Exception(
					'Step not found',
					1366914638
				);
			}

			// Bootstrap restriction: The steps are constructed to add bootstrap calls
			// in __construct() that need to be done *additionally* to the bootstrap calls
			// of the previous step. So, the steps have a cross-dependency to each other
			// at this point: Step a does bootstrap work and step b needs additional
			// bootstrap work and relies on a.
			// The *additional* bootstrap is done in the step's __construct(). So, if we
			// need to execute step b, step a needs to be constructed again. To ensure,
			// __construct()-bootstrap is not called multiple times, the constructed
			// objects are stored in a local variable and re-used in the needsExecution()
			// part below.
			// This whole construct could be simplified if we have a dependency based bootstrap.

			// Create step objects in front of requested step
			foreach($this->steps as $previousStepName => $previousStepDetails) {
				if ($previousStepName === $stepName) {
					break;
				}
				$stepObjects[$previousStepName] = new $previousStepDetails['className'];
				if (!$stepObjects[$previousStepName] instanceof \TYPO3\CMS\Install\StepAction\StepActionInterface) {
					throw new Exception(
						'Step ' . $previousStepDetails['className'] . 'must implement StepActionInterface',
						1368038168
					);
				}
			}

			/** @var $stepObject \TYPO3\CMS\Install\StepAction\StepActionInterface */
			$stepClassName = $this->steps[$stepName]['className'];
			$stepObjects[$stepName] = new $stepClassName();
			$executionMessages = $stepObjects[$stepName]->execute();
		}

		// Check if some step needs execution and render if so
		foreach ($this->steps as $stepName => $stepDetails) {
			// Create step instance if not done yet
			if (!array_key_exists($stepName, $stepObjects)) {
				$stepObjects[$stepName] = new $stepDetails['className'];
				if (!$stepObjects[$stepName] instanceof \TYPO3\CMS\Install\StepAction\StepActionInterface) {
					throw new Exception(
						'Step ' . $stepDetails['className'] . 'must implement StepActionInterface',
						1365967344
					);
				}
			}
			$stepObject = $stepObjects[$stepName];

			if ($stepObject->needsExecution()) {
				$stepContent = $stepObject->render();
				$stepContent = $this->render($stepContent, $executionMessages);
				$this->output($stepContent);
			}
		}

		// If there was no output yet, we have reached the last step.
		// In this case, redirect to plain install tool
		$getPostValues = GeneralUtility::_GP('install');
		$context = '';
		// Add context parameter in case this script was called within backend scope
		if (isset($getPostValues['context']) && $getPostValues['context'] === 'backend') {
			$context = '?install[context]=backend';
		}
		\TYPO3\CMS\Core\Utility\HttpUtility::redirect('InstallTool.php' . $context, \TYPO3\CMS\Core\Utility\HttpUtility::HTTP_STATUS_307);
	}

	/**
	 * Render a step with the execution messages of the executed step and the current step content
	 *
	 * @param string $stepContent Inner step content
	 * @param array $executionMessages<\TYPO3\CMS\Install\Status\StatusInterface> Status objects of executed step
	 * @return string
	 */
	protected function render($stepContent, array $executionMessages = array()) {
		$mainPageContent = file_get_contents(__DIR__ . '/../../Resources/Private/Templates/StepInstaller/Main.html');

		$statusUtility = new \TYPO3\CMS\Install\Status\StatusUtility();
		$executionMessagesHtml = $statusUtility->renderStatusObjectsAsHtml($executionMessages);

		$markerArray = array();
		$markerArray['HEAD_TITLE'] = 'TYPO3 ' . TYPO3_branch;
		$markerArray['STEP_EXECUTION_MESSAGES'] = $executionMessagesHtml;
		$markerArray['CONTENT'] = $stepContent;
		$markerArray['BODY_TITLE'] = 'Installing TYPO3 ' . TYPO3_version;
		$markerArray['COPYRIGHT'] = $this->getCopyRightString();

		foreach ($markerArray as $key => $value) {
			$mainPageContent = str_replace('###' . $key . '###', $value, $mainPageContent);
		}

		return $mainPageContent;
	}

	/**
	 * Get copyright string
	 *
	 * @return string copyright
	 */
	protected function getCopyRightString() {
		$content = array();
		$content[] = '<p>';
		$content[] = '<strong>TYPO3 CMS.</strong> Copyright &copy; 1998-' . date('Y');
		$content[] = 'Kasper Sk&#229;rh&#248;j. Extensions are copyright of their respective';
		$content[] = 'owners. Go to <a href="' . TYPO3_URL_GENERAL . '">' . TYPO3_URL_GENERAL . '</a>';
		$content[] = 'for details. TYPO3 comes with ABSOLUTELY NO WARRANTY;';
		$content[] = '<a href="' . TYPO3_URL_LICENSE . '">click</a> for details.';
		$content[] = 'This is free software, and you are welcome to redistribute it';
		$content[] = 'under certain conditions; <a href="' . TYPO3_URL_LICENSE . '">click</a>';
		$content[] = 'for details. Obstructing the appearance of this notice is prohibited by law.';
		$content[] = '</p>';
		$content[] = '<p>';
		$content[] = '<a href="' . TYPO3_URL_DONATE . '"><strong>Donate</strong></a> |';
		$content[] = '<a href="' . TYPO3_URL_ORG . '">TYPO3.org</a>';
		$content[] = '</p>';
		return implode(LF, $content);
	}

	/**
	 * Print output
	 *
	 * @param string $content Content to output
	 */
	protected function output($content) {
		header('Content-Type: text/html; charset=utf-8');
		echo $content;
		die();
	}

}
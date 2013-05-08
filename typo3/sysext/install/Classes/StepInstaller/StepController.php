<?php
namespace TYPO3\CMS\Install\StepInstaller;

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
		require_once __DIR__ . '/../Exception.php';
		require_once __DIR__ . '/Exception.php';
		require_once __DIR__ . '/Step/StepInterface.php';
		require_once __DIR__ . '/../Status/StatusUtility.php';

		$this->steps = array(
			'environmentCheck' => array(
				'className' => '\\TYPO3\\CMS\\Install\\StepInstaller\\Step\\EnvironmentAndFolders',
				'file' => __DIR__ . '/Step/EnvironmentAndFolders.php',
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
		// Require step classes if given
		foreach ($this->steps as $step) {
			if (!empty($step['file'])) {
				require_once $step['file'];
			}
		}

		$executionMessages = array();
		if (isset($GLOBALS['_POST']['executeStep'])) {
			$stepName = $GLOBALS['_POST']['executeStep'];
			if (!array_key_exists($stepName, $this->steps)) {
				throw new Exception(
					'Step not found',
					1366914638
				);
			}
			/** @var $stepObject Step\StepInterface */
			$stepClassName = $this->steps[$stepName]['className'];
			$stepObject = new $stepClassName();
			$executionMessages = $stepObject->execute();
		}

		foreach ($this->steps as $step) {
			$stepObject = new $step['className'];
			if (!$stepObject instanceof Step\StepInterface) {
				throw new Exception(
					'Step ' . $step['className'] . 'must implement StepInterface',
					1365967344
				);
			}

			$stepContent = '';
			if ($stepObject->needsExecution()) {
				$stepContent = $stepObject->render();
			}
			$stepContent = $this->render($stepContent, $executionMessages);
			$this->output($stepContent);
		}
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
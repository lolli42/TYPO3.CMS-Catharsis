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
		$this->steps = array(
			'environmentCheck' => array(
				'className' => '\\TYPO3\\CMS\\Install\\StepInstaller\\Step\\EnvironmentCheck',
				'file' => __DIR__ . '/Step/EnvironmentCheck.php',
			),
		);


		$expectedStructure = array(
			'type' => 'root',
			'permission' => '2770',
			'childs' => array(
				'web' => array(
					'type' => 'directory',
					'permission' => '2770',
					'childs' => array(
						'typo3conf' => array(
							'type' => 'directory',
							'permission' => '2770',
							'childs' => array(
								'LocalConfiguration.php' => array(
									'type' => 'file',
									'permission' => '660',
								),
							),
							'fooOld' => array(
								'type' => 'directory',
							),
						),
						'typo3_src' => array(
							'type' => 'link',
							'target' => '../typo3_src',
						),
						'index.php' => array(
							'type' => 'link',
							'target' => 'typo3_src/index.php',
						),
					),
				),
				'typo3_src-core-version1' => array(
					'type' => 'directory',
					'permission' => '2550',
				),
				'typo3_src-core-version2' => array(
					'type' => 'directory',
					'permission' => '2550',
				),
			),
		);
		/*
		$this->createStructureObjectsFromDefinition($expectedStructure);

		checkStatus(); if exists && isPermissionCorrect, else kaputt
		isFixable(); check if root isWritable!! nicht: if exists && isPermissionCorrect || !exists && isWritable, else kaputt
		fix(); if !exists -> create, fixPermissions

		objects:
		 * directory
		 * root (extends directory, implements checkStatus, isFixable, fix())
		 * link
		 * file

		properties:
		 * name
		 * childs
		 * parent
		 * permissions
		 * target

		interface methods
		 * delete (dirs: delete recursive, file: rm, link: rm)
		 * create (dirs: mkdir, file: touch, link: ln -s to target)
		 * fixPermissions (dir / file: chmod depending on permission property, link: ignore)
		 *
		 * isWritable (dirs / file / link: is parent writable, root: must exist!)
		 * exists
		 * isPermissionCorrect
		 *
		 * setTarget (dir / file: ignore, link: set)
		 * setPermission (dir / file: ueberlegen, link: ignore)
		 * addChild (dir : add, file / link: ignore)
		 * getChilds (dir: return childs, file / link: return empty array)
		 * getParent (root: throw exception, dir / file / link: return parent object
		 *
		 * __construct($parent = NULL) (file / dir / link throw if NULL, root throws in !NULL)
		 */
	}


	/**
	 * Index action acts a a dispatcher to different steps
	 *
	 * @return string|boolean If string, rendered output of a step, FALSE if nothing needed
	 * @TODO: check if false as return is a good idea
	 */
	public function indexAction() {
		require_once __DIR__ . '/Step/StepInterface.php';

		// Require step classes if given
		foreach ($this->steps as $step) {
			if (!empty($step['file'])) {
				require_once $step['file'];
			}
		}

//		if isset _post (executeStep) -> hole step namen, instantiere, rufe exec()

		foreach ($this->steps as $step) {
			$stepObject = new $step['className'];
			if (!$stepObject instanceof Step\StepInterface) {
				throw new \BadMethodCallException('Step ' . $step['className'] . 'must implement StepInterface', 1365967344);
			}

			$stepContent = '';
			if ($stepObject->needsExecution()) {
				$stepContent = $stepObject->render();
			}
			$stepContent = $this->embedStepOutputInMainPage($stepContent);
			$this->output($stepContent);
		}
	}

	/**
	 * Fetch step template content and embed step content
	 *
	 * @param string $content Inner step content
	 * @return string
	 */
	protected function embedStepOutputInMainPage($content) {
		$mainPageContent = file_get_contents(__DIR__ . '/../../Resources/Private/Templates/StepInstaller/Main.html');

		$markerArray = array();
		$markerArray['HEAD_TITLE'] = 'TYPO3 ' . TYPO3_branch;
		$markerArray['CONTENT'] = $content;
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
		$content = '
			<p>
				<strong>TYPO3 CMS.</strong> Copyright &copy; 1998-' . date('Y') . '
				Kasper Sk&#229;rh&#248;j. Extensions are copyright of their respective
				owners. Go to <a href="' . TYPO3_URL_GENERAL . '">' . TYPO3_URL_GENERAL . '</a>
				for details. TYPO3 comes with ABSOLUTELY NO WARRANTY;
				<a href="' . TYPO3_URL_LICENSE . '">click</a> for details.
				This is free software, and you are welcome to redistribute it
				under certain conditions; <a href="' . TYPO3_URL_LICENSE . '">click</a>
				for details. Obstructing the appearance of this notice is prohibited by law.
			</p>
			<p>
				<a href="' . TYPO3_URL_DONATE . '"><strong>Donate</strong></a> |
				<a href="' . TYPO3_URL_ORG . '">TYPO3.org</a>
			</p>
		';
		return $content;
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
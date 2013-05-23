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
	 * Render this action
	 *
	 * @return string content
	 */
	protected function render() {
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
}
?>

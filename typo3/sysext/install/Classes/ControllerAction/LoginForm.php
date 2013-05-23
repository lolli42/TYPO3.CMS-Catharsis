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
 * Login action
 */
class LoginForm extends AbstractAction {

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
	 * @var \TYPO3\CMS\Install\Status\StatusInterface Optional status message from install tool controller
	 */
	protected $message = NULL;

	protected $formToken = '';

	/**
	 * Render this action
	 *
	 * @return string content
	 */
	public function render() {
		$viewRootPath = GeneralUtility::getFileAbsFileName('EXT:install/Resources/Private/');
		$this->view->setTemplatePathAndFilename($viewRootPath . 'Templates/ControllerAction/LoginForm.html');
		$this->view->setLayoutRootPath($viewRootPath . 'Layouts/');
		$this->view->setPartialRootPath($viewRootPath . 'Partials/');
		$this->view
			->assign('typo3Version', TYPO3_version)
			->assign('siteName', $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'])
			->assign('message', $this->message)
			->assign('formToken', $this->formToken)
		;
		return $this->view->render();
	}

	/**
	 * Login form only: Display a status message set by install tool controller
	 *
	 * @param \TYPO3\CMS\Install\Status\StatusInterface $message
	 */
	public function setMessage(\TYPO3\CMS\Install\Status\StatusInterface $message = NULL) {
		$this->message = $message;
	}

	/**
	 * Set form protection token
	 *
	 * @param string $token Form protection token
	 */
	public function setFormToken($token) {
		$this->formToken = $token;
	}
}
?>

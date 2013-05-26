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
 * Clean up page
 */
class CleanUp extends AbstractAction implements ActionInterface {

	/**
	 * Handle this action
	 *
	 * @return string content
	 */
	public function handle() {
		$this->initialize();

		$actionMessages = array();
		if (isset($this->postValues['set']['deleteCachedImageSizes'])) {
			$actionMessages[] = $this->deleteCachedImageSizes();
		}
		$this->view->assign('actionMessages', $actionMessages);


		$database = $this->getDatabase();
		$numberOfCachedImageSizes = intval($database->exec_SELECTcountRows('*', 'cache_imagesizes'));
		$this->view->assign('numberOfCachedImageSizes', $numberOfCachedImageSizes);

		return $this->view->render();
	}

	/**
	 * Truncate cache_imagesizes table
	 *
	 * @return \TYPO3\CMS\Install\Status\StatusInterface
	 */
	protected function deleteCachedImageSizes() {
		$database = $this->getDatabase();
		$database->exec_TRUNCATEquery('cache_imagesizes');
		$message = $this->objectManager->get('TYPO3\\CMS\\Install\\Status\\OkStatus');
		$message->setTitle('Cleared cached image sizes');
		return $message;
	}
}
?>

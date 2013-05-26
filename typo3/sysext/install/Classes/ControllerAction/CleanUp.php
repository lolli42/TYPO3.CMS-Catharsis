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
	 * Status messages of submitted actions
	 *
	 * @var array
	 */
	protected $actionMessages = array();

	/**
	 * Handle this action
	 *
	 * @return string content
	 */
	public function handle() {
		$this->initialize();

		if (isset($this->postValues['set']['deleteCachedImageSizes'])) {
			$this->actionMessages[] = $this->deleteCachedImageSizes();
		}
		if (isset($this->postValues['set']['resetBackendUserUc'])) {
			$this->actionMessages[] = $this->resetBackendUserUc();
		}

		$database = $this->getDatabase();
		$numberOfCachedImageSizes = intval($database->exec_SELECTcountRows('*', 'cache_imagesizes'));
		$this->view->assign('numberOfCachedImageSizes', $numberOfCachedImageSizes);

		$typo3TempData = $this->getTypo3TempStatistics();
		$this->view->assign('typo3TempData', $typo3TempData);

		$this->view->assign('actionMessages', $this->actionMessages);
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

	/**
	 * Reset uc field of all be_users to empty string
	 *
	 * @return \TYPO3\CMS\Install\Status\StatusInterface
	 */
	protected function resetBackendUserUc() {
		$database = $this->getDatabase();
		$database->exec_UPDATEquery('be_users', '', array('uc' => ''));
		$message = $this->objectManager->get('TYPO3\\CMS\\Install\\Status\\OkStatus');
		$message->setTitle('Reset all backend users preferences');
		return $message;
	}

	/**
	 * Data for the typo3temp/ deletion view
	 *
	 * @return array Data array
	 */
	protected function getTypo3TempStatistics() {
		$data = array();
		$pathTypo3Temp= PATH_site . 'typo3temp/';
		$postValues = $this->postValues['values'];

		$condition = '0';
		if (isset($postValues['condition'])) {
			$condition = $postValues['condition'];
		}
		$numberOfFilesToDelete = 0;
		if (isset($postValues['numberOfFiles'])) {
			$numberOfFilesToDelete = $postValues['numberOfFiles'];
		}
		$subDirectory = '';
		if (isset($postValues['subDirectory'])) {
			$subDirectory = $postValues['subDirectory'];
		}

		// Run through files
		$fileCounter = 0;
		$deleteCounter = 0;
		$criteriaMatch = 0;
		$timeMap = array('day' => 1, 'week' => 7, 'month' => 30);
		$directory = @dir($pathTypo3Temp . $subDirectory);
		if (is_object($directory)) {
			while ($entry = $directory->read()) {
				$absoluteFile = $pathTypo3Temp . $subDirectory . '/' . $entry;
				if (@is_file($absoluteFile)) {
					$ok = 0;
					$fileCounter++;
					if ($condition) {
						if (\TYPO3\CMS\Core\Utility\MathUtility::canBeInterpretedAsInteger($condition)) {
							if (filesize($absoluteFile) > $condition * 1024) {
								$ok = 1;
							}
						} else {
							if (fileatime($absoluteFile) < $GLOBALS['EXEC_TIME'] - intval($timeMap[$condition]) * 60 * 60 * 24) {
								$ok = 1;
							}
						}
					} else {
						$ok = 1;
					}
					if ($ok) {
						$hashPart = substr(basename($absoluteFile), -14, 10);
						// This is a kind of check that the file being deleted has a 10 char hash in it
						if (
							!preg_match('/[^a-f0-9]/', $hashPart)
							|| substr($absoluteFile, -6) === '.cache'
							|| substr($absoluteFile, -4) === '.tbl'
							|| substr($absoluteFile, -4) === '.css'
							|| substr($absoluteFile, -3) === '.js'
							|| substr($absoluteFile, -5) === '.gzip'
							|| substr(basename($absoluteFile), 0, 8) === 'installTool'
						) {
							if ($numberOfFilesToDelete && $deleteCounter < $numberOfFilesToDelete) {
								$deleteCounter++;
								unlink($absoluteFile);
							} else {
								$criteriaMatch++;
							}
						}
					}
				}
			}
			$directory->close();
		}
		$data['numberOfFilesMatchingCriteria'] = $criteriaMatch;
		$data['numberOfDeletedFiles'] = $deleteCounter;

		if ($deleteCounter > 0) {
			$message = $this->objectManager->get('TYPO3\\CMS\\Install\\Status\\OkStatus');
			$message->setTitle('Deleted ' . $deleteCounter . ' files from typo3temp/' . $subDirectory . '/');
			$this->actionMessages[] = $message;
		}

		$data['selectedCondition'] = $condition;
		$data['numberOfFiles'] = $numberOfFilesToDelete;
		$data['selectedSubDirectory'] = $subDirectory;

		// Set up sub directory data
		$data['subDirectories'] = array(
			'' => array(
				'name' => '',
				'filesNumber' => count(GeneralUtility::getFilesInDir($pathTypo3Temp)),
			),
		);
		$directories = dir($pathTypo3Temp);
		if (is_object($directories)) {
			while ($entry = $directories->read()) {
				if (is_dir($pathTypo3Temp . $entry) && $entry != '..' && $entry != '.') {
					$data['subDirectories'][$entry]['name'] = $entry;
					$data['subDirectories'][$entry]['filesNumber'] = count(GeneralUtility::getFilesInDir($pathTypo3Temp . $entry));
					$data['subDirectories'][$entry]['selected'] = FALSE;
					if ($entry === $data['selectedSubDirectory']) {
						$data['subDirectories'][$entry]['selected'] = TRUE;
					}
				}
			}
		}
		$data['numberOfFilesInSelectedDirectory'] = $data['subDirectories'][$data['selectedSubDirectory']]['filesNumber'];

		return $data;
	}
}
?>

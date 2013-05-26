<?php
namespace TYPO3\CMS\Install\Action;

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
 * Provides a tool cleaning up various tables in the database
 */
class CleanupManager extends AbstractAction {

	/**
	 * Handle cleanup action
	 *
	 * @return void
	 */
	public function handle() {
		$formValues = GeneralUtility::_GP('cleanupManager');

		$headCode = 'Clean up your TYPO3 installation';

		$this->message($headCode, 'typo3temp/ folder', '
			<p>
				TYPO3 uses this directory for temporary files, mainly processed
				and cached images.
				<br />
				The filenames are very cryptic; They are unique representations
				of the file properties made by md5-hashing a serialized array
				with information.
				<br />
				Anyway this directory may contain many thousand files and a lot
				of them may be of no use anymore.
			</p>
			<p>
				With this test you can delete the files in this folder. When you
				do that, you should also clear the cache database tables
				afterwards.
			</p>
		');
		// Run through files
		$fileCounter = 0;
		$deleteCounter = 0;
		$criteriaMatch = 0;
		$tmap = array('day' => 1, 'week' => 7, 'month' => 30);
		$tt = $formValues['typo3temp_delete'];
		$subdir = $formValues['typo3temp_subdir'];
		if (strlen($subdir) && !preg_match('/^[[:alnum:]_]+\\/$/', $subdir)) {
			die('subdir "' . $subdir . '" was not allowed!');
		}
		$action = $formValues['typo3temp_action'];
		$d = @dir(PATH_site . 'typo3temp/' . $subdir);
		if (is_object($d)) {
			while ($entry = $d->read()) {
				$theFile = PATH_site . 'typo3temp/' . $subdir . $entry;
				if (@is_file($theFile)) {
					$ok = 0;
					$fileCounter++;
					if ($tt) {
						if (\TYPO3\CMS\Core\Utility\MathUtility::canBeInterpretedAsInteger($tt)) {
							if (filesize($theFile) > $tt * 1024) {
								$ok = 1;
							}
						} else {
							if (fileatime($theFile) < $GLOBALS['EXEC_TIME'] - intval($tmap[$tt]) * 60 * 60 * 24) {
								$ok = 1;
							}
						}
					} else {
						$ok = 1;
					}
					if ($ok) {
						$hashPart = substr(basename($theFile), -14, 10);
						// This is a kind of check that the file being deleted has a 10 char hash in it
						if (!preg_match('/[^a-f0-9]/', $hashPart) || substr($theFile, -6) === '.cache' || substr($theFile, -4) === '.tbl' || substr(basename($theFile), 0, 8) === 'install_') {
							if ($action && $deleteCounter < $action) {
								$deleteCounter++;
								unlink($theFile);
							} else {
								$criteriaMatch++;
							}
						}
					}
				}
			}
			$d->close();
		}
		// Find sub-dirs:
		$subdirRegistry = array('' => '');
		$d = @dir(PATH_site . 'typo3temp/');
		if (is_object($d)) {
			while ($entry = $d->read()) {
				$theFile = $entry;
				if (@is_dir(PATH_site . 'typo3temp/' . $theFile) && $theFile != '..' && $theFile != '.') {
					$subdirRegistry[$theFile . '/'] = $theFile . '/ (Files: ' . count(GeneralUtility::getFilesInDir(PATH_site . 'typo3temp/' . $theFile)) . ')';
				}
			}
		}
		$deleteType = array(
			'0' => 'All',
			'day' => 'Last access more than a day ago',
			'week' => 'Last access more than a week ago',
			'month' => 'Last access more than a month ago',
			'10' => 'Filesize greater than 10KB',
			'50' => 'Filesize greater than 50KB',
			'100' => 'Filesize greater than 100KB'
		);
		$actionType = array(
			'0' => 'Don\'t delete, just display statistics',
			'100' => 'Delete 100',
			'500' => 'Delete 500',
			'1000' => 'Delete 1000'
		);
		// Get the template file
		$templateFile = @file_get_contents((PATH_site . $this->templateFilePath . 'Typo3TempManager.html'));
		// Get the template part from the file
		$template = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($templateFile, '###TEMPLATE###');
		// Get the subpart for 'Delete files by condition' dropdown
		$deleteOptionsSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($template, '###DELETEOPTIONS###');
		$deleteOptions = array();
		foreach ($deleteType as $deleteKey => $deleteValue) {
			// Define the markers content
			$deleteMarkers = array(
				'value' => htmlspecialchars($deleteKey),
				'selected' => !strcmp($deleteKey, $tt) ? 'selected="selected"' : '',
				'data' => htmlspecialchars($deleteValue)
			);
			// Fill the markers in the subpart
			$deleteOptions[] = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($deleteOptionsSubpart, $deleteMarkers, '###|###', TRUE, FALSE);
		}
		// Substitute the subpart for 'Delete files by condition' dropdown
		$content = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($template, '###DELETEOPTIONS###', implode(LF, $deleteOptions));
		// Get the subpart for 'Number of files at a time' dropdown
		$actionOptionsSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($template, '###ACTIONOPTIONS###');
		$actionOptions = array();
		foreach ($actionType as $actionKey => $actionValue) {
			// Define the markers content
			$actionMarkers = array(
				'value' => htmlspecialchars($actionKey),
				'data' => htmlspecialchars($actionValue)
			);
			// Fill the markers in the subpart
			$actionOptions[] = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($actionOptionsSubpart, $actionMarkers, '###|###', TRUE, FALSE);
		}
		// Substitute the subpart for 'Number of files at a time' dropdown
		$content = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($content, '###ACTIONOPTIONS###', implode(LF, $actionOptions));
		// Get the subpart for 'From sub-directory' dropdown
		$subDirectoryOptionsSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($template, '###SUBDIRECTORYOPTIONS###');
		$subDirectoryOptions = array();
		foreach ($subdirRegistry as $subDirectoryKey => $subDirectoryValue) {
			// Define the markers content
			$subDirectoryMarkers = array(
				'value' => htmlspecialchars($subDirectoryKey),
				'selected' => !strcmp($subDirectoryKey, $formValues['typo3temp_subdir']) ? 'selected="selected"' : '',
				'data' => htmlspecialchars($subDirectoryValue)
			);
			// Fill the markers in the subpart
			$subDirectoryOptions[] = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($subDirectoryOptionsSubpart, $subDirectoryMarkers, '###|###', TRUE, FALSE);
		}
		// Substitute the subpart for 'From sub-directory' dropdown
		$content = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($content, '###SUBDIRECTORYOPTIONS###', implode(LF, $subDirectoryOptions));
		// Define the markers content
		$markers = array(
			'numberTemporary' => 'Number of temporary files:',
			'numberMatching' => 'Number matching:',
			'numberDeleted' => 'Number deleted:',
			'temporary' => $fileCounter - $deleteCounter,
			'matching' => $criteriaMatch,
			'deleteType' => '<span>' . htmlspecialchars($deleteType[$tt]) . '</span>',
			'deleted' => $deleteCounter,
			'deleteCondition' => 'Delete files by condition',
			'numberFiles' => 'Number of files at a time:',
			'fromSubdirectory' => 'From sub-directory:',
			'execute' => 'Execute',
			'explanation' => '
				<p>
					This tool will delete files only if the last 10 characters
					before the extension (3 chars+\'.\') are hexadecimal valid
					ciphers, which are lowercase a-f and 0-9.
				</p>
			'
		);
		// Fill the markers
		$content = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($content, $markers, '###|###', TRUE, FALSE);
		// Add the content to the message array
		$this->message($headCode, 'Statistics', $content, 1);
	}

}
?>

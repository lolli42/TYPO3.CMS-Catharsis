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
 * Edit files in typo3conf/ folder
 */
class Typo3ConfEdit extends AbstractAction {

	public function handle() {
		$formValues = GeneralUtility::_GP('typo3ConfEdit');
		$EDIT_path = PATH_typo3conf;
		$headCode = 'Edit files in ' . basename($EDIT_path) . '/';
		$messages = '';
		if ($formValues['SAVE_FILE']) {
			$save_to_file = $formValues['FILE']['name'];
			if (@is_file($save_to_file)) {
				$save_to_file_md5 = md5($save_to_file);
				if (isset($formValues['FILE'][$save_to_file_md5]) && GeneralUtility::isFirstPartOfStr($save_to_file, $EDIT_path . '') && substr($save_to_file, -1) != '~' && !strstr($save_to_file, '_bak')) {
					$formValues['typo3conf_files'] = $save_to_file;
					$save_fileContent = $formValues['FILE'][$save_to_file_md5];
					if ($formValues['FILE']['win_to_unix_br']) {
						$save_fileContent = str_replace(CRLF, LF, $save_fileContent);
					}
					$backupFile = $this->getBackupFilename($save_to_file);
					if ($formValues['FILE']['backup']) {
						if (@is_file($backupFile)) {
							unlink($backupFile);
						}
						rename($save_to_file, $backupFile);
						$messages .= '
							Backup written to <strong>' . $backupFile . '</strong>
							<br />
						';
					}
					GeneralUtility::writeFile($save_to_file, $save_fileContent);
					$messages .= '
						File saved: <strong>' . $save_to_file . '</strong>
						<br />
						MD5-sum: ' . $formValues['FILE']['prevMD5'] . ' (prev)
						<br />
						MD5-sum: ' . md5($save_fileContent) . ' (new)
						<br />
					';
				}
			}
		}
		// Filelist:
		// Get the template file
		$templateFile = @file_get_contents((PATH_site . $this->templateFilePath . 'Typo3ConfEdit.html'));
		// Get the template part from the file
		$template = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($templateFile, '###TEMPLATE###');
		// Get the subpart for the files
		$filesSubpart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($template, '###FILES###');
		$files = array();
		$typo3conf_files = GeneralUtility::getFilesInDir($EDIT_path, '', 1, 1);
		$fileFound = 0;
		foreach ($typo3conf_files as $file) {
			if ($formValues['typo3conf_files'] && !strcmp($formValues['typo3conf_files'], $file)) {
				$fileFound = 1;
			}
			// Define the markers content for the files subpart
			$filesMarkers = array(
				'editUrl' => 'index.php?TYPO3_INSTALL[type]=typo3conf_edit&amp;typo3ConfEdit[typo3conf_files]=' . rawurlencode($file) . '#confEditFileList',
				'fileName' => basename($file),
				'fileSize' => GeneralUtility::formatSize(filesize($file)),
				'class' => $formValues['typo3conf_files'] && !strcmp($formValues['typo3conf_files'], $file) ? 'class="act"' : ''
			);
			// Fill the markers in the subpart
			$files[] = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($filesSubpart, $filesMarkers, '###|###', TRUE, FALSE);
		}
		$fileEditContent = '';
		if ($fileFound && @is_file($formValues['typo3conf_files'])) {
			$backupFile = $this->getBackupFilename($formValues['typo3conf_files']);
			$fileContent = GeneralUtility::getUrl($formValues['typo3conf_files']);
			// Get the subpart to edit the files
			$fileEditTemplate = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($template, '###FILEEDIT###');
			$allowFileEditOutsideTypo3ConfDirSubPart = '';
			$showSaveButtonSubPart = '';
			if (substr($formValues['typo3conf_files'], -1) != '~' && !strstr($formValues['typo3conf_files'], '_bak')) {
				// Get the subpart to show the save button
				$showSaveButtonSubPart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($fileEditTemplate, '###SHOWSAVEBUTTON###');
			}
			// Substitute the subpart for the save button
			$fileEditContent = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($fileEditTemplate, '###SHOWSAVEBUTTON###', $showSaveButtonSubPart);
			// Substitute the subpart to show if files are allowed outside the directory typo3conf
			$fileEditContent = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($fileEditContent, '###ALLOWFILEEDITOUTSIDETYPO3CONFDIR###', $allowFileEditOutsideTypo3ConfDirSubPart);
			// Define the markers content for subpart to edit the files
			$fileEditMarkers = array(
				'messages' => !empty($messages) ? '<p class="typo3-message message-warning">' . $messages . '</p>' : '',
				'action' => 'index.php?TYPO3_INSTALL[type]=typo3conf_edit#fileEditHeader',
				'saveFile' => 'Save file',
				'close' => 'Close',
				'llEditing' => 'Editing file:',
				'file' => $formValues['typo3conf_files'],
				'md5Sum' => 'MD5-sum: ' . md5($fileContent),
				'fileName' => $formValues['typo3conf_files'],
				'fileEditPath' => $formValues['FILE']['EDIT_path'],
				'filePreviousMd5' => md5($fileContent),
				'fileMd5' => md5($formValues['typo3conf_files']),
				'fileContent' => GeneralUtility::formatForTextarea($fileContent),
				'winToUnixBrChecked' => TYPO3_OS == 'WIN' ? '' : 'checked="checked"',
				'winToUnixBr' => 'Convert Windows linebreaks (13-10) to Unix (10)',
				'backupChecked' => @is_file($backupFile) ? 'checked="checked"' : '',
				'backup' => 'Make backup copy (rename to ' . basename($backupFile) . ')'
			);
			// Fill the markers in the subpart to edit the files
			$fileEditContent = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($fileEditContent, $fileEditMarkers, '###|###', TRUE, FALSE);
		}
		// Substitute the subpart to edit the file
		$fileListContent = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($template, '###FILEEDIT###', $fileEditContent);
		// Substitute the subpart for the files
		$fileListContent = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($fileListContent, '###FILES###', implode(LF, $files));
		// Define the markers content
		$fileListMarkers = array(
			'editPath' => '(' . $EDIT_path . ')',
		);
		// Fill the markers
		$fileListContent = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($fileListContent, $fileListMarkers, '###|###', TRUE, FALSE);
		// Add the content to the message array
		$this->message($headCode, 'Files in folder', $fileListContent);
	}

	/**
	 * Return the filename that will be used for the backup.
	 * It is important that backups of PHP files still stay as a PHP file, otherwise they could be viewed un-parsed in clear-text.
	 *
	 * @param string $filename Full path to a file
	 * @return string The name of the backup file (again, including the full path)
	 */
	protected function getBackupFilename($filename) {
		if (preg_match('/\\.php$/', $filename)) {
			$backupFile = str_replace('.php', '_bak.php', $filename);
		} else {
			$backupFile = $filename . '~';
		}
		return $backupFile;
	}
}
?>

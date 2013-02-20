<?php
if (!defined('TYPO3_MODE')) {
	die('Access denied.');
}

$extensionTcaPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($_EXTKEY) . 'Configuration/TCA/';

$GLOBALS['TCA']['sys_note'] = require_once($extensionTcaPath . 'sys_note.php');

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::allowTableOnStandardPages('sys_note');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('sys_note', 'EXT:sys_note/Resources/Private/Language/locallang_csh_sysnote.xlf');
?>
<?php
if (!defined('TYPO3_MODE')) {
	die('Access denied.');
}

$extensionTcaPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($_EXTKEY) . 'Configuration/TCA/';

$GLOBALS['TCA']['tx_rsaauth_keys'] = require_once($extensionTcaPath . 'tx_rsaauth_keys.php');
?>
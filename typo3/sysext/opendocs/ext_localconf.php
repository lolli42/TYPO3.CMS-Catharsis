<?php
if (TYPO3_MODE === 'BE') {
	$GLOBALS['TYPO3_CONF_VARS']['BE']['toolbarItems'][] = \TYPO3\CMS\Opendocs\Backend\ToolbarItems\OpendocsToolbarItem::class;
}

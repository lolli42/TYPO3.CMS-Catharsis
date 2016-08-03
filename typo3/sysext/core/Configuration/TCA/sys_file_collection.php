<?php
return array(
    'ctrl' => array(
        'title' => 'LLL:EXT:lang/locallang_tca.xlf:sys_file_collection',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'versioningWS' => true,
        'origUid' => 't3_origuid',
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'default_sortby' => 'ORDER BY crdate',
        'delete' => 'deleted',
        'type' => 'type',
        'typeicon_column' => 'type',
        'typeicon_classes' => array(
            'default' => 'apps-filetree-folder-media',
            'static' => 'apps-clipboard-images',
            'folder' => 'apps-filetree-folder-media'
        ),
        'requestUpdate' => 'storage',
        'enablecolumns' => array(
            'disabled' => 'hidden',
            'starttime' => 'starttime',
            'endtime' => 'endtime'
        ),
        'searchFields' => 'files,title'
    ),
    'interface' => array(
        'showRecordFieldList' => 'sys_language_uid,l10n_parent,l10n_diffsource,hidden,starttime,endtime,files,title'
    ),
    'columns' => array(
        't3ver_label' => array(
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.versionLabel',
            'config' => array(
                'type' => 'input',
                'size' => 30,
                'max' => 30
            )
        ),
        'sys_language_uid' => array(
            'exclude' => true,
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.language',
            'config' => array(
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'sys_language',
                'foreign_table_where' => 'ORDER BY sys_language.title',
                'items' => array(
                    array('LLL:EXT:lang/locallang_general.xlf:LGL.allLanguages', -1),
                    array('LLL:EXT:lang/locallang_general.xlf:LGL.default_value', 0)
                ),
                'default' => 0,
                'showIconTable' => true,
            )
        ),
        'l10n_parent' => array(
            'displayCond' => 'FIELD:sys_language_uid:>:0',
            'exclude' => true,
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.l18n_parent',
            'config' => array(
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => array(
                    array('', 0)
                ),
                'foreign_table' => 'sys_file_collection',
                'foreign_table_where' => 'AND sys_file_collection.pid=###CURRENT_PID### AND sys_file_collection.sys_language_uid IN (-1,0)'
            )
        ),
        'l10n_diffsource' => array(
            'config' => array(
                'type' => 'passthrough',
                'default' => ''
            )
        ),
        'hidden' => array(
            'exclude' => true,
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.hidden',
            'config' => array(
                'type' => 'check',
                'default' => 0
            )
        ),
        'starttime' => array(
            'exclude' => true,
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.starttime',
            'config' => array(
                'type' => 'input',
                'size' => 8,
                'max' => 20,
                'eval' => 'date',
                'default' => 0,
            )
        ),
        'endtime' => array(
            'exclude' => true,
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.endtime',
            'config' => array(
                'type' => 'input',
                'size' => 8,
                'max' => 20,
                'eval' => 'date',
                'default' => 0,
                'range' => array(
                    'upper' => mktime(0, 0, 0, 1, 1, 2038),
                )
            )
        ),
        'type' => array(
            'label' => 'LLL:EXT:lang/locallang_tca.xlf:sys_file_collection.type',
            'config' => array(
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => array(
                    array('LLL:EXT:lang/locallang_tca.xlf:sys_file_collection.type.0', 'static'),
                    array('LLL:EXT:lang/locallang_tca.xlf:sys_file_collection.type.1', 'folder'),
                    array('LLL:EXT:lang/locallang_tca.xlf:sys_file_collection.type.2', 'category')
                )
            )
        ),
        'files' => array(
            'label' => 'LLL:EXT:lang/locallang_tca.xlf:sys_file_collection.files',
            'config' => \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::getFileFieldTCAConfig('files')
        ),
        'title' => array(
            'label' => 'LLL:EXT:lang/locallang_tca.xlf:sys_file_collection.title',
            'config' => array(
                'type' => 'input',
                'size' => 30,
                'eval' => 'required'
            )
        ),
        'storage' => array(
            'label' => 'LLL:EXT:lang/locallang_tca.xlf:sys_file_collection.storage',
            'config' => array(
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => array(
                    array('', 0)
                ),
                'foreign_table' => 'sys_file_storage',
                'foreign_table_where' => 'ORDER BY sys_file_storage.name',
                'size' => 1,
                'minitems' => 0,
                'maxitems' => 1
            )
        ),
        'folder' => array(
            'label' => 'LLL:EXT:lang/locallang_tca.xlf:sys_file_collection.folder',
            'config' => array(
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => array(),
                'itemsProcFunc' => 'TYPO3\\CMS\\Core\\Resource\\Service\\UserFileMountService->renderTceformsSelectDropdown',
                'default' => '',
            )
        ),
        'recursive' => array(
            'label' => 'LLL:EXT:lang/locallang_tca.xlf:sys_file_collection.recursive',
            'config' => array(
                'type' => 'check',
                'default' => 0
            )
        ),
        'category' => array(
            'label' => 'LLL:EXT:lang/locallang_tca.xlf:sys_file_collection.category',
            'config' => array(
                'minitems' => 0,
                'maxitems' => 1,
                'type' => 'select',
                'renderType' => 'selectTree',
                'foreign_table' => 'sys_category',
                'foreign_table_where' => ' AND sys_category.sys_language_uid IN (-1,0) ORDER BY sys_category.sorting ASC',
                'treeConfig' => array(
                    'parentField' => 'parent',
                    'appearance' => array(
                        'expandAll' => true,
                        'showHeader' => true,
                    )
                )
            )
        )
    ),
    'types' => array(
        '0' => array(
            'showitem' => 'sys_language_uid, l10n_parent, l10n_diffsource, title, --palette--;;1, type, files',
        ),
        'static' => array(
            'showitem' => 'sys_language_uid, l10n_parent, l10n_diffsource, title, --palette--;;1, type, files',
        ),
        'folder' => array(
            'showitem' => 'sys_language_uid, l10n_parent, l10n_diffsource, title, --palette--;;1, type, storage, folder, recursive',
        ),
        'category' => array(
            'showitem' => 'sys_language_uid, l10n_parent, l10n_diffsource, title, --palette--;;1, type, category',
        ),
    ),
    'palettes' => array(
        '1' => array(
            'showitem' => 'hidden, starttime, endtime',
        ),
    ),
);

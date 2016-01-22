<?php
namespace TYPO3\CMS\Backend\Form\Container;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Lang\LanguageService;

/**
 * Render a given list of field of a TCA table.
 *
 * This is an entry container called from FormEngine to handle a
 * list of specific fields. Access rights are checked here and globalOption array
 * is prepared for further processing of single fields by PaletteAndSingleContainer.
 */
class ListOfFieldsContainer extends AbstractContainer
{
    /**
     * Entry method
     *
     * @return array As defined in initializeResultArray() of AbstractNode
     */
    public function render()
    {
        $table = $this->data['tableName'];
        $fieldListToRender = $this->data['fieldListToRender'];
        $recordTypeValue = $this->data['recordTypeValue'];

        // Load the description content for the table if requested
        if ($GLOBALS['TCA'][$table]['interface']['always_description']) {
            $languageService = $this->getLanguageService();
            $languageService->loadSingleTableDescription($table);
        }

        $fieldListToRender = array_unique(GeneralUtility::trimExplode(',', $fieldListToRender, true));

        $fieldsByShowitem = $this->data['processedTca']['types'][$recordTypeValue]['showitem'];
        $fieldsByShowitem = GeneralUtility::trimExplode(',', $fieldsByShowitem, true);

        $finalFieldsList = array();
        foreach ($fieldListToRender as $fieldName) {
            $found = false;
            foreach ($fieldsByShowitem as $fieldByShowitem) {
                $fieldByShowitemArray = $this->explodeSingleFieldShowItemConfiguration($fieldByShowitem);
                if ($fieldByShowitemArray['fieldName'] === $fieldName) {
                    $found = true;
                    $finalFieldsList[] = implode(';', $fieldByShowitemArray);
                    break;
                }
            }
            if (!$found) {
                // @todo: Field is shown even if it is not in type showitem list? Will be shown if field is in a palette.
                $finalFieldsList[] = $fieldName;
            }
        }

        $options = $this->data;
        $options['fieldsArray'] = $finalFieldsList;
        $options['renderType'] = 'paletteAndSingleContainer';
        return $this->nodeFactory->create($options)->render();
    }

    /**
     * @return LanguageService
     */
    protected function getLanguageService()
    {
        return $GLOBALS['LANG'];
    }
}

<?php
namespace TYPO3\CMS\Fluid\ViewHelpers\Be;

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

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * View helper which return page info icon as known from TYPO3 backend modules
 * Note: This view helper is experimental!
 *
 * = Examples =
 *
 * <code>
 * <f:be.pageInfo />
 * </code>
 * <output>
 * Page info icon with context menu
 * </output>
 */
class PageInfoViewHelper extends AbstractBackendViewHelper
{

    /**
     * This view helper renders HTML, thus output must not be escaped
     *
     * @var bool
     */
    protected $escapeOutput = false;

    /**
     * Render javascript in header
     *
     * @return string the rendered page info icon
     * @see \TYPO3\CMS\Backend\Template\DocumentTemplate::getPageInfo() Note: can't call this method as it's protected!
     */
    public function render()
    {
        return static::renderStatic(
            array(),
            $this->buildRenderChildrenClosure(),
            $this->renderingContext
        );
    }

    /**
     * @param array $arguments
     * @param \Closure $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     *
     * @return string
     */
    public static function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext)
    {
        $id = GeneralUtility::_GP('id');
        $pageRecord = BackendUtility::readPageAccess($id, $GLOBALS['BE_USER']->getPagePermsClause(1));
        // Add icon with clickmenu, etc:
        /** @var IconFactory $iconFactory */
        $iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        if ($pageRecord['uid']) {
            // If there IS a real page
            $altText = BackendUtility::getRecordIconAltText($pageRecord, 'pages');
            $theIcon = '<span title="' . $altText . '">' . $iconFactory->getIconForRecord('pages', $pageRecord, Icon::SIZE_SMALL)->render() . '</span>';
            // Make Icon:
            $theIcon = BackendUtility::wrapClickMenuOnIcon($theIcon, 'pages', $pageRecord['uid']);

            // Setting icon with clickmenu + uid
            $theIcon .= ' <em>[PID: ' . $pageRecord['uid'] . ']</em>';
        } else {
            // On root-level of page tree
            // Make Icon
            $theIcon = '<span title="' . htmlspecialchars($GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename']) . '">' . $iconFactory->getIcon('apps-pagetree-page-domain', Icon::SIZE_SMALL)->render() . '</span>';
            if ($GLOBALS['BE_USER']->user['admin']) {
                $theIcon = BackendUtility::wrapClickMenuOnIcon($theIcon, 'pages', 0);
            }
        }
        return $theIcon;
    }
}

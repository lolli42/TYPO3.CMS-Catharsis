<?php
namespace TYPO3\CMS\Belog\ViewHelpers;

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

use TYPO3\CMS\Belog\Domain\Repository\WorkspaceRepository;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContext;
use TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * Get workspace title from workspace id
 * @internal
 */
class WorkspaceTitleViewHelper extends AbstractViewHelper
{
    /**
     * First level cache of workspace titles
     *
     * @var array
     */
    protected static $workspaceTitleRuntimeCache = array();

    /**
     * Initializes the arguments
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('uid', 'int', 'UID of the workspace', true);
    }

    /**
     * Resolve workspace title from UID.
     *
     * @return string workspace title or UID
     */
    public function render()
    {
        return static::renderStatic(
            $this->arguments,
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
     * @throws \InvalidArgumentException
     */
    public static function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext)
    {
        if (!$renderingContext instanceof RenderingContext) {
            throw new \InvalidArgumentException('The given rendering context is not of type "TYPO3\CMS\Fluid\Core\Rendering\RenderingContext"', 1468363946);
        }

        $uid = $arguments['uid'];
        if (isset(static::$workspaceTitleRuntimeCache[$uid])) {
            return static::$workspaceTitleRuntimeCache[$uid];
        }

        if ($uid === 0) {
            static::$workspaceTitleRuntimeCache[$uid] = LocalizationUtility::translate(
                'live',
                $renderingContext->getControllerContext()->getRequest()->getControllerExtensionName()
            );
        } elseif (!ExtensionManagementUtility::isLoaded('workspaces')) {
            static::$workspaceTitleRuntimeCache[$uid] = '';
        } else {
            $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
            $workspaceRepository = $objectManager->get(WorkspaceRepository::class);
            /** @var \TYPO3\CMS\Belog\Domain\Model\Workspace $workspace */
            $workspace = $workspaceRepository->findByUid($uid);
            // $workspace may be null, force empty string in this case
            static::$workspaceTitleRuntimeCache[$uid] = $workspace === null ? '' : $workspace->getTitle();
        }

        return static::$workspaceTitleRuntimeCache[$uid];
    }
}

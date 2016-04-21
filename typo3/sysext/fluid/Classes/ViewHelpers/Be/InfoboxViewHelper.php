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
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * View helper for rendering a styled content infobox markup.
 *
 * = States =
 *
 * The Infobox provides different context sensitive states that
 * can be used to provide an additional visual feedback to the
 * to the user to underline the meaning of the information.
 *
 * Possible values are in range from -2 to 2. Please choose a
 * meaningful value from the following list.
 *
 * -2: Notices (Default)
 * -1: Information
 * 0: Positive feedback
 * 1: Warnings
 * 2: Error
 *
 * = Examples =
 *
 * <code title="Simple">
 * <f:be.infobox title="Message title">your box content</f:be.infobox>
 * </code>
 *
 * <code title="All options">
 * <f:be.infobox title="Message title" message="your box content" state="-2" iconName="check" disableIcon="TRUE" />
 * </code>
 *
 * @api
 */
class InfoboxViewHelper extends AbstractViewHelper
{
    const STATE_NOTICE = -2;
    const STATE_INFO = -1;
    const STATE_OK = 0;
    const STATE_WARNING = 1;
    const STATE_ERROR = 2;

    /**
     * As this ViewHelper renders HTML, the output must not be escaped.
     *
     * @var bool
     */
    protected $escapeOutput = false;

    /**
     * @param string $title The title of the info box
     * @param string $message The message of the info box, if NULL tag content is used
     * @param int $state The state of the box, InfoboxViewHelper::STATE_*
     * @param string $iconName The icon name from font awesome, NULL sets default icon
     * @param bool $disableIcon If set to TRUE, the icon is not rendered.
     *
     * @return string
     */
    public function render($title = null, $message = null, $state = self::STATE_NOTICE, $iconName = null, $disableIcon = false)
    {
        return static::renderStatic(
            [
                'title' => $title,
                'message' => $message,
                'state' => $state,
                'iconName' => $iconName,
                'disableIcon' => $disableIcon
            ],
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
        $title = $arguments['title'];
        $message = $arguments['message'];
        $state = $arguments['state'];
        $isInRange = MathUtility::isIntegerInRange($state, -2, 2);
        if (!$isInRange) {
            $state = -2;
        }

        $iconName = $arguments['iconName'];
        $disableIcon = $arguments['disableIcon'];
        if ($message === null) {
            $messageTemplate = $renderChildrenClosure();
        } else {
            $messageTemplate = htmlspecialchars($message);
        }
        $classes = [
            self::STATE_NOTICE => 'notice',
            self::STATE_INFO => 'info',
            self::STATE_OK => 'success',
            self::STATE_WARNING => 'warning',
            self::STATE_ERROR => 'danger'
        ];
        $icons = [
            self::STATE_NOTICE => 'lightbulb-o',
            self::STATE_INFO => 'info',
            self::STATE_OK => 'check',
            self::STATE_WARNING => 'exclamation',
            self::STATE_ERROR => 'times'
        ];
        $stateClass = $classes[$state];
        $icon = $icons[$state];
        if ($iconName !== null) {
            $icon = $iconName;
        }
        $iconTemplate = '';
        if (!$disableIcon) {
            $iconTemplate = '' .
                '<div class="media-left">' .
                    '<span class="fa-stack fa-lg callout-icon">' .
                        '<i class="fa fa-circle fa-stack-2x"></i>' .
                        '<i class="fa fa-' . htmlspecialchars($icon) . ' fa-stack-1x"></i>' .
                    '</span>' .
                '</div>';
        }
        $titleTemplate = '';
        if ($title !== null) {
            $titleTemplate = '<h4 class="callout-title">' . htmlspecialchars($title) . '</h4>';
        }
        return '<div class="callout callout-' . htmlspecialchars($stateClass) . '">' .
                '<div class="media">' .
                    $iconTemplate .
                    '<div class="media-body">' .
                        $titleTemplate .
                        '<div class="callout-body">' . $messageTemplate . '</div>' .
                    '</div>' .
                '</div>' .
            '</div>';
    }
}

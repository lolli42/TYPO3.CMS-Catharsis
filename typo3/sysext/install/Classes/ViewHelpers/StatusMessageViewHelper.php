<?php
namespace TYPO3\CMS\Install\ViewHelpers;

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

/**
 * Render a status message
 */
class StatusMessageViewHelper extends \TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper {

	/**
	 * Render a status message
	 *
	 * @param \TYPO3\CMS\Install\Status\StatusInterface $message The message to render
	 * @return string Rendered message html
	 */
	public function render(\TYPO3\CMS\Install\Status\StatusInterface $message) {
		$messageHtmlBoilerPlate =
			'<div class="typo3-message message-%1s" >' .
				'<div class="header-container">' .
					'<div class="message-header message-left"><strong>%2s</strong></div>' .
					'<div class="message-header message-right"></div>' .
				'</div>' .
				'<div class="message-body">%3s</div>' .
			'</div>' .
			'<p></p>';
		$html = sprintf(
			$messageHtmlBoilerPlate,
			$message->getSeverity(),
			$message->getTitle(),
			$message->getMessage()
		);
		return $html;
	}
}

?>

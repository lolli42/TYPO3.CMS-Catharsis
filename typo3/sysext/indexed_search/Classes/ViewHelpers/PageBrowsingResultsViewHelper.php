<?php
namespace TYPO3\CMS\IndexedSearch\ViewHelpers;

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

use TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3\CMS\Fluid\Core\ViewHelper\Facets\CompilableInterface;

/**
 * renders the header of the results page
 *
 * @author Benjamin Mack <benni@typo3.org>
 * @internal
 */
class PageBrowsingResultsViewHelper extends AbstractViewHelper implements CompilableInterface {

	/**
	 * main render function
	 *
	 * @param int $numberOfResults
	 * @param int $resultsPerPage
	 * @param int $currentPage
	 * @return string the content
	 */
	public function render($numberOfResults, $resultsPerPage, $currentPage = 1) {
		return self::renderStatic(
			array(
				'numberOfResults' => $numberOfResults,
				'resultsPerPage' => $resultsPerPage,
				'currentPage' => $currentPage,
			),
			$this->buildRenderChildrenClosure(),
			$this->renderingContext
		);
	}

	/**
	 * @param array $arguments
	 * @param callable $renderChildrenClosure
	 * @param RenderingContextInterface $renderingContext
	 *
	 * @return string
	 */
	static public function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext) {
		$numberOfResults = $arguments['numberOfResults'];
		$resultsPerPage = $arguments['resultsPerPage'];
		$currentPage = $arguments['currentPage'];

		$firstResultOnPage = $currentPage * $resultsPerPage + 1;
		$lastResultOnPage = $currentPage * $resultsPerPage + $resultsPerPage;
		$label = \TYPO3\CMS\Extbase\Utility\LocalizationUtility::translate('displayResults', 'indexed_search');
		return sprintf($label, $firstResultOnPage, min(array($numberOfResults, $lastResultOnPage)), $numberOfResults);
	}

}

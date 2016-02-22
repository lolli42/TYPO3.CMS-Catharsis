<?php
namespace TYPO3\CMS\Core\Tests\Acceptance\Backend\Search;

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

use TYPO3\CMS\Core\Tests\Acceptance\Step\Backend\Actor;

/**
 * Acceptance test
 */
class SearchAdminCest
{
    public function _before(Actor $I)
    {
        $I->loginAsAdmin();
    }

    public function _after(Actor $I)
    {
        $I->logout();
    }

    // tests
    public function tryToTest(\AcceptanceTester $I)
    {
        $liveSearchToolBarItem = '#typo3-cms-backend-backend-toolbaritems-livesearchtoolbaritem';
        $searchResultHeader = $liveSearchToolBarItem . ' > div > div > div:nth-child(1)';
        $I->wantTo('Search "admin" with auto completion');
        $I->fillField('#live-search-box', 'ad');
        $I->waitForElement($searchResultHeader);
        $dropdownHeader = $I->grabTextFrom($searchResultHeader);
        $I->assertEquals('Backend user', $dropdownHeader);
        $I->click($liveSearchToolBarItem . ' > div > div > div:nth-child(2) > a');
        $I->switchToIFrame("content");
        $I->waitForElement('#EditDocumentController');
        $I->see('Edit Backend user "admin" on root level');
        $I->switchToIFrame();
    }
}

<?php
namespace TYPO3\CMS\Core\Tests\Acceptance\Backend\Topbar;

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

use TYPO3\CMS\Core\Tests\Acceptance\Step\Backend\Admin;
use TYPO3\CMS\Core\Tests\Acceptance\Support\Helper\Topbar;

/**
 * Test the search module in the top bar
 */
class SearchCest
{
    /**
     * Selector for the module container in the topbar
     *
     * @var string
     */
    public static $topBarModuleSelector = '#typo3-cms-backend-backend-toolbaritems-livesearchtoolbaritem';

    /**
     * @param Admin $I
     */
    public function _before(Admin $I)
    {
        $I->useExistingSession();
    }

    /**
     * @param Admin $I
     */
    public function searchAndTestIfAutocompletionWorks(Admin $I)
    {
        $I->cantSeeElement(Topbar::$dropdownListSelector, self::$topBarModuleSelector);
        $I->fillField('#live-search-box', 'adm');
        $I->waitForElementVisible(Topbar::$dropdownListSelector, self::$topBarModuleSelector);

        $I->canSee('Backend user', self::$topBarModuleSelector);
        $I->click('.icon-status-user-admin', self::$topBarModuleSelector);

        $I->switchToIFrame("content");
        $I->waitForElementVisible('#EditDocumentController');
        $I->canSee('Edit Backend user "admin" on root level');
    }

    /**
     * @param Admin $I
     */
    public function searchForFancyTextAndCheckEmptyResultInfo(Admin $I)
    {
        $I->fillField('#live-search-box', 'Kasper = Jesus # joh316');
        $I->waitForElementVisible(Topbar::$dropdownListSelector, self::$topBarModuleSelector);

        // tod0: check why TYPO3 does not return a result for "Kasper" by itself
        $I->canSee('No results found.', self::$topBarModuleSelector);

        $I->click(self::$topBarModuleSelector . ' .close');
        $I->waitForElementNotVisible(Topbar::$dropdownListSelector, self::$topBarModuleSelector);
        $I->cantSeeInField('#live-search-box', '20160401256161');
    }

    /**
     * @param Admin $I
     */
    public function checkIfTheShowAllLinkPointsToTheListViewWithSearchResults(Admin $I)
    {
        $I->fillField('#live-search-box', 'fileadmin');
        $I->waitForElementVisible(Topbar::$dropdownListSelector, self::$topBarModuleSelector);

        $I->canSee('fileadmin/ (auto-created)', self::$topBarModuleSelector);
        $I->click('.t3js-live-search-show-all', self::$topBarModuleSelector);

        $I->switchToIFrame('content');
        $I->waitForElementVisible('form[name="dblistForm"]');
        $I->canSee('fileadmin/ (auto-created)');
    }
}

<?php
namespace TYPO3\CMS\Core\Tests\Acceptance\Backend\Page;

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


class InfoOnModuleCest
{
    public function _before(Actor $I)
    {
        $I->loginAsAdmin();
    }

    public function _after(Actor $I)
    {
        $I->logout();
    }

    /**
     * @env firefox
     * @env chrome
     * @param \AcceptanceTester $I
     */
    public function tryToTest(\AcceptanceTester $I)
    {
        $I->wantToTest('ino is ok when select page module');
        $I->click('Page');
        $I->switchToIFrame('content');
        $I->waitForElement('h4');
        $I->see('Web>Page module');
        $I->switchToIFrame();
    }
}
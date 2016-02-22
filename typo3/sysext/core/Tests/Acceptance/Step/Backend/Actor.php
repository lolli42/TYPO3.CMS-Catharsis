<?php
namespace TYPO3\CMS\Core\Tests\Acceptance\Step\Backend;

class Actor extends \AcceptanceTester
{
    public function loginAsAdmin()
    {
        $I = $this;
        $I->login('admin', 'password');
    }

    public function loginAsEditor()
    {
        $I = $this;
        $I->login('editor', 'password');
    }

    public function logout()
    {
        $I = $this;
        $I->amGoingTo('step backend login');
        $I->amGoingTo('logout');
        $I->click('#typo3-cms-backend-backend-toolbaritems-usertoolbaritem > a');
        $I->click('Logout');
        $I->waitForElement('#t3-username');
    }

    protected function login($username, $password)
    {
        $I = $this;
        $I->amGoingTo('Step\Backend\Login username: ' . $username);
        $I->amOnPage('/typo3/index.php');
        $I->waitForElement('#t3-username');
        $I->fillField('#t3-username', $username);
        $I->fillField('#t3-password', $password);
        $I->click('#t3-login-submit-section > button');
        $I->see('Verifying Login Data');
        $I->waitForElement('.nav');
        $I->seeCookie('be_lastLoginProvider');
        $I->seeCookie('be_typo_user');
    }
}

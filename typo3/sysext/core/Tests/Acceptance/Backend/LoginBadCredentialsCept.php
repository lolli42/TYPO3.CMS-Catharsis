<?php
/**
 * This is a file to test login with bad credentials.
 */

$I = new AcceptanceTester($scenario);
$I->wantTo('check login functions');
$I->amOnPage('/typo3/index.php');
$I->waitForElement('#t3-username');

$I->wantTo('check empty credentials');
$required = $I->executeInSelenium(function(\Facebook\WebDriver\Remote\RemoteWebDriver $webdriver) {
    return $webdriver->findElement(WebDriverBy::cssSelector('#t3-username'))->getAttribute('required');
});
$this->assertEquals('true', $required, '#t3-username');

$required = $I->executeInSelenium(function(\Facebook\WebDriver\Remote\RemoteWebDriver $webdriver) {
    return $webdriver->findElement(WebDriverBy::cssSelector('#t3-password'))->getAttribute('required');
});
$this->assertEquals('true', $required, '#t3-password');

$I->wantTo('use bad credentials');
$I->fillField('#t3-username', 'testify');
$I->fillField('#t3-password', '123456');
$I->click('#t3-login-submit-section > button');
$I->see('Verifying Login Data');
$I->waitForElement('#t3-login-error');
$I->see('Your login attempt did not succeed');

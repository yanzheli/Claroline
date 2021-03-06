<?php

namespace Claroline\CoreBundle\Controller;

use Claroline\CoreBundle\Library\Testing\FunctionalTestCase;
use Claroline\CoreBundle\Entity\User;

class AuthenticationControllerTest extends FunctionalTestCase
{
    protected function setUp()
    {
        parent::setUp();
        $this->loadPlatformRolesFixture();
        $this->client->followRedirects();
    }

    public function testLoginWithValidCredentialsDoesntReturnFailureMsg()
    {
        $this->loadUserData(array('user' => 'user'));
        $crawler = $this->logUser($this->getUser('user'));
        $this->assertEquals(0, $crawler->filter('#login-error')->count());
    }

    public function testLoginWithWrongCredentialsReturnsFailureMsg()
    {
        $unknownUser = new User();
        $unknownUser->setUsername('unknown_user');
        $unknownUser->setPlainPassword('bad_password');
        $crawler = $this->logUser($unknownUser);
        $this->assertEquals(1, $crawler->filter('#login-error')->count());
    }
}
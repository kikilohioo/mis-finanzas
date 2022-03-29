<?php

use Laravel\Lumen\Testing\DatabaseTransactions;

class AuthTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @test
     */
    function login()
    {
        $auth = $this->auth();

        $this->assertObjectHasAttribute('Token', $auth, 'Problemas al iniciar sesión');
    }
}

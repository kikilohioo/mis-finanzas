<?php

use App\Models\Usuario;
use Laravel\Lumen\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Creates the application.
     *
     * @return \Laravel\Lumen\Application
     */
    public function createApplication()
    {
        return require __DIR__.'/../bootstrap/app.php';
    }

    public function auth($attrs = [])
    {
        $faker = \Faker\Factory::create();
        $password = $faker->password;

        $usuario = Usuario::factory()->make([
            'Contrasenia' => md5($password),
        ]);

        $usuario->save();

        $this->post(route('auth.login'), [
            'IdUsuario' => $usuario->IdUsuario,
            'Contrasenna' => $password,
            'Administrador' => array_key_exists('isAdmin', $attrs) ? $attrs['isAdmin'] : null,
            'Gestion' => array_key_exists('isManager', $attrs) ? $attrs['isManager'] : null,
        ]);
        return json_decode($this->response->getContent());
    }
}

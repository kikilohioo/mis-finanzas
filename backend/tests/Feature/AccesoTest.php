<?php

use Laravel\Lumen\Testing\DatabaseTransactions;

class AccesoTest extends TestCase
{
	use DatabaseTransactions;

	/**
	 * @test
	 */
	function get_acceso()
	{
		$this->get(route('acceso.index'), [
			'X-FS-TOKEN' => $this->auth()->Token,
		]);
		$this->assertResponseStatus(200);
    }

    /**
     * @test
     */
    function create_acceso_as_admin()
    {
        $faker = \Faker\Factory::create();
        $name = $faker->name;
        $this->post(route('acceso.create'), [
            'Descripcion' => $name,
            'Asignable' => $faker->randomElement([true, false]),
            'Cantina' => $faker->randomElement([true, false]),
        ], [
            'X-FS-TOKEN' => $this->auth(['isAdmin' => true])->Token,
        ]);
        $this->assertResponseStatus(200);
        $this->assertStringContainsString($name, $this->response->getContent());
    }

    /**
     * @test
     */
    // function create_acceso_as_not_admin()
    // {
    //     $faker = \Faker\Factory::create();
    //     $name = $faker->name;
    //     $this->post(route('acceso.create'), [
    //         'Descripcion' => $name,
    //         'Asignable' => $faker->randomElement([true, false]),
    //         'Cantina' => $faker->randomElement([true, false]),
    //     ], [
    //         'X-FS-TOKEN' => $this->auth(['isAdmin' => false])->Token,
    //     ]);
    //     $this->assertResponseStatus(401);
    // }
}

<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UsuarioFactory extends Factory
{
    protected $model = \App\Models\Usuario::class;

    public function definition(): array
    {
    	return [
    	    'IdUsuario' => Str::slug($this->faker->word(), '.'),
            'Administrador' => true,
    	];
    }
}

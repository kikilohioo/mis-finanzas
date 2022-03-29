<?php

namespace App\Models;

class UsuarioRestaurarContrasena extends BaseModel {
    /**
     *
     * @var string
    */
    protected $table = 'UsuariosRestContrasena';

    /**
     * @var string
     */
    protected $primaryKey = 'Id';

    protected $casts = [
		'Id' => 'string',
        'Desde' => 'date',
        'Hasta' => 'date',
        'Usado' => 'boolean'
	];

    public function Usuario() {
        return $this->hasOne('App\Models\Usuario', 'IdUsuario', 'IdUsuario');
    }

    public $incrementing = false;
}
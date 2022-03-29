<?php

namespace App\Models;

class Sesion extends BaseModel
{
    /**
     * The table associated with the model.
     * 
     * @var string
     */
    protected $table = 'UsuariosSesiones';

    /**
     * The primary key associated with the table.
     * 
     * @var string
     */
    protected $primaryKey = 'IdSesion';

    protected $casts = [
        'Estado' => 'boolean',
        'ForzarCierre' => 'boolean',
    ];

    public function Usuario()
    {
        return $this->hasOne('App\Models\Usuario', 'IdUsuario', 'IdUsuario');
    }
}

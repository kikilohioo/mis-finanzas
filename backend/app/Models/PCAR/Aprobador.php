<?php

namespace App\Models\PCAR;

class Aprobador extends \App\Models\BaseModel
{
	protected $table = 'PCAR_Aprobadores';
	protected $primaryKey = 'IdUsuarioAprobador';
    protected $keyType = 'varchar';
    public $incrementing = false;

	/**
    * The attributes excluded from the model's JSON form.
    *
    * @var array
    */
    protected $hidden = [
        'FechaHora',
		'IdUsuario'
    ];

    protected $casts = [
        'IdUsuarioAprobador' => 'string',
    ];
	
    // protected $fillable = [
	// 	'Nombre'
	// ];
}

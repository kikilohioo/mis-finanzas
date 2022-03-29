<?php

namespace App\Models\PCAR;

class Area extends \App\Models\BaseModel
{
	protected $table = 'PCAR_Areas';
	protected $primaryKey = 'Id';

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
		'Id' => 'integer',
	];
	
    protected $fillable = [
		'Nombre'
	];
}

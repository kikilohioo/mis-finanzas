<?php

namespace App\Models;

class TiposAlojamiento extends BaseModel
{
	protected $table = 'TiposAlojamientos';
	
	protected $primaryKey = 'IdTipoAlojamiento';
	
	protected $casts = [
		'IdTipoAlojamiento' => 'integer',
		'Baja' => 'boolean',
		'RequiereDireccion' => 'boolean',
		'RequiereLocalidad' => 'boolean',
	];

	protected $hidden = [
		'rowguid'
	];
	
	protected $fillable = [
		'Nombre',
	];
	
	public $incrementing = false;
}

<?php

namespace App\Models;

class Alojamiento extends BaseModel
{
	protected $table = 'Alojamientos';
	
	protected $primaryKey = 'IdAlojamiento';
	
	protected $casts = [
		'IdAlojamiento' => 'integer',
		'IdTipoAlojamiento' => 'integer',
		'Plazas' => 'integer',
		'RequiereUnidad' => 'boolean',
		'Baja' => 'boolean',
	];

	protected $hidden = [
		'rowguid'
	];
	
	protected $fillable = [
		'Nombre',
		'Direccion',
		'Localidad',
		'Telefono',
		'Plazas',
	];
	
	public $incrementing = true;
}

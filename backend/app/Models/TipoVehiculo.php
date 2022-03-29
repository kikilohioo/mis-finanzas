<?php

namespace App\Models;

class TipoVehiculo extends BaseModel
{
	protected $table = 'TiposVehiculos';
	protected $primaryKey = 'IdTipoVehiculo';
	protected $casts = [
		'IdTipoVehiculo' => 'integer',
		'Baja' => 'boolean',
		'PGP' => 'boolean',
	];
	protected $fillable = [
		'Descripcion',
		'PGP',
	];
	public $incrementing = false;
}

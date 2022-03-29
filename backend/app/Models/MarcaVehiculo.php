<?php

namespace App\Models;

class MarcaVehiculo extends BaseModel
{
	protected $table = 'MarcasVehiculos';
	protected $primaryKey = 'IdMarcaVehic';
	protected $casts = [
		'IdMarcaVehic' => 'integer',
	];
	protected $fillable = [
		'Descripcion',
	];
	public $incrementing = false;
}

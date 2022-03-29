<?php

namespace App\Models;

class EstadoActividad extends BaseModel
{
	protected $table = 'EstadosActividad';
	protected $primaryKey = 'IdEstadoActividad';
	protected $casts = [
		'IdEstadoActividad' => 'integer',
		'Baja' => 'boolean',
		'Desactivar' => 'boolean',
		'Dias' => 'integer',
	];
	protected $fillable = [
		'Descripcion',
		'Accion',
		'Dias',
	];
	public $incrementing = false;
}

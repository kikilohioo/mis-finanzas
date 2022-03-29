<?php

namespace App\Models;

class TipoMaquina extends BaseModel
{
	protected $table = 'TiposMaquinas';
	protected $primaryKey = 'IdTipoMaquina';
	protected $casts = [
		'IdTipoMaquina' => 'integer',
		'Baja' => 'boolean',
	];
	protected $fillable = [
		'Descripcion',
	];
	public $incrementing = false;
}
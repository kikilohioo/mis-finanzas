<?php

namespace App\Models;

class TipoEquipo extends BaseModel
{
	protected $table = 'TiposEquipo';
	protected $primaryKey = 'IdTipoEquipo';
	protected $casts = [
		'IdTipoEquipo' => 'integer',
	];
	protected $fillable = [
		'Descripcion',
	];
	public $incrementing = false;
}
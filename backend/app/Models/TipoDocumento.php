<?php

namespace App\Models;

class TipoDocumento extends BaseModel
{
	protected $table = 'TiposDocumento';
	
	protected $primaryKey = 'IdTipoDocumento';

	protected $casts = [
		'IdTipoDocumento' => 'integer',
		'Extranjero' => 'boolean',
		'TipoPersona' => 'integer',
	];
	
	protected $fillable = [
		'Descripcion',
	];

	protected $hidden = [
		'rowguid'
	];

	public $incrementing = false;
}

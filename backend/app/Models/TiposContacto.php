<?php

namespace App\Models;

class TiposContacto extends BaseModel
{
	protected $table = 'TiposContacto';
	
	protected $primaryKey = 'IdTipoContacto';
	
	protected $casts = [
		'IdTipoContacto' => 'integer',
		'Baja' => 'boolean',
	];

	protected $hidden = [
		'rowguid'
	];
	
	protected $fillable = [
		'Nombre',
	];
	
	public $incrementing = false;
}

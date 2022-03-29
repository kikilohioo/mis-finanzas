<?php

namespace App\Models;

class Transportista extends BaseModel
{
	protected $table = 'Transportistas';
	
	protected $primaryKey = 'IdTransportista';
	
	protected $casts = [
		'IdTransportista' => 'integer',
		'Baja' => 'boolean',
	];

	protected $hidden = [
		'rowguid'
	];
	
	protected $fillable = [
		'Nombre',
	];
	
	public $incrementing = true;
}

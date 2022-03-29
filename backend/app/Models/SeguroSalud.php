<?php

namespace App\Models;

class SeguroSalud extends BaseModel
{
	protected $table = 'SegurosSalud';
	
	protected $primaryKey = 'IdSeguroSalud';
	
	protected $casts = [
		'IdSeguroSalud' => 'integer',
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

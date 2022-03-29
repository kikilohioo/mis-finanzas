<?php

namespace App\Models\PTC;

class EspacioConfinado extends \App\Models\BaseModel
{
	protected $table = 'PTCTanques';
	protected $primaryKey = 'IdTanque';
	protected $casts = [
		'IdTanque' => 'integer',
		'IdArea' => 'integer',
	];
	protected $fillable = [
		'Nombre',
		'IdArea',
	];
	public $incrementing = true;
}
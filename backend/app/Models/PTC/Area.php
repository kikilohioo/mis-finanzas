<?php

namespace App\Models\PTC;

class Area extends \App\Models\BaseModel
{
	protected $table = 'PTCAreas';
	protected $primaryKey = 'IdArea';
	protected $casts = [
		'IdArea' => 'integer',
	];
	protected $fillable = [
		'Nombre',
	];
	//public $incrementing = false;
}
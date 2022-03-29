<?php

namespace App\Models\Visitas;

class Area extends \App\Models\BaseModel
{
	protected $table = 'Visitas_Areas';
	protected $primaryKey = 'Id';
	protected $casts = [
		'Id' => 'integer',
	];
	protected $fillable = [
		'Nombre',
	];
	//public $incrementing = false;
}
<?php

namespace App\Models\PTC;

class PlanBloqueo extends \App\Models\BaseModel
{
	protected $table = 'PTCPlanesBloqueos';
	protected $primaryKey = 'IdPlanBloqueo';
	protected $casts = [
		'IdPlanBloqueo' => 'integer',
		'IdArea' => 'integer',
	];
	protected $fillable = [
		'Nombre',
		'AnhoPGP',
		'IdArea',
	];
	public $incrementing = false;
}
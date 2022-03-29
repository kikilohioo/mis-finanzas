<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class Concepto extends BaseModel
{
	protected $table = 'conceptos';
	protected $primaryKey = 'id';

	protected $casts = [
		'id' => 'integer',
		'nombre' => 'boolean',
		'tipo' => 'integer',
		'monto' => 'integer',
	];

    protected $guarded = [];
}

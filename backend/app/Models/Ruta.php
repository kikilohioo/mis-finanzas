<?php

namespace App\Models;

class Ruta extends BaseModel
{
	protected $table = 'TRANRutas';
	protected $primaryKey = 'idRuta';

	protected $casts = [
		'idRuta' => 'integer',
		'distancia' => 'integer',
		'cantPlazas' => 'integer',
        'baja' => 'boolean',
	];
	protected $fillable = [
		'descripcion',
	];
    
	public $incrementing = false;

	public function getName(): string
    {
        return sprintf('%s' , $this->idOrigDest);
    }
}
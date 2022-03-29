<?php

namespace App\Models;

class Laboratorio extends BaseModel
{
    public $incrementing = false;
	protected $table = 'Laboratorios';
    protected $primaryKey = 'IdLaboratorio';
    protected $fillable = [
        'Nombre',
    ];

}

<?php

namespace App\Models;

class OrigenDestino extends BaseModel
{
	protected $table = 'TRANOrigDest';
	
    protected $primaryKey = 'idOrigDest';
	
    protected $casts = [
		'idOrigDest' => 'integer',
		'baja' => 'boolean',
	];
    
    protected $fillable = [
        'nombre',
    ];

    public $incrementing = false;

    public function getName(): string
    {
        return sprintf('%s' , $this->idOrigDest);
    }
}
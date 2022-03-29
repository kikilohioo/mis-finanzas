<?php

namespace App\Models;

class Tarjeta extends BaseModel
{
	protected $table = 'Tarjetas';
	
    protected $primaryKey = 'CodigoZK';
	
    protected $casts = [
		'CodigoZK' => 'integer',
		'CodigoHY' => 'integer',
	];
    protected $hidden = [
		'rowguid'
    ];
    
    protected $fillable = [
        'CodigoHY',
        'CodigoPSION',
    ];
    public $incrementing = false;

    public function getName(): string
    {
        return sprintf('%s (%s)', $this->CodigoZk, implode('-', [$this->CodigoHY, $this->CodigoPSION]));
    }
}
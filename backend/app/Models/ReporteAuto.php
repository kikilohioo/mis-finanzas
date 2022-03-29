<?php

namespace App\Models;

class ReporteAuto extends BaseModel
{
	protected $table = 'ReportesAuto';
	
    protected $primaryKey = 'idReporte';
	
    protected $casts = [
		'idReporte' => 'integer',
		'lunes' => 'integer',
		'martes' => 'integer',
		'miercoles' => 'integer',
		'jueves' => 'integer',
		'viernes' => 'integer',
		'sabado' => 'integer',
		'domingo' => 'integer',
		'diaEjecucion' => 'integer',
		'estado' => 'boolean',
		'forzarEjecucion' => 'boolean',
        'baja' => 'boolean',
	];

    public $incrementing = false;

    public function mails() {
        return $this->hasMany('App\Models\ReporteAutoNotificacion', 'idReporte'); // belongsToMany('App\Models\ReporteAutoNotificacion', 'idMail', 'idReporte');
    }

    public function getName(): string
	{
		return  $this->descripcion;
	}
}
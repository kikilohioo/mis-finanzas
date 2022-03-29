<?php

namespace App\Models;

class ReporteAutoNotificacion extends BaseModel
{
	protected $table = 'ReportesAutoNotificaciones';

	protected $primaryKey = null;

	protected $casts = [
		'idMail' => 'integer',
		'idReporte' => 'integer',
	];

	protected $fillable = [
		'idMail',
		'idReporte',
		'mail',
	];

    public $incrementing = false;

	public $auditar = false;

    public function ReporteAutoMail() {
        return $this->belongsTo('App\Models\ReporteAuto', 'idReporte'); // belongsToMany('App\Models\ReporteAuto', 'idReporte', 'idMail');
    }
}

<?php

namespace App\Models;

class Equipo extends BaseModel
{
	protected $table = 'Equipos';
	protected $primaryKey = 'IdEquipo';
	protected $hidden = [
		'rowguid',
	];

	protected $casts = [
		'IdEquipo' => 'integer',
		'IdAcceso' => 'integer',
		'IdTipoEquipo' => 'integer',
		
		'Baja' => 'boolean',
		'InicioAuto' => 'boolean',
		'BorrarMarcas' => 'boolean',
		'Estado' => 'boolean',
	];
	
	protected $fillable = [
		'Nombre', 
		'IdTipoEquipo', 
		'DireccionIP', 
		'IdAcceso', 
		'Estado',
	];

	public $incrementing = false;
	
	public function Acceso()
	{
       return $this->hasOne('App\Models\Acceso', 'IdAcceso', 'IdAcceso');
    }
}

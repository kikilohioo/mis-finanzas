<?php

namespace App\Models;

class Pais extends BaseModel
{
	protected $table = 'Paises';
	
	protected $primaryKey = 'IdPais';
	
	protected $casts = [
		'IdPais' => 'integer',
		'Baja' => 'boolean',
	];

	protected $hidden = [
		'rowguid'
	];
	
	protected $fillable = [
		'Nombre',
	];
	
	public $incrementing = false;

	//Metodo para especificar que departamentos depende de Pais por el atributo IdPais(en ambas tablas)
	public function Departamentos()
    {
        return $this->hasMany(Departamento::class, 'IdPais', 'IdPais');
    }
}

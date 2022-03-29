<?php

namespace App\Models;

class Departamento extends BaseModel
{
	protected $table = 'Departamentos';
	
	protected $primaryKey = ['IdPais', 'IdDepartamento'];
	
	protected $casts = [
		'IdPais' => 'integer',
		'IdDepartamento' => 'integer',
	];

	protected $hidden = [
		'rowguid'
    ];
	
	protected $fillable = [
		'Nombre',
	];
	
	public $incrementing = false;

	public function getName(): string
	{
		return sprintf('%s (%s)', $this->Nombre, implode('-', [$this->IdPais, $this->IdDepartamento]));
	}

	public function getKeyAlt(): ?string
	{
		return implode('-', [$this->IdPais, $this->IdDepartamento]);
	}
}
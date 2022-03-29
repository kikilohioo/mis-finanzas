<?php

namespace App\Models;

class MatriculaBaja extends BaseModel
{
	protected $table = 'MatriculasBajas';
	
	protected $primaryKey = 'Matricula';
	
	protected $casts = [
		'Matricula' => 'integer',
	];

	protected $hidden = [
		'rowguid'
	];
	
	protected $fillable = [
		'Matricula',
		'Observaciones'
	];
	
	public $incrementing = false;

	public function getName(): string
    {
        return sprintf('%s (%s)', $this->Nombre, $this->IdTipoDocMaq);
    }
}

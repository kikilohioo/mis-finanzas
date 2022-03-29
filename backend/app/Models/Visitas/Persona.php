<?php

namespace App\Models\Visitas;

class Persona extends \App\Models\BaseModel
{
    
    const ESTADO_SOLICITADA = 'I';
    const ESTADO_AUTORIZADA = 'Z';
    const ESTADO_RECHAZADA = 'R';
    const ESTADO_VENCIDA = 'V';
    const ESTADO_CERRADA = 'C';
    const ESTADO_APROBADA = 'A';
    
	protected $table = 'Visitas_SolicitudesPersonas';
	protected $primaryKey = "Id";
	protected $casts = [
		'Id' => 'string',
	];

	protected $fillable = [
		'Documento',
        'Nombres',
        'Apellidos',
        'Email',
	];

	public $incrementing = false;

   /* public function getName(): string
	{
		return  'test';//$this->Id;
	}

    public function getKeyAlt(): ?string
	{
		return 'test2';//$this->Id;
	}*/

}
<?php

namespace App\Models\Visitas;

use App\Models\Usuario;
use App\Models\Empresa;

class Solicitud extends \App\Models\BaseModel
{
    
    const ESTADO_SOLICITADA = 'I';
    const ESTADO_AUTORIZADA = 'Z';
    const ESTADO_RECHAZADA = 'R';
    const ESTADO_VENCIDA = 'V';
    const ESTADO_CERRADA = 'C';
    const ESTADO_APROBADA = 'A';
    
	protected $table = 'Visitas_Solicitudes';
	protected $primaryKey = "Id";
	protected $casts = [
		'Id' => 'string',
		'FechaHoraDesde' => 'datetime:d/m/Y',
		'FechaHoraHasta' => 'datetime:d/m/Y',
	];

	protected $fillable = [
		'EmpresaVisitante',
        'PersonaContacto',
        'TelefonoContacto',
        'IdArea',
        'Motivo',
        'Observaciones',
        'TipoVisita',
	];

	protected $appends = [
		'Empresa'
	];

	public $incrementing = true;

    public function getName(): string
	{
		return  'test';//$this->Id;
	}

    public function getKeyAlt(): ?string
	{
		return 'test2';//$this->Id;
	}

	public function Solicitante()
    {
        return $this->hasOne(Usuario::class, 'IdUsuario', 'IdUsuario');
    }

	public function getEmpresaAttribute()
    {
		return Empresa::where('Documento', $this->DocEmpresa)->where('IdTipoDocumento', $this->TipoDocEmpresa)->first();
    }

	public function Personas()
    {
        return $this->hasMany(Visitante::class, 'IdSolicitud', 'Id');
    }

	public function Area()
    {
        return $this->hasOne(Area::class, 'Id', 'IdArea');
    }

	public static function getById($id) {

		return self::with(['Empresa', 'Solicitante', 'Personas', 'Area'])->find($id);

    }

}
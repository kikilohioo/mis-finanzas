<?php

namespace App\Models;

use App\Traits\Contratado;

class Maquina extends BaseModel
{
    use Contratado;

    protected $table = 'Maquinas';

    protected $primaryKey = 'NroSerie';

    protected $fillable = [
        "NroSerie",
        "IdTipoMaq",
        "IdMarcaMaq",
        "Modelo",
        "Propietario",
        "Conductor",
        "DocEmpresa",
        "TipoDocEmp",
        "IdCategoria",
        "IdUsuarioAlta",
        "EnTransito",
        "NroContratoCompra",
        "Matricula",
        "NotifEntrada",
        "NotifSalida",
        "EmailsEntrada",
        "EmailsSalida",
        "Observaciones"
    ];

    protected $casts = [
        'IdTipoMaq' => 'integer',
        'IdMarcaMaq' => 'integer',
        'Matricula' => 'integer',
        'TipoDocEmp' => 'integer',
        'IdCategoria' => 'integer',
        'Estado' => 'boolean',
        'Baja' => 'boolean',
        'NotifEntrada' => 'boolean',
        'NotifSalida' => 'boolean',
        'Aviso' => 'boolean',
        'EnTransito' => 'boolean',
    ];

    public static $castProperties = [
        'ControlLlegada' => 'boolean',
        'EnTransito' => 'boolean',
        'Estado' => 'boolean',
        'Baja' => 'boolean',
        'IdCategoria' => 'integer',
        'IdTipoMaq' => 'integer',
        'IdMarcaMaq' => 'integer',
        'TipoDocEmp' => 'integer',
        'Matricula' => 'integer',
        'VigenciaDesde' => 'datetime',
        'VigenciaHasta' => 'datetime',
        'Documentos' => [
            'NroDoc' => 'integer',
            'Id' => 'integer',
            'IdTipoDocMaq' => 'integer',
            'TieneVto' => 'boolean',
            'Vto' => 'date',
            'Obligatorio' => 'boolean',
        ],
        'Incidencias' => [
            'NroIncidencia' => 'integer',
            'IdTipoDocumento' => 'integer',
            'Fecha' => 'date',
        ],
        'Contratos' => [
            'FechaAltaContrato' => 'date',
            'FechaBajaContrato' => 'date',
        ],
    ];

    protected $hidden = [
		'rowguid'
    ];

    // protected $appends = [
    //     'Descripcion'
    // ];

    public $incrementing = false;

    // public function getDescripcionAttribute() {
    //     return sprintf('%s %s (%s)', $this->MarcaMaquina, $this->TipoMaquina, $this->NroSerie);
    // }

    public static function comprobarArgs($args)
    {
        return;
        // No Implementado.
    }

    public function TipoMaquina()
    {
        return $this->hasOne('App\Models\TipoMaquina', 'IdTipoMaquina', 'IdTipoMaq');
    }

    public function MarcaMaquina()
    {
        return $this->hasOne('App\Models\MarcaMaquina', 'IdMarcaMaq', 'IdMarcaMaq');
    }

    public function getName(): string
    {
        return sprintf('%s %s %s (%s)', $this->TipoMaquina->Descripcion, $this->MarcaMaquina->Descripcion, $this->Modelo, $this->NroSerie);
    }

    public static function id(object $args): string
    {
        return $args->NroSerie;
    }
}
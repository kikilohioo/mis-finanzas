<?php

namespace App\Models;

use App\Integrations\OnGuard;
use App\Traits\Contratado;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use App\FsUtils;

class Vehiculo extends BaseModel
{
    use Contratado;

    protected $table = 'Vehiculos';

    protected $primaryKey = ['Serie', 'Numero'];

    protected $fillable = [
        "IdTipoVehiculo",
        "IdMarcaVehic",
        "Modelo",
        "Propietario",
        "Conductor",
        "Matricula",
        "DocEmpresa",
        "TipoDocEmp",
        "IdCategoria",
        "EnTransito",
        "ControlLlegada",
        "IdOrigenDest",
        "TransportaMadera",
        "NotifEntrada",
        "NotifSalida",
        "EmailsEntrada",
        "EmailsSalida",
        "Tara",
        "Observaciones"
    ];

    protected $casts = [
        'IdTipoVehiculo' => 'integer',
        'IdMarcaVehic' => 'integer',
        'Matricula' => 'integer',
        'TipoDocEmp' => 'integer',
        'IdCategoria' => 'integer',
        'Estado' => 'boolean',
        'Baja' => 'boolean',
        'NotifEntrada' => 'boolean',
        'NotifSalida' => 'boolean',
        'Aviso' => 'boolean',
        'EnTransito' => 'boolean',
        'ControlLlegada' => 'boolean',
        'IdOrigDest' => 'integer',
        'TransportaMadera' => 'integer'
    ];

    public static $castProperties = [
        'ControlLlegada' => 'boolean',
        'EnTransito' => 'boolean',
        'Estado' => 'boolean',
        'Baja' => 'boolean',
        'IdCategoria' => 'integer',
        'IdTipoVehiculo' => 'integer',
        'IdMarcaVehic' => 'integer',
        'TipoDocEmp' => 'integer',
        'Matricula' => 'integer',
        'VigenciaDesde' => 'datetime',
        'VigenciaHasta' => 'datetime',
        'Documentos' => [
            'NroDoc' => 'integer',
            'Id' => 'integer',
            'IdTipoDocVehic' => 'integer',
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
    //     return sprintf('%s %s (%s)', $this->MarcaVehiculo->Descripcion, $this->TipoVehiculo->Descripcion, $this->Serie . $this->Numero);
    // }

    public static function comprobarArgs($args)
    {
        // No Implementado.
    }

    public function TipoVehiculo()
    {
        return $this->hasOne('App\Models\TipoVehiculo', 'IdTipoVehiculo', 'IdTipoVehiculo');
    }

    public function MarcaVehiculo()
    {
        return $this->hasOne('App\Models\MarcaVehiculo', 'IdMarcaVehic', 'IdMarcaVehic');
    }

    public function getKeyAlt(): string
    {
        return implode('', [$this->Serie, $this->Numero]);
    }

    public function getName(): string
    {
        return sprintf('%s %s %s (%s)', $this->TipoVehiculo->Descripcion, $this->MarcaVehiculo->Descripcion, $this->Modelo, $this->Serie . $this->Numero);
    }

    /**
     * @todo Probar Disponible TAG --
     */

    // PROBAR 
    public static function disponible($tag)
    {
        if (empty($tag)) {
            return true;
        } else {
            $results = DB::select(
                "SELECT * FROM Vehiculos WHERE TAG = ? AND Estado = 0",
                [$tag]
            );
            return count($results) === 0;
        }
    }

    public static function disponibilizarTag($tag)
    {
        if (!empty($tag)) {
            if (!self::disponible($tag)) {
                throw new ConflictHttpException('El TAG que está intentando utilizar no está disponible');
            }
        }
    }

    public static function obtenerMatriculaDesdeOnGuard(object &$Args)
    {
        $id = self::id($Args);

        $result = OnGuard::obtenerTarjetaEntidadLenel($id);
        
        Log::info('MATRICULA LENEL ' . $id, isset($result) ? (array)$result : ['NULL']);

        if (!empty($result)) {
            $vigenciaDesde = FsUtils::strToDate(substr($result->Activate, 0, -11), 'YmdHis');
            $vigenciaHasta = FsUtils::strToDate(substr($result->Deactivate, 0, -11), 'YmdHis');

            $Args->Matricula = $result->Id;
            // $Args->Estado = $tarjeta->Status;
            $Args->FechaVigenciaDesde = $vigenciaDesde->format(FsUtils::DDMMYY);
            $Args->HoraVigenciaDesde = $vigenciaDesde->format(FsUtils::HHMMSS);
            $Args->FechaVigenciaHasta = $vigenciaHasta->format(FsUtils::DDMMYY);
            $Args->HoraVigenciaHasta = $vigenciaHasta->format(FsUtils::HHMMSS);
        }
    }

    public static function id(object $args): string
    {
        return implode('', [trim($args->Serie), $args->Numero]);
    }
}
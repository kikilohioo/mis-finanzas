<?php

namespace App\Models\PTC;

class PTC extends \App\Models\BaseModel
{
    protected $table = 'PTC';
    protected $primaryKey = 'NroPTC';

    public const E_SOLICITADO = 'I';
    public const E_REVISION_SOLICITUD = 'RVS';
    public const E_INGRESADO = 'I';
    public const E_EN_CURSO = 'E';
    public const E_EN_PROCESO_LIBERACION = 'PL';
    public const E_LIBERADO = 'L';
    public const E_EN_EJECUCION = 'EJE';
    public const E_PENDIENTE_REVALIDACION = 'PR';
    public const E_REVALIDACION_SOLICITADA = 'RS';
    public const E_EJECUTADO = 'EJD'; // E_ESPERANDO_ACEPTACION_DE_CIERRE
    public const E_CERRADO_PARCIALMENTE = 'CP';
    public const E_CERRADO_POR_RESPONSABLE = 'CPR';
    public const E_CERRADO_SIN_EJECUCION = 'CSE';
    public const E_VENCIDO = 'V';

    protected $fillable = [
        'IdArea',
        'Descripcion',
        'UbicacionFuncional',
        'TelefonoContacto',
        'CantidadPersonas',
        'NroOT',
        'CierreTapa',
        'AperturaTapa',
        'PTCRiesgosOtroObs',
        'EquiposObs',
        'InspeccionNombre',
        'PlanBloqueoExistente',
        'RequiereBloqueoDoc',
        'PlanBloqueoObs',
        'PTCTiposOtroObs',
        'TagEquipo',
	];
    protected $casts = [
        'EsDePGP' => 'boolean',
        'Baja' => 'boolean',
        'CierreTapa' => 'boolean',
        'Baja' => 'boolean',
        'RequiereBloqueo' => 'boolean',
        'RequiereBloqueoEjecutado' => 'boolean',
        'RequiereInspeccion' => 'boolean',
        'RequiereInspeccionEjecutado' => 'boolean',
        'RequiereDrenarPurgar' => 'boolean',
        'RequiereDrenarPurgarEjecutado' => 'boolean',
        'RequiereLimpieza' => 'boolean',
        'RequiereLimpiezaEjecutado' => 'boolean',
        'RequiereMedicion' => 'boolean',
        'RequiereMedicionEjecutado' => 'boolean',
        'AceptaCondArea' => 'boolean',
        'InformaRiesgo' => 'boolean',
        'EjecutaTareasPTC' => 'boolean',
        'EsperandoPorPTCVinculado' => 'boolean',
        'PermitirUtilizarMediciones' => 'boolean',
    ];
    public $incrementing = true;

    public function getName(): string
    {
        return $this->NroPTC;
    }
}
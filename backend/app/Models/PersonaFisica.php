<?php

namespace App\Models;

use App\FsUtils;
use App\Traits\Contratado;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class PersonaFisica extends BaseModel
{
    use Contratado;

    public $incrementing = false;
	protected $table = 'PersonasFisicas';
    protected $primaryKey = ['Documento', 'IdTipoDocumento'];
    protected $fillable = [
        // 'FechaVtoDoc',
        'Payroll',
        'PrimerNombre',
        'SegundoNombre',
        'PrimerApellido',
        'SegundoApellido',
        'Sexo',
        'DocEmpresa',
        'TipoDocEmpresa',
        'IdSector',
        'Matricula',
        // 'VigenciaDesde',
        // 'VigenciaHasta',
        'IdPaisNac',
        'IdDepartamentoNac',
        // 'FechaNac',
        'Extranjero',
        // 'FechaDocRec',
        'FechaTarjLista',
        'FechaTarjEnt',
        'IdEstadoActividad',
        // 'FechaEstActividad',
        'NotifEntrada',
        'NotifSalida',
        'EmailsEntrada',
        'EmailsSalida',
        'Observaciones',
        'AdministraEquipos',

    ];
    protected $casts = [
        'Extranjero' => 'boolean',
        'Payroll' => 'boolean',
    ];
    protected $appends = [
        'NombreCompleto',
    ];

    public static $castProperties = [
        'Transito' => 'boolean',
        'Estado' => 'boolean',
        'IdTipoDocumento' => 'integer',
        'IdCategoria' => 'integer',
        'IdPais' => 'integer',
        'IdDepartamento' => 'integer',
        'IdPaisNac' => 'integer',
        'IdPaisOrigen' => 'integer',
        'IdDepartamentoNac' => 'integer',
        'IdEstadoActividad' => 'integer',
        'IdPaisTemp' => 'integer',
        'IdDepartamentoTemp' => 'integer',
        'Sexo' => 'integer',
        'FechaVtoDoc' => 'date',
        'VigenciaDesde' => 'date',
        'VigenciaHasta' => 'date',
        'FechaEstActividad' => 'date',
        'DocRecibida' => 'boolean', 
        'TarjetaLista' => 'boolean', 
        'TarjetaEnt' => 'boolean', 
        'TransporteDesdeAeropuerto' => 'boolean', 
        'CategoriaContratistaDisponible' => 'boolean', 
        'FechaDocRec' => 'date', 
        'FechaVuelo' => 'date', 
        'FechaArribo' => 'date', 
        'FechaRetorno' => 'date', 
        'FechaTarjLista' => 'date', 
        'FechaTarjEnt' => 'date',
        'Documentos' => [
            'Id' => 'integer',
            'IdTipoDocPF' => 'integer',
            'IdTipoDocumento' => 'integer',
            'Vto' => 'date',
        ],
        'Incidencias' => [
            'NroIncidencia' => 'integer',
            'IdTipoDocumento' => 'integer',
            'Fecha' => 'date',
        ],
        'Cargos' => [
            'IdCargo' => 'integer',
            'fechaDesde' => 'date',
            'fechaHasta' => 'date',
        ],
        'Empresas' => [
            'TipoDocEmpresa' => 'integer',
            'FechaAlta' => 'date',
            'FechaBaja' => 'date',
            'Contratos' => [
                'FechaAltaContrato' => 'date',
                'FechaBajaContrato' => 'date',
            ],
        ],
    ];

    public function getName(): string
    {
        return sprintf('%s (%s)', $this->NombreCompleto, implode('-', [$this->Documento, $this->IdTipoDocumento]));
    }

    public function getKeyAlt(): string
    {
        return implode('-', [$this->Documento, $this->IdTipoDocumento]);
    }

    public function getNombreCompletoAttribute(): string
    {
        return implode(' ', [$this->PrimerNombre, $this->SegundoNombre, $this->PrimerApellido, $this->SegundoApellido]);
    }

    public function setNombreCompletoAttribute(string $value)
    {
        $this->attributes['NombreCompleto'] = str_replace('  ', ' ', $value);
    }

    public static function comprobarArgs(&$Args)
    {
        if (!isset($Args->Sexo) || !in_array($Args->Sexo, ['1', '2'])) {
            throw new BadRequestHttpException("El campo 'Sexo' es requerido");
        }

        if (!empty($Args->VigenciaDesde) && !empty($Args->VigenciaHasta)) {
            if (FsUtils::fromHumanDate($Args->VigenciaDesde) > FsUtils::fromHumanDate($Args->VigenciaHasta)) {
                throw new BadRequestHttpException("'VigenciaHasta' no puede ser anterior a 'VigenciaDesde'");
            }
        }

        if (!empty($Args->Cargos)) {
            foreach ($Args->Cargos as $cargo) {
                $cargo = (object)$cargo;
                if (isset($cargo->fechaDesde) && isset($cargo->fechaHasta)) {
                    if (FsUtils::fromHumanDate($cargo->fechaDesde) > FsUtils::fromHumanDate($cargo->fechaHasta)) {
                        throw new BadRequestHttpException("El cargo '" . $cargo->Descripcion .  "' tiene una fecha de comienzo posterior a la fecha de fin");
                    }
                }
            }
        }

        if (!empty($Args->FechaDocRec) && FsUtils::fromHumanDate($Args->FechaDocRec) > new Carbon()) {
            throw new BadRequestHttpException("'Documentación recibida' no puede ser posterior al día de hoy");
        }

        if (!empty($Args->FechaDocRec) && !empty($Args->FechaTarjLista)) {
            if (FsUtils::fromHumanDate($Args->FechaDocRec) > FsUtils::fromHumanDate($Args->FechaTarjLista)) {
                throw new BadRequestHttpException("'Tarjeta lista para entregar' no puede ser anterior a 'Documentación recibida'");
            }
        }

        if (!empty($Args->FechaTarjLista) && !empty($Args->FechaTarjEnt)) {
            if (FsUtils::fromHumanDate($Args->FechaTarjLista) > FsUtils::fromHumanDate($Args->FechaTarjEnt)) {
                throw new BadRequestHttpException("'Tarjeta entregada' no puede ser anterior a 'Tarjeta lista para entregar'");
            }
        }

        if (!empty($Args->FechaDocRec) && !empty($Args->FechaTarjEnt)) {
            if (FsUtils::fromHumanDate($Args->FechaDocRec) > FsUtils::fromHumanDate($Args->FechaTarjEnt)) {
                throw new BadRequestHttpException("'Tarjeta entregada' no puede ser anterior a 'Documentación recibida'");
            }
        }

        $contratosCont = 0;
        if (!empty($Args->Empresas)) {
            $empresaHabilitada = null;
            $empresaMasVieja = null;
            $empresasHabilitadas = 0;
            $empresasCont = count($Args->Empresas);

            for ($i = 0; $i < $empresasCont; $i++) {
                $empresa = (object)$Args->Empresas[$i];
                for ($j = $i + 1; $j < $empresasCont; $j++) {
                    if (
                        $empresa->IdEmpresa == $Args->Empresas[$j]['IdEmpresa'] &&
                        $empresa->FechaAlta == $Args->Empresas[$j]['FechaAlta']
                    ) {
                        throw new BadRequestHttpException("La persona no puede tener dos períodos asignados a la misma empresa con la misma fecha de alta");
                    }
                }

                if (FsUtils::fromHumanDate($empresa->FechaAlta) > new Carbon()) {
                    throw new BadRequestHttpException("La asignación a la empresa '" . $empresa->Empresa .  "' tiene una 'Fecha alta' posterior al día de hoy");
                }

                // Chequeamos que la persona no tenga más de una empresa habilitada asignada
                if (FsUtils::fromHumanDate($empresa->FechaAlta) <= new Carbon() && (empty($empresa->FechaBaja) || FsUtils::fromHumanDate($empresa->FechaBaja) > new Carbon())) {
                    $contratosActivos = array_filter($empresa->Contratos, function ($contrato) {
                        return !empty($contrato['FechaAltaContrato'])
                            && FsUtils::fromHumanDate($contrato['FechaAltaContrato']) <= new Carbon()
                            && (empty($contrato['FechaBajaContrato']) || FsUtils::fromHumanDate($contrato['FechaBajaContrato']) > new Carbon());
                    });
                    // $contratosCont += count($empresa->Contratos);
                    $contratosCont += count($contratosActivos);
                    $empresaHabilitada = $empresa;
                    $empresasHabilitadas++;
                    if ($empresasHabilitadas > 1) {
                        throw new BadRequestHttpException("La persona no puede tener más de una empresa habilitada asignada");
                    }
                }

                if (!empty($empresa->FechaBaja) && FsUtils::fromHumanDate($empresa->FechaAlta) > FsUtils::fromHumanDate($empresa->FechaBaja)) {
                    throw new BadRequestHttpException("La asignación a la empresa '" . $empresa->Empresa .  "' tiene una 'Fecha baja' anterior a la 'Fecha alta'");
                }

                if ($empresaMasVieja == null || FsUtils::fromHumanDate($empresaMasVieja->FechaAlta) <= FsUtils::fromHumanDate($empresa->FechaAlta)) {
                    $empresaMasVieja = $empresa;
                }
            }

            if (!empty($empresaHabilitada)) {
                $IdEmpresaHabilitada = FsUtils::explodeId($empresaHabilitada->IdEmpresa);
                $Args->DocEmpresa = $IdEmpresaHabilitada[0];
                $Args->TipoDocEmpresa = $IdEmpresaHabilitada[1];
                $Args->IdSector = @$empresaHabilitada->IdSector;
            } else if (!empty($empresaMasVieja)) {
                $IdEmpresaMasVieja = FsUtils::explodeId($empresaMasVieja->IdEmpresa);
                $Args->DocEmpresa = $IdEmpresaMasVieja[0];
                $Args->TipoDocEmpresa = $IdEmpresaMasVieja[1];
            }

            if ((int)$contratosCont > 1) {
                throw new BadRequestHttpException("La persona no puede tener más de un contrato asignado");
            }
        }

        if (!empty($Args->IdEstadoActividad)) {
            $estadoActividad = DB::selectOne("SELECT TOP 1 Descripcion FROM EstadosActividad WHERE Accion = 'A' AND IdEstadoActividad = ?", [$Args->IdEstadoActividad]);
            if (!!$estadoActividad && empty($Args->FechaEstActividad)) {
                throw new BadRequestHttpException('Ingrese una fecha de hasta cuando se mantiene en estado: ' . $estadoActividad->Descripcion);
            }
        }
    }
}
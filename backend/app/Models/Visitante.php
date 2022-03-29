<?php

namespace App\Models;

use App\FsUtils;
use Carbon\Carbon;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Traits\Contratado;

class Visitante extends PersonaFisica
{
    use Contratado;
    
    protected $fillable = [
        // 'DocumentoMasked',
        // 'DocEmpresaVisit',
        // 'TipoDocEmpresaVisit',
        'PrimerNombre',
        'SegundoNombre',
        'PrimerApellido',
        'SegundoApellido',
        'IdSector',
        'Matricula',
        'EmpresaVisitante',
        'EmpresaSolicitante',
        'NombreVisitante',
        'CorreoSolicitante',
        'NombreEmpresa',
        // 'IdCategoria',
        // 'AccionRemotaToken',
        // 'AccionAprobador',
        'Archivo',
        'Archivo2',
        'Archivo3',
        'MotivoVisita',
        'VehiculoVisita',
        'NombrePersonaVisit',
        'Autorizante',
    ];

    protected $appends = [
        'NombreCompleto',
    ];

    public static $castProperties = [
        'VigenciaDesde' => 'datetime',
        'VigenciaHasta' => 'datetime',
        'Estado' => 'boolean',
        'Empresas' => [
            'TipoDocEmpresaVisit' => 'integer',
        ],
    ];

    public static function comprobarArgs(&$args)
    {
        if (isset($args->FechaVigenciaDesde)) {
            $fechaDesde = FsUtils::strToDateByPattern($args->FechaVigenciaDesde);
        }
        if (isset($args->FechaVigenciaHasta)) {
            $fechaHasta = FsUtils::strToDateByPattern($args->FechaVigenciaHasta);
        }

        if (isset($args->AltaPublica)) { // AltaPublica ????
            // Si es Categoria Visitante
            if ($args->IdCategoria == env('CONF_VISITANTE_CATEGORIA')) {
                $args->VigenciaDesde = FsUtils::fromHumanDatetime($args->FechaVigenciaDesde  . ' ' . $args->HoraVigenciaDesde . ':00');
                $args->VigenciaHasta = FsUtils::datetime_add($args->FechaVigenciaDesde, '3 days');
            }
            // Si es Categoria de Excepcion
            else if ($args->IdCategoria == env('CONF_VISITANTE_EXCEPCION_CATEGORIA')) {
                $args->VigenciaDesde = FsUtils::fromHumanDatetime($args->FechaVigenciaDesde  . ' ' . '00:00:00');
                $args->VigenciaHasta = FsUtils::fromHumanDatetime($args->FechaVigenciaHasta  . ' ' . '23:59:00');
                // $entity->VigenciaDesde = FsUtils::fromHumanDatetime($args->VigenciaDesde  . ' ' . $args->HoraVigenciaDesde . ':00');
                // $entity->VigenciaHasta = FsUtils::fromHumanDatetime($args->VigenciaHasta  . ' ' . $args->HoraVigenciaHasta . ':00');
                if (FsUtils::datetime_diff($args->FechaVigenciaDesde, $args->FechaVigenciaHasta, 'day') > 14) {
                    throw new ConflictHttpException("Una visita de categoría Excepción no puede tener una vigencia mayor a 14 días");
                }
            }
        }

        if (FsUtils::datetime_diff($fechaDesde, $fechaHasta, 'day') > 3) {
            throw new ConflictHttpException("Una visita no puede tener una vigencia mayor a 3 días");
        }

        if (!isset($args->Sexo) || !in_array($args->Sexo, ['1', '2'])) {
            throw new BadRequestHttpException("El campo 'Sexo' es requerido");
        }
    }
}
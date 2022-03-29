<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class Incidencia
{
    public static function loadByPersonaFisica($documento, $idTipoDocumento): array
    {
        return DB::select('SELECT * FROM PersonasFisicasIncidencias WHERE Documento = ? AND IdTipoDocumento = ? ORDER BY Fecha ASC', [$documento, $idTipoDocumento]);
    }

    public static function loadByVehiculo($serie, $numero): array
    {
        return DB::select('SELECT * FROM VehiculosIncidencias WHERE Serie = ? AND Numero = ? ORDER BY Fecha ASC', [$serie, $numero]);
    }

    public static function loadByMaquina($nroSerie): array
    {
        return DB::select('SELECT * FROM MaquinasIncidencias WHERE NroSerie = ? ORDER BY Fecha ASC', [$nroSerie]);
    }

    public static function createByMaquina(object $args, bool $reset = false)
    {
        if($reset) {
            DB::delete("DELETE FROM MaquinasIncidencias WHERE NroSerie = ? ", [$args->NroSerie]);
        }

        if(!empty($args->Incidencias)) {
            $nro = 0;
            foreach($args->Incidencias as $incidencia) {
                DB::insert(
                    "INSERT INTO MaquinasIncidencias (NroSerie, NroIncidencia, Fecha, Observaciones)VALUES (?, ?, ?, ?)",
                    [$args->NroSerie, $nro++, $incidencia->Fecha, $incidencia->Observaciones]
                );
            }
        }
    }

    public static function createByVehiculo(object $args, bool $reset = false)
    {
        if ($reset ) {
            DB::delete("DELETE FROM VehiculosIncidencias WHERE Serie = ? AND Numero = ?;", [$args->Serie, $args->Numero]);
        }

        if (!empty($args->Incidencias)) {
            $nro = 0;
            foreach ($args->Incidencias as $incidencia) {
                DB::insert(
                    "INSERT INTO VehiculosIncidencias (Serie, Numero, NroIncidencia, Fecha, Observaciones) VALUES (?, ?, ?, ?, ?)",
                    [$args->Serie, $args->Numero, $nro++, $incidencia->Fecha, $incidencia->Observaciones]
                );
            }
        }
    }

    public static function createByPersonaFisica(object $args, bool $reset = false)
    {
        if ($reset) {
            DB::delete('DELETE FROM PersonasFisicasIncidencias WHERE Documento = ? AND IdTipoDocumento = ?', [$args->Documento, $args->IdTipoDocumento]);
        }

        if (!empty($args->Incidencias)) {
            $nro = 0;
            foreach ($args->Incidencias as $incidencia) {
                DB::insert(
                    'INSERT INTO PersonasFisicasIncidencias (Documento, IdTipoDocumento, NroIncidencia, Fecha, Observaciones) VALUES (?, ?, ?, CONVERT(DATETIME, ?, 103), ?)',
                    [$args->Documento, $args->IdTipoDocumento, $nro++, $incidencia['Fecha'], $incidencia['Observaciones']]
                );
            }
        }
    }
}
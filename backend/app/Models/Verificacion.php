<?php

namespace App\Models;
use Illuminate\Support\Facades\DB;

class Verificacion
{
    public static function loadByMaquina($nroSerie): array
    {
        $entity_result = DB::select("SELECT * FROM MaquinasVerificaciones WHERE NroSerie = ? ORDER BY Fecha", [$nroSerie]);

        if (isset($entity_result)) {
            return $entity_result;
        }
        return [];
    }

    public static function loadByVehiculo($serie, $numero): array
    {
        $entity_result = DB::select("SELECT * FROM VehiculosVerificaciones WHERE Serie = ? AND Numero = ? ORDER BY Fecha", [$serie, $numero]);

        if (isset($entity_result)) {
            return $entity_result;
        }
        return [];
    }

    public static function createByMaquina(object $args, $reset = false) 
    {
        if ($reset) {
            DB::delete("DELETE FROM MaquinasVerificaciones WHERE NroSerie = ? ", [$args->NroSerie]);
        }

        if (!empty($args->Verificaciones)) {
            $nro = 0;
            foreach($args->Verificaciones as $verificacion) {
                DB::insert(
                    "INSERT INTO MaquinasVerificaciones (NroSerie, NroVerificacion, Fecha, Observaciones) VALUES (?, ?, ?, ?)",
                    [$args->NroSerie, $nro++, $verificacion['Fecha'], $verificacion['Observaciones']]
                );
            }
        }
    }

    public static function createByVehiculo(object $args, $reset = false) {
        if ($reset ) {
            DB::delete("DELETE FROM VehiculosVerificaciones WHERE Serie like ? AND Numero = ?;", [$args->Serie, $args->Numero]);
        }

        if (!empty($args->Verificaciones)) {
            $nro = 0;
            foreach ($args->Verificaciones as $verificacion) {
                DB::insert(
                    "INSERT INTO VehiculosIncidencias (Serie, Numero, NroIncidencia, Fecha, Observaciones) VALUES (?, ?, ?, ?, ?);",
                    [$args->Serie, $args->Numero, $nro++, $verificacion['Fecha'], $verificacion['Observaciones']]
                );
            }
        }
    }
}
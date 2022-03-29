<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class Capacitacion extends BaseModel
{
    protected $table = 'Capacitaciones';
    protected $primaryKey = 'IdCapacitacion';
    protected $casts = [
        'IdCapacitacion' => 'integer',
        'Baja' => 'boolean',
    ];
    protected $fillable = [
        'Descripcion',
    ];
    public $incrementing = false;

    public static function loadByPersonaFisica($documento, $idTipoDocumento): array
    {
        return DB::select("SELECT c.IdCapacitacion, c.Descripcion, c.FechaHora, pfc.FechaRealizada, pfc.DictadaPor, pfc.CargaHoraria, pfc.Nota, pfc.Observaciones, pfc.Archivo "
            . "FROM Capacitaciones c INNER JOIN PersonasFisicasCapacitaciones pfc ON c.IdCapacitacion = pfc.IdCapacitacion "
            . "WHERE c.Baja = 0 AND pfc.Documento = ? AND pfc.IdTipoDocumento = ? ORDER BY Descripcion ASC", [$documento, $idTipoDocumento]);
    }

    public static function loadByPersonaFisicaTransac($documento, $idTipoDocumento): array
    {
        return DB::select("SELECT c.IdCapacitacion, c.Descripcion, c.FechaHora, pfc.FechaRealizada, pfc.DictadaPor, pfc.CargaHoraria, pfc.Nota, pfc.Observaciones, pfc.Archivo "
            . "FROM Capacitaciones c INNER JOIN PersonasFisicasTransacCapacitaciones pfc ON c.IdCapacitacion = pfc.IdCapacitacion "
            . "WHERE c.Baja = 0 AND pfc.Documento = ? AND pfc.IdTipoDocumento = ? ORDER BY Descripcion ASC", [$documento, $idTipoDocumento]);
    }

    public static function createByPersonaFisica(object $args, $reset = false, $modifier = '')
    {
        if ($reset) {
            DB::delete('DELETE FROM PersonasFisicas' . $modifier . 'Capacitaciones WHERE Documento = ? AND IdTipoDocumento = ?', [$args->Documento, $args->IdTipoDocumento]);
        }

        if (!empty($args->Capacitaciones)) {
            $nro = 0;
            foreach ($args->Capacitaciones as $capacitacion) {
                DB::insert(
                    'INSERT INTO PersonasFisicas' . $modifier . 'Capacitaciones '
                    . '(Documento, IdTipoDocumento, NroCapacitacion, IdCapacitacion, FechaRealizada, DictadaPor, CargaHoraria, Nota, Observaciones, Archivo) '
                    . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        $args->Documento,
                        $args->IdTipoDocumento,
                        $nro++,
                        $capacitacion['IdCapacitacion'],
                        $capacitacion['FechaRealizada'],
                        $capacitacion['DictadaPor'],
                        $capacitacion['CargaHoraria'],
                        $capacitacion['Nota'],
                        $capacitacion['Observaciones'],
                        $capacitacion['Archivo'],
                    ]
                );
            }
        }
    }
}

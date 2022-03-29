<?php

namespace App\Models;

use App\FsUtils;
use Illuminate\Support\Facades\DB;

class Cargo extends BaseModel
{
    protected $table = 'Cargos';
    protected $primaryKey = 'IdCargo';
    protected $casts = [
        'IdCargo' => 'integer',
        'Baja' => 'boolean',
    ];
    protected $fillable = [
        'Descripcion',
    ];
    public $incrementing = false;

    public static function loadByPersonaFisica($documento, $idTipoDocumento): array
    {
        return DB::select("SELECT c.IdCargo, c.Descripcion, CONVERT(varchar(10), pfc.FechaDesde, 103) AS FechaDesde, CONVERT(varchar(10), pfc.FechaHasta, 103) AS FechaHasta, pfc.Observaciones "
            . "FROM Cargos c INNER JOIN PersonasFisicasCargos pfc ON c.IdCargo = pfc.IdCargo "
            . "WHERE pfc.Documento = ? AND pfc.IdTipoDocumento = ? ORDER BY Descripcion ASC", [$documento, $idTipoDocumento]);
    }

    public static function loadByPersonaFisicaTransac($documento, $idTipoDocumento): array
    {
        return DB::select("SELECT c.IdCargo, c.Descripcion, CONVERT(varchar(10), pfc.FechaDesde, 103) AS FechaDesde, CONVERT(varchar(10), pfc.FechaHasta, 103) AS FechaHasta, pfc.Observaciones "
            . "FROM Cargos c INNER JOIN PersonasFisicasTransacCargos pfc ON c.IdCargo = pfc.IdCargo "
            . "WHERE pfc.Documento = ? AND pfc.IdTipoDocumento = ? ORDER BY Descripcion ASC", [$documento, $idTipoDocumento]);
    }

    public static function createByPersonaFisica(object $args, $reset = true, $modifier = '')
    {
        if ($reset) {
            DB::delete('DELETE FROM PersonasFisicas' . $modifier . 'Cargos WHERE Documento = ? AND IdTipoDocumento = ?', [$args->Documento, $args->IdTipoDocumento]);
        }

        if (!empty($args->Cargos)) {
            $nro = 0;
            foreach ($args->Cargos as $cargo) {
                DB::insert(
                    'INSERT INTO PersonasFisicas' . $modifier . 'Cargos '
                        . '(Documento, IdTipoDocumento, NroCargo, IdCargo, FechaDesde, FechaHasta, Observaciones) '
                        . 'VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$args->Documento, $args->IdTipoDocumento, $nro++, $cargo['IdCargo'], 
                    isset($cargo['FechaDesde']) ? FsUtils::fromHumanDate($cargo['FechaDesde']) : null, 
                    isset($cargo['FechaHasta']) ? FsUtils::fromHumanDate($cargo['FechaHasta']) : null, @$cargo['Observaciones']]
                );
            }
        }
    }
}

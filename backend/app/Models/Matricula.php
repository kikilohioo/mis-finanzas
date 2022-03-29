<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class Matricula
{
    public static function disponible($matricula)
    {
        if (empty($matricula)) {
            return true;
        } else {
            DB::transaction(function () use ($matricula) {
                DB::update('UPDATE pf SET pf.Matricula = NULL FROM PersonasFisicas AS pf
                    INNER JOIN Personas AS p ON pf.IdTipoDocumento = p.IdTipoDocumento AND pf.Documento = p.Documento
                    WHERE pf.Matricula = ? AND p.Baja = 1', [$matricula]);
                DB::update('UPDATE Maquinas SET Matricula = NULL WHERE Matricula = ? AND Baja = 1', [$matricula]);
                DB::update('UPDATE Vehiculos SET Matricula = NULL WHERE Matricula = ? AND Baja = 1', [$matricula]);
            });
            
            $results = DB::select(
                'SELECT Matricula FROM PersonasFisicas WHERE Matricula = ? '
                    . 'UNION ALL SELECT Matricula FROM Maquinas WHERE Matricula = ? '
                    . 'UNION ALL SELECT Matricula FROM Vehiculos WHERE Matricula = ? '
                    . 'UNION ALL SELECT Matricula FROM MatriculasBajas WHERE Matricula =  ? ',
                [$matricula, $matricula, $matricula, $matricula]
            );

            return count($results) === 0;
        }
    }

    public static function disponibilizar($matricula)
    {
        if (!empty($matricula)) {
            if (!self::disponible($matricula)) {
                DB::update('UPDATE PersonasFisicas SET Estado = 0, Matricula = NULL WHERE Transito = 1 AND Matricula = ?', [$matricula]);
                if (!self::disponible($matricula)) {
                    throw new ConflictHttpException('La matrícula que está intentando utilizar no está disponible');
                }
            }
        }
    }
}

<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class Acceso extends BaseModel
{
	protected $table = 'Accesos';
	protected $primaryKey = 'IdAcceso';

	/**
    * The attributes excluded from the model's JSON form.
    *
    * @var array
    */
    protected $hidden = [
		'rowguid',
        'pivot',
    ];

	protected $casts = [
		'IdAcceso' => 'integer',
		'Baja' => 'boolean',
		'Asignable' => 'boolean',
		'Cantina' => 'boolean',
	];

	protected $fillable = [
		'Descripcion', 'Cantina', 'Asignable',
	];

	public function Categoria() {
        return $this->belongsToMany('App\Models\Categoria', 'CategoriasAccesos', 'IdCategoria', 'IdAcceso')->using('App\Models\CategoriaAcceso');
    }

	public $incrementing = false;

    public static function loadByPersonaFisica($documento, $idTipoDocumento): array
    {
        return self::query()
            ->join(DB::raw('PersonasFisicasAccesos pfa'), 'Accesos.IdAcceso', '=', 'pfa.IdAcceso')
            ->where('Documento', $documento)
            ->where('IdTipoDocumento', $idTipoDocumento)
            ->get()
            ->toArray();
    }

    public static function loadByVisitante($documento, $idTipoDocumento, $tokenAccionRemota): array
    {
        return DB::select(
            "SELECT a.IdAcceso, a.Descripcion
            FROM PersonasFisicasTransacAccesos pfta
            INNER JOIN PersonasFisicasTransac pft ON pft.Documento = pfta.Documento AND pft.IdTipoDocumento = pfta.IdTipoDocumento AND pft.AccionFechaHora = pfta.AccionFechaHora
            INNER JOIN Accesos a ON pfta.IdAcceso = a.IdAcceso
            WHERE pfta.Documento = ?
            AND pfta.IdTipoDocumento = ?
            AND pft.AccionRemotaToken = ?",
            [$documento, $idTipoDocumento, $tokenAccionRemota]
        );
    }

    public static function loadByMaquina($nroSerie): array
    {
        return self::query()
            ->join(DB::raw('MaquinasAccesos maa'), 'Accesos.IdAcceso', '=', 'maa.IdAcceso')
            ->where('NroSerie', $nroSerie)
            ->get()
            ->toArray();
    }

    public static function loadByVehiculo($serie, $numero): array
    {
        return self::query()
            ->join(DB::raw('VehiculosAccesos vea'), 'Accesos.IdAcceso', '=', 'vea.IdAcceso')
            ->where('serie', $serie)
            ->where('numero', $numero)
            ->get()
            ->toArray();
    }

    public static function createByMaquina(object $args, $reset = false)
    {
        if($reset) {
            DB::delete("DELETE FROM MaquinasAccesos WHERE NroSerie = ? ", [$args->NroSerie]);
        }

        if(!empty($args->Accesos)) {
            foreach($args->Accesos as $acceso) {
                DB::insert(
                    "INSERT INTO MaquinasAccesos (NroSerie, IdAcceso) VALUES (?, ?)",
                    [$args->NroSerie, $acceso['IdAcceso']]
                );
            }
        }
    }

    public static function createByVehiculo(object $args, $reset = false) {
        if ($reset) {
            DB::delete(
                "DELETE FROM VehiculosAccesos WHERE Serie = ? AND Numero = ?;",
                [$args->Serie, $args->Numero]
            );
        }

        if (!empty($args->Accesos)) {
            foreach ($args->Accesos as $acceso) {
                DB::insert(
                    "INSERT INTO VehiculosAccesos (Serie, Numero, IdAcceso) VALUES (?, ?, ?)",
                    [$args->Serie, $args->Numero, $acceso['IdAcceso']]
                );
            }
        }
    }

    public static function createByPersonaFisica(object $args, $reset = false)
    {
        if ($reset) {
            DB::delete('DELETE FROM PersonasFisicasAccesos WHERE Documento = ? AND IdTipoDocumento = ?', [$args->Documento, $args->IdTipoDocumento]);
        }

        if (!empty($args->Accesos)) {
            foreach ($args->Accesos as $acceso) {
                $idAcceso = is_numeric($acceso) ? $acceso : $acceso['IdAcceso'];
                DB::insert(
                    'INSERT INTO PersonasFisicasAccesos (IdTipoDocumento, Documento, IdAcceso) VALUES (?, ?, ?)',
                    [$args->IdTipoDocumento, $args->Documento, $idAcceso]
                );
            }
        }
    }
}

<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class Categoria extends BaseModel
{
    protected $table = 'Categorias';
    protected $primaryKey = 'IdCategoria';
    protected $hidden = [
        'rowguid',
    ];
    protected $casts = [
        'IdCategoria' => 'integer',
        'PF' => 'boolean',
        'PJ' => 'boolean',
        'MAQ' => 'boolean',
        'VEH' => 'boolean',
		'VIS' => 'boolean',
		'Baja' => 'boolean',
        'Sige' => 'boolean',
        'SincSIGE' => 'boolean',
        'SincLenel' => 'boolean',
        'RequiereContratoActivar' => 'boolean',
        'ContratistaDisponible' => 'boolean',
        'NoAsociaTarjeta' => 'boolean',
        'CatLenel' => 'integer',
        'MatNoObligatoria' => 'integer',
        'IdDisenhoMatricula' => 'integer',
	];

	protected $fillable = [
		'Descripcion',
        'PF',
        'PJ',
        'MAQ',
        'VEH',
		'VIS',
        'Sige',
        'SincLenel',
        'RequiereContratoActivar',
        'ContratistaDisponible',
        'NoAsociaTarjeta',
        'CatLenel',
        'MatNoObligatoria',
        'IdDisenhoMatricula',
	];

    protected $appends = [
        'SincSIGE'
    ];

    public $incrementing = false;

    public function setSincSigeAttribute($SincSIGE) {
        $this->Sige = $SincSIGE;
    }

    public function getSincSigeAttribute()
    {
        return $this->Sige;
    }

    public function Accesos() {
        return $this->belongsToMany('App\Models\Acceso', 'CategoriasAccesos', 'IdCategoria', 'IdAcceso');
    }

    /**
     * Comprueba si la categoría gestiona la matricula de la entidad en FSAcceso.
     * @param int $idCategoria
     * @return bool
     */
    public static function gestionaMatriculaEnFSA(int $idCategoria): bool
    {
        if (!empty($idCategoria)) {
            $result = DB::selectOne('SELECT NoAsociaTarjeta FROM Categorias WHERE IdCategoria = ?', [$idCategoria]);
            return empty($result->NoAsociaTarjeta);
        }
        return false;
    }

    /**
     * Indica si la categoría sincroniza la entidad con Lenel OnGuard.
     * @param int $idCategoria
     * @return bool
     */
    public static function sincConOnGuard(int $idCategoria): bool
    {
        return true; // env('INTEGRADO', 'false') === true;
    }

    /**
     * Indica si la categoría sincroniza la entidad con SIGE.
     * @param int $idCategoria
     * @return bool
     */
    public static function sincConSIGE(int $idCategoria): bool
    {
        if (env('INTEGRADO', 'false') === true) {
            if (!empty($idCategoria)) {
                $result = DB::selectOne('SELECT Sige FROM Categorias WHERE IdCategoria = ?', [$idCategoria]);
                return !empty($result->Sige);
            }
        }
        return false;
    }

    /**
     * Indica si la categoría requiere de un contrato para activar a la entidad.
     * @param int $idCategoria
     * @return bool
     */
    public static function requiereContratoActivar(int $idCategoria): bool
    {
        if (!empty($idCategoria)) {
            $requiereContrato = DB::selectOne('SELECT RequiereContratoActivar FROM Categorias WHERE IdCategoria = ?', [$idCategoria]);
            return !empty($requiereContrato->RequiereContratoActivar);
        }
        return false;
    }
}

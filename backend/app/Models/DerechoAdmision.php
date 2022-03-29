<?php

namespace App\Models;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class DerechoAdmision extends BaseModel
{
    protected $table = 'DerechoAdmision';

    protected $primaryKey = 'IdDerechoAdmision';

    protected $casts = [
        'IdDerechoAdmision' => 'integer',
        'Baja' => 'boolean'
    ];

    protected $fillable = [
        'NombreCompleto',
        'Motivo'
    ];

    public $incrementing = false;

    /**
     * @todo Solo Utiliza Usuario y Empresa para ejecutar la consulta.
     */
    public static function comprobarDocumentoEnLista($documento)
    {
        $count = self::query()
            ->where('Baja', 0)
            ->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(Documento, ' ', ''), '-', ''), '_', ''), '.', '') LIKE ?", [str_replace(['-', '_', '.', ' '], '', $documento)])
            ->count();
            
        if ($count > 0) {
            throw new ConflictHttpException("Listado de personas con acceso restringido");
        }
    }

    public function getName(): string
	{
		return sprintf('%s (%s)', $this->NombreCompleto, $this->IdDerechoAdmision);
	}

	// public function getKeyAlt(): ?string
	// {
	// 	return implode('-', [$this->IdPais, $this->IdDepartamento]);
	// }
}
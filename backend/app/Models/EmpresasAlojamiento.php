<?php

namespace App\Models;

class EmpresasAlojamiento extends BaseModel
{   
    protected $table = 'EmpresasAlojamientos';
    protected $primaryKey = 'IdEmpresasAlojamiento';
    protected $fillable = [
    ];

    public static $castProperties = [
        'IdEmpresasAlojamiento' => 'integer',
        'IdTipoDocumento' => 'integer',
        'IdAlojamiento' => 'integer',
        'IdTipoAlojamiento' => 'integer',
        'CantidadPersonas' => 'integer',
        'TipoReserva' => 'boolean',
    ];

    public $incrementing = true;

    public function getName(): string
    {
        return sprintf('%s (%s)', $this->Nombre,$this->IdEmpresasAlojamiento);
    }

    public function getKeyAlt(): string
    {
        return $this->IdEmpresasAlojamiento;
    }

}
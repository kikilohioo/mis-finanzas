<?php

namespace App\Models;

class EmpresasTransporte extends BaseModel
{   
    protected $table = 'EmpresasTransportes';

    protected $primaryKey = 'IdEmpresaTransporte';

    protected $fillable = [];

    public static $cast = [
        'IdEmpresaTransporte' => 'integer',
        'IdTipoDocumento' => 'integer',
        'IdTipoDocumentoChofer1' => 'integer',
        'IdTipoDocumentoChofer2' => 'integer',
        'TipoReserva' => 'boolean',
    ];

    protected $hidden = [
		'rowguid'
	];

    public $incrementing = true;

    public function getName(): string
    {
        return sprintf('%s', $this->IdEmpresaTransporte);
    }

    public function getKeyAlt(): string
    {
        return $this->IdEmpresaTransporte;
    }

}
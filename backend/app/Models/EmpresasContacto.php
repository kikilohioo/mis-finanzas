<?php

namespace App\Models;

class EmpresasContacto extends BaseModel
{   
    protected $table = 'EmpresasContactos';
    protected $primaryKey = 'IdEmpresaContacto';
    protected $fillable = [
    ];

    public static $castProperties = [
        'IdEmpresaContacto' => 'integer',
        'IdTipoContacto' => 'integer',
    ];

    public $incrementing = false;

    public function getName(): string
    {
        return sprintf('%s (%s)', $this->Nombre,implode('-', [$this->Documento, $this->IdTipoDocumento, $this->Matricula]));
    }

    public function getKeyAlt(): string
    {
        return implode('-', [$this->Documento, $this->IdTipoDocumento, $this->Matricula]);
    }

}
<?php

namespace App\Models;

class Persona extends BaseModel
{
    public $incrementing = false;
	protected $table = 'Personas';
    protected $primaryKey = ['Documento', 'IdTipoDocumento'];
    protected $fillable = [
        'IdCategoria',
        'IdPais',
        'IdDepartamento',
        'Ciudad',
        'Localidad',
        'Direccion',
        'Email',
        'IdUsuarioAlta',
        'FechaHoraAlta',
        'IdPaisTemp',
        'IdDepartamentoTemp',
        'CiudadTemp',
        'LocalidadTemp',
        'DireccionTemp',
        'TelefonoTemp',
        'Telefono',
    ];

    public function getName(): string
    {
        return implode('-', [$this->Documento, $this->IdTipoDocumento]);
    }

    public function getKeyAlt(): string
    {
        return implode('-', [$this->Documento, $this->IdTipoDocumento]);
    }
}

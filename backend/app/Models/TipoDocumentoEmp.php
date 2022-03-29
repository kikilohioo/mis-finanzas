<?php

namespace App\Models;

class TipoDocumentoEmp extends Documento
{
    protected $table = 'TiposDocEmp';
    
    protected $primaryKey = 'IdTipoDocEmp';
    
    protected $fillable = [
        'Nombre',
        'TieneVto',
        'Obligatorio',
        'Categorizado',
    ];

    protected $hidden = [
        'rowguid'
    ];

    public function Categorias()
    {
        return $this->belongsToMany(Categoria::class, $this->table . 'Categorias', $this->primaryKey, 'IdCategoria');
    }

    public static function list(string $documento, int $idTipoDocumento, string $modifier = ''): array
    {
        $args = (object)[
            'IdEmpresa' => implode('-', [$documento, $idTipoDocumento]),
        ];
        if ($modifier === 'Transac') {
            $args = (object)[
                'IdEmpresaTransac' => $args->IdEmpresa,
                'TablePreffix' => 'EmpresasTransac',
            ];
        }

        return self::index($args);
    }

    public function getName(): string
    {
        return sprintf('%s (%s)', $this->Nombre, $this->IdTipoDocEmp);
    }
}
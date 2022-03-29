<?php

namespace App\Models;

class TipoDocumentoVis extends Documento
{
    protected $table = 'TiposDocPF';
    protected $primaryKey = 'IdTipoDocPF';
    protected $fillable = [
        'Nombre',
        'TieneVto',
        'Obligatorio',
        'Categorizado',
        'Extranjeros',
    ];

    public function Categorias()
    {
        return $this->belongsToMany(Categoria::class, $this->table . 'Categorias', $this->primaryKey, 'IdCategoria');
    }

    public static function list(string $documento, int $idTipoDocumento, string $modifier = ''): array
    {
        $args = (object)[
            'IdPersonaFisica' => implode('-', [$documento, $idTipoDocumento]),
        ];
        if ($modifier === 'Transac') {
            $args = (object)[
                'IdPersonaFisicaTransac' => $args->IdPersonaFisica,
                'TablePreffix' => 'PersonasFisicasTransac',
            ];
        }

        return self::index($args);
    }
}
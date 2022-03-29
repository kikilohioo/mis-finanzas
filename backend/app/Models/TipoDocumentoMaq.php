<?php

namespace App\Models;

class TipoDocumentoMaq extends Documento
{
    protected $table = 'TiposDocMaq';
    protected $primaryKey = 'IdTipoDocMaq';
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

    public static function list(string $nroSerie, string $modifier = ''): array
    {
        $args = (object)[
            'NroSerie' => $nroSerie,
        ];
        if ($modifier === 'Transac') {
            $args->TablePreffix = 'MaquinasTransac';
        }

        return self::index($args);
    }

    public function getName(): string
    {
        return sprintf('%s (%s)', $this->Nombre, $this->IdTipoDocMaq);
    }
}
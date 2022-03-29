<?php

namespace App\Models;

class TipoDocumentoVehic extends Documento
{
    protected $table = 'TiposDocVehic';
    
    protected $primaryKey = 'IdTipoDocVehic';
    
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

    public static function list(string $serie, string $numero, string $modifier = ''): array
    {
        $args = (object)[
            'Serie' => $serie,
            'Numero' => $numero,
        ];
        if ($modifier === 'Transac') {
            $args->TablePreffix = 'VehiculosTransac';
        }

        return self::index($args);
    }

    public function getName(): string
    {
        return sprintf('%s (%s)', $this->Nombre, $this->IdTipoDocVehic);
    }
}
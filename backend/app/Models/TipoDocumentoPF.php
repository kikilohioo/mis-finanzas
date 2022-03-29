<?php

namespace App\Models;

class TipoDocumentoPF extends Documento
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

    public function getName(): string
    {
        return sprintf('%s (%s)', $this->Nombre, $this->IdTipoDocPF);
    }

    #region Deprecated 
    // protected $table = 'TiposDocPF';
    // protected $primaryKey = 'IdTipoDocPF';

    // public $incrementing = false;

    // public static function list(object $filter = null): array
    // {
    //     // $query = self::query()
    //     // $query = DB::table('TiposDocPF')
    //     $query = self::select(['TiposDocPF.IdTipoDocPF', 'Nombre', 'TieneVto', 'Obligatorio', 'Categorizado', 'Extranjeros'])
    //         ->leftJoin('TiposDocPFCategorias', 'TiposDocPF.IdTipoDocPF', 'TiposDocPFCategorias.IdTipoDocPF')
    //         ->orderBy('Nombre');

    //     if (isset($filter->DocPFFilter)) {
    //         throw new \Exception('Comprobar que pasa en este caso');
    //     }

    //     if (isset($filter->Extranjeros) && !empty($filter->Extranjeros) && isset($filter->IdCategoria) && !empty($filter->IdCategoria)) {
    //         $query->whereRaw('Extranjeros = 1 OR IdCategoria = ?', [$filter->IdCategoria]);
    //     } else if (isset($filter->IdCategoria) && !empty($filter->IdCategoria)) {
    //         $query->where('IdCategoria', $filter->IdCategoria);
    //     }
        
    //     if (isset($filter->Obligatorio) && !empty($filter->Obligatorio)) {
    //         $query->where('Obligatorio', 1);
    //     }

    //     if (isset($filter->Busqueda) && !empty($filter->Busqueda)) {
    //         $query->whereRaw('Nombre COLLATE Latin1_general_CI_AI LIKE ? COLLATE Latin1_general_CI_AI', '%' . $filter->Busqueda . '%');
    //     }
            
    //     return $query->get()->toArray();
    // }
    #endregion
}
<?php

namespace App\Http\Controllers;
use App\Models\TipoDocumentoPF;

class TipoDocumentoPersonaFisicaController extends TipoDocumentoAbstractController
{
    // protected $table = 'TiposDocPF';
    // protected $tableAlias = 'tdpf';
    // protected $keyName = 'IdTipoDocPF';
    protected $modelClass = TipoDocumentoPF::class;
}
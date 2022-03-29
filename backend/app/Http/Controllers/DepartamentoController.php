<?php

namespace App\Http\Controllers;

use App\Models\Pais;
use App\Models\Departamento;
use Illuminate\Http\Request;

class DepartamentoController extends Controller
{

    /**
     * @var Request
     */
    private $req;

    public function __construct(Request $req)
    {
        $this->req = $req;
    }

    public function index(int $idPais)
    {
        $query = Departamento::where('IdPais', $idPais);
        
        $query->orderBy('Nombre', 'ASC');

        $data = $query->get();

        return $this->responsePaginate($data);
    }

}
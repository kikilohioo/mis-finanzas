<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Funcion;

class FuncionController extends Controller
{

    /**
     * @var Request
     */
    private $req;

    public function __construct(Request $req)
    {
        $this->req = $req;
    }

    public function index()
    {
        $query = Funcion::query()->whereNotNull('Grupo');
        
        if ($grupo = $this->req->input('Grupo')) {
            $query->where('Grupo', $grupo);
        }

        if (!$this->req->input('Gestion')) {
            $query->where('Gestion', 0);
        }

        $query->orderBy('Descripcion');

        return $this->responsePaginate($query->get());
    }
    
}
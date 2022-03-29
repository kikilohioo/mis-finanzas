<?php

namespace App\Http\Controllers;

use App\FsUtils;
use App\Models\Laboratorio;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LaboratorioController extends Controller
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
        $query = Laboratorio::query();

        /*if ($this->req->input('MostrarEliminados', 'false') === 'false') {
            $query->where('Baja', false);
        }

        if ($busqueda = $this->req->input('Busqueda')) {
            $query->where('Nombre', 'like', '%' . $busqueda . '%');
        }*/
        
        $data = $query->get();        

        return $this->responsePaginate($data);
    }
}
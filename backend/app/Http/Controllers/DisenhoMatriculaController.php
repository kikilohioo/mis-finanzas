<?php

namespace App\Http\Controllers;

use App\Models\DisenhoMatricula;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DisenhoMatriculaController extends Controller
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
        $query = DisenhoMatricula::query()->orderBy('Nombre')->get();
        return $this->responsePaginate($query);
    }

    // metodo para retornar el diseño de la matrícula
    public function loadDisenhoEnBase64($id)
    {
        return DisenhoMatricula::loadDisenhoEnBase64($id);
    }
}
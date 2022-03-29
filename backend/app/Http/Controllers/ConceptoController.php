<?php

namespace App\Http\Controllers;

use App\FsUtils;
use App\Models\Concepto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Support\Facades\DB;

class ConceptoController extends Controller
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
        $conceptos = Concepto::all();
        
    }

    public function show(int $id)
    {
        
    }

    public function create()
    {
        
    }

    public function update(int $id)
    {
        
    }

    public function delete(int $id)
    {
        
    }

}
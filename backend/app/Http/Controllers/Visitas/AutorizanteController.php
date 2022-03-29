<?php

namespace App\Http\Controllers\Visitas;

use App\FsUtils;
use App\Models\Visitas\Autorizante;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class AutorizanteController extends \App\Http\Controllers\Controller
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
        // $query = Autorizante::query();
        // $data = $query->get();
        $data = Autorizante::getAll();
        return $data;
    }

    public function show(string $IdUsuarioAutorizante, int $IdArea)
    {
        $entity = Autorizante::where('IdUsuarioAutorizante', $IdUsuarioAutorizante)
                            ->where('IdArea', $IdArea)
                            ->first();

        if (!isset($entity)) {
            throw new NotFoundHttpException('Autorizante no encontrado');
        }

        return $entity;
    }

    public function create()
    {
        Autorizante::exigirArgs($this->req->all(), ['IdArea', 'IdUsuarioAutorizante', 'AutorizaExcepciones', 'AutorizaVisitas', 'RecibeNotificaciones']);
        
        $entity = Autorizante::where('IdUsuarioAutorizante', $this->req->input('IdUsuarioAutorizante'))
                            ->where('IdArea', $this->req->input('IdArea'))
                            ->first();

        if (isset($entity)) {
            throw new NotFoundHttpException('Autorizante ya creado');
        }

        $entity = new Autorizante($this->req->all());
        $entity->IdUsuario = Auth::id();
        $entity->FechaHora = new \DateTime;

        $entity->save();

        return $entity->refresh();
    }

    public function update()
    {
        throw new NotFoundHttpException('Los autorizantes no se pueden modificar');
    }

    public function delete(string $IdUsuarioAutorizante, int $IdArea)
    {
        $entity = Autorizante::where('IdUsuarioAutorizante', $IdUsuarioAutorizante)
                            ->where('IdArea', $IdArea)
                            ->first();
        
        if (!isset($entity)) {
            throw new NotFoundHttpException('Autorizante no encontrado');
        }
        
        Autorizante::where('IdUsuarioAutorizante', $IdUsuarioAutorizante)
                    ->where('IdArea', $IdArea)
                    ->delete();
    }

}
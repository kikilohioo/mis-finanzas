<?php

namespace App\Http\Controllers\PCAR;

use App\Models\PCAR\Autorizante;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
        $data = DB::table('PCAR_Autorizantes')
                    ->join('PCAR_Areas', 'PCAR_Autorizantes.IdArea', '=', 'PCAR_Areas.Id')
                    ->join('Usuarios', 'PCAR_Autorizantes.IdUsuarioAutorizante', '=', 'Usuarios.IdUsuario')
                    ->select('PCAR_Autorizantes.*', 'PCAR_Areas.Nombre as Area', 'Usuarios.Nombre', 'Usuarios.Email', 'Usuarios.PTC')
                    ->get();

        return $data;
    }

    public function show(string $id, int $idArea)
    {
        $entity = DB::table('PCAR_Autorizantes')
                ->join('PCAR_Areas', 'PCAR_Autorizantes.IdArea', '=', 'PCAR_Areas.Id')
                ->join('Usuarios', 'PCAR_Autorizantes.IdUsuarioAutorizante', '=', 'Usuarios.IdUsuario')
                ->select('PCAR_Autorizantes.*', 'PCAR_Areas.Nombre as Area', 'Usuarios.Nombre', 'Usuarios.Email')
                ->where('PCAR_Autorizantes.IdUsuarioAutorizante', '=', $id )
                ->where('PCAR_Autorizantes.IdArea', '=', $idArea)
                ->get();

        if (count($entity) <= 0) {
            throw new NotFoundHttpException('Autorizante no encontrado');
        }

        return $entity;
    }

    public function create()
    {
        Autorizante::exigirArgs($this->req->all(), ['IdUsuarioAutorizante', 'IdArea']);

        if (empty($this->req->input('IdUsuarioAutorizante'))) {
            throw new ConflictHttpException('Debe seleccionar un Usuario');
        }

        if (empty($this->req->input('IdArea'))) {
            throw new ConflictHttpException('Debe seleccionar un área');
        }

        /**
         * @var Autorizante
        */
        $entity = Autorizante::query()->where('IdUsuarioAutorizante', $this->req->input("IdUsuarioAutorizante"))->where('IdArea', $this->req->input("IdArea"))->first();

        if (isset($entity)) {
            throw new ConflictHttpException('El usuario ' . $this->req->input('IdUsuarioAutorizante') . ' ya esta asignado para esta área');
        }

        $entity = new Autorizante($this->req->all());
        $entity->IdUsuario = Auth::id();
        $entity->FechaHora = new \DateTime;
        
        $entity->save();
        return $entity;
    }

    public function update(string $id, int $idArea)
	{
		throw new \Exception("Los autorizantes no se pueden actualizar. Sólo crear o eliminar");
    }

    public function delete(string $id, int $idArea)
    {
        /**
         * @var Autorizante
        */
        $entity = Autorizante::query()->where('IdUsuarioAutorizante', $id)->where('IdArea', $idArea)->first();

        if (!isset($entity)) {
            throw new NotFoundHttpException('Autorizante no encontrado');
        }
        $entity::where('IdUsuarioAutorizante', $id)->where('IdArea', $idArea)->delete();
        $entity->save();
    }
}
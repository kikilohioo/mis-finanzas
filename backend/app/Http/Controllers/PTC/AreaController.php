<?php

namespace App\Http\Controllers\PTC;

use App\FsUtils;
use App\Models\PTC\Area;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class AreaController extends \App\Http\Controllers\Controller
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
        $query = Area::query();

        if ($busqueda = $this->req->input('Busqueda')) {
            $query->where('Nombre', 'like', '%' . $busqueda . '%');
        }
        if (!auth()->user()->PTCGestion && $this->req->input('SoloAsignadas', 'false') === 'true') {
            $query->whereRaw('IdArea IN (SELECT IdArea FROM PTCAreasUsuarios WHERE IdUsuario = ?)', [auth()->user()->IdUsuario]);
        }

        $data = $query->orderBy('Nombre')->get();

        return $this->responsePaginate($data);
    }

    public function show(int $id)
    {
        $entity = Area::find($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Area no encontrada');
        }

        return $entity;
    }

    public function create()
    {
        Area::exigirArgs($this->req->all(), ['Nombre']);
        
        $entity = new Area($this->req->all());
        $entity->save();

        return $entity->refresh();
    }

    public function update(int $id)
    {
        Area::exigirArgs($this->req->all(), ['Nombre']);

        $entity = Area::find($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Area no encontrada');
        }

        $entity->fill($this->req->all());
        $entity->save();

        return $entity;
    }

    public function delete(int $id)
    {
        $entity = Area::find($id);
        if (!isset($entity)) { throw new NotFoundHttpException('Area no encontrada'); }
        
        $areaEnUso = DB::select("Select top 1 1 from PTC where IdArea = :id", [":id" => $id]);
        if (!empty($areaEnUso[0])) { throw new NotFoundHttpException('No se puede eliminar el Area ya que está asociada a un Permiso de trabajo'); }

        $entity->delete();
    }

}
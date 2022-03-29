<?php

namespace App\Http\Controllers\Visitas;

use App\FsUtils;
use App\Models\Visitas\Area;
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
        $data = $query->get();

        return $data;
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
        $entity->IdUsuario = Auth::id();
        $entity->FechaHora = new \DateTime;

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
        
        if (!isset($entity)) {
            throw new NotFoundHttpException('Area no encontrada');
        }
        
        $entity->delete();
    }

}
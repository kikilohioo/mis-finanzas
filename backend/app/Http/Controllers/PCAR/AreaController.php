<?php

namespace App\Http\Controllers\PCAR;

use App\Models\PCAR\Area;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
            $query->where(function ($query) use ($busqueda) {
                return $query->where('id', 'like', '%' . $busqueda . '%')
                    ->orWhere('Nombre', 'like', '%' . $busqueda . '%');
            });
        }

        return $query->get();
    }

    public function show(int $id)
    {
        $entity = Area::find($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Área no encontrada');
        }

        return $entity;
    }

    public function create()
    {
        Area::exigirArgs($this->req->all(), ['Nombre']);

        if (empty($this->req->input('Nombre'))) {
            throw new ConflictHttpException('Debe ingresar un nombre al área');
        }

        /**
         * @var Area
        */
        $existeNombreArea = Area::query()->where('Nombre', $this->req->input('Nombre'))->first();
        
        if(isset($existeNombreArea)) {
            throw new ConflictHttpException('El nombre ' . $existeNombreArea->Nombre . ' ya esta en uso');
        }

        $entity = new Area($this->req->all());
        $entity->save();

        return $entity->refresh();
    }

    public function update(int $id)
    {
        Area::exigirArgs($this->req->all(), ['Nombre']);

        $entity = Area::find($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Área no encontrada');
        }

        if (empty($this->req->input('Nombre'))) {
            throw new ConflictHttpException('Debe ingresar un nombre al área');
        }

        /**
         * @var Area
        */
        $existeNombreArea = Area::query()->where('Nombre', $this->req->input('Nombre'))->where('Id', '!=', $id)->first();
        
        if(isset($existeNombreArea)) {
            throw new ConflictHttpException('El nombre ' . $existeNombreArea->Nombre . ' ya esta en uso');
        }

        $entity->fill($this->req->all());
        $entity->save();

        return $entity;
    }

    public function delete(int $id)
    {
        $entity = Area::find($id);
        
        if (!isset($entity)) {
            throw new NotFoundHttpException('Área no encontrada');
        }
        $entity::where('Id',$id)->delete();
        $entity->save();
    }
}
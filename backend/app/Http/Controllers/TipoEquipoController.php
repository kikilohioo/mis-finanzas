<?php

namespace App\Http\Controllers;

use App\Models\TipoEquipo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TipoEquipoController extends Controller
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
        $query = TipoEquipo::query();

        if ($busqueda = $this->req->input('Busqueda')) {
            $query->where('Descripcion', 'like', '%' . $busqueda . '%');
        }

        $data = $query->orderBy('Descripcion')->get();

        return $this->responsePaginate($data);
    }

    /**
     * @todo Validar Usuario Habilitado
     */
    public function show(int $id)
    {
        $entity = TipoEquipo::find($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Tipo de Equipo no encontrado');
        }

        return $entity;
    }

    public function create()
    {
        TipoEquipo::exigirArgs($this->req->all(), ['Descripcion']);
        
        $entity = new TipoEquipo($this->req->all());
        $entity->IdTipoEquipo = TipoEquipo::getNextId();
        $entity->FechaHora = new \DateTime;
        $entity->IdUsuario = Auth::id();

        $entity->save();

        return $entity->refresh();
    }

    /**
     * @todo Validar Usuario Habilitado
     */
    public function update(int $id)
    {
        TipoEquipo::exigirArgs($this->req->all(), ['Descripcion']);

        $entity = TipoEquipo::find($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Tipo de Equipo no encontrado');
        }

        $entity->fill($this->req->all());
        $entity->save();

        return $entity;
    }

    /**
     * @todo VALIDAR SI EL TIPO DE EQUIPO ESTA SIENDO UTILIZADO. VALIDAR POR USUARIO Y EMPRESA 
     */
    public function delete(int $id)
    {
        $entity = TipoEquipo::find($id);
        
        if (!isset($entity)) {
            throw new NotFoundHttpException('Tipo de Equipo no encontrado');
        }
        
        $entity::where('IdTipoEquipo',$id)->delete(); // SE ELIMINA FISICAMENTE
        $entity->save();
    }
}
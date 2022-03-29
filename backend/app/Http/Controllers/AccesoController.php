<?php

namespace App\Http\Controllers;

use App\FsUtils;
use App\Models\Acceso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Support\Facades\DB;

class AccesoController extends Controller
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
        $query = Acceso::query();

        if ($this->req->input('MostrarEliminados', 'false') === 'false') {
            $query->where('Baja', false);
        }

        if ($asignable = ($this->req->input('Asignable', 'false') === 'true')) {
            $query->where('Asignable', $asignable);
        }

        if ($busqueda = $this->req->input('Busqueda')) {
            $query->where('Descripcion', 'like', '%' . $busqueda . '%');
        }

        if ($this->req->input('ObtenerPorParametro', 'false') === 'true') {
            $param = FsUtils::getParams('AccesosPorDef');
            $idAccesos = explode(',', $param);
            $query->whereIn('IdAcceso', $idAccesos);
        }

        if ($this->req->input('ObtenerPorParametro', 'false') === 'true' && env('ACCESOS_POR_PARAMERTRO')) {
            $query->orWhereIn('IdAcceso', explode(',', env('ACCESOS_POR_PARAMERTRO')));
        }

        $data = $query->orderBy('Descripcion')->get();

        $output = $this->req->input('output', 'json');
        if ($output !== 'json') {
            return $this->export($data->toArray(), $output);
        }

        return $this->responsePaginate($data);
    }

    private function export(array $data, string $type)
    {
        $filename = 'FSAcceso-Accesos-' . date('Ymd-his');
        $headers = [
            'Descripcion' => 'Descripción',
        ];

        return FsUtils::export($type, $data, $headers, $filename);
    }

    public function show(int $id)
    {
        $entity = Acceso::find($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Acceso no encontrado');
        }

        return $entity;
    }

    public function create()
    {
        Acceso::exigirArgs($this->req->all(), ['Descripcion']);
        
        $entity = new Acceso($this->req->all());
        $entity->IdAcceso = Acceso::getNextId();
        $entity->IdUsuario = Auth::id();
        $entity->FechaHora = new \DateTime;
        $entity->Baja = false;

        $entity->save();

        return $entity->refresh();
    }

    public function update(int $id)
    {
        Acceso::exigirArgs($this->req->all(), ['Descripcion']);

        $entity = Acceso::find($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Acceso no encontrado');
        }

        $entity->fill($this->req->all());
        $entity->Baja = false;
        $entity->save();

        return $entity;
    }

    public function delete(int $id)
    {
        $entity = Acceso::find($id);
        
        if (!isset($entity)) {
            throw new NotFoundHttpException('Acceso no encontrado');
        }
        
        $entity->Baja = true;
        $entity->save();
    }

}
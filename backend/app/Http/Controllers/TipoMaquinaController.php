<?php

namespace App\Http\Controllers;

use App\FsUtils;
use App\Models\TipoMaquina;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TipoMaquinaController extends Controller
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
        $query = TipoMaquina::query();

        if ($this->req->input('MostrarEliminados', 'false') === 'false') {
            $query->where('Baja', false);
        }

        if ($busqueda = $this->req->input('Busqueda')) {
            $query->where('Descripcion', 'like', '%' . $busqueda . '%');
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
        $filename = 'FSAcceso-Tipo-Maquina-' . date('Ymd-his');
        $headers = [
            'Descripcion' => 'Nombre',
        ];

        return FsUtils::export($type, $data, $headers, $filename);
    }

    public function show(int $id)
    {
        $entity = TipoMaquina::find($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Tipo de Maquina no encontrado');
        }

        return $entity;
    }

    public function create()
    {
        TipoMaquina::exigirArgs($this->req->all(), ['Descripcion']);
        
        $entity = new TipoMaquina($this->req->all());
        $entity->IdTipoMaquina = TipoMaquina::getNextId();
        $entity->FechaHora = new \DateTime;
        $entity->FechaHoraBaja = new \DateTime;
        $entity->IdUsuario = Auth::id();
        $entity->IdUsuarioBaja = Auth::id();
        $entity->Baja = false;

        $entity->save();

        return $entity->refresh();
    }

    public function update(int $id)
    {
        TipoMaquina::exigirArgs($this->req->all(), ['Descripcion']);

        $entity = TipoMaquina::find($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Tipo de Maquina no encontrado');
        }

        $entity->fill($this->req->all());
        $entity->Baja = false;
        $entity->save();

        return $entity;
    }

    public function delete(int $id)
    {
        $entity = TipoMaquina::find($id);
        
        if (!isset($entity)) {
            throw new NotFoundHttpException('Tipo de Maquina no encontrado');
        }
        
        $entity->Baja = true;
        $entity->save();
    }

}
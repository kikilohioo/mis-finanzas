<?php

namespace App\Http\Controllers;

use App\FsUtils;
use App\Models\EmpresasTransporte;
use App\Models\Transportista;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TransportistaController extends Controller
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
        $query = Transportista::query();

        if ($this->req->input('MostrarEliminados', 'false') === 'false') {
            $query->where('Baja', false);
        }

        if ($busqueda = $this->req->input('Busqueda')) {
            $query->where('Nombre', 'like', '%' . $busqueda . '%');
        }
        
        $data = $query->get();

        $output = $this->req->input('output', 'json');
        
        if ($output !== 'json') {
            return $this->export($data->toArray(), $output);
        }

        return $this->responsePaginate($data);
    }

    private function export(array $data, string $type)
    {
        $filename = 'FSAcceso-Transportista-' . date('Ymd-his');
        $headers = [
            'Nombre' => 'Nombre',
        ];
        
        return FsUtils::export($type, $data, $headers, $filename);
    }

    public function show(int $id)
    {
        $entity = Transportista::find($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Transportista no encontrado');
        }

        return $entity;
    }

    public function create()
    {           
        Transportista::exigirArgs($this->req->all(), ['Nombre']);

        $entity = new Transportista($this->req->all());
        $entity->Baja = false;
        $entity->save();

        return $entity->refresh();
    }

    public function update(int $id)
    {
        Transportista::exigirArgs($this->req->all(), ['Nombre']);

        $entity = Transportista::find($id);
        if (!isset($entity)) {
            throw new NotFoundHttpException('Transportista no encontrado');
        }

        $entity->fill($this->req->all());
        $entity->Baja = false;
        $entity->save();
    }

    public function delete(int $id)
    {
        $entity = Transportista::find($id);
        
        $transportistaEnUso = EmpresasTransporte::query()
        ->where('IdTransportista', $id)
        ->first();

        if (!isset($entity)) {
            throw new NotFoundHttpException('Transportista no encontrado');
        }

        if (isset($transportistaEnUso)) {
            throw new NotFoundHttpException('El Transportista está siendo utilizado por una empresa/s');
        }
        
        $entity->Baja = true;        
        $entity->save();
    }

}
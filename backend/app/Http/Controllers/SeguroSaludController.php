<?php

namespace App\Http\Controllers;

use App\FsUtils;
use App\Models\SeguroSalud;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SeguroSaludController extends Controller
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
        $query = SeguroSalud::query();

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
        $filename = 'FSAcceso-SeguroSalud-' . date('Ymd-his');
        $headers = [
            'Nombre' => 'Nombre',
        ];
        
        return FsUtils::export($type, $data, $headers, $filename);
    }

    public function show(int $id)
    {
        $entity = SeguroSalud::find($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Seguro salud no encontrado');
        }

        return $entity;
    }

    public function create()
    {           
        SeguroSalud::exigirArgs($this->req->all(), ['Nombre']);

        $entity = new SeguroSalud($this->req->all());
        $entity->Baja = false;
        $entity->save();

        return $entity->refresh();
    }

    public function update(int $id)
    {
        SeguroSalud::exigirArgs($this->req->all(), ['Nombre']);

        $entity = SeguroSalud::find($id);
        if (!isset($entity)) {
            throw new NotFoundHttpException('Seguro salud no encontrado');
        }

        $entity->fill($this->req->all());
        $entity->Baja = false;
        $entity->save();
    }

    public function delete(int $id)
    {
        $entity = SeguroSalud::find($id);
        
        //buscar que el Seguro salud no exista en ?.
        //buscar que el Seguro salud no exista en ?.
        //buscar que el Seguro salud no exista en ?.
        //buscar que el Seguro salud no exista en ?.
        /*$seguroSaludEnUso = Alojamiento::query()
        ->where('IdTipoAlojamiento', $id)
        ->where('Baja', false)
        ->first();*/

        if (!isset($entity)) {
            throw new NotFoundHttpException('Seguro salud no encontrado');
        }

        if (isset($seguroSaludEnUso)) {
            throw new NotFoundHttpException('El Seguro salud está siendo utilizado por una empresa de transporte');
        }
        
        $entity->Baja = true;        
        $entity->save();
    }

}
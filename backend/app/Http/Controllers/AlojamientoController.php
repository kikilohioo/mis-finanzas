<?php

namespace App\Http\Controllers;

use App\FsUtils;
use App\Models\Alojamiento;
use App\Models\EmpresasAlojamiento;
use App\Models\TiposAlojamiento;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AlojamientoController extends Controller
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
        $query = Alojamiento::query();

        $er = $this->req->all();
        if ($this->req->input('MostrarEliminados', 'false') === 'false') {
            $query->where('Baja', false);
        }

        if ($busqueda = $this->req->input('Busqueda')) {
            $query->where('Nombre', 'like', '%' . $busqueda . '%');
        }

        if ($IdTipoAlojamiento = $this->req->input('IdTipoAlojamiento')) {
            $query->where('IdTipoAlojamiento', $IdTipoAlojamiento);
        }

        $data = $query->get();

        foreach($data as &$item){
            if(!empty($item['IdTipoAlojamiento'])){
                $item['TipoAlojamientoNombre'] = TiposAlojamiento::select('Nombre')->where('IdTipoAlojamiento', $item['IdTipoAlojamiento'])->first()->Nombre;
            }
        }

        $output = $this->req->input('output', 'json');
        
        if ($output !== 'json') {
            return $this->export($data->toArray(), $output);
        }

        return $this->responsePaginate($data);
    }

    private function export(array $data, string $type)
    {
        $filename = 'FSAcceso-Alojamientos-' . date('Ymd-his');
        $headers = [
            'Nombre' => 'Nombre',
            'Direccion' => 'Direccion',
            'Localidad' => 'Localidad',
            'Telefono' => 'Telefono',
            'Plazas' => 'Plazas',
            'TipoAlojamientoNombre' => 'Tipo de Alojamiento',
        ];

        foreach($data as &$item){
            $item['Casa'] = $item['Casa'] ? 'Si' : 'No'; 
        }
        
        return FsUtils::export($type, $data, $headers, $filename);
    }

    public function show(int $id)
    {
        $entity = Alojamiento::find($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Alojamiento no encontrado');
        }

        return $entity;
    }

    public function create()
    {
        Alojamiento::exigirArgs($this->req->all(), ['Nombre', 'Telefono', 'Direccion', 'Localidad', 'IdTipoAlojamiento', 'Plazas']);

        $entity = new Alojamiento($this->req->all());
        $entity->IdTipoAlojamiento = $this->req->input('IdTipoAlojamiento');
        $entity->RequiereUnidad = $this->req->input('RequiereUnidad');
        $entity->Baja = false;
        $entity->save();

        return $entity->refresh();
    }

    public function update(int $id)
    {
        Alojamiento::exigirArgs($this->req->all(), ['Nombre', 'Telefono', 'Direccion', 'Localidad', 'IdTipoAlojamiento', 'Plazas']);

        $entity = Alojamiento::find($id);
        if (!isset($entity)) {
            throw new NotFoundHttpException('Alojamiento no encontrado');
        }

        $entity->fill($this->req->all());
        $entity->IdTipoAlojamiento = $this->req->input('IdTipoAlojamiento');
        $entity->RequiereUnidad = $this->req->input('RequiereUnidad');
        $entity->Baja = false;
        $entity->save();

    }

    public function delete(int $id)
    {
        $entity = Alojamiento::find($id);

        $alojamientoEnUso = EmpresasAlojamiento::query()
        ->where('IdAlojamiento', $id)
        ->first();

        if (!isset($entity)) {
            throw new NotFoundHttpException('Alojamiento no encontrado');
        }

        if (isset($alojamientoEnUso)) {
            throw new NotFoundHttpException('El alojamiento está siendo utilizado por una empresa/s');
        }
        
        $entity->Baja = true;        
        $entity->save();
    }

}
<?php

namespace App\Http\Controllers;

use App\FsUtils;
use App\Models\Alojamiento;
use App\Models\TiposAlojamiento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class TiposAlojamientoController extends Controller
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
        $query = TiposAlojamiento::query();

        if ($this->req->input('MostrarEliminados', 'false') === 'false') {
            $query->where('Baja', false);
        }

        if ($busqueda = $this->req->input('Busqueda')) {
            $query->where('Nombre', 'like', '%' . $busqueda . '%');
        }
        
        if ($casa = $this->req->input('Casa')) {
            $query->where('Casa', $casa);
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
        $filename = 'FSAcceso-TiposAlojamiento-' . date('Ymd-his');
        $headers = [
            'Nombre' => 'Nombre',
            'Casa' => 'Es Casa',
            'RequiereDireccion' => 'Requiere Direccion',
            'RequiereLocalidad' => 'Requiere Localidad'
        ];

        foreach($data as &$item){
            $item['Casa'] = $item['Casa'] ? 'Si' : 'No'; 
            $item['RequiereDireccion'] = $item['RequiereDireccion'] ? 'Si' : 'No'; 
            $item['RequiereLocalidad'] = $item['RequiereLocalidad'] ? 'Si' : 'No'; 
        }
        
        return FsUtils::export($type, $data, $headers, $filename);
    }

    public function show(int $id)
    {
        $entity = TiposAlojamiento::find($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Tipo de alojamiento no encontrado');
        }

        return $entity;
    }

    public function create()
    {

        return DB::transaction(function () {
           
            TiposAlojamiento::exigirArgs($this->req->all(), ['Nombre']);

            $entity = new TiposAlojamiento($this->req->all());
            $entity->IdTipoAlojamiento = TiposAlojamiento::getNextId();
            $entity->Casa = $this->req->input('Casa', false);
            $entity->RequiereDireccion = $this->req->input('RequiereDireccion', false);
            $entity->RequiereLocalidad = $this->req->input('RequiereLocalidad', false);
            $entity->Baja = false;
            $entity->save();

            return $entity->refresh();
        });
    }

    public function update(int $id)
    {
        TiposAlojamiento::exigirArgs($this->req->all(), ['Nombre']);

        $entity = TiposAlojamiento::find($id);
        if (!isset($entity)) {
            throw new NotFoundHttpException('Tipo de alojamiento no encontrado');
        }

        $entity->fill($this->req->all());
        $entity->Casa = $this->req->input('Casa');
        $entity->RequiereDireccion = $this->req->input('RequiereDireccion');
        $entity->RequiereLocalidad = $this->req->input('RequiereLocalidad');
        $entity->Baja = false;
        $entity->save();
    }

    public function delete(int $id)
    {
        $entity = TiposAlojamiento::find($id);
        
        $tipoAlojamientoEnUso = Alojamiento::query()
        ->where('IdTipoAlojamiento', $id)
        ->where('Baja', false)
        ->first();

        if (!isset($entity)) {
            throw new NotFoundHttpException('Tipo de alojamiento no encontrado');
        }

        if (isset($tipoAlojamientoEnUso)) {
            throw new NotFoundHttpException('El tipo de alojamiento está siendo utilizado por alojamiento/s');
        }
        
        $entity->Baja = true;        
        $entity->save();
    }

}
<?php

namespace App\Http\Controllers;

use App\FsUtils;
use App\Models\TiposContacto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TiposContactoController extends Controller
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
        $query = TiposContacto::query();

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
        $filename = 'FSAcceso-TiposContacto-' . date('Ymd-his');
        $headers = [
            'Nombre' => 'Nombre',
        ];
        
        return FsUtils::export($type, $data, $headers, $filename);
    }

    public function show(int $id)
    {
        $entity = TiposContacto::find($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Tipo de contacto no encontrado');
        }

        return $entity;
    }

    public function create()
    {

        return DB::transaction(function () {
           
            TiposContacto::exigirArgs($this->req->all(), ['Nombre']);

            $entity = new TiposContacto($this->req->all());
            $entity->IdTipoContacto = TiposContacto::getNextId();
            $entity->Baja = false;
            $entity->save();

            return $entity->refresh();
        });
    }

    public function update(int $id)
    {
        TiposContacto::exigirArgs($this->req->all(), ['Nombre']);

        $entity = TiposContacto::find($id);
        if (!isset($entity)) {
            throw new NotFoundHttpException('Tipo de contacto no encontrado');
        }

        $entity->fill($this->req->all());
        $entity->Baja = false;
        $entity->save();
    }

    public function delete(int $id)
    {
        $entity = TiposContacto::find($id);
        
        /*$tipoContactoEnUso = EmpresasContactos::query()
        ->where('IdTipoContacto', $id)
        ->first();*/

        if (!isset($entity)) {
            throw new NotFoundHttpException('Tipo de contacto no encontrado');
        }

        if (isset($tipoContactoEnUso)) {
            throw new NotFoundHttpException('El tipo de contacto está siendo utilizado por una empresa/s');
        }
        
        $entity->Baja = true;        
        $entity->save();
    }

}
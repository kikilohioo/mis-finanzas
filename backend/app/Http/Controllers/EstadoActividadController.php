<?php

namespace App\Http\Controllers;

use App\FsUtils;
use App\Models\EstadoActividad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class EstadoActividadController extends Controller
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
        $query = EstadoActividad::query();

        if ($this->req->input('MostrarEliminados', 'false') === 'false') {
            $query->where('Baja', false);
        }

        if ($busqueda = $this->req->input('Busqueda')) {
            $query->where('Descripcion', 'like', '%' . $busqueda . '%');
        }

        $query->orderBy('Descripcion', 'ASC');

        $data = $query->get();

        $output = $this->req->input('output', 'json');
        
        if ($output !== 'json') {
            return $this->export($data->toArray(), $output);
        }

        return $this->responsePaginate($data);
    }

    private function export(array $data, string $type)
    {
        $filename = 'FSAcceso-Estado-Actividad-' . date('Ymd-his');
        $headers = [
            'Descripcion' => 'Nombre',
        ];

        return FsUtils::export($type, $data, $headers, $filename);
    }

    public function show(int $id)
    {
        $entity = EstadoActividad::find($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Estado de actividad no encontrado');
        }

        return $entity;
    }

    public function create()
    {
        EstadoActividad::exigirArgs($this->req->all(), ['Descripcion', 'Accion']);
       // if (!empty($ea->Descripcion) && !empty($ea->Accion) && ($ea->Accion != 'D' || ($ea->Accion == 'D' && !empty($ea->Dias))))

        $entity = new EstadoActividad($this->req->all());
        $entity->IdEstadoActividad = EstadoActividad::getNextId();
        $entity->IdUsuario = Auth::id();
        $entity->FechaHora = new \DateTime;

        if ( $this->req->input('Accion') == 'D' ) {
            EstadoActividad::exigirArgs($this->req->all(), ['Dias']);
        }
        $entity->Dias = $this->req->input('Dias') == "" ? 0 : $this->req->input('Dias');
        $entity->Desactivar = $this->req->input('Desactivar');
        $entity->Baja = false;

        $entity->save();

        return $entity->refresh();
    }

    public function update(int $id)
    {
        EstadoActividad::exigirArgs($this->req->all(), ['Descripcion', 'Accion']);

        $entity = EstadoActividad::find($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Estado de actividad no encontrado');
        }

        $entity->fill($this->req->all());

        if ( $this->req->input('Accion') == 'D' ) {
            EstadoActividad::exigirArgs($this->req->all(), ['Dias']);
        }
        $entity->Dias = $this->req->input('Dias');

        if( $this->req->input('Accion') !== 'D' ) {
            $entity->Dias = 0;
        }
        $entity->Desactivar = $this->req->input('Desactivar');
        $entity->save();

        return $entity;
    }

    public function delete(int $id)
    {
        $entity = EstadoActividad::find($id);
        
        if (!isset($entity)) {
            throw new NotFoundHttpException('Estado de actividad no encontrado');
        }
        
        $entity->Baja = true;

        $entity->save();
    }

}
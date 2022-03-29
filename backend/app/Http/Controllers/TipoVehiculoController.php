<?php

namespace App\Http\Controllers;

use App\FsUtils;
use App\Models\TipoVehiculo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class TipoVehiculoController extends Controller
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
        $query = TipoVehiculo::query();

        if ($this->req->input('MostrarEliminados', 'false') === 'false') {
            $query->where('Baja', false);
        }

        if ($busqueda = $this->req->input('Busqueda')) {
            $query->where('Descripcion', 'like', '%' . $busqueda . '%');
        }

        if ($PGP = $this->req->input('PGP')) {
            $query->where('PGP', $PGP);
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
        $filename = 'FSAcceso-Tipo-Vehiculo-' . date('Ymd-his');
        $headers = [
            'Descripcion' => 'Nombre',
        ];
        return FsUtils::export($type, $data, $headers, $filename);
    }

    public function show(int $id)
    {
        $entity = TipoVehiculo::find($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Tipo de vehículo no encontrado');
        }

        return $entity;
    }

    public function create()
    {
        TipoVehiculo::exigirArgs($this->req->all(), ['Descripcion']);
        
        $entity = new TipoVehiculo($this->req->all());
        $entity->IdTipoVehiculo = TipoVehiculo::getNextId();
        $entity->IdUsuario = Auth::id();
        $entity->FechaHora = new \DateTime;
        $entity->Baja = false;

        $entity->save();

        return $entity->refresh();
    }

    public function update(int $id)
    {
        TipoVehiculo::exigirArgs($this->req->all(), ['Descripcion']);

        $entity = TipoVehiculo::find($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Tipo de vehiculo no encontrado');
        }

        $entity->fill($this->req->all());
        $entity->Baja = false;
        $entity->IdUsuarioBaja = null;
        $entity->FechaHoraBaja = null;
        $entity->save();

        return $entity;
    }

    public function delete(int $id)
    {
        $entity = TipoVehiculo::find($id);
        
        if (!isset($entity)) {
            throw new NotFoundHttpException('Tipo de vehiculo no encontrado');
        }
        
        $entity->Baja = true;
        $entity->IdUsuarioBaja = Auth::id();
        $entity->FechaHoraBaja = new \DateTime;
        $entity->save();
    }

}
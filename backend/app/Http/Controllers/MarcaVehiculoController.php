<?php

namespace App\Http\Controllers;

use App\FsUtils;
use App\Models\MarcaVehiculo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class MarcaVehiculoController extends Controller
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
        $query = MarcaVehiculo::query();

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
        $filename = 'FSAcceso-Marca-Vehiculo-' . date('Ymd-his');
        $headers = [
            'Descripcion' => 'Nombre',
        ];

        return FsUtils::export($type, $data, $headers, $filename);
    }

    public function show(int $id)
    {
        $entity = MarcaVehiculo::find($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Marca de vehículo no encontrado');
        }

        return $entity;
    }

    public function create()
    {
        MarcaVehiculo::exigirArgs($this->req->all(), ['Descripcion']);
        
        $entity = new MarcaVehiculo($this->req->all());
        $entity->IdMarcaVehic = MarcaVehiculo::getNextId();
        $entity->IdUsuario = Auth::id();
        $entity->FechaHora = new \DateTime;

        $entity->save();

        return $entity->refresh();
    }

    public function update(int $id)
    {
        MarcaVehiculo::exigirArgs($this->req->all(), ['Descripcion']);

        $entity = MarcaVehiculo::find($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Marca de vehiculo no encontrado');
        }

        $entity->fill($this->req->all());

        $entity->save();

        return $entity;
    }

    public function delete(int $id)
    {
        $result = DB::selectOne("SELECT COUNT(*) AS Cantidad FROM Vehiculos WHERE IdMarcaVehic = ?", [$id]);

        if ($result->Cantidad > 0) {
            throw new ConflictHttpException('No se puede dar de baja una marca de vehículo que este siendo usada');
        }

        $entity = MarcaVehiculo::find($id);
        
        if (!isset($entity)) {
            throw new NotFoundHttpException('Marca de vehiculo no encontrado');
        }
        
        $entity->delete();
    }
}
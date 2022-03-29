<?php

namespace App\Http\Controllers;

use App\FsUtils;
use App\Models\MarcaMaquina;
use App\Models\MarcaVehiculo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MarcaMaquinaController extends Controller
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
        $query = MarcaMaquina::query();

        if ($busqueda = $this->req->input('Busqueda')) {
            $query->where('Descripcion', 'like', '%' . $busqueda . '%');
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
        $filename = 'FSAcceso-Marca-Maquinas-' . date('Ymd-his');
        $headers = [
            'Descripcion' => 'Nombre',
        ];

        return FsUtils::export($type, $data, $headers, $filename);
    }

    public function show(int $id)
    {
        $entity = MarcaMaquina::find($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Marca de Maquina no encontrada');
        }

        return $entity;
    }

    public function create()
    {
        MarcaMaquina::exigirArgs($this->req->all(), ['Descripcion']);
        
        $entity = new MarcaMaquina($this->req->all());
        $entity->IdMarcaMaq = MarcaMaquina::getNextId();
        $entity->FechaHora = new \DateTime;
        $entity->IdUsuario = Auth::id();

        $entity->save();

        return $entity->refresh();
    }

    public function update(int $id)
    {
        MarcaMaquina::exigirArgs($this->req->all(), ['Descripcion']);

        $entity = MarcaMaquina::find($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Marca de Maquina no encontrada');
        }

        $entity->fill($this->req->all());
        $entity->save();

        return $entity;
    }
    
    public function delete(int $id)
    {
        $existe = DB::selectOne("SELECT COUNT(*) AS Cantidad FROM Maquinas WHERE IdMarcaMaq = ?", [$id]);

        if ($existe->Cantidad > 0) {
            throw new ConflictHttpException('No se puede dar de baja una marca de máquina que este siendo usada');
        }

        $entity = MarcaMaquina::find($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Marca no encontrada');
        }

        $entity->delete();
    }
}
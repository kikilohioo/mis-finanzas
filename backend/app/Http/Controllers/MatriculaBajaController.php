<?php

namespace App\Http\Controllers;

use App\FsUtils;
use App\Models\MatriculaBaja;
use App\Models\Matricula;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MatriculaBajaController extends Controller
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
        $query = MatriculaBaja::query();

        /*if ($this->req->input('MostrarEliminados', 'false') === 'false') {
            $query->where('Baja', false);
        }*/

        if ($busqueda = $this->req->input('Busqueda')) {
            $query->where('Matricula', 'like', '%' . $busqueda . '%')
                ->orWhere('Observaciones', 'like', '%' . $busqueda . '%');
        }

        $query->orderBy('Matricula', 'ASC');

        $data = $query->get();

        $output = $this->req->input('output', 'json');
        
        if ($output !== 'json') {
            return $this->export($data->toArray(), $output);
        }

        return $this->responsePaginate($data);
    }

    private function export(array $data, string $type)
    {
        $filename = 'FSAcceso-Matricula-Baja-' . date('Ymd-his');
        $headers = [
            'Matricula' => 'Matrícula',
            'Observaciones' => 'Motivo'
        ];

        return FsUtils::export($type, $data, $headers, $filename);
    }

    public function show(int $id)
    {
        $entity = MatriculaBaja::find($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Matricula no encontrada');
        }

        return $entity;
    }

    public function create()
    {
        MatriculaBaja::exigirArgs($this->req->all(), ['Matricula']);
        
        $MatriculaBaja = MatriculaBaja::find($this->req->input('Matricula'));

        if (isset($MatriculaBaja)) {
            throw new NotFoundHttpException('Esta Matricula ya fue ingresada');
        }
        
        if (!Matricula::disponible($this->req->input('Matricula'))) {
            throw new NotFoundHttpException("La matricula esta siendo utilizada o ya fue marcada como no disponible");
        }

        $entity = new MatriculaBaja($this->req->all());
        $entity->IdUsuario = Auth::id();
        $entity->FechaHora = new \DateTime;

        $entity->save();

        return $entity->refresh();
    }

    public function update(int $id)
    {
        MatriculaBaja::exigirArgs($this->req->all(), ['Matricula']);

        $entity = MatriculaBaja::find($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Matricula no encontrada');
        }

        $entity->fill($this->req->all());
        $entity->save();

        return $entity;
    }

    public function delete(int $id)
    {
        $entity = MatriculaBaja::find($id);
        
        if (!isset($entity)) {
            throw new NotFoundHttpException('Matricula no encontrada');
        }
        
        $entity->delete();
    }

}
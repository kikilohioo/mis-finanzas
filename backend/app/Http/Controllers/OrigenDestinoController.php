<?php

namespace App\Http\Controllers;

use App\FsUtils;
use App\Models\OrigenDestino;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OrigenDestinoController extends Controller
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
        $binding = [];
        $sql = "SELECT od.IdOrigDest, od.Nombre
                FROM TRANOrigDest od
                WHERE od.Baja = 0 ";

        if (null !== ($busqueda = $this->req->input('Busqueda'))) {
            $sql .= " AND Nombre LIKE :busqueda";
            $binding[':busqueda'] = '%' . $busqueda . '%';
        }

        $sql .= " ORDER BY od.Nombre ASC";
        
        $items = DB::select($sql, $binding);

        $output = $this->req->input('output', 'json');
        
        if ($output !== 'json') {
            $dataOutput = array_map(function($item) {
                return [
                    'Nombre' => $item->Nombre
                ];
            },$items);

            $filename = 'FSAcceso-Destinos-' . date('Ymd his');

            $headers = [
                'Nombre' => 'Nombre' 
            ];

            return FsUtils::export($output, $dataOutput, $headers, $filename);
        }
        return $this->responsePaginate($items);
    }

    public function show($id)
    {
        $entity = OrigenDestino::find($id);

        if (!isset($entity) || $entity->baja == true) {
            throw new NotFoundHttpException('El destino no existe');
        }

        return $entity;
    }

    public function create()
    {
        $entity = new OrigenDestino();
        $entity->idOrigDest = OrigenDestino::getNextId();

        if(empty($this->req->input('nombre'))) {
            throw new ConflictHttpException('Campo Nombre no encontrado');
        }
        $entity->nombre = $this->req->input('nombre');
        
        $entity->baja = false;
        $entity->fechaHora = new \DateTime;
        $entity->idUsuario = Auth::id();

        $result = $entity->save();

        if ($result == false) {
            throw new ConflictHttpException('Ocurrio un error al dar de alta el destino');
        }

        return $entity->refresh();
    }

    public function update($id)
    {
        $entity = OrigenDestino::find($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Destino no encontrado');
        }

        if(empty($this->req->input('nombre'))) {
            throw new ConflictHttpException('Campo Nombre no encontrado');
        }
        $entity->idUsuario = Auth::id();
        $entity->fill($this->req->all());

        $result = $entity->save();

        if ($result == false) {
            throw new ConflictHttpException('Ocurrio un error al modificar el destino');
        }
    }

    public function delete($id)
    {
        $entity = OrigenDestino::find($id);
        
        if (!isset($entity)) {
            throw new NotFoundHttpException('Destino no encontrado');
        }

        $entity->baja = true;
        $result = $entity->save();
        
        if($result == false) {
            throw new ConflictHttpException("Ocurrio un error al eliminar el destino");
        }
    }
}
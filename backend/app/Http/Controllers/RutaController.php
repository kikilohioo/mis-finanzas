<?php

namespace App\Http\Controllers;

use App\FsUtils;
use App\Models\Ruta;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RutaController extends Controller
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
        $sql = "SELECT CASE r.Tipo
                        WHEN 'N' THEN 'Normal'
                        WHEN 'E' THEN 'Extra'
                    END AS TipoDesc,
                    r.IdRuta, r.Descripcion, o.IdOrigDest as IdOrigenRuta, o.Nombre as Origen,
                    d.IdOrigDest as IdDestinoRuta, d.Nombre as Destino, CONVERT(VARCHAR(5), r.Hora, 108) AS Hora,
                    r.Distancia, r.CantPlazas, r.FechaInicio, r.FechaFin, r.FechaHora, r.IdUsuario, r.Baja
                FROM TRANRutas r
                INNER JOIN TRANOrigDest o ON r.IdOrigenRuta = o.IdOrigDest
                INNER JOIN TRANOrigDest d ON r.IdDestinoRuta = d.IdOrigDest
                WHERE r.Baja = 0 ";

        if (null !== ($busqueda = $this->req->input('Busqueda'))) {
            $sql .= " AND Descripcion LIKE :busqueda";
            $binding[':busqueda'] = '%' . $busqueda . '%';
        }

        $sql .= " ORDER BY r.Descripcion ASC";

        $items = DB::select($sql, $binding);

        $output = $this->req->input('output', 'json');
        
        if ($output !== 'json') {
            $dataOutput = array_map(function($item) {
                return [
                    'Descripcion' => $item->Descripcion,
                    'Origen' => $item->Origen,
                    'Destino' => $item->Destino,
                    'Hora' => $item->Hora,
                    'Tipo' => $item->TipoDesc
                ];
            },$items);

            $filename = 'FSAcceso-Destinos-' . date('Ymd his');

            $headers = [
                'Descripcion' => 'Descripcion',
                'Origen' => 'Origen',
                'Destino' => 'Destino',
                'Hora' => 'Hora',
                'Tipo' => 'Tipo'
            ];

            return FsUtils::export($output, $dataOutput, $headers, $filename);
        }

        return $this->responsePaginate($items);
    }

    public function create()
    {
        Ruta::exigirArgs($this->req->all(),['descripcion', 'idOrigenRuta', 'idDestinoRuta', 'hora', 'distancia', 'cantPlazas', 'fechaInicio', 'fechaFin']);

        $entity = new Ruta();
        $IdRuta = Ruta::getNextId();

        $entity->idRuta = $IdRuta;
        $entity->descripcion = $this->req->input('descripcion');
        $entity->idOrigenRuta = $this->req->input('idOrigenRuta');
        $entity->idDestinoRuta = $this->req->input('idDestinoRuta');
        $entity->hora = $this->req->input('hora');
        $entity->distancia = $this->req->input('distancia');
        $entity->cantPlazas = $this->req->input('cantPlazas');
        $entity->fechaInicio = FsUtils::strToDateByPattern($this->req->input('fechaInicio'));
        $entity->fechaFin = FsUtils::strToDateByPattern($this->req->input('fechaFin'));

        $entity->tipo = $this->req->input('tipo') == null ? 'N' : $this->req->input('tipo');
        $entity->fechaHora = new \DateTime;
        $entity->idUsuario = Auth::id();

        $queryResult = $entity->save();

        if($queryResult == false) {
            throw new ConflictHttpException('Ocurrio un error al dar de alta la ruta');
        }
    }

    public static function show($id)
    {
        $entity = Ruta::find($id);

        if (!isset($entity) || $entity->baja == true) {
            throw new NotFoundHttpException('Ruta no encontrada');
        }

        return $entity;
    }

    public function update($id)
    {
        Ruta::exigirArgs($this->req->all(),['descripcion', 'idOrigenRuta', 'idDestinoRuta', 'hora', 'distancia', 'cantPlazas', 'fechaInicio', 'fechaFin']);

        $entity = Ruta::find($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Ruta no encontrada');
        }

        $entity->descripcion = $this->req->input('descripcion');
        $entity->idOrigenRuta = $this->req->input('idOrigenRuta');
        $entity->idDestinoRuta = $this->req->input('idDestinoRuta');
        $entity->hora = $this->req->input('hora');
        $entity->distancia = $this->req->input('distancia');
        $entity->cantPlazas = $this->req->input('cantPlazas');
        $entity->fechaInicio = FsUtils::strToDateByPattern($this->req->input('fechaInicio'));
        $entity->fechaFin = FsUtils::strToDateByPattern($this->req->input('fechaFin'));
        $entity->tipo = $this->req->input('tipo') == null ? 'N' : $this->req->input('tipo');
        $entity->idUsuario = Auth::id();

        $queryResult = $entity->save();

        if($queryResult == false) {
            throw new ConflictHttpException('Ocurrio un error al modificar la ruta');
        }
    }

    public function delete($id)
    {
        $entity = Ruta::find($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Ruta no encontrada');
        }

        $entity->baja = true;
        $result = $entity->save();

        if($result == false) {
            throw new ConflictHttpException("Ocurrio un error al eliminar la ruta");
        }
    }
}
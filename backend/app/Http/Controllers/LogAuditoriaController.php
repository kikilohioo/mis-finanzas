<?php

namespace App\Http\Controllers;

use App\FsUtils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Usuario;
use App\Models\LogAuditoria;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LogAuditoriaController extends Controller
{

    /**
     * @var Request
     */
    private $req;


    public function __construct(Request $req)
    {
        $this->req = $req;
    }

    public function busqueda()
    {
        $args = $this->req->all();

        $binding = [
            $this->req->input('FechaDesde') . ' 00:00:00',
            $this->req->input('FechaHasta') . ' 23:59:59',
        ];

        $sql = "SELECT "
            . "'func=LogActividadDetalle|usuario=' + IdUsuario + '|fechaHora=' + CONVERT(varchar(24), FechaHora, 121) AS ObjUrl, "
            . "CONVERT(varchar(10), FechaHora, 103) + ' ' + CONVERT(varchar(8), FechaHora, 108) AS FechaHora, "
            . "IdUsuario, "
            . "Modulo, "
            . "Operacion, "
            . "REPLACE(Entidad,'mz', '') AS Entidad, "
            . "CASE "
            . "WHEN Modulo = 'FSAcceso' THEN Observacion "
            . "ELSE EntidadDesc "
            . "END AS EntidadDesc "
            . "FROM LogActividades la "
            . "WHERE la.FechaHora BETWEEN CONVERT(DATETIME, ?, 103) AND CONVERT(DATETIME, ?, 103) ";

        if ($entidad = $this->req->input('Entidad')) {
            $sql .= "AND la.Entidad LIKE ? ";
            $binding[] = "%" . $entidad . "%";
        }
        if ($entidadDesc = $this->req->input('EntidadDesc')) {
            $sql .= "AND (la.EntidadDesc LIKE ? OR (la.Modulo = 'FSAcceso' AND la.Observacion LIKE ?))";
            $binding[] = "'%" . str_replace(' ', '%', $entidadDesc) . "%'";
            $binding[] = "'%" . str_replace(' ', '%', $entidadDesc) . "%'";
        }
        if ($idUsuario = $this->req->input('IdUsuario')) {
            $sql .= "AND la.IdUsuario = ? ";
            $binding[] = $idUsuario;
        }
        if ($operacion = $this->req->input('operacion')) {
            $sql .= "AND la.IdUsuario LIKE ? ";
            $binding[] = "%" . $operacion . "%";
        }

        $sql .= " ORDER BY FechaHora DESC";
        
        $data = DB::select($sql, $binding);

        $output = isset($args['output']);

        if ($output !== 'json' && $output == true) {

            $output = $args['output'];

            $dataOutput = array_map(function($item) {
                return [
                    'IdUsuario' => $item->IdUsuario,
                    'FechaHora' => $item->FechaHora,
                    'Modulo' => $item->Modulo,
                    'Operacion' => $item->Operacion,
                    'Entidad' => $item->Entidad,
                    'Descripcion' => $item->EntidadDesc
                ];
            },$data);

            $filename = 'FSAcceso-LogAuditoria-Consulta-' . date('Ymd his');
            
            $headers = [
                'IdUsuario' => 'Usuario',
                'FechaHora' => 'FechaHora',
                'Modulo' => 'Módulo',
                'Tipo' => 'Operación',
                'Entidad' => 'Entidad',
                'Descripcion' => 'Descripción'
            ];

            return FsUtils::export($output, $dataOutput, $headers, $filename);
        }

        $page = (int)$this->req->input('page', 1);
        $paginate = FsUtils::paginateArray($data, $this->req);
        return $this->responsePaginate($paginate->items(), $paginate->total(), $page);
    }
    
    public function show(string $idUsuario, string $fechaHora)
    {
        $fechaHora = str_replace('%20', ' ', $fechaHora);
        $entity = LogAuditoria::where('IdUsuario', $idUsuario)->where('FechaHora', $fechaHora)->first();

        if (!isset($entity)) {
            throw new NotFoundHttpException('Registro no encontrado');
        }

        return $entity;
    }
    
}
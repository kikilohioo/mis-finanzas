<?php

namespace App\Http\Controllers;

use App\FsUtils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ConsultaDeCumplimientoController extends Controller
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
        $list = [];
        $binding = [];
        
        if(($fechaDesde = $this->req->input('FechaDesde')) !== null && ($fechaHasta = $this->req->input('FechaHasta')) !== null ) {
            $fechaDesde = FsUtils::strToDateByPattern($fechaDesde);
            $fechaHasta = FsUtils::strToDateByPattern($fechaHasta);

            if ($fechaHasta < $fechaDesde) {
                throw new ConflictHttpException("El campo Fecha Hasta no puede ser menor al campo Fecha Desde");
            }

            $dias = ((int)FsUtils::datetime_diff($fechaDesde, $fechaHasta, '%a')) + 1;

            for ($dia = 0; $dia < $dias; $dia++) {
                $fecha = strtotime($fechaDesde."+ $dia days");
                $date = date("d/m/Y", $fecha);
                $binding[':fecha1'] = $date;
                $binding[':fecha2'] = $date;

                $sql = "SELECT
                             CONVERT(varchar(10), E.FechaHora, 103) AS Fecha,
                             TT.Nombre AS Turno,
                             V.Serie + LTRIM(RTRIM(STR(V.Numero))) AS Vehiculo,
                             CONVERT(varchar(8), TTH.Horario, 108) AS HoraEstipulada,
                             CONVERT(varchar(8), E.FechaHora, 108) AS HoraReal,
                             DATEDIFF(minute, CONVERT(time, TTH.Horario), CONVERT(time, E.FechaHora)) AS Cumplimiento
                        FROM TRANTurnos TT
                        INNER JOIN TRANTurnosHorarios TTH ON TT.idTurno = TTH.idTurno
                        INNER JOIN Vehiculos V ON V.IdOrigDest = TT.IdOrigDest AND V.ControlLlegada = 1
                        INNER JOIN Eventos E ON E.Numero = V.Numero AND e.Serie = V.Serie AND E.Entidad = 'V'
                        WHERE TT.Baja = 0
                        AND TTH.Baja = 0
                        AND E.Estado = 1
                        AND E.TipoOperacion = 0
                        AND E.idAcceso = :IdAcceso
                        AND E.fechaHora >= CONVERT(datetime, :fecha1 + ' ' + CONVERT(varchar(8), DATEADD(n, -30, TTH.Horario), 108), 103)
                        AND E.fechaHora <= CONVERT(datetime, :fecha2 + ' ' + CONVERT(varchar(8), DATEADD(n, 30, TTH.Horario), 108), 103)
                        ORDER BY TTH.horario, TT.Nombre";

                $binding[':IdAcceso'] = (int)$this->req->input('IdAcceso');
                
                $list = array_merge($list, DB::select($sql, $binding));

                $turnos = "";
                foreach ($list as $item) {
                    if (!empty($turnos)) {
                        $turnos .= ":itemTurno";
                        $binding[':itemTurno'] = $item->Turno;
                    }
                }
                
                $sql = "SELECT :fecha AS Fecha,
                        tt.Nombre AS Turno, CONVERT(varchar(8), tth.Horario, 108) AS HoraEstipulada
                        FROM TRANTurnos tt
                        INNER JOIN TRANTurnosHorarios tth ON tt.IdTurno = tth.IdTurno ";
    
                if (!empty($turnos)) {
                    $sql .= "WHERE tt.Nombre NOT IN(:turnos)";
                    $binding[':turnos'] = [$turnos];
                    $list = array_merge($list, DB::select($sql, $binding));
                }
            }

            if(($output = $this->req->input('output')) !== null) {
                if ($output !== 'json') {
                    $dataOutput = array_map(function($item) {
                        return [
                            'Fecha' => $item->Fecha,
                            'Turno' => $item->Turno,
                            'Vehiculo' => $item->Vehiculo,
                            'HoraEstipulada' => $item->HoraEstipulada,
                            'HoraReal' => $item->HoraReal,
                            'Cumplimiento' => $item->Cumplimiento
                        ];
                    },$list);
        
                    $filename = 'FSAcceso-Consultas-De-Cumplimiento-' . date('Ymd his');
                    
                    $headers = [
                        'Fecha' => 'Fecha',
                        'Turno' => 'Turno',
                        'Vehiculo' => 'Vehículo',
                        'HoraEstipulada' => 'Hora de entrada estipulada',
                        'HoraReal' => 'Hora de entrada real',
                        'Cumplimiento' => 'Cumplimiento',
                    ];
                }
                $a = $dataOutput;
                return FsUtils::export($output, $dataOutput, $headers, $filename);
            }
            return $this->responsePaginate($list);
        }
    }
 
    // Método no implementado al momento, se agrega lógica.
    public static function listarTurnos() {
        $sql = "SELECT TT.*, TTH.horario
                FROM TRANTurnos TT INNER JOIN TRANTurnosHorarios TTH ON TT.idTurno = TTH.idTurno
                WHERE TT.baja = 0
                AND TTH.baja = 0
                ORDER BY TTH.horario";
        
        $data = DB::select($sql);
        return $data;
    }
}
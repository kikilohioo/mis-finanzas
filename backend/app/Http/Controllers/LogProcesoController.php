<?php

namespace App\Http\Controllers;

use App\FsUtils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Usuario;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LogProcesoController extends Controller
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
        
        $binding = [];

        $sql = "SELECT "
                    . "CASE"
                    . "     WHEN Entidad = 'P' AND pf.Transito = 0 THEN 'func=AdmPersonas|Documento=' + pf.Documento + '|IdTipoDocumento=' + LTRIM(RTRIM(STR(pf.IdTipoDocumento))) "
                    . "     WHEN Entidad = 'P' AND pf.Transito = 1 THEN 'func=AdmVisitantes|Documento=' + pf.Documento + '|IdTipoDocumento=' + LTRIM(RTRIM(STR(pf.IdTipoDocumento))) "
                    . "     WHEN Entidad = 'M' THEN 'func=AdmMaquinas|NroSerie=' + m.NroSerie "
                    . "     WHEN Entidad = 'V' THEN 'func=AdmVehiculos|Serie=' + v.Serie + '|Numero=' + LTRIM(RTRIM(STR(v.Numero))) "
                    . "     WHEN Entidad = 'E' THEN 'func=AdmEmpresas|Documento=' + e.Documento + '|IdTipoDocumento=' + LTRIM(RTRIM(STR(e.IdTipoDocumento))) "
                    . "END AS ObjUrl, "
                    . "CONVERT(varchar(10), l.Proceso, 103) + ' ' + CONVERT(varchar(5), l.Proceso, 108) AS FechaHoraProceso, "
                    . "l.Ordinal, "
                    . "CASE"
                    . "     WHEN Entidad = 'P' THEN 'Persona' "
                    . "     WHEN Entidad = 'M' THEN 'Máquina' "
                    . "     WHEN Entidad = 'V' THEN 'Vehículo' "
                    . "     WHEN Entidad = 'E' THEN 'Empresa' "
                    . "END AS Entidad, "
                    . "CONVERT(varchar(10), l.FechaHora, 103) + ' ' + CONVERT(varchar(5), l.FechaHora, 108) AS FechaHora, "
                    . "CASE"
                    . "     WHEN Entidad = 'P' THEN dbo.Mask(pf.Documento, td.Mascara, 1, 1) "
                    . "     WHEN Entidad = 'M' THEN m.NroSerie "
                    . "     WHEN Entidad = 'V' THEN v.Serie + ' ' + RTRIM(LTRIM(STR(v.Numero))) "
                    . "     WHEN Entidad = 'E' THEN dbo.Mask(e.Documento, tde.Mascara, 1, 1) "
                    . "END AS Identificacion, "
                    . "CASE"
                    . "     WHEN Entidad = 'P' THEN pf.NombreCompleto "
                    . "     WHEN Entidad = 'M' THEN mm.Descripcion + ' ' + m.Modelo + ' (' + tm.Descripcion + ')' "
                    . "     WHEN Entidad = 'V' THEN mv.Descripcion + ' ' + v.Modelo + ' (' + tv.Descripcion + ')' "
                    . "     WHEN Entidad = 'E' THEN e.Nombre "
                    . "END AS Detalle, "
                    . "l.Empresa,"
                    . "l.Observaciones "
                . "FROM LogProceso l "
                . "LEFT JOIN PersonasFisicas pf ON l.Documento = pf.Documento AND l.IdTipoDocumento = pf.IdTipoDocumento "
                . "LEFT JOIN TiposDocumento td ON pf.IdTipoDocumento = td.IdTipoDocumento "
                . "LEFT JOIN Maquinas m ON l.NroSerie = m.NroSerie "
                . "LEFT JOIN MarcasMaquinas mm ON m.IdMarcaMaq = mm.IdMarcaMaq "
                . "LEFT JOIN TiposMaquinas tm ON m.IdTipoMaq = tm.IdTipoMaquina "
                . "LEFT JOIN Vehiculos v ON l.Serie = v.Serie AND l.Numero = v.Numero "
                . "LEFT JOIN MarcasVehiculos mv ON v.IdMarcaVehic = mv.IdMarcaVehic "
                . "LEFT JOIN TiposVehiculos tv ON v.IdTipoVehiculo = tv.IdTipoVehiculo "
                . "LEFT JOIN Empresas e ON l.Documento = e.Documento AND l.IdTipoDocumento = e.IdTipoDocumento "
                . "LEFT JOIN TiposDocumento tde ON e.IdTipoDocumento = tde.IdTipoDocumento 
                where 1 = 1";
        
        if(!empty($args['FechaDesde'])) {
            $sql .= " AND CONVERT(date, l.FechaHora , 103) >= CONVERT(date, :FechaDesde, 103)";
            $binding[':FechaDesde'] = $args['FechaDesde']. ' 00:00:00';
        }
        
        if(!empty($args['FechaHasta'])) {
            $sql .= " AND CONVERT(date, l.FechaHora , 103) <= CONVERT(date, :FechaHasta, 103)";
            $binding[':FechaHasta'] = $args['FechaHasta']. ' 23:59:59';
        }

        $sql .= "Order by l.Proceso, l.Ordinal ASC";
        
        $data = DB::select($sql, $binding);

        $output = isset($args['output']);

        if ($output !== 'json' && $output == true) {

            $output = $args['output'];

            $dataOutput = array_map(function($item) {
                return [
                    'Proceso' => '',// $item->Proceso,
                    'Ordinal' => '',// $item->Ordinal,
                    'FechaHora' => '',// $item->FechaHora,
                    'Entidad' => '',// $item->Entidad,
                    'Identificacion' => '',// $item->Identificacion,
                    'Detalle' => '',// $item->Detalle,
                    'Observaciones' => '',// $item->Observaciones,
                    'Empresa' => ''// $item->Empresa
                ];
            },$data);

            $filename = 'FSAcceso-LogVerificacion-Consulta-' . date('Ymd his');
            
            $headers = [
                'Proceso' => 'Proceso',
                'Ordinal' => 'Ordinal',
                'FechaHora' => 'FechaHora',
                'Entidad' => 'Entidad',
                'Identificacion' => 'Identificacion',
                'Detalle' => 'Detalle',
                'Observaciones' => 'Observaciones',
                'Empresa' => 'Empresa'
            ];

            if (empty($dataOutput)) {
                return ['message' =>  'No hay datos para exportar'];
            }

            return FsUtils::export($output, $dataOutput, $headers, $filename);
        }

        return $this->responsePaginate($data);
    }
    
}
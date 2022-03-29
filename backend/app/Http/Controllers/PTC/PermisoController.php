<?php

namespace App\Http\Controllers\PTC;

use App\ExcelReaders\OTExcelReader;
use App\ExcelReaders\PermisoExcelReader;
use App\FsUtils;
use App\Http\Controllers\Controller;
use App\Mails\PTC\PTCCreado;
use App\Mails\PTC\PTCModificado;
use App\Mails\PTC\PTCAprobar;
use App\Mails\PTC\PTCAutorizar;
use App\Mails\PTC\PTCEjecutar;
use App\Mails\PTC\PTCFinalizarEjecucion;
use App\Mails\PTC\PTCTomar;
use App\Mails\PTC\PTCFinalizarMediciones;
use App\Mails\PTC\PTCRechazar;
use App\Mails\PTC\PTCRechazarSolicitud;
use App\Mails\PTC\PTCCerrar;
use App\Mails\PTC\PTCSolicitarRevalidacion;
use App\Mails\PTC\PTCEjecutarRevalidacion;
use App\Mails\PTC\PTCMediciones;
use App\Mails\PTC\PTCRevisionLiberacion;
use App\Mails\PTC\PTCAprobarRevisionLiberacion;
use App\Models\Empresa;
use App\Models\LogAuditoria;
use App\Models\PTC\PTC;
use App\Models\Usuario;
use DateTime;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use stdClass;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


class PermisoController extends Controller
{
    
    /**
     * @var Request
     */
    private $req;

    /**
     * @var Usuario
     */
    private $user;

    public function __construct(Request $req)
    {
        $this->req = $req;

        $exception = [
            '/PermisoDeTrabajo/cerrarSiFechaFinPrevistaVencioTodos',
            '/PermisoDeTrabajo/marcarComoPendienteDeRevalidacionTodos',
            '/PermisoDeTrabajo/marcarComoVencidoTodos',
        ];
        $result = array_filter($exception, function ($v) { return $this->req->getPathInfo() === $v; });
        if (count($result) === 0) {
            $this->user = auth()->user();
        }
    }

    public function index() {

        $Args = $this->req->all();

        $Usuario = $this->user;
        
        $bindings = [];
        $estados = [];

        $estado = " CASE
                        WHEN ptc.IdEstado <> 'E' AND ptc.IdEstado <> 'L' THEN ptce.Nombre
                        WHEN ptc.IdEstado = 'L' THEN ptce.Nombre
                        ELSE
                            CASE 
                                WHEN (ptc.RequiereBloqueo = 1 AND NULLIF(ptc.RequiereBloqueoEjecutado, 0) IS NULL) THEN 'Esperando bloqueo'
                                WHEN (ptc.RequiereInspeccion = 1 AND NULLIF(ptc.RequiereInspeccionEjecutado, 0) IS NULL) THEN 'Esperando inspección interna'
                                WHEN (ptc.RequiereDrenarPurgar = 1 AND NULLIF(ptc.RequiereDrenarPurgarEjecutado, 0) IS NULL) THEN 'Esperando drenaje'
                                WHEN (ptc.RequiereLimpieza = 1 AND NULLIF(ptc.RequiereLimpiezaEjecutado, 0) IS NULL) THEN 'Esperando limpieza'
                                WHEN (ptc.RequiereMedicion = 1 AND NULLIF(ptc.RequiereMedicionEjecutado, 0) IS NULL AND ptc.EsperandoPorPTCVinculado = 1) THEN 'Esperando por PT asociado'
                                WHEN (ptc.RequiereMedicion = 1 AND NULLIF(ptc.RequiereMedicionEjecutado, 0) IS NULL) THEN 'Esperando medición'
                                ELSE 'En curso'
                            END
                    END ";
        
        $sql = "SELECT 
                    CASE
                        WHEN ptc.IdEstado = 'CPR' THEN 'closed resp'
                        WHEN ptc.IdEstado = 'CSE' THEN 'closed without ex'
                        WHEN ptc.IdEstado = 'E' THEN 
                            CASE
                                WHEN (ptc.RequiereMedicion = 1 AND NULLIF(ptc.RequiereMedicionEjecutado, 0) IS NULL AND (ptc.EsperandoPorPTCVinculado IS NULL OR ptc.EsperandoPorPTCVinculado = 0)) THEN 'waiting for measurement'
                                WHEN (ptc.RequiereBloqueo = 1 AND NULLIF(ptc.RequiereBloqueoEjecutado, 0) IS NULL) OR (ptc.RequiereDrenarPurgar = 1 AND NULLIF(ptc.RequiereDrenarPurgarEjecutado, 0) IS NULL) OR (ptc.RequiereLimpieza = 1 AND NULLIF(ptc.RequiereLimpiezaEjecutado, 0) IS NULL) OR (ptc.RequiereInspeccion = 1 AND NULLIF(ptc.RequiereInspeccionEjecutado, 0) IS NULL) THEN 'waiting for blocking action'
                                ELSE 'taken'
                            END
                        WHEN ptc.IdEstado = 'I' THEN 'entered'
                        WHEN ptc.IdEstado = 'RVS' THEN 'entered rejection'
                        WHEN ptc.IdEstado = 'L' THEN 'released'
                        WHEN ptc.IdEstado = 'EJE' THEN 'executing'
                        WHEN ptc.IdEstado = 'EJD' THEN 'executed'
                        WHEN ptc.IdEstado = 'CP' THEN 'partially closed'
                        WHEN ptc.IdEstado = 'PR' THEN 'revalidation pending'
                        WHEN ptc.IdEstado = 'RS' THEN 'revalidation requested'
                        WHEN ptc.IdEstado = 'RL' THEN 'revalidation review'
                        WHEN ptc.IdEstado = 'PRL' THEN 'revalidation pending released'
                        WHEN ptc.IdEstado = 'RSL' THEN 'revalidation requested released'
                        WHEN ptc.IdEstado = 'PL' THEN 'release in process'
                        WHEN ptc.IdEstado = 'V' THEN 'expired'
                        ELSE 'none'
                    END AS FsRC,
                    ptc.NroPTC,
                    ptc.EsDePGP,
                    ptc.IdEquipoMedicion1,
                    ptc.NroOT,
                    ptc.IdUsuario,
                    ptc.Descripcion AS DescripcionTrabajo,
                    ptc.UbicacionFuncional,
                    ptc.RequiereBloqueo,
                    ptc.RequiereMedicion,
                    ptc.RequiereInspeccion,
                    ptc.RequiereDrenarPurgar,
                    ptc.RequiereLimpieza,
                    ptc.RequiereBloqueoEjecutado,
                    ptc.RequiereDrenarPurgarEjecutado,
                    ptc.RequiereLimpiezaEjecutado,
                    ptc.PlanBloqueoExistente,
                    ptc.PlanDrenajeExistente,
                    ptc.PlanLimpiezaExistente,
                    e.Nombre AS NombreEmpresa,
                    u.Nombre AS NombreUsuario,
                    a.Nombre AS NombreArea, 
                    ptc.TagEquipo, ";

        $sql .= "   CASE  ";    
        
        
        if ($this->user->usuarioEsOperador($Usuario)) {
            $sql .= " WHEN ptc.IdEstado = 'I' THEN -4 ";
            $sql .= " WHEN ptc.IdEstado = 'RS' THEN -3 ";
            $sql .= " WHEN ptc.IdEstado = 'RL' THEN -2 ";
            $sql .= " WHEN ptc.IdEstado = 'RSL' THEN -1 ";
            $sql .= " WHEN ptc.IdEstado = 'V' THEN 20 ";
        }

        if ($this->user->usuarioEsAprobador($Usuario)) {
            $sql .= " WHEN ptc.IdEstado = 'PL' THEN -1 ";
            $sql .= " WHEN ptc.IdEstado = 'EJE' THEN 2 ";
            $sql .= " WHEN ptc.IdEstado = 'L' THEN 3 ";
            $sql .= " WHEN ptc.IdEstado = 'E' THEN 4 ";
            $sql .= " WHEN ptc.IdEstado = 'RVS' THEN 5 ";
        }

        if ($this->user->usuarioEsSoloSYSO($Usuario)) {
            $sql .= " WHEN ptc.IdEstado = 'E' AND (ptc.RequiereMedicion = 1 AND NULLIF(ptc.RequiereMedicionEjecutado, 0) IS NULL) THEN -1 ";
            $sql .= " WHEN ptc.IdEstado = 'E' THEN ptce.Orden + 5 ";
        }

        if ($this->user->usuarioEsSoloSolicitante($Usuario)) {
            $sql .= " WHEN ptc.IdEstado = 'RVS' THEN 1 ";
            $sql .= " WHEN ptc.IdEstado = 'L' THEN 2 ";
            $sql .= " WHEN ptc.IdEstado = 'PR' THEN 3 ";
            $sql .= " WHEN ptc.IdEstado = 'PRL' THEN 4 ";
            $sql .= " WHEN ptc.IdEstado = 'EJE' THEN 5 ";
            $sql .= " WHEN ptc.IdEstado = 'E' THEN 6 ";
            $sql .= " WHEN ptc.IdEstado = 'PL' THEN 7 ";
            $sql .= " WHEN ptc.IdEstado = 'I' THEN 8 ";
        }

        $sql .= "   WHEN ptc.IdEstado = 'E' THEN CASE 
                            WHEN (ptc.RequiereBloqueo = 1 AND NULLIF(ptc.RequiereBloqueoEjecutado, 0) IS NULL) THEN ptce.Orden + 0.1
                            WHEN (ptc.RequiereInspeccion = 1 AND NULLIF(ptc.RequiereInspeccionEjecutado, 0) IS NULL) THEN ptce.Orden + 0.2
                            WHEN (ptc.RequiereDrenarPurgar = 1 AND NULLIF(ptc.RequiereDrenarPurgarEjecutado, 0) IS NULL) THEN ptce.Orden + 0.3
                            WHEN (ptc.RequiereLimpieza = 1 AND NULLIF(ptc.RequiereLimpiezaEjecutado, 0) IS NULL) THEN ptce.Orden + 0.4
                            WHEN (ptc.RequiereMedicion = 1 AND NULLIF(ptc.RequiereMedicionEjecutado, 0) IS NULL) THEN ptce.Orden + 0.5
                            ELSE ptce.Orden
                        END 
                    ELSE ptce.Orden
                END AS SortLevel,";

        if ($this->user->usuarioEsSoloSolicitante($Usuario)) {
            $sql .= " ptce.Nombre AS Estado, ";
        } else {
            $sql .= " " . $estado . " AS Estado,";
        }

        $sql .= " e.Documento + '-' + LTRIM(RTRIM(STR(ptc.IdTipoDocumento))) AS IdEmpresa,
                                ptc.FechaHoraComienzoPrev,
                                CONVERT(varchar(10), ptc.FechaHoraComienzoPrev, 103) + ' ' + CONVERT(varchar(8), ptc.FechaHoraComienzoPrev, 108) AS InicioPrevisto,
                                CONVERT(varchar(10), ptc.FechaHoraFinPrev, 103) + ' ' + CONVERT(varchar(8), ptc.FechaHoraFinPrev, 108) AS FinalPrevisto,
                                e.Nombre AS Empresa,
                                CASE
                                WHEN ((SELECT COUNT(*) FROM PTCPTCTipos WHERE ptc.NroPTC = NroPTC) + 
                                        (SELECT COUNT(*) FROM PTCPTCRiesgos WHERE ptc.NroPTC = NroPTC)) > 0 THEN 1
                                ELSE 0
                                END AS TieneRiesgosAsociados
                            FROM PTC ptc
                            INNER JOIN PTCAreas a ON ptc.IdArea = a.IdArea
                            INNER JOIN Usuarios u ON ptc.IdUsuario = u.IdUsuario
                            INNER JOIN Empresas e ON e.Documento = ptc.Documento AND e.IdTipoDocumento = ptc.IdTipoDocumento 
                            INNER JOIN PTCEstados ptce ON ptc.IdEstado = ptce.Codigo";

        if ($this->user->usuarioEsOperador($Usuario) || $this->user->usuarioEsAprobador($Usuario)) {
            $sql .= " INNER JOIN PTCAreasUsuarios ptcau ON ptc.IdArea = ptcau.IdArea AND ptcau.IdUsuario = :IdUsuario ";
            $bindings[':IdUsuario'] = $Usuario->IdUsuario;
        }
        
        $sql .= " WHERE ptc.Baja = 0 ";

        if ($this->user->usuarioEsSYSO($Usuario)) {
            array_push($estados, "'E'", "'EJE'");
        }

        if ($this->user->usuarioEsAprobador($Usuario)) {
            array_push($estados, "'PL'", "'EJE'", "'L'", "'E'", "'RVS'", "'CP'");
        }

        if ($this->user->usuarioEsOperador($Usuario)) {
            array_push($estados, "'I'", "'RS'", "'RSL'", "'E'", "'EJD'", "'PL'");
        }

        if ($this->user->usuarioEsSolicitante($Usuario)) {
            array_push($estados, "'RVS'", "'L'", "'PR'", "'PRL'", "'EJE'", "'E'", "'PL'", "'I'", "'CP'");
        }

        if (!empty($Args['Busqueda'])) {
            $bcont = 0;
            $busquedas = explode('|', $Args['Busqueda']);

            $sql .= " AND (" ;
            $i = 0;
            foreach ($busquedas as $b) {
                if ($bcont > 0) {
                    $sql .= " OR ";
                }

                $b = trim($b);

                $i++;
                $bindings[':b'.$i] = $b;
                $sql .= " (LTRIM(RTRIM(STR(ptc.NroPTC))) = :b".$i." OR ";

                $i++;
                $bindings[':b'.$i] = "%" . $b . "%";
                $sql .= $estado." COLLATE Latin1_general_CI_AI LIKE :b".$i." OR ";

                $i++;
                $bindings[':b'.$i] = "%" . $b . "%";
                $sql .= "ptc.Descripcion COLLATE Latin1_general_CI_AI LIKE :b".$i." OR ";

                $i++;
                $bindings[':b'.$i] = "%" . $b . "%";
                $sql .= "ptc.UbicacionFuncional COLLATE Latin1_general_CI_AI LIKE :b".$i." OR ";

                $i++;
                $bindings[':b'.$i] = "%" . $b . "%";
                $sql .= "ptc.PlanBloqueoExistente COLLATE Latin1_general_CI_AI LIKE :b".$i." OR ";

                $i++;
                $bindings[':b'.$i] = "%" . $b . "%";
                $sql .= "ptc.PlanDrenajeExistente COLLATE Latin1_general_CI_AI LIKE :b".$i." OR ";

                $i++;
                $bindings[':b'.$i] = "%" . $b . "%";
                $sql .= "ptc.PlanLimpiezaExistente COLLATE Latin1_general_CI_AI LIKE :b".$i." OR ";
                
                $i++;
                $bindings[':b'.$i] = "%" . $b . "%";
                $sql .= "u.Nombre COLLATE Latin1_general_CI_AI LIKE :b".$i." OR ";

                $i++;
                $bindings[':b'.$i] = "%" . $b . "%";
                $sql .= "e.Nombre COLLATE Latin1_general_CI_AI LIKE :b".$i." OR ";

                $i++;
                $bindings[':b'.$i] = "%" . $b . "%";
                $sql .= "a.Nombre COLLATE Latin1_general_CI_AI LIKE :b".$i.")";

                $bcont++;
            }

            $sql .= " )" ;
        }
        

        if (isset($Args['IdArea']) && !empty($Args['IdArea'])) { 
			$bindings[':IdArea'] = $Args['IdArea'];
            $sql .= " AND ptc.IdArea = :IdArea";
		}

        if ($Args['CustomFilter'] != 'Todos') {
            if ($Args['CustomFilter'] == -1) {
                $sql .= " AND (ptc.EsDePGP = 0 OR ptc.EsDePGP IS NULL) ";
            }
            else {
                $sql .= " AND ptc.EsDePGP = 1 AND ptc.AnhoPGP = :CustomFilter ";
                $bindings[':CustomFilter'] = $Args['CustomFilter'];
            }
        }
        
        // Chequear permisos sobre empresa para usuarios solo solicitante
        if ($this->user->usuarioEsSoloSolicitante($Usuario)) {
            $empresa = Empresa::loadBySession($this->req);
            $bindings[':doc_empresa'] = $empresa->Documento;
            $bindings[':tipo_doc_empresa'] = $empresa->IdTipoDocumento;
            $sql .= " AND ptc.Documento = :doc_empresa AND ptc.IdTipoDocumento = :tipo_doc_empresa ";
        }
        
        $cantPermisos = DB::selectOne("SELECT COUNT(*) AS CantPermisosVencidos FROM PTC WHERE IdEstado = 'V' AND IdUsuario = :IdUsuario1", [':IdUsuario1' => $this->user->IdUsuario])->CantPermisosVencidos;

        if ($cantPermisos > 0) {
            $sql .= " AND ptc.IdEstado = 'V' AND ptc.IdUsuario = :IdUsuario2 ";
            $bindings[':IdUsuario2'] = $this->user->IdUsuario;
        } else {
            if (isset($Args['CustomCheckbox']) && $Args['CustomCheckbox'] == 'false') {
                if (!$this->user->usuarioEsSoloSYSO()) {
                    $sql .= " AND ptc.IdEstado NOT IN ('CPR', 'CSE') ";
                }
            } else {
                array_push($estados, "'CPR'", "'CSE'");
            }
        }

        if ($this->user->IdUsuario != 'fsa') {
            if ($this->user->usuarioEsSoloSolicitante()) {
                
            } else if ($this->user->usuarioEsSoloSYSO()) {
                $sql .= " AND ((ptc.RequiereMedicion = 1 AND (NULLIF(ptc.RequiereMedicionEjecutado, 0) IS NULL) AND ptc.IdEstado = 'E') OR ptc.IdEstado IN (".implode(', ', $estados).")) ";
            } else if ($this->user->usuarioSinRol()) {
                throw new HttpException(409, "El usuario no tiene permisos para realizar esta acción");
            }
        }

        $sql .= ' order by SortLevel, ptc.NroPTC asc ';

        $registros = DB::select($sql, $bindings);

        $i = 0;
        foreach($registros as $registro){
            $registros[$i]->PTCCondicionAmbiental = $this->showMediciones($registro->NroPTC);

            $i++;
        }

        $output = $this->req->input('output', 'json');
        if ($output !== 'json') {
            $dataOutput = array_map(function($item) {
                return [
                    'Estado' => $item->Estado,
                    'NroPTC' => $item->NroPTC,
                    'UbicacionFuncional' => $item->UbicacionFuncional,
                    'DescripcionTrabajo' => $item->DescripcionTrabajo,
                    'TagEquipo' => $item->TagEquipo,
                    'NombreUsuario' => $item->NombreUsuario,
                    'NombreEmpresa' => $item->Empresa,
                    'NombreArea' => $item->NombreArea,
                    'PlanBloqueoExistente' => $item->PlanBloqueoExistente,
                    'PlanDrenajeExistente' => $item->PlanDrenajeExistente,
                    'PlanLimpiezaExistente' => $item->PlanLimpiezaExistente,
                    'InicioPrevisto' => $item->InicioPrevisto,
                    'FinalPrevisto' => $item->FinalPrevisto
                ];
            },$registros);
            return $this->export($dataOutput, $output);
        }

        $page = (int)$this->req->input('page', 1);        
        $paginate = FsUtils::paginateArray($registros, $this->req);
        return $this->responsePaginate($paginate->items(), $paginate->total(), $page);
    }

    private function export(array $data, string $type) {
        $filename = 'FSAcceso-Permisos-de-Trabajo-' . date('Ymd his');
        $headers = [
            'Estado' => 'Estado',
            'NroPTC' => 'N° PTC',
            'UbicacionFuncional' => 'Descripción de Ubicación Funcional',
            'DescripcionTrabajo' => 'Descripción del Trabajo',
            'TagEquipo' => 'Tag Equipo',
            'NombreUsuario' => 'Solicitante',
            'NombreEmpresa' => 'Empresa',
            'NombreArea' => 'Área',
            'PlanBloqueoExistente' => 'Plan de Bloqueo',
            'PlanDrenajeExistente' => 'Plan de Drenaje',
            'PlanLimpiezaExistente' => 'Plan de Limpieza',
            'InicioPrevisto' => 'Inicio Previsto',
            'FinalPrevisto' => 'Final Previsto'
        ];
        return FsUtils::export($type, $data, $headers, $filename);
    }

    public function show(int $id){
        
        $entity = $this->show_interno($id);
        $entity = json_decode($entity);

        if(!isset($entity)){
            throw new NotFoundHttpException('El permiso no existe');
        }

        return $this->response($entity);
    }
    
    private function show_interno($id){

        $bindings = [];
        $sql = "SELECT ptc.NroPTC, "
                . "ptc.IdEstado, "
                . "CASE
                            WHEN ptc.IdEstado = 'CE' THEN 'engagement'
                            WHEN ptc.IdEstado = 'CPE' THEN 'closed ex'
                            WHEN ptc.IdEstado = 'CPR' THEN 'closed resp'
                            WHEN ptc.IdEstado = 'CSE' THEN 'closed without ex'
                            WHEN ptc.IdEstado = 'E' THEN 
                                CASE
                                    WHEN (ptc.RequiereMedicion = 1 AND NULLIF(ptc.RequiereMedicionEjecutado, 0) IS NULL AND (ptc.EsperandoPorPTCVinculado IS NULL OR ptc.EsperandoPorPTCVinculado = 0)) THEN 'waiting for measurement'
                                    WHEN (ptc.RequiereBloqueo = 1 AND NULLIF(ptc.RequiereBloqueoEjecutado, 0) IS NULL) OR (ptc.RequiereDrenarPurgar = 1 AND NULLIF(ptc.RequiereDrenarPurgarEjecutado, 0) IS NULL) OR (ptc.RequiereLimpieza = 1 AND NULLIF(ptc.RequiereLimpiezaEjecutado, 0) IS NULL) OR (ptc.RequiereInspeccion = 1 AND NULLIF(ptc.RequiereInspeccionEjecutado, 0) IS NULL) THEN 'waiting for blocking action'
                                    ELSE 'taken'
                                END
                            WHEN ptc.IdEstado = 'I' THEN 'entered'
                            WHEN ptc.IdEstado = 'RVS' THEN 'entered rejection'
                            WHEN ptc.IdEstado = 'L' THEN 'released'
                            WHEN ptc.IdEstado = 'EJE' THEN 'executing'
                            WHEN ptc.IdEstado = 'EJD' THEN 'executed'
                            WHEN ptc.IdEstado = 'CP' THEN 'partially closed'
                            WHEN ptc.IdEstado = 'PR' THEN 'revalidation pending'
                            WHEN ptc.IdEstado = 'RS' THEN 'revalidation requested'
                            WHEN ptc.IdEstado = 'RL' THEN 'revalidation review'
                            WHEN ptc.IdEstado = 'PRL' THEN 'revalidation pending released'
                            WHEN ptc.IdEstado = 'RSL' THEN 'revalidation requested released'
                            WHEN ptc.IdEstado = 'PL' THEN 'release in process'
                            WHEN ptc.IdEstado = 'V' THEN 'expired'
                            ELSE 'none'
                        END AS FsRC, "
                . "'' AS NroOT, "
                . "ptc.IdUsuario, "
                . "ptc.CierreTapa, "
                . "ptc.AperturaTapa, "
                . "u.Nombre as NombreSolicitante, "
                . "ptc.TelefonoContacto, "
                . "ptc.CantidadPersonas, "
                . "e.MdP AS EmpresaEsMdP, "
                . "ptc.Documento + '-' + LTRIM(RTRIM(STR(ptc.IdTipoDocumento))) AS IdEmpresa, "
                . "e.Nombre AS NombreEmpresa, "
                . "ptc.IdArea, "
                . "a.Nombre AS NombreArea, "
                . "ptc.Descripcion AS DescripcionTrabajo, "
                . "ptc.UbicacionFuncional, "
                . "CONVERT(varchar(10), FechaHoraComienzoPrev, 103) AS FechaComienzoPrev, "
                . "CONVERT(varchar(5), FechaHoraComienzoPrev, 108) AS HoraComienzoPrev, "
                . "CONVERT(varchar(10), FechaHoraFinPrev, 103) AS FechaFinPrev, "
                . "CONVERT(varchar(5), FechaHoraFinPrev, 108) AS HoraFinPrev, "
                . "IdEquipoMedicion1, "
                . "IdEquipoMedicion2, "
                . "ptc.PTCTiposOtroObs, "
                . "ptc.PTCRiesgosOtroObs, "
                . "ptc.PTCEquiposOtroObs, "
                . "ptc.CondAmbNombre, "
                . "ptc.CondAmbCargo, "
                . "ptc.CondAmbEquipo, "
                . "ptc.PermitirUtilizarMediciones, "
                . "ptc.CondAmbFechaHora, "
                . "ptc.CondAmbVigFechaHora, "
                . "CONVERT(varchar(10), ptc.CondAmbVigFechaHora, 103) AS CondAmbVigenciaFecha, "
                . "CONVERT(varchar(5), ptc.CondAmbVigFechaHora, 108) AS CondAmbVigenciaHora, "
                . "ptc.EquiposObs, "
                . "ptc.RequiereBloqueo, "
                . "ptc.RequiereBloqueoEjecutado, "
                . "ptc.RequiereDrenarPurgar, "
                . "ptc.RequiereDrenarPurgarEjecutado, "
                . "ptc.RequiereLimpieza, "
                . "ptc.RequiereLimpiezaEjecutado, "
                . "ptc.RequiereInspeccion, "
                . "ptc.RequiereInspeccionEjecutado, "
                . "ptc.RequiereMedicion, "
                . "ptc.RequiereMedicionEjecutado, "
                . "ptc.EquiposObs, "
                . "CONVERT(varchar(10), CondAmbFechaHora, 103) AS CondAmbFecha, "
                . "CONVERT(varchar(5), CondAmbFechaHora, 108) AS CondAmbHora, "
                . "ptc.CondAmbObs, "
                . "ptc.TanquesNombresAlternativos, "
                . "ptc.InspeccionNombre, "
                . "ptc.PlanBloqueoExistente, "
                . "ptc.PlanLimpiezaExistente, "
                . "ptc.PlanDrenajeExistente, "
                . "ptc.RequiereBloqueoDoc, "
                . "ptc.PlanBloqueoObs, "
                . "ptc.InformaRiesgos, "
                . "ptc.EjecutaTareasPTC, "
                . "ptc.AceptaCondArea, "
                . "ptc.RetiraBloqueos, "
                . "ptc.AutorizarObs, "
                . "ptc.TagEquipo, "
                . "ptc.EsperandoPorPTCVinculado, "
                . "logAut.IdUsuario AS IdUsuarioAutoriza, "
                . "logAutU.Nombre AS UsuarioAutoriza, "
                . "logApr.IdUsuario AS IdUsuarioAprueba, "
                . "logAprU.Nombre AS UsuarioAprueba, "
                . "logEjeU.Nombre AS RespNombre, "
                . "CONVERT(varchar(10), logEje.FechaHora, 103) + ' ' + CONVERT(varchar(8), logEje.FechaHora, 108) AS RespFechaHoraComienzo, "
                . "logCerr.IdUsuario AS IdUsuarioCierra, "
                . "logCerrU.Nombre AS UsuarioCierra, "
                . "FechaCierre, "
                . "'#' + LTRIM(RTRIM(STR(ptc.NroPTC))) + ' - ' + ptc.Descripcion AS Detalle, "
                . "CASE "
                . "     WHEN "
                . "         logAut.IdUsuario IS NOT NULL AND "
                . "         ((SELECT COUNT(*) FROM PTCPTCTipos ppt WHERE ptc.NroPTC = ppt.NroPTC) + "
                . "          (SELECT COUNT(*) FROM PTCPTCRiesgos ppt WHERE ptc.NroPTC = ppt.NroPTC)) = 0 AND " // no tiene riesgos
                . "         (ISNULL(ptc.RequiereBloqueo, 0) = 0 AND ISNULL(ptc.RequiereDrenarPurgar, 0) = 0 AND ISNULL(ptc.RequiereLimpieza, 0) = 0 AND ISNULL(ptc.RequiereInspeccion, 0) = 0) " // no requiere bloqueos
                . "     THEN logAutU.Nombre "
                . "     ELSE logAprU.Nombre "
                . "END AS UsuarioGestionLiberacion, "
                . "CASE "
                . "     WHEN "
                . "         ((SELECT COUNT(*) FROM PTCPTCTipos ppt WHERE ptc.NroPTC = ppt.NroPTC) + "
                . "          (SELECT COUNT(*) FROM PTCPTCRiesgos ppt WHERE ptc.NroPTC = ppt.NroPTC)) > 0 OR " // tiene riesgos
                . "         (ISNULL(ptc.RequiereBloqueo, 0) > 0 OR ISNULL(ptc.RequiereDrenarPurgar, 0) > 0 OR ISNULL(ptc.RequiereLimpieza, 0) > 0 OR ISNULL(ptc.RequiereInspeccion, 0) > 0) " // requiere bloqueos
                . "     THEN logAutU.Nombre "
                . "     ELSE '' "
                . "END AS UsuarioResponsableOperaciones, "
                . "RespNombre, "
                . "RespEmpresa, "
                . "RespTelefono, "
                . "CONVERT(varchar(10), RespFechaHora, 103) + ' ' + CONVERT(varchar(5), RespFechaHora, 108) AS RespFechaHora, "
                . "logEjec.Nombre AS EjecutanteNombre, "
                . "CONVERT(varchar(10), EjecutorFechaHora, 103) AS EjecFecha, "
                . "CONVERT(varchar(5), EjecutorFechaHora, 108) AS EjecHora, "
                . "EsDePGP,"
                . "AnhoPGP,"
                . "u.Nombre AS NombreUsuario,"
                . "(SELECT ptcrv.IdUsuario FROM (SELECT RANK() OVER (ORDER BY FechaHora) AS Rank, IdUsuario, FechaHora FROM PTCRevalidaciones WHERE NroPTC = ptc.NroPTC) ptcrv WHERE ptcrv.Rank = 1) AS EjecRevalidacion1, "
                . "(SELECT ptcrv.IdUsuario FROM (SELECT RANK() OVER (ORDER BY FechaHora) AS Rank, IdUsuario, FechaHora FROM PTCRevalidaciones WHERE NroPTC = ptc.NroPTC) ptcrv WHERE ptcrv.Rank = 2) AS EjecRevalidacion2, "
                . "(SELECT ptcrv.IdUsuario FROM (SELECT RANK() OVER (ORDER BY FechaHora) AS Rank, IdUsuario, FechaHora FROM PTCRevalidaciones WHERE NroPTC = ptc.NroPTC) ptcrv WHERE ptcrv.Rank = 3) AS EjecRevalidacion3, "
                . "(SELECT ptcrv.IdUsuario FROM (SELECT RANK() OVER (ORDER BY FechaHora) AS Rank, IdUsuario, FechaHora FROM PTCRevalidaciones WHERE NroPTC = ptc.NroPTC) ptcrv WHERE ptcrv.Rank = 4) AS EjecRevalidacion4, "
                . "(SELECT ptcrv.IdUsuario FROM (SELECT RANK() OVER (ORDER BY FechaHora) AS Rank, IdUsuario, FechaHora FROM PTCRevalidaciones WHERE NroPTC = ptc.NroPTC) ptcrv WHERE ptcrv.Rank = 5) AS EjecRevalidacion5, "
                . "(SELECT ptcrv.IdUsuarioAprobador FROM (SELECT RANK() OVER (ORDER BY FechaHora) AS Rank, IdUsuarioAprobador, FechaHora FROM PTCRevalidaciones WHERE NroPTC = ptc.NroPTC) ptcrv WHERE ptcrv.Rank = 1) AS OpRevalidacion1, "
                . "(SELECT ptcrv.IdUsuarioAprobador FROM (SELECT RANK() OVER (ORDER BY FechaHora) AS Rank, IdUsuarioAprobador, FechaHora FROM PTCRevalidaciones WHERE NroPTC = ptc.NroPTC) ptcrv WHERE ptcrv.Rank = 2) AS OpRevalidacion2, "
                . "(SELECT ptcrv.IdUsuarioAprobador FROM (SELECT RANK() OVER (ORDER BY FechaHora) AS Rank, IdUsuarioAprobador, FechaHora FROM PTCRevalidaciones WHERE NroPTC = ptc.NroPTC) ptcrv WHERE ptcrv.Rank = 3) AS OpRevalidacion3, "
                . "(SELECT ptcrv.IdUsuarioAprobador FROM (SELECT RANK() OVER (ORDER BY FechaHora) AS Rank, IdUsuarioAprobador, FechaHora FROM PTCRevalidaciones WHERE NroPTC = ptc.NroPTC) ptcrv WHERE ptcrv.Rank = 4) AS OpRevalidacion4, "
                . "(SELECT ptcrv.IdUsuarioAprobador FROM (SELECT RANK() OVER (ORDER BY FechaHora) AS Rank, IdUsuarioAprobador, FechaHora FROM PTCRevalidaciones WHERE NroPTC = ptc.NroPTC) ptcrv WHERE ptcrv.Rank = 5) AS OpRevalidacion5, "
                . "(SELECT CONVERT(varchar(10), ptcrv.FechaHora, 103) + ' ' + CONVERT(varchar(5), ptcrv.FechaHora, 108) FROM (SELECT RANK() OVER (ORDER BY FechaHora) AS Rank, IdUsuario, FechaHora FROM PTCRevalidaciones WHERE NroPTC = ptc.NroPTC) ptcrv WHERE ptcrv.Rank = 1) AS FechaHoraRevalidacion1, "
                . "(SELECT CONVERT(varchar(10), ptcrv.FechaHora, 103) + ' ' + CONVERT(varchar(5), ptcrv.FechaHora, 108) FROM (SELECT RANK() OVER (ORDER BY FechaHora) AS Rank, IdUsuario, FechaHora FROM PTCRevalidaciones WHERE NroPTC = ptc.NroPTC) ptcrv WHERE ptcrv.Rank = 2) AS FechaHoraRevalidacion2, "
                . "(SELECT CONVERT(varchar(10), ptcrv.FechaHora, 103) + ' ' + CONVERT(varchar(5), ptcrv.FechaHora, 108) FROM (SELECT RANK() OVER (ORDER BY FechaHora) AS Rank, IdUsuario, FechaHora FROM PTCRevalidaciones WHERE NroPTC = ptc.NroPTC) ptcrv WHERE ptcrv.Rank = 3) AS FechaHoraRevalidacion3, "
                . "(SELECT CONVERT(varchar(10), ptcrv.FechaHora, 103) + ' ' + CONVERT(varchar(5), ptcrv.FechaHora, 108) FROM (SELECT RANK() OVER (ORDER BY FechaHora) AS Rank, IdUsuario, FechaHora FROM PTCRevalidaciones WHERE NroPTC = ptc.NroPTC) ptcrv WHERE ptcrv.Rank = 4) AS FechaHoraRevalidacion4, "
                . "(SELECT CONVERT(varchar(10), ptcrv.FechaHora, 103) + ' ' + CONVERT(varchar(5), ptcrv.FechaHora, 108) FROM (SELECT RANK() OVER (ORDER BY FechaHora) AS Rank, IdUsuario, FechaHora FROM PTCRevalidaciones WHERE NroPTC = ptc.NroPTC) ptcrv WHERE ptcrv.Rank = 5) AS FechaHoraRevalidacion5, "
                . "CASE "
                . "    WHEN ((SELECT COUNT(*) FROM PTCPTCTipos WHERE ptc.NroPTC = NroPTC) + "
                . "         (SELECT COUNT(*) FROM PTCPTCRiesgos WHERE ptc.NroPTC = NroPTC)) > 0 THEN 1 "
                . "    ELSE 0 "
                . "END AS TieneRiesgosAsociados "
                . "FROM PTC ptc "
                . "LEFT JOIN PTCLogActividades logAut ON ptc.NroPTC = logAut.NroPTC AND logAut.Operacion = 'Autorización' "
                . "LEFT JOIN Usuarios logAutU ON logAut.IdUsuario = logAutU.IdUsuario "
                . "LEFT JOIN PTCLogActividades logApr ON ptc.NroPTC = logApr.NroPTC AND logApr.Operacion = 'Aprobación' "
                . "LEFT JOIN Usuarios logAprU ON logApr.IdUsuario = logAprU.IdUsuario "
                . "LEFT JOIN PTCLogActividades logCerr ON ptc.NroPTC = logCerr.NroPTC AND logCerr.Operacion = 'Cerrado por operador' "
                . "LEFT JOIN Usuarios logCerrU ON logCerr.IdUsuario = logCerrU.IdUsuario "
                . "LEFT JOIN PTCLogActividades logEje ON ptc.NroPTC = logEje.NroPTC AND logEje.Operacion = 'Ejecución' "
                . "LEFT JOIN Usuarios logEjeU ON logEje.IdUsuario = logEjeU.IdUsuario "
                . "LEFT JOIN Usuarios logEjec ON logEjec.IdUsuario = ptc.EjecutanteNombre "
                . "INNER JOIN PTCAreas a ON ptc.IdArea = a.IdArea "
                . "INNER JOIN Usuarios u ON ptc.IdUsuario = u.IdUsuario "
                . "INNER JOIN Empresas e ON ptc.Documento = e.Documento AND ptc.IdTipoDocumento = e.IdTipoDocumento "
                . "WHERE ptc.NroPTC = :NroPTC";
        
        $bindings[':NroPTC'] = $id;

        $obj = DB::selectOne($sql, $bindings);

        if ($obj) {
            $obj->OTs = $this->showOts($id);
            $obj->PTCTipos = $this->showTipos($id);
            $obj->PTCRiesgos = $this->showRiesgos($id);
            $obj->PTCEquipos = $this->showEquipos($id);
            $obj->PTCCondicionAmbiental = $this->showMediciones($id);
            $obj->PTCTanques = $this->detalle_tanques($id);
            $obj->PTCTanques_Operador = $this->detalle_tanques_operador($id);
            $obj->PTCLog = $this->showLogs($id);
            $obj->PTCDocs = $this->indexDocs($id);
            $obj->PTCPersonas = $this->indexPersonas($id);
            $obj->PTCVinculados = $this->showVinculados($id);
        }
        
        return json_encode($obj);
    }

    private static function detalle_tanques($id) {
        $bindings = [];
        $bindings[':NroPTC'] = $id;
        $sql = "SELECT ptct.IdTanque AS IdTanque, ptct.Nombre AS Nombre FROM PTCTanques ptct "
            . "INNER JOIN PTCMedicionesTanques ptcmt ON ptcmt.IdTanque = ptct.IdTanque "
            . "INNER JOIN PTCMediciones ptcm ON ptcm.NroPTC = ptcmt.NroPTC AND ptcm.IdCondAmbPTC = ptcmt.IdCondAmbPTC "
            . "WHERE ptcm.NroPTC = :NroPTC GROUP BY ptct.IdTanque, ptct.Nombre";
        return DB::select($sql, $bindings);
    }
    
    private static function detalle_tanques_operador($id) {
        $bindings = [];
        $bindings[':NroPTC'] = $id;
        $sql = "SELECT ptct.IdTanque AS IdTanque, ptct.Nombre AS Nombre FROM PTCPTCTanques ptcptct "
            . "INNER JOIN PTCTanques ptct ON ptct.IdTanque = ptcptct.IdTanque "
            . "WHERE ptcptct.NroPTC = :NroPTC";
        return DB::select($sql, $bindings);
    }

    /**
     * [Lógica 1]
     * Comprueba los permisos mayores a 24 horas de tiempo previsto, que no sean PGP
     * y que estén en el estado En Ejecucción, para marcarlos como Pendiente de Revalidación.
     * Sin embargo, si el permiso se ha tenido 5 revalidaciones, entonces se marca como Vencido.
     * 
     * [Lógica 2]
     * Compruba los permisos mayores a 24 horas de tiempo previsto, que no sean PGP
     * y que estén en el estado Liberado, para marcarlos como Pendiente de Revalidación (Liberado).
     */
    public function marcarComoPendienteDeRevalidacionTodos() {
        // Log::info('PTC::marcarComoPendienteDeRevalidacionTodos start');

        $permisosDeTrabajo = DB::select("select * from PTC where DATEDIFF(hour, FechaHoraComienzoPrev, FechaHoraFinPrev) > 24 and EsDePGP = 0 and IdEstado = 'EJE'");
        
        foreach ($permisosDeTrabajo as $permisoDeTrabajo) {
            $this->marcarComoPendienteDeRevalidacionUno($permisoDeTrabajo);
        }
        
        $permisosDeTrabajoLiberados = DB::select("select * from PTC where DATEDIFF(hour, FechaHoraComienzoPrev, FechaHoraFinPrev) > 24 and EsDePGP = 0 and IdEstado = 'L'");
        
        foreach ($permisosDeTrabajoLiberados as $permisoDeTrabajoLiberado) {
            $this->marcarComoPendienteDeRevalidacionLiberadoUno($permisoDeTrabajoLiberado);
        }

        // Log::info('PTC::marcarComoPendienteDeRevalidacionTodos end');
    }

    private function marcarComoPendienteDeRevalidacionUno($permisoDeTrabajo) {
        try {
            $this->marcarComoPendienteDeRevalidacion($permisoDeTrabajo);
        }
        catch (Exception $ex) {
            Log::error('Error al marcar como pendiente de revalidación el permiso de trabajo N° '.$permisoDeTrabajo->NroPTC.'. Error: '.$ex);
        }
    }

    private function marcarComoPendienteDeRevalidacion($permisoDeTrabajo) {
        
        if ($this->obtenerRevalidaciones($permisoDeTrabajo->NroPTC) < 5) {
            $this->cambiarEstado($permisoDeTrabajo->NroPTC, 'PR');
            $this->altaLog($permisoDeTrabajo->NroPTC, 'PR', 'PermisoDeTrabajo.marcarComoPendienteDeRevalidacion', $permisoDeTrabajo);
        } else {
            $this->marcarComoVencido($permisoDeTrabajo);
        }
    }

    private function obtenerRevalidaciones($NroPTC) {
        return DB::select("select count(*) as Cantidad from PTCRevalidaciones where Estado = 1 and NroPTC = :NroPTC", [":NroPTC" => $NroPTC])[0]->Cantidad;
    }

    private function marcarComoVencido($permisoDeTrabajo) {
        $this->cambiarEstado($permisoDeTrabajo->NroPTC, 'V');
        $this->altaLog($permisoDeTrabajo->NroPTC, 'V', 'PermisoDeTrabajo.marcarComoVencido', $permisoDeTrabajo);
        $this->enviarCorreoMarcarComoVencido($permisoDeTrabajo);
    }

    private function marcarComoPendienteDeRevalidacionLiberadoUno($permisoDeTrabajo) {
        try {
            $this->cambiarEstado($permisoDeTrabajo->NroPTC, 'PRL');
            $this->altaLog($permisoDeTrabajo->NroPTC, 'PRL', 'PermisoDeTrabajo.marcarComoPendienteDeRevalidacion', $permisoDeTrabajo);
        }
        catch (Exception $ex) {
            Log::error('Error al marcar como pendiente de revalidación liberado el permiso de trabajo N° '.$permisoDeTrabajo->NroPTC.'. Error: '.$ex);
        }
    }

    private function enviarCorreoMarcarComoVencido($pt) {
        $pt = $this->show_interno($pt->NroPTC);
        $pt = json_decode($pt);
        try {
            Mail::to('fsacceso@montesdelplata.com.uy')->send(new PTCCreado($pt));
        } catch (\Exception $err) {
            Log::error('Error al enviar correo al marcar como vencido un permiso de trabajo');
        }
    }

    /**
     * Comprueba los permisos con fecha prevista de fin anteriores a hoy y que estén en estado
     * En Ejecucción, Pendiente de Revalidación o Revalidación Solicitada, para marcarlos como Vencidos.
     */
    public function marcarComoVencidoTodos() {
        // Log::info('PTC::marcarComoVencidoTodos start');

        $permisosDeTrabajo = DB::select("select * from PTC where CONVERT(date, FechaHoraFinPrev, 103) < CONVERT(date, GETDATE(), 103) and IdEstado IN('EJE', 'PR', 'RS')");
        
        foreach ($permisosDeTrabajo as $permisoDeTrabajo) {
            $this->marcarComoVencido($permisoDeTrabajo);
        }

        $permisosDeTrabajoLiberados = DB::select("select * from PTC where CONVERT(date, FechaHoraFinPrev, 103) < CONVERT(date, GETDATE(), 103) and IdEstado IN('L', 'PRL', 'RSL')");
        
        foreach ($permisosDeTrabajoLiberados as $permisoDeTrabajoLiberado) {
            $this->cerrar($permisoDeTrabajoLiberado->NroPTC, ['VieneDeProcedimiento' => true, 'Motivo' => 'Cerrado sin ejecucciòn por que la Fecha final está vencida']);
        }

        // Log::info('PTC::marcarComoVencidoTodos end');
    }

    /**
     * Comprueba los permisos con fecha prevista de fin anterior a hoy y que no hayan pasado por el estado En Ejecucción o estén cerrados o vencidos.
     */
    public function cerrarSiFechaFinPrevistaVencioTodos()
    {
        // Log::info('PTC::cerrarSiFechaFinPrevistaVencioTodos start');
        $permisos = DB::select(
            'SELECT NroPTC FROM PTC WHERE FechaHoraFinPrev < GETDATE() AND IdEstado NOT IN (?, ?, ?, ?, ?, ?, ?, ?) AND Baja = 0',
            [
                PTC::E_EN_EJECUCION,
                PTC::E_PENDIENTE_REVALIDACION,
                PTC::E_REVALIDACION_SOLICITADA,
                PTC::E_EJECUTADO,
                PTC::E_CERRADO_PARCIALMENTE,
                PTC::E_CERRADO_POR_RESPONSABLE,
                PTC::E_CERRADO_SIN_EJECUCION,
                PTC::E_VENCIDO,
            ]
        );

        foreach ($permisos as $permiso) {
            // Log::info('PTC::cerrarSiFechaFinPrevistaVencioTodos #' . $permiso->NroPTC);
            $this->cerrarSinEjecuccion($permiso->NroPTC, 'Fecha de finalización prevista vencida');
        }

        // Log::info('PTC::cerrarSiFechaFinPrevistaVencioTodos end');
    }

    public function cargarTanquesPorArea($idArea) {
        $bindings = [];
        $bindings[':idArea'] = $idArea;
        $sql = "SELECT * FROM PTCTanques where IdArea = :idArea ORDER BY Nombre ASC";

       return $this->response(DB::select($sql, $bindings));
    }

    public function cargarTanquesPorNroPTC($nroPTC) {
        $bindings = [];
        $bindings[':nroPTC'] = $nroPTC;
        $sql = "SELECT ptct.IdTanque, ptct.Nombre, ptcptct.NombreAlternativo FROM PTCPTCTanques ptcptct INNER JOIN PTCTanques ptct ON ptct.IdTanque = ptcptct.IdTanque WHERE NroPTC = :nroPTC ORDER BY Nombre ASC";

       return $this->response(DB::select($sql, $bindings));
    }

    private function showVinculados($nroPTC) {
        $bindings = [];
        $bindings[':nroPTC'] = $nroPTC;
        $sql = "SELECT 
                    CASE
                        WHEN p.IdEstado <> 'E' AND p.IdEstado <> 'L' THEN e.Nombre
                        WHEN p.IdEstado = 'L' THEN e.Nombre
                        ELSE
                            CASE 
                                WHEN (p.RequiereBloqueo = 1 AND NULLIF(p.RequiereBloqueoEjecutado, 0) IS NULL) THEN 'Esperando bloqueo'
                                WHEN (p.RequiereInspeccion = 1 AND NULLIF(p.RequiereInspeccionEjecutado, 0) IS NULL) THEN 'Esperando inspección interna'
                                WHEN (p.RequiereDrenarPurgar = 1 AND NULLIF(p.RequiereDrenarPurgarEjecutado, 0) IS NULL) THEN 'Esperando drenaje'
                                WHEN (p.RequiereLimpieza = 1 AND NULLIF(p.RequiereLimpiezaEjecutado, 0) IS NULL) THEN 'Esperando limpieza'
                                WHEN (p.RequiereMedicion = 1 AND NULLIF(p.RequiereMedicionEjecutado, 0) IS NULL AND p.EsperandoPorPTCVinculado = 1) THEN 'Esperando por PT asociado'
                                WHEN (p.RequiereMedicion = 1 AND NULLIF(p.RequiereMedicionEjecutado, 0) IS NULL) THEN 'Esperando medición'
                                ELSE 'En curso'
                            END
                    END as Estado, p.Descripcion, v.NroPTCPredecesor as NroPTC, p.EsperandoPorPTCVinculado
                    FROM PTCVinculados v INNER JOIN PTC p ON p.NroPTC = v.NroPTCPredecesor
                    INNER JOIN PTCEstados e ON p.IdEstado = e.Codigo
                    WHERE v.NroPTCSucesor = :nroPTC ORDER BY NroPTCPredecesor ASC";

        return DB::select($sql, $bindings); 
    }

    public function cargarPTCaVincular($nroPTC) {

        $bindings = [];
        $bindings[':NroPTC1'] = $nroPTC;
        $bindings[':NroPTC2'] = $nroPTC;
        $bindings[':NroPTC3'] = $nroPTC;
        $sql = "SELECT 
                    CASE  
                        WHEN v.IdEstado <> 'E' AND v.IdEstado <> 'L' THEN e.Nombre
                        WHEN v.IdEstado = 'L' THEN e.Nombre
                        ELSE
                            CASE 
                                WHEN (v.RequiereBloqueo = 1 AND NULLIF(v.RequiereBloqueoEjecutado, 0) IS NULL) THEN 'Esperando bloqueo'
                                WHEN (v.RequiereInspeccion = 1 AND NULLIF(v.RequiereInspeccionEjecutado, 0) IS NULL) THEN 'Esperando inspección interna'
                                WHEN (v.RequiereDrenarPurgar = 1 AND NULLIF(v.RequiereDrenarPurgarEjecutado, 0) IS NULL) THEN 'Esperando drenaje'
                                WHEN (v.RequiereLimpieza = 1 AND NULLIF(v.RequiereLimpiezaEjecutado, 0) IS NULL) THEN 'Esperando limpieza'
                                WHEN (v.RequiereMedicion = 1 AND NULLIF(v.RequiereMedicionEjecutado, 0) IS NULL AND v.EsperandoPorPTCVinculado = 1) THEN 'Esperando por PT asociado'
                                WHEN (v.RequiereMedicion = 1 AND NULLIF(v.RequiereMedicionEjecutado, 0) IS NULL) THEN 'Esperando medición'
                                ELSE 'En curso'
                            END
                END as Estado, v.Descripcion, v.NroPTC, v.EsperandoPorPTCVinculado
                FROM PTC p right JOIN PTC v ON p.NroPTC != v.NroPTC and p.IdArea = v.IdArea and p.AnhoPGP = v.AnhoPGP
                INNER JOIN PTCEstados e ON p.IdEstado = e.Codigo
                where p.NroPTC = :NroPTC1 AND v.IdEstado not in('CSE', 'CPR', 'CP', 'V') and v.AperturaTapa = 1 and exists(select * from ptcptctanques t where idtanque in (select idtanque from ptcptctanques where nroptc= :NroPTC2) and t.nroptc = v.nroPTc)
                and exists(select * from PTC pv where pv.NroPTC not in (select NroPTCSucesor from PTCVinculados where PTCVinculados.NroPTCPredecesor = :NroPTC3) and pv.NroPTC = v.nroPTc)";
                    

       return $this->response(DB::select($sql, $bindings));
    }

    public function cargarPTCaVincularPorTanques(int $nroPTC, string $idTanques)
    {
        $idTanques = explode(',', $idTanques);
        $binding = array_merge([$nroPTC], $idTanques);

        $sqlTanques = implode(', ', array_map(function () { return '?'; }, $idTanques));

        $sql = "SELECT 
                    CASE  
                        WHEN v.IdEstado <> 'E' AND v.IdEstado <> 'L' THEN e.Nombre
                        WHEN v.IdEstado = 'L' THEN e.Nombre
                        ELSE
                            CASE 
                                WHEN (v.RequiereBloqueo = 1 AND NULLIF(v.RequiereBloqueoEjecutado, 0) IS NULL) THEN 'Esperando bloqueo'
                                WHEN (v.RequiereInspeccion = 1 AND NULLIF(v.RequiereInspeccionEjecutado, 0) IS NULL) THEN 'Esperando inspección interna'
                                WHEN (v.RequiereDrenarPurgar = 1 AND NULLIF(v.RequiereDrenarPurgarEjecutado, 0) IS NULL) THEN 'Esperando drenaje'
                                WHEN (v.RequiereLimpieza = 1 AND NULLIF(v.RequiereLimpiezaEjecutado, 0) IS NULL) THEN 'Esperando limpieza'
                                WHEN (v.RequiereMedicion = 1 AND NULLIF(v.RequiereMedicionEjecutado, 0) IS NULL AND v.EsperandoPorPTCVinculado = 1) THEN 'Esperando por PT asociado'
                                WHEN (v.RequiereMedicion = 1 AND NULLIF(v.RequiereMedicionEjecutado, 0) IS NULL) THEN 'Esperando medición'
                                ELSE 'En curso'
                            END
                END as Estado, v.Descripcion, v.NroPTC, v.EsperandoPorPTCVinculado
                FROM PTC p right JOIN PTC v ON p.NroPTC != v.NroPTC and p.IdArea = v.IdArea and p.AnhoPGP = v.AnhoPGP
                INNER JOIN PTCEstados e ON p.IdEstado = e.Codigo
                where p.NroPTC = ? AND v.IdEstado not in('CSE', 'CPR', 'CP', 'V') and v.AperturaTapa = 1 and exists(select * from ptcptctanques t where idtanque in ($sqlTanques) and t.nroptc = v.nroPTc)
                and exists(select * from PTC pv where pv.NroPTC not in (select NroPTCSucesor from PTCVinculados where PTCVinculados.NroPTCPredecesor = p.NroPTC) and pv.NroPTC = v.nroPTc)";
        
       return $this->response(DB::select($sql, $binding));
    }

    public function exportarOtsExcel(int $nroPTC){
        $registros = $this->showOts($nroPTC);

        $output = $this->req->input('output', 'json');
        if ($output !== 'json') {
            $dataOutput = array_map(function($item) {
                return [
                    'NroOT' => $item->NroOT,
                    'Estado' => $item->EstadoNombre,
                    'Ubicacion' => $item->UbicacionTecnica,
                    'NombreUbicacion' => $item->NombreUbicacionTec,
                    'Descripcion' => $item->Descripcion,
                    'IdUsuario' => $item->IdUsuario,
                    'FechaModificacion' => $item->FechaModificacion
                ];
            },$registros);
            return $this->exportOts($dataOutput, $output);
        }
    }

    private function exportOts(array $data, string $type) {
        $filename = 'FSAcceso-Permisos-de-Trabajo-' . date('Ymd his');
        $headers = [

            'NroOT' => 'N° Ot',
            'Estado' => 'Estado',
            'Ubicacion' => 'Ubicación',
            'NombreUbicacion' => 'Nombre Ubicación',
            'Descripcion' => 'Descripción',
            'IdUsuario' => 'Usuario',
            'FechaModificacion' => 'Última Actualización'
        ];
        return FsUtils::export($type, $data, $headers, $filename);
    }

    public function importarOts(int $nroPTC) {
        $response = [];
        $response['data'] = $this->importarOtsData($nroPTC);

        $errors = array_filter($response['data'], function ($element) { return !$element['success']; });
        $response['success'] = count($errors) === 0;
        if (count($errors) === 0) {
            $response['message'] = 'Importación realizada con éxito';
        } else if (count($errors) === count($response['data'])) {
            $response['message'] = 'Importación no realizada correctamente';
        } else {
            $response['message'] = 'Importación finalizada con ' . count($errors) . ' error(es).';
        }
        return $response;
    }

    public function importarDesdeExcel() {
        $res = [];

        $file = $this->req->file('importar_pts');

        if ($file->getClientOriginalExtension() === 'xlsx' || $file->getClientOriginalExtension() === 'xls') {
            $reader = new PermisoExcelReader;
            $reader->open($file);
            $reader->rowNumber++; // skip headers
            $res[$file->getClientOriginalName()] = [];
            while ($reader->hasRows()) {
                $error = false;
                $message = null;
                $data = new stdClass();
                try {
                    $permiso = $reader->nextRow($this->user->IdUsuario);
                    $data = $this->create($permiso)->toArray();
                    $data['NombreArea'] = $permiso['NombreArea'];
                    $data['NombreEmpresa'] = $permiso['NombreEmpresa'];

                    if($data['EsDePGP']){ $data['EsDePGP'] = 'Si';
                    }else{ $data['EsDePGP'] = 'No'; }
                    $message = 'Creado correctamente';
                }
                catch (\Exception $ex) {
                    $error = true;
                    $message = $ex->getMessage();
                    if(empty($permiso['EsDePGP']) || strtolower($permiso['EsDePGP']) === 'no'){ $permiso['EsDePGP'] = 'No';
                    }else{ $permiso['EsDePGP'] = 'Si'; }
                    $data = $permiso;
                }
                $res[$file->getClientOriginalName()][$reader->rowNumber++ - 1] = [
                    'error' => $error,
                    'message' => $message,
                    'data' => $data
                ];
            }
        }

        return array_values($res);
    }
    
    public function descargarExcelPT(){
        $excel = storage_path('app/static/PdT-Importacion.xlsx');

        if (isset($excel)) {
            $content_type = mime_content_type($excel);

            header('Content-Type: '.$content_type);
            header('Content-Disposition: attachment;filename="PdT-Importacion.xlsx"');
        
            header('Cache-Control: max-age=0');
            // If you're serving to IE 9, then the following may be needed
            header('Cache-Control: max-age=1');
            // If you're serving to IE over SSL, then the following may be needed
            header ('Expires: Mon, 03 Jan 1991 05:00:00 GMT'); // Date in the past
            header ('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
            header ('Cache-Control: cache, must-revalidate'); // HTTP/1.1
            header ('Pragma: public'); // HTTP/1.0
            echo file_get_contents($excel);
        }
    }

    private function importarOtsData(int $nroPTC) {
        $res = [];

        $file = $this->req->file('importar_ot');

        if ($file->getClientOriginalExtension() === 'xlsx' || $file->getClientOriginalExtension() === 'xls') {
            $reader = new OTExcelReader;
            $reader->open($file);
            $reader->rowNumber++;
            $res = [];
            while ($reader->hasRows()) {
                $success = true;
                $message = null;
                $data = null;
                try {
                    $ot = $reader->nextRow();
                    
                    $existe = DB::select("Select nroOT, Estado, EO.Nombre as EstadoNombre from PTCOT LEFT JOIN PTCOTEstados EO ON PTCOT.Estado = EO.Codigo where nroPTC = :nroPTC and NroOt = :NroOT", ["nroPTC" => $nroPTC, ":NroOT" => trim($ot->NroOT)]);

                    if(!empty($existe)){
                        $this->updateOT($nroPTC, $ot, $this->user->IdUsuario);
                        $data = [
                            'NroOT' => $ot->NroOT,
                            'UbicacionTecnica' => $ot->UbicacionTecnica,
                            'NombreUbicacionTec' => $ot->NombreUbicacionTec,
                            'Descripcion' => $ot->Descripcion,
                            'Estado' => $existe[0]->Estado,
                            'EstadoNombre' => $existe[0]->EstadoNombre,
                            'IdUsuario' => $this->user->IdUsuario,
                            'FechaModificacion' => (new \DateTime)->format('d/m/Y H:i'),
                        ];
                    }else{
                        $this->addOT($nroPTC, $ot, $this->user->IdUsuario);
                        $message = 'Órden número ' . $ot->NroOT . ' agregada al permiso número ' . $nroPTC . ' correctamente';
                        $data = [
                            'NroOT' => $ot->NroOT,
                            'UbicacionTecnica' => $ot->UbicacionTecnica,
                            'NombreUbicacionTec' => $ot->NombreUbicacionTec,
                            'Descripcion' => $ot->Descripcion,
                            'Estado' => 'SE',
                            'EstadoNombre' => 'Sin ejecutar',
                            'IdUsuario' => $this->user->IdUsuario,
                            'FechaModificacion' => (new \DateTime)->format('d/m/Y H:i'),
                        ];
                    }

                    
                }
                catch (\Exception $ex) {
                    $success = false;
                    $message = $ex->getMessage();
                }
                $res[$reader->rowNumber++ - 1] = [
                    'success' => $success,
                    'message' => $message,
                    'data' => $data,
                ];
            }
        }

        return array_values($res);
    }

    private function addOT(int $nroPTC, $ot, $idUsuario)
    {
        DB::insert(
            "INSERT INTO PTCOT (NroOT, Estado, UbicacionTecnica, NombreUbicacionTec, Descripcion, NroPTC, FechaHora, IdUsuario, FechaModificacion, IdUsuarioModificacion)
            VALUES (:nroOt, 'SE', :UbicacionTecnica, :NombreUbicacionTec, :Descripcion, :NroPTC, GETDATE(), :IdUsuario, GETDATE(), :IdUsuarioModificacion)",
            [":nroOt" => trim($ot->NroOT), ":UbicacionTecnica" => $ot->UbicacionTecnica, ":NombreUbicacionTec" => $ot->NombreUbicacionTec,
            ":Descripcion" => $ot->Descripcion, ":NroPTC" => $nroPTC, ":IdUsuario" => $idUsuario, ":IdUsuarioModificacion" => $idUsuario]);
    }

    private function updateOT(int $nroPTC, $ot, $idUsuario)
    {
        DB::update(
            "UPDATE PTCOT SET   
                    UbicacionTecnica = :UbicacionTecnica,
                    NombreUbicacionTec = :NombreUbicacionTec,
                    Descripcion = :Descripcion,
                    IdUsuario = :IdUsuario,
                    FechaModificacion = GETDATE(),
                    IdUsuarioModificacion = :IdUsuarioModificacion
            WHERE   NroPTC = :NroPTC and NroOT = :nroOt",
            [":nroOt" => trim($ot->NroOT), ":UbicacionTecnica" => $ot->UbicacionTecnica, ":NombreUbicacionTec" => $ot->NombreUbicacionTec,
            ":Descripcion" => $ot->Descripcion, ":NroPTC" => $nroPTC, ":IdUsuario" => $idUsuario, ":IdUsuarioModificacion" => $idUsuario]);
    }

    private function showOts($id) {
        $bindings = [];
        $bindings[':NroPTC'] = $id;

        $sql = "SELECT  NroOT, Estado, EO.Nombre as EstadoNombre, UbicacionTecnica, 
                        NombreUbicacionTec, Descripcion, IdUsuario,
                        CONVERT(varchar(10), FechaHora, 103) AS FechaCreado,
                        CONVERT(varchar(5), FechaHora, 108) AS HoraCreado,
                        IdUsuarioModificacion,
                        CONVERT(varchar(10), FechaModificacion, 103) AS FechaModificacion,
                        CONVERT(varchar(5), FechaModificacion, 108) AS HoraModificacion
                FROM PTCOT LEFT JOIN PTCOTEstados EO ON PTCOT.Estado = EO.Codigo WHERE NroPTC = :NroPTC order by NroOT";

        $listado = DB::select($sql, $bindings);

        foreach ($listado as &$item) {
            $item->FechaHora = $item->FechaCreado . ' ' . $item->HoraCreado;
            $item->FechaModificacion = $item->FechaModificacion . ' ' . $item->HoraModificacion;
        }

        return $listado;
    }

    private static function showTipos($id) {
        $bindings = [];
        $bindings[':NroPTC'] = $id;

        $sql = "SELECT pt.IdTipoPTC, pt.Nombre "
                . "FROM PTCTipos pt "
                . "INNER JOIN PTCPTCTipos ppt ON pt.IdTipoPTC = ppt.IdTipoPTC "
                . "WHERE ppt.NroPTC = :NroPTC";

       return DB::select($sql, $bindings);
    }

    public function cargarTipos() {
        $sql = "SELECT IdTipoPTC, Nombre FROM PTCTipos";
        return DB::select($sql);
    }

    private static function showRiesgos($id) {
        $bindings = [];
        $bindings[':NroPTC'] = $id;

        $sql = "SELECT pr.IdRiesgoPTC, pr.Nombre "
                . "FROM PTCRiesgos pr "
                . "INNER JOIN PTCPTCRiesgos ppr ON pr.IdRiesgoPTC = ppr.IdRiesgoPTC "
                . "WHERE ppr.NroPTC = :NroPTC";

       return DB::select($sql, $bindings);
    }

    public function cargarRiesgos() {
        $sql = "SELECT pr.IdRiesgoPTC, pr.Nombre FROM PTCRiesgos pr ";
        return DB::select($sql);
    }

    private static function showEquipos($id) {
        $bindings = [];
        $bindings[':NroPTC'] = $id;

        $sql = "SELECT pt.IdEquipoPTC, pt.Nombre "
                . "FROM PTCEquipos pt "
                . "INNER JOIN PTCPTCEquipos ppt ON ppt.IdEquipoPTC = pt.IdEquipoPTC "
                . "WHERE ppt.NroPTC = :NroPTC";

       return DB::select($sql, $bindings);
    }

    public function cargarEquipos() {
        $sql = "SELECT IdEquipoPTC, Nombre
                FROM PTCEquipos ORDER BY IdEquipoPTC";

        $listado = DB::select($sql);
        usort($listado, function($a, $b) {
            return $a->Nombre === 'Otros (Especificar)' ? 1 : 0;
        });
       return $this->response($listado);
    }

    private static function showMediciones($id) {
        $bindings = [];
        $bindings[':NroPTC'] = $id;

        $sql = "SELECT 
                    pt.IdCondAmbPTC, 
                    CASE WHEN pt.IdCondAmbPTC = 999 THEN ptc.PTCMedicion999Nombre ELSE pt.Nombre END AS Nombre, 
                    CASE WHEN pt.IdCondAmbPTC = 999 THEN ptc.PTCMedicion999Unidad ELSE pt.UnidadMedida END AS UnidadMedida, 
                    ppt.Valor "
                . "FROM PTCCondicionesAmbientales pt "
                . "INNER JOIN PTCMediciones ppt ON ppt.IdCondAmbPTC = pt.IdCondAmbPTC "
                . "INNER JOIN PTC ptc ON ppt.NroPTC = ptc.NroPTC "
                . "WHERE ppt.NroPTC = :NroPTC";

       return DB::select($sql, $bindings);
    }

    public function cargarMediciones($nroPTC) {
        $bindings = [];
        $bindings[':NroPTC'] = $nroPTC;
        $bindings[':NroPTC1'] = $nroPTC;
        $bindings[':NroPTC2'] = $nroPTC;
        $sql = "SELECT 
                    pt.IdCondAmbPTC, 
                    CASE WHEN pt.IdCondAmbPTC = 999 THEN (select PTCMedicion999Nombre from PTC where NroPTC = :NroPTC) ELSE pt.Nombre END AS Nombre, 
                    CASE WHEN pt.IdCondAmbPTC = 999 THEN (select PTCMedicion999Unidad from PTC where NroPTC = :NroPTC1) ELSE pt.UnidadMedida END AS UnidadMedida,
                    (select Valor from PTCMediciones where IdCondAmbPTC = pt.IdCondAmbPTC and NroPTC = :NroPTC2) as Valor 
                FROM PTCCondicionesAmbientales pt";

        return $this->response(DB::select($sql, $bindings));
    }

    public function cargarMedicionesGral() {
        $sql = "SELECT IdCondAmbPTC, Nombre, UnidadMedida FROM PTCCondicionesAmbientales";
        return $this->response(DB::select($sql));
    }

    public function cargarEstadosOT() {
        $sql = "SELECT Codigo, Nombre FROM PTCOTEstados order by orden";
        return $this->response(DB::select($sql));
    }

    public function cargarEstados() {
        $sql = "SELECT Codigo, Nombre FROM PTCEstados ORDER BY Orden";
        $estados = DB::select($sql);

        $estados[] = (object)['Codigo' => '>EB', 'Nombre' => 'Esperando bloqueo'];
        $estados[] = (object)['Codigo' => '>EI', 'Nombre' => 'Esperando inspección interna'];
        $estados[] = (object)['Codigo' => '>ED', 'Nombre' => 'Esperando drenaje'];
        $estados[] = (object)['Codigo' => '>EL', 'Nombre' => 'Esperando limpieza'];
        $estados[] = (object)['Codigo' => '>EM', 'Nombre' => 'Esperando medición'];

        usort($estados, function ($a, $b) {
            return strcmp($a->Nombre, $b->Nombre);
        });

        return $this->response($estados);
    }

    public function agregarComentario($nroPTC) {
        $Args = $this->req->all();

        $bindings = [];
        $bindings[':NroPTC'] = $nroPTC;
        $bindings[':Comentario'] = $Args['Comentario'];
        $bindings[':IdUsuario'] = $this->user->IdUsuario;
        $bindings[':FechaHora'] = new DateTime();

        DB::insert("INSERT INTO PTCComentarios (NroPTC, Comentario, FechaHora, IdUsuario)
                    VALUES (:NroPTC, :Comentario, :FechaHora, :IdUsuario)",
                    $bindings);
        
        return $bindings;     
    }

    public function cargarComentarios($nroPTC) {
        $bindings = [];
        $bindings[':NroPTC'] = $nroPTC;
        $sql = "SELECT CONVERT(varchar(10), FechaHora, 103) + ' ' + CONVERT(varchar(8), FechaHora, 108) FechaHora, Nombre, Comentario 
                FROM PTCComentarios INNER JOIN usuarios ON PTCComentarios.IdUsuario = usuarios.IdUsuario where NroPTC = :NroPTC";
       return $this->response(DB::select($sql, $bindings));
    }

    public function cargarEquiposMediciones() {
        $sql = "SELECT IdEquipoMedicion, Nombre FROM PTCEquiposMedicion";
       return $this->response(DB::select($sql));
    }

    private function showLogs($id) {
        
        if(empty($id)){
            throw new HttpException(409, "NroPTC no encontrado");
        }

        $logs = DB::select("SELECT ptcl.NroPTC, ptcl.IdEstado, ptce.Nombre AS Estado, ptcl.Operacion, "
                            . " CONVERT(varchar(10), ptcl.FechaHora, 103) + ' ' + CONVERT(varchar(8), ptcl.FechaHora, 108) AS FechaHora, "
                            . " ptcl.FechaHora AS FechaHoraT, "
                            . " ptcl.Observaciones, ptcl.IdUsuario, CASE WHEN u.Nombre IS NOT NULL THEN u.Nombre ELSE ptcl.IdUsuario END AS Usuario, u.Email, "
                            . " STUFF((
                                    SELECT ', ' + r.Nombre
                                    FROM PTCRolesUsuarios ru
                                    INNER JOIN PTCRoles r ON r.Codigo = ru.Codigo
                                    WHERE ptcl.IdUsuario = ru.IdUsuario
                                    ORDER BY r.Nombre
                                    FOR XML PATH ('')), 1, 2, '') AS PTCRol"
                            . " FROM PTCLogActividades ptcl"
                            . " INNER JOIN PTCEstados ptce ON ptcl.IdEstado = ptce.Codigo"
                            . " LEFT JOIN Usuarios u ON ptcl.IdUsuario = u.IdUsuario "
                            . " LEFT JOIN PTCRoles ptcr ON ptcr.Codigo = u.PTCRol "
                            . " LEFT JOIN PTCRolesUsuarios ptcru ON (ptcru.IdUsuario = ptcl.IdUsuario AND ptcru.Codigo = ptcr.Codigo) "
                            . " WHERE ptcl.NroPTC like :NroPTC ORDER BY FechaHoraT", [":NroPTC" => $id]);
        
        foreach($logs as $log) {
            $obs = json_decode($log->Observaciones);
            if($log->Operacion == 'Autorización' && !empty($obs->AutorizarObs)) $log->Observaciones = $obs->AutorizarObs;
            else $log->Observaciones = isset($obs->Motivo) ? $obs->Motivo : null;
            
            if (strpos($log->Operacion, 'PermisoDeTrabajo.') === 0) {
                $log->Operacion = ucfirst(str_replace('PermisoDeTrabajo.', '', $log->Operacion));
            }
        }
        
        return $logs;
    }

    private function comprobarArgs(&$Args, $mod) {
        // Chequeos generales
        // Chequeos específicos
        switch ($mod) {
            case "SOL":
                
                if(!empty($Args['FechaHoraComienzoPrev'])){
                    $fechaInicio = $Args['FechaHoraComienzoPrev'];
                }else{
                    $fechaInicio = FsUtils::datetime($Args['FechaComienzoPrev'] . " " . $Args['HoraComienzoPrev'] . ":00", FsUtils::DDMMYYHHMMSS);

                    if (!FsUtils::datetime_is_valid_time($Args['HoraComienzoPrev'])) {
                        throw new HttpException(409, "El campo 'Hora comienzo prevista' tiene una hora no válida");
                    }
                }

                if(!empty($Args['FechaHoraFinPrev'])){
                    $fechaFin = $Args['FechaHoraFinPrev'];
                }else{
                    $fechaFin = FsUtils::datetime($Args['FechaFinPrev'] . " " . $Args['HoraFinPrev'] . ":00", FsUtils::DDMMYYHHMMSS);

                    if (!FsUtils::datetime_is_valid_time($Args['HoraFinPrev'])) {
                        throw new HttpException(409, "El campo 'Hora finalización prevista' tiene una hora no válida");
                    }
                }

                if ($fechaInicio < new DateTime()) {
                    throw new HttpException(409, "La 'Fecha de comienzo prevista' no puede ser anterior al día de hoy");
                }

                if ($fechaInicio > $fechaFin) {
                    throw new HttpException(409, "La 'Fecha fin prevista' no puede ser anterior a la 'Fecha comienzo prevista'");
                }
                
                if (empty($Args['EsDePGP']) && FsUtils::datetime_diff($fechaFin, $fechaInicio, '%a') > 6) {
                    throw new HttpException(409, "El permiso de trabajo no puede tener una duración mayor a 6 días");
                }

                if (!empty($Args['PTCTiposOtroObs'])) {
                    $existeTipoOtros = false;
                    
                    foreach ($Args['PTCTipos'] as $tipo) {
                        if ($tipo == "999") {
                            $existeTipoOtros = true;
                            break;
                        }
                    }

                    if (!$existeTipoOtros) {
                        $Args['PTCTipos'][] = '999';
                    }
                }

                break;

            case "MODOPR":
                $requerimientos = array("Medicion", "DrenarPurgar", "Limpieza", "Bloqueo", "Inspeccion");
                $tieneRequerimientos = false;
                foreach ($requerimientos as $req) {
                    if (empty($Args["Requiere" . $req])) {
                        $Args["Requiere" . $req . "Ejecutado"] = false;
                    } else if (!$tieneRequerimientos) {
                        $tieneRequerimientos = true;
                    }
                }

                if (!$this->ptTieneRiesgosAsociados($Args) && $tieneRequerimientos) {
                    //throw new HttpException(409, "Un permiso de trabajo sin riesgos asociados no puede requerir bloqueo o medición");
                }

                if (!empty($Args['RequiereBloqueo']) && empty($Args['PlanBloqueoExistente'])) {
                    throw new HttpException(409, "Es necesario especificar un 'Plan de bloqueo' si el permiso de trabajo requiere bloqueo.");
                }

                /*if (!empty($Args['RequiereDrenarPurgar']) && empty($Args['PlanDrenajeExistente'])) {
                    throw new HttpException(409, "Es necesario especificar un 'Plan de drenaje' si el permiso de trabajo requiere drenaje.");
                }

                if (!empty($Args['RequiereLimpieza']) && empty($Args['PlanLimpiezaExistente'])) {
                    throw new HttpException(409, "Es necesario especificar un 'Plan de limpieza' si el permiso de trabajo requiere limpieza.");
                }*/

                break;

            case "MODSOL":
                break;

            case "MODSSO":
                break;

            case "CPR":
                $today = new DateTime();
                $today->setTime(0, 0, 0);
                if (FsUtils::datetime($Args['FechaCierre'], FsUtils::DDMMYY) < $today) {
                    throw new HttpException(409, "La 'Fecha de cierre' del permiso de trabajo no puede ser anterior al día de hoy");
                }
                break;
        }
        
        return true;
    }

    private function ptTieneRiesgosAsociados($pt) {
        return (!empty($pt->PTCTipos) || !empty($pt->PTCRiesgos));
    }

    private function ptRequiereBloqueo($pt) {
        return !empty($pt->RequiereDrenarPurgar) || !empty($pt->RequiereLimpieza) || !empty($pt->RequiereBloqueo) || !empty($pt->RequiereInspeccion);
    }

    private function ptRequiereMedicion($pt) {
        return !empty($pt->RequiereMedicion);
    }

    private function ptRequiereMedicionOBloqueo($pt) {
        return $this->ptRequiereMedicion($pt) || $this->ptRequiereBloqueo($pt);
    }

    public function create(?array $Args = null) {

        if(empty($Args)){
            $Args = $this->req->all();
        }

        PTC::exigirArgs($Args, array("IdEmpresa", "IdArea"));
        $this->comprobarArgs($Args, "SOL");

        if ($this->user->usuarioEsSolicitante()) {

            $retorno = DB::transaction(function () use ($Args){

                $IdEmpresaObj = fsUtils::explodeId($Args['IdEmpresa']);
                
                if ((bool)$Args['EsDePGP'] === true && empty($Args['AnhoPGP'])) {
                    throw new HttpException(409, 'Debe seleccionar un año PGP.');
                }

                if (!is_numeric($Args['CantidadPersonas'])) {
                    throw new HttpException(409, 'El campo Cantidad de Personas debe ser de tipo numerico.');
                }

                $FechaHoraDesde = null;
                $FechaHoraHasta = null;

                if (!empty($Args['FechaHoraComienzoPrev'])) {
                    $FechaHoraDesde = $Args['FechaHoraComienzoPrev'];
                }else{
                    if (!empty($Args['FechaComienzoPrev'])) {
                        $FechaHoraDesde = FsUtils::strToDate($Args['FechaComienzoPrev'].' '.$Args['HoraComienzoPrev'].':00', FsUtils::DDMMYYHHMMSS);
                    }
                }
                
                if (!empty($Args['FechaHoraFinPrev'])) {
                    $FechaHoraHasta = $Args['FechaHoraFinPrev'];
                }else{
                    if (!empty($Args['FechaFinPrev'])) {
                        $FechaHoraHasta = FsUtils::strToDate($Args['FechaFinPrev'].' '.$Args['HoraFinPrev'].':00', FsUtils::DDMMYYHHMMSS);
                    }
                }

                $entity = new PTC($Args);
                $entity->IdEstado = "I";
                $entity->Documento = $IdEmpresaObj[0];
                $entity->IdTipoDocumento = $IdEmpresaObj[1];
                $entity->IdUsuario = $this->user->IdUsuario;
                $entity->FechaHoraComienzoPrev = $FechaHoraDesde;
                $entity->FechaHoraFinPrev = $FechaHoraHasta;
                $entity->FechaHora = new \DateTime;
                $entity->Baja = false;

                if($Args['EsDePGP']){
                    $entity->EsDePGP = $Args['EsDePGP'];
                    $entity->AnhoPGP = $Args['AnhoPGP'];
                }

                if (!empty($Args['RequiereBloqueo']) || !empty($Args['RequiereInspeccion']) || !empty($Args['RequiereDrenarPurgar']) || !empty($Args['RequiereLimpieza']) || !empty($Args['RequiereMedicion'])) {

                    $entity->RequiereBloqueo = $Args['RequiereBloqueo'];
                    $entity->RequiereInspeccion = $Args['RequiereInspeccion'];
                    $entity->RequiereDrenarPurgar = $Args['RequiereDrenarPurgar'];
                    $entity->RequiereLimpieza = $Args['RequiereLimpieza'];
                    $entity->RequiereMedicion = $Args['RequiereMedicion'];
                }

                $entity->save();

                $Args['NroPTC'] = $entity->NroPTC;
                
                if (!empty($Args['PrimerComentario'])) {
                    DB::insert("INSERT INTO PTCComentarios(NroPTC, FechaHora, IdUsuario, Comentario) VALUES (:NroPTC, GETDATE(), :IdUsuario, :PrimerComentario)", 
                            [":NroPTC" => $Args['NroPTC'], ":IdUsuario" => $this->user->IdUsuario, ":PrimerComentario" => $Args['PrimerComentario']]);
                }

                
                $this->modificaciontipos_sql($Args, true);
                $this->altaLog($Args['NroPTC'], 'I', 'Alta', $Args);
                $this->solicitar_mail($Args);
                
                return $entity->refresh();
            });

            DB::delete("DELETE FROM PTCPTCPersonas WHERE NroPTC = :NroPTC",[":NroPTC" => $retorno->NroPTC]);
            
            if(!empty($Args['PTCPersonas'])){
                foreach ($Args['PTCPersonas'] as $persona) {
                    if (!is_object($persona)) { $persona = (object) $persona; }
                    DB::insert(
                        "INSERT INTO PTCPTCPersonas (NroPTC, Documento, IdTipoDocumento, FechaHora, IdUsuario) 
                            values(:NroPTC, :Documento, :IdTipoDocumento, GETDATE(), :IdUsuario)",
                        [":NroPTC" => $retorno->NroPTC, ':Documento' => $persona->Documento, ":IdTipoDocumento" => $persona->IdTipoDocumento, ":IdUsuario" => $this->user->IdUsuario]
                    );
                }
            }
            
            return $retorno;
        }

        throw new HttpException(409, "El usuario no tiene permisos para realizar esta acción");
    }

    private function altaLog($NroPTC, $IdEstado, $Operacion, $Observaciones) {
            $obs = json_encode($Observaciones);

            $bindings = [];
            $bindings[':NroPTC'] = $NroPTC;
            $bindings[':IdEstado'] = $IdEstado;
            $bindings[':Operacion'] = $Operacion;
            $bindings[':IdUsuario'] = isset($this->user) ? $this->user->IdUsuario : LogAuditoria::FSA_USER_DEFAULT;
            $bindings[':obs'] = $obs;

            DB::insert("INSERT INTO PTCLogActividades (NroPTC, FechaHora, IdEstado, Operacion, IdUsuario, Observaciones)
                        VALUES (:NroPTC, GETDATE(), :IdEstado, :Operacion, :IdUsuario, :obs)",
                        $bindings);
    }

    private function modificaciontipos_sql($Args, $revisarReglas = false) {
       DB::delete("DELETE FROM PTCPTCTipos WHERE NroPTC = :NroPTC", [":NroPTC" => $Args['NroPTC']]);

        foreach ($Args['PTCTipos'] as $tipo) {
            $descr = $tipo == "999" ? $Args['PTCTiposOtroObs'] : "";

            $bindings = [];
            $bindings[':NroPTC'] = $Args['NroPTC'];
            $bindings[':tipo'] = intval($tipo);
            $bindings[':descr'] = $descr;

            DB::insert("INSERT INTO PTCPTCTipos(NroPTC, IdTipoPTC, Descripcion) VALUES (:NroPTC, :tipo, :descr)", $bindings);
            
            if ($revisarReglas && $tipo == env("PTC_ESPACIOS_CONFINADOS", 3)) {
                DB::update("UPDATE PTC SET RequiereMedicion = 1, RequiereBloqueo = 1 WHERE NroPTC = :NroPTC", [":NroPTC" => $Args['NroPTC']]);
            }

            if ($revisarReglas && $tipo == env("PTC_CONTACTO_ENERGIA", 1)) {
                DB::update("UPDATE PTC SET RequiereBloqueo = 1 WHERE NroPTC = :NroPTC", [":NroPTC" => $Args['NroPTC']]);
            }
        }
        if (!empty($Args['PTCTiposOtroObs'])) {
            DB::update("UPDATE PTC SET PTCTiposOtroObs = :PTCTiposOtroObs WHERE NroPTC like :NroPTC",
                [":PTCTiposOtroObs" => $Args['PTCTiposOtroObs'], ":NroPTC" => $Args['NroPTC']]);
        }
    }

    private function modificacionriesgos_sql($Args) {
        DB::delete("DELETE FROM PTCPTCRiesgos WHERE NroPTC = :NroPTC", [':NroPTC' => $Args['NroPTC']]);

        foreach ($Args['PTCRiesgos'] as $riesgo) {
            $descr = $riesgo == "999" ? $Args['PTCRiesgosOtroObs'] : "";
            DB::insert("INSERT INTO PTCPTCRiesgos(NroPTC, IdRiesgoPTC, Descripcion) 
                VALUES (:NroPTC, :riesgo, :descr)",
                [':NroPTC' => $Args['NroPTC'], ':riesgo' => $riesgo, ':descr' => $descr]);
        }
    }

    private function modificacionequipos_sql($Args) {
        DB::delete("DELETE FROM PTCPTCEquipos WHERE NroPTC = :NroPTC", [':NroPTC' => $Args['NroPTC']]);
        
        foreach ($Args['PTCEquipos'] as $equipo) {
            $descr = $equipo == "999" ? $Args['PTCTiposOtroObs'] : "";
            DB::insert("INSERT INTO PTCPTCEquipos(NroPTC, IdEquipoPTC, Descripcion) 
                VALUES (:NroPTC, :equipo, :descr)",
                [':NroPTC' => $Args['NroPTC'], ':equipo' => $equipo, ':descr' => $descr]);
        }
    }

    private function solicitar_mail($pt) {
        $pt = $this->show_interno($pt['NroPTC']);
        $pt = json_decode($pt);

        $solicitante = $this->ptObtenerSolicitante($pt);        
        $operadores = $this->ptObtenerOperadores($pt);

        try {
            
            if(!empty($solicitante)){
                Mail::to($solicitante)->send(new PTCCreado($pt));
            }

            if(!empty($operadores)){
                foreach($operadores as $operador){
                    Mail::to($operador)->send(new PTCCreado($pt));
                }
            }
            
        } catch (\Exception $err) {
            Log::error('Error al enviar correo al ingresar permiso de trabajo');
            throw new HttpException(409, "El permiso de trabajo fue solicitado pero las notificaciones vía mail no pudieron ser enviadas");
        }

    }

    private function ptObtenerSolicitante($pt) {

        $obj = DB::selectOne("SELECT DISTINCT u.Email "
                . "FROM Usuarios u "
                . "INNER JOIN PTC p ON u.IdUsuario = p.IdUsuario "
                . "INNER JOIN PTCRolesUsuarios pru ON pru.IdUsuario = u.IdUsuario AND pru.Codigo = 'SOL' "
                . "WHERE u.Baja = 0 "
                . "AND u.Estado = 1 "
                . "AND u.PTC = 1 "
                . "AND p.NroPTC = :NroPTC "
                . "AND u.RecibeNotificaciones = 1 "
                . "AND pru.RecibeNotificaciones = 1", [":NroPTC" => $pt->NroPTC]);
        
        if(!empty($obj)){
            return $obj->Email;
        }

        return null;
    }

    private function ptObtenerOperadores($pt) {

        $operadores = DB::select("SELECT DISTINCT u.Email "
                . "FROM Usuarios u "
                . "INNER JOIN PTCAreasUsuarios a ON u.IdUsuario = a.IdUsuario "
                . "INNER JOIN PTCRolesUsuarios pru ON pru.IdUsuario = u.IdUsuario AND pru.Codigo = 'OPR' "
                . "WHERE u.Baja = 0 "
                . "AND u.Estado = 1 "
                . "AND u.PTC = 1 "
                . "AND (u.PTCGestion = 1 OR a.IdArea = :IdArea) "
                . "AND u.RecibeNotificaciones = 1 "
                . "AND pru.RecibeNotificaciones = 1", [":IdArea" => $pt->IdArea]);

        $o = array();
        if(!empty($operadores)){
            foreach ($operadores as $operador){
                $o[] = $operador->Email;
            }
        }
        
        return $o;
    }

    private function ptEstaCerrado($pt) {
        return $pt->IdEstado == "CPR" || $pt->IdEstado == "CSE";
    }

    public function update(int $id) {

        $Args = $this->req->All();

        $pt = $this->show_interno($id);
        $pt = json_decode($pt);

        $Args['NroPTC'] = $id;

        if (!isset($pt)) {
            throw new NotFoundHttpException('Permiso de trabajo no encontrado');
        }

        if (!$this->ptEstaCerrado($pt)) {
            
            $retorno = DB::transaction(function () use ($pt, $Args){

                $operacion = '';
                
                $entity = PTC::find($Args['NroPTC']);

                if (!isset($entity)) {
                    throw new NotFoundHttpException('Permiso de trabajo no encontrado');
                }

                $entity->fill($this->req->all());

                if ($pt->IdEstado == 'I') {
                    if ($this->user->usuarioEsSolicitante() && $this->comprobarArgs($Args, "SOL")) {
                        $operacion = "Modificación";
                        $IdEmpresaObj = fsUtils::explodeId($Args['IdEmpresa']);

                        DB::delete("DELETE FROM PTCOT WHERE NroPTC = :NroPTC",[":NroPTC" => $Args['NroPTC']]);

                        foreach ($Args['OTs'] as $ot) {
                            $ot = (object) $ot;
                            if (is_object($ot)) {
                                if (!empty($ot->NroOT)) {
                                    if (empty($ot->IdUsuario)) $ot->IdUsuario = $this->user->IdUsuario;
                                    if (empty($ot->IdUsuarioModificacion)) $ot->IdUsuarioModificacion = $this->user->IdUsuario;
                                    DB::insert(
                                        "INSERT INTO PTCOT (NroOT, Estado, UbicacionTecnica, NombreUbicacionTec, Descripcion, NroPTC, FechaHora, IdUsuario, FechaModificacion, IdUsuarioModificacion)
                                        VALUES (:ot,:Estado ,:UbicacionTecnica ,:NombreUbicacionTec ,:Descripcion, :NroPTC, GETDATE(), :IdUsuario, GETDATE(), :IdUsuarioModificacion)",
                                        [":ot" => trim($ot->NroOT), ":Estado" => $ot->Estado, ":UbicacionTecnica" => $ot->UbicacionTecnica, ":NombreUbicacionTec" => $ot->NombreUbicacionTec,
                                        ":Descripcion" => $ot->Descripcion, ":NroPTC" => $Args['NroPTC'], ":IdUsuario" => $ot->IdUsuario, ":IdUsuarioModificacion" => $ot->IdUsuarioModificacion]);
                                }
                            } else if (!empty($ot)) {
                                DB::insert(
                                    "INSERT INTO PTCOT(NroOT, Estado, NroPTC, FechaHora, IdUsuario, FechaModificacion, IdUsuarioModificacion) 
                                    VALUES (:ot, :Estado, :NroPTC, GETDATE(), :IdUsuario, GETDATE(), :IdUsuario1)",
                                    [":ot" => trim($ot['NroOT']), ":Estado" => $ot->Estado, ":NroPTC" => $Args['NroPTC'], ":IdUsuario" => $this->user->IdUsuario, ":IdUsuario1" => $this->user->IdUsuario]
                                );
                            }
                        }

                        if ((bool)$Args['EsDePGP'] === true && empty($Args['AnhoPGP'])) {
                            throw new HttpException(409, 'Debe seleccionar un año PGP.');
                        }
                        
                        $FechaHoraDesde = null;
                        $FechaHoraHasta = null;

                        if (!empty($Args['FechaComienzoPrev'])) {
                            $FechaHoraDesde = FsUtils::strToDate($Args['FechaComienzoPrev'].' '.$Args['HoraComienzoPrev'].':00', FsUtils::DDMMYYHHMMSS);
                        }
                        if (!empty($Args['FechaFinPrev'])) {
                            $FechaHoraHasta = FsUtils::strToDate($Args['FechaFinPrev'].' '.$Args['HoraFinPrev'].':00', FsUtils::DDMMYYHHMMSS);
                        }
                        
                        if($Args['EsDePGP']){
                            $entity->EsDePGP = $Args['EsDePGP'];
                            $entity->AnhoPGP = $Args['AnhoPGP'];
                        }

                        $entity->Documento = $IdEmpresaObj[0];
                        $entity->IdTipoDocumento = $IdEmpresaObj[1];
                        $entity->FechaHoraComienzoPrev = $FechaHoraDesde;
                        $entity->FechaHoraFinPrev = $FechaHoraHasta;
                        $entity->Baja = false;
                        $entity->RequiereBloqueo = $Args['RequiereBloqueo'];
                        $entity->RequiereInspeccion = $Args['RequiereInspeccion'];
                        $entity->RequiereDrenarPurgar = $Args['RequiereDrenarPurgar'];
                        $entity->RequiereLimpieza = $Args['RequiereLimpieza'];
                        $entity->RequiereMedicion = $Args['RequiereMedicion'];


                        $entity->save();
                
                        $this->modificaciontipos_sql($Args, true);

                    }
                    else {
                        throw new HttpException(409, "El usuario no tiene permisos para realizar esta acción");
                    }
                } 
                
                else if ($pt->IdEstado == 'RVS') {
                    if ($this->user->usuarioEsSolicitante() && $this->comprobarArgs($Args, "SOL")) {
                        $operacion = "Modificación";
                        $IdEmpresaObj = fsUtils::explodeId($Args['IdEmpresa']);

                        DB::delete("DELETE FROM PTCOT WHERE NroPTC = :NroPTC",[":NroPTC" => $Args['NroPTC']]);
                        
                        foreach ($Args['OTs'] as $ot) {
                            
                            DB::delete("DELETE FROM PTCOT WHERE NroPTC = :NroPTC",[":NroPTC" => $Args['NroPTC']]);

                            foreach ($Args['OTs'] as $ot) {
                                $ot = (object) $ot;
                                if (!empty($ot->NroOT)) {
                                    if (empty($ot->IdUsuario)) $ot->IdUsuario = $this->user->IdUsuario;
                                    if (empty($ot->IdUsuarioModificacion)) $ot->IdUsuarioModificacion = $this->user->IdUsuario;
                                    if (empty($ot->FechaHora)) $ot->FechaHora = (new \DateTime)->format('d/m/Y H:i');
                                    if (empty($ot->FechaModificacion)) $ot->FechaModificacion = (new \DateTime)->format('d/m/Y H:i');
                                    DB::insert(
                                        "INSERT INTO PTCOT (NroOT, Estado, UbicacionTecnica, NombreUbicacionTec, Descripcion, NroPTC, FechaHora, IdUsuario, FechaModificacion, IdUsuarioModificacion)
                                        VALUES (:ot ,:Estado ,:UbicacionTecnica ,:NombreUbicacionTec ,:Descripcion, :NroPTC, CONVERT(datetime, :FechaHora, 103), :IdUsuario, CONVERT(datetime, :FechaModificacion, 103), :IdUsuarioModificacion)",
                                        [":ot" => trim($ot->NroOT), ":Estado" => $ot->Estado, ":UbicacionTecnica" => $ot->UbicacionTecnica, ":NombreUbicacionTec" => $ot->NombreUbicacionTec,
                                        ":Descripcion" => $ot->Descripcion, ":NroPTC" => $Args['NroPTC'], ":FechaHora" => $ot->FechaHora, ":IdUsuario" => $ot->IdUsuario, ":FechaModificacion" => $ot->FechaModificacion,
                                        ":IdUsuarioModificacion" => $ot->IdUsuarioModificacion]);
                                }
                            }
                        }
                        
                        $pt->IdEstado = 'I';
                        
                        $FechaHoraDesde = null;
                        $FechaHoraHasta = null;

                        if (!empty($Args['FechaComienzoPrev'])) {
                            $FechaHoraDesde = FsUtils::strToDate($Args['FechaComienzoPrev'].' '.$Args['HoraComienzoPrev'].':00', FsUtils::DDMMYYHHMMSS);
                        }
                        if (!empty($Args['FechaFinPrev'])) {
                            $FechaHoraHasta = FsUtils::strToDate($Args['FechaFinPrev'].' '.$Args['HoraFinPrev'].':00', FsUtils::DDMMYYHHMMSS);
                        }
                        
                        $entity->Documento = $IdEmpresaObj[0];
                        $entity->IdTipoDocumento = $IdEmpresaObj[1];
                        $entity->IdEstado = $pt->IdEstado;
                        $entity->FechaHoraComienzoPrev = $FechaHoraDesde;
                        $entity->FechaHoraFinPrev = $FechaHoraHasta;
                        $entity->Baja = false;

                        $entity->RequiereBloqueo = $Args['RequiereBloqueo'];
                        $entity->RequiereInspeccion = $Args['RequiereInspeccion'];
                        $entity->RequiereDrenarPurgar = $Args['RequiereDrenarPurgar'];
                        $entity->RequiereLimpieza = $Args['RequiereLimpieza'];
                        $entity->RequiereMedicion = $Args['RequiereMedicion'];
                        
                        $entity->save();
                
                        $this->modificaciontipos_sql($Args, true);

                    }
                    else {
                        throw new HttpException(409, "El usuario no tiene permisos para realizar esta acción");
                    }
                }
                
                else if ($pt->IdEstado == 'E') {
                    
                    $tieneCondAmb = false;
                    foreach($Args['PTCCondicionAmbiental'] as $condAmb){
                        if(!empty($condAmb['Valor'])){
                            $tieneCondAmb = true;
                            break;
                        }
                        
                    }

                    if (!empty($Args['EsDePGP']) && $tieneCondAmb && $Args['RequiereMedicion'] && count($Args['PTCTanques']) === 0) {
                        throw new HttpException(409, 'Para cargar mediciones a un permiso PGP debe seleccionar uno o más tanques asociados');
                    }

                    if ($this->user->usuarioEsOperador() && is_array($Args['OTs'])) {
                        
                        DB::Delete("DELETE FROM PTCOT WHERE NroPTC = :NroPTC", [":NroPTC" => $Args['NroPTC']]);
                        foreach ($Args['OTs'] as $ot) {

                            $ot = (object) $ot;
                            if (is_object($ot)) {
                                if (!empty($ot->NroOT)) {
                                    if (empty($ot->IdUsuario)) $ot->IdUsuario = $this->user->IdUsuario;
                                    if (empty($ot->IdUsuarioModificacion)) $ot->IdUsuarioModificacion = $this->user->IdUsuario;
                                    if (empty($ot->FechaHora)) $ot->FechaHora = (new \DateTime)->format('d/m/Y H:i');
                                    if (empty($ot->FechaModificacion)) $ot->FechaModificacion = (new \DateTime)->format('d/m/Y H:i');
                                    DB::insert(
                                        "INSERT INTO PTCOT (NroOT, Estado, UbicacionTecnica, NombreUbicacionTec, Descripcion, NroPTC, FechaHora, IdUsuario, FechaModificacion, IdUsuarioModificacion)
                                        VALUES (:ot ,:Estado ,:UbicacionTecnica ,:NombreUbicacionTec ,:Descripcion, :NroPTC, CONVERT(datetime, :FechaHora, 103), :IdUsuario, CONVERT(datetime, :FechaModificacion, 103), :IdUsuarioModificacion)",
                                        [":ot" => trim($ot->NroOT), ":Estado" => $ot->Estado, ":UbicacionTecnica" => $ot->UbicacionTecnica, ":NombreUbicacionTec" => $ot->NombreUbicacionTec,
                                        ":Descripcion" => $ot->Descripcion, ":NroPTC" => $Args['NroPTC'], ":FechaHora" => $ot->FechaHora, ":IdUsuario" => $ot->IdUsuario, ":FechaModificacion" => $ot->FechaModificacion,
                                        ":IdUsuarioModificacion" => $ot->IdUsuarioModificacion]);
                                }
                            }
                        }
                    }

                    if ($this->user->usuarioEsOperador() && $this->comprobarArgs($Args, "MODOPR")) {
                        $operacion = "Modificación";

                        $entity->Baja = false;
                        $entity->TelefonoContacto = $Args['TelefonoContacto'];
                        $entity->RequiereBloqueo = $Args['RequiereBloqueo'];
                        $entity->RequiereInspeccion = $Args['RequiereInspeccion'];
                        $entity->RequiereDrenarPurgar = $Args['RequiereDrenarPurgar'];
                        $entity->RequiereLimpieza = $Args['RequiereLimpieza'];
                        $entity->RequiereMedicion = $Args['RequiereMedicion'];
                        
                        $entity->RequiereBloqueoEjecutado = !!$Args['RequiereBloqueoEjecutado'];
                        $entity->RequiereInspeccionEjecutado = !!$Args['RequiereInspeccionEjecutado'];
                        $entity->RequiereDrenarPurgarEjecutado = !!$Args['RequiereDrenarPurgarEjecutado'];
                        $entity->RequiereLimpiezaEjecutado = !!$Args['RequiereLimpiezaEjecutado'];
                        $entity->RequiereMedicionEjecutado = !!$Args['RequiereMedicionEjecutado'];

                        $entity->PTCRiesgosOtroObs = $Args['PTCRiesgosOtroObs'];
                        $entity->EquiposObs = $Args['EquiposObs'];
                        $entity->InspeccionNombre = $Args['InspeccionNombre'];
                        $entity->PlanBloqueoExistente = isset($Args['PlanBloqueoExistente']) ? $Args['PlanBloqueoExistente'] : null;
                        $entity->PlanLimpiezaExistente = $Args['PlanLimpiezaExistente'];
                        $entity->PlanDrenajeExistente = $Args['PlanDrenajeExistente'];
                        $entity->RequiereBloqueoDoc = $Args['RequiereBloqueoDoc'];
                        $entity->TanquesNombresAlternativos = $Args['TanquesNombresAlternativos'];
                        $entity->PTCTiposOtroObs = $Args['PTCTiposOtroObs'];
                        
                        $entity->save();
                        
                        $this->modificaciontipos_sql($Args);
                        $this->modificacionriesgos_sql($Args);
                        $this->modificacionequipos_sql($Args);

                        if(isset($Args['RequiereBloqueoAsociados']) && $Args['RequiereBloqueoEjecutado'] === true && !empty($Args['EsDePGP']) && !empty($Args['PlanBloqueoExistente'])){
                            $arrPlanBloqueoExistente = explode(',', $Args['PlanBloqueoExistente']);
                            $bindingsBloqueo = [];
                            $where = '';
                            $bindingsBloqueo[] = $Args['AnhoPGP'];
                            $bindingsBloqueo[] = $Args['IdArea'];

                            for($i = 0; $i < count($arrPlanBloqueoExistente); $i++) {
                                $bindingsBloqueo[] = $arrPlanBloqueoExistente[$i];
                                if ($i === 0) { $where .= ' and (PlanBloqueoExistente = ?'; }
                                else
                                { $where .= ' or PlanBloqueoExistente = ?'; }
                            }
                            if(!empty($where)){ $where .= ' )'; }

                            DB::UPDATE("UPDATE PTC SET RequiereBloqueoEjecutado = 1 WHERE EsDePGP = 1 and AnhoPGP = ? and IdArea = ? ".$where, $bindingsBloqueo);
                        }

                        DB::delete("DELETE FROM PTCPTCTanques WHERE NroPTC = :NroPTC",[":NroPTC" => $Args['NroPTC']]);

                        $TanquesNombresAlternativos = [];
                        if (isset($Args['TanquesNombresAlternativos']) && !empty($Args['TanquesNombresAlternativos'])) {
                            foreach (explode(';', $Args['TanquesNombresAlternativos']) as $row_TanquesNombresAlternativos) {
                                $TanquesNombresAlternativos[explode('=', $row_TanquesNombresAlternativos)[0]] = explode('=', $row_TanquesNombresAlternativos)[1];
                            }
                        }
    
                        if (!empty($Args['EsDePGP']) && empty($Args['PTCTanques_Operador']) && $Args['RequiereMedicion']) {
                            throw new HttpException(409, 'Si el permiso es de PGP y requiere mediciones, debe seleccionar al menos un tanque en la pestaña Operador.');
                        }
    
                        $tanques = [];
                        if (!empty($Args['PTCTanques_Operador']) && $Args['RequiereMedicion']) {
                            foreach ($Args['PTCTanques_Operador'] as $idTanque) {
                                $tanques[] = $idTanque;
                                $TanqueNombreAlternativo = '';
                                if (array_key_exists($idTanque, $TanquesNombresAlternativos)) {
                                    $TanqueNombreAlternativo = $TanquesNombresAlternativos[$idTanque];
                                }
                                DB::insert("INSERT INTO PTCPTCTanques (NroPTC, IdTanque, NombreAlternativo) VALUES (:NroPTC, :IdTanque, :TanqueNombreAlternativo)",
                                    [":NroPTC" => $Args['NroPTC'], ":IdTanque" => $idTanque, ":TanqueNombreAlternativo" => $TanqueNombreAlternativo]);
                            }
                        }
    
                        /// Cargar y finalizar mediciones sí y sólo sí se
                        /// satisface que para la selección de tanques existen
                        /// las mismas condiciones ambientales para el conjunto
                        /// de mediciones de los tanques.
                        if (!empty($Args['PTCTanquesCargarMediciones'])) {
                            $bindings = [];
                            $i = 0;
                            foreach($tanques as $tanque){
                                $i++;
                                $bindings[':tanque'.$i] = $tanque;
                                $values[] = ':tanque'.$i;
                            }
                            $medicionesVigentes = DB::select("SELECT ptcm.IdCondAmbPTC, ptcm.Valor, ptc.CondAmbFechaHora, ptc.CondAmbNombre, ptct.IdTanque, ptc.IdEquipoMedicion1, ptc.IdEquipoMedicion2, ptc.RequiereMedicionEjecutado "
                                . "FROM PTCMedicionesTanques ptcmt INNER JOIN PTCMediciones ptcm ON ptcm.NroPTC = ptcmt.NroPTC AND ptcm.IdCondAmbPTC = ptcmt.IdCondAmbPTC INNER JOIN PTC ptc ON ptc.NroPTC = ptcm.NroPTC INNER JOIN PTCTanques ptct ON ptct.IdTanque = ptcmt.IdTanque "
                                . "WHERE ptct.IdTanque IN (" . implode(',', $values) . ") AND ptc.IdEstado IN ('E', 'EJE') AND (ptc.CondAmbVigFechaHora IS NULL OR ptc.CondAmbVigFechaHora > GETDATE()) AND ptc.PermitirUtilizarMediciones = 1 AND ptct.IdArea IS NOT NULL AND ptc.RequiereMedicionEjecutado = 1 "
                                . "GROUP BY ptcm.IdCondAmbPTC, ptcm.Valor, ptc.CondAmbFechaHora, ptc.CondAmbNombre, ptct.IdTanque, ptc.IdEquipoMedicion1, ptc.IdEquipoMedicion2, ptc.RequiereMedicionEjecutado "
                                . "ORDER BY ptc.CondAmbFechaHora DESC", $bindings);

                            if (count($medicionesVigentes) > 0) {
                                $medicionesPorTanque = [];
                                foreach ($medicionesVigentes as $medicion) {
                                    if (!array_key_exists($medicion->IdTanque, $medicionesPorTanque)) {
                                        $medicionesPorTanque[$medicion->IdTanque] = [];
                                    }
                                    $medicionesPorTanque[$medicion->IdTanque][$medicion->IdCondAmbPTC] = $medicion->Valor;
                                }
                                if (count($medicionesPorTanque) !== count($tanques)) {
                                    throw new HttpException(409, 'No hay mediciones para todos los tanques seleccionados');
                                }
                                $tanqueMuestra = current($medicionesPorTanque);
                                if (count($medicionesPorTanque) > 1) {
                                    foreach ($medicionesPorTanque as $tanque) {
                                        $condAmbIgnoradas = count($tanque);
                                        foreach ($tanqueMuestra as $condAmb => $condAmbMuestra) {
                                            /// Si la condición ambiental "Otros" (999) se encuentra en el listado
                                            /// se debe proceder a anular esta característica.
                                            if ((int) $condAmb === 999) {
                                                throw new HttpException(409, 'El sistema no permite el uso de la medición "Otros" para cargar de forma automática');
                                            }
                                            if (!array_key_exists($condAmb, $tanque)) {
                                                throw new HttpException(409, 'Existen diferencias de conjunto de condiciones ambientales para las mediciones vigentes de los tanques seleccionados');
                                            } else {
                                                $condAmbIgnoradas--;
                                            }
                                        }
                                        if ($condAmbIgnoradas > 0) {
                                            throw new HttpException(409, 'Diferencia en conjuntos de condiciones ambientales');
                                        }
                                    }
                                }
                                

                                /// Cargo las medidas vigentes en este permiso y finalizo las mediciones
                                DB::delete("DELETE FROM PTCMedicionesTanques WHERE NroPTC = :NroPTC",[":NroPTC" => $Args['NroPTC']]);
                                DB::delete("DELETE FROM PTCMediciones WHERE NroPTC = :NroPTC",[":NroPTC" => $Args['NroPTC']]);

                                foreach ($tanqueMuestra as $condAmb => $condAmbMuestra) {
                                    DB::insert("INSERT INTO PTCMediciones (NroPTC, IdCondAmbPTC, Valor) VALUES (:NroPTC, :condAmb, :condAmbMuestra)",
                                        [":NroPTC" => $Args['NroPTC'], ":condAmb" => $condAmb, ":condAmbMuestra" => $condAmbMuestra ]);
                                }
                                foreach ($medicionesPorTanque as $IdTanque => $mediciones) {
                                    foreach ($mediciones as $condAmb => $medicion) {
                                        $bindings = [];
                                        $bindings[':IdTanque'] = $IdTanque;
                                        $bindings[':NroPTC'] = $Args['NroPTC'];
                                        $bindings[':condAmb'] = $condAmb;
                                        DB::insert("INSERT INTO PTCMedicionesTanques (NroPTC, IdCondAmbPTC, IdTanque) SELECT TOP 1 NroPTC, IdCondAmbPTC, :IdTanque FROM PTCMediciones WHERE NroPTC = :NroPTC AND IdCondAmbPTC = :condAmb", $bindings);
                                    }
                                }
                                DB::UPDATE("UPDATE PTC SET RequiereMedicionEjecutado = 1, CondAmbNombre = :CondAmbNombre, CondAmbFechaHora = :CondAmbFechaHora WHERE NroPTC = :NroPTC", [":NroPTC" => $Args['NroPTC'], ':CondAmbNombre' => $medicionesVigentes[0]->CondAmbNombre, ':CondAmbFechaHora' => $medicionesVigentes[0]->CondAmbFechaHora]);
                                $Args['RequiereMedicionEjecutado'] = 1; // Actualizo el estado del permiso en cuanto a las mediciones
                                $Args['CondAmbNombre'] = $medicionesVigentes[0]->CondAmbNombre;
                                $Args['CondAmbFechaHora'] = $medicionesVigentes[0]->CondAmbFechaHora;
                                if (!empty($medicionesVigentes[0]->IdEquipoMedicion1)) {
                                    DB::UPDATE("UPDATE PTC SET IdEquipoMedicion1 = :medicionesVigentes WHERE NroPTC = :NroPTC", [":NroPTC" => $Args['NroPTC'], ":medicionesVigentes" => $medicionesVigentes[0]->IdEquipoMedicion1]);
                                }
                                if (!empty($medicionesVigentes[0]->IdEquipoMedicion2)) {
                                    DB::UPDATE("UPDATE PTC SET IdEquipoMedicion2 = :medicionesVigentes WHERE NroPTC = :NroPTC", [":NroPTC" => $Args['NroPTC'], ":medicionesVigentes" => $medicionesVigentes[0]->IdEquipoMedicion2]);
                                }
                            } else {
                                Log::info('No hay mediciones vigentes disponibles');

                            }
                        }
                        
                    }
                    
                    if ($pt->RequiereMedicion == 1 && $Args['RequiereMedicion']) {
                        if ($this->user->usuarioEsSYSO() && $this->comprobarArgs($Args, "MODSSO")) {
                            $operacion = "Medición";
                            

                            $CondAmbVigFechaHora = null;
                            if (!empty($Args['PermitirUtilizarMediciones'])) {
                                if (!empty($Args['CondAmbVigenciaFecha']) && !empty($Args['CondAmbVigenciaHora'])) {
                                    $CondAmbVigFechaHora = DateTime::createFromFormat('d/m/Y H:i:s', $Args['CondAmbVigenciaFecha'] . ' ' . $Args['CondAmbVigenciaHora'] . ':00');
                                } else if (!empty($Args['FechaFinPrev']) && !empty($Args['HoraFinPrev'])) {
                                    $CondAmbVigFechaHora = DateTime::createFromFormat('d/m/Y H:i:s', $Args['FechaFinPrev'] . ' ' . $Args['HoraFinPrev'] . ':00');
                                } else {
                                    throw new HttpException(409, 'Debe indicar una fecha y hora de vigencia para las mediciones realizadas');
                                }
                            }

                            $requiereIdEquipoMedicion = false;
                            foreach ($Args['PTCCondicionAmbiental'] as $medicion) {
                                if(isset($medicion['Valor'])){
                                    $requiereIdEquipoMedicion = true;
                                }
                            }
                            if($requiereIdEquipoMedicion && empty($Args['IdEquipoMedicion1'])){
                                throw new HttpException(409, 'Debe indicar un equipo de medición');
                            }

                            $entity->Baja = false;
                            $entity->IdEquipoMedicion1 = $Args['IdEquipoMedicion1'];
                            $entity->IdEquipoMedicion2 = $Args['IdEquipoMedicion2'];
                            $entity->CondAmbNombre =$this->user->Nombre;
                            $entity->CondAmbCargo = $Args['CondAmbCargo'];
                            $entity->CondAmbEquipo = $Args['CondAmbEquipo'];
                            $entity->CondAmbFechaHora = new DateTime();
                            $entity->CondAmbVigFechaHora = $CondAmbVigFechaHora;
                            $entity->PermitirUtilizarMediciones = $Args['PermitirUtilizarMediciones'];
                            $entity->CondAmbObs = $Args['CondAmbObs'];


                            if (!empty($Args['Finish'])) { // DontFinish
                                if (empty($Args['IdEquipoMedicion1'])) {
                                    throw new HttpException(409, 'Debe indicar un Equipo de medición');
                                }
                                $entity->RequiereMedicionEjecutado = 1;
                            }

                            DB::delete("DELETE FROM PTCMedicionesTanques WHERE NroPTC = :NroPTC", [':NroPTC' => $Args['NroPTC']]); // Limpio la lista de tanques antes de eliminar las mediciones
                            DB::delete("DELETE FROM PTCMediciones WHERE NroPTC = :NroPTC", [':NroPTC' => $Args['NroPTC']]);

                            foreach ($Args['PTCCondicionAmbiental'] as $medicion) {
                                if ($medicion['IdCondAmbPTC'] == 999) {
                                    $entity->PTCMedicion999Nombre = $medicion['Nombre'];
                                    $entity->PTCMedicion999Unidad = $medicion['UnidadMedida'];
                                }
                                
                                DB::insert("INSERT INTO PTCMediciones (NroPTC, IdCondAmbPTC, Valor) 
                                    VALUES (:NroPTC, :IdCondAmbPTC, :Valor)",
                                    [
                                        ':NroPTC' => $Args['NroPTC'], 
                                        ':IdCondAmbPTC' => $medicion['IdCondAmbPTC'], 
                                        ':Valor' => $medicion['Valor']
                                    ]
                                );

                                if (is_array($Args['PTCTanques']) || is_object($Args['PTCTanques'])) {
                                    foreach ($Args['PTCTanques'] as $tanque) {
                                        if (is_object($tanque)) {
                                            $IdTanque = $tanque->IdTanque;
                                        } else {
                                            $IdTanque = $tanque;
                                        }
                                        DB::insert("INSERT INTO PTCMedicionesTanques(NroPTC, IdCondAmbPTC, IdTanque) 
                                            SELECT TOP 1 NroPTC, IdCondAmbPTC, :IdTanque FROM PTCMediciones WHERE NroPTC = :NroPTC AND IdCondAmbPTC = :medicion",
                                            [":IdTanque" => $IdTanque, ':NroPTC' => $Args['NroPTC'], ":medicion" => $medicion['IdCondAmbPTC']]);
                                    }
                                }
                            }

                            $entity->save();
                        }
                    }
                   
                    if (!isset($operacion)) {
                        throw new HttpException(409, "El usuario no tiene permisos para realizar esta acción");
                    }

                    //PREDECESORES Y SUCESORES
                    $predecesores = DB::select("SELECT NroPTCPredecesor FROM PTCVinculados WHERE NroPTCSucesor = :NroPTCSucesor",[":NroPTCSucesor" => $Args['NroPTC']]);
                    
                    foreach ($predecesores as $predecesor) {
                        
                        $vinoDelFrontend = false;
                        foreach ($Args['PTCVinculados'] as $vinculado) {
                            $vinculado = (object) $vinculado;
                            if($predecesor->NroPTCPredecesor == $vinculado->NroPTC){ $vinoDelFrontend = true; }
                        }

                        if(!$vinoDelFrontend){
                            DB::delete( "DELETE FROM PTCVinculados WHERE NroPTCSucesor = :NroPTCSucesor AND NroPTCPredecesor = :NroPTCPredecesor",
                                        [":NroPTCSucesor" => $Args['NroPTC'], ':NroPTCPredecesor' => $predecesor->NroPTCPredecesor]);
                        }
                    }

                    foreach ($Args['PTCVinculados'] as $vinculado) {
                        $vinculado = (object) $vinculado;
                        $yaExisteEnBD = false;
                        foreach ($predecesores as $predecesor) {
                            if($predecesor->NroPTCPredecesor == $vinculado->NroPTC){ $yaExisteEnBD = true; }
                        }

                        if(!$yaExisteEnBD){
                            DB::insert(
                                "INSERT INTO PTCVinculados (NroPTCPredecesor, NroPTCSucesor, FechaHora, IdUsuario) 
                                    values(:NroPTCPredecesor, :NroPTCSucesor, GETDATE(), :IdUsuario)",
                                [':NroPTCPredecesor' => $vinculado->NroPTC, ":NroPTCSucesor" => $Args['NroPTC'], ":IdUsuario" => $this->user->IdUsuario]
                            );
                        }
                    }

                    //PREDECESORES
                    $bindings = [];
                    $bindings[':NroPTC'] = $Args['NroPTC'];
                    $sql = "SELECT COUNT(*) as Cantidad FROM PTCVinculados WHERE NroPTCSucesor = :NroPTC";

                    $CantidadPredecesores = DB::selectOne($sql, $bindings);

                    $bindings = [];
                    $bindings[':NroPTC'] = $Args['NroPTC'];
                    $sql = "SELECT count(*) as Cantidad
                            FROM PTCVinculados v
                            WHERE v.NroPTCSucesor = :NroPTC 
                            AND exists(select * from ptcptctanques t where idtanque in (select idtanque from ptcptctanques where nroptc= v.NroPTCSucesor) and t.nroptc = v.NroPTCPredecesor)";

                    $CantidadPredecesoresConTanquesCompartidos = DB::selectOne($sql, $bindings);

                    if($CantidadPredecesores->Cantidad != $CantidadPredecesoresConTanquesCompartidos->Cantidad){

                        $bindings = [];
                        $bindings[':NroPTC'] = $Args['NroPTC'];
                        $sql = "SELECT NroPTCPredecesor FROM PTCVinculados WHERE NroPTCSucesor = :NroPTC";

                        $Predecesores = DB::select($sql, $bindings);

                        $bindings = [];
                        $bindings[':NroPTC'] = $Args['NroPTC'];
                        $sql = "SELECT NroPTCPredecesor 
                                FROM PTCVinculados v
                                WHERE v.NroPTCSucesor = :NroPTC 
                                AND exists(select * from ptcptctanques t where idtanque in (select idtanque from ptcptctanques where nroptc= v.NroPTCSucesor) and t.nroptc = v.NroPTCPredecesor)";

                        $PredecesoresConTanquesCompartidos = DB::select($sql, $bindings);

                        $predecesoresNoCompartenTanques = array_filter($Predecesores, function($Predecesor) use($PredecesoresConTanquesCompartidos){
                            foreach($PredecesoresConTanquesCompartidos as $PredecesorConTanquesCompartidos){
                                if($PredecesorConTanquesCompartidos->NroPTCPredecesor == $Predecesor->NroPTCPredecesor){
                                    return false;
                                }
                            }
                            return true;
                        });

                        $predecesoresNoCompartenTanques = array_map(function($v){
                            return $v->NroPTCPredecesor;
                        },$predecesoresNoCompartenTanques);

                        throw new HttpException(409, "Los permisos de trabajo N° ".implode(",", $predecesoresNoCompartenTanques)." no comparten tanques por lo que no pueden estar vinculados como predecesores");
                    }

                    //SUCESORES
                    $bindings = [];
                    $bindings[':NroPTC'] = $Args['NroPTC'];
                    $sql = "SELECT COUNT(*) as Cantidad FROM PTCVinculados WHERE NroPTCPredecesor = :NroPTC";

                    $CantidadSucesores = DB::selectOne($sql, $bindings);

                    $bindings = [];
                    $bindings[':NroPTC'] = $Args['NroPTC'];
                    $sql = "SELECT count(*) as Cantidad
                            FROM PTCVinculados v
                            WHERE v.NroPTCPredecesor = :NroPTC 
                            AND exists(select * from ptcptctanques t where idtanque in (select idtanque from ptcptctanques where nroptc= v.NroPTCPredecesor) and t.nroptc = v.NroPTCSucesor)";

                    $CantidadSucesoresConTanquesCompartidos = DB::selectOne($sql, $bindings);
                    
                    if($CantidadSucesores->Cantidad != $CantidadSucesoresConTanquesCompartidos->Cantidad){

                        $bindings = [];
                        $bindings[':NroPTC'] = $Args['NroPTC'];
                        $sql = "SELECT NroPTCSucesor FROM PTCVinculados WHERE NroPTCPredecesor = :NroPTC";

                        $Sucesores = DB::select($sql, $bindings);

                        $bindings = [];
                        $bindings[':NroPTC'] = $Args['NroPTC'];
                        $sql = "SELECT NroPTCSucesor 
                                FROM PTCVinculados v
                                WHERE v.NroPTCPredecesor = :NroPTC 
                                AND exists(select * from ptcptctanques t where idtanque in (select idtanque from ptcptctanques where nroptc= v.NroPTCPredecesor) and t.nroptc = v.NroPTCSucesor)";

                        $SucesoresConTanquesCompartidos = DB::select($sql, $bindings);

                        $SucesoresNoCompartenTanques = array_filter($Sucesores, function($Sucesor) use($SucesoresConTanquesCompartidos){
                            foreach($SucesoresConTanquesCompartidos as $SucesorConTanquesCompartidos){
                                if($SucesorConTanquesCompartidos->NroPTCSucesor == $Sucesor->NroPTCSucesor){
                                    return false;
                                }
                            }
                            return true;
                        });

                        $SucesoresNoCompartenTanques = array_map(function($v){
                            return $v->NroPTCSucesor;
                        },$SucesoresNoCompartenTanques);

                        throw new HttpException(409, "Este permiso de trabajo tiene sucesores asociados los cuales le impiden eliminar tanques asociados, ellos son: N° ".implode(",", $SucesoresNoCompartenTanques));
                    }


                    if(!empty($Args['PTCVinculados'])){
                        $this->verificarPadresEjecutados($pt);
                        /*$EsperandoPorPTCVinculado = 0;
                        foreach($Predecesores as $Predecesor){
                            $bindings = [];
                            $bindings[':NroPTC'] = $Predecesor->NroPTCPredecesor;
                            $PredecesorNoCerrado = DB::selectOne("SELECT 'S' as Existe FROM PTC WHERE NroPTC = :NroPTC AND IdEstado != 'CPR'", $bindings);

                            if(isset($PredecesorNoCerrado) && $PredecesorNoCerrado->Existe === 'S'){
                                $EsperandoPorPTCVinculado = 1;
                                break;
                            }
                        }

                        $entity->EsperandoPorPTCVinculado = $EsperandoPorPTCVinculado;
                        $entity->save();

                        if($pt->EsperandoPorPTCVinculado && $EsperandoPorPTCVinculado == 0){
                            $this->esperandoMediciones_mail($pt);
                        }*/
                    }
                    
                }                
                else if ($pt->IdEstado == 'L') {
                   /* if ($this->user->usuarioEsSolicitante() && $this->comprobarArgs($Args, "MODSOL")) {
                        $operacion = "Cierre de OTs";
                        
                        foreach ($Args['OTs'] as $ot) {
                            DB::UPDATE(
                                "UPDATE PTCOT SET Cerrada = :Cerrada WHERE NroPTC = :NroPTC AND NroOT = :NroOT",
                                [":Cerrada" => $ot['Cerrada'], ":NroOT" => $ot['NroOT'], ":NroPTC" => $Args['NroPTC']]
                            );
                        }
                    }
                    else {
                        throw new HttpException(409, "El usuario no tiene permisos para realizar esta acción");
                    }*/
                }
                
                else if ($pt->IdEstado == 'EJE') {
                    if ($this->user->usuarioEsEjecutante()) {
                        $operacion = "Modificación";

                        $entity->RespTelefono = $Args['RespTelefono'];

                        $entity->save();
                    }
                }

                if (isset($operacion)) {
                    $this->altaLog($Args['NroPTC'], $pt->IdEstado, $operacion, $Args);

                    if (empty($pt->RequiereMedicion) && !empty($Args['RequiereMedicion'])) {
                        $this->modificacion_mail($pt);
                    }

                    return $entity;
                }
            
            });
            
            DB::delete("DELETE FROM PTCPTCPersonas WHERE NroPTC = :NroPTC",[":NroPTC" => $id]);
            
            if(!empty($Args['PTCPersonas'])){
                foreach ($Args['PTCPersonas'] as $persona) {
                    if (!is_object($persona)) {
                        $persona = (object) $persona;
                    }
                    
                    DB::insert(
                        "INSERT INTO PTCPTCPersonas (NroPTC, Documento, IdTipoDocumento, FechaHora, IdUsuario) 
                            values(:NroPTC, :Documento, :IdTipoDocumento, GETDATE(), :IdUsuario)",
                        [":NroPTC" => $id, ':Documento' => $persona->Documento, ":IdTipoDocumento" => $persona->IdTipoDocumento, ":IdUsuario" => $this->user->IdUsuario]
                    );
                }
            }

            return $retorno;
        
        } 
        else {
            throw new HttpException(409, "El permiso de trabajo ya fue cerrado");
        }
    }

    private function verificarPadresEjecutados($pt) {
        $bindings = [];
        $bindings[':NroPTC'] = $pt->NroPTC;
        $sql = "SELECT NroPTCPredecesor FROM PTCVinculados WHERE NroPTCSucesor = :NroPTC";

        $Predecesores = DB::select($sql, $bindings);
                    
        $EsperandoPorPTCVinculado = 0;
        foreach($Predecesores as $Predecesor){
            $bindings = [];
            $bindings[':NroPTC'] = $Predecesor->NroPTCPredecesor;
            $PredecesorNoCerrado = DB::selectOne("SELECT 'S' as Existe FROM PTC WHERE NroPTC = :NroPTC AND IdEstado != 'CPR'", $bindings);

            if(isset($PredecesorNoCerrado) && $PredecesorNoCerrado->Existe === 'S'){
                $EsperandoPorPTCVinculado = 1;
                break;
            }
        }

        $entity = PTC::find($pt->NroPTC);
        $entity->EsperandoPorPTCVinculado = $EsperandoPorPTCVinculado;
        $entity->save();

        if($pt->EsperandoPorPTCVinculado && $EsperandoPorPTCVinculado == 0){
            $this->esperandoMediciones_mail($pt);
        }
    }

    private function esperandoMediciones_mail($pt) {
        $syso = $this->ptObtenerSYSO($pt);
        try {
            
            if(!empty($syso)){
                foreach($syso as $s){
                    Mail::to($s)->send(new PTCMediciones($pt));
                }
            }

        } catch (\Exception $err) {
            Log::error('Error al enviar correo al editar permiso de trabajo');
            throw new HttpException(409, "El permiso de trabajo fue editado correctamente pero las notificaciones vía mail no pudieron ser enviadas");
        }

    }

    private function modificacion_mail($pt) {
        $syso = $this->ptObtenerSYSO($pt);
        try {
            
            if(!empty($syso)){
                foreach($syso as $s){
                    Mail::to($s)->send(new PTCModificado($pt));
                }
            }

        } catch (\Exception $err) {
            Log::error('Error al enviar correo al editar permiso de trabajo');
            throw new HttpException(409, "El permiso de trabajo fue editado correctamente pero las notificaciones vía mail no pudieron ser enviadas");
        }

    }

    private function ptObtenerSYSO($pt) {
        $syso = DB::select("SELECT DISTINCT u.Email "
                . "FROM Usuarios u "
                . "LEFT JOIN PTCAreasUsuarios a ON u.IdUsuario = a.IdUsuario "
                . "INNER JOIN PTCRolesUsuarios pru ON pru.IdUsuario = u.IdUsuario AND pru.Codigo = 'SSO' "
                . "WHERE u.Baja = 0 "
                . "AND u.Estado = 1 "
                . "AND u.PTC = 1 "
                . "AND u.Email is not null "
                . "AND (u.PTCGestion = 1 OR a.IdArea = " . $pt->IdArea . ") "
                . "AND u.RecibeNotificaciones = 1 "
                . "AND pru.RecibeNotificaciones = 1");

        $s = [];
        
        if(!empty($syso)){
            foreach ($syso as $sso){
                $s[] = $sso->Email;
            }
        }
        
        return $s;
    }

    // Método que invoca el Operador para pasar un PT al flujo para su liberación
    public function autorizaroAprobar(int $nroPTC) {

        if ($this->user->usuarioEsOperador()) {
            
            $pt = $this->show_interno($nroPTC);
            $pt = json_decode($pt);

            if ($this->ptTieneRiesgosAsociados($pt) || $this->ptRequiereMedicionOBloqueo($pt)) {
                $this->aprobar($nroPTC);
                return "Se ha solicitado la liberación del permiso de trabajo correctamente.";
            } else {
                $this->autorizar($nroPTC);
                return "El pedido de trabajo fue liberado correctamente.";
            }
        }

        throw new HttpException(409, "El usuario no tiene permisos para realizar esta acción");
    }

     // Método que invoca el Operador para pasar un PT a Pendiente de liberación 
    // (Pasa si todos las mediciones y bloqueos fueron efectuadas)
    public function aprobar(int $nroPTC, $noMail = null) {
        
        $Args = $this->req->All();
        
        PTC::exigirArgs($Args, ["InformaRiesgos", "EjecutaTareasPTC"]);

        if ($this->user->usuarioEsOperador()) {

            $pt = $this->show_interno($nroPTC);
            $pt = json_decode($pt);

            if ($pt->IdEstado == "E") {
                if ($this->ptTieneRiesgosAsociados($pt) || $this->ptRequiereMedicionOBloqueo($pt)) {
                    if ($this->ptTieneEjecutadoTodasLasMedicionesYBloqueos($pt)) {
                        $entity = PTC::find($nroPTC);

                        if (!isset($entity)) {
                            throw new NotFoundHttpException('Permiso de trabajo no encontrado');
                        }

                        $entity->IdEstado = 'PL';
                        $entity->InformaRiesgos = $Args['InformaRiesgos'];
                        $entity->EjecutaTareasPTC = $Args['EjecutaTareasPTC'];

                        $entity->save();

                        $this->altaLog($nroPTC, 'PL', 'Aprobación', $Args);

                        if (empty($noMail)) {
                            $this->aprobar_mail($pt);
                        }

                        return true;
                    } 
                    else {
                        throw new HttpException(409, "No se puede solicitar la liberación de un permiso de trabajo si tiene mediciones o bloqueos pendientes de ejecución");
                    }
                }
                else {
                    throw new HttpException(409, "No se puede solicitar la liberación de un permiso de trabajo si no tiene riesgos asociados");
                }
            } else {
                throw new HttpException(409, "No se puede solicitar la liberación de un permiso de trabajo si su estado no es 'En curso'");
            }
        }

        throw new HttpException(409, "El usuario no tiene permisos para realizar esta acción");
    }

    private function ptTieneEjecutadoTodasLasMedicionesYBloqueos($pt) {
        $requerimientos = array("Medicion", "DrenarPurgar", "Limpieza", "Bloqueo", "Inspeccion");

        foreach ($requerimientos as $req) {
            if (!empty($pt->{"Requiere" . $req}) && empty($pt->{"Requiere" . $req . "Ejecutado"})) {
                return false;
            }
        }

        return true;
    }

    private function ptObtenerAprobadores($pt) {
        $aprobadores = DB::select("SELECT DISTINCT u.Email "
                . "FROM Usuarios u "
                . "INNER JOIN PTCAreasUsuarios a ON u.IdUsuario = a.IdUsuario "
                . "INNER JOIN PTCRolesUsuarios pru ON pru.IdUsuario = u.IdUsuario AND pru.Codigo = 'APR' "
                . "WHERE u.Baja = 0 "
                . "AND u.Estado = 1 "
                . "AND u.PTC = 1 "
                . "AND (u.PTCGestion = 1 OR a.IdArea = " . $pt->IdArea . ") "
                . "AND u.RecibeNotificaciones = 1 "
                . "AND pru.RecibeNotificaciones = 1");

        $a = array();
        if(!empty($aprobadores)){
            foreach ($aprobadores as $aprobador){
                $a[] = $aprobador->Email;
            }
        }
        
        return $a;
    }

    private function aprobar_mail($pt) {
        $aprobador = $this->ptObtenerAprobadores($pt);

        try {
            
            if(!empty($aprobador)){
                foreach($aprobador as $a){
                    Mail::to($a)->send(new PTCAprobar($pt));
                }
            }

        } catch (\Exception $err) {
            Log::error('Error al enviar correo aprobar permiso de trabajo');
            throw new HttpException(409, "Se ha solicitado la liberación del permiso de trabajo pero las notificaciones vía mail no pudieron ser enviadas");
        }
    }

    public function autorizar(int $nroPTC) {

        $Args = $this->req->All();

        $pt = $this->show_interno($nroPTC);
        $pt = json_decode($pt);
        
        if ($pt->IdEstado == "E") {
            if ($this->user->usuarioEsAprobador()) {
                $operacion = "Autorización";
                
                if ($this->ptTieneRiesgosAsociados($pt) && $this->user->usuarioEsOperador()) {
                    $autoAprobar = true;
                }
            }
            else if ($this->user->usuarioEsOperador()) {
                if (!$this->user->usuarioEsSoloAprobador()) {
                    PTC::exigirArgs($Args, ["InformaRiesgos", "EjecutaTareasPTC"]);
                }

                if (!$this->ptRequiereMedicionOBloqueo($pt)) {
                    $operacion = "Autorización";
                } else if ($this->ptTieneRiesgosAsociados($pt)) {
                    throw new HttpException(409, "Un operador no puede liberar un pedido de trabajo que tiene riesgos asociados. Para poder liberarlo debe marcar las mediciones o bloqueos que se requieran y continuar el flujo del permiso de trabajo crítico.");
                } else {
                    throw new HttpException(409, "Un operador no puede liberar un pedido de trabajo que requiere medición o bloqueo");
                }
            }
        }
        else if ($pt->IdEstado == "PL") {
            if ($this->user->usuarioEsAprobador()) {
                $operacion = "Autorización";
            }
        } 
        else if ($this->user->usuarioEsOperador() || $this->user->usuarioEsAprobador()) {
            throw new HttpException(409, "El permiso de trabajo sólo puede liberarse si está en curso o pendiente de liberación");
        }

        if (isset($operacion)) {
            
            if (isset($autoAprobar)) {
                $Args['NoMail'] = true;
                $this->aprobar($nroPTC, true);
            }
            
            $entity = PTC::find($nroPTC);

            if (!isset($entity)) {
                throw new NotFoundHttpException('Permiso de trabajo no encontrado');
            }

            $entity->IdEstado = 'L';

            if ($pt->IdEstado == "E" && !$this->user->usuarioEsSoloAprobador()) {
                $entity->InformaRiesgos = $Args['InformaRiesgos'];
                $entity->EjecutaTareasPTC = $Args['EjecutaTareasPTC'];
            }

            if (!empty($Args['AutorizarObs'])) {
                $pt->AutorizarObs = $Args['AutorizarObs'];
                $entity->AutorizarObs = $Args['AutorizarObs'];
            } else {
                $entity->AutorizarObs = '';
                $pt->AutorizarObs = '';
            }

            $entity->save();
            $this->altaLog($nroPTC, 'L', $operacion, $Args);

            $this->autorizar_mail($pt);
            return true;
        }

        throw new HttpException(409, "El usuario no tiene permisos para realizar esta acción");
    }

    private function autorizar_mail($pt) {
        $solicitante = $this->ptObtenerSolicitante($pt);
        try {
            
            if(!empty($solicitante)){
               Mail::to($solicitante)->send(new PTCAutorizar($pt));               
            }

        } catch (\Exception $err) {
            Log::error('Error al enviar correo autorizar permiso de trabajo');
            throw new HttpException(409, "El permiso de trabajo fue liberado pero las notificaciones vía mail no pudieron ser enviadas");
        }
    }

    public function ejecutar(int $nroPTC) {

        $Args = $this->req->All();

        $pt = $this->show_interno($nroPTC);
        $pt = json_decode($pt);

        if ($pt->IdEstado == "L" && $this->user->usuarioEsEjecutante()) {
            
            if (empty($Args['RespTelefono'])) {
                throw new HttpException(409, 'Para marcar como ejecutado un Permiso de Trabajo debe haber completado todos los campos requeridos.');
            }

            $entity = PTC::find($nroPTC);

            if (!isset($entity)) {
                throw new NotFoundHttpException('Permiso de trabajo no encontrado');
            }

            $entity->IdEstado = 'EJE';
            $entity->RespNombre = $this->user->Nombre;
            $entity->RespEmpresa = $pt->IdEmpresa;
            $entity->RespTelefono = $Args['RespTelefono'];
            $entity->RespFechaHora = new \DateTime;

            $entity->save();
            $this->altaLog($nroPTC, 'EJE', 'Ejecución', $Args);

            $this->ejecutar_mail($pt);
            return $nroPTC;
        }

        throw new HttpException(409, "El usuario no tiene permisos para realizar esta acción");
    }

    private function ejecutar_mail($pt) {
        $solicitante = $this->ptObtenerSolicitante($pt);
        $operadores = $this->ptObtenerOperadores($pt);

		try {
            
            if(!empty($solicitante)){
                Mail::to($solicitante)->send(new PTCEjecutar($pt));               
            }

            if(!empty($operadores)){
                foreach($operadores as $operador){
                    Mail::to($operador)->send(new PTCEjecutar($pt));
                }
            }

        } catch (\Exception $err) {
            Log::error('Error al enviar correo ejecutar permiso de trabajo');
            throw new HttpException(409, "El permiso de trabajo pasó a ejecución pero las notificaciones vía mail no pudieron ser enviadas");
        }
    }

    public function revalidar(int $nroPTC) {
        $asunto = '';
        $notificacion = '';
        $pt = $this->show_interno($nroPTC);
        $pt = json_decode($pt);

        if ($pt->IdEstado != "RS" && $pt->IdEstado != "RSL"){
            throw new HttpException(409, 'No es posible revalidar un permiso de trabajo cuyo estado no es \'Revalidación Solicitada\' o \'Revalidación Solicitada (Liberado)\'');
        }

        if ($pt->IdEstado === "RS"){
            $binding = [];
            $binding[':nroPTC'] = $nroPTC;
            $binding[':IdUsuario'] = $this->user->IdUsuario;                    
    
            DB::update("update PTCRevalidaciones set 
                                Estado = 1,
                                IdUsuarioAprobador = :IdUsuario,
                                FechaHora = GETDATE() 
                        where   Estado = 0
                        and     NroPTC = :nroPTC",$binding);
            
            $this->cambiarEstado($nroPTC, 'EJE');

            $asunto = 'Permiso de Trabajo N° '.$pt->NroPTC .' '.($pt->EsDePGP ? '[PGP] ' : '').'[En ejecución]';
            $notificacion = 'El permiso de trabajo N° '.$pt->NroPTC.' fue revalidado';
            $this->altaLog($pt->NroPTC, 'PR', 'PermisoDeTrabajo.revalidar', $pt);
        }

        if ($pt->IdEstado === "RSL"){
            $this->cambiarEstado($nroPTC, 'L');
            
            $asunto = 'Permiso de Trabajo N° '.$pt->NroPTC .' '.($pt->EsDePGP ? '[PGP] ' : '').'[Liberado]';
            $notificacion = 'El permiso de trabajo N° '.$pt->NroPTC.' fue revalidado';
            $this->altaLog($pt->NroPTC, 'L', 'PermisoDeTrabajo.revalidar', $pt);
        }

        $this->enviarCorreoRevalidar($pt, $asunto, $notificacion);        
    }

    private function enviarCorreoRevalidar($permiso, $asunto, $notificacion) {
        $operadores = $this->ptObtenerOperadores($permiso);
        
        try {
            if(!empty($operadores)){
                foreach($operadores as $operador){
                    Mail::to($operador)->send(new PTCEjecutarRevalidacion($permiso, $asunto, $notificacion));
                }
            }

        } catch (\Exception $err) {
            Log::error('Error al enviar correo ejecutar revalidación permiso de trabajo');
            throw new HttpException(409, "Se ejecutar la revalidación del permiso pero las notificaciones vía mail no pudieron ser enviadas");
        }
    }

    public function solicitarRevalidacion(int $nroPTC) {

        $pt = $this->show_interno($nroPTC);
        $pt = json_decode($pt);
        
        if ($pt->IdEstado != "PR" && $pt->IdEstado != "PRL"){
            throw new HttpException(409, 'No es posible solicitar la revalidación de un permiso de trabajo cuyo estado no es \'Pendiente de Revalidación\' o \'Pendiente de Revalidación (Liberado)\'');
        }

        if ($pt->IdEstado === "PR"){

            $revalidacionesPendientes = DB::selectOne("select COUNT(*) as cantidad from PTCRevalidaciones where Estado = 0 and NroPTC = :nroPTC", ['nroPTC' => $nroPTC]);

            // Este chequeo no debería ser necesario (Revisar transaction...)
            if ($revalidacionesPendientes->cantidad > 0) {
                throw new HttpException(409, 'No es posible solicitar la revalidación de un permiso de trabajo que tiene revalidaciones pendientes');
            }
            
            $binding = [];
            $binding[':nroPTC'] = $nroPTC;
            $binding[':IdUsuario'] = $this->user->IdUsuario;                    

            DB::insert("INSERT INTO PTCRevalidaciones (Estado, NroPTC, IdUsuario, FechaHora) 
                            VALUES (0, :nroPTC, :IdUsuario, GETDATE())",$binding);
            
        
            $this->cambiarEstado($nroPTC, 'RS');
            $this->altaLog($nroPTC, 'RS', 'PermisoDeTrabajo.solicitarRevalidacion', $pt);
        }

        if ($pt->IdEstado === "PRL"){
            $this->cambiarEstado($nroPTC, 'RSL');
            $this->altaLog($nroPTC, 'RSL', 'PermisoDeTrabajo.solicitarRevalidacion', $pt);
        }
        
        
        $this->enviarCorreoSolicitarRevalidacion($pt);
    }

    private function enviarCorreoSolicitarRevalidacion($pt) {
        $solicitante = $this->ptObtenerSolicitante($pt);
        $operadores = $this->ptObtenerOperadores($pt);

        $asunto = "Permiso de Trabajo #" . $pt->NroPTC . " " . ($pt->EsDePGP ? '[PGP] ' : '') . "[Revalidación Solicitada]";
        $notificacion = 'La revalidación del permiso de trabajo N° '.$pt->NroPTC.' fue solicitada.';

		try {
            
            if(!empty($solicitante)){
                Mail::to($solicitante)->send(new PTCSolicitarRevalidacion($pt, $asunto, $notificacion));
            }

            if(!empty($operadores)){
                foreach($operadores as $operador){
                    Mail::to($operador)->send(new PTCSolicitarRevalidacion($pt, $asunto, $notificacion));
                }
            }

        } catch (\Exception $err) {
            Log::error('Error al enviar correo solicitar revalidación permiso de trabajo');
            throw new HttpException(409, "Se solicitó la revalidación del permiso pero las notificaciones vía mail no pudieron ser enviadas");
        }
    }

    private function cambiarEstado($nroPTC, $estado) {
        $entity = PTC::find($nroPTC);
        $entity->IdEstado = $estado;
        $entity->save();
    }

    // private function cambiarEstadoLog($nroPTC, $estado)
    // {
    //     $operacion = 'Estado: ' . $estado;
    //     switch ($estado) {
    //         case '': $operacion = 'PermisoDeTrabajo.cerrarParcialmente'; break;
    //         case 'CSE': $operacion = 'PermisoDeTrabajo.cerrarSinEjecucion'; break;
    //         case '': $operacion = 'PermisoDeTrabajo.marcarComoVencido'; break;
    //         case '': $operacion = 'PermisoDeTrabajo.rechazarSolicitud'; break;
    //         case 'RS': $operacion = 'PermisoDeTrabajo.revalidar'; break;
    //         case 'RS': $operacion = 'PermisoDeTrabajo.revalidar'; break;
    //         case 'PR': $operacion = 'PermisoDeTrabajo.solicitarRevalidacion'; break;
    //         case '': $operacion = 'PermisoDeTrabajo.comentario'; break;
    //     }
    //     $obs = json_encode($Observaciones);

    //     $bindings = [];
    //     $bindings[':NroPTC'] = $NroPTC;
    //     $bindings[':IdEstado'] = $IdEstado;
    //     $bindings[':Operacion'] = $Operacion;
    //     $bindings[':IdUsuario'] = isset($this->user) ? $this->user->IdUsuario : LogAuditoria::FSA_USER_DEFAULT;
    //     $bindings[':obs'] = $obs;

    //     DB::insert("INSERT INTO PTCLogActividades (NroPTC, FechaHora, IdEstado, Operacion, IdUsuario, Observaciones)
    //                 VALUES (:NroPTC, GETDATE(), :IdEstado, :Operacion, :IdUsuario, :obs)",
    //                 $bindings);
    // }

    public function finalizarEjecucion(int $nroPTC) {
        $Args = $this->req->All();

        $pt = $this->show_interno($nroPTC);
        $pt = json_decode($pt);
        
        if (($pt->IdEstado == "EJE" || $pt->IdEstado == "CP" || $pt->IdEstado == "V" || $pt->IdEstado == "PR" || $pt->IdEstado == "RS") && $this->user->usuarioEsEjecutante()) {

			$entity = PTC::find($nroPTC);

            if (!isset($entity)) {
                throw new NotFoundHttpException('Permiso de trabajo no encontrado');
            }

            $entity->IdEstado = 'EJD';
            $entity->EjecutanteNombre = $this->user->Nombre;
            $entity->RetiraBloqueos = $Args['RetiraBloqueos'];
            $entity->EjecutorFechaHora = new \DateTime;

            $entity->save();			
			$this->altaLog($nroPTC, 'EJD', 'Fin de ejecución', $Args);

			$this->finalizarejecucion_mail($pt);
            return true;
        }

        throw new HttpException(409, "El usuario no tiene permisos para realizar esta acción");
    }

    private function finalizarejecucion_mail($pt) {
        $solicitante = $this->ptObtenerSolicitante($pt);
		try {
            
            if(!empty($solicitante)){
                Mail::to($solicitante)->send(new PTCFinalizarEjecucion($pt));
            }

        } catch (\Exception $err) {
            Log::error('Error al enviar correo finalizar ejecución permiso de trabajo');
            throw new HttpException(409, "El permiso de trabajo pasó a ejecución pero las notificaciones vía mail no pudieron ser enviadas");
        }
    }

	public function tomarpt(int $nroPTC) {
        $Args = $this->req->All();

        if ($this->user->usuarioEsOperador()) {
            $pt = $this->show_interno($nroPTC);
            $pt = json_decode($pt);
            
            if ($pt->IdEstado == "I") {
                $entity = PTC::find($nroPTC);

                if (!isset($entity)) {
                    throw new NotFoundHttpException('Permiso de trabajo no encontrado');
                }

                $entity->IdEstado = 'E';
                $entity->save();

                $this->altaLog($nroPTC, 'E', 'Solicitud tomada', $Args);
                $this->tomarpt_mail($pt);
                return true;
            } else {
                throw new HttpException(409, "Sólo pueden tomarse los permisos de trabajo en estado ingresado");
            }
        } else {
            throw new HttpException(409, "El usuario no tiene permisos para realizar esta acción");
        }
    }

    private function tomarpt_mail($pt) {
        $solicitante = $this->ptObtenerSolicitante($pt);
        try {
            
            if(!empty($solicitante)){
                Mail::to($solicitante)->send(new PTCTomar($pt));
            }

        } catch (\Exception $err) {
            Log::error('Error al enviar correo tomar permiso de trabajo');
            throw new HttpException(409, "El permiso de trabajo fue tomado pero las notificaciones vía mail no pudieron ser enviadas");
        }
    }

	public function modificacionmasiva() {
        
		$Args = $this->req->All();
		
		$ArrayData = json_decode(json_encode($Args['Datos']), true);
				
		$counter = 0;	
		foreach ($Args['Seleccion'] as $nroPTC){
			
			$ArrayData["NroPTC"] =  $nroPTC;
			
			$Data = json_decode(json_encode($ArrayData));
			
			$pt = $this->show_interno($Data->NroPTC);
			$pt = json_decode($pt);
			
			
			if ($pt->RequiereMedicion) {						
								
				if ($this->user->usuarioEsSYSO() && $this->comprobarArgs($Data, "MODSSO")) {
					$operacion = "Medición";

					$retorno = DB::transaction(function () use ($Args, $Data) {
						$entity = PTC::find($Data->NroPTC);
						$entity->IdEquipoMedicion1 = $Args['IdEquipoMedicion1'];
						$entity->IdEquipoMedicion2 = $Args['IdEquipoMedicion2'];
						$entity->CondAmbNombre =$this->user->Nombre;
						$entity->CondAmbCargo = $Data->CondAmbCargo;
						$entity->CondAmbEquipo = $Data->CondAmbEquipo;
						$entity->CondAmbFechaHora = new DateTime();
						$entity->CondAmbObs = $Data->CondAmbObs;
						
						$entity->save();

						DB::delete("DELETE FROM PTCMediciones WHERE NroPTC = :NroPTC", [":NroPTC" => $Data->NroPTC]);
	
						foreach ($Data->PTCCondicionAmbiental as $medicion) {
							DB::insert("INSERT INTO PTCMediciones (NroPTC, IdCondAmbPTC, Valor) 
								VALUES (:NroPTC, :IdCondAmbPTC, :Valor)",
							[":NroPTC" => $Data->NroPTC, ":IdCondAmbPTC" => $medicion->IdCondAmbPTC, ":Valor" => $medicion->Valor]
						);
						}
					});
					
				}
								
				if (isset($operacion)) {
					$this->altaLog($Data->NroPTC, $pt->IdEstado, $operacion, $Data);
					
					if (empty($pt->RequiereMedicion)) {
						$this->modificacion_mail($pt);
					}
				}
			}
			
			$counter++;
		}
		
		if ($counter>0)
		{
			$retorno = "Se actualizaron $counter mediciones.";
		}
		return $retorno;

    }

	public function tienemediciones(int $id) {

        if ($this->user->usuarioEsSYSO()) {
			$pt = $this->show_interno($id);
            $pt = json_decode($pt);

            if ($this->ptRequiereMedicion($pt)) {

                $mediciones = DB::selectOne("SELECT COUNT(*) AS Cant FROM PTCMediciones WHERE NroPTC = " . $pt->NroPTC);
                return $mediciones->Cant;
            } else {
                throw new HttpException(409, "El permiso de trabajo no requiere mediciones");
            }
        }

        throw new HttpException(409, "El usuario no tiene permisos para realizar esta acción");
    }

	private function ptTieneTodasLasMediciones($pt) {
        return true;
    }

	private static function ptTieneTodasLasOTCerradas($pt) {
        return true;
    }

	public function finalizarmediciones(int $nroPTC) {
		$Args = $this->req->all();
        if ($this->user->usuarioEsSYSO()) {
            $pt = $this->show_interno($nroPTC);
            $pt = json_decode($pt);

            if ($this->ptRequiereMedicion($pt)) {
                if ($this->ptTieneTodasLasMediciones($pt)) {
                    $entity = PTC::find($nroPTC);

					if (!isset($entity)) {
						throw new NotFoundHttpException('Permiso de trabajo no encontrado');
					}

					$entity->IdEstado = 'E';
					$entity->RequiereMedicionEjecutado = 1;
                    
                    $entity->save();
					
                    $this->altaLog($nroPTC, 'E', 'Finalización de Mediciones', $Args);
                    
					$this->finalizarmediciones_mail($pt);
                    return true;
                } else {
                    throw new HttpException(409, "No es posible finalizar las mediciones ya que existen algunas que aún están pendientes");
                }
            } else {
                throw new HttpException(409, "El permiso de trabajo no requiere mediciones");
            }
        }

        throw new HttpException(409, "El usuario no tiene permisos para realizar esta acción");
    }

    private function finalizarmediciones_mail($pt) {
        $operadores = $this->ptObtenerOperadores($pt);
		try {
            
            if(!empty($operadores)){
				foreach($operadores as $operador){
					Mail::to($operador)->send(new PTCFinalizarMediciones($pt));
				}
            }

        } catch (\Exception $err) {
            Log::error('Error al enviar correo finalizar mediciones permiso de trabajo');
            throw new HttpException(409, "Se agregaron correctamente las mediciones al permiso de trabajo pero las notificaciones vía mail no pudieron ser enviadas");
        }        
    }

	public function escritico(int $id) {

        if ($this->user->usuarioEsOperador()) {
            $pt = $this->show_interno($id);
            $pt = json_decode($pt);
            return $this->ptTieneRiesgosAsociados($pt) || $this->ptRequiereMedicionOBloqueo($pt) ? 1 : 0;
        }

        throw new HttpException(409, "El usuario no tiene permisos para realizar esta acción");
    }

    public function tienebloqueosasociados(int $id) {

        if ($this->user->usuarioEsOperador()) {
            $pt = $this->show_interno($id);
            $pt = json_decode($pt);
            return $this->ptRequiereMedicionOBloqueo($pt) ? 1 : 0;
        }

        throw new HttpException(409, "El usuario no tiene permisos para realizar esta acción");
    }

    public function solicitarRevision(int $nroPTC) {        
        $pt = $this->show_interno($nroPTC);
        $pt = json_decode($pt);

        $this->cambiarEstado($nroPTC, 'RL');

        return $this->solicitarRevisionLiberacion_mail($pt);
    }

    private function solicitarRevisionLiberacion_mail($pt) {
        $solicitante = $this->ptObtenerSolicitante($pt); 
        $operadores = $this->ptObtenerOperadores($pt);
		
		try {
            
            if(!empty($solicitante)){
                Mail::to($solicitante)->send(new PTCRevisionLiberacion($pt));
            }

			if(!empty($operadores)){
				foreach($operadores as $operador){
					Mail::to($operador)->send(new PTCRevisionLiberacion($pt));
				}
            }

        } catch (\Exception $err) {
            Log::error('Error al enviar correo solicitar revisión de liberación de permiso de trabajo');
            throw new HttpException(409, "El permiso de trabajo fue marcado para revisión de liberación pero las notificaciones vía mail no pudieron ser enviadas");
        }
    }

    public function aprobarRevisionLiberacion(int $nroPTC) {        
        $pt = $this->show_interno($nroPTC);
        $pt = json_decode($pt);

        $this->cambiarEstado($nroPTC, 'E');

        return $this->aprobarRevisionLiberado_mail($pt);
    }

    private function aprobarRevisionLiberado_mail($pt) {
        $solicitante = $this->ptObtenerSolicitante($pt); 
		
		try {
            
            if(!empty($solicitante)){
                Mail::to($solicitante)->send(new PTCAprobarRevisionLiberacion($pt));
            }

        } catch (\Exception $err) {
            Log::error('Error al enviar correo aprobar revisión de liberación de permiso de trabajo');
            throw new HttpException(409, "Fue aprobada la revisión de liberación del permiso de trabajo pero las notificaciones vía mail no pudieron ser enviadas");
        }
    }

    public function rechazarSolicitud(int $nroPTC) {
        $Args = $this->req->All();
        PTC::exigirArgs($Args, ["Motivo"]);

        $pt = $this->show_interno($nroPTC);
        $pt = json_decode($pt);

        $entity = PTC::find($nroPTC);

        $this->chequearRequerimientosRechazarSolicitud($entity);
        
        $entity->IdEstado = 'RVS';
        $entity->Motivo = $Args['Motivo'];
        
        $entity->save();

        $pt->Motivo = $Args['Motivo'];

        return $this->rechazarSolicitud_mail($pt);
    }

    private function chequearRequerimientosRechazarSolicitud($PTC) {
        if ($PTC->IdEstado != 'I' && $PTC->IdEstado != 'PL' && $PTC->IdEstado != 'E') {
            throw new HttpException(409, 'No es posible rechazar la solicitud de un permiso de trabajo cuyo estado no es Solicitado ni En curso');
        }
    }

    private function rechazarSolicitud_mail($permiso) {
        $solicitante = $this->ptObtenerSolicitante($permiso); 
        
        try {
            if(!empty($solicitante)){
                Mail::to($solicitante)->send(new PTCRechazarSolicitud($permiso));
            }

        } catch (\Exception $err) {
            Log::error('Error al enviar correo rechazar solicitud permiso de trabajo');
            throw new HttpException(409, "El permiso de trabajo fue marcado para revisión pero las notificaciones vía mail no pudieron ser enviadas");
        }
    }

	// Método que invoca el Aprobador para rechazar un PT 
    // que está en estado "PL" y pasa a estado "E" (en curso) 
    public function rechazar(int $nroPTC) {
		$Args = $this->req->All();
        PTC::exigirArgs($Args, ["Motivo"]);

		$pt = $this->show_interno($nroPTC);
        $pt = json_decode($pt);

        $pt->Motivo = $Args['Motivo'];

        if ($pt->IdEstado == "PL" && $this->user->usuarioEsAprobador()) {
			$entity = PTC::find($nroPTC);

			if (!isset($entity)) {
				throw new NotFoundHttpException('Permiso de trabajo no encontrado');
			}

			$entity->IdEstado = 'E';
			$entity->Motivo = $pt->Motivo;
			$entity->save();
			
			$this->altaLog($nroPTC, 'E', 'Rechazo', $Args);

			return $this->rechazar_mail($pt);
            return true;
        }

        throw new HttpException(409, "El usuario no tiene permisos para realizar esta acción");
    }

	public function rechazarejecucion(int $nroPTC) {
        $Args = $this->req->All();
		PTC::exigirArgs($Args, ["Motivo"]);

        $pt = $this->show_interno($nroPTC);
        $pt = json_decode($pt);

       	$pt->Motivo = $Args['Motivo'];

        if ($pt->IdEstado == "EJD" && $this->user->usuarioEsOperador()) {
			$entity = PTC::find($nroPTC);

			if (!isset($entity)) {
				throw new NotFoundHttpException('Permiso de trabajo no encontrado');
			}

			$entity->IdEstado = 'EJE';
			$entity->Motivo = $pt->Motivo;
			$entity->save();
			
			$this->altaLog($nroPTC, 'EJE', 'Rechazo de Ejecución', $Args);

			$this->rechazar_mail($pt);
            return true;
        }

        throw new HttpException(409, "El usuario no tiene permisos para realizar esta acción");
    }

    private function rechazar_mail($pt) {
        $solicitante = $this->ptObtenerSolicitante($pt);
        $operadores = $this->ptObtenerOperadores($pt);
		
		try {
            
            if(!empty($solicitante)){
                Mail::to($solicitante)->send(new PTCRechazar($pt));
            }

			if(!empty($operadores)){
				foreach($operadores as $operador){
					Mail::to($operador)->send(new PTCRechazar($pt));
				}
            }

        } catch (\Exception $err) {
            Log::error('Error al enviar correo rechazar permiso de trabajo');
            throw new HttpException(409, "El permiso de trabajo fue marcado para revisión pero las notificaciones vía mail no pudieron ser enviadas");
        }
    }

	// Método que invoca el Operador para cerrar un PT (Pasarlo a estado
    // Cerrado
    public function cerrar(int $nroPTC, ?array $Args = null) {

        if(!isset($Args)){
            $Args = $this->req->All();
        }		

        if ($this->user->usuarioEsOperador() || $this->user->usuarioEsAprobador() || $this->user->usuarioEsSoloSolicitante()) {
			$pt = $this->show_interno($nroPTC);
			$pt = json_decode($pt);

			$entity = PTC::find($nroPTC);

			if (!isset($entity)) {
				throw new NotFoundHttpException('Permiso de trabajo no encontrado');
			}
			
            if ($pt->IdEstado == "EJD" && ($this->user->usuarioEsAprobador() || $this->user->usuarioEsOperador())) {

                if ($this->ptTieneTodasLasOTCerradas($pt)) {
                    PTC::exigirArgs($Args, ["FechaCierre", "AceptaCondArea"]);
                    $this->comprobarArgs($Args, "CPR");

					$FechaCierre = FsUtils::strToDate($Args['FechaCierre'].'00:00:00', FsUtils::DDMMYYHHMMSS);

					$entity->IdEstado = 'CPR';
					$entity->AceptaCondArea = $Args['AceptaCondArea'];
					$entity->FechaCierre = $FechaCierre;
					$entity->save();

                    $operacion = "Cerrado por operador";
                    $IdEstado = 'CPR';

                    $bindings = [];
                    $bindings[':NroPTC'] = $nroPTC;
                    $sql = "SELECT NroPTCSucesor FROM PTCVinculados WHERE NroPTCPredecesor = :NroPTC";

                    $Sucesores = DB::select($sql, $bindings);

                    foreach($Sucesores as $Sucesor){
                        $pt = $this->show_interno($Sucesor->NroPTCSucesor);
			            $pt = json_decode($pt);
                        $this->verificarPadresEjecutados($pt);
                    }
                    
                } else {
                    throw new HttpException(409, "El pedido de trabajo no se puede cerrar si quedan OTs por cerrar");
                }
            } 
            
            else if (($this->user->usuarioEsOperador() && ($pt->IdEstado == "E" || $pt->IdEstado == "L" || $pt->IdEstado == "PL" || $pt->IdEstado == "EJE" || $pt->IdEstado == "PR" || $pt->IdEstado == "RS" || $pt->IdEstado == "PRL" || $pt->IdEstado == "RSL")) || 
                    ($this->user->usuarioEsAprobador() && ($pt->IdEstado == "L" || $pt->IdEstado == "PL" || $pt->IdEstado == "EJE" || $pt->IdEstado == "PR" || $pt->IdEstado == "RS" || $pt->IdEstado == "PRL" || $pt->IdEstado == "RSL")) || 
                    ($this->user->usuarioEsOperador() || $this->user->usuarioEsAprobador() && ($pt->IdEstado == "V")) || 
                    (isset($Args['VieneDeProcedimiento']) && ($pt->IdEstado == "L" || $pt->IdEstado == "PRL" || $pt->IdEstado == "RSL")) ||
                    ($this->user->usuarioEsSoloSolicitante() && ($pt->IdEstado == "I" || $pt->IdEstado == "RVS" || $pt->IdEstado == "L" || $pt->IdEstado == "E" || $pt->IdEstado == "V")) ) {
                
                PTC::exigirArgs($Args, ["Motivo"]);
                // Cerrado sin ejecución

				$entity->IdEstado = 'CSE';
				$entity->Motivo = $Args['Motivo'];
				$entity->save();

                $IdEstado = 'CSE';
                $operacion = "Cerrado sin ejecución";
                $asunto = "Permiso de Trabajo #" . $pt->NroPTC . " " . ($pt->EsDePGP ? '[PGP] ' : '') . "[Cerrado sin liberación]";
                $notificacion = "El permiso de trabajo N° " . $nroPTC . " fue cerrado sin liberación: " . $Args['Motivo'];
            } 
            
            else {
                throw new HttpException(409, "El permiso de trabajo sólo se puede cerrar si está ejecutado, en ejecución, liberado, pendiente de liberación, en curso o vencido");
            }

            if (isset($operacion)) {
				
				$this->altaLog($nroPTC, $IdEstado, $operacion, $Args);

                if (isset($asunto) && isset($notificacion)) {
                   $this->cerrar_mail($pt, $asunto, $notificacion);
                }

                return true;
            }
        }

        throw new HttpException(409, "El usuario no tiene permisos para realizar esta acción");
    }

    public function cerrarSinEjecuccion(int $nroPTC, string $motivo)
    {
        $entity = PTC::find($nroPTC);
        $entity->IdEstado = 'CSE';
        $entity->Motivo = $motivo;
        $entity->save();

        $IdEstado = 'CSE';
        $operacion = "Cerrado sin ejecución";
        $asunto = "Permiso de Trabajo #" . $entity->NroPTC . " " . ($entity->EsDePGP ? '[PGP] ' : '') . "[Cerrado sin liberación]";
        $notificacion = "El permiso de trabajo N° " . $nroPTC . " fue cerrado sin liberación: " . $motivo;

        $this->altaLog($nroPTC, $IdEstado, $operacion, ['Motivo' => $motivo]);
        $this->cerrar_mail($entity, $asunto, $notificacion);
    }

    public function cerrarParcialmente(int $nroPTC) {
        $Args = $this->req->All();

        $pt = $this->show_interno($nroPTC);
		$pt = json_decode($pt);

        $entity = PTC::find($nroPTC);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Permiso de trabajo no encontrado');
        }

        
        if ($pt->IdEstado == "EJD"){
            
            PTC::exigirArgs($Args, ["Motivo", "EjecCierreParcialObs"]);

            $entity->IdEstado = 'CP';
            $entity->Motivo = $Args['Motivo'];
            $entity->EjecCierreParcialObs = $Args['EjecCierreParcialObs'];
            $entity->save();

            $asunto = "Permiso de Trabajo #" . $pt->NroPTC . " " . ($pt->EsDePGP ? '[PGP] ' : '') . "[Cerrado parcialmente]";
            $notificacion = "El permiso de trabajo N° " . $nroPTC . " fue cerrado parcialmente con motivo: " . $Args['Motivo'];

            $this->cerrar_mail($pt, $asunto, $notificacion);
        }else{
            throw new HttpException(409, 'No es posible cerrar parcialmente un permiso de trabajo que no está esperando aceptación de cierre');
        }
    }

    private function cerrar_mail($pt, $asunto, $notificacion) {
        $solicitante = $this->ptObtenerSolicitante($pt);
        $operadores = $this->ptObtenerOperadores($pt);
		
		try {
            
            if(!empty($solicitante)){
                Mail::to($solicitante)->send(new PTCCerrar($pt, $asunto, $notificacion));
            }

			if(!empty($operadores)){
				foreach($operadores as $operador){
					Mail::to($operador)->send(new PTCCerrar($pt, $asunto, $notificacion));
				}
            }

        } catch (\Exception $err) {
            Log::error('Error al enviar correo cerrar permiso de trabajo');
            throw new HttpException(409, "El permiso de trabajo fue cerrado pero las notificaciones vía mail no pudieron ser enviadas");
        }
    }

	public function delete(int $nroPTC) {
		$Args = $this->req->All();

		$entity = PTC::find($nroPTC);

        if ($entity->IdEstado == "I" && $this->user->usuarioEsSolicitante()) {
			
			$entity->Baja = 1;
			$entity->IdUsuarioBaja = $this->user->IdUsuario;
			$entity->FechaHoraBaja = new \DateTime;
            $entity->save();

			$this->altaLog($nroPTC, 'B', 'Baja', $Args);

            return true;
        }

        throw new HttpException(409, "El usuario no tiene permisos para realizar esta acción");
    }

	public function busqueda() {
       
		$Args = $this->req->All();

		$bindings = [];

        $sql = "SELECT 'func=PTCAdministracion|NroPTC=' + LTRIM(RTRIM(STR(ptc.NroPTC))) AS ObjUrl,
                    ptc.NroPTC, 
                    ptc.NroOT,
                    ptc.Descripcion AS DescripcionTrabajo,
                    ptc.UbicacionFuncional,
                    ptc.PlanBloqueoExistente,
                    ptc.PlanDrenajeExistente,
                    ptc.PlanLimpiezaExistente,
                    u.Nombre AS NombreUsuario,
                    a.Nombre AS NombreArea, ";

        if ($this->user->usuarioEsSoloSolicitante()) {
            $sql .= " ptce.Nombre AS Estado, ";
        } else {
            $sql .= " CASE
                                WHEN ptc.IdEstado <> 'E' AND ptc.IdEstado <> 'L' THEN ptce.Nombre
                                WHEN ptc.IdEstado = 'L' THEN ptce.Nombre
                                ELSE
                                    CASE
                                        WHEN (ptc.RequiereBloqueo = 1 AND NULLIF(ptc.RequiereBloqueoEjecutado, 0) IS NULL) THEN 'Esperando bloqueo'
                                        WHEN (ptc.RequiereInspeccion = 1 AND NULLIF(ptc.RequiereInspeccionEjecutado, 0) IS NULL) THEN 'Esperando inspección interna'
                                        WHEN (ptc.RequiereDrenarPurgar = 1 AND NULLIF(ptc.RequiereDrenarPurgarEjecutado, 0) IS NULL) THEN 'Esperando drenaje'
                                        WHEN (ptc.RequiereLimpieza = 1 AND NULLIF(ptc.RequiereLimpiezaEjecutado, 0) IS NULL) THEN 'Esperando limpieza'
                                        WHEN (ptc.RequiereMedicion = 1 AND NULLIF(ptc.RequiereMedicionEjecutado, 0) IS NULL AND EsperandoPorPTCVinculado = 1) THEN 'Esperando por PT asociado'
                                        WHEN (ptc.RequiereMedicion = 1 AND NULLIF(ptc.RequiereMedicionEjecutado, 0) IS NULL) THEN 'Esperando medición'
                                        ELSE 'En curso'
                                    END
                                END AS Estado,";
        }

        $sql .= "   CASE
                                WHEN ptc.IdEstado = 'I' THEN '1'
                                WHEN ptc.IdEstado = 'E' THEN 
                                    CASE
                                        WHEN (ptc.RequiereMedicion = 1 AND NULLIF(ptc.RequiereMedicionEjecutado, 0) IS NULL) THEN '3'
                                        WHEN (ptc.RequiereBloqueo = 1 AND NULLIF(ptc.RequiereBloqueoEjecutado, 0) IS NULL) OR (ptc.RequiereInspeccion = 1 AND NULLIF(ptc.RequiereInspeccionEjecutado, 0) IS NULL) OR (ptc.RequiereDrenarPurgar = 1 AND NULLIF(ptc.RequiereDrenarPurgarEjecutado, 0) IS NULL) OR (ptc.RequiereLimpieza = 1 AND NULLIF(ptc.RequiereLimpiezaEjecutado, 0) IS NULL) THEN '4'
                                        ELSE '2'
                                    END
                                WHEN ptc.IdEstado = 'PL' THEN '5'
                                WHEN ptc.IdEstado = 'L' THEN '6'
                                WHEN ptc.IdEstado = 'CPR' THEN '7'
                                WHEN ptc.IdEstado = 'CSE' THEN '8'
                                ELSE '999'
                            END AS SortLevel,";

        $sql.= " e.Documento + '-' + LTRIM(RTRIM(STR(ptc.IdTipoDocumento))) AS IdEmpresa,
                                CONVERT(varchar(10), ptc.FechaHoraComienzoPrev, 103) + ' ' + CONVERT(varchar(8), ptc.FechaHoraComienzoPrev, 108) AS InicioPrevisto,
                                CONVERT(varchar(10), ptc.FechaHoraFinPrev, 103) + ' ' + CONVERT(varchar(8), ptc.FechaHoraFinPrev, 108) AS FinalPrevisto,
                                e.Nombre AS Empresa 
                            FROM PTC ptc
                            INNER JOIN PTCAreas a ON ptc.IdArea = a.IdArea
                            INNER JOIN Usuarios u ON ptc.IdUsuario = u.IdUsuario
                            INNER JOIN Empresas e ON e.Documento = ptc.Documento AND e.IdTipoDocumento = ptc.IdTipoDocumento 
                            INNER JOIN PTCEstados ptce ON ptc.IdEstado = ptce.Codigo";

        if (empty($this->user->PTCGestion)) {
            $sql .= " INNER JOIN PTCAreasUsuarios ptcau ON ptc.IdArea = ptcau.IdArea AND ptcau.IdUsuario = '" . $this->user->IdUsuario . "' ";
        }

        if (!empty($Args['Operador'])) {
			$bindings[':Operador'] = "%" . $Args['Operador'] . "%";
            $sql .= " INNER JOIN PTCLogActividades ptclog ON ptc.NroPTC = ptclog.NroPTC AND ptclog.Operacion = 'Solicitud tomada' AND ptclog.IdUsuario LIKE :Operador ";
        }
		else if (!empty($Args['Aprobador'])) {
			$bindings[':Aprobador'] = "%" . $Args['Aprobador'] . "%";
            $sql .= " INNER JOIN PTCLogActividades ptclog ON ptc.NroPTC = ptclog.NroPTC AND ptclog.Operacion = 'Autorización' AND ptclog.IdUsuario LIKE :Aprobador";
        }

        $sql .= " WHERE ptc.Baja = 0 ";

        if (!empty($Args['Busqueda'])) {

			$bindings[':Busqueda1'] = $Args['Busqueda'];
			$bindings[':Busqueda2'] = "%" . $Args['Busqueda'] . "%";
			$bindings[':Busqueda3'] = "%" . $Args['Busqueda'] . "%";
			$bindings[':Busqueda4'] = "%" . $Args['Busqueda'] . "%";
			$bindings[':Busqueda5'] = "%" . $Args['Busqueda'] . "%";
			$bindings[':Busqueda6'] = "%" . $Args['Busqueda'] . "%";
			$bindings[':Busqueda7'] = "%" . $Args['Busqueda'] . "%";

            $sql .= " AND (LTRIM(RTRIM(STR(ptc.NroPTC))) = :Busqueda1 OR "
                    . "ptce.Nombre COLLATE Latin1_general_CI_AI LIKE :Busqueda2 OR "
                    . "ptc.Descripcion COLLATE Latin1_general_CI_AI LIKE :Busqueda3 OR "
                    . "ptc.UbicacionFuncional COLLATE Latin1_general_CI_AI LIKE :Busqueda4 OR "
                    . "u.Nombre COLLATE Latin1_general_CI_AI LIKE :Busqueda5 OR "
                    . "e.Nombre COLLATE Latin1_general_CI_AI LIKE :Busqueda6 OR "
                    . "a.Nombre COLLATE Latin1_general_CI_AI LIKE :Busqueda7)";
        }
        
        if (isset($Args['PGP']) && $Args['PGP'] != 'Todos') {
            if ($Args['PGP'] == -1) {
                $sql .= " AND (ptc.EsDePGP = 0 OR ptc.EsDePGP IS NULL) ";
            }
            else if (!empty($Args['PGP'])){
				$bindings[':PGP'] = $Args['PGP'];
                $sql .= " AND ptc.EsDePGP = 1 AND ptc.AnhoPGP = :PGP";
            }
        }
        
        // Chequear permisos sobre empresa para usuarios NO Gestión?
        if (empty($this->user->Gestion)) {
                $empresa = Empresa::loadBySession($this->req);

                $bindings[':IdEmpresaObj0'] = $empresa->Documento;
                $bindings[':IdEmpresaObj1'] = $empresa->IdTipoDocumento;
                $sql .= ' AND ptc.Documento = :IdEmpresaObj0 and ptc.IdTipoDocumento = :IdEmpresaObj1';

        } else if (!empty($Args['IdEmpresa'])) {
            $IdEmpresaObj = FsUtils::explodeId($Args['IdEmpresa']);

			$bindings[':IdEmpresaObj0'] = $IdEmpresaObj[0];
			$bindings[':IdEmpresaObj1'] = $IdEmpresaObj[1];
            $sql .= ' AND ptc.Documento = :IdEmpresaObj0 and ptc.IdTipoDocumento = :IdEmpresaObj1';
        }

        if (!empty($Args['Codigo'])) {
            if ($Args['Codigo'][0] == '>') {
                switch ($Args['Codigo']) {
                    case '>EB':
                        $sql .= " AND ptc.IdEstado = 'E' AND ptc.RequiereBloqueo = 1 AND NULLIF(ptc.RequiereBloqueoEjecutado, 0) IS NULL ";
                        break;
                    case '>EI':
                        $sql .= " AND ptc.IdEstado = 'E' AND ptc.RequiereInspeccion = 1 AND NULLIF(ptc.RequiereInspeccionEjecutado, 0) IS NULL ";
                        break;
                    case '>ED':
                        $sql .= " AND ptc.IdEstado = 'E' AND ptc.RequiereDrenarPurgar = 1 AND NULLIF(ptc.RequiereDrenarPurgarEjecutado, 0) IS NULL ";
                        break;
                    case '>EL':
                        $sql .= " AND ptc.IdEstado = 'E' AND ptc.RequiereLimpieza = 1 AND NULLIF(ptc.RequiereLimpiezaEjecutado, 0) IS NULL ";
                        break;
                    case '>EM':
                        $sql .= " AND ptc.IdEstado = 'E' AND ptc.RequiereMedicion = 1 AND NULLIF(ptc.RequiereMedicionEjecutado, 0) IS NULL ";
                        break;
                }
            }
            else {
				$bindings[':Codigo'] = $Args['Codigo'];
                $sql .= " AND ptc.IdEstado = :Codigo";
            }
        }

		if (!empty($Args['NroPTC'])) { 
			$bindings[':NroPTC'] = $Args['NroPTC'];
            $sql .= " AND ptc.NroPTC = :NroPTC";
		}
		if (!empty($Args['NroOT'])) { 
			$bindings[':NroPTC'] = "%" . $Args['NroPTC'] . "%";
			$bindings[':NroOT1'] = "%" . $Args['NroPTC'] . "%";
            $sql .= " AND (ptc.NroOT LIKE :NroPTC OR EXISTS(SELECT ptcot.NroOT FROM PTCOT ptcot WHERE ptcot.NroPTC = ptc.NroPTC AND ptcot.NroOT LIKE :NroOT1";
		}
        if (!empty($Args['OTsEstado'])) {
            $bindings[':OTEstado'] = $Args['OTsEstado'];
            $sql .= " AND EXISTS(SELECT ptcot.NroOT FROM PTCOT ptcot INNER JOIN PTCOTEstados EO ON PTCOT.Estado = EO.Codigo WHERE ptcot.NroPTC = ptc.NroPTC and EO.Codigo = :OTEstado)";
		}
		if (!empty($Args['DescripcionTrabajo'])) { 
			$bindings[':DescripcionTrabajo'] = "%" . $Args['DescripcionTrabajo'] . "%";
            $sql .= " AND ptc.Descripcion LIKE :DescripcionTrabajo";
		}
		if (!empty($Args['UbicacionFuncional'])) { 
			$bindings[':UbicacionFuncional'] = "%" . $Args['UbicacionFuncional'] . "%";
            $sql .= " AND ptc.UbicacionFuncional LIKE :UbicacionFuncional";
		}
		if (!empty($Args['FechaDesdeComienzoPrev'])) {
			$bindings[':FechaDesdeComienzoPrev'] = FsUtils::strToDate($Args['FechaDesdeComienzoPrev'].' 00:00:00', FsUtils::DDMMYYHHMMSS);
            $sql .= " AND CONVERT(date, ptc.FechaHoraComienzoPrev, 103) >= :FechaDesdeComienzoPrev";
		}
		if (!empty($Args['FechaHastaComienzoPrev'])) {
			$bindings[':FechaHastaComienzoPrev'] = FsUtils::strToDate($Args['FechaHastaComienzoPrev'].' 00:00:00', FsUtils::DDMMYYHHMMSS);
            $sql .= " AND CONVERT(date, ptc.FechaHoraComienzoPrev, 103) <= :FechaHastaComienzoPrev";
		}
		if (!empty($Args['FechaDesdeFinPrev'])) {
			$bindings[':FechaDesdeFinPrev'] = FsUtils::strToDate($Args['FechaDesdeFinPrev'].' 00:00:00', FsUtils::DDMMYYHHMMSS);
            $sql .= " AND CONVERT(date, ptc.FechaHoraFinPrev, 103) >= :FechaDesdeFinPrev";
		}
		if (!empty($Args['FechaHastaFinPrev'])) {
			$bindings[':FechaHastaFinPrev'] = FsUtils::strToDate($Args['FechaHastaFinPrev'].' 00:00:00', FsUtils::DDMMYYHHMMSS);
            $sql .= " AND CONVERT(date, ptc.FechaHoraFinPrev, 103) <= :FechaHastaFinPrev";
		}
		if (!empty($Args['IdArea'])) { 
			$bindings[':IdArea'] = $Args['IdArea'];
            $sql .= " AND ptc.IdArea = :IdArea";
		}
		if (!empty($Args['PlanBloqueoExistente'])) { 
			$bindings[':PlanBloqueoExistente'] = "%" . $Args['PlanBloqueoExistente'] . "%";
            $sql .= " AND ptc.PlanBloqueoExistente LIKE :PlanBloqueoExistente";
		}
		if (isset($Args['PlanDrenajeExistente']) && !empty($Args['PlanDrenajeExistente'])) { 
			$bindings[':PlanDrenajeExistente'] = "%" . $Args['PlanDrenajeExistente'] . "%";
            $sql .= " AND ptc.PlanDrenajeExistente LIKE :PlanDrenajeExistente";
		}
		if (isset($Args['PlanLimpiezaExistente']) && !empty($Args['PlanLimpiezaExistente'])) { 
			$bindings[':PlanLimpiezaExistente'] = "%" . $Args['PlanLimpiezaExistente'] . "%";
            $sql .= " AND ptc.PlanLimpiezaExistente LIKE :PlanLimpiezaExistente";
		}
		if (!empty($Args['NombreUsuario'])) { 
			$bindings[':NombreUsuario'] = "%" . $Args['NombreUsuario'] . "%";
            $sql .= " AND ptc.IdUsuario LIKE :NombreUsuario";
		}

		$sql .= " order by SortLevel";

        $registros = DB::select($sql, $bindings);

        $output = $this->req->input('output', 'json');
        if ($output !== 'json') {
            $dataOutput = array_map(function($item) {
                return [
                    'Estado' => $item->Estado,
                    'NroPTC' => $item->NroPTC,
                    'UbicacionFuncional' => $item->UbicacionFuncional,
                    'DescripcionTrabajo' => $item->DescripcionTrabajo,
                    'NombreUsuario' => $item->NombreUsuario,
                    'NombreEmpresa' => $item->Empresa,
                    'NombreArea' => $item->NombreArea,
                    'PlanBloqueoExistente' => $item->PlanBloqueoExistente,
                    'PlanDrenajeExistente' => $item->PlanDrenajeExistente,
                    'PlanLimpiezaExistente' => $item->PlanLimpiezaExistente,
                    'InicioPrevisto' => $item->InicioPrevisto,
                    'FinalPrevisto' => $item->FinalPrevisto
                ];
            },$registros);
            return $this->export($dataOutput, $output);
        }
        
		$page = (int)$this->req->input('page', 1);        
        $paginate = FsUtils::paginateArray($registros, $this->req);
        return $this->responsePaginate($paginate->items(), $paginate->total(), $page);
    }

    public function establecerRequiereBloqueo(int $nroPTC) {
		$entity = PTC::find($nroPTC);
        $entity->RequiereBloqueo = 1;
        $entity->save();
    }

    public function establecerRequiereBloqueoEjecutado(int $nroPTC) {
		$Args = $this->req->All();

        $entity = PTC::find($nroPTC);
        
        if($entity->RequiereBloqueo == 1 && !empty($entity->PlanBloqueoExistente)){

            $entity->RequiereBloqueoEjecutado = 1;
            $entity->save();

            if(isset($Args['RequiereBloqueoAsociados']) && $entity->RequiereBloqueoEjecutado === true && !empty($entity->EsDePGP) && !empty($entity->PlanBloqueoExistente)){
                $arrPlanBloqueoExistente = explode(',', $entity->PlanBloqueoExistente);
                $bindingsBloqueo = [];
                $where = '';
                $bindingsBloqueo[] = $entity->AnhoPGP;
                $bindingsBloqueo[] = $entity->IdArea;

                for($i = 0; $i < count($arrPlanBloqueoExistente); $i++) {
                    $bindingsBloqueo[] = $arrPlanBloqueoExistente[$i];
                    if ($i === 0) { $where .= ' and (PlanBloqueoExistente = ?'; }
                    else
                    { $where .= ' or PlanBloqueoExistente = ?'; }
                }
                if(!empty($where)){ $where .= ' )'; }

                DB::UPDATE("UPDATE PTC SET RequiereBloqueoEjecutado = 1 WHERE EsDePGP = 1 and AnhoPGP = ? and IdArea = ? ".$where, $bindingsBloqueo);
            }
            
        }else{
            throw new HttpException(409, 'Debe requerir bloqueo y tener plan de bloqueo agregado.');
        }
        
    }
    
    public function establecerRequiereInspeccion(int $nroPTC) {
        $entity = PTC::find($nroPTC);
        $entity->RequiereInspeccion = 1;
        $entity->save();
    }
    
    public function establecerRequiereDrenaje(int $nroPTC) {
        $entity = PTC::find($nroPTC);
        $entity->RequiereDrenarPurgar = 1;
        $entity->save();
    }
    
    public function establecerRequiereDrenajeEjecutado(int $nroPTC) {
        $entity = PTC::find($nroPTC);

        if($entity->RequiereDrenarPurgar == 1){
            $entity->RequiereDrenarPurgarEjecutado = 1;
            $entity->save();
        }else{
            throw new HttpException(409, 'Debe requerir drenar purgar.');
        }
    }
    
    public function establecerRequiereLimpieza(int $nroPTC) {
        $entity = PTC::find($nroPTC);
        $entity->RequiereLimpieza = 1;
        $entity->save();
    }
    
    public function establecerRequiereLimpiezaEjecutado(int $nroPTC) {
        $entity = PTC::find($nroPTC);

        if($entity->RequiereLimpieza == 1){
            $entity->RequiereLimpiezaEjecutado = 1;
            $entity->save();
        }
    }
    
    public function establecerRequiereMedicion(int $nroPTC) {
        $entity = PTC::find($nroPTC);
        $entity->RequiereMedicion = 1;
        $entity->save();
    }

    public function exportarPDF(int $nroPTC){

        $filename = 'PTC-'.$nroPTC;

        $pt = $this->show_interno($nroPTC);
		$pt = json_decode($pt);

        $fpdf = $this->fsa_pt_fpdf_from_obj($pt);

        if (isset($fpdf)) {
            $targetpath = $this->fsa_pt_fpdf_to_pdf($fpdf, $pt->PTCTipos);

            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment;filename="' . $filename . '.pdf"');
        
            header('Cache-Control: max-age=0');
            // If you're serving to IE 9, then the following may be needed
            header('Cache-Control: max-age=1');
            // If you're serving to IE over SSL, then the following may be needed
            header ('Expires: Mon, 03 Jan 1991 05:00:00 GMT'); // Date in the past
            header ('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
            header ('Cache-Control: cache, must-revalidate'); // HTTP/1.1
            header ('Pragma: public'); // HTTP/1.0
            header ('Access-Control-Allow-Origin: http://localhost:8000');
            echo file_get_contents($targetpath);
        }
        
        if (file_exists($fpdf)) unlink($fpdf);
        if (file_exists($targetpath)) unlink($targetpath);
    }

    private function fsa_pt_fpdf_from_obj($obj) {
        $pdfFields = array();
        
        if (!empty($obj->EmpresaEsMdP)) $pdfFields["EmpresaEsMdP"] = "X";
        else $pdfFields["EmpresaEsContratista"] = "X";
    
        $pdfFields["NroPTC"] = "N° " . $obj->NroPTC;
        $pdfFields["NombreEmpresa"] = $obj->NombreEmpresa;
        $pdfFields["NombreArea"] = $obj->NombreArea;
        $pdfFields["DescripcionTrabajo"] = $obj->UbicacionFuncional . ' / \n' . $obj->DescripcionTrabajo;
        $pdfFields["NroOT"] = $obj->NroOT;
        $pdfFields["NombreSolicitante"] = $obj->NombreSolicitante;
        $pdfFields["TelefonoContacto"] = $obj->TelefonoContacto;
        $pdfFields["CantidadPersonas"] = $obj->CantidadPersonas;
    
        $pdfFields["FechaComienzoPrev"] = $obj->FechaComienzoPrev;
        $pdfFields["HoraComienzoPrev"] = $obj->HoraComienzoPrev;
        $pdfFields["FechaFinPrev"] = $obj->FechaFinPrev;
        $pdfFields["HoraFinPrev"] = $obj->HoraFinPrev;
    
        // Sección B
        if (!empty($obj->CierreTapa)) {
            $pdfFields["TiposCierreTapas"] = "X";
        }
        if (!empty($obj->AperturaTapa)) {
            $pdfFields["TiposAperturaTapas"] = "X";
        }
        if (empty($obj->PTCTipos)) {
            $pdfFields["TiposNA"] = "X";
        } 
        else {
            foreach ($obj->PTCTipos as $tipo) {
                switch ($tipo->Nombre) {
                    case "Contacto con energía eléctrica / mecánica / vapor / otra":
                        $cell = "TiposContactoEnergia";
                        break;
                    case "Espacios Confinados":
                        $cell = "TiposEspaciosConfinados";
                        break;
                    case "Trabajo en Caliente":
                        $cell = "TiposTrabajoCaliente";
                        break;
                    case "Equipos radiactivos":
                        $cell = "TiposEquiposRadiactivos";
                        break;
                    case "Tarea no rutinaria c/productos químicos":
                        $cell = "TiposProdQuimicos";
                        break;
                    case "Izaje de personas o cargas que superen el 75% de capacidad de la grúa":
                        $cell = "TiposIzaje";
                        break;
                    case "Excavaciones":
                        $cell = "TiposExcavaciones";
                        break;
                    case "Descarga de Lógica":
                        $cell = "TiposDescargaLogica";
                        break;
                    case "Otros*":
                        $cell = "TiposOtros";
                        $pdfFields["TiposOtrosObs"] = $obj->PTCTiposOtroObs;
                        break;
                    default:
                        $cell = "TiposOtros";
                        $pdfFields["TiposOtrosObs"] = $tipo->Nombre;
                        break;
                }
    
                if (isset($cell)) {
                    $pdfFields[$cell] = "X";
                }
            }
        }
    
        if ($obj->FsRC != "entered") {
            if (empty($obj->PTCRiesgos)) {
                if ($obj->FsRC != "entered" && $obj->FsRC != "taken" && $obj->FsRC != "waiting for blocking action" && $obj->FsRC != "waiting for measurement") {
                    $pdfFields["RiesgosNA"] = "X";
                }
            } 
            else {
                foreach ($obj->PTCRiesgos as $riesgo) {
                    switch ($riesgo->Nombre) {
                        case "Contacto con químicos":
                            $cell = "RiesgosContactoQuimicos";
                            break;
                        case "Contacto con energía eléctrica / mecánica / vapor / otra":
                            $cell = "RiesgosContactoVapor";
                            break;
                        case "Inhalación de gases":
                            $cell = "RiesgosInhalacionGases";
                            break;
                        case "Zonas con Atmósferas Explosivas y NCG":
                            $cell = "RiesgosAtmosferasExplosivas";
                            break;
                        case "Atrapamiento":
                            $cell = "RiesgosAtrapamiento";
                            break;
                        case "Otros*":
                            $cell = "RiesgosOtros";
                            $pdfFields["RiesgosOtrosObs"] = $obj->PTCRiesgosOtroObs;
                            break;
                        default:
                            $cell = "RiesgosOtros";
        //                                    $pdfFields["RiesgosOtrosObs"] = $riesgo->Nombre;
                            break;
                    }
    
                    if (isset($cell)) {
                        $pdfFields[$cell] = "X";
                    }
                }
            }
    
            // Sección C
            if (!empty($obj->PTCEquipos)) {
                foreach ($obj->PTCEquipos as $equipo) {
                    switch ($equipo->Nombre) {
                        case "Traje para Químicos":
                            $cell = "EquiposTrajeQuimicos";
                            break;
                        case "Botas para Químicos":
                            $cell = "EquiposBotasQuimicos";
                            break;
                        case "Guantes Específicos":
                            $cell = "EquiposGuantesEspecificos";
                            break;
                        case "Protección Auditiva":
                            $cell = "EquiposProteccionAuditiva";
                            break;
                        case "Extintor":
                            $cell = "EquiposExtintor";
                            break;
                        case "Antiparras":
                            $cell = "EquiposAntiparras";
                            break;
                        case "Equipo de Respiración Autónomo":
                            $cell = "EquiposRespiracion";
                            break;
                        case "Protección Facial (Careta)":
                            $cell = "EquiposProteccionFacial";
                            break;
                        case "Protección Anticaídas (Arnés u otro)":
                            $cell = "EquiposProteccionAnticaidas";
                            break;
                        case "Protección Respiratoria (Especificar)":
                            $cell = "EquiposProteccionRespiratoria";
                            break;
                        case "Manta ignífuga":
                            $cell = "EquiposMantaIgnifuga";
                            break;
                        case "Detectores gases":
                            $cell = "EquiposDetectoresGases";
                            break;
                        case "Otros (Especificar)":
                            $cell = "EquiposOtros";
                            //$pdfFields["EquiposObs"] = $obj->EquiposObs;
                            break;
                        default:
                            $cell = "EquiposOtros";
                            $pdfFields["EquiposObs"] = $equipo->Nombre;
                            break;
                    }
    
                    if (isset($cell)) {
                        $pdfFields[$cell] = "X";
                    }
                }
            }
            $pdfFields["EquiposObs"] = $obj->EquiposObs;
    
            //$sheet->setCellValue("S47", $obj->EquiposObs);
    
            // Sección D
            if ($obj->FsRC != "entered" && $obj->FsRC != "taken" && $obj->FsRC != "waiting for blocking action" && $obj->FsRC != "waiting for measurement") {
                $pdfFields[(!empty($obj->RequiereBloqueo) ? "" : "No") . "RequiereBloqueo"] = "X";
                $pdfFields[(!empty($obj->RequiereDrenarPurgar) ? "" : "No") . "RequiereDrenarPurgar"] = "X";
                $pdfFields[(!empty($obj->RequiereLimpieza) ? "" : "No") . "RequiereLimpieza"] = "X";
                $pdfFields[(!empty($obj->RequiereMedicion) ? "" : "No") . "RequiereMedicion"] = "X";
                $pdfFields[(!empty($obj->RequiereInspeccion) ? "" : "No") . "RequiereInspeccion"] = "X";
            }
            else {
                if (!empty($obj->RequiereBloqueo)) $pdfFields["RequiereBloqueo"] = "X";
                if (!empty($obj->RequiereDrenarPurgar)) $pdfFields["RequiereDrenarPurgar"] = "X";
                if (!empty($obj->RequiereLimpieza)) $pdfFields["RequiereLimpieza"] = "X";
                if (!empty($obj->RequiereMedicion)) $pdfFields["RequiereMedicion"] = "X";
                if (!empty($obj->RequiereInspeccion)) $pdfFields["RequiereInspeccion"] = "X";
            }
            
            if (!empty($obj->RequiereBloqueoEjecutado)) $pdfFields["RequiereBloqueoEjecutado"] = "X";
            if (!empty($obj->RequiereDrenarPurgarEjecutado)) $pdfFields["RequiereDrenarPurgarEjecutado"] = "X";
            if (!empty($obj->RequiereLimpiezaEjecutado)) $pdfFields["RequiereLimpiezaEjecutado"] = "X";
            if (!empty($obj->RequiereMedicionEjecutado)) $pdfFields["RequiereMedicionEjecutado"] = "X";
            if (!empty($obj->RequiereInspeccionEjecutado)) $pdfFields["RequiereInspeccionEjecutado"] = "X";
    
            $pdfFields["PlanBloqueoExistente"] = $obj->PlanBloqueoExistente;
            $pdfFields["PlanBloqueoObs"] = $obj->PlanBloqueoObs;
    
            // Sección E
            if(!empty($obj->PTCCondicionAmbiental)) {
                foreach ($obj->PTCCondicionAmbiental as $cond) {
                    if (isset($cond->Valor)) {
                        switch ($cond->Nombre) {
                            case "O2":
                                $cell = "CondAmbO2";
                                $pdfFields["CondAmbO2Valor"] = $cond->Valor;
                                break;
                            case "Comb/ex":
                                $cell = "CondAmbCombEx";
                                $pdfFields["CondAmbCombExValor"] = $cond->Valor;
                                break;
                            case "Ruido":
                                $cell = "CondAmbRuido";
                                $pdfFields["CondAmbRuidoValor"] = $cond->Valor;
                                break;
                            case "Carga Térmica":
                                $cell = "CondAmbCargaTermica";
                                $pdfFields["CondAmbCargaTermicaValor"] = $cond->Valor;
                                break;
                            case "H2S":
                                $cell = "CondAmbH2S";
                                $pdfFields["CondAmbH2SValor"] = $cond->Valor;
                                break;
                            case "CO":
                                $cell = "CondAmbCO";
                                $pdfFields["CondAmbCOValor"] = $cond->Valor;
                                break;
                            case "ClO2":
                                $cell = "CondAmbClO2";
                                $pdfFields["CondAmbClO2Valor"] = $cond->Valor;
                                break;
                            case "Otros (Especificar)":
                                $cell = "CondAmbOtros";
                                $pdfFields["CondAmbOtrosValor"] = $cond->Valor;
                                break;
                            default:
                                $cell = "CondAmbOtros";
                                $condAmbObs2 = $cond->Nombre . ": " . $cond->Valor . " " . $cond->UnidadMedida;
                                $pdfFields["CondAmbObs"] = $condAmbObs2;
                                $pdfFields["CondAmbOtrosValor"] = $condAmbObs2; // $cond->Valor;
                                break;
                        }
                    }
    
                    if (isset($cell)) {
                        $pdfFields[$cell] = "X";
                    }
                }
            }
    
            $pdfFields["CondAmbObs"] = (isset($condAmbObs) ? $condAmbObs . "; " : "") . $obj->CondAmbObs;
            $pdfFields["CondAmbNombre"] = $obj->CondAmbNombre;
            $pdfFields["CondAmbCargo"] = !empty($obj->CondAmbNombre) ? 'SYSO' : null; // $obj->CondAmbCargo;
            $pdfFields["CondAmbEquipo"] = $obj->IdEquipoMedicion1.($obj->IdEquipoMedicion2 !== null ? ' / '.$obj->IdEquipoMedicion2 : null);
            $pdfFields["CondAmbFechaHora"] = $obj->CondAmbFecha . ' ' . $obj->CondAmbHora;
    
            // Sección F
            if (!empty($obj->InformaRiesgos)) $pdfFields["InformaRiesgos"] = "Sí";
            else if (   $obj->FsRC != "entered" && 
                        $obj->FsRC != "taken" && 
                        $obj->FsRC != "waiting for blocking action" && 
                        $obj->FsRC != "waiting for measurement" && 
                        $obj->FsRC != "release in process") 
                $pdfFields["InformaRiesgos"] = "N/A";
    
            if (!empty($obj->EjecutaTareasPTC)) $pdfFields["EjecutaTareasPTC"] = "Sí";
            else if (   $obj->FsRC != "entered" && 
                        $obj->FsRC != "taken" && 
                        $obj->FsRC != "waiting for blocking action" && 
                        $obj->FsRC != "waiting for measurement" && 
                        $obj->FsRC != "release in process") 
                $pdfFields["EjecutaTareasPTC"] = "N/A";
            
            if (!empty($obj->AutorizarObs)) 
                $pdfFields["AutorizarObs"] = $obj->AutorizarObs;
    
            if (!empty($obj->UsuarioAprueba)) $pdfFields["UsuarioGestionLiberacion"] = $obj->UsuarioAprueba;
            if (!empty($obj->UsuarioAutoriza)) {
                $tieneRiesgos = !empty($obj->PTCTipos) || !empty($obj->PTCRiesgos);
                $requiereBloqueo = !empty($obj->RequiereDrenarPurgar) || !empty($obj->RequiereLimpieza) || !empty($obj->RequiereBloqueo);
                if ($tieneRiesgos || $requiereBloqueo) $cell = "UsuarioResponsableOperaciones";
                else $cell = "UsuarioGestionLiberacion";
                $pdfFields[$cell] = $obj->UsuarioAutoriza;
            }
    
            // Sección H
            if (!empty($obj->AceptaCondArea)) $pdfFields["AceptaCondArea"] = "Sí";
            
            if ($obj->FsRC == "executed" || $obj->FsRC == "closed without ex" || $obj->FsRC == "closed resp") {
                
                
            if (!empty($obj->RetiraBloqueos)) $pdfFields["RetiraBloqueos"] = "Sí";
                else $pdfFields["RetiraBloqueos"] = "N/A";
            }
    
            if (!empty($obj->FechaCierre)) $pdfFields["FechaCierra"] = $obj->FechaCierre;
            if (!empty($obj->UsuarioCierra)) $pdfFields["UsuarioCierra"] = $obj->UsuarioCierra;
        }
    
        if ($obj->FsRC == "executed" || $obj->FsRC == "closed without ex" || $obj->FsRC == "closed resp") {
            $pdfFields["EjecNombre"] = $obj->EjecutanteNombre;
            $pdfFields["EjecEmpresa"] = $obj->EjecEmpresa;
            $pdfFields["EjecTelefono"] = $obj->EjecTelefono;
            $pdfFields["EjecFecha"] = $obj->EjecFecha;
            $pdfFields["EjecHora"] = $obj->EjecHora;
            
            if (!empty($obj->InformaRiesgos)) $pdfFields["InformaRiesgos"] = "Sí";
            if (!empty($obj->EjecutaTareasPTC)) $pdfFields["EjecutaTareasPTC"] = "Sí";
        }
        
        $pdfFields["RespNombre"] = !empty($obj->RespNombre) ? $obj->RespNombre : '';
        $pdfFields["RespFechaHoraComienzo"] = !empty($obj->RespFechaHoraComienzo) ? $obj->RespFechaHoraComienzo : '';
        $pdfFields["RespTelefono"] = !empty($obj->RespTelefono) ? $obj->RespTelefono : '';
        
        for ($i = 1; $i <= 5; $i++) {
            if (!empty($obj->{"OpRevalidacion" . $i})) {
                $pdfFields["FechaHoraRevalidacion" . $i] = $obj->{"FechaHoraRevalidacion" . $i};
                $pdfFields["EjecRevalidacion" . $i] = $obj->{"EjecRevalidacion" . $i};
                $pdfFields["OpRevalidacion" . $i] = $obj->{"OpRevalidacion" . $i};
            }
        }
        
        
        // Creo un arhivo fpdf con los fields
        $fpdf = storage_path('app/temp/'. md5(rand()) . ".fdf");
        //$fpdf = DMZ_LOCAL_URL . "/var/" . md5(rand()) . ".fdf";
        $fpdfFile = fopen($fpdf, "w");
    
        fwrite($fpdfFile, "%FDF-1.2\n1 0 obj<</FDF<< /Fields[");
    
        foreach ($pdfFields as $k => $v) {
            fwrite($fpdfFile, "<</T(" . $k . ")/V(" . str_replace(array('(', ')'), array('\\(', '\\)'), utf8_decode($v)) . ")>>");
        }
    
        fwrite($fpdfFile, "] >> >>\nendobj\ntrailer\n<</Root 1 0 R>>\n%%EOF");
        fclose($fpdfFile);
        
        return $fpdf;
    }

    private function fsa_pt_fpdf_to_pdf($fpdf, $PTCTipos = []) {
        $tempname = md5(rand());
        $i = 0;
        //$temppath = DMZ_LOCAL_URL . "/var/" . $tempname . ".xlsx";
        //$targetpath = DMZ_LOCAL_URL."/var/".$tempname; // .".pdf"
        $targetpath = storage_path('app/temp/'. $tempname);
    
        //$pdf->Output($targetpath);
    
        //$cmd = 'pdftk.exe "'.DMZ_LOCAL_URL.'/data/@pt.pdf" fill_form "'.$fpdf.'" output "'.$targetpath.'-'.$i.'.pdf" flatten';
        $cmd = 'pdftk.exe "'.storage_path('app/ptc/templates/@pt.pdf').'" fill_form "'.$fpdf.'" output "'.$targetpath.'-'.$i.'.pdf" flatten';
        $cmd = str_replace('/', '\\', $cmd);
        $output = shell_exec($cmd);
    
        //fs_log_global('fs_pdf.log', $cmd);
        $this->altaLog('fs_pdf.log', 'I', 'Alta', $cmd);
    
        if (!empty($PTCTipos)) {
            foreach ($PTCTipos as $tipo) {
                switch ($tipo->Nombre) {
                    case "Espacios Confinados":
                        $cell = "TiposEspaciosConfinados";
                        break;
                    case "Trabajo en Caliente":
                        $cell = "TiposTrabajoCaliente";
                        break;
                    case "Excavaciones":
                        $cell = "TiposExcavaciones";
                        break;
                }
                if (isset($cell)) {
                    //$checklistpath = DMZ_LOCAL_URL."/data/@pt-checklist-".$cell.".pdf";
                    //$checklisttargetpath = DMZ_LOCAL_URL."/var/".$tempname."-".$cell.".checklist.pdf";
                    
                    $checklistpath = storage_path('app/ptc/templates/@pt-checklist-'.$cell.'.pdf');
                    $checklisttargetpath = storage_path("app/temp/".$tempname."-".$cell.".checklist.pdf");

                    if (file_exists($checklistpath)) {
                        // fill checklist
                        $cmd = 'pdftk.exe "'.$checklistpath.'" fill_form "'.$fpdf.'" output "'.$checklisttargetpath.'" flatten';
                        $cmd = str_replace('/', '\\', $cmd);
                        $output = shell_exec($cmd);
                        // join pdfs
                        $cmd = 'pdftk.exe "'.$targetpath.'-'.$i++.'.pdf" "'.$checklisttargetpath.'" cat output "'.$targetpath.'-'.$i.'.pdf"';
                        $cmd = str_replace('/', '\\', $cmd);
                        $output = shell_exec($cmd);
                        unlink($checklisttargetpath);
                    }
                }
                unset($cell);
            }
    
            for ($j = 0; $j < $i; $j++) {
                unlink($targetpath.'-'.$j.'.pdf');
            }
            //unlink($fpdf);
            
            // rename($targetpath.'-'.$i.'.pdf', $targetpath.'.pdf');
            // return $targetpath.'.pdf';
        }
        
        unlink($fpdf);
        rename($targetpath.'-'.$i.'.pdf', $targetpath.'.pdf');
        return $targetpath.'.pdf';
    }

    public function crearMedicionesMasivas() {

        $Args = $this->req->all();

        if($Args['cantTanques'] > 0 && $Args['EsDePGP'] && count($Args['PTCTanques']) === 0){
            throw new HttpException(409, 'Debe seleccionar uno o más tanques.');
        }

        $medicionConValor = false;
        foreach($Args['mediciones'] as $medicion){
            if(isset($medicion['Valor'])){
                $medicionConValor = true;
                if ($medicion['IdCondAmbPTC'] == 999) {
                    DB::table('PTC')
                    ->whereIn('NroPTC', $Args['NrosPTC'])
                    ->update(['PTCMedicion999Nombre' => $medicion['Nombre'], 'PTCMedicion999Unidad' => $medicion['UnidadMedida']]);
                }

                $this->crearMedicion($medicion, $Args);
            }
        }

        $RequiereMedicionEjecutado = null;
        if (!empty($Args['Finish'])) { // DontFinish
            $faltaEquipoMedicion = [];
            $faltaMediciones = [];
                
            foreach($Args['NrosPTC'] as $nroPTC){
                $entity = PTC::find($nroPTC);
                if(empty($Args['IdEquipoMedicion1']) && empty($entity->IdEquipoMedicion1)){
                    $faltaEquipoMedicion[] = $nroPTC;
                }

                $tieneMediciones = DB::selectOne("SELECT COUNT(1) AS Total from PTCMediciones where nroPTC = :nroPTC and valor is not null", ['nroPTC' => $nroPTC]);
                if(!$medicionConValor && $tieneMediciones->Total == 0){
                    $faltaMediciones[] = $nroPTC;
                }
            }

            if(!empty($faltaEquipoMedicion)){
                throw new HttpException(409, 'Debe indicar un Equipo de medición para el PT N° ('.implode(', ',$faltaEquipoMedicion).')');
            }
            if(!empty($faltaMediciones)){
                throw new HttpException(409, 'Debe agregar mediciones al PT N° ('.implode(', ',$faltaMediciones).')');
            }
            

            $RequiereMedicionEjecutado = 1;
        }

        $condAmbFechaHora = new DateTime();

        $atributos = ['CondAmbNombre' => $this->user->Nombre, 'RequiereMedicionEjecutado' => $RequiereMedicionEjecutado, 'CondAmbFechaHora' => $condAmbFechaHora];

        if(!empty($Args['IdEquipoMedicion1'])){
            $atributos += ['IdEquipoMedicion1' => $Args['IdEquipoMedicion1']];
        }
        if(!empty($Args['IdEquipoMedicion2'])){
            $atributos += ['IdEquipoMedicion2' => $Args['IdEquipoMedicion2']];
        }
        if(!empty($Args['Observaciones'])){
            $atributos += ['CondAmbObs' => $Args['Observaciones']];
        }
        

        DB::table('PTC')
        ->whereIn('NroPTC', $Args['NrosPTC'])
        ->update($atributos);
        
    }

    private function crearMedicion($medicion, $Args){
        
        if (empty($Args['IdEquipoMedicion1'])) {
            throw new HttpException(409, 'Debe indicar un Equipo de medición');
        }

        foreach($Args['NrosPTC'] as $nroPTC) {

            if (isset($Args['PTCTanques']) && !empty($Args['PTCTanques'])) {
                foreach ($Args['PTCTanques'] as $idTanque) {


                    $existeTanque = DB::select("SELECT 1 AS existe FROM PTCPTCTanques WHERE IdTanque = :IdTanque AND NroPTC = :NroPTC", [':IdTanque' => $idTanque, ':NroPTC' => $nroPTC]);

                    if (count($existeTanque) !== 0) {
                        
                        DB::delete("delete PTCMedicionesTanques WHERE IdTanque = :IdTanque AND NroPTC = :NroPTC and IdCondAmbPTC = :IdCondAmbPTC", [':IdTanque' => $idTanque, ':NroPTC' => $nroPTC, 'IdCondAmbPTC' => $medicion['IdCondAmbPTC']]);

                        DB::insert("insert into PTCMedicionesTanques (IdTanque, IdCondAmbPTC, NroPTC) values(:IdTanque, :IdCondAmbPTC, :NroPTC)", 
                            [':IdTanque' => $idTanque, ':IdCondAmbPTC' => $medicion['IdCondAmbPTC'], ':NroPTC' => $nroPTC]);
                    }
                }
            }

            DB::table('PTCMediciones')
            ->where('NroPTC', '=', $nroPTC)
            ->where('IdCondAmbPTC', '=', $medicion['IdCondAmbPTC'])
            ->delete();

            DB::table('PTCMediciones')->insert([
                'IdCondAmbPTC' => $medicion['IdCondAmbPTC'],
                'Valor' => $medicion['Valor'],
                'NroPTC' => $nroPTC
            ]);
        }
    }

    public function createDocs(int $nroPTC) {

		$Args = $this->req->All();

		$retornoInsert = false;

        $file = $this->req->file('importarAdjunto');
        $filenameOriginal = $file->getClientOriginalName();
        $filename = 'PTCDocs-' . $nroPTC . '-' . uniqid() . '.' . $file->getClientOriginalExtension();

        $file->storeAs('uploads/ptc/docs', $filename);

        $retornoInsert = DB::insert("INSERT INTO PTCDocs (NroPTC, IdUsuario, Archivo, Nombre, FechaHora) 
            VALUES(:NroPTC, :IdUsuario, :filenamee, :Nombre, GETDATE())",
            [":IdUsuario" => $this->user->IdUsuario, ":filenamee" => $filename , ":Nombre" => $filenameOriginal, ":NroPTC" => $nroPTC]);

        if ($retornoInsert) {
            $data = [
                'filename' => $filename,
            ];

            $idPTCDoc = DB::select("SELECT TOP 1 IdPTCDoc, NroPTC FROM PTCDocs WHERE NroPTC = :NroPTC AND IdUsuario = :IdUsuario AND Archivo = :filenamee ORDER BY FechaHora DESC",
                [":NroPTC" => $nroPTC, ":IdUsuario" => $this->user->IdUsuario, ":filenamee" => $filename]);

            LogAuditoria::log(
                Auth::id(),
                PTC::class,
                LogAuditoria::FSA_METHOD_CREATE,
                $Args,
                $this->user->IdUsuario,
                $filenameOriginal. ' (' . $idPTCDoc[0]->NroPTC . ')'
            );

            return $data;
        } else {
            throw new HttpException(409, 'Error al guardar el archivo');
        }
    }

    public function indexDocs(int $id) {

		return DB::select("SELECT d.IdPTCDoc, d.NroPTC, d.IdUsuario, d.Archivo, d.Nombre, CONVERT(varchar(10), d.FechaHora, 103) + ' ' + CONVERT(varchar(8), d.FechaHora, 108) AS FechaHora , u.Nombre as UsuarioNombre 
						FROM PTCDocs d
						INNER JOIN Usuarios u ON d.IdUsuario = u.IdUsuario
						WHERE d.NroPTC = :NroPTC AND d.Baja = 0 ORDER BY d.FechaHora ASC", [":NroPTC" => $id]);
    }

    public function indexPersonas(int $id) {

		$registros = DB::select("   SELECT  p.Documento, p.IdTipoDocumento, pf.NombreCompleto, e.Nombre Empresa,
                                            dbo.Mask(p.Documento, td.Mascara, 1, 1) AS DocumentoMasked
                                    FROM PTCPTCPersonas p
                                    INNER JOIN PersonasFisicas pf ON p.IdTipoDocumento = pf.IdTipoDocumento and p.Documento = pf.Documento
                                    INNER JOIN TiposDocumento td ON (p.IdTipoDocumento = td.IdTipoDocumento)
                                    LEFT JOIN PersonasFisicasEmpresas pfe ON (pf.Documento = pfe.Documento AND pf.IdTipoDocumento = pfe.IdTipoDocumento
                                                                    AND pfe.FechaAlta <= GETDATE()
                                                                    AND (pfe.FechaBaja IS NULL OR pfe.FechaBaja > GETDATE()))
                                    LEFT JOIN Empresas e ON (pfe.DocEmpresa = e.Documento AND pfe.TipoDocEmpresa = e.IdTipoDocumento)
                                    WHERE p.NroPTC = :NroPTC ORDER BY pf.NombreCompleto", [":NroPTC" => $id]);

        return $registros;
    }

    public function deleteDocs(int $IdPTCDoc) {

        $retornoUpdate = DB::update("UPDATE PTCDocs SET Baja = 1 WHERE IdPTCDoc = :IdPTCDoc AND IdUsuario = :IdUsuario AND Baja = 0",
        [":IdPTCDoc" => $IdPTCDoc, ":IdUsuario" => $this->user->IdUsuario]);

        if ($retornoUpdate) {
            $PTCDoc = DB::select("SELECT TOP 1 IdPTCDoc, NroPTC, Nombre, Archivo FROM PTCDocs WHERE IdPTCDoc = :IdPTCDoc AND IdUsuario = :IdUsuario ORDER BY FechaHora DESC",
            [":IdPTCDoc" => $IdPTCDoc, ":IdUsuario" => $this->user->IdUsuario]);

            $pathName = storage_path('app/uploads/ptc/docs/'.$PTCDoc[0]->Archivo);

            if (file_exists($pathName)) unlink($pathName);

            LogAuditoria::log(
                Auth::id(),
                PTC::class,
                LogAuditoria::FSA_METHOD_DELETE,
                $IdPTCDoc,
                '',
                $PTCDoc[0]->Nombre . ' (' . $PTCDoc[0]->NroPTC . ')'
            );

            return true;
        } else {
            throw new HttpException(409, 'Error al eliminar el archivo');
        }
    }

    public function verDocs($fileName){

        $adjunto = storage_path('app/uploads/ptc/docs/'.$fileName);

        $content_type = mime_content_type($adjunto);

        if (isset($adjunto)) {
            header('Content-Type: '. $content_type);
            header('Content-Disposition: attachment;filename="' . $fileName . '"');
        
            header('Cache-Control: max-age=0');
            // If you're serving to IE 9, then the following may be needed
            header('Cache-Control: max-age=1');
            // If you're serving to IE over SSL, then the following may be needed
            header ('Expires: Mon, 03 Jan 1991 05:00:00 GMT'); // Date in the past
            header ('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
            header ('Cache-Control: cache, must-revalidate'); // HTTP/1.1
            header ('Pragma: public'); // HTTP/1.0
            echo file_get_contents($adjunto);
        }

    }

    public function createDocBloqueo(int $nroPTC) {

		$retornoUpdate = false;
        
        $Args = $this->req->All();
        $pathName = storage_path('app/ptc/planes-bloqueo/'.$Args['filename']);
        if (file_exists($pathName)) unlink($pathName);

        $file = $this->req->file('importarAdjunto');
        $filename = 'RequiereBloqueoDoc-' . $nroPTC . '-' . str_replace(' ', '.', $this->user->Nombre) . '.' . $file->getClientOriginalExtension();

        $file->storeAs('ptc/planes-bloqueo', $filename);

        $retornoUpdate = DB::update("update PTC set RequiereBloqueoDoc = :filenamee where NroPTC= :NroPTC", [":filenamee" => $filename, ":NroPTC" => $nroPTC]);

        if ($retornoUpdate) {

            LogAuditoria::log(
                Auth::id(),
                PTC::class,
                LogAuditoria::FSA_METHOD_CREATE,
                [$nroPTC, $filename],
                $this->user->IdUsuario,
                $filename. ' (' . $nroPTC . ')'
            );

            return $filename;
        } else {
            throw new HttpException(409, 'Error al guardar el archivo');
        }
    }

    public function deleteDocBloqueo(int $nroPTC) {

        $Args = $this->req->All();

        $retornoUpdate = DB::update("UPDATE PTC SET RequiereBloqueoDoc = null WHERE nroPTC = :nroPTC", [":nroPTC" => $nroPTC]);

        if ($retornoUpdate) {

            $pathName = storage_path('app/ptc/planes-bloqueo/'.$Args['filename']);

            if (file_exists($pathName)) unlink($pathName);

            LogAuditoria::log(
                Auth::id(),
                PTC::class,
                LogAuditoria::FSA_METHOD_DELETE,
                $nroPTC,
                '',
                $nroPTC . ' (' . $Args['filename'] . ')'
            );

            return true;
        } else {
            throw new HttpException(409, 'Error al eliminar el archivo');
        }
    }

    public function verDocBloqueo($fileName){

        $adjunto = storage_path('app/ptc/planes-bloqueo/'.$fileName);

        $content_type = mime_content_type($adjunto);

        if (isset($adjunto)) {
            header('Content-Type: '. $content_type);
            header('Content-Disposition: attachment;filename="' . $fileName . '"');
        
            header('Cache-Control: max-age=0');
            // If you're serving to IE 9, then the following may be needed
            header('Cache-Control: max-age=1');
            // If you're serving to IE over SSL, then the following may be needed
            header ('Expires: Mon, 03 Jan 1991 05:00:00 GMT'); // Date in the past
            header ('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
            header ('Cache-Control: cache, must-revalidate'); // HTTP/1.1
            header ('Pragma: public'); // HTTP/1.0
            //header ('Access-Control-Allow-Origin: http://localhost:8000');
            echo file_get_contents($adjunto);
        }

    }
}
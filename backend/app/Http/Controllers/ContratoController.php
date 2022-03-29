<?php

namespace App\Http\Controllers;

use App\Models\BaseModel;
use App\Models\Acceso;
use App\FsUtils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class ContratoController extends Controller
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
        $this->user = auth()->user();
    }

    public function index()
    {
        $binding = [];

        $Args = $this->req->all();

        $sql = "SELECT * FROM (";
        $orderBy = "Entidad, Detalle";
        
        switch ($Args['Funcion']) {
            case 'ContratosFaltantes':
                return $this->wsBusquedaFaltantes($Args);
            case 'ContratosHabilitados':
                return $this->wsBusquedaHabilitados_sql($Args);
                //$sql .= self::wsBusquedaHabilitados_sql($Usuario, $IdEmpresa, $Args, $MaxFilas, $NroPagina);
                break;
            case 'HorasTrabajadas':
                return $this->horasTrabajadas($Args);
            case 'ContratosIngresosDiarios':
                $accesos = $Args['Accesos'];
                $ingresos = array();
                
                if (empty($accesos)) {
                    /*$accesos = mzacceso::wslistado($Usuario, $IdEmpresa, (object)array(
                        'Plain' => true
                    ));*/

                    $selectAccesos = Acceso::where('Baja', 0)->get();
                    $retornoAccesos = [];
                    foreach($selectAccesos as $acceso){
                        array_push($retornoAccesos, $acceso['IdAcceso']);
                    }
                    $accesos = $retornoAccesos;
                }

                if (empty($Args['Contratos'])) {
                   
                    $Args['Contratos'] = $this->listado((object)['PlainList' => true]);
                    $Args['Contratos'] = array_map(function ($contrato){
                        return $contrato['Identificador'];
                    }, $Args['Contratos']);
                }
                
                foreach ($Args['Contratos'] as $contrato) {
                    $ingresosContrato = array();
                    $tempArgs = $Args;
                    $tempArgs['Contratos'] = array($contrato);
                    
                    foreach ($accesos as $acceso) {
                        $tempArgs['Accesos'] = array($acceso);
                        $retornoBusquedaIngresosDiarios = $this->wsBusquedaIngresosDiarios_sql($tempArgs);
                        $ingresosAccesoSql = $retornoBusquedaIngresosDiarios[0];
                        $ingresosAccesosBindings = $retornoBusquedaIngresosDiarios[1];
                        $ingresosAcceso = DB::select($ingresosAccesoSql, $ingresosAccesosBindings);
                        
                        if (empty($ingresosContrato)) {
                            $ingresosContrato = $ingresosAcceso;
                        }
                        else {
                            $ingresosContrato[0]['Personas'] += $ingresosAcceso[0]['Personas'];
                            $ingresosContrato[0]['Maquinas'] += $ingresosAcceso[0]['Maquinas'];
                            $ingresosContrato[0]['Vehiculos'] += $ingresosAcceso[0]['Vehiculos'];
                        }
                    }
                    
                    $ingresos = array_merge($ingresos, $ingresosContrato);
                }
                
                $page = (int)$this->req->input('page', 1);        
                $paginate = FsUtils::paginateArray($ingresos, $this->req);
                return $this->responsePaginate($paginate->items(), $paginate->total(), $page);


            case 'ContratosVencimientosDocs':
                return $this->wsBusquedaVencimientosDocs($Args);    
            case 'ContratosVencimientos':
                $retornoBusquedaVencimientos = $this->wsBusquedaVencimientos_sql($Args);
                $sql .= $retornoBusquedaVencimientos[0];

                foreach($retornoBusquedaVencimientos[1] as $key => $valor){
                    $binding[$key] = $valor;
                }
                break;       
        }
        
        $sql .= ") cc ORDER BY " . $orderBy;

        $items = DB::select($sql, $binding);

        if($Args['Funcion'] === 'ContratosVencimientos'){
            $output = $this->req->input('output', 'json');
            if ($output !== 'json') {
                $dataOutput = array_map(function($item) {
                    return [
                        'EmpContratante' => $item->EmpContratante,
                        'EmpContratada' => $item->EmpContratada,
                        'NroContrato' => $item->NroContrato,
                        'FechaDesde' => $item->FechaDesde,
                        'FechaHasta' => $item->FechaHasta,
                        'DescEstado' => $item->DescEstado,
                    ];
                },$items);
                return $this->exportVencimientos($dataOutput, $output);
            }
        }
        
        $page = (int)$this->req->input('page', 1);        
        $paginate = FsUtils::paginateArray($items, $this->req);
        return $this->responsePaginate($paginate->items(), $paginate->total(), $page);
    }

    private function exportVencimientos(array $data, string $type) {
        $filename = 'FSAcceso-Vencimientos-' . date('Ymd his');
        $headers = [
            'EmpContratante' => 'Empresa Contratante',
            'EmpContratada' => 'Empresa Contratada',
            'NroContrato' => 'Contrato',
            'FechaDesde' => 'Fecha Desde',
            'FechaHasta' => 'Fecha Hasta',
            'DescEstado' => 'Estado',
        ];
        return FsUtils::export($type, $data, $headers, $filename);
    }

    private function wsBusquedaFaltantes($Args) {     
        
        throw new HttpException(409, "Componente no definido");
        /*$Args->TipoListado = "Faltantes";
        $Args->ConNroContrato = true;
        unset($Args->Funcion);
        return mzdocumento::wsbusqueda($Usuario, $IdEmpresa, $Args, $MaxFilas, $NroPagina);*/
    }
    
    private function wsBusquedaHabilitados_sql($Args) {

        throw new HttpException(409, "Componente no definido");
        /*$Args->ConNroContrato = true;
        //unset($Args->Funcion);
        return mzcontratado::wsbusquedahabilitados_sql($Usuario, $IdEmpresa, $Args, $MaxFilas, $NroPagina);*/
    }

    private function horasTrabajadas($Args = []) {

        if (!property_exists((object) $Args, 'IdAcceso') && property_exists((object) $Args, 'Accesos') && is_array($Args['Accesos'])) {
            if (count((array)$Args['Accesos']) > 0) {
                $Args['IdAcceso'] = $Args['Accesos'][0];
            } else {
                // throw new \Exception('Seleccione un Acceso');
                return [];
            }
            unset($Args['Accesos']);
        }
        
        BaseModel::exigirArgs($Args, array("FechaDesde", "FechaHasta", "IdAcceso"));
        
        $sql = "";
        $eventFilters = "";
        
        if((!empty($Args['FechaDesde']) && !empty($Args['FechaHasta'])) && $Args['FechaDesde'] == $Args['FechaHasta']) {
            $eventFilters .= " AND CONVERT(date, e.fechaHora, 103) = CONVERT(date, '". $Args['FechaDesde']. " 00:00:00', 103)";
        } else {
            if(!empty($Args['FechaDesde'])){
                $eventFilters .= " AND CONVERT(date, e.fechaHora, 103) >= CONVERT(date, '". $Args['FechaDesde']. " 00:00:00', 103)";
            }
            
            if(!empty($Args['FechaHasta'])){
                $eventFilters .= " AND CONVERT(date, e.FechaHora, 103) <= CONVERT(date, '". $Args['FechaHasta']. " 00:00:00', 103)";
            }
        }
        if (!empty($Args['Contratos'])) {
            $i = 0;
            $first = true;
            $eventFilters .= " AND EC.nroContrato IN (";
            foreach ($Args['Contratos'] as $contrato) {
                $i++;
                if (!$first) {
                    $eventFilters .= ", ";
                } else {
                    $first = false;
                }
                $eventFilters .= "'" . $contrato . "'";
            }
            $eventFilters .= ")";
        }
        
        $p = [];
        $m = [];
        $v = [];

        if (!empty($Args['Personas'])) {
            $p = $this->horasTrabajadasPersonas($Args, $eventFilters);
        }
        if (!empty($Args['Maquinas'])) {
            $m = $this->horasTrabajadasMaquinas($Args, $eventFilters);
        }
        if (!empty($Args['Vehiculos'])) {
            $v = $this->horasTrabajadasVehiculos($Args, $eventFilters);
        }
		
        $list = $p;
                
        foreach ($m as $maquina) {
            $added = false;
			
            foreach ($list as $contrato) {
                if ($contrato['NroContrato'] == $maquina['NroContrato']) {
                    if (!isset($contrato['HorasMaq'])) {
                        $contrato['HorasMaq'] = 0;
                    }
                    $contrato['HorasMaq'] += (int)$contrato['HorasMaq'];
                    $added = true;
                    break;
                }
            }
            
            if (!$added) {
                $list[] = $maquina;
            }
        }
        
        foreach ($v as $vehiculo) {
            $added = false;
            
            foreach ($list as $contrato) {
                if ($contrato['NroContrato'] == $vehiculo['NroContrato']) {
                    if (!isset($contrato['HorasVehic'])) {
                        $contrato['HorasVehic'] = 0;
                    }
                    $contrato['HorasVehic'] += (int)$contrato['HorasVehic'];
                    $added = true;
                    break;
                }
            }
            
            if (!$added) {
                $list[] = $vehiculo;
            }
        }
        
        return $list;
    }

    private function horasTrabajadasPersonas($Args, $eventFilters) {
        $sql = "SELECT DISTINCT E.documento, E.idTipoDocumento, PF.primerNombre, PF.segundoNombre, PF.primerApellido, PF.segundoApellido, EMP.nombre AS empresa, P.idCategoria
                FROM Eventos E INNER JOIN PersonasFisicas PF ON E.documento = PF.documento AND E.idTipoDocumento = PF.idTipoDocumento
                INNER JOIN Personas P ON PF.documento = P.documento AND PF.idTipoDocumento = P.idTipoDocumento
                LEFT JOIN Empresas EMP ON PF.docEmpresa = EMP.documento AND PF.tipoDocEmpresa = EMP.idTipoDocumento
                INNER JOIN EventosContratos EC ON E.fechaHora = EC.fechaHora AND E.idEquipo = EC.idEquipo
                    WHERE E.estado = 1 
                    AND E.entidad = 'P'
                    AND E.transito = 0";

        $sql .= $eventFilters;
        
        $params = [];
        $params[':fechaDesde'] = $Args['FechaDesde'];
        $params[':fechaHasta'] = $Args['FechaHasta'];
        $params[':sql'] = $sql;
        $params[':IdAcceso'] = $Args['IdAcceso'];
        $params[':respuesta'] = 0;
        
        $sp = "EXEC spAsistencia2  @fechaDesde = :fechaDesde, 
                    @fechaHasta = :fechaHasta, 
                    @sql = :sql, 
                    @Accesos = :IdAcceso, 
                    @respuesta = :respuesta";
        
        $resp = DB::statement($sp,$params);
                
        $sql = "SELECT SUM(ph.TotalMinutos) AS HorasPF, ph.NroContrato
                    FROM TMPPresHoras ph
                    INNER JOIN TMPPresPersonas pp ON pp.Documento = ph.Documento AND pp.IdTipoDocumento = ph.IdTipoDocumento
                    WHERE ph.TotalMinutos > 0
                    GROUP BY ph.NroContrato
                    ORDER BY HorasPF DESC";
        
        return DB::select($sql);
    }

    private function horasTrabajadasMaquinas($Args, $eventFilters) {
        $sql = "SELECT DISTINCT E.nroSerie, 1, EMP.nombre AS empresa, M.idCategoria
                FROM Eventos E INNER JOIN Maquinas M ON E.nroSerie = M.nroSerie
                LEFT JOIN Empresas EMP ON M.docEmpresa = EMP.documento AND M.tipoDocEmp = EMP.idTipoDocumento
                INNER JOIN EventosContratos EC ON E.fechaHora = EC.fechaHora AND E.idEquipo = EC.idEquipo
                        WHERE E.estado = 1
                        AND E.entidad = 'M'
                        AND E.transito = 0";

        $sql .= $eventFilters;
        
        $params = [];
        $params[':fechaDesde'] = $Args['FechaDesde'];
        $params[':fechaHasta'] = $Args['FechaHasta'];
        $params[':sql'] = $sql;
        $params[':IdAcceso'] = $Args['IdAcceso'];
        $params[':respuesta'] = 0;
        
        $sp = "EXEC spAsistenciaMaq2  @fechaDesde = :fechaDesde, 
                    @fechaHasta = :fechaHasta, 
                    @sql = :sql, 
                    @Accesos = :IdAcceso, 
                    @respuesta = :respuesta";
        
        $resp = DB::statement($sp,$params);
                
        $sql = "SELECT SUM(ph.TotalMinutos) AS HorasMaq, ph.NroContrato
                    FROM TMPPresHoras ph
                    INNER JOIN TMPPresPersonas pp ON pp.Documento = ph.Documento AND pp.IdTipoDocumento = ph.IdTipoDocumento
                    WHERE ph.TotalMinutos > 0
                    GROUP BY ph.NroContrato
                    ORDER BY HorasMaq DESC";
        
        return DB::select($sql);
    }

    private function horasTrabajadasVehiculos($Args, $eventFilters) {
        $sql = "SELECT DISTINCT E.serie, E.numero, EMP.nombre AS empresa, V.idCategoria
                FROM Eventos E INNER JOIN Vehiculos V ON E.serie = V.serie AND E.numero = V.numero
                LEFT JOIN Empresas EMP ON V.docEmpresa = EMP.documento AND V.tipoDocEmp = EMP.idTipoDocumento
                INNER JOIN EventosContratos EC ON E.fechaHora = EC.fechaHora AND E.idEquipo = EC.idEquipo
                        WHERE E.estado = 1
                        AND E.entidad = 'V'
                        AND E.transito = 0";

        $sql .= $eventFilters;
        
        $params = [];
        $params[':fechaDesde'] = $Args['FechaDesde'];
        $params[':fechaHasta'] = $Args['FechaHasta'];
        $params[':sql'] = $sql;
        $params[':IdAcceso'] = $Args['IdAcceso'];
        $params[':respuesta'] = 0;

        $sp = "EXEC spAsistenciaMaq2  @fechaDesde = :fechaDesde, 
                    @fechaHasta = :fechaHasta, 
                    @sql = :sql, 
                    @Accesos = :IdAcceso, 
                    @respuesta = :respuesta";
        
        
        $resp = DB::statement($sp,$params);
                
        $sql = "SELECT SUM(ph.TotalMinutos) AS HorasVehic, ph.NroContrato
                    FROM TMPPresHoras ph
                    INNER JOIN TMPPresPersonas pp ON pp.Documento = ph.Documento AND pp.IdTipoDocumento = ph.IdTipoDocumento
                    WHERE ph.TotalMinutos > 0
                    GROUP BY ph.NroContrato
                    ORDER BY HorasVehic DESC";
        
        return DB::select($sql);
    }

    public function wslistado()
    {
        return $this->listado($this->req->all());
    }

    private function listado($Args = []) {
        if (!empty($Args)) {
            $ArgsArray = array();

            $bindings = [];

            foreach ($Args as $k => $v) {
                $ArgsArray[] = array($k, $v);
            }

            for ($i = 0; $i < count($ArgsArray); $i++) {
                switch ($ArgsArray[$i][0]) {
                    case 'IdEmpresa':

                        $ide = FsUtils::explodeId($ArgsArray[$i][1]);
                        $sql = "SELECT  ec.Documento + '-' + LTRIM(RTRIM(STR(ec.IdTipoDocumento))) AS IdEmpContratista,
                                        ec.Documento + '-' + LTRIM(RTRIM(STR(ec.IdTipoDocumento))) + '-' + ec.NroContrato AS IdContrato,
                                        e.Nombre AS Contratista,
                                        ec.NroContrato As NroContrato
                                FROM EmpresasContratos ec
                                INNER JOIN Empresas e ON e.Documento = ec.Documento AND e.IdTipoDocumento = ec.IdTipoDocumento
                                WHERE ec.DocEmpCont = :ide0 AND ec.IdTipoDocCont = :ide1";
                        $bindings[':ide0'] = $ide[0];
                        $bindings[':ide1'] = $ide[1];
                        break;

                    case 'IdPersonaFisica':
                    case 'IdPersonaFisicaTransac':

                        $idpf = FsUtils::explodeId($ArgsArray[$i][1]);
                        $idemp = FsUtils::explodeId($ArgsArray[$i + 1][1]);
                        $mod = str_replace('IdPersonaFisica', "", $ArgsArray[$i][0]);
                        
                        $bindings1 = [];

                        $bindings1[':idpf0'] = $idpf[0];
                        $bindings1[':idpf1'] = $idpf[1];
                        $bindings1[':idemp0'] = $idemp[0];
                        $bindings1[':idemp1'] = $idemp[1];
                        $bindings1[':fecha'] = $ArgsArray[$i + 2][1];

                        $emp = DB::selectOne("SELECT COUNT(*) AS Cantidad
                                            FROM PersonasFisicas " . $mod . " Empresas pfe
                                            WHERE pfe.Documento = :idpf0
                                            AND pfe.IdTipoDocumento = :idpf1 
                                            AND pfe.DocEmpresa = :idemp0
                                            AND pfe.TipoDocEmpresa = :idemp1 
                                            AND pfe.FechaAlta = CONVERT(datetime, :fecha, 103)
                                            AND (pfe.FechaBaja IS NULL OR pfe.FechaBaja >= GETDATE())", $bindings1);
                        
                        if ($emp > 0) {
                            $sql = "SELECT DISTINCT
                                            pfc.DocEmpCont + '-' + LTRIM(RTRIM(STR(pfc.IdTipoDocCont))) AS IdEmpContratista,
                                            pfc.DocEmpCont + '-' + LTRIM(RTRIM(STR(pfc.IdTipoDocCont))) + '-' + pfc.NroContrato AS IdContrato,
                                            e.Nombre AS Contratista,
                                            pfc.NroContrato AS NroContrato,
                                            pfc.FechaAlta AS FechaAltaContrato
                                    FROM PersonasFisicas" . $mod . "Contratos pfc
                                    INNER JOIN PersonasFisicasEmpresas pfe  ON pfe.DocEmpresa = pfc.DocEmpresa AND pfe.TipoDocEmpresa = pfc.TipoDocEmpresa AND pfe.FechaAlta = CONVERT(datetime, '" . $ArgsArray[$i + 2][1] . "', 103)
                                    INNER JOIN Empresas e ON e.Documento = pfe.DocEmpresa AND e.IdTipoDocumento = pfe.TipoDocEmpresa
                                    WHERE pfc.Documento = :idpf00 AND pfc.IdTipoDocumento = :idpf01
                                    AND pfc.DocEmpresa = :idemp00 AND pfc.TipoDocEmpresa = :idemp01";
                            
                            $bindings[':idpf00'] = $idpf[0];
                            $bindings[':idpf01'] = $idpf[1];
                            $bindings[':idemp00'] = $idemp[0];
                            $bindings[':idemp01'] = $idemp[1];
                        }
						
                        break;

                    case 'IdVehiculo':
                    case 'IdVehiculoTransac':

                        $idv = FsUtils::explodeId($ArgsArray[$i][1]);
                        $mod = str_replace('IdVehiculo', "", $ArgsArray[$i][0]);

                        $sql = "SELECT  vc.DocEmpCont + '-' + LTRIM(RTRIM(STR(vc.IdTipoDocCont))) AS IdEmpContratista,
                                        vc.DocEmpCont + '-' + LTRIM(RTRIM(STR(vc.IdTipoDocCont))) + '-' + vc.NroContrato AS IdContrato,
                                        e.Nombre AS Contratista,
                                        vc.NroContrato AS NroContrato,
                                        vc.FechaAlta AS FechaAltaContrato
                                FROM Vehiculos" . $mod . "Contratos vc
                                INNER JOIN Empresas e ON e.Documento = vc.DocEmpCont AND e.IdTipoDocumento = vc.IdTipoDocCont
                                WHERE vc.Serie = :idv0 AND vc.Numero = :idv1";

                        $bindings[':idv0'] = $idv[0];
                        $bindings[':idv1'] = $idv[1];

                        break;

                    case 'IdMaquina':
                    case 'IdMaquinaTransac':
                        $idm = [$ArgsArray[$i][1]];
                        $mod = str_replace('IdMaquina', "", $ArgsArray[$i][0]);

                        $sql = "SELECT  mc.DocEmpCont + '-' + LTRIM(RTRIM(STR(mc.IdTipoDocCont))) AS IdEmpContratista,
                                        mc.DocEmpCont + '-' + LTRIM(RTRIM(STR(mc.IdTipoDocCont))) + '-' + mc.NroContrato AS IdContrato,
                                        e.Nombre AS Contratista,
                                        mc.NroContrato AS NroContrato,
                                        mc.FechaAlta AS FechaAltaContrato
                                FROM Maquinas" . $mod . "Contratos mc
                                INNER JOIN Empresas e ON e.Documento = mc.DocEmpCont AND e.IdTipoDocumento = mc.IdTipoDocCont
                                WHERE mc.NroSerie = :idm0";

                        $bindings[':idm0'] = $idm[0];

                        break;

                    case "TwoFields":

                        $sql = "SELECT DISTINCT 
                                        ec.NroContrato + '-' + ec.Documento + '-' + LTRIM(RTRIM(STR(ec.IdTipoDocumento))) AS Identificador,
                                        ec.NroContrato + ' (' + e.Nombre + ')' As Descripcion
                                FROM EmpresasContratos ec
                                INNER JOIN Empresas e ON e.Documento = ec.DocEmpCont AND e.IdTipoDocumento = ec.IdTipoDocCont
                                ORDER BY Descripcion";

                        break;

                    case "PlainList":

                        $sql = "SELECT DISTINCT NroContrato, NroContrato AS Identificador
                                FROM EmpresasContratos
                                ORDER BY NroContrato";

                        break;
                }
            }
        } else {
            $sql = "SELECT DISTINCT NroContrato FROM EmpresasContratos";
        }
        //fs_log("fs_var.log", "DETALLE ");
        //fs_log("fs_var.log", self::listado($Usuario, $IdEmpresa, $sql));
        return DB::select($sql, $bindings);
    }

    private function wsBusquedaIngresosDiarios_sql($Args) {
        $eventFilters = "";
        $bindings = [];

        if((!empty($Args['FechaDesde']) && !empty($Args['FechaHasta'])) && $Args['FechaDesde'] == $Args['FechaHasta']) {
            $eventFilters .= " AND CONVERT(date, E.fechaHora, 103) = CONVERT(date, :FechaDesde, 103)";
            $binding[':FechaDesde'] = $Args['FechaDesde']. ' 00:00:00';
        } else {

            if(!empty($Args['FechaDesde'])){
                $eventFilters .= " AND CONVERT(date, E.fechaHora, 103) >= CONVERT(date, :FechaDesde, 103)";
                $binding[':FechaDesde'] = $Args['FechaDesde']. ' 00:00:00';
            }

            if(!empty($Args['FechaHasta'])){
                $eventFilters .= " AND CONVERT(date, E.fechaHora, 103) >= CONVERT(date, :FechaHasta, 103)";
                $binding[':FechaHasta'] = $Args['FechaHasta']. ' 00:00:00';
            }
        }    

        if (!empty($Args['Accesos'])) {
            $first = true;
            $i = 0;
            $eventFilters .= " AND E.IdAcceso IN (";
            foreach ($Args['Accesos'] as $acceso) {
                $i++;
                if (!$first) {
                    $eventFilters .= ", ";
                } else {
                    $first = false;
                }
                $eventFilters .= ':acceso'.$i;
                $binding[':acceso'.$i] = $acceso;
            }
            $eventFilters .= ")";
        }
        if (!empty($Args['Contratos'])) {
            $first = true;
            $i = 0;
            $eventFilters .= " AND EC.nroContrato IN (";
            foreach ($Args['Contratos'] as $contrato) {
                $i++;
                if (!$first) {
                    $eventFilters .= ", ";
                } else {
                    $first = false;
                }
                $eventFilters .= ':contrato'.$i;
                $binding[':contrato'.$i] = $contrato;
            }
            $eventFilters .= ")";
        }

        $sql = "SELECT COUNT(V.serie + '-' + RTRIM(LTRIM(STR(V.numero)))) AS Vehiculos, COUNT(PF.Documento) AS Personas, COUNT(M.NroSerie) AS Maquinas, EC.NroContrato AS NroContrato
                FROM Eventos E LEFT JOIN PersonasFisicas PF ON E.documento = PF.documento AND E.idTipoDocumento = PF.idTipoDocumento
                LEFT JOIN Accesos A ON E.idAcceso = A.idAcceso
                LEFT JOIN Empresas EMP ON E.docEmpresa = EMP.documento AND E.tipoDocEmpresa = EMP.idTipoDocumento
                LEFT JOIN TiposDocumento TD ON PF.idTipoDocumento = TD.idTipoDocumento
                LEFT JOIN Personas P ON PF.idTipoDocumento = PF.idTipoDocumento AND P.documento = PF.documento
                LEFT JOIN Maquinas M ON E.nroSerie = M.nroSerie
                LEFT JOIN Vehiculos V ON E.serie = V.serie AND E.numero = V.numero
                INNER JOIN EventosContratos EC ON E.idEquipo = EC.idEquipo AND E.fechaHora = EC.fechaHora AND E.tipoOperacion = 0
                WHERE E.estado = 1";

        $sql .= $eventFilters . " GROUP BY EC.NroContrato";

        return [$sql, $bindings];
    }

    private function wsBusquedaVencimientosDocs($Args) {
        
        throw new HttpException(409, "Componente no definido");
        /*$Args->TipoListado = "Vencimientos";
        unset($Args->Funcion);
        return mzdocumento::wsbusqueda($Usuario, $IdEmpresa, $Args, $MaxFilas, $NroPagina);*/
    }

    private function wsBusquedaVencimientos_sql($Args) {
        
        $bindings = [];
        $sql = "SELECT
                e.Nombre AS EmpContratante,
                e2.Nombre AS EmpContratada,
                ec.NroContrato,
                CONVERT(varchar(10), ec.FechaDesde, 103) as FechaDesde,
                CONVERT(varchar(10), ec.FechaHasta, 103) as FechaHasta,
                CASE
                     WHEN ec.FechaDesde >= GETDATE() THEN 'Inactivo'
                     ELSE CASE WHEN GETDATE() <= ec.FechaHasta THEN 'Activo'
                     ELSE 'Vencido' END
                END AS DescEstado,
                'Contrato' AS Entidad,
                ec.NroContrato + '(' + e.Nombre + ' contrata a ' + e2.Nombre + ')' AS Detalle
                FROM EmpresasContratos ec
                INNER JOIN Empresas e ON ec.Documento = e.Documento AND ec.IdTipoDocumento = e.IdTipoDocumento
                INNER JOIN Empresas e2 ON ec.DocEmpCont = e2.Documento AND ec.IdTipoDocCont = e2.IdTipoDocumento
                WHERE 1 = 1";
        
        if(!empty($Args['MostrarSoloVencidos']) && !empty($Args['MostrarVencenEn'])) {
            $sql .= " AND ec.FechaHasta <= DATEADD(day, :MostrarVencenEnDias - 1, GETDATE())";
            $bindings[':MostrarVencenEnDias'] = $Args['MostrarVencenEnDias'];
        } else {
            if(!empty($Args['MostrarSoloVencidos'])) {
                $sql .= " AND ec.FechaHasta <= GETDATE()";
            }
            if(!empty($Args['MostrarVencenEn'])) {
                $sql .= " AND ec.FechaHasta >= GETDATE() AND ec.FechaHasta <= DATEADD(day, :MostrarVencenEnDias - 1, GETDATE())";
                $bindings[':MostrarVencenEnDias'] = $Args['MostrarVencenEnDias'];
            }
        }
        
        return [$sql, $bindings];
    }

    public function arbol() {
        $mostrarInactivos = $this->req->input('MostrarInactivos') === 'true';

        $stmt = "
            WITH ArbolDeContratos (DocEmpCont, IdTipoDocCont, IdEmpresa, Nombre, Documento, IdTipoDocumento, IdEmpresaCont, NroContrato, Estado, Level)
            AS
            (
            -- Anchor member definition
                SELECT 
                    CAST('1' AS varchar(20)) AS DocEmpCont, 
                    CAST(2 AS numeric) AS IdTipoDocCont, 
                    CAST('1-2' AS varchar(MAX)) AS IdEmpresa,
                    CAST('Contratos' AS varchar(100)) AS Nombre, 
                    CAST(null AS varchar(20)) AS Documento, 
                    CAST(null AS numeric) AS IdTipoDocumento, 
                    CAST(null AS varchar(MAX)) AS IdEmpresaCont,
                    CAST(null AS varchar(20)) AS NroContrato, 
                    CAST(1 AS tinyint) AS Estado,
                    0 AS Level
                UNION ALL
            -- Recursive member definition
                SELECT 
                    ec.DocEmpCont, 
                    ec.IdTipoDocCont, 
                    CAST(ec.DocEmpCont + '-' + LTRIM(RTRIM(STR(ec.IdTipoDocCont))) AS varchar(MAX)) AS IdEmpresa,
                    CAST((LTRIM(STR(Level + 1)) + '. ' + e.Nombre) AS varchar(100)) AS Nombre, 
                    ec.Documento, 
                    ec.IdTipoDocumento, 
                    CAST(ec.Documento + '-' + LTRIM(RTRIM(STR(ec.IdTipoDocumento))) AS varchar(MAX)) AS IdEmpresaCont,
                    ec.NroContrato,
                    e.Estado,
                    Level + 1
                FROM EmpresasContratos AS ec
                INNER JOIN ArbolDeContratos AS adc 
                    ON ec.Documento = adc.DocEmpCont 
                    AND ec.IdTipoDocumento = adc.IdTipoDocCont 
                    AND (adc.NroContrato IS NULL 
                        OR ec.NroContrato = adc.NroContrato 
                        OR NOT EXISTS(SELECT Documento, IdTipoDocumento 
                                    FROM EmpresasContratos
                                    WHERE Documento = adc.DocEmpCont
                                    AND IdTipoDocumento = adc.IdTipoDocCont
                                    AND DocEmpCont = ec.DocEmpCont
                                    AND IdTipoDocCont = ec.IdTipoDocCont
                                    AND NroContrato = adc.NroContrato
                                    UNION ALL
                                    SELECT Documento, IdTipoDocumento 
                                    FROM EmpresasContratos
                                    WHERE DocEmpCont = ec.Documento
                                    AND IdTipoDocCont = ec.IdTipoDocumento
                                    AND NroContrato = ec.NroContrato))
                INNER JOIN Empresas AS e ON e.Documento = ec.DocEmpCont AND e.IdTipoDocumento = ec.IdTipoDocCont " . ($mostrarInactivos ? "" : " AND e.Estado = 1") . "
                INNER JOIN Personas AS p ON e.Documento = p.Documento AND e.IdTipoDocumento = p.IdTipoDocumento AND p.Baja = 0
				WHERE Level <= 8
            )

            -- Statement that executes the CTE
            SELECT DISTINCT
                DocEmpCont, 
                IdTipoDocCont, 
                IdEmpresa, 
                Nombre AS NombreEmpresa,
                Nombre + CASE WHEN NroContrato IS NOT NULL THEN ' (' + NroContrato + ')' ELSE '' END AS Nombre,
                Documento, 
                IdTipoDocumento, 
                IdEmpresaCont, 
                NroContrato, 
                Estado,
                Level,
                (SELECT DISTINCT COUNT(Documento + '-' + LTRIM(RTRIM(STR(IdTipoDocumento)))) 
                FROM PersonasFisicasContratos pfc 
                WHERE pfc.DocEmpresa = ac.DocEmpCont 
                AND pfc.TipoDocEmpresa = ac.IdTipoDocCont
                AND pfc.NroContrato = ac.NroContrato) AS ContPF,
                (SELECT DISTINCT COUNT(NroSerie)
                FROM MaquinasContratos mc
                WHERE mc.DocEmpresa = ac.DocEmpCont 
                AND mc.TipoDocEmpresa = ac.IdTipoDocCont
                AND mc.NroContrato = ac.NroContrato) AS ContM,
                (SELECT DISTINCT COUNT(Serie + LTRIM(RTRIM(STR(Numero))))
                FROM VehiculosContratos vc
                WHERE vc.DocEmpresa = ac.DocEmpCont 
                AND vc.TipoDocEmpresa = ac.IdTipoDocCont
                AND vc.NroContrato = ac.NroContrato) AS ContV
            FROM ArbolDeContratos ac
            ORDER BY Level, Nombre
            OPTION (maxrecursion 500)";

        $data = DB::select($stmt);
        $arbol = $data[0];

        self::arbol_subcontratos($arbol, $data);

        if ($this->req->input('output', 'json') === 'xls') {
            $filename = 'Árbol de contratos (' . date('Ymd-his') . ')';
            $output = [];
            self::arbol_indentacion($arbol, $output);
            return (new FastExcel(collect($output)))->download($filename . '.xlsx');
        }

        return $this->response([$arbol]);
    }

    private static function arbol_indentacion($data, &$output, $isLast = false)
    {
        $level = (int)$data->Level;
        $indent = "";
        for ($i = 0; $i < $level; $i++) { // └ ├ ┼ ─
            if ($isLast && $i + 1 === $level) {
                if ($i === 0) {
                    $indent .= " └    ";
                } else {
                    $indent .= " └    "; // ┴
                }
            } else if ($i === 0) {
                $indent .= " │    "; // ├
            } else {
                $indent .= " │    "; // ┼ ├
            }
        }

        $output[] = [
            'Empresa' => $indent . ' ' . $data->NombreEmpresa, // . "'",
            'Contrato' => $data->NroContrato ?? '',
            'PersonasFisicas' => $data->ContPF,
            'Maquinas' => $data->ContM,
            'Vehiculos' => $data->ContV,
        ];

        if (property_exists($data, 'children')) {
            foreach ($data->children as $index => $child) {
                self::arbol_indentacion($child, $output, $index + 1 === count($data->children));
            }
        }
    }

    private static function arbol_subcontratos(&$item, &$tabla)
    {
        $item->title = $item->Nombre;

        for ($i = 0; $i < count($tabla); $i++) {
            if ($tabla[$i]->Level == $item->Level + 1 && $tabla[$i]->IdEmpresaCont == $item->IdEmpresa) {
                if (!isset($item->children)) {
                    $item->isFolder = true;
                    $item->children = array();
                }

                self::arbol_subcontratos($tabla[$i], $tabla);
                $item->children[] = $tabla[$i];
            }
        }
    }

}
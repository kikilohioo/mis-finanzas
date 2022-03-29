<?php

namespace App\Http\Controllers;

use App\FsUtils;
use App\Models\Acceso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use App\Models\Usuario;

class EventoController extends Controller
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

    public function create(?object $args = null)
    {
        if (!isset($args)) {
            $args = (object)$this->req->all();
        }

        $resp = DB::statement('EXEC spGrabarEventoOff ?, ?, ?, ?, ?', [
            $args->NroReloj,
            $args->Matricula, 
            $args->Accion, 
            $args->FechaHora,
            $args->Observaciones,
        ]);

        return (bool)$resp;
    }

    public function index()
    {
        $Args = $this->req->all();

        switch ($Args['Funcion']) {
            case "ConsActivos":
                return $this->wsbusqueda();
        }
    }
    
    public function wsbusqueda() {

        $Args = $this->req->all();

        switch ($Args['Funcion']) {
            case "Busqueda":
                return $this->busqueda($Args);
            case "ConsActivos":
                return $this->consActivos($Args);
            case "ConsIngresos":
                return $this->consIngresos($Args);
            case "ConsIngresosPorEmpresa":
                return $this->consIngresosPorEmpresa($Args);
            case "ConsSitio":
                return $this->consSitio($Args);
            case "Duplicados":
                return $this->consDuplicados($Args);
            case "EveConsCantina":
                $resumido = $this->eveConsCantinaResumido($Args);
                $detallado = $this->eveConsCantinaDetallado($Args);
                return array(
                    "resumido" => $resumido,
                    "detallado" => $detallado
                );
            case "ListAsistencias":
                $this->sqlListAsistencias($Args);
                switch ($Args['TipoListadoAsistencias']) {
                    case "Defecto":
                        $sql = "SELECT
                                    RTRIM(LTRIM(pf.Nombre)) + ' (' + dbo.Mask(h.Documento, td.Mascara, 1, 1) + ') ' AS Identificacion,
                                    pf.Nombre AS NombreCompleto,
                                    pf.Empresa,
                                    Fecha,
                                    Dia,
                                    CONVERT(varchar(5), Entrada, 108) AS HoraEntrada,
                                    CONVERT(varchar(5), Salida, 108) AS HoraSalida,
                                    CONVERT(varchar(5), InicioDescanso, 108) AS InicioDescanso,
                                    CONVERT(varchar(5), FinDescanso, 108) AS FinDescanso,
                                    TotalHoras AS TiempoHoras,
                                    dbo.TIME_DIFF(0, DATEADD(SECOND, DATEDIFF(SECOND, 0, h.TotalHoras), 0)) AS Tiempo
                                FROM TMPPresHoras h
                                INNER JOIN TMPPresPersonas pf ON h.Documento = pf.Documento AND h.IdTipoDocumento = pf.IdTipoDocumento
                                INNER JOIN TiposDocumento td ON pf.IdTipoDocumento = td.IdTipoDocumento
                                WHERE 1 = 1 ";

                        $list = DB::select($sql);

                        $totals = [];
                        if (!empty($list)) {
                            $totals = DB::select("SELECT 'TOTALES' AS Identificacion, 'Total' AS FinDescanso, "
                                . "CONVERT(varchar, SUM(DATEDIFF(second, 0, TiempoHoras)) / 3600) + ':' + "
                                . "CONVERT(varchar, SUM(DATEDIFF(second, 0, TiempoHoras)) % 60) AS Tiempo FROM (" . $sql .  ") tablaTotal");
                        }
                        
                        $output = $this->req->input('output', 'json');
                        if ($output !== 'json' && $output !== null) {
                            $filename = 'FSAcceso-ListAsistencias-' . date('Ymd-his');
                            $headers = [
                                'NombreCompleto' => 'Nombre',
                                'Empresa' => 'Empresa',
                                'Fecha' => 'Fecha',
                                'Dia' => 'Dia',
                                'HoraEntrada' => 'Entrada',
                                'HoraSalida' => 'Salida',
                                'InicioDescanso' => 'Inicio Descanso',
                                'FinDescanso' => 'Fin Descanso',
                                // 'TiempoHoras' => 'Tiempo Horas',
                                'Tiempo' => 'Tiempo',
                            ];
                            return FsUtils::export($output, array_merge($list, $totals), $headers, $filename);
                        }
                        
                        $page = (int)$this->req->input('page', 1);
                        $paginate = FsUtils::paginateArray($list, $this->req);
                        $items = $paginate->items();
                        return $this->responsePaginate(array_merge($items, $totals), $paginate->total(), $page);
                    case "Resumido":
                       $sql = "SELECT 
                                    RTRIM(LTRIM(pf.Nombre)) AS Detalle, 
                                    dbo.Mask(h.Documento, td.Mascara, 1, 1) AS Identificacion, 
                                    pf.Empresa, 
                                    NroContrato, 
                                    DATEADD(second, SUM(DATEDIFF(second, 0, h.TotalHoras)), 0) AS TiempoHoras, 
                                    dbo.TIME_DIFF(0, DATEADD(SECOND, SUM(DATEDIFF(SECOND, 0, h.TotalHoras)), 0)) AS Tiempo 
                                FROM TMPPresHoras h 
                                INNER JOIN TMPPresPersonas pf ON h.Documento = pf.Documento AND h.IdTipoDocumento = pf.IdTipoDocumento 
                                INNER JOIN TiposDocumento td ON pf.IdTipoDocumento = td.IdTipoDocumento 
                                WHERE h.TotalHoras IS NOT NULL 
                                GROUP BY h.Documento, td.Mascara, pf.Nombre, pf.Empresa, h.NroContrato ";
                        
                        $list = DB::select($sql);

                        $totals = [];
                        if (!empty($list)) {
                            $totals = DB::select("SELECT 'TOTAL' AS NroContrato, "
                                . "CONVERT(varchar, SUM(DATEDIFF(second, 0, TiempoHoras)) / 3600) + ':' + "
                                . "CONVERT(varchar, SUM(DATEDIFF(second, 0, TiempoHoras)) % 60) AS Tiempo FROM (" . $sql .  ") tablaTotal");
                        }

                        $output = $this->req->input('output', 'json');
                        if ($output !== 'json' && $output !== null) {
                            $filename = 'FSAcceso-ListAsistencias-Resumido-' . date('Ymd-his');
                            $headers = [
                                'Detalle' => 'Detalle',
                                'Identificacion' => 'Identificación',
                                'Empresa' => 'Empresa',
                                'NroContrato' => 'NroContrato',
                                // 'TiempoHoras' => 'Tiempo Horas',
                                'Tiempo' => 'Tiempo',
                            ];
                            return FsUtils::export($output, array_merge($list, $totals), $headers, $filename);
                        }
                        
                        $page = (int)$this->req->input('page', 1);
                        $paginate = FsUtils::paginateArray($list, $this->req);
                        $items = $paginate->items();
                        return $this->responsePaginate(array_merge($items, $totals), $paginate->total(), $page);
                   case "Totalizado":
                        $sql = "SELECT 
                                pf.Empresa, 
                                h.NroContrato, 
                                DATEADD(second, SUM(DATEDIFF(second, 0, h.TotalHoras)), 0) AS TiempoHoras, 
                                CONVERT(varchar(MAX), SUM(DATEDIFF(second, 0, h.TotalHoras)) / 3600) + ':' + CONVERT(varchar(2), (SUM(DATEDIFF(second, 0, h.TotalHoras)) % 3600) / 60) + ' h' AS Tiempo
                                FROM TMPPresPersonas pf 
                                INNER JOIN TMPPresHoras h ON h.Documento = pf.Documento AND h.IdTipoDocumento = pf.IdTipoDocumento 
                                WHERE pf.Empresa IS NOT NULL 
                                AND h.TotalHoras IS NOT NULL 
                                GROUP BY pf.Empresa, h.NroContrato";
                        
                        $list = DB::select($sql);

                        $totals = [];
                        if (!empty($list)) {
                            $totals = DB::select(" SELECT 'Total' AS NroContrato, "
                                . "CONVERT(varchar, SUM(DATEDIFF(second, 0, TiempoHoras)) / 3600) + ':' + "
                                . "CONVERT(varchar, SUM(DATEDIFF(second, 0, TiempoHoras)) % 60) AS Tiempo FROM (" . $sql .  ") tablaTotal");
                        }

                        $output = $this->req->input('output', 'json');
                        if ($output !== 'json' && $output !== null) {
                            $filename = 'FSAcceso-ListAsistencias-Totalizado-' . date('Ymd-his');
                            $headers = [
                                'Empresa' => 'Empresa',
                                'NroContrato' => 'NroContrato',
                                // 'TiempoHoras' => 'Tiempo Horas',
                                'Tiempo' => 'Tiempo',
                            ];
                            return FsUtils::export($output, array_merge($list, $totals), $headers, $filename);
                        }

                        $page = (int)$this->req->input('page', 1);
                        $paginate = FsUtils::paginateArray($list, $this->req);
                        $items = $paginate->items();
                        return $this->responsePaginate(array_merge($items, $totals), $paginate->total(), $page);
                    case "PorFecha":
                        $sql = "SELECT
                                    h.Fecha,
                                    RTRIM(LTRIM(pf.Nombre)) AS Detalle,
                                    dbo.Mask(h.Documento, td.Mascara, 1, 1) AS Identificacion,
                                    pf.Empresa,
                                    CONVERT(varchar(5), Entrada, 108) AS HoraEntrada,
                                    CONVERT(varchar(5), Salida, 108) AS HoraSalida,
                                    dbo.TIME_DIFF(0, TotalHoras) AS Tiempo
                                FROM TMPPresHoras h
                                INNER JOIN TMPPresPersonas pf ON h.Documento = pf.Documento AND h.IdTipoDocumento = pf.IdTipoDocumento
                                INNER JOIN TiposDocumento td ON pf.IdTipoDocumento = td.IdTipoDocumento
                                WHERE 1 = 1";
                        
                        $list = DB::select($sql);

                        $output = $this->req->input('output', 'json');
                        if ($output !== 'json' && $output !== null) {
                            $filename = 'FSAcceso-ListAsistencias-PorFecha-' . date('Ymd-his');
                            $headers = [
                                'Fecha' => 'Fecha',
                                'Detalle' => 'Detalle',
                                'Identificacion' => 'Identificación',
                                'Empresa' => 'Empresa',
                                'HoraEntrada' => 'Entrada',
                                'HoraSalida' => 'Salida',
                                'Tiempo' => 'Tiempo',
                            ];
                            return FsUtils::export($output, $list, $headers, $filename);
                        }
                        
                        $page = (int)$this->req->input('page', 1);
                        $paginate = FsUtils::paginateArray($list, $this->req);
                        return $this->responsePaginate($paginate->items(), $paginate->total(), $page);
                }
        }
    }

    public function busqueda($Args) {

        $accesos = [];
        foreach ($Args['Accesos'] as $idAcceso) {
            $accesos[] = $idAcceso;
        }

        $Args['DocEmpresa'] = null;
        $Args['TipoDocEmpresa'] = null;
        if (!empty($Args['IdEmpresa'])) {
            $docEmp = explode('-', $Args['IdEmpresa']);
            $Args['DocEmpresa'] = $docEmp[0];
            $Args['TipoDocEmpresa'] = $docEmp[1];
        }

        $Args['TipoOperacion'] = null;
        if ($Args['Ingreso'] === 'true' || $Args['Egreso'] === 'true') {
            $Args['TipoOperacion'] = [];
            if ($Args['Ingreso'] === 'true') {
                $Args['TipoOperacion'][] = 0;
            }
            if ($Args['Egreso'] === 'true') {
                $Args['TipoOperacion'][] = 1;
            }
        }

        if (!empty($Args['TipoMaquinas'])) {
            $Args['TipoMaquinas'] = array_map(function ($el) { return "'".$el."'"; }, $Args['TipoMaquinas']);
        }
        if (!empty($Args['TipoVehiculos'])) {
            $Args['TipoVehiculos'] = array_map(function ($el) { return "'".$el."'"; }, $Args['TipoVehiculos']);
        }

        $spArgs = [];
        $spArgs[':FechaDesde'] = ((\DateTime::createFromFormat('d/m/Y', $Args['FechaDesde'])->format('Y-m-d')) . " " . $Args['HoraDesde'] . ":00");
		$spArgs[':FechaHasta'] = ((\DateTime::createFromFormat('d/m/Y', $Args['FechaHasta'])->format('Y-m-d')) . " " . $Args['HoraHasta'] . ":00");
		$spArgs[':IdAcceso'] = implode(',', $accesos);
		$spArgs[':Nombres'] = ($Args['Nombres'] ?: '');
        $spArgs[':Apellidos'] = ($Args['Apellidos'] ?: '');
        $spArgs[':NoPaginar'] = (empty($Args['NoPaginar']) ? '0' : '1');
        $spArgs[':EntidadPersona'] = ($Args['Personas'] === 'true' ? '1' : '0');
		$spArgs[':EntidadMaquina'] = ($Args['Maquinas'] === 'true' ? '1' : '0');
		$spArgs[':EntidadVehiculo'] = ($Args['Vehiculos'] === 'true' ? '1' : '0');
		$spArgs[':Documento'] = (empty($Args['Documento']) ? NULL : $Args['Documento']);
		$spArgs[':Extranjero'] = (isset($Args['Extranjero']) ? 1 : 0);
		$spArgs[':Matricula'] = (empty($Args['Matricula']) ? NULL : $Args['Matricula']);
		$spArgs[':DocEmpresa'] = (empty($Args['DocEmpresa']) ? NULL : $Args['DocEmpresa']);
		$spArgs[':TipoDocEmpresa'] = ($Args['TipoDocEmpresa'] ?: NULL);
		$spArgs[':NroContrato'] = (empty($Args['NroContrato']) ? NULL : $Args['NroContrato']);
		$spArgs[':IdCategoria'] = (isset($Args['IdCategoria']) ? $Args['IdCategoria'] : NULL);
		$spArgs[':Estado'] = (empty($Args['Estado']) ? NULL : $Args['Estado']);
		$spArgs[':TipoOperacion'] = (empty($Args['TipoOperacion']) ? NULL : implode(',', $Args['TipoOperacion']));
		$spArgs[':NroSerie'] = (empty($Args['NroSerie']) ? NULL : $Args['NroSerie']);
		$spArgs[':TipoMaquinas'] = (empty($Args['TipoMaquinas']) ? NULL : implode(',', $Args['TipoMaquinas']));
		$spArgs[':TipoVehiculos'] = (empty($Args['TipoVehiculos']) ? NULL : implode(',', $Args['TipoVehiculos']));
		$spArgs[':Serie'] = (empty($Args['Serie']) ? NULL : $Args['Serie']);
        $spArgs[':Numero'] = (empty($Args['Numero']) ? NULL : $Args['Numero']);
        
        //return $spArgs;
        $data = DB::select("EXEC spConsultarEvento @idAcceso = :IdAcceso, 
                                                    @Nombres = :Nombres,
                                                    @Apellidos = :Apellidos,
                                                    @NoPaginar = :NoPaginar,
                                                    @EntidadPersona = :EntidadPersona,
                                                    @EntidadMaquina = :EntidadMaquina,
                                                    @EntidadVehiculo = :EntidadVehiculo,
                                                    @Documento = :Documento,
                                                    @Extranjero = :Extranjero,
                                                    @Matricula = :Matricula,
                                                    @DocEmpresa = :DocEmpresa,
                                                    @TipoDocEmpresa = :TipoDocEmpresa,
                                                    @NroContrato = :NroContrato,
                                                    @IdCategoria = :IdCategoria,
                                                    @Estado = :Estado,
                                                    @TipoOperacion = :TipoOperacion,
                                                    @NroSerie = :NroSerie,
                                                    @TipoMaquinas = :TipoMaquinas,
                                                    @TipoVehiculos = :TipoVehiculos,
                                                    @Serie = :Serie,
                                                    @Numero = :Numero,
                                                    @FechaDesde = :FechaDesde,
                                                    @FechaHasta = :FechaHasta", $spArgs);

        //return $this->responsePaginate($data);

        $output = $this->req->input('output', 'json');

        if ($output !== 'json' && $output !== null) {
        
            $filename = 'FSAcceso-Eventos-' . date('Ymd-his');
           
            $headers = [
                'FechaHora' => 'Fecha / Hora',
                'Operacion' => 'Operación',
                'Entidad' => 'Entidad',
                'Identificacion' => 'Identificación',
                'Detalle' => 'Detalle',
                'Matricula' => 'Matrícula',
                'Categoria' => 'Categoría',
                'Empresa' => 'Empresa',
                'NroContrato' => 'Contrato',
                'Acceso' => 'Acceso',
                'Equipo' => 'Equipo',
            ];
    
            return FsUtils::export($output, $data, $headers, $filename);
        }

        $page = (int)$this->req->input('page', 1);
        $paginate = FsUtils::paginateArray($data, $this->req);
        return $this->responsePaginate($paginate->items(), $paginate->total(), $page);

        // $spSQL = preg_replace('/\s+/', ' ', $spSQL);
        //return fs_paged_query($Usuario, $IdEmpresa, $spSQL, $MaxFilas, $NroPagina);
    }

    private function consActivos($Args) {
        //self::exigirArgs($Args, array("IdAcceso"));

        if (!isset($Args['IdAcceso'])) {
            throw new NotFoundHttpException('Debe seleccionar un acceso');
        }
        
        $spArgs = [];
		$spArgs[':IdAcceso'] = $Args['IdAcceso'];

        $spSQL = "EXEC spConsultarEventoActivo @IdAcceso = :IdAcceso";
        $spSQL = preg_replace('/\s+/', ' ', $spSQL);
        
        $items = DB::select($spSQL, $spArgs);
        
        $output = $this->req->input('output', 'json');

        if ($output !== 'json' && $output !== null) {
        
            $filename = 'FSAcceso-Eventos-Consulta-Activos-' . date('Ymd-his');
            
            $headers = [
                'Entidad' => 'Entidad',
                'Identificacion' => 'Identificación',
                'Detalle' => 'Detalle',
                'Empresa' => 'Empresa',
                'Categoria' => 'Categoría',
                'Matricula' => 'Matrícula',
                'NroEvento' => 'Nro. Evento',
                'FechaHora' => 'Fecha / Hora',
                'Tiempo' => 'Tiempo',
                'Acceso' => 'Acceso'
            ];
    
            return FsUtils::export($output, $items, $headers, $filename);
        }
        
        
        $page = (int)$this->req->input('page', 1);        
        $paginate = FsUtils::paginateArray($items, $this->req);
        
        return $this->responsePaginate($paginate->items(), $paginate->total(), $page);
    }

    private function consIngresos($Args) {
        
        if (empty($Args['Accesos'])) {
            //$Args->Accesos = mzacceso::wslistado($Usuario, $IdEmpresa, (object) array("Plain" => true));
            $accesos = Acceso::where('Baja', 0)->get();
            $retornoAccesos = [];
            foreach($accesos as $acceso){
                array_push($retornoAccesos, $acceso['IdAcceso']);
            }
            $Args['Accesos'] = $retornoAccesos;
        }
        
        $sql = "SELECT * FROM (
                    SELECT 'func=AdmPersonas|Documento=' + pf.Documento + '|IdTipoDocumento=' + LTRIM(RTRIM(STR(pf.IdTipoDocumento))) AS ObjUrl,
                            'Persona' AS Entidad,
                            dbo.Mask(pf.Documento, td.Mascara, 1, 1) AS Identificacion,
                            pf.PrimerNombre + ' ' + pf.SegundoNombre + ' ' + pf.PrimerApellido + ' ' + pf.SegundoApellido AS Detalle,
                            EMP.Nombre AS Empresa,
                            C.descripcion AS Categoria,
                            pf.Matricula,
                            E1.NroEvento,
                            CONVERT(VARCHAR(10), E1.FechaHora, 103) + ' ' + CONVERT(VARCHAR(8), E1.FechaHora, 108) AS FechaHora,
                            A.descripcion AS Acceso
                    FROM Eventos E1
                    LEFT JOIN PersonasFisicas PF ON E1.documento = PF.documento AND E1.idTipoDocumento = PF.idTipoDocumento
                    LEFT JOIN Accesos A ON E1.idAcceso = A.idAcceso
                    LEFT JOIN Empresas EMP ON E1.docEmpresa = EMP.documento AND E1.tipoDocEmpresa = EMP.idTipoDocumento
                    LEFT JOIN TiposDocumento TD ON PF.idTipoDocumento = TD.idTipoDocumento
                    LEFT JOIN Personas P ON P.idTipoDocumento = PF.idTipoDocumento AND P.documento = PF.documento
                    LEFT JOIN Personas PEMP ON P.idTipoDocumento = EMP.idTipoDocumento AND P.documento = EMP.documento
                    LEFT JOIN Categorias C ON P.idCategoria = C.idCategoria
                    WHERE E1.estado = 1
                    AND E1.tipoOperacion = 0
                    AND E1.entidad = 'P'
                    AND E1.FechaHora IN (
                        SELECT MIN(FechaHora) 
                        FROM Eventos e2 
                        WHERE e2.Documento = e1.Documento 
                        AND e2.IdTipoDocumento = e1.IdTipoDocumento 
                        AND CONVERT(date, e1.FechaHora, 103) = CONVERT(date, e2.FechaHora, 103)
                        AND e2.TipoOperacion = 0
                        ";
        
        if (!empty($Args['Accesos'])) {
            $sql .= " AND e2.IdAcceso IN (";
            $noComma = true;
            $i = 0;
            foreach ($Args['Accesos'] as $acceso) {

                $accesos = explode(',', $acceso);
                foreach($accesos as $accesoFull){
                    $i++;
                    if (!$noComma) $sql .= ", ";
                    $sql .= ':acceso'.$i;
                    $binding[':acceso'.$i] = $accesoFull;
                    $noComma = false;
                }
            }
            $sql .= ")";
        }
        
        $sql .= ")";

        if (!empty($Args['FechaDesde'])) {
            $sql .= " AND CONVERT(date, E1.fechaHora, 103) = CONVERT(date, :FechaDesde, 103)";
            $binding[':FechaDesde'] = $Args['FechaDesde']. ' 00:00:00';
        }

        if (!empty($Args['NoBaja'])) {
            $sql .= " AND P.baja = 0";
        }

        if (!empty($Args['Accesos'])) {
            $sql .= " AND E1.IdAcceso IN (";
            $noComma = true;
            $i = 0;
            foreach ($Args['Accesos'] as $acceso) {
                $accesos = explode(',', $acceso);
                foreach($accesos as $accesoFull){
                    $i++;
                    if (!$noComma) $sql .= ", ";
                    $sql .= ':acceso11'.$i;
                    $binding[':acceso11'.$i] = $accesoFull;
                    $noComma = false;
                }
            }
            $sql .= ")";
        }
        
        if (!empty($Args['IdEmpresa']) && $Args['IdEmpresa'] != "") {
            $id = FsUtils::explodeId($Args['IdEmpresa']);
            $binding[':id0'] = $id[0];
            $binding[':id1'] = $id[1];
            $sql .= "AND E1.tipoDocEmpresa = :id1
                     AND E1.docEmpresa = :id0";
        }

        $sql .= " UNION ALL ";

        $sql .= "SELECT 'func=AdmMaquinas|NroSerie=' + m.NroSerie AS ObjUrl,
                            'Máquina' AS Entidad,
                            m.NroSerie AS Identificacion,
                            mm.Descripcion + ' ' + m.Modelo + ' (' + tm.Descripcion + ')' AS Detalle,
                            emp.Nombre AS Empresa,
                            cm.Descripcion AS Categoria,
                            m.Matricula,
                            E1.NroEvento,
                            CONVERT(VARCHAR(10), E1.FechaHora, 103) + ' ' + CONVERT(VARCHAR(8), E1.FechaHora, 108) AS FechaHora,
                            A.Descripcion AS Acceso
                    FROM Eventos E1
                    LEFT JOIN Accesos A ON E1.idAcceso = A.idAcceso
                    LEFT JOIN Empresas EMP ON E1.docEmpresa = EMP.documento AND E1.tipoDocEmpresa = EMP.idTipoDocumento
                    LEFT JOIN Maquinas M ON E1.nroSerie = M.nroSerie AND E1.matricula = M.matricula
                    LEFT JOIN Categorias CM ON M.idCategoria = CM.idCategoria
                    LEFT JOIN TiposMaquinas TM ON M.idTipoMaq = TM.idTipoMaquina
                    LEFT JOIN MarcasMaquinas MM ON M.idMarcaMaq = MM.idMarcaMaq
                    WHERE E1.estado = 1
                    AND E1.tipoOperacion = 0
                    AND E1.entidad = 'M'
                    AND E1.FechaHora IN (
                        SELECT MIN(FechaHora) 
                        FROM Eventos e2 
                        WHERE e2.Documento = e1.Documento 
                        AND e2.IdTipoDocumento = e1.IdTipoDocumento 
                        AND CONVERT(date, e1.FechaHora, 103) = CONVERT(date, e2.FechaHora, 103)
                        AND e2.TipoOperacion = 0
                        ";
        
        if (!empty($Args['Accesos'])) {
            $sql .= " AND e2.IdAcceso IN (";
            $noComma = true;
            $i = 0;
            foreach ($Args['Accesos'] as $acceso) {
                $accesos = explode(',', $acceso);
                foreach($accesos as $accesoFull){
                    $i++;
                    if (!$noComma) $sql .= ", ";
                    $sql .= ':acceso22'.$i;
                    $binding[':acceso22'.$i] = $accesoFull;
                    $noComma = false;
                }
            }
            $sql .= ")";
        }

        $sql .= ")";

        if (!empty($Args['FechaDesde'])) {
            $sql .= " AND CONVERT(date, E1.fechaHora, 103) = CONVERT(date, :FechaDesde1, 103)";
            $binding[':FechaDesde1'] = $Args['FechaDesde']. ' 00:00:00';
        }

        if (!empty($Args['NoBaja'])) {
            $sql .= " AND M.baja = 0";
        }
        
        if (!empty($Args['Accesos'])) {
            $sql .= " AND E1.IdAcceso IN (";
            $noComma = true;
            $i = 0;
            foreach ($Args['Accesos'] as $acceso) {
                $accesos = explode(',', $acceso);
                foreach($accesos as $accesoFull){
                    $i++;
                    if (!$noComma) $sql .= ", ";
                    $sql .= ':acceso33'.$i;
                    $binding[':acceso33'.$i] = $accesoFull;
                    $noComma = false;
                }
            }
            $sql .= ")";
        }

        if (!empty($Args['IdEmpresa']) && $Args['IdEmpresa'] != "") {
            $id = FsUtils::explodeId($Args['IdEmpresa']);
            $binding[':id10'] = $id[0];
            $binding[':id11'] = $id[1];
            $sql .= "AND E1.tipoDocEmpresa = :id10
                     AND E1.docEmpresa = :id11";
        }

        $sql .= " UNION ALL ";

        $sql .= "SELECT 'func=AdmVehiculos|Serie=' + v.Serie + '|Numero=' + LTRIM(RTRIM(STR(v.Numero))) AS ObjUrl,                         
                            'Vehículo' AS Entidad,
                            v.Serie + ' ' + RTRIM(LTRIM(STR(v.Numero))) AS Identificacion,
                            mv.Descripcion + ' ' + v.Modelo + ' (' + tv.Descripcion + ')' AS Detalle,
                            emp.Nombre AS Empresa,
                            cv.Descripcion AS Categoria,
                            v.Matricula,
                            E1.NroEvento,
                            CONVERT(VARCHAR(10), E1.FechaHora, 103) + ' ' + CONVERT(VARCHAR(8), E1.FechaHora, 108) AS FechaHora,
                            A.Descripcion AS Acceso
                    FROM Eventos E1
                    LEFT JOIN Accesos A ON E1.idAcceso = A.idAcceso
                    LEFT JOIN Empresas EMP ON E1.docEmpresa = EMP.documento AND E1.tipoDocEmpresa = EMP.idTipoDocumento
                    LEFT JOIN Vehiculos V ON E1.Serie = V.Serie AND E1.Numero = V.Numero AND E1.matricula = V.matricula
                    LEFT JOIN Categorias CV ON V.idCategoria = CV.idCategoria
                    LEFT JOIN TiposVehiculos TV ON V.idTipoVehiculo = TV.idTipoVehiculo
                    LEFT JOIN MarcasVehiculos MV ON V.idMarcaVehic = MV.idMarcaVehic
                    WHERE E1.estado = 1
                    AND E1.tipoOperacion = 0
                    AND E1.entidad = 'V'
                    AND E1.FechaHora IN (
                        SELECT MIN(FechaHora) 
                        FROM Eventos e2 
                        WHERE e2.Documento = e1.Documento 
                        AND e2.IdTipoDocumento = e1.IdTipoDocumento 
                        AND CONVERT(date, e1.FechaHora, 103) = CONVERT(date, e2.FechaHora, 103)
                        AND e2.TipoOperacion = 0
                        ";
        

        if (!empty($Args['Accesos'])) {
            $sql .= " AND e2.IdAcceso IN (";
            $noComma = true;
            $i = 0;
            foreach ($Args['Accesos'] as $acceso) {
                $accesos = explode(',', $acceso);
                foreach($accesos as $accesoFull){
                    $i++;
                    if (!$noComma) $sql .= ", ";
                    $sql .= ':acceso44'.$i;
                    $binding[':acceso44'.$i] = $accesoFull;
                    $noComma = false;
                }

            }
            $sql .= ")";
        }

        $sql .= ")";

        if (!empty($Args['FechaDesde'])) {
            $sql .= " AND CONVERT(date, E1.fechaHora, 103) = CONVERT(date, :FechaDesde2, 103)";
            $binding[':FechaDesde2'] = $Args['FechaDesde']. ' 00:00:00';
        }

        if (!empty($Args['NoBaja'])) {
            $sql .= " AND M.baja = 0";
        }
        
        if (!empty($Args['Accesos'])) {
            $sql .= " AND E1.IdAcceso IN (";
            $noComma = true;
            $i = 0;
            foreach ($Args['Accesos'] as $acceso) {
                $accesos = explode(',', $acceso);
                foreach($accesos as $accesoFull){
                    $i++;
                    if (!$noComma) $sql .= ", ";
                    $sql .= ':acceso55'.$i;
                    $binding[':acceso55'.$i] = $accesoFull;
                    $noComma = false;
                }
            }
            $sql .= ")";
        }

        if (!empty($Args['IdEmpresa']) && $Args['IdEmpresa'] != "") {
            $id = FsUtils::explodeId($Args['IdEmpresa']);
            $binding[':id20'] = $id[0];
            $binding[':id21'] = $id[1];
            $sql .= "AND E1.tipoDocEmpresa = :id20
                     AND E1.docEmpresa = :id21";
        }

        $sql .= ") el ORDER BY FechaHora DESC";
        
        $items = DB::select($sql, $binding);

        $output = $this->req->input('output', 'json');

        if ($output !== 'json' && $output !== null) {
        
            $filename = 'FSAcceso-Eventos-Consulta-Activos-' . date('Ymd-his');
            
            $headers = [
                'NroEvento' => 'Nro. Evento',
                'FechaHora' => 'Fecha / Hora',
                'Acceso' => 'Acceso',
                'Entidad' => 'Entidad',
                'Categoria' => 'Categoría',
                'Identificacion' => 'Identificación',
                'Detalle' => 'Detalle',
                'Matricula' => 'Matrícula',
                'Empresa' => 'Empresa',
            ];
    
            return FsUtils::export($output, $items, $headers, $filename);
        }

        $page = (int)$this->req->input('page', 1);        
        $paginate = FsUtils::paginateArray($items, $this->req);
        return $this->responsePaginate($paginate->items(), $paginate->total(), $page);
    }
    
    private function consIngresosPorEmpresa($Args) {
        
        if (empty($Args['Accesos'])) {
            $accesos = Acceso::where('Baja', 0)->get();
            $retornoAccesos = [];
            foreach($accesos as $acceso){
                array_push($retornoAccesos, $acceso['IdAcceso']);
            }
            $Args['Accesos'] = $retornoAccesos;
        }
        
        $sql = "SELECT Empresa, Contrato, COUNT(*) AS Accesos
                FROM (";
                    $sql .= "SELECT DISTINCT
                            dbo.Mask(pf.Documento, td.Mascara, 1, 1) AS Identificacion,
                            EMP.Nombre AS Empresa,
                            CASE WHEN E1c.NroContrato IS NULL THEN '[Sin contrato]' ELSE E1c.NroContrato END AS Contrato
                    FROM Eventos E1
                    LEFT JOIN PersonasFisicas PF ON E1.documento = PF.documento AND E1.idTipoDocumento = PF.idTipoDocumento
                    LEFT JOIN Accesos A ON E1.idAcceso = A.idAcceso
                    LEFT JOIN Empresas EMP ON E1.docEmpresa = EMP.documento AND E1.tipoDocEmpresa = EMP.idTipoDocumento
                    LEFT JOIN TiposDocumento TD ON PF.idTipoDocumento = TD.idTipoDocumento
                    LEFT JOIN Personas P ON P.idTipoDocumento = PF.idTipoDocumento AND P.documento = PF.documento
                    LEFT JOIN Personas PEMP ON P.idTipoDocumento = EMP.idTipoDocumento AND P.documento = EMP.documento
                    LEFT JOIN Categorias C ON P.idCategoria = C.idCategoria
                    LEFT JOIN EventosContratos E1c ON E1c.IdEquipo = E1.IdEquipo AND E1c.FechaHora = E1.FechaHora AND E1c.Matricula = E1.Matricula
                    WHERE E1.estado = 1
                    AND E1.tipoOperacion = 0
                    AND E1.entidad = 'P'";

        if (!empty($Args['ExcluirVisitantes'])) {
            $sql .= " AND E1.Transito = 0 ";
        }

        $sql .= " AND E1.FechaHora IN (
                        SELECT MIN(e2.FechaHora) 
                        FROM Eventos e2 
                        WHERE e2.Documento = e1.Documento 
                        AND e2.IdTipoDocumento = e1.IdTipoDocumento 
                        AND CONVERT(date, e1.FechaHora, 103) = CONVERT(date, e2.FechaHora, 103)
                        AND e2.estado = 1
                        AND e2.TipoOperacion = 0
                        ";

        if (!empty($Args['Accesos'])) {
            $sql .= " AND e2.IdAcceso IN (";
            $noComma = true;
            $i = 0;
            foreach ($Args['Accesos'] as $acceso) {

                $accesos = explode(',', $acceso);
                foreach($accesos as $accesoFull){
                    $i++;
                    if (!$noComma) $sql .= ", ";
                    $sql .= ':acceso0'.$i;
                    $binding[':acceso0'.$i] = $accesoFull;
                    $noComma = false;
                }
                
            }
            $sql .= ")";
        }

        $sql .= ")";

        if (!empty($Args['FechaDesde'])) {
            $sql .= " AND CONVERT(date, E1.fechaHora, 103) >= CONVERT(date, :FechaDesde, 103)";
            $binding[':FechaDesde'] = $Args['FechaDesde']. ' 00:00:00';
        }

        if (!empty($Args['FechaHasta'])) {
            $sql .= " AND CONVERT(date, E1.fechaHora, 103) <= CONVERT(date, :FechaHasta, 103)";
            $binding[':FechaHasta'] = $Args['FechaHasta']. ' 00:00:00';
        }
        
        if (!empty($Args['NoBaja'])) {
            $sql .= " AND P.baja = 0";
        }

        if (!empty($Args['Accesos'])) {
            $sql .= " AND E1.IdAcceso IN (";
            $noComma = true;
            $i = 0;
            foreach ($Args['Accesos'] as $acceso) {
                
                $accesos = explode(',', $acceso);
                foreach($accesos as $accesoFull){
                    $i++;
                    if (!$noComma) $sql .= ", ";
                    $sql .= ':acceso1'.$i;
                    $binding[':acceso1'.$i] = $accesoFull;
                    $noComma = false;
                }
                
            }
            $sql .= ")";
        }

        if (!empty($Args['IdEmpresa']) && $Args['IdEmpresa'] != "") {
            $id = FsUtils::explodeId($Args['IdEmpresa']);
            $binding[':id0'] = $id[0];
            $binding[':id1'] = $id[1];
            $sql .= "AND E1.tipoDocEmpresa = :id1
                     AND E1.docEmpresa = :id0";
        }

        $sql .= ") el ";
        $sql .= " GROUP BY Empresa, Contrato "
                . "ORDER BY Empresa, Contrato ASC";

        $items = DB::select($sql, $binding);

        $output = $this->req->input('output', 'json');
        if ($output !== 'json') {
            $dataOutput = array_map(function($item) {
                return [
                    'Empresa' => $item->Empresa,
                    'Contrato' => $item->Contrato,
                    'Accesos' => $item->Accesos,
                ];
            },$items);
            return $this->exportIngresosPorEmpresa($dataOutput, $output);
        }

        $page = (int)$this->req->input('page', 1);
        $paginate = FsUtils::paginateArray($items, $this->req);
        return $this->responsePaginate($paginate->items(), $paginate->total(), $page);
    }

    private function exportIngresosPorEmpresa(array $data, string $type) {
        $filename = 'FSAcceso-Ingresos-Por-Empresa-' . date('Ymd his');
        $headers = [
            'Empresa' => 'Empresa',
            'Contrato' => 'Contrato',
            'Accesos' => 'Accesos',
        ];
        return FsUtils::export($type, $data, $headers, $filename);
    }

    private function consSitio($Args) {
        
        /**
         * @todo
         */
        //Fata codifiar parte del codigo ya que crea una tabla temporal y luego lee sus datos.

        $entidades = array('MAQ' => 'M', 'PF' => 'P', 'VEH' => 'V', 'VIS' => 'P');
        
        //SELECT * INTO #ConsSitioTMP FROM(
        $sql = "SELECT E1.NroEvento, E1.Entidad,
                    CASE
                        WHEN PF.NombreCompleto IS NOT NULL THEN PF.NombreCompleto
                        WHEN e1.PrimerNombre IS NOT NULL AND e1.PrimerApellido IS NOT NULL THEN e1.PrimerNombre + ' ' + e1.SegundoNombre + ' ' + e1.PrimerApellido + ' ' + e1.SegundoApellido
                        WHEN M.NroSerie IS NOT NULL THEN M.NroSerie
                        WHEN V.Serie IS NOT NULL AND V.Numero IS NOT NULL THEN V.Serie + ' ' + LTRIM(RTRIM(STR(V.Numero)))
                        ELSE ''
                    END AS Identificacion,
                    EMP.nombre AS Empresa, 
                    CASE
                        WHEN C.descripcion IS NOT NULL THEN C.descripcion
                        WHEN CM.descripcion IS NOT NULL THEN CM.descripcion
                        WHEN CV.descripcion IS NOT NULL THEN CV.descripcion
                        ELSE ''
                    END AS Categoria,
                    E1.Matricula,
                    E1.FechaHora,
                    A.descripcion AS Acceso
                FROM Eventos E1 
                INNER JOIN Accesos A ON E1.idAcceso = A.idAcceso 
                LEFT JOIN PersonasFisicas PF ON E1.documento = PF.documento AND E1.idTipoDocumento = PF.idTipoDocumento AND E1.matricula = PF.matricula 
                LEFT JOIN Empresas EMP ON E1.docEmpresa = EMP.documento AND E1.tipoDocEmpresa = EMP.idTipoDocumento 
                LEFT JOIN TiposDocumento TD ON PF.idTipoDocumento = TD.idTipoDocumento 
                LEFT JOIN Personas P ON P.idTipoDocumento = PF.idTipoDocumento AND P.documento = PF.documento 
                LEFT JOIN Categorias C ON P.idCategoria = C.idCategoria 
                LEFT JOIN Maquinas M ON E1.nroSerie = M.nroSerie AND E1.matricula = M.matricula 
                LEFT JOIN Categorias CM ON M.idCategoria = CM.idCategoria 
                LEFT JOIN Vehiculos V ON E1.serie = V.serie AND E1.numero = V.numero AND E1.matricula = V.matricula 
                LEFT JOIN Categorias CV ON V.idCategoria = CV.idCategoria 
                LEFT JOIN TiposMaquinas TM ON M.idTipoMaq = TM.idTipoMaquina 
                LEFT JOIN MarcasMaquinas MM ON M.idMarcaMaq = MM.idMarcaMaq 
                LEFT JOIN TiposVehiculos TV ON V.idTipoVehiculo = TV.idTipoVehiculo 
                LEFT JOIN MarcasVehiculos MV ON V.idMarcaVehic = MV.idMarcaVehic 
                
                WHERE CONVERT(date, E1.fechaHora, 103) >= CONVERT(date, :FechaDesde, 103)
                AND CONVERT(date, E1.fechaHora, 103) <= CONVERT(date, :FechaHasta, 103)";
                
                $binding[':FechaDesde'] = $Args['Fecha']. ' 00:00:00';
                $binding[':FechaHasta'] = $Args['Fecha']. ' 23:59:59';

                $sql .= "AND E1.estado = 1 
                AND E1.tipoOperacion = 0 
                AND NOT E1.matricula IS NULL 
                AND NOT EXISTS (SELECT E2.* 
                                FROM Eventos E2 
                                WHERE E2.estado = 1 
                                AND E2.TipoOperacion = 1 
                                AND E2.fechaHora > E1.fechaHora 
                                AND E1.matricula = E2.matricula ";
                
        if (!empty($Args['Accesos'])) {
            $sql .= " AND E2.IdAcceso IN (";
            $noComma = true;
            $i = 0;
            foreach ($Args['Accesos'] as $acceso) {
                $accesos = explode(',', $acceso);
                foreach($accesos as $accesoFull){
                    $i++;
                    if (!$noComma) $sql .= ", ";
                    $sql .= ':acceso'.$i;
                    $binding[':acceso'.$i] = $accesoFull;
                    $noComma = false;
                }
            }
            $sql .= ")";
        }
        
        if (!empty($Args['Entidad'])) {
            $sql .= " AND E2.Entidad = :Entidad ";
            $binding[':Entidad'] = $entidades[$Args['Entidad']];
        }
        
        $sql .= ") ";

        if (!empty($Args['Accesos'])) {
            $sql .= " AND E1.IdAcceso IN (";
            $noComma = true;
            $i = 0;
            foreach ($Args['Accesos'] as $acceso) {
                $accesos = explode(',', $acceso);
                foreach($accesos as $accesoFull){
                    $i++;
                    if (!$noComma) $sql .= ", ";
                    $sql .= ':acceso11'.$i;
                    $binding[':acceso11'.$i] = $accesoFull;
                    $noComma = false;
                }
            }
            $sql .= ")";
        }
        
        if (!empty($Args['Entidad'])) {
            $sql .= " AND E1.Entidad = :Entidad1 ";
            $binding[':Entidad1'] = $entidades[$Args['Entidad']];

            if ($Args['Entidad'] == 'PF') {
                //$sql .= ' AND pf.Transito = 0 ';
            }
            else if ($Args['Entidad'] == 'VIS') {
                $sql .= ' AND pf.Transito = 1 ';
            }
        }

        

        if (!empty($Args['Empresas'])) {
            $sql .= "AND EMP.Documento + '-' + LTRIM(RTRIM(STR(EMP.IdTipoDocumento))) IN (";
            $noComma = true;
            $i = 0;
            foreach ($Args['Empresas'] as $e) {
                $i++;
                if (!$noComma) $sql .= ", ";
                $sql .= ':empresa'.$i;
                $binding[':empresa'.$i] = $e['IdEmpresa'];
                $noComma = false;
            }
            $sql .= ")";
        }


        //$sql .= ") sitio";
                
        $data = DB::select($sql, $binding);
        
       // $sql = "SELECT * INTO #EventosListado FROM (";
        //$sql = "SELECT * FROM (";
        $sql = "SELECT "
                  . "t1.NroEvento, "
                  . "t1.FechaHora AS FechaHoraT, "
                  . "CONVERT(varchar(10), t1.FechaHora, 103) + ' ' + CONVERT(varchar(5), t1.FechaHora, 108) AS FechaHora, "
                  . "dbo.TIME_DIFF(t1.FechaHora, GETDATE()) AS Tiempo,"
                  . "DATEDIFF(ss, t1.FechaHora, GETDATE()) AS TiempoSegs,"
                  . "t1.Acceso, "
                  . "t2.*,"
                  . "t2.Empresa AS Cabezal "
              . "FROM (SELECT DISTINCT 
                           Identificacion,
                           Entidad,
                           Empresa,
                           Categoria,
                           Matricula,
                           CONVERT(varchar(10), FechaHora, 103) AS Fecha "
                  . "FROM #ConsSitioTMP) t2 "
              . "CROSS APPLY (SELECT TOP 1 
                                  NroEvento,
                                  FechaHora,
                                  Acceso
                              FROM #ConsSitioTMP
                              WHERE CONVERT(varchar(10), FechaHora, 103) = t2.Fecha AND Matricula = t2.Matricula) t1
               ORDER BY FechaHoraT DESC"; 

        $rndTable = '';
        $list = DB::select($sql);
        
        /*if (!empty($list)) {
            $totals = self::listado($Usuario, $IdEmpresa, " SELECT 
                                                                'TOTALES' AS Cabezal,
                                                                CASE
                                                                    WHEN Entidad = 'P' THEN 'Personas'
                                                                    WHEN Entidad = 'M' THEN 'Máquinas'
                                                                    WHEN Entidad = 'V' THEN 'Vehículos'
                                                                    ELSE 'Otros'
                                                                END AS Acceso, 
                                                                COUNT(*) AS Tiempo 
                                                            FROM " . $rndTable .  " 
                                                            GROUP BY Entidad");
            
            if (isset($list->Data)) {
                $list->Data = array_merge($list->Data, $totals);
            }
            else {
                $list = array_merge($list, $totals);
            }
        }
        
        return $list;*/
    }

    private function consDuplicados($Args) {
       
        $binding = [];

       //SELECT * INTO #EventosListado FROM (
            $sql = "         SELECT
                        'func=AdmPersonas/Documento=' + e.Documento + '/IdTipoDocumento=' + LTRIM(RTRIM(STR(e.IdTipoDocumento))) AS ObjUrl,
                        e.NroEvento,
                        CONVERT(VARCHAR(10), e.FechaHora, 103) + ' ' + CONVERT(VARCHAR(8), e.FechaHora, 108) AS FechaHora,
                        'Persona' AS Entidad,
                        dbo.Mask(e.Documento, td.Mascara, 1, 1) AS Identificacion,
                        pf.PrimerNombre + ' ' + pf.SegundoNombre + ' ' + pf.PrimerApellido + ' ' + pf.SegundoApellido AS Detalle,
                        e.Matricula,
                        em.Nombre AS Empresa,
                        dbo.TIME_DIFF((SELECT TOP 1 
                                        e2.FechaHora 
                                        FROM Eventos e2 
                                        WHERE e.Estado = 1
                                        AND e.TipoOperacion = 0
                                        AND e2.FechaHora < e.FechaHora 
                                        ORDER BY FechaHora DESC), e.FechaHora) AS Diff
                    FROM Eventos e
                    INNER JOIN PersonasFisicas pf ON e.Documento = pf.Documento AND e.IdTipoDocumento = pf.IdTipoDocumento
                    INNER JOIN TiposDocumento td ON e.IdTipoDocumento = td.IdTipoDocumento
                    LEFT JOIN Empresas em ON e.DocEmpresa = em.Documento AND e.TipoDocEmpresa = em.IdTipoDocumento
                    INNER JOIN Categorias c ON c.IdCategoria = e.IdCategoria
                    WHERE e.Estado = 1
                    AND e.TipoOperacion = 0";
                
        if (!empty($Args['IdAcceso'])) {
            $sql .= " AND e.IdAcceso = :IdAcceso";
            $binding[':IdAcceso'] = $Args['IdAcceso'];
        }
        
        if (!empty($Args['FechaDesde'])) {
            $sql .= " AND CONVERT(date, e.FechaHora, 103) = CONVERT(date, :FechaDesde, 103)";
            $binding[':FechaDesde'] = $Args['FechaDesde']. ' 00:00:00';
        }  

        $sql .= " AND e.Matricula IN (
                    SELECT e1.Matricula
                    FROM Eventos e1
                    WHERE e1.Estado = 1
                    AND e1.TipoOperacion = 0";
        
        if (!empty($Args['IdAcceso'])) {
            $sql .= " AND e1.IdAcceso = :IdAcceso";
            $binding[':IdAcceso'] = $Args['IdAcceso'];
        }

        if (!empty($Args['FechaDesde'])) {
            $sql .= " AND CONVERT(date, e1.FechaHora, 103) = CONVERT(date, :FechaDesde1, 103)";
            $binding[':FechaDesde1'] = $Args['FechaDesde']. ' 00:00:00';
        }

        //if (!empty($Args->FechaHasta)) {
        //$sql .= " AND CONVERT(date, e1.FechaHora, 103) <= " . mzbasico::valueToDb("date", $Args->FechaHasta);
        //}

        $sql .= "
                GROUP BY e1.Matricula
                HAVING COUNT(*) > 1)";
        
        if (!empty($Args['DiferenciaMins'])) {
            $sql .= " AND DATEDIFF(mi, (SELECT TOP 1 
                                        e2.FechaHora 
                                    FROM Eventos e2 
                                    WHERE e.Estado = 1
                                    AND e.TipoOperacion = 0
                                    AND e2.FechaHora < e.FechaHora 
                                    ORDER BY FechaHora DESC), e.FechaHora) >= :DiferenciaMins";
            $binding[':DiferenciaMins'] = $Args['DiferenciaMins'];
        }
                    
        //$sql .= ") el;";

        $items = DB::select($sql, $binding);

        $output = $this->req->input('output', 'json');

        if ($output !== 'json' && $output !== null) {
        
            $filename = 'FSAcceso-Eventos-Consulta-Duplicados-' . date('Ymd-his');
            
            $headers = [
                'FechaHora' => 'Fecha / Hora',
                'Matricula' => 'Matrícula',
                'Identificacion' => 'Identificación',
                'Detalle' => 'Detalle',
                'Empresa' => 'Empresa',
                'Diferencia' => 'Diferencia',
            ];
    
            return FsUtils::export($output, $items, $headers, $filename);
        }

        $page = (int)$this->req->input('page', 1);        
        $paginate = FsUtils::paginateArray($items, $this->req);
        return $this->responsePaginate($paginate->items(), $paginate->total(), $page);
    }

    private static function eveConsCantinaResumido($Args) {
        
        $binding = [];

        $sql = "SELECT
                    CASE WHEN adc.NroContrato IS NOT NULL THEN adc.NroContrato ELSE 'Sin contrato' END AS NroContrato,
                    'func=AdmEmpresas/Documento=' + adc.DocEmpresa + '/IdTipoDocumento=' + LTRIM(RTRIM(STR(adc.TipoDocEmpresa))) AS ObjUrl,
                    'Empresa' AS Entidad,
                    dbo.Mask(adc.DocEmpresa, td.Mascara, 1, 1) AS Identificacion,
                    em.Nombre AS Empresa,
                    adc.Fecha,
                    adc.Desayunos,
                    adc.Almuerzos,
                    adc.Cenas,
                    adc.Total
                FROM
                    (SELECT
                        ec.NroContrato,
                        e.DocEmpresa,
                        e.TipoDocEmpresa,
                        CONVERT(varchar(10), CONVERT(date, e.FechaHora, 103)) AS Fecha,
                        SUM(
                            CASE 
                                WHEN CONVERT(time, e.FechaHora, 108) BETWEEN CONVERT(time, '08:00:00', 108) AND CONVERT(time, '11:59:59', 108) THEN 1
                                ELSE 0
                            END) AS Desayunos,
                        SUM(
                            CASE 
                                WHEN CONVERT(time, e.FechaHora, 108) BETWEEN CONVERT(time, '12:00:00', 108) AND CONVERT(time, '17:59:59', 108) THEN 1
                                ELSE 0
                            END) AS Almuerzos,
                        SUM(
                            CASE 
                                WHEN CONVERT(time, e.FechaHora, 108) BETWEEN CONVERT(time, '18:00:00', 108) AND CONVERT(time, '23:59:59', 108) THEN 1
                                ELSE 0
                            END) AS Cenas,
                        COUNT(*) AS Total,
                        e.Estado
                    FROM Eventos e
                    LEFT JOIN EventosContratos ec ON ec.IdEquipo = e.IdEquipo AND ec.FechaHora = e.FechaHora
                    INNER JOIN Accesos a ON a.IdAcceso = e.IdAcceso
                    WHERE e.Estado = 1
                    AND a.Cantina = 1";
              
        if (!empty($Args['FechaDesde'])) {
            $sql .= " AND CONVERT(date, e.FechaHora, 103) >= CONVERT(date, :FechaDesde, 103)";
            $binding[':FechaDesde'] = $Args['FechaDesde']. ' 00:00:00';
        }
        
        if (!empty($Args['FechaHasta'])) {
            $sql .= " AND CONVERT(date, e.FechaHora, 103) <= CONVERT(date, :FechaHasta, 103)";
            $binding[':FechaHasta'] = $Args['FechaHasta']. ' 00:00:00';
        }
        
        if (!empty($Args['Accesos'])) {
            $sql .= " AND e.IdAcceso IN (";
            $noComma = true;
            $i = 0;
            foreach ($Args['Accesos'] as $acceso) {

                $accesos = explode(',', $acceso);
                foreach($accesos as $accesoFull){
                    $i++;
                    if (!$noComma) $sql .= ", ";
                    $sql .= ':acceso'.$i;
                    $binding[':acceso'.$i] = $accesoFull;
                    $noComma = false;
                }
            }
            $sql .= ")";
        }

        
        if (!empty($Args['Contratos'])) {
            
            $contratos = "";
            $docsEmpresa = "";
            $tipoDocsEmpresa = "";
           
            $noComma = true;
            $i = 0;

            $sql .= " AND ec.NroContrato IN (";

            foreach ($Args['Contratos'] as $contrato) {
                $aux = FsUtils::explodeId($contrato); // 1 => TipoDocEmpresa
                $aux2 = FsUtils::explodeId($aux[0]); // 0 => NroContrato, 1 => DocEmpresa
                $i++;
                if (!$noComma) {
                    $sql .= ", ";
                }
                $sql .= ":contratos".$i;
                $binding[':contratos'.$i] = $aux2[0];
                $noComma = false;
            }

            $sql .= ") AND e.DocEmpresa IN (";

            $noComma = true;
            $i = 0;

            foreach ($Args['Contratos'] as $contrato) {
                $aux = FsUtils::explodeId($contrato); // 1 => TipoDocEmpresa
                $aux2 = FsUtils::explodeId($aux[0]); // 0 => NroContrato, 1 => DocEmpresa
                $i++;
                if (!$noComma) {
                    $sql .= ", ";
                }
                $sql .= ":docsEmpresa".$i;
                $binding[':docsEmpresa'.$i] = $aux2[1];
                $noComma = false;
            }

            $sql .= ") AND e.TipoDocEmpresa IN (";

            $noComma = true;
            $i = 0;

            foreach ($Args['Contratos'] as $contrato) {
                $aux = FsUtils::explodeId($contrato); // 1 => TipoDocEmpresa
                $i++;
                if (!$noComma) {
                    $sql .= ", ";
                }
                $sql .= ':tipoDocsEmpresa'.$i;
                $binding[':tipoDocsEmpresa'.$i] = $aux[1];
                $noComma = false;
            }
            
            $sql .= ")";
            //$sql .= " AND ec.NroContrato IN (" . $contratos . ") AND e.DocEmpresa IN (" . $docsEmpresa . ") AND e.TipoDocEmpresa IN (" . $tipoDocsEmpresa . ")";
            
        }
        
        $sql .= " GROUP BY ec.NroContrato, e.DocEmpresa, e.TipoDocEmpresa, CONVERT(varchar(10), CONVERT(date, e.FechaHora, 103)), e.Estado) adc
                LEFT JOIN Empresas em ON adc.DocEmpresa = em.Documento AND adc.TipoDocEmpresa = em.IdTipoDocumento
                INNER JOIN TiposDocumento td ON adc.TipoDocempresa = td.IdTipoDocumento";
        
        return DB::select($sql, $binding);
    }

    private static function eveConsCantinaDetallado($Args) {
        
        $binding = [];
        
        $sql = "SELECT
                    'func=AdmPersonas/Documento=' + adc.Documento + '/IdTipoDocumento=' + LTRIM(RTRIM(STR(adc.IdTipoDocumento))) AS ObjUrl,
                    'Persona' AS Entidad,
                    dbo.Mask(adc.Documento, td.Mascara, 1, 1) AS Identificacion,
                    pf.PrimerNombre + ' ' + pf.SegundoNombre + ' ' + pf.PrimerApellido + ' ' + pf.SegundoApellido AS Detalle,
                    em.Nombre AS Empresa,
                    c.Descripcion AS Categoria,
                    adc.Fecha,
                    adc.Desayunos,
                    adc.Almuerzos,
                    adc.Cenas,
                    adc.Total
                FROM
                    (SELECT
                        e.Documento,
                        e.IdTipoDocumento,
                        CONVERT(varchar(10), CONVERT(date, e.FechaHora, 103)) AS Fecha,
                        e.DocEmpresa,
                        e.TipoDocEmpresa,
                        e.IdCategoria,
                        SUM(
                            CASE 
                                WHEN CONVERT(time, e.FechaHora, 108) BETWEEN CONVERT(time, '08:00:00', 108) AND CONVERT(time, '11:59:59', 108) THEN 1
                                ELSE 0
                            END) AS Desayunos,
                        SUM(
                            CASE 
                                WHEN CONVERT(time, e.FechaHora, 108) BETWEEN CONVERT(time, '12:00:00', 108) AND CONVERT(time, '17:59:59', 108) THEN 1
                                ELSE 0
                            END) AS Almuerzos,
                        SUM(
                            CASE 
                                WHEN CONVERT(time, e.FechaHora, 108) BETWEEN CONVERT(time, '18:00:00', 108) AND CONVERT(time, '23:59:59', 108) THEN 1
                                ELSE 0
                            END) AS Cenas,
                        COUNT(*) AS Total,
                        e.Estado
                    FROM Eventos e
                    LEFT JOIN EventosContratos ec ON ec.IdEquipo = e.IdEquipo AND ec.FechaHora = e.FechaHora
                    INNER JOIN Accesos a ON a.IdAcceso = e.IdAcceso
                    WHERE e.Estado = 1
                    AND a.Cantina = 1";
        
        if (!empty($Args['FechaDesde'])) {
            $sql .= " AND CONVERT(date, e.FechaHora, 103) >= CONVERT(date, :FechaDesde, 103)";
            $binding[':FechaDesde'] = $Args['FechaDesde']. ' 00:00:00';
        }
        
        if (!empty($Args['FechaHasta'])) {
            $sql .= " AND CONVERT(date, e.FechaHora, 103) <= CONVERT(date, :FechaHasta, 103)";
            $binding[':FechaHasta'] = $Args['FechaHasta']. ' 00:00:00';
        }
        
        if (!empty($Args['Accesos'])) {
            $sql .= " AND e.IdAcceso IN (";
            $noComma = true;
            $i = 0;
            foreach ($Args['Accesos'] as $acceso) {
                $accesos = explode(',', $acceso);
                foreach($accesos as $accesoFull){
                    $i++;
                    if (!$noComma) $sql .= ", ";
                    $sql .= ':acceso'.$i;
                    $binding[':acceso'.$i] = $accesoFull;
                    $noComma = false;
                }
            }
            $sql .= ")";
        }
        
        if (!empty($Args['Contratos'])) {
            
            $contratos = "";
            $docsEmpresa = "";
            $tipoDocsEmpresa = "";
           
            $noComma = true;
            $i = 0;

            $sql .= " AND ec.NroContrato IN (";

            foreach ($Args['Contratos'] as $contrato) {
                $aux = FsUtils::explodeId($contrato); // 1 => TipoDocEmpresa
                $aux2 = FsUtils::explodeId($aux[0]); // 0 => NroContrato, 1 => DocEmpresa
                $i++;
                if (!$noComma) {
                    $sql .= ", ";
                }
                $sql .= ":contratos".$i;
                $binding[':contratos'.$i] = $aux2[0];
                $noComma = false;
            }

            $sql .= ") AND e.DocEmpresa IN (";

            $noComma = true;
            $i = 0;

            foreach ($Args['Contratos'] as $contrato) {
                $aux = FsUtils::explodeId($contrato); // 1 => TipoDocEmpresa
                $aux2 = FsUtils::explodeId($aux[0]); // 0 => NroContrato, 1 => DocEmpresa
                $i++;
                if (!$noComma) {
                    $sql .= ", ";
                }
                $sql .= ":docsEmpresa".$i;
                $binding[':docsEmpresa'.$i] = $aux2[1];
                $noComma = false;
            }

            $sql .= ") AND e.TipoDocEmpresa IN (";

            $noComma = true;
            $i = 0;

            foreach ($Args['Contratos'] as $contrato) {
                $aux = FsUtils::explodeId($contrato); // 1 => TipoDocEmpresa
                $i++;
                if (!$noComma) {
                    $sql .= ", ";
                }
                $sql .= ':tipoDocsEmpresa'.$i;
                $binding[':tipoDocsEmpresa'.$i] = $aux[1];
                $noComma = false;
            }
            
            $sql .= ")";
            //$sql .= " AND ec.NroContrato IN (" . $contratos . ") AND e.DocEmpresa IN (" . $docsEmpresa . ") AND e.TipoDocEmpresa IN (" . $tipoDocsEmpresa . ")";
            
        }
        
        $sql .= " GROUP BY e.Documento, e.IdTipoDocumento, CONVERT(varchar(10), CONVERT(date, e.FechaHora, 103)), e.DocEmpresa, e.TipoDocEmpresa, e.IdCategoria, e.Estado) adc
            INNER JOIN PersonasFisicas pf ON adc.Documento = pf.Documento AND adc.IdTipoDocumento = pf.IdTipoDocumento
            INNER JOIN TiposDocumento td ON adc.IdTipoDocumento = td.IdTipoDocumento
            LEFT JOIN Empresas em ON adc.DocEmpresa = em.Documento AND adc.TipoDocEmpresa = em.IdTipoDocumento
            INNER JOIN Categorias c ON c.IdCategoria = adc.IdCategoria";
        
        $sql .= " UNION ALL ";

        $sql .= "SELECT
                    'func=AdmMaquinas/NroSerie=' + adc.NroSerie AS ObjUrl,
                    'Máquina' AS Entidad,
                    adc.NroSerie AS Identificacion,
                    mm.Descripcion + ' ' + m.Modelo + ' (' + tm.Descripcion + ')' AS Detalle,
                    em.Nombre AS Empresa,
                    c.Descripcion AS Categoria,
                    adc.Fecha,
                    adc.Desayunos,
                    adc.Almuerzos,
                    adc.Cenas,
                    adc.Total
                FROM
                    (SELECT
                        e.NroSerie,
                        CONVERT(varchar(10), CONVERT(date, e.FechaHora, 103)) AS Fecha,
                        e.DocEmpresa,
                        e.TipoDocEmpresa,
                        e.IdCategoria,
                        SUM(
                            CASE 
                                WHEN CONVERT(time, e.FechaHora, 108) BETWEEN CONVERT(time, '08:00:00', 108) AND CONVERT(time, '11:59:59', 108) THEN 1
                                ELSE 0
                            END) AS Desayunos,
                        SUM(
                            CASE 
                                WHEN CONVERT(time, e.FechaHora, 108) BETWEEN CONVERT(time, '12:00:00', 108) AND CONVERT(time, '17:59:59', 108) THEN 1
                                ELSE 0
                            END) AS Almuerzos,
                        SUM(
                            CASE 
                                WHEN CONVERT(time, e.FechaHora, 108) BETWEEN CONVERT(time, '18:00:00', 108) AND CONVERT(time, '23:59:59', 108) THEN 1
                                ELSE 0
                            END) AS Cenas,
                        COUNT(*) AS Total,
                        e.Estado
                    FROM Eventos e
                    LEFT JOIN EventosContratos ec ON ec.IdEquipo = e.IdEquipo AND ec.FechaHora = e.FechaHora
                    INNER JOIN Accesos a ON a.IdAcceso = e.IdAcceso
                    WHERE e.Estado = 1
                    AND a.Cantina = 1";
        
        if (!empty($Args['FechaDesde'])) {
            $sql .= " AND CONVERT(date, e.FechaHora, 103) >= CONVERT(date, :FechaDesde1, 103)";
            $binding[':FechaDesde1'] = $Args['FechaDesde']. ' 00:00:00';
        }
        
        if (!empty($Args['FechaHasta'])) {
            $sql .= " AND CONVERT(date, e.FechaHora, 103) <= CONVERT(date, :FechaHasta1, 103)";
            $binding[':FechaHasta1'] = $Args['FechaHasta']. ' 00:00:00';
        }
        
        if (!empty($Args['Accesos'])) {
            $sql .= " AND e.IdAcceso IN (";
            $noComma = true;
            $i = 0;
            foreach ($Args['Accesos'] as $acceso) {
                $accesos = explode(',', $acceso);
                foreach($accesos as $accesoFull){
                    $i++;
                    if (!$noComma) $sql .= ", ";
                    $sql .= ':acceso1'.$i;
                    $binding[':acceso1'.$i] = $accesoFull;
                    $noComma = false;
                }
            }
            $sql .= ")";
        }
        
        if (!empty($Args['Contratos'])) {
            
            $contratos = "";
            $docsEmpresa = "";
            $tipoDocsEmpresa = "";
           
            $noComma = true;
            $i = 0;

            $sql .= " AND ec.NroContrato IN (";

            foreach ($Args['Contratos'] as $contrato) {
                $aux = FsUtils::explodeId($contrato); // 1 => TipoDocEmpresa
                $aux2 = FsUtils::explodeId($aux[0]); // 0 => NroContrato, 1 => DocEmpresa
                $i++;
                if (!$noComma) {
                    $sql .= ", ";
                }
                $sql .= ":contratos1".$i;
                $binding[':contratos1'.$i] = $aux2[0];
                $noComma = false;
            }

            $sql .= ") AND e.DocEmpresa IN (";

            $noComma = true;
            $i = 0;

            foreach ($Args['Contratos'] as $contrato) {
                $aux = FsUtils::explodeId($contrato); // 1 => TipoDocEmpresa
                $aux2 = FsUtils::explodeId($aux[0]); // 0 => NroContrato, 1 => DocEmpresa
                $i++;
                if (!$noComma) {
                    $sql .= ", ";
                }
                $sql .= ":docsEmpresa1".$i;
                $binding[':docsEmpresa1'.$i] = $aux2[1];
                $noComma = false;
            }

            $sql .= ") AND e.TipoDocEmpresa IN (";

            $noComma = true;
            $i = 0;

            foreach ($Args['Contratos'] as $contrato) {
                $aux = FsUtils::explodeId($contrato); // 1 => TipoDocEmpresa
                $i++;
                if (!$noComma) {
                    $sql .= ", ";
                }
                $sql .= ':tipoDocsEmpresa1'.$i;
                $binding[':tipoDocsEmpresa1'.$i] = $aux[1];
                $noComma = false;
            }
            
            $sql .= ")";
            //$sql .= " AND ec.NroContrato IN (" . $contratos . ") AND e.DocEmpresa IN (" . $docsEmpresa . ") AND e.TipoDocEmpresa IN (" . $tipoDocsEmpresa . ")";
            
        }
        
        $sql .= " GROUP BY e.NroSerie, CONVERT(varchar(10), CONVERT(date, e.FechaHora, 103)), e.DocEmpresa, e.TipoDocEmpresa, e.IdCategoria, e.Estado) adc
            INNER JOIN Maquinas m ON adc.NroSerie = m.NroSerie
            INNER JOIN MarcasMaquinas mm ON mm.IdMarcaMaq = m.IdMarcaMaq
            INNER JOIN TiposMaquinas tm ON tm.IdTipoMaquina = m.IdTipoMaq
            LEFT JOIN Empresas em ON adc.DocEmpresa = em.Documento AND adc.TipoDocEmpresa = em.IdTipoDocumento
            INNER JOIN Categorias c ON c.IdCategoria = adc.IdCategoria";

        $sql .= " UNION ALL ";
        
        $sql .= "SELECT
                    'func=AdmVehiculos/Serie=' + adc.Serie + '/Numero=' + LTRIM(RTRIM(STR(adc.Numero))) AS ObjUrl,
                    'Vehículo' AS Entidad,
                    v.Serie + ' ' + RTRIM(LTRIM(STR(v.Numero))) AS Identificacion,
                    mv.Descripcion + ' ' + v.Modelo + ' (' + tv.Descripcion + ')' AS Detalle,
                    em.Nombre AS Empresa,
                    c.Descripcion AS Categoria,
                    adc.Fecha,
                    adc.Desayunos,
                    adc.Almuerzos,
                    adc.Cenas,
                    adc.Total
                FROM
                    (SELECT
                        e.Serie,
                        e.Numero,
                        CONVERT(varchar(10), CONVERT(date, e.FechaHora, 103)) AS Fecha,
                        e.DocEmpresa,
                        e.TipoDocEmpresa,
                        e.IdCategoria,
                        SUM(
                            CASE 
                                WHEN CONVERT(time, e.FechaHora, 108) BETWEEN CONVERT(time, '08:00:00', 108) AND CONVERT(time, '11:59:59', 108) THEN 1
                                ELSE 0
                            END) AS Desayunos,
                        SUM(
                            CASE 
                                WHEN CONVERT(time, e.FechaHora, 108) BETWEEN CONVERT(time, '12:00:00', 108) AND CONVERT(time, '17:59:59', 108) THEN 1
                                ELSE 0
                            END) AS Almuerzos,
                        SUM(
                            CASE 
                                WHEN CONVERT(time, e.FechaHora, 108) BETWEEN CONVERT(time, '18:00:00', 108) AND CONVERT(time, '23:59:59', 108) THEN 1
                                ELSE 0
                            END) AS Cenas,
                        COUNT(*) AS Total,
                        e.Estado
                    FROM Eventos e
                    LEFT JOIN EventosContratos ec ON ec.IdEquipo = e.IdEquipo AND ec.FechaHora = e.FechaHora
                    INNER JOIN Accesos a ON a.IdAcceso = e.IdAcceso
                    WHERE e.Estado = 1
                    AND a.Cantina = 1";
        
        if (!empty($Args['FechaDesde'])) {
            $sql .= " AND CONVERT(date, e.FechaHora, 103) >= CONVERT(date, :FechaDesde2, 103)";
            $binding[':FechaDesde2'] = $Args['FechaDesde']. ' 00:00:00';
        }
        
        if (!empty($Args['FechaHasta'])) {
            $sql .= " AND CONVERT(date, e.FechaHora, 103) <= CONVERT(date, :FechaHasta2, 103)";
            $binding[':FechaHasta2'] = $Args['FechaHasta']. ' 00:00:00';
        }
        
        if (!empty($Args['Accesos'])) {
            $sql .= " AND e.IdAcceso IN (";
            $noComma = true;
            $i = 0;
            foreach ($Args['Accesos'] as $acceso) {
                $accesos = explode(',', $acceso);
                foreach($accesos as $accesoFull){
                    $i++;
                    if (!$noComma) $sql .= ", ";
                    $sql .= ':acceso2'.$i;
                    $binding[':acceso2'.$i] = $accesoFull;
                    $noComma = false;
                }
            }
            $sql .= ")";
        }
        
        if (!empty($Args['Contratos'])) {
            
            $contratos = "";
            $docsEmpresa = "";
            $tipoDocsEmpresa = "";
           
            $noComma = true;
            $i = 0;

            $sql .= " AND ec.NroContrato IN (";

            foreach ($Args['Contratos'] as $contrato) {
                $aux = FsUtils::explodeId($contrato); // 1 => TipoDocEmpresa
                $aux2 = FsUtils::explodeId($aux[0]); // 0 => NroContrato, 1 => DocEmpresa
                $i++;
                if (!$noComma) {
                    $sql .= ", ";
                }
                $sql .= ":contratos2".$i;
                $binding[':contratos2'.$i] = $aux2[0];
                $noComma = false;
            }

            $sql .= ") AND e.DocEmpresa IN (";

            $noComma = true;
            $i = 0;

            foreach ($Args['Contratos'] as $contrato) {
                $aux = FsUtils::explodeId($contrato); // 1 => TipoDocEmpresa
                $aux2 = FsUtils::explodeId($aux[0]); // 0 => NroContrato, 1 => DocEmpresa
                $i++;
                if (!$noComma) {
                    $sql .= ", ";
                }
                $sql .= ":docsEmpresa2".$i;
                $binding[':docsEmpresa2'.$i] = $aux2[1];
                $noComma = false;
            }

            $sql .= ") AND e.TipoDocEmpresa IN (";

            $noComma = true;
            $i = 0;

            foreach ($Args['Contratos'] as $contrato) {
                $aux = FsUtils::explodeId($contrato); // 1 => TipoDocEmpresa
                $i++;
                if (!$noComma) {
                    $sql .= ", ";
                }
                $sql .= ':tipoDocsEmpresa2'.$i;
                $binding[':tipoDocsEmpresa2'.$i] = $aux[1];
                $noComma = false;
            }
            
            $sql .= ")";
            //$sql .= " AND ec.NroContrato IN (" . $contratos . ") AND e.DocEmpresa IN (" . $docsEmpresa . ") AND e.TipoDocEmpresa IN (" . $tipoDocsEmpresa . ")";
            
        }
        
        $sql .= " GROUP BY e.Serie, e.Numero, CONVERT(varchar(10), CONVERT(date, e.FechaHora, 103)), e.DocEmpresa, e.TipoDocEmpresa, e.IdCategoria, e.Estado) adc
            INNER JOIN Vehiculos v ON adc.Serie = v.Serie AND adc.Numero = v.Numero
            INNER JOIN MarcasVehiculos mv ON mv.IdMarcaVehic = v.IdMarcaVehic
            INNER JOIN TiposVehiculos tv ON tv.IdTipoVehiculo = v.IdTipoVehiculo
            LEFT JOIN Empresas em ON adc.DocEmpresa = em.Documento AND adc.TipoDocEmpresa = em.IdTipoDocumento
            INNER JOIN Categorias c ON c.IdCategoria = adc.IdCategoria";
        
        // ORDER

        $sql .= ' ORDER BY Fecha DESC';

        return DB::select($sql, $binding);
    }
    
    public function sqlListAsistencias($Args) {
        
        $sql = "SELECT DISTINCT 
                    E.documento, 
                    E.idTipoDocumento, 
                    PF.primerNombre, 
                    PF.segundoNombre, 
                    PF.primerApellido, 
                    PF.segundoApellido, 
                    EMP.nombre AS empresa, 
                    P.idCategoria,
                    E.TipoOperacion
                FROM Eventos E 
                INNER JOIN PersonasFisicas PF ON E.documento = PF.documento AND E.idTipoDocumento = PF.idTipoDocumento
                INNER JOIN TiposDocumento td ON td.IdTipoDocumento = pf.IdTipoDocumento
                INNER JOIN Personas P ON PF.documento = P.documento AND PF.idTipoDocumento = P.idTipoDocumento
                LEFT JOIN Empresas EMP ON e.docEmpresa = EMP.documento AND e.tipoDocEmpresa = EMP.idTipoDocumento
                LEFT JOIN EventosContratos EC ON E.idEquipo = EC.idEquipo AND E.fechaHora = EC.fechaHora AND E.matricula = EC.matricula 
                WHERE E.estado = 1 AND CONVERT(date, e.FechaHora, 103) >= CONVERT(date, '" . $Args['FechaDesde'] . " 00:00:00', 103)
                AND CONVERT(date, e.FechaHora, 103) <= CONVERT(date, '" . $Args['FechaHasta'] . " 23:59:59', 103)
                AND E.entidad = 'P'
                AND PF.Transito = 0";

        if (!empty($Args['Busqueda'])) {

            $sql .= " AND (pf.Documento COLLATE Latin1_general_CI_AI LIKE '%" . $Args['Busqueda'] . "%' COLLATE Latin1_general_CI_AI OR "
                        . "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(PF.Documento, '_', ''), '-', ''), ';', ''), ',', ''), ':', ''), '.', '') COLLATE Latin1_general_CI_AI LIKE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE('%" . $Args['Busqueda'] . "%', '_', ''), '-', ''), ';', ''), ',', ''), ':', ''), '.', '') COLLATE Latin1_general_CI_AI OR "
                        . "pf.NombreCompleto COLLATE Latin1_general_CI_AI LIKE '%" . $Args['Busqueda'] . "%' COLLATE Latin1_general_CI_AI OR "
                        . "CONVERT(varchar(18), pf.matricula) COLLATE Latin1_general_CI_AI LIKE '%" . $Args['Busqueda'] . "%' COLLATE Latin1_general_CI_AI)";
        }
        
        if (!empty($Args['IdCategoria'])) {
            $sql .= " AND p.IdCategoria IN (" . implode(", ", $Args['IdCategoria']) . ")";
        }
        
        if (!empty($Args['IdEmpresa'])) {
            $IdEmpresaObj = FsUtils::explodeId($Args['IdEmpresa']);
            $sql .= " AND e.DocEmpresa = '" . $IdEmpresaObj[0] . "' AND e.TipoDocEmpresa = " . $IdEmpresaObj[1];
        }

        if (!empty($Args['NroContrato'])) {
            $sql .= " AND ec.NroContrato = '" . $Args['NroContrato']. "'";
        }

        // if (empty($Args['Accesos'])) {
        //     $Args['Accesos'] = [];
        //     $accesos = Acceso::where('Baja', 0)->get();
        //     foreach($accesos as $acceso){
        //         array_push($Args['Accesos'], $acceso['IdAcceso']);
        //     }
        // }

        if (!empty($Args['Accesos'])) {
            $sql .= " AND E.IdAcceso IN (" . implode(", ", $Args['Accesos']) . ")";
        }

        $resp = DB::statement("{call spAsistencia3 (?, ?, ?, ?, ?)}", array(
                    $Args['FechaDesde'] . " 00:00:00", 
                    $Args['FechaHasta'] . " 23:59:59", 
                    $sql,
                    implode(", ", $Args['Accesos']),
                    ''));
        
        if ($resp) {
            return true;
        }

        throw new HttpException(409, "Ocurrió un error al obtener el listado de asistencias");

    }

}
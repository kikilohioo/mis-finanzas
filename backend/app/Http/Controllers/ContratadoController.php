<?php

namespace App\Http\Controllers;

use App\FsUtils;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContratadoController extends Controller
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
        $data = [];

        switch (@$this->req->input('Funcion')) {
            case 'Pendientes':
                $sql = "SELECT
                        'func=AdmPersonas|Documento=' + pft.Documento + '|IdTipoDocumento=' + LTRIM(RTRIM(STR(pft.IdTipoDocumento))) AS ObjUrl,
                        'Personas' AS Tipo,
                        CASE pft.Completada
                            WHEN 0 THEN CASE pft.Accion 
                                WHEN 'A' THEN 'pending' 
                                WHEN 'M' THEN 'mod-pending' 
                            END
                            WHEN 2 THEN 'rejected'
                        END AS FsRC,
                        CASE pft.Estado
                            WHEN 1 THEN 'Activo'
                            ELSE CASE pft.Accion
                                WHEN 'A' THEN 'Pendiente'
                                WHEN 'M' THEN 'Inactivo'
                            END
                        END AS Estado,
                        e.Nombre AS Empresa,
                        dbo.Mask(pft.Documento, td.Mascara, 1, 1) AS Identificacion,
                        pft.NombreCompleto AS Detalle,
                        pft.Matricula
                    FROM PersonasFisicasTransac pft
                    INNER JOIN TiposDocumento td ON (pft.IdTipoDocumento = td.IdTipoDocumento)
                    LEFT JOIN PersonasFisicasTransacEmpresas pfte ON (pft.Documento = pfte.Documento AND pft.IdTipoDocumento = pfte.IdTipoDocumento)
                    LEFT JOIN Empresas e ON (pfte.DocEmpresa = e.Documento AND pfte.TipoDocEmpresa = e.IdTipoDocumento)
                    WHERE pft.Transito = 0 
                    AND pft.Completada = 0

                    UNION ALL
                    
                    SELECT
                        'func=VISAprobar|id=' + CONVERT(char(36), vsp.Id) AS ObjUrl,
                        'Visitas & Excepciones' AS Tipo,
                        CASE vsp.Estado
                            WHEN 'Z' THEN 'pending'
                            WHEN 'O' THEN 'mod-pending'
                            WHEN 'P' THEN 'mod-pending'
                            ELSE 'rejected'
                        END AS FsRC,
                        CASE vsp.Estado
                            WHEN 'A' THEN 'Activo'
                            WHEN 'Z' THEN 'Pendiente'
                            WHEN 'O' THEN 'Web'
                            WHEN 'P' THEN 'Presencial'
                        END AS Estado,
                        vs.EmpresaVisitante AS Empresa,
                        vsp.Documento AS Identificacion,
                        CONCAT(Nombres, ' ', Apellidos) AS Detalle,
                        NULL AS Matricula
                    FROM Visitas_SolicitudesPersonas vsp
                    INNER JOIN Visitas_Solicitudes vs ON vs.Id = vsp.IdSolicitud
                    WHERE vsp.Estado IN ('Z') AND vs.FechaHoraHasta > GETDATE()

                    UNION ALL

                    SELECT
                        'func=VISAprobar|id=' + CONVERT(char(36), vsp.Id) AS ObjUrl,
                        'Visitas & Excepciones notificados' AS Tipo,
                        CASE vsp.Estado
                            WHEN 'Z' THEN 'pending'
                            WHEN 'O' THEN 'mod-pending'
                            WHEN 'P' THEN 'mod-pending'
                            ELSE 'rejected'
                        END AS FsRC,
                        CASE vsp.Estado
                            WHEN 'A' THEN 'Activo'
                            WHEN 'Z' THEN 'Pendiente'
                            WHEN 'O' THEN 'Web'
                            WHEN 'P' THEN 'Presencial'
                        END AS Estado,
                        vs.EmpresaVisitante AS Empresa,
                        vsp.Documento AS Identificacion,
                        CONCAT(Nombres, ' ', Apellidos) AS Detalle,
                        NULL AS Matricula
                    FROM Visitas_SolicitudesPersonas vsp
                    INNER JOIN Visitas_Solicitudes vs ON vs.Id = vsp.IdSolicitud
                    WHERE vsp.Estado IN ('O', 'P') AND vs.FechaHoraHasta > GETDATE()
                    
                    UNION ALL

                    SELECT
                        'func=AdmMaquinas|NroSerie=' + mt.NroSerie AS ObjUrl,
                        'Máquinas' AS Tipo,
                        CASE mt.Completada
                            WHEN 0 THEN CASE mt.Accion 
                                WHEN 'A' THEN 'pending' 
                                WHEN 'M' THEN 'mod-pending' 
                            END
                            WHEN 2 THEN 'rejected'
                        END AS FsRC,
                        CASE mt.Estado
                            WHEN 1 THEN 'Activo'
                            ELSE CASE mt.Accion
                                WHEN 'A' THEN 'Pendiente'
                                WHEN 'M' THEN 'Inactivo'
                            END
                        END AS Estado,
                        e.Nombre AS Empresa,
                        mt.NroSerie AS Identificacion,
                        mm.Descripcion + ' ' + mt.Modelo + ' (' + tm.Descripcion + ')' AS Detalle,
                        mt.Matricula
                    FROM MaquinasTransac mt
                    INNER JOIN MarcasMaquinas mm ON mm.IdMarcaMaq = mt.IdMarcaMaq
                    INNER JOIN TiposMaquinas tm ON tm.IdTipoMaquina = mt.IdTipoMaq
                    LEFT JOIN Empresas e ON (mt.DocEmpresa = e.Documento AND mt.TipoDocEmp = e.IdTipoDocumento)
                    WHERE mt.Completada = 0

                    UNION ALL

                    SELECT
                        'func=AdmVehiculos|Serie=' + vt.Serie + '|Numero=' + LTRIM(RTRIM(STR(vt.Numero))) AS ObjUrl,      
                        'Vehículos' AS Tipo,                   
                        CASE vt.Completada
                            WHEN 0 THEN CASE vt.Accion 
                                WHEN 'A' THEN 'pending' 
                                WHEN 'M' THEN 'mod-pending' 
                            END
                            WHEN 2 THEN 'rejected'
                        END AS FsRC,
                        CASE vt.Estado
                            WHEN 1 THEN 'Activo'
                            ELSE CASE vt.Accion
                                WHEN 'A' THEN 'Pendiente'
                                WHEN 'M' THEN 'Inactivo'
                            END
                        END AS Estado,
                        e.Nombre AS Empresa,
                        vt.Serie + ' ' + RTRIM(LTRIM(STR(vt.Numero))) AS Identificacion,
                        mv.Descripcion + ' ' + vt.Modelo + ' (' + tv.Descripcion + ')' AS Detalle,
                        vt.Matricula
                    FROM VehiculosTransac vt
                    INNER JOIN MarcasVehiculos mv ON mv.IdMarcaVehic = vt.IdMarcaVehic
                    INNER JOIN TiposVehiculos tv ON tv.IdTipoVehiculo = vt.IdTipoVehiculo
                    LEFT JOIN Empresas e ON (vt.DocEmpresa = e.Documento AND vt.TipoDocEmp = e.IdTipoDocumento)
                    WHERE vt.Completada = 0

                    ORDER BY Detalle";
                $data = DB::select($sql);
                break;
        }

        return $this->responsePaginate($data);
    }

    public function busqueda()
    {
        $data = [];
        $headers = [];

        $ArrayArgs = $this->req->all();
        
        //Aca se va a cargar el valor de la empresa propia si no es gestion.
        $IdEmpresaObj = [];
       
        if ($ArrayArgs['Funcion'] == "ConsAltasBajas") {
            $data = $this->busquedaaltasbajas($IdEmpresaObj, $ArrayArgs);
            $headers = [];
        } else if ($ArrayArgs['Funcion'] == 'ConsCambiosMat') {
            $data = $this->busquedacambiosmat($IdEmpresaObj, $ArrayArgs);
            $headers = [
                'FechaHora' => 'Fecha/Hora',
                'Entidad' => 'Entidad',
                'Identificacion' => 'Identificación',
                'Detalle' => 'Detalle',
                'Matricula' => 'Matrícula',
                'MatriculaAnt' => 'Matricula Anterior',
                'IdUsuario' => 'Usuario',
                'Observaciones' => 'Observaciones'
            ];
            /**
             * @todo
             */
            /*$list = self::listado($Usuario, $IdEmpresa, $sql);
            for ($i = 0; $i < count($list); $i++) {
                if (!empty($list[$i]->LogObjAnt)) {
                    $LogObj = json_decode($list[$i]->LogObjAnt);
                    $list[$i]->MatriculaAnt = $LogObj->Matricula;
                }
            }
            return fs_paged_array($list, $MaxFilas, $NroPagina);*/
        } else if ($ArrayArgs['Funcion'] == 'ConsHabilitados') {
            $data = $this->busquedahabilitados($IdEmpresaObj, $ArrayArgs);
            $headers = [
                'Entidad' => 'Entidad',
                'Identificacion' => 'Identificación',
                'Detalle' => 'Detalle',
                'Matricula' => 'Matrícula',
                'Empresa' => 'Empresa',
                'Contrato' => 'Contrato',
                'NroContrato' => 'NroContrato',
            ];
        } else if ($ArrayArgs['Funcion'] == 'ConsIncidencias') {
            $data = $this->busquedaincidencias($IdEmpresaObj, $ArrayArgs);

            $headers = [
                'Entidad' => 'Entidad',
                'Identificacion' => 'Identificación',
                'Detalle' => 'Detalle',
                'Matricula' => 'Matrícula',
                'Empresa' => 'Empresa',
                'Contrato' => 'Contrato',
                'Fecha' => 'Fecha',
                'Observaciones' => 'Observaciones'
            ];
        } else if ($ArrayArgs['Funcion'] == 'ConsInducciones') {
            $data = $this->busquedainducciones($IdEmpresaObj, $ArrayArgs);
        } else if ($ArrayArgs['Funcion'] == "ContratosAltasBajas") {
            $data = $this->busquedacontratosaltasbajas($IdEmpresaObj, $ArrayArgs);
            $orderBy = "FechaHora DESC";
        } else if ($ArrayArgs['Funcion'] == "SelectEntidad") {
            return $this->busquedaselectentidad($IdEmpresaObj, $ArrayArgs);
        } else if ($ArrayArgs['Funcion'] == "Arbol") {
            $data = $this->busquedaarbol($IdEmpresaObj, $ArrayArgs);
        }
        
        $output = $this->req->input('output');
        if ($output !== 'json' && $output !== null) {
            
            $filename = 'FSAcceso-Cambio-de-Matriculas-Consulta-' . date('Ymd his');

            return FsUtils::export($output,$data,$headers,$filename);
        }

        return $this->responsePaginate($data);
    }

    public function busquedaarbol($IdEmpresaObj, $Args)
    {
        $sql = "";
        $binding = [];
        $UsuarioEsGestion = $this->user->isGestion();

        // MAQUINAS
        $emp = 'm';

        $sql .= "SELECT DISTINCT 'func=AdmMaquinas|NroSerie=' + m.NroSerie AS ObjUrl,
                        'Maquina' AS Entidad,
                        m.NroSerie AS Identificacion,
                        mm.Descripcion + ' ' + m.Modelo + ' (' + tm.Descripcion + ')' AS Detalle,
                        m.Matricula,
                        e.Nombre AS Empresa,
                        mc.NroContrato + ' (' + e.Nombre + ')' AS Contrato,
                        mc.NroContrato
                FROM Maquinas m
                INNER JOIN MarcasMaquinas mm ON mm.IdMarcaMaq = m.IdMarcaMaq
                INNER JOIN TiposMaquinas tm ON tm.IdTipoMaquina = m.IdTipoMaq
                LEFT JOIN Empresas e ON e.Documento = m.DocEmpresa AND e.IdTipoDocumento = m.TipoDocEmp
                LEFT JOIN MaquinasContratos mc ON mc.DocEmpCont = e.Documento AND mc.IdTipoDocCont = e.IdTipoDocumento";
        
        if (!empty($Args['IdAcceso'])) {
            $sql .= " INNER JOIN MaquinasAccesos ma ON ma.NroSerie = m.NroSerie AND ma.IdAcceso = :IdAcceso";
            $binding[':IdAcceso'] = $Args['IdAcceso'];
        }

        $sql .= " WHERE m.Baja = 0 AND m.Estado = 1 AND NOT m.Matricula IS NULL";

        if (!empty($Args['MostrarInactivos'])) {
            $sql .= " AND e.Estado = 1";
        }

        /**
         * @todo
         */
        /*if (empty($UsuarioEsGestion)) {
            $sql .= " AND e.Documento = :IdEmpresaObj AND e.IdTipoDocumento = :IdEmpresaObj1";
            $binding[':IdEmpresaObj'] = $IdEmpresaObj[0];
            $binding[':IdEmpresaObj1'] = $IdEmpresaObj[1];
        }*/
        
        if (!empty($Args['NroContrato'])) {
            $sql .= " AND mc.NroContrato LIKE :NroContrato";
            $binding[':NroContrato'] = "%" . $Args['NroContrato'] . "%";
        } else if (!empty($Args['ConNroContrato'])) {
            $sql .= " AND mc.NroContrato IS NOT NULL AND LEN(mc.NroContrato) > 0";
        }
        
        /**
         * @todo
         */
        //$sql .= self::busquedaWhereFromArgs($Args, 'MAQ', $emp);

        // PERSONAS FÍSICAS
        $emp = 'pfe';
        $empctr = 'pfc';
        $sql .= ' UNION ALL ';

        $sql .= "SELECT DISTINCT 'func=AdmPersonas|Documento=' + pf.Documento + '|IdTipoDocumento=' + LTRIM(RTRIM(STR(pf.IdTipoDocumento))) AS ObjUrl,
                        'Persona' AS Entidad,
                        dbo.Mask(pf.Documento, td.Mascara, 1, 1) AS Identificacion,
                        pf.PrimerNombre + ' ' + pf.SegundoNombre + ' ' + pf.PrimerApellido + ' ' + pf.SegundoApellido AS Detalle,
                        pf.Matricula,
                        e.Nombre AS Empresa,
                        pfc.NroContrato + ' (' + pfce.Nombre + ')' AS Contrato,
                        pfc.NroContrato
                FROM PersonasFisicas pf
                INNER JOIN Personas p ON p.Documento = pf.Documento AND p.IdTipoDocumento = pf.IdTipoDocumento
                INNER JOIN TiposDocumento td ON td.IdTipoDocumento = pf.IdTipoDocumento
                LEFT JOIN PersonasFisicasEmpresas pfe ON pfe.Documento = pf.Documento AND pfe.IdTipoDocumento = pf.IdTipoDocumento AND pfe.FechaBaja IS NULL
                LEFT JOIN Empresas e ON e.Documento = pfe.DocEmpresa AND e.IdTipoDocumento = pfe.TipoDocEmpresa
                LEFT JOIN PersonasFisicasContratos pfc ON pfc.Documento = pf.Documento AND pfc.IdTipoDocumento = pf.IdTipoDocumento AND pfc.DocEmpresa = pfe.DocEmpresa AND pfc.TipoDocEmpresa = pfe.TipoDocEmpresa
                LEFT JOIN Empresas pfce ON pfce.Documento = pfc.DocEmpCont AND pfce.IdTipoDocumento = pfc.IdTipoDocCont";
        
        if (!empty($Args['IdAcceso'])) {
            $sql .= " INNER JOIN PersonasFisicasAccesos pfa ON pfa.Documento = pf.Documento AND pfa.IdTipoDocumento = pf.IdTipoDocumento AND pfa.IdAcceso = :IdAcceso1";
            $binding[':IdAcceso1'] = $Args['IdAcceso'];
        }

        $sql .= " WHERE p.Baja = 0 AND pf.Estado = 1 AND NOT pf.Matricula IS NULL";

        if (!empty($Args['MostrarInactivos'])) {
            $sql .= " AND e.Estado = 1";
        }

        /*if (empty($UsuarioEsGestion)) {
            $sql .= " AND e.Documento = :IdEmpresaObj2 AND e.IdTipoDocumento = :IdEmpresaObj3";
            $binding[':IdEmpresaObj2'] = $IdEmpresaObj[0];
            $binding[':IdEmpresaObj3'] = $IdEmpresaObj[1];
        }*/
        
        if (!empty($Args['NroContrato'])) {
            $sql .= " AND pfc.NroContrato LIKE :NroContrato2";
            $binding[':NroContrato2'] = "%" . $Args['NroContrato'] . "%";
        } else if (!empty($Args['ConNroContrato'])) {
            $sql .= " AND pfc.NroContrato IS NOT NULL AND LEN(pfc.NroContrato) > 0";
        }
        
        /**
         * @todo
         */
        //$sql .= self::busquedaWhereFromArgs($Args, 'PF', $emp, $empctr);

        // VEHÍCULOS
        $emp = 'v';
        $sql .= ' UNION ALL ';

        $sql .= "SELECT DISTINCT 'func=AdmVehiculos|Serie=' + v.Serie + '|Numero=' + LTRIM(RTRIM(STR(v.Numero))) AS ObjUrl,                         
                        'Vehículo' AS Entidad,
                        v.Serie + ' ' + RTRIM(LTRIM(STR(v.Numero))) AS Identificacion,
                        mv.Descripcion + ' ' + v.Modelo + ' (' + tv.Descripcion + ')' AS Detalle,
                        v.Matricula,
                        e.Nombre AS Empresa,
                        vc.NroContrato + ' (' + e.Nombre + ')' AS Contrato,
                        vc.NroContrato
                FROM Vehiculos v
                INNER JOIN MarcasVehiculos mv ON mv.IdMarcaVehic = v.IdMarcaVehic
                INNER JOIN TiposVehiculos tv ON tv.IdTipoVehiculo = v.IdTipoVehiculo
                LEFT JOIN Empresas e ON e.Documento = v.DocEmpresa AND e.IdTipoDocumento = v.TipoDocEmp
                LEFT JOIN VehiculosContratos vc ON vc.DocEmpCont = e.Documento AND vc.IdTipoDocCont = e.IdTipoDocumento";
        
        if (!empty($Args['IdAcceso'])) {
            $sql .= " INNER JOIN VehiculosAccesos va ON va.Serie = v.Serie AND va.Numero = v.Numero AND va.IdAcceso = :IdAcceso2";
            $binding[':IdAcceso2'] = $Args['IdAcceso'];
        }

        $sql .= " WHERE v.Baja = 0 AND v.Estado = 1 AND NOT v.Matricula IS NULL";

        if (!empty($Args['MostrarInactivos'])) {
            $sql .= " AND e.Estado = 1";
        }

        /**
         * @todo 
         * */
        /*if (empty($UsuarioEsGestion)) {
            $sql .= " AND e.Documento = :IdEmpresaObj4 AND e.IdTipoDocumento = :IdEmpresaObj5";
            $binding[':IdEmpresaObj4'] = $IdEmpresaObj[0];
            $binding[':IdEmpresaObj5'] = $IdEmpresaObj[1];
        }*/
        
        if (!empty($Args['NroContrato'])) {
            $sql .= " AND vc.NroContrato LIKE :NroContrato3";
            $binding[':NroContrato3'] = "%" . $Args['NroContrato'] . "%";
        } else if (!empty($Args['ConNroContrato'])) {
            $sql .= " AND vc.NroContrato IS NOT NULL AND LEN(vc.NroContrato) > 0";
        }
        
        /**
         * @todo
         */
        //$sql .= self::busquedaWhereFromArgs($Args, 'VEH', $emp);

        $data = DB::select($sql, $binding);

        return $data;
    }

    private function busquedaselectentidad($IdEmpresaObj, $Args)
    {
        
        $binding = [];

        $sql = "SELECT ";
        
        switch ($Args['Entidad']) {
            case "PF":
                $sql .= "t.Documento + '-' + LTRIM(RTRIM(STR(t.IdTipoDocumento))) AS Id, "
                      . "dbo.Mask(t.Documento, td.Mascara, 1, 1) AS Identificacion, "
                      . "pf.NombreCompleto AS Detalle, "
                      . "c.Descripcion AS Categoria, "
                      . "e.Nombre AS Empresa, "
                      . "pf.Matricula "
                      . "FROM Personas t "
                      . "INNER JOIN PersonasFisicas pf ON t.Documento = pf.Documento AND t.IdTipoDocumento = pf.IdTipoDocumento "
                      . "INNER JOIN TiposDocumento td ON pf.IdTipoDocumento = td.IdTipoDocumento "
                      . "INNER JOIN Categorias c ON t.IdCategoria = c.IdCategoria "
                      . "LEFT JOIN PersonasFisicasEmpresas pfe ON t.Documento = pfe.Documento AND t.IdTipoDocumento = pfe.IdTipoDocumento AND pfe.FechaAlta < GETDATE() AND (pfe.FechaBaja IS NULL OR pfe.FechaBaja > GETDATE()) "
                      . "LEFT JOIN Empresas e ON e.Documento = pfe.DocEmpresa AND e.IdTipoDocumento = pfe.TipoDocEmpresa "
                      . "WHERE t.Baja = 0 "
                      . "AND pf.Transito = 0 ";
                
                if (!empty($Args['Busqueda'])) {
                    $sql .= " AND (pf.Documento COLLATE Latin1_general_CI_AI LIKE :Busqueda COLLATE Latin1_general_CI_AI OR "
                                . "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(pf.Documento, '_', ''), '-', ''), ';', ''), ',', ''), ':', ''), '.', '') COLLATE Latin1_general_CI_AI LIKE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(:Busqueda3, '_', ''), '-', ''), ';', ''), ',', ''), ':', ''), '.', '') COLLATE Latin1_general_CI_AI OR "
                                . "pf.NombreCompleto COLLATE Latin1_general_CI_AI LIKE :Busqueda2 COLLATE Latin1_general_CI_AI OR "
                                . "CONVERT(varchar(18), pf.matricula) COLLATE Latin1_general_CI_AI LIKE :Busqueda4 COLLATE Latin1_general_CI_AI)";
                    
                    $binding[':Busqueda'] = "%" . $Args['Busqueda'] . "%";
                    $binding[':Busqueda2'] = "%" . $Args['Busqueda'] . "%";
                    $binding[':Busqueda3'] = "%" . $Args['Busqueda'] . "%";
                    $binding[':Busqueda4'] = "%" . $Args['Busqueda'] . "%";
                    
                }
                break;
            case "MAQ":
                $sql .= "t.NroSerie AS Id, "
                      . "t.NroSerie AS Identificacion, "
                      . "mm.Descripcion + ' ' + t.Modelo + ' (' + tm.Descripcion + ')' AS Detalle, "
                      . "c.Descripcion AS Categoria, "
                      . "e.Nombre AS Empresa, "
                      . "t.Matricula "
                      . "FROM Maquinas t "
                      . "INNER JOIN TiposMaquinas TM ON t.idTipoMaq = TM.idTipoMaquina "
                      . "INNER JOIN MarcasMaquinas MM ON t.idMarcaMaq = MM.idMarcaMaq "
                      . "INNER JOIN Categorias c ON t.IdCategoria = c.IdCategoria "
                      . "LEFT JOIN Empresas e ON e.Documento = t.DocEmpresa AND e.IdTipoDocumento = t.TipoDocEmp "
                      . "WHERE t.Baja = 0 ";
                    
                if (!empty($Args['Busqueda'])) {
                    $sql .= " AND (t.NroSerie COLLATE Latin1_general_CI_AI LIKE :Busqueda5 COLLATE Latin1_general_CI_AI OR "
                                . "tm.Descripcion COLLATE Latin1_general_CI_AI LIKE :Busqueda6 COLLATE Latin1_general_CI_AI OR "
                                . "mm.Descripcion COLLATE Latin1_general_CI_AI LIKE :Busqueda7 COLLATE Latin1_general_CI_AI OR "
                                . "t.Propietario COLLATE Latin1_general_CI_AI LIKE :Busqueda8 COLLATE Latin1_general_CI_AI OR "
                                . "t.Conductor COLLATE Latin1_general_CI_AI LIKE :Busqueda9 COLLATE Latin1_general_CI_AI OR "
                                . "CONVERT(varchar(18), t.matricula) COLLATE Latin1_general_CI_AI LIKE :Busqueda10 COLLATE Latin1_general_CI_AI)";
                    
                    $binding[':Busqueda5'] = "%" . $Args['Busqueda'] . "%";
                    $binding[':Busqueda6'] = "%" . $Args['Busqueda'] . "%";
                    $binding[':Busqueda7'] = "%" . $Args['Busqueda'] . "%";
                    $binding[':Busqueda8'] = "%" . $Args['Busqueda'] . "%";
                    $binding[':Busqueda9'] = "%" . $Args['Busqueda'] . "%";
                    $binding[':Busqueda10'] = "%" . $Args['Busqueda'] . "%";
                }
                break;
            case "VEH":
                $sql .= "t.Serie + '-' + RTRIM(LTRIM(STR(t.Numero))) AS Id, "
                      . "t.Serie + ' ' + RTRIM(LTRIM(STR(t.Numero))) AS Identificacion, "
                      . "mv.Descripcion + ' ' + t.Modelo + ' (' + tv.Descripcion + ')' AS Detalle, "
                      . "c.Descripcion AS Categoria, "
                      . "e.Nombre AS Empresa, "
                      . "t.Matricula "
                      . "FROM Vehiculos t "
                      . "INNER JOIN TiposVehiculos Tv ON t.idTipoVehiculo = Tv.idTipoVehiculo "
                      . "INNER JOIN MarcasVehiculos Mv ON t.idMarcaVehic = Mv.idMarcaVehic "
                      . "INNER JOIN Categorias c ON t.IdCategoria = c.IdCategoria "
                      . "LEFT JOIN Empresas e ON e.Documento = t.DocEmpresa AND e.IdTipoDocumento = t.TipoDocEmp "
                      . "WHERE t.Baja = 0 ";
                  
                if (!empty($Args['Busqueda'])) {
                    $sql .= " AND (t.Serie COLLATE Latin1_general_CI_AI LIKE :Busqueda11 COLLATE Latin1_general_CI_AI OR "
                                . "STR(t.Numero) COLLATE Latin1_general_CI_AI LIKE :Busqueda12 COLLATE Latin1_general_CI_AI OR "
                                . "tv.Descripcion COLLATE Latin1_general_CI_AI LIKE :Busqueda13 COLLATE Latin1_general_CI_AI OR "
                                . "mv.Descripcion COLLATE Latin1_general_CI_AI LIKE :Busqueda14 COLLATE Latin1_general_CI_AI OR "
                                . "t.Modelo COLLATE Latin1_general_CI_AI LIKE :Busqueda15 COLLATE Latin1_general_CI_AI OR "
                                . "CONVERT(varchar(18), t.matricula) COLLATE Latin1_general_CI_AI LIKE :Busqueda16 COLLATE Latin1_general_CI_AI)";
                    $binding[':Busqueda11'] = "%" . $Args['Busqueda'] . "%";
                    $binding[':Busqueda12'] = "%" . $Args['Busqueda'] . "%";
                    $binding[':Busqueda13'] = "%" . $Args['Busqueda'] . "%";
                    $binding[':Busqueda14'] = "%" . $Args['Busqueda'] . "%";
                    $binding[':Busqueda15'] = "%" . $Args['Busqueda'] . "%";
                    $binding[':Busqueda16'] = "%" . $Args['Busqueda'] . "%";
                }
                break;
        }
        
        if (!empty($Args['IdCategoria'])) {
            $sql .= " AND t.IdCategoria = :IdCategoria";
            $binding[':IdCategoria'] = $Args['IdCategoria'];
        }

        if (!empty($Args['IdEmpresa'])) {
            $ArgsIdEmpresaObj = FsUtils::explodeId($Args['IdEmpresa']);
            $sql .= " AND e.Documento = :IdEmpresaObj AND e.IdTipoDocumento = :IdEmpresaObj1";
            $binding[':IdEmpresaObj'] = $ArgsIdEmpresaObj[0];
            $binding[':IdEmpresaObj1'] = $ArgsIdEmpresaObj[1];
        }
        
        //return self::listado($Usuario, implode("-", $IdEmpresaObj), $sql);

        $data = [];

        if(!empty($sql)){
            $data = DB::select($sql, $binding);
        }

        return $data;
    }

    private function busquedainducciones($IdEmpresaObj, $Args)
    {
        $sql = "";
        
        $binding = [];

        $UsuarioEsGestion = $this->user->isGestion();
        //
        // MAQUINAS
        //
        
        $ArgsIdEmpresaObj = [];
        if(!empty($Args['IdEmpresa'])){
            $ArgsIdEmpresaObj = FsUtils::explodeId($Args['IdEmpresa']);
        }

        $EmpresaBaja = $Args['EmpresaBaja'] == "0" ? " AND e.Estado = 1" : "";

        if (!isset($Args['Entidad']) || $Args['Entidad'] == 'MAQ') {
            $emp = "m";
            $empctr = "mc";

            $sql .= "SELECT 'func=AdmMaquinas|NroSerie=' + m.NroSerie AS ObjUrl,
                            'Maquina' AS Entidad,
                            m.NroSerie AS Identificacion,
                            mm.Descripcion + ' ' + m.Modelo + ' (' + tm.Descripcion + ')' AS Detalle,
                            m.Matricula,
                            e.Nombre AS Empresa,
                            mc.NroContrato + ' (' + e.Nombre + ')' AS Contrato,
                            mi.Fecha,
                            mi.Observaciones
                    FROM MaquinasIncidencias mi
                    INNER JOIN Maquinas m ON m.NroSerie = mi.NroSerie
                    INNER JOIN MarcasMaquinas mm ON mm.IdMarcaMaq = m.IdMarcaMaq
                    INNER JOIN TiposMaquinas tm ON tm.IdTipoMaquina = m.IdTipoMaq
                    LEFT JOIN Empresas e ON e.Documento = m.DocEmpresa AND e.IdTipoDocumento = m.TipoDocEmp
                    LEFT JOIN MaquinasContratos mc ON mc.NroSerie = m.NroSerie AND mc.DocEmpresa = m.DocEmpresa AND mc.TipoDocEmpresa = m.TipoDocEmp
                    LEFT JOIN Empresas mce ON mce.Documento = mc.DocEmpCont AND mce.IdTipoDocumento = mc.IdTipoDocCont
                    WHERE m.Baja = 0" . $EmpresaBaja;
            /**
             * @todo
             */
            /*if (empty($UsuarioEsGestion)){
                $sql .= " AND e.Documento = ':IdEmpresaObj1 AND e.IdTipoDocumento = IdEmpresaObj2";
                $binding[':IdEmpresaObj1'] = $IdEmpresaObj[0];
                $binding[':IdEmpresaObj2'] = $IdEmpresaObj[1];
            }*/
            
            //$sql .= self::busquedaWhereFromArgs($Args, 'MAQ', $emp, $empctr);
        }

        //
        // PERSONAS FÍSICAS
        //
        
        if (!isset($Args['Entidad']) || $Args['Entidad'] == 'PF') {
            $emp = 'pfe';
            $empctr = 'pfc';

            if (!empty($sql))
                $sql .= ' UNION ALL ';

            $sql .= "SELECT 'func=AdmPersonas|Documento=' + pf.Documento + '|IdTipoDocumento=' + LTRIM(RTRIM(STR(pf.IdTipoDocumento))) AS ObjUrl,
                            'Persona' AS Entidad,
                            dbo.Mask(pf.Documento, td.Mascara, 1, 1) AS Identificacion,
                            pf.PrimerNombre + ' ' + pf.SegundoNombre + ' ' + pf.PrimerApellido + ' ' + pf.SegundoApellido AS Detalle,
                            pf.Matricula,
                            e.Nombre AS Empresa,
                            pfc.NroContrato + ' (' + pfce.Nombre + ')' AS Contrato,
                            pfi.Fecha,
                            pfi.Observaciones
                    FROM PersonasFisicasIncidencias pfi
                    INNER JOIN PersonasFisicas pf ON pf.Documento = pfi.Documento AND pf.IdTipoDocumento = pfi.IdTipoDocumento
                    INNER JOIN Personas p ON p.Documento = pf.Documento AND p.IdTipoDocumento = pf.IdTipoDocumento
                    INNER JOIN TiposDocumento td ON td.IdTipoDocumento = pf.IdTipoDocumento
                    LEFT JOIN PersonasFisicasEmpresas pfe ON pfe.Documento = pf.Documento AND pfe.IdTipoDocumento = pf.IdTipoDocumento AND pfe.FechaBaja IS NULL
                    LEFT JOIN Empresas e ON e.Documento = pfe.DocEmpresa AND e.IdTipoDocumento = pfe.TipoDocEmpresa
                    LEFT JOIN PersonasFisicasContratos pfc ON pfc.Documento = pf.Documento AND pfc.IdTipoDocumento = pf.IdTipoDocumento AND pfc.DocEmpresa = pfe.DocEmpresa AND pfc.TipoDocEmpresa = pfe.TipoDocEmpresa
                    LEFT JOIN Empresas pfce ON pfce.Documento = pfc.DocEmpCont AND pfce.IdTipoDocumento = pfc.IdTipoDocCont
                    WHERE p.Baja = 0" . $EmpresaBaja;

            /**
             * @todo
             */
            /*if (empty($UsuarioEsGestion)){
                $sql .= " AND e.Documento = ':IdEmpresaObj3 AND e.IdTipoDocumento = IdEmpresaObj4";
                $binding[':IdEmpresaObj3'] = $IdEmpresaObj[0];
                $binding[':IdEmpresaObj4'] = $IdEmpresaObj[1];
            }*/

            //$sql .= self::busquedaWhereFromArgs($Args, 'PF', $emp, $empctr);
            
            if (!empty($Args['IdEmpresa'])){
                $sql .= " AND " . $emp . ".DocEmpresa = :IdEmpresaObj5 AND " . $emp . ".TipoDocEmpresa = :IdEmpresaObj6";
                $binding[':IdEmpresaObj5'] = $ArgsIdEmpresaObj[0];
                $binding[':IdEmpresaObj6'] = $ArgsIdEmpresaObj[1];
            }
        }

        //
        // VEHÍCULOS
        //

        if (!isset($Args['Entidad']) || $Args['Entidad'] == 'VEH') {
            $emp = 'v';
            $empctr = 'vc';

            if (!empty($sql))
                $sql .= ' UNION ALL ';

            $sql .= "SELECT 'func=AdmVehiculos|Serie=' + v.Serie + '|Numero=' + LTRIM(RTRIM(STR(v.Numero))) AS ObjUrl,                         
                            'Vehículo' AS Entidad,
                            v.Serie + ' ' + RTRIM(LTRIM(STR(v.Numero))) AS Identificacion,
                            mv.Descripcion + ' ' + v.Modelo + ' (' + tv.Descripcion + ')' AS Detalle,
                            v.Matricula,
                            e.Nombre AS Empresa,
                            vc.NroContrato + ' (' + e.Nombre + ')' AS Contrato,
                            vi.Fecha,
                            vi.Observaciones
                    FROM VehiculosIncidencias vi
                    INNER JOIN Vehiculos v ON v.Serie = vi.Serie AND v.Numero = vi.Numero
                    INNER JOIN MarcasVehiculos mv ON mv.IdMarcaVehic = v.IdMarcaVehic
                    INNER JOIN TiposVehiculos tv ON tv.IdTipoVehiculo = v.IdTipoVehiculo
                    LEFT JOIN Empresas e ON e.Documento = v.DocEmpresa AND e.IdTipoDocumento = v.TipoDocEmp
                    LEFT JOIN VehiculosContratos vc ON vc.Numero = v.Numero AND vc.Serie = v.Serie AND vc.DocEmpresa = v.DocEmpresa AND vc.TipoDocEmpresa = v.TipoDocEmp
                    LEFT JOIN Empresas vce ON vce.Documento = vc.DocEmpCont AND vce.IdTipoDocumento = vc.IdTipoDocCont
                    WHERE v.Baja = 0" . $EmpresaBaja;

            /**
             * @todo
             */
            /*if (empty($UsuarioEsGestion)){
                $sql .= " AND e.Documento = ':IdEmpresaObj7 AND e.IdTipoDocumento = IdEmpresaObj8";
                $binding[':IdEmpresaObj7'] = $IdEmpresaObj[0];
                $binding[':IdEmpresaObj8'] = $IdEmpresaObj[1];
            } else */ if (!empty($Args['IdEmpresa'])){
                $sql .= " AND " . $emp . ".DocEmpresa = :IdEmpresaObj9 AND " . $emp . ".TipoDocEmp = :IdEmpresaObj10";
                $binding[':IdEmpresaObj9'] = $ArgsIdEmpresaObj[0];
                $binding[':IdEmpresaObj10'] = $ArgsIdEmpresaObj[1];
            }
            //$sql .= self::busquedaWhereFromArgs($Args, 'VEH', $emp, $empctr);
        }
        
        $data = [];
        
        if(!empty($sql)){
            $data = DB::select($sql, $binding);
        }

        return $data;
    }

    private function busquedaaltasbajas($IdEmpresaObj, $Args)
    {
        $sql = "";
        $binding = [];

        $UsuarioEsGestion = $this->user->isGestion();

        
        /**
         * @todo
         */
        //No se que valores le llegana $Args, entonces no se que codigo iria.
        //$sql .= self::busquedaWhereFromArgs

        //
        // MAQUINAS
        //

        if (!isset($Args['Entidad']) || $Args['Entidad'] == 'MAQ') {
            $emp = 'm';

            $sql .= "SELECT 'func=AdmMaquinas|NroSerie=' + m.NroSerie AS ObjUrl,
                            CONVERT(varchar(10), la.FechaHora, 103) + ' ' + CONVERT(varchar(8), la.FechaHora, 108) AS FechaHora,
                            'Maquina' AS Entidad,
                            m.NroSerie AS Identificacion,
                            mm.Descripcion + ' ' + m.Modelo + ' (' + tm.Descripcion + ')' AS Detalle,
                            m.Matricula,
                            la.IdUsuario,
                            la.Operacion,
                            e.Nombre as Empresa
                    FROM LogActividades la
                    INNER JOIN Maquinas m ON la.EntidadId = m.NroSerie
                    INNER JOIN MarcasMaquinas mm ON mm.IdMarcaMaq = m.IdMarcaMaq
                    INNER JOIN TiposMaquinas tm ON tm.IdTipoMaquina = m.IdTipoMaq
                    LEFT JOIN Empresas e ON e.Documento = m.DocEmpresa AND e.IdTipoDocumento = m.TipoDocEmp
                    WHERE 1 = 1";
            
            /**
             * @todo
             */
            /*if (empty($UsuarioEsGestion)){
                $sql .= " AND e.Documento = ':IdEmpresaObj1 AND e.IdTipoDocumento = IdEmpresaObj2";
                $binding[':IdEmpresaObj1'] = $IdEmpresaObj[0];
                $binding[':IdEmpresaObj2'] = $IdEmpresaObj[1];

            } else*/ if (!empty($Args['IdEmpresa'])) {

                $sql .= " AND e.Documento = :IdEmpresaObj3 AND e.IdTipoDocumento = :IdEmpresaObj4";
                $binding[':IdEmpresaObj3'] = $IdEmpresaObj[0];
                $binding[':IdEmpresaObj4'] = $IdEmpresaObj[1];
            }
                
            if(!empty($Args['FechaDesde'])){
                $sql .= " AND CONVERT(date, la.FechaHora, 103) >= CONVERT(date, :FechaDesde, 103)";
                $binding[':FechaDesde'] = $Args['FechaDesde']. ' 00:00:00';
            }
            
            if(!empty($Args['FechaHasta'])){
                $sql .= " AND CONVERT(date, la.FechaHora, 103) <= CONVERT(date, :FechaHasta, 103)";
                $binding[':FechaHasta'] = $Args['FechaHasta']. ' 00:00:00';
            }

            if (!empty($Args['Busqueda'])) {
                $sql .= " AND (m.NroSerie LIKE :Busqueda OR "
                            . "tm.Descripcion LIKE :Busqueda1 OR "
                            . "mm.Descripcion LIKE :Busqueda2)";
                $binding[':Busqueda'] = "%" . $Args['Busqueda'] . "%";
                $binding[':Busqueda1'] = "%" . $Args['Busqueda'] . "%";
                $binding[':Busqueda2'] = "%" . $Args['Busqueda'] . "%";
            }
            
            $sql .= " AND la.Operacion IN (";
            if (!empty($Args['MostrarAltas'])) {
                $sql .= "'Alta'";
            }
            if (!empty($Args['MostrarBajas'])) {
                if (!empty($Args['MostrarAltas'])) {
                    $sql .= ", ";
                }
                $sql .= "'Baja'";
            }
            $sql .= ")";
            
            //$sql .= self::busquedaWhereFromArgs($Args, 'MAQ', $emp);
        }

        //
        // PERSONAS FÍSICAS
        //

        if (!isset($Args['Entidad']) || $Args['Entidad'] == 'PF') {
            $emp = 'pfe';
            $empctr = 'pfc';

            if (!empty($sql)) {
                $sql .= ' UNION ALL ';
            }

            $sql .= "SELECT 'func=AdmPersonas|Documento=' + pf.Documento + '|IdTipoDocumento=' + LTRIM(RTRIM(STR(pf.IdTipoDocumento))) AS ObjUrl,
                            CONVERT(varchar(10), la.FechaHora, 103) + ' ' + CONVERT(varchar(8), la.FechaHora, 108) AS FechaHora,
                            CASE
                                WHEN pf.Transito = 0 THEN 'Persona'
                                ELSE 'Visita'
                            END AS Entidad,
                            dbo.Mask(pf.Documento, td.Mascara, 1, 1) AS Identificacion,
                            pf.PrimerNombre + ' ' + pf.SegundoNombre + ' ' + pf.PrimerApellido + ' ' + pf.SegundoApellido AS Detalle,
                            pf.Matricula,
                            la.IdUsuario,
                            la.Operacion,
                            e.Nombre AS Empresa
                    FROM LogActividades la
                    INNER JOIN PersonasFisicas pf ON la.EntidadId = pf.Documento + '-' + LTRIM(RTRIM(STR(pf.IdTipoDocumento)))
                    INNER JOIN Personas p ON p.Documento = pf.Documento AND p.IdTipoDocumento = pf.IdTipoDocumento
                    INNER JOIN TiposDocumento td ON td.IdTipoDocumento = pf.IdTipoDocumento
                    LEFT JOIN PersonasFisicasEmpresas pfe ON pfe.Documento = pf.Documento AND pfe.IdTipoDocumento = pf.IdTipoDocumento AND pfe.FechaBaja IS NULL
                    LEFT JOIN Empresas e ON e.Documento = pfe.DocEmpresa AND e.IdTipoDocumento = pfe.TipoDocEmpresa
                    WHERE 1 = 1";
            
            /**
             * @todo
             */
            /*if (empty($UsuarioEsGestion)){
                $sql .= " AND e.Documento = ':IdEmpresaObj5 AND e.IdTipoDocumento = IdEmpresaObj6";
                $binding[':IdEmpresaObj5'] = $IdEmpresaObj[0];
                $binding[':IdEmpresaObj6'] = $IdEmpresaObj[1];

            } else*/ if (!empty($Args['IdEmpresa'])) {

                $sql .= " AND e.Documento = :IdEmpresaObj7 AND e.IdTipoDocumento = :IdEmpresaObj8";
                $binding[':IdEmpresaObj7'] = $IdEmpresaObj[0];
                $binding[':IdEmpresaObj8'] = $IdEmpresaObj[1];
            }
            

            if ($Args['Modo'] == "FiltrarPorIngreso") {
                $campoFiltroFecha = "la.FechaHora";
            } else if ($Args['Modo'] == "FiltrarPorVigencia") {
                $campoFiltroFecha = "la.FechaHora";
            }

            if (isset($campoFiltroFecha)) {
                $sql .= " AND CONVERT(date, " . $campoFiltroFecha . ", 103) >= CONVERT(date, :FechaDesde1, 103)";
                $binding[':FechaDesde1'] = $Args['FechaDesde']. ' 00:00:00';
            
                $sql .= " AND CONVERT(date, " . $campoFiltroFecha . ", 103) <= CONVERT(date, :FechaHasta1, 103)";
                $binding[':FechaHasta1'] = $Args['FechaHasta']. ' 00:00:00';
            }
            
            if (!empty($Args['Busqueda'])) {
                $sql .= " AND (pf.Documento LIKE :Busqueda4 OR "
                            . "pf.PrimerNombre LIKE :Busqueda5 OR "
                            . "pf.SegundoNombre LIKE :Busqueda6 OR "
                            . "pf.PrimerApellido LIKE :Busqueda7 OR "
                            . "pf.SegundoApellido LIKE :Busqueda8)";
                $binding[':Busqueda4'] = "%" . $Args['Busqueda'] . "%";
                $binding[':Busqueda5'] = "%" . $Args['Busqueda'] . "%";
                $binding[':Busqueda6'] = "%" . $Args['Busqueda'] . "%";
                $binding[':Busqueda7'] = "%" . $Args['Busqueda'] . "%";
                $binding[':Busqueda8'] = "%" . $Args['Busqueda'] . "%";
            }
            
            $sql .= " AND la.Operacion IN (";
            if (!empty($Args['MostrarAltas'])) {
                $sql .= "'Alta'";
            }
            if (!empty($Args['MostrarBajas'])) {
                if (!empty($Args['MostrarAltas'])) {
                    $sql .= ", ";
                }
                $sql .= "'Baja'";
            }
            $sql .= ")";
            
            if (!empty($Args['NoListarTransitos'])) {
                $sql .= " AND pf.Transito = 0";
            }
            
            //$sql .= self::busquedaWhereFromArgs($Args, 'PF', $emp, $empctr);
        }

        //
        // VEHÍCULOS
        //

        if (!isset($Args['Entidad']) || $Args['Entidad'] == 'VEH') {
            $emp = 'v';

            if (!empty($sql)) {
                $sql .= ' UNION ALL ';
            }
            
            $sql .= "SELECT 'func=AdmVehiculos|Serie=' + v.Serie + '|Numero=' + LTRIM(RTRIM(STR(v.Numero))) AS ObjUrl,                         
                            CONVERT(varchar(10), la.FechaHora, 103) + ' ' + CONVERT(varchar(8), la.FechaHora, 108) AS FechaHora,
                            'Vehículo' AS Entidad,
                            v.Serie + ' ' + RTRIM(LTRIM(STR(v.Numero))) AS Identificacion,
                            mv.Descripcion + ' ' + v.Modelo + ' (' + tv.Descripcion + ')' AS Detalle,
                            v.Matricula,
                            la.IdUsuario,
                            la.Operacion,
                            e.Nombre AS Empresa
                    FROM LogActividades la
                    INNER JOIN Vehiculos v ON la.EntidadId = v.Serie + LTRIM(RTRIM(STR(v.Numero)))
                    INNER JOIN MarcasVehiculos mv ON mv.IdMarcaVehic = v.IdMarcaVehic
                    INNER JOIN TiposVehiculos tv ON tv.IdTipoVehiculo = v.IdTipoVehiculo
                    LEFT JOIN Empresas e ON e.Documento = v.DocEmpresa AND e.IdTipoDocumento = v.TipoDocEmp
                    WHERE 1 = 1";

            /**
             * @todo
             */
            /*if (empty($UsuarioEsGestion)){
                $sql .= " AND e.Documento = ':IdEmpresaObj5 AND e.IdTipoDocumento = IdEmpresaObj6";
                $binding[':IdEmpresaObj9'] = $IdEmpresaObj[0];
                $binding[':IdEmpresaObj10'] = $IdEmpresaObj[1];

            } else */if (!empty($Args['IdEmpresa'])) {

                $sql .= " AND e.Documento = :IdEmpresaObj11 AND e.IdTipoDocumento = :IdEmpresaObj12";
                $binding[':IdEmpresaObj11'] = $IdEmpresaObj[0];
                $binding[':IdEmpresaObj12'] = $IdEmpresaObj[1];
            }
            
            if (isset($Args['FechaDesde'])) {
                $sql .= " AND CONVERT(date, la.FechaHora, 103) >= CONVERT(date, :FechaDesde2, 103)";
                $binding[':FechaDesde2'] = $Args['FechaDesde']. ' 00:00:00';

            } if (isset($Args['FechaHasta'])) {

                $sql .= " AND CONVERT(date, la.FechaHora, 103) <= CONVERT(date, :FechaHasta2, 103)";
                $binding[':FechaHasta2'] = $Args['FechaHasta']. ' 00:00:00';
            }
            
            /**
             * @todo
             */

            //la tabla pf. no se nombra en el sql
            if (!empty($Args['Busqueda'])) {
                $sql .= " AND (pf.Documento LIKE :Busqueda4 OR "
                            . "pf.PrimerNombre LIKE :Busqueda5 OR "
                            . "pf.SegundoNombre LIKE :Busqueda6 OR "
                            . "pf.PrimerApellido LIKE :Busqueda7 OR "
                            . "pf.SegundoApellido LIKE :Busqueda8)";
                $binding[':Busqueda4'] = "%" . $Args['Busqueda'] . "%";
                $binding[':Busqueda5'] = "%" . $Args['Busqueda'] . "%";
                $binding[':Busqueda6'] = "%" . $Args['Busqueda'] . "%";
                $binding[':Busqueda7'] = "%" . $Args['Busqueda'] . "%";
                $binding[':Busqueda8'] = "%" . $Args['Busqueda'] . "%";
            }

            
            if (!empty($Args['Busqueda'])) {
                $sql .= " AND (v.Serie LIKE :Busqueda9 OR "
                            . "STR(v.Numero) LIKE :Busqueda10 OR "
                            . "mv.Descripcion LIKE :Busqueda11 OR "
                            . "tv.Descripcion LIKE :Busqueda12)";
                $binding[':Busqueda9'] = "%" . $Args['Busqueda'] . "%";
                $binding[':Busqueda10'] = "%" . $Args['Busqueda'] . "%";
                $binding[':Busqueda11'] = "%" . $Args['Busqueda'] . "%";
                $binding[':Busqueda12'] = "%" . $Args['Busqueda'] . "%";
            }
            
            $sql .= " AND la.Operacion IN (";
            if (!empty($Args['MostrarAltas'])) {
                $sql .= "'Alta'";
            }
            if (!empty($Args['MostrarBajas'])) {
                if (!empty($Args['MostrarAltas'])) {
                    $sql .= ", ";
                }
                $sql .= "'Baja'";
            }
            $sql .= ")";

            //$sql .= self::busquedaWhereFromArgs($Args, 'VEH', $emp);
        }

        $sql .= ' ORDER BY FechaHora DESC';
        
        $data = DB::select($sql, $binding);

        return $data;
    }

    private function busquedaincidencias($IdEmpresaObj, $Args)
    {
        $sql = "";
        
        $binding = [];
        
        $UsuarioEsGestion = $this->user->isGestion();
        
        //
        // MAQUINAS
        //

        $EmpresaBaja = $Args['EmpresaBaja'] == '0' ? ' AND e.Estado = 1' : "";

        if (!isset($Args['Entidad']) || $Args['Entidad'] == 'MAQ') {
            $emp = 'm';
            $empctr = 'mc';


            $sql .= "SELECT DISTINCT 'func=AdmMaquinas|NroSerie=' + m.NroSerie AS ObjUrl,
                            'Maquina' AS Entidad,
                            m.NroSerie AS Identificacion,
                            mm.Descripcion + ' ' + m.Modelo + ' (' + tm.Descripcion + ')' AS Detalle,
                            m.Matricula,
                            e.Nombre AS Empresa,
                            mc.NroContrato + ' (' + e.Nombre + ')' AS Contrato,
                            mi.Fecha,
                            mi.Observaciones
                    FROM MaquinasIncidencias mi
                    INNER JOIN Maquinas m ON m.NroSerie = mi.NroSerie
                    INNER JOIN MarcasMaquinas mm ON mm.IdMarcaMaq = m.IdMarcaMaq
                    INNER JOIN TiposMaquinas tm ON tm.IdTipoMaquina = m.IdTipoMaq
                    LEFT JOIN Empresas e ON e.Documento = m.DocEmpresa AND e.IdTipoDocumento = m.TipoDocEmp
                    LEFT JOIN MaquinasContratos mc ON mc.NroSerie = m.NroSerie AND mc.DocEmpresa = m.DocEmpresa AND mc.TipoDocEmpresa = m.TipoDocEmp
                    LEFT JOIN Empresas mce ON mce.Documento = mc.DocEmpCont AND mce.IdTipoDocumento = mc.IdTipoDocCont
                    WHERE m.Baja = 0" . $EmpresaBaja;

           /**
             * @todo
             */
            /* if (empty($UsuarioEsGestion)){
                $sql .= " AND e.Documento = ':IdEmpresaObj1 AND e.IdTipoDocumento = IdEmpresaObj2";
                $binding[':IdEmpresaObj1'] = $IdEmpresaObj[0];
                $binding[':IdEmpresaObj2'] = $IdEmpresaObj[1];
            }*/
                
            if(!empty($Args['FechaDesde'])){
                $sql .= " AND CONVERT(date, mi.Fecha, 103) >= CONVERT(date, :FechaDesde, 103)";
                $binding[':FechaDesde'] = $Args['FechaDesde']. ' 00:00:00';
            }
            
            if(!empty($Args['FechaHasta'])){
                $sql .= " AND CONVERT(date, mi.Fecha, 103) <= CONVERT(date, :FechaHasta, 103)";
                $binding[':FechaHasta'] = $Args['FechaHasta']. ' 00:00:00';
            }
            
            //$sql .= self::busquedaWhereFromArgs($Args, 'MAQ', $emp, $empctr);
            
        }
        
        //
        // PERSONAS FÍSICAS
        //

        if (!isset($Args['Entidad']) || $Args['Entidad'] == 'PF') {
            $emp = 'pfe';
            $empctr = 'pfc';

            if (!empty($sql))
                $sql .= ' UNION ALL ';

            $sql .= "SELECT 'func=AdmPersonas|Documento=' + pf.Documento + '|IdTipoDocumento=' + LTRIM(RTRIM(STR(pf.IdTipoDocumento))) AS ObjUrl,
                            'Persona' AS Entidad,
                            dbo.Mask(pf.Documento, td.Mascara, 1, 1) AS Identificacion,
                            pf.PrimerNombre + ' ' + pf.SegundoNombre + ' ' + pf.PrimerApellido + ' ' + pf.SegundoApellido AS Detalle,
                            pf.Matricula,
                            e.Nombre AS Empresa,
                            pfc.NroContrato + ' (' + pfce.Nombre + ')' AS Contrato,
                            pfi.Fecha,
                            pfi.Observaciones
                    FROM PersonasFisicasIncidencias pfi
                    INNER JOIN PersonasFisicas pf ON pf.Documento = pfi.Documento AND pf.IdTipoDocumento = pfi.IdTipoDocumento
                    INNER JOIN Personas p ON p.Documento = pf.Documento AND p.IdTipoDocumento = pf.IdTipoDocumento
                    INNER JOIN TiposDocumento td ON td.IdTipoDocumento = pf.IdTipoDocumento
                    LEFT JOIN PersonasFisicasEmpresas pfe ON pfe.Documento = pf.Documento AND pfe.IdTipoDocumento = pf.IdTipoDocumento AND pfe.FechaBaja IS NULL
                    LEFT JOIN Empresas e ON e.Documento = pfe.DocEmpresa AND e.IdTipoDocumento = pfe.TipoDocEmpresa
                    LEFT JOIN PersonasFisicasContratos pfc ON pfc.Documento = pf.Documento AND pfc.IdTipoDocumento = pf.IdTipoDocumento AND pfc.DocEmpresa = pfe.DocEmpresa AND pfc.TipoDocEmpresa = pfe.TipoDocEmpresa
                    LEFT JOIN Empresas pfce ON pfce.Documento = pfc.DocEmpCont AND pfce.IdTipoDocumento = pfc.IdTipoDocCont
                    WHERE p.Baja = 0" . $EmpresaBaja;
            
            /**
             * @todo
             */
            /*if (empty($UsuarioEsGestion)){
                $sql .= " AND e.Documento = ':IdEmpresaObj3 AND e.IdTipoDocumento = IdEmpresaObj4";
                $binding[':IdEmpresaObj3'] = $IdEmpresaObj[0];
                $binding[':IdEmpresaObj4'] = $IdEmpresaObj[1];
            }*/
                
            if(!empty($Args['FechaDesde'])){
                $sql .= " AND CONVERT(date, pfi.Fecha, 103) >= CONVERT(date, :FechaDesde1, 103)";
                $binding[':FechaDesde1'] = $Args['FechaDesde']. ' 00:00:00';
            }
            
            if(!empty($Args['FechaHasta'])){
                $sql .= " AND CONVERT(date, pfi.Fecha, 103) <= CONVERT(date, :FechaHasta1, 103)";
                $binding[':FechaHasta1'] = $Args['FechaHasta']. ' 00:00:00';
            }
            
            //$sql .= self::busquedaWhereFromArgs($Args, 'PF', $emp, $empctr);
            
            if(!empty($Args['IdEmpresa'])){
                $sql .= " AND " . $emp . ".DocEmpresa = :IdEmpresaObj5 AND " . $emp . ".TipoDocEmpresa = IdEmpresaObj6";
                $binding[':IdEmpresaObj5'] = $IdEmpresaObj[0];
                $binding[':IdEmpresaObj6'] = $IdEmpresaObj[1];
            }
            
        }

        //
        // VEHÍCULOS
        //

        if (!isset($Args['Entidad']) || $Args['Entidad'] == 'VEH') {
            $emp = 'v';
            $empctr = 'vc';

            if (!empty($sql))
                $sql .= ' UNION ALL ';

            $sql .= "SELECT 'func=AdmVehiculos|Serie=' + v.Serie + '|Numero=' + LTRIM(RTRIM(STR(v.Numero))) AS ObjUrl,                         
                            'Vehículo' AS Entidad,
                            v.Serie + ' ' + RTRIM(LTRIM(STR(v.Numero))) AS Identificacion,
                            mv.Descripcion + ' ' + v.Modelo + ' (' + tv.Descripcion + ')' AS Detalle,
                            v.Matricula,
                            e.Nombre AS Empresa,
                            vc.NroContrato + ' (' + e.Nombre + ')' AS Contrato,
                            vi.Fecha,
                            vi.Observaciones
                    FROM VehiculosIncidencias vi
                    INNER JOIN Vehiculos v ON v.Serie = vi.Serie AND v.Numero = vi.Numero
                    INNER JOIN MarcasVehiculos mv ON mv.IdMarcaVehic = v.IdMarcaVehic
                    INNER JOIN TiposVehiculos tv ON tv.IdTipoVehiculo = v.IdTipoVehiculo
                    LEFT JOIN Empresas e ON e.Documento = v.DocEmpresa AND e.IdTipoDocumento = v.TipoDocEmp
                    LEFT JOIN VehiculosContratos vc ON vc.Numero = v.Numero AND vc.Serie = v.Serie AND vc.DocEmpresa = v.DocEmpresa AND vc.TipoDocEmpresa = v.TipoDocEmp
                    LEFT JOIN Empresas vce ON vce.Documento = vc.DocEmpCont AND vce.IdTipoDocumento = vc.IdTipoDocCont
                    WHERE v.Baja = 0 " . $EmpresaBaja;

            /**
             * @todo
             */
            /*if (empty($UsuarioEsGestion)){
                $sql .= " AND e.Documento = ':IdEmpresaObj7 AND e.IdTipoDocumento = IdEmpresaObj8";
                $binding[':IdEmpresaObj7'] = $IdEmpresaObj[0];
                $binding[':IdEmpresaObj8'] = $IdEmpresaObj[1];
            }*/
                
            if(!empty($Args['FechaDesde'])){
                $sql .= " AND CONVERT(date, vi.Fecha, 103) >= CONVERT(date, :FechaDesde2, 103)";
                $binding[':FechaDesde2'] = $Args['FechaDesde']. ' 00:00:00';
            }

            if(!empty($Args['FechaHasta'])){
                $sql .= " AND CONVERT(date, vi.Fecha, 103) <= CONVERT(date, :FechaHasta2, 103)";
                $binding[':FechaHasta2'] = $Args['FechaHasta']. ' 00:00:00';
            }


            //$sql .= self::busquedaWhereFromArgs($Args, 'VEH', $emp, $empctr);
            
            if(!empty($Args['IdEmpresa'])){
                $sql .= " AND " . $emp . ".DocEmpresa = :IdEmpresaObj9 AND " . $emp . ".TipoDocEmp = IdEmpresaObj10";
                $binding[':IdEmpresaObj9'] = $IdEmpresaObj[0];
                $binding[':IdEmpresaObj10'] = $IdEmpresaObj[1];
            }
        }
        
        //return $sql;
        $data = [];

        if(!empty($sql)){
            $data = DB::select($sql, $binding);
        }
       
        return $data;
    }

    /**
     * @todo
     */
    //error, falta la tabla en if (empty($UsuarioEsGestion)) {} else if (!empty($Args['IdEmpresa'])) {
    private function busquedacambiosmat($IdEmpresaObj, $Args)
    {
        $sql = "";
        
        $binding = [];
        $UsuarioEsGestion = $this->user->isGestion();

        //
        // MAQUINAS
        //

        if (!isset($Args['Entidad']) || $Args['Entidad'] == 'MAQ') {
            $emp = 'm';

            $sql .= "SELECT 
                        'func=AdmMaquinas|NroSerie=' + m.NroSerie AS ObjUrl,
                        CONVERT(varchar(10), mmm.FechaHora, 103) + ' ' + CONVERT(varchar(8), mmm.FechaHora, 108) AS FechaHora,
                        'Maquina' AS Entidad,
                        m.NroSerie AS Identificacion,
                        mm.Descripcion + ' ' + m.Modelo + ' (' + tm.Descripcion + ')' AS Detalle,
                        mmm.Matricula,
                        (SELECT TOP 1 Matricula FROM MaquinasMatriculas WHERE NroSerie = mmm.NroSerie AND FechaHora < mmm.FechaHora ORDER BY FechaHora DESC) AS MatriculaAnt,
                        mmm.IdUsuario,
                        mmm.Observaciones
                    FROM Maquinas M 
                    INNER JOIN TiposMaquinas tm ON m.IdTipoMaq = tm.IdTipoMaquina
                    INNER JOIN MarcasMaquinas mm ON m.IdMarcaMaq = mm.IdMarcaMaq
                    INNER JOIN MaquinasMatriculas mmm ON mmm.NroSerie = m.NroSerie 
                    WHERE 1 = 1";

            // LEFT JOIN Empresas e ON m.DocEmpresa = e.Documento AND m.TipoDocEmp = e.IdTipoDocumento
            
            if(!empty($Args['FechaDesde'])){
                $sql .= " AND CONVERT(date, mmm.FechaHora, 103) >= CONVERT(date, :FechaDesde, 103)";
                $binding[':FechaDesde'] = $Args['FechaDesde']. ' 00:00:00';
            }
            
            if(!empty($Args['FechaHasta'])){
                $sql .= " AND CONVERT(date, mmm.FechaHora, 103) <= CONVERT(date, :FechaHasta, 103)";
                $binding[':FechaHasta'] = $Args['FechaHasta']. ' 00:00:00';
            }

            
            /**
             * @todo
             */
            /*if (empty($UsuarioEsGestion)) {
                //$sql .= " AND e.DocEmpresa = '" . $IdEmpresaObj[0] . "' AND e.TipoDocEmpresa = " . $IdEmpresaObj[1];
                $sql .= " AND EXISTS (SELECT * FROM PersonasFisicasEmpresas e WHERE pf.Documento = e.Documento AND pf.IdTipoDocumento = e.IdTipoDocumento AND e.DocEmpresa = :IdEmpresaObj1 AND e.TipoDocEmpresa = :IdEmpresaObj2)";
                $binding[':IdEmpresaObj1'] = $IdEmpresaObj[0];
                $binding[':IdEmpresaObj2'] = $IdEmpresaObj[1];
            } 
            else */if (!empty($Args['IdEmpresa'])) {
                // $sql .= " AND e.DocEmpresa = '" . $IdEmpresaObj[0] . "' AND e.TipoDocEmpresa = " . $IdEmpresaObj[1];
                $sql .= " AND EXISTS (SELECT * FROM PersonasFisicasEmpresas e WHERE pf.Documento = e.Documento AND pf.IdTipoDocumento = e.IdTipoDocumento AND e.DocEmpresa = :IdEmpresaObj3 AND e.TipoDocEmpresa = :IdEmpresaObj4)";
                $binding[':IdEmpresaObj3'] = $IdEmpresaObj[0];
                $binding[':IdEmpresaObj4'] = $IdEmpresaObj[1];
            }
            
            if (!empty($Args['Busqueda'])) {
                $sql .= " AND (m.NroSerie LIKE :Busqueda1 OR "
                            . "tm.Descripcion LIKE :Busqueda2 OR "
                            . "mm.Descripcion LIKE :Busqueda3)";
                $binding[':Busqueda1'] = "%" . $Args['Busqueda'] . "%";
                $binding[':Busqueda2'] = "%" . $Args['Busqueda'] . "%";
                $binding[':Busqueda3'] = "%" . $Args['Busqueda'] . "%";
            }
        }

        //
        // PERSONAS FÍSICAS
        //

        if (!isset($Args['Entidad']) || $Args['Entidad'] == 'PF') {
            $emp = 'pfe';
            $empctr = 'pfc';

            if (!empty($sql)) {
                $sql .= ' UNION ALL ';
            }

            $sql .= "SELECT 
                        'func=AdmPersonas|Documento=' + pf.Documento + '|IdTipoDocumento=' + LTRIM(RTRIM(STR(pf.IdTipoDocumento))) AS ObjUrl,
                        CONVERT(varchar(10), pfm.FechaHora, 103) + ' ' + CONVERT(varchar(8), pfm.FechaHora, 108) AS FechaHora,
                        'Persona' AS Entidad,
                        dbo.Mask(pf.Documento, td.Mascara, 1, 1) AS Identificacion,
                        pf.NombreCompleto AS Detalle,
                        pfm.Matricula,
                        (SELECT TOP 1 Matricula FROM PersonasFisicasMatriculas WHERE Documento = pfm.Documento AND IdTipoDocumento = pfm.IdTipoDocumento AND FechaHora < pfm.FechaHora ORDER BY FechaHora DESC) AS MatriculaAnt,
                        pfm.IdUsuario,
                        pfm.Observaciones
                    FROM PersonasFisicas PF 
                    INNER JOIN TiposDocumento td ON pf.IdTipoDocumento = td.IdTipoDocumento
                    INNER JOIN PersonasFisicasMatriculas PFM ON PF.documento = PFM.documento AND PF.idTipoDocumento = PFM.idTipoDocumento 
                    WHERE 1 = 1";

            // LEFT JOIN PersonasFisicasEmpresas e ON pf.Documento = e.Documento AND pf.IdTipoDocumento = e.IdTipoDocumento

            if(!empty($Args['FechaDesde'])){
                $sql .= " AND CONVERT(date, pfm.FechaHora, 103) >= CONVERT(date, :FechaDesde1, 103)";
                $binding[':FechaDesde1'] = $Args['FechaDesde']. ' 00:00:00';
            }
            
            if(!empty($Args['FechaHasta'])){
                $sql .= " AND CONVERT(date, pfm.FechaHora, 103) <= CONVERT(date, :FechaHasta1, 103)";
                $binding[':FechaHasta1'] = $Args['FechaHasta']. ' 00:00:00';
            }
            
            /**
             * @todo
             */
            /*if (empty($UsuarioEsGestion)) {
                //$sql .= " AND e.DocEmpresa = '" . $IdEmpresaObj[0] . "' AND e.TipoDocEmpresa = " . $IdEmpresaObj[1];
                $sql .= " AND EXISTS (SELECT * FROM PersonasFisicasEmpresas e WHERE pf.Documento = e.Documento AND pf.IdTipoDocumento = e.IdTipoDocumento AND e.DocEmpresa = :IdEmpresaObj5 AND e.TipoDocEmpresa = IdEmpresaObj6)";
                $binding[':IdEmpresaObj5'] = $IdEmpresaObj[0];
                $binding[':IdEmpresaObj6'] = $IdEmpresaObj[1];
            } 
            else*/ if (!empty($Args['IdEmpresa'])) {
                // $sql .= " AND e.DocEmpresa = '" . $IdEmpresaObj[0] . "' AND e.TipoDocEmpresa = " . $IdEmpresaObj[1];
                $sql .= " AND EXISTS (SELECT * FROM PersonasFisicasEmpresas e WHERE pf.Documento = e.Documento AND pf.IdTipoDocumento = e.IdTipoDocumento AND e.DocEmpresa = :IdEmpresaObj7 AND e.TipoDocEmpresa = :IdEmpresaObj8)";
                $binding[':IdEmpresaObj7'] = $IdEmpresaObj[0];
                $binding[':IdEmpresaObj8'] = $IdEmpresaObj[1];
            }
            
            if (!empty($Args->Busqueda)) {
                $sql .= " AND (pf.Documento LIKE :Busqueda4 OR "
                            . "pf.PrimerNombre LIKE :Busqueda5 OR "
                            . "pf.SegundoNombre LIKE :Busqueda6 OR "
                            . "pf.PrimerApellido LIKE :Busqueda7 OR "
                            . "pf.SegundoApellido LIKE :Busqueda8)";
                $binding[':Busqueda4'] = "%" . $Args['Busqueda'] . "%";
                $binding[':Busqueda5'] = "%" . $Args['Busqueda'] . "%";
                $binding[':Busqueda6'] = "%" . $Args['Busqueda'] . "%";
                $binding[':Busqueda7'] = "%" . $Args['Busqueda'] . "%";
                $binding[':Busqueda8'] = "%" . $Args['Busqueda'] . "%";
            }
        }

        //
        // VEHÍCULOS
        //

        if (!isset($Args['Entidad']) || $Args['Entidad'] == 'VEH') {
            $emp = 'v';

            if (!empty($sql)) {
                $sql .= ' UNION ALL ';
            }
            
            $sql .= "SELECT 
                        'func=AdmVehiculos|Serie=' + v.Serie + '|Numero=' + LTRIM(RTRIM(STR(v.Numero))) AS ObjUrl,
                        CONVERT(varchar(10), vm.FechaHora, 103) + ' ' + CONVERT(varchar(8), vm.FechaHora, 108) AS FechaHora,
                        'Vehículo' AS Entidad,
                        v.Serie + ' ' + RTRIM(LTRIM(STR(v.Numero))) AS Identificacion,
                        mv.Descripcion + ' ' + v.Modelo + ' (' + tv.Descripcion + ')' AS Detalle,
                        vm.Matricula,
                        (SELECT TOP 1 Matricula FROM VehiculosMatriculas WHERE Serie = vm.Serie AND Numero = vm.Numero AND FechaHora < vm.FechaHora ORDER BY FechaHora DESC) AS MatriculaAnt,
                        vm.IdUsuario,
                        vm.Observaciones
                    FROM Vehiculos V 
                    INNER JOIN TiposVehiculos tv ON v.IdTipoVehiculo = tv.IdTipoVehiculo
                    INNER JOIN MarcasVehiculos mv ON v.IdMarcaVehic = mv.IdMarcaVehic
                    INNER JOIN VehiculosMatriculas vm ON vm.Numero = v.Numero AND vm.Serie = v.Serie 
                    WHERE 1 = 1";

            // LEFT JOIN Empresas e ON v.DocEmpresa = e.Documento AND v.TipoDocEmp = e.IdTipoDocumento

            if(!empty($Args['FechaDesde'])){
                $sql .= " AND CONVERT(date, vm.FechaHora, 103) >= CONVERT(date, :FechaDesde2, 103)";
                $binding[':FechaDesde2'] = $Args['FechaDesde']. ' 00:00:00';
            }
            
            if(!empty($Args['FechaHasta'])){
                $sql .= " AND CONVERT(date, vm.FechaHora, 103) <= CONVERT(date, :FechaHasta2, 103)";
                $binding[':FechaHasta2'] = $Args['FechaHasta']. ' 00:00:00';
            }
            
            /**
             * @todo
             */
            /*if (empty($UsuarioEsGestion)) {
                //$sql .= " AND e.DocEmpresa = '" . $IdEmpresaObj[0] . "' AND e.TipoDocEmpresa = " . $IdEmpresaObj[1];
                $sql .= " AND EXISTS (SELECT * FROM PersonasFisicasEmpresas e WHERE pf.Documento = e.Documento AND pf.IdTipoDocumento = e.IdTipoDocumento AND e.DocEmpresa = :IdEmpresaObj9 AND e.TipoDocEmpresa = :IdEmpresaObj10)";
                $binding[':IdEmpresaObj9'] = $IdEmpresaObj[0];
                $binding[':IdEmpresaObj10'] = $IdEmpresaObj[1];
            } 
            else */if (!empty($Args['IdEmpresa'])) {

                // $sql .= " AND e.DocEmpresa = '" . $IdEmpresaObj[0] . "' AND e.TipoDocEmpresa = " . $IdEmpresaObj[1];
                $sql .= " AND EXISTS (SELECT * FROM PersonasFisicasEmpresas e WHERE pf.Documento = e.Documento AND pf.IdTipoDocumento = e.IdTipoDocumento AND e.DocEmpresa = :IdEmpresaObj11 AND e.TipoDocEmpresa = :IdEmpresaObj12)";
                $binding[':IdEmpresaObj11'] = $IdEmpresaObj[0];
                $binding[':IdEmpresaObj12'] = $IdEmpresaObj[1];
            }
            
            if (!empty($Args['Busqueda'])) {
                $sql .= " AND (v.Serie LIKE :Busqueda9 OR "
                            . "STR(v.Numero) LIKE :Busqueda10 OR "
                            . "mv.Descripcion LIKE :Busqueda11 OR "
                            . "tv.Descripcion LIKE :Busqueda12)";
                $binding[':Busqueda9'] = "%" . $Args['Busqueda'] . "%";
                $binding[':Busqueda10'] = "%" . $Args['Busqueda'] . "%";
                $binding[':Busqueda11'] = "%" . $Args['Busqueda'] . "%";
                $binding[':Busqueda12'] = "%" . $Args['Busqueda'] . "%";
            }
        }

        $sql .= ' ORDER BY FechaHora DESC';

        $data = DB::select($sql, $binding);

        return $data;
    }

    public function busquedahabilitados($IdEmpresaObj, $Args)
    {
        $sql = "";
        
        $binding = [];
        $UsuarioEsGestion = $this->user->isGestion();
        
        $ArgsIdEmpresaObj = [];
        if (!empty($Args['IdEmpresa'])) {
            $ArgsIdEmpresaObj = FsUtils::explodeId($Args['IdEmpresa']);
        }

        //
        // MAQUINAS
        //

        if (!isset($Args['Entidad']) || $Args['Entidad'] == 'MAQ') {
            $emp = 'm';

            $sql .= "SELECT DISTINCT 'func=AdmMaquinas|NroSerie=' + m.NroSerie AS ObjUrl,
                            'Maquina' AS Entidad,
                            m.NroSerie AS Identificacion,
                            mm.Descripcion + ' ' + m.Modelo + ' (' + tm.Descripcion + ')' AS Detalle,
                            m.Matricula,
                            e.Nombre AS Empresa,
                            mc.NroContrato + ' (' + e.Nombre + ')' AS Contrato,
                            mc.NroContrato
                    FROM Maquinas m
                    INNER JOIN MarcasMaquinas mm ON mm.IdMarcaMaq = m.IdMarcaMaq
                    INNER JOIN TiposMaquinas tm ON tm.IdTipoMaquina = m.IdTipoMaq
                    LEFT JOIN Empresas e ON e.Documento = m.DocEmpresa AND e.IdTipoDocumento = m.TipoDocEmp
                    LEFT JOIN MaquinasContratos mc ON mc.DocEmpCont = e.Documento AND mc.IdTipoDocCont = e.IdTipoDocumento";
            
            if (!empty($Args['IdAcceso'])) {
                $sql .= " INNER JOIN MaquinasAccesos ma ON ma.NroSerie = m.NroSerie AND ma.IdAcceso = :IdAcceso";
                $binding[':IdAcceso'] = $Args['IdAcceso']; 
            }

            $sql .= " WHERE m.Baja = 0 
                    AND m.Estado = 1 
                    AND NOT m.Matricula IS NULL
                    AND e.Estado = 1";

            /**
             * @todo
             */
            /*if (empty($UsuarioEsGestion)) {
                $sql .= " AND e.Documento = :IdEmpresaObj1 AND e.IdTipoDocumento = :IdEmpresaObj2";
                $binding[':IdEmpresaObj1'] = $IdEmpresaObj[0];
                $binding[':IdEmpresaObj2'] = $IdEmpresaObj[1];
                
            }*/
            
            if (!empty($Args['NroContrato'])) {
                $sql .= " AND mc.NroContrato LIKE :NroContrato";
                $binding[':NroContrato'] = "%" . $Args['NroContrato'] . "%";
            } else if (!empty($Args['ConNroContrato'])) {
                $sql .= " AND mc.NroContrato IS NOT NULL AND LEN(mc.NroContrato) > 0";
            }
            
            //$sql .= self::busquedaWhereFromArgs($Args, 'MAQ', $emp);
        }

        //
        // PERSONAS FÍSICAS
        //

        if (!isset($Args['Entidad']) || $Args['Entidad'] == 'PF') {
            $emp = 'pfe';
            $empctr = 'pfc';

            if (!empty($sql)) {
                $sql .= ' UNION ALL ';
            }

            $sql .= "SELECT DISTINCT 'func=AdmPersonas|Documento=' + pf.Documento + '|IdTipoDocumento=' + LTRIM(RTRIM(STR(pf.IdTipoDocumento))) AS ObjUrl,
                            'Persona' AS Entidad,
                            dbo.Mask(pf.Documento, td.Mascara, 1, 1) AS Identificacion,
                            pf.PrimerNombre + ' ' + pf.SegundoNombre + ' ' + pf.PrimerApellido + ' ' + pf.SegundoApellido AS Detalle,
                            pf.Matricula,
                            e.Nombre AS Empresa,
                            pfc.NroContrato + ' (' + pfce.Nombre + ')' AS Contrato,
                            pfc.NroContrato
                    FROM PersonasFisicas pf
                    INNER JOIN Personas p ON p.Documento = pf.Documento AND p.IdTipoDocumento = pf.IdTipoDocumento
                    INNER JOIN TiposDocumento td ON td.IdTipoDocumento = pf.IdTipoDocumento
                    LEFT JOIN PersonasFisicasEmpresas pfe ON pfe.Documento = pf.Documento AND pfe.IdTipoDocumento = pf.IdTipoDocumento AND pfe.FechaBaja IS NULL
                    LEFT JOIN Empresas e ON e.Documento = pfe.DocEmpresa AND e.IdTipoDocumento = pfe.TipoDocEmpresa
                    LEFT JOIN PersonasFisicasContratos pfc ON pfc.Documento = pf.Documento AND pfc.IdTipoDocumento = pf.IdTipoDocumento AND pfc.DocEmpresa = pfe.DocEmpresa AND pfc.TipoDocEmpresa = pfe.TipoDocEmpresa
                    LEFT JOIN Empresas pfce ON pfce.Documento = pfc.DocEmpCont AND pfce.IdTipoDocumento = pfc.IdTipoDocCont";
            
            if (!empty($Args['IdAcceso'])) {
                $sql .= " INNER JOIN PersonasFisicasAccesos pfa ON pfa.Documento = pf.Documento AND pfa.IdTipoDocumento = pf.IdTipoDocumento AND pfa.IdAcceso = :IdAcceso1";
                $binding[':IdAcceso1'] = $Args['IdAcceso'];
            }

            $sql .= " WHERE p.Baja = 0 
                    AND pf.Estado = 1 
                    AND NOT pf.Matricula IS NULL
                    AND e.Estado = 1";

            /**
             * @todo
             */
            /*if (empty($UsuarioEsGestion)) {
                $sql .= " AND e.Documento = :IdEmpresaObj3 AND e.IdTipoDocumento = IdEmpresaObj4";
                $binding[':IdEmpresaObj3'] = $IdEmpresaObj[0];
                $binding[':IdEmpresaObj4'] = $IdEmpresaObj[1];

            }*/
            
            if (!empty($Args['NroContrato'])) {
                $sql .= " AND pfc.NroContrato LIKE :NroContrato2";
                $binding[':NroContrato2'] = "%" . $Args['NroContrato'] . "%";
            } else if (!empty($Args['ConNroContrato'])) {
                $sql .= " AND pfc.NroContrato IS NOT NULL AND LEN(pfc.NroContrato) > 0";
            }

            //$sql .= self::busquedaWhereFromArgs($Args, 'PF', $emp, $empctr);
            if(!empty($Args['IdEmpresa'])){
                $binding[':IdEmpresaObj5'] = $ArgsIdEmpresaObj[0];
                $binding[':IdEmpresaObj6'] = $ArgsIdEmpresaObj[1];
                $sql .= " AND ". $emp .".DocEmpresa = :IdEmpresaObj5 AND ". $emp .".TipoDocEmpresa = :IdEmpresaObj6";
            }
            
        }

        //
        // VEHÍCULOS
        //

        if (!isset($Args['Entidad']) || $Args['Entidad'] == 'VEH') {
            $emp = 'v';

            if (!empty($sql)) {
                $sql .= ' UNION ALL ';
            }

            $sql .= "SELECT DISTINCT 'func=AdmVehiculos|Serie=' + v.Serie + '|Numero=' + LTRIM(RTRIM(STR(v.Numero))) AS ObjUrl,                         
                            'Vehículo' AS Entidad,
                            v.Serie + ' ' + RTRIM(LTRIM(STR(v.Numero))) AS Identificacion,
                            mv.Descripcion + ' ' + v.Modelo + ' (' + tv.Descripcion + ')' AS Detalle,
                            v.Matricula,
                            e.Nombre AS Empresa,
                            vc.NroContrato + ' (' + e.Nombre + ')' AS Contrato,
                            vc.NroContrato
                    FROM Vehiculos v
                    INNER JOIN MarcasVehiculos mv ON mv.IdMarcaVehic = v.IdMarcaVehic
                    INNER JOIN TiposVehiculos tv ON tv.IdTipoVehiculo = v.IdTipoVehiculo
                    LEFT JOIN Empresas e ON e.Documento = v.DocEmpresa AND e.IdTipoDocumento = v.TipoDocEmp
                    LEFT JOIN VehiculosContratos vc ON vc.DocEmpCont = e.Documento AND vc.IdTipoDocCont = e.IdTipoDocumento";
            
            if (!empty($Args->IdAcceso)) {
                $sql .= " INNER JOIN VehiculosAccesos va ON va.Serie = v.Serie AND va.Numero = v.Numero AND va.IdAcceso = :IdAcceso3";
                $binding[':IdAcceso2'] = $Args['IdAcceso'];
            }

            $sql .= " WHERE v.Baja = 0 
                    AND v.Estado = 1 
                    AND NOT v.Matricula IS NULL
                    AND e.Estado = 1";

            /**
             * @todo
             */
            /*if (empty($UsuarioEsGestion)) {

                $sql .= " AND e.Documento = :IdEmpresaObj7 AND e.IdTipoDocumento = :IdEmpresaObj8";
                $binding[':IdEmpresaObj7'] = $IdEmpresaObj[0];
                $binding[':IdEmpresaObj8'] = $IdEmpresaObj[1];
            }*/
            
            if (!empty($Args['NroContrato'])) {
                $sql .= " AND vc.NroContrato LIKE :NroContrato2";
                $binding[':NroContrato2'] = "%" . $Args['NroContrato'] . "%";
            } else if (!empty($Args['ConNroContrato'])) {
                $sql .= " AND vc.NroContrato IS NOT NULL AND LEN(vc.NroContrato) > 0";
            }
            
            //$sql .= self::busquedaWhereFromArgs($Args, 'VEH', $emp);
            if(!empty($Args['IdEmpresa'])){
                $binding[':IdEmpresaObj9'] = $ArgsIdEmpresaObj[0];
                $binding[':IdEmpresaObj10'] = $ArgsIdEmpresaObj[1];
                $sql .= " AND ". $emp . ".DocEmpresa = :IdEmpresaObj9 AND ". $emp .".TipoDocEmp = :IdEmpresaObj10";
            }
        }

        $data = [];

        if (!empty($sql)) {
          $data = DB::select($sql, $binding);
        }
        
        return $data;
    }

    private function busquedacontratosaltasbajas($IdEmpresaObj, $Args)
    {
        $sql = "";
        $binding = [];

        $UsuarioEsGestion = $this->user->isGestion();

        $ArgsIdEmpresaObj = [];
        if(!empty($Args['IdEmpresa'])){
            $ArgsIdEmpresaObj = FsUtils::explodeId($Args['IdEmpresa']);
        }

        //
        // MAQUINAS
        //

        if (!empty($Args['Maquinas'])) {
            $emp = 'm';
           

            if (!empty($Args['MostrarAltas'])) {
                $sql .= "SELECT 'func=AdmMaquinas|NroSerie=' + m.NroSerie AS ObjUrl,
                                CONVERT(varchar(10), mca.FechaAlta, 103) AS Fecha,
                                CONVERT(varchar(10), mca.FechaHoraAlta, 103) + ' ' + CONVERT(varchar(8), mca.FechaHoraAlta, 108) AS FechaHora,
                                'Maquina' AS Entidad,
                                m.NroSerie AS Identificacion,
                                mm.Descripcion + ' ' + m.Modelo + ' (' + tm.Descripcion + ')' AS Detalle,
                                m.Matricula,
                                mca.IdUsuarioAlta AS IdUsuario,
                                mca.NroContrato,
                                e.Nombre AS Empresa,
                                'Alta' AS Operacion,
                                c.Descripcion AS Categoria
                        FROM MaquinasContratosAltas mca
                        INNER JOIN Maquinas m ON mca.NroSerie = m.NroSerie
                        INNER JOIN MarcasMaquinas mm ON mm.IdMarcaMaq = m.IdMarcaMaq
                        INNER JOIN TiposMaquinas tm ON tm.IdTipoMaquina = m.IdTipoMaq
                        INNER JOIN Categorias c ON c.IdCategoria = m.IdCategoria
                        LEFT JOIN Empresas e ON e.Documento = mca.DocEmpresa AND e.IdTipoDocumento = mca.TipoDocEmpresa
                        WHERE 1 = 1";

                /**
             * @todo
             */
            /*if (empty($UsuarioEsGestion)) {

                     $sql .= " AND e.Documento = :IdEmpresaObj1 AND e.IdTipoDocumento = :IdEmpresaObj2";
                    
                     $binding[':IdEmpresaObj1'] = $IdEmpresaObj[0];
                     $binding[':IdEmpresaObj2'] = $IdEmpresaObj[1];

                } else */if (!empty($Args['IdEmpresa'])) {

                    $sql .= " AND e.Documento = :IdEmpresaObj3 AND e.IdTipoDocumento = :IdEmpresaObj4";
                    $binding[':IdEmpresaObj3'] = $ArgsIdEmpresaObj[0];
                    $binding[':IdEmpresaObj4'] = $ArgsIdEmpresaObj[1];
                }

                if(!empty($Args['FechaDesde'])){
                    $sql .= " AND CONVERT(date, mca.FechaAlta, 103) >= CONVERT(date, :FechaDesde, 103)";
                    $binding[':FechaDesde'] = $Args['FechaDesde']. ' 00:00:00';
                }
                
                if(!empty($Args['FechaHasta'])){
                    $sql .= " AND CONVERT(date, mca.FechaAlta, 103) <= CONVERT(date, :FechaHasta, 103)";
                    $binding[':FechaHasta'] = $Args['FechaHasta']. ' 00:00:00';
                }


                if (!empty($Args['Busqueda'])) {
                    $sql .= " AND (m.NroSerie LIKE :Busqueda1 OR "
                                . "tm.Descripcion LIKE :Busqueda2 OR "
                                . "mm.Descripcion LIKE :Busqueda3)";

                    $binding[':Busqueda1'] = "%" . $Args['Busqueda'] . "%";
                    $binding[':Busqueda2'] = "%" . $Args['Busqueda'] . "%";
                    $binding[':Busqueda3'] = "%" . $Args['Busqueda'] . "%";
                }
            }
            
            if (!empty($Args['MostrarBajas'])) {
                if (!empty($Args['MostrarAltas'])) {
                    $sql .= " UNION ALL ";
                }
                
                $sql .= "SELECT 'func=AdmMaquinas|NroSerie=' + m.NroSerie AS ObjUrl,
                                CONVERT(varchar(10), mcb.Fechabaja, 103) AS Fecha,
                                CONVERT(varchar(10), mcb.FechaHoraBaja, 103) + ' ' + CONVERT(varchar(8), mcb.FechaHoraBaja, 108) AS FechaHora,
                                'Maquina' AS Entidad,
                                m.NroSerie AS Identificacion,
                                mm.Descripcion + ' ' + m.Modelo + ' (' + tm.Descripcion + ')' AS Detalle,
                                m.Matricula,
                                mcb.IdUsuarioBaja AS IdUsuario,
                                mcb.NroContrato,
                                e.Nombre AS Empresa,
                                'Baja' AS Operacion,
                                c.Descripcion AS Categoria
                        FROM MaquinasContratosBajas mcb
                        INNER JOIN Maquinas m ON mcb.NroSerie = m.NroSerie
                        INNER JOIN MarcasMaquinas mm ON mm.IdMarcaMaq = m.IdMarcaMaq
                        INNER JOIN TiposMaquinas tm ON tm.IdTipoMaquina = m.IdTipoMaq
                        INNER JOIN Categorias c ON c.IdCategoria = m.IdCategoria
                        LEFT JOIN Empresas e ON e.Documento = mcb.DocEmpresa AND e.IdTipoDocumento = mcb.TipoDocEmpresa
                        WHERE 1 = 1";

                /**
             * @todo
             */
            /*if (empty($UsuarioEsGestion)) {
                    
                    $sql .= " AND e.Documento = :IdEmpresaObj5 AND e.IdTipoDocumento = :IdEmpresaObj6";
                    
                    $binding[':IdEmpresaObj5'] = $IdEmpresaObj[0];
                    $binding[':IdEmpresaObj6'] = $IdEmpresaObj[1];
                } else */if (!empty($Args['IdEmpresa'])) {

                    $sql .= " AND e.Documento = :IdEmpresaObj7 AND e.IdTipoDocumento = :IdEmpresaObj7";
                    
                    $binding[':IdEmpresaObj7'] = $ArgsIdEmpresaObj[0];
                    $binding[':IdEmpresaObj8'] = $ArgsIdEmpresaObj[1];
                }
                
                if(!empty($Args['FechaDesde'])){
                    $sql .= " AND CONVERT(date, mcb.FechaBaja, 103) >= CONVERT(date, :FechaDesde1, 103)";
                    $binding[':FechaDesde1'] = $Args['FechaDesde']. ' 00:00:00';
                }
                
                if(!empty($Args['FechaHasta'])){
                    $sql .= " AND CONVERT(date, mcb.FechaBaja, 103) <= CONVERT(date, :FechaHasta1, 103)";
                    $binding[':FechaHasta1'] = $Args['FechaHasta']. ' 00:00:00';
                }

                if (!empty($Args['Busqueda'])) {
                    $sql .= " AND (m.NroSerie LIKE :Busqueda4 OR "
                                . "tm.Descripcion LIKE :Busqueda5 OR "
                                . "mm.Descripcion LIKE :Busqueda6)";

                    $binding[':Busqueda4'] = "%" . $Args['Busqueda'] . "%";
                    $binding[':Busqueda5'] = "%" . $Args['Busqueda'] . "%";
                    $binding[':Busqueda6'] = "%" . $Args['Busqueda'] . "%";
                }

            }
            
            //$sql .= self::busquedaWhereFromArgs($Args, 'MAQ', $emp);
        }

        //
        // PERSONAS FÍSICAS
        //

        if (!empty($Args['Personas'])) {
            $emp = 'pfe';
            $empctr = 'pfc';

            if (!empty($sql)) {
                $sql .= ' UNION ALL ';
            }
            
            if (!empty($Args['MostrarAltas'])) {
                $sql .= "SELECT 'func=AdmPersonas|Documento=' + pf.Documento + '|IdTipoDocumento=' + LTRIM(RTRIM(STR(pf.IdTipoDocumento))) AS ObjUrl,
                                CONVERT(varchar(10), pfca.FechaAlta, 103) AS Fecha,
                                CONVERT(varchar(10), pfca.FechaHoraAlta, 103) + ' ' + CONVERT(varchar(8), pfca.FechaHoraAlta, 108) AS FechaHora,
                                CASE
                                    WHEN pf.Transito = 0 THEN 'Persona'
                                    ELSE 'Visita'
                                END AS Entidad,
                                dbo.Mask(pf.Documento, td.Mascara, 1, 1) AS Identificacion,
                                pf.PrimerNombre + ' ' + pf.SegundoNombre + ' ' + pf.PrimerApellido + ' ' + pf.SegundoApellido AS Detalle,
                                pf.Matricula,
                                pfca.IdUsuarioAlta AS IdUsuario,
                                pfca.NroContrato,
                                e.Nombre AS Empresa,
                                'Alta' AS Operacion,
                                c.Descripcion AS Categoria
                        FROM PersonasFisicasContratosAltas pfca
                        INNER JOIN PersonasFisicas pf ON pfca.Documento = pf.Documento AND pfca.IdTipoDocumento = pf.IdTipoDocumento
                        INNER JOIN Personas p ON p.Documento = pf.Documento AND p.IdTipoDocumento = pf.IdTipoDocumento
                        INNER JOIN TiposDocumento td ON td.IdTipoDocumento = pf.IdTipoDocumento
                        INNER JOIN Categorias c ON c.IdCategoria = p.IdCategoria
                        LEFT JOIN Empresas e ON e.Documento = pfca.DocEmpresa AND e.IdTipoDocumento = pfca.TipoDocEmpresa
                        WHERE 1 = 1";

                /**
             * @todo
             */
            /*if (empty($UsuarioEsGestion)) {
                    $sql .= " AND e.Documento = :IdEmpresaObj9 AND e.IdTipoDocumento = :IdEmpresaObj10";
                    
                    $binding[':IdEmpresaObj9'] = $IdEmpresaObj[0];
                    $binding[':IdEmpresaObj10'] = $IdEmpresaObj[1];
                } else */if (!empty($Args['IdEmpresa'])) {

                    $sql .= " AND e.Documento = :IdEmpresaObj11 AND e.IdTipoDocumento = :IdEmpresaObj12";
                    
                    $binding[':IdEmpresaObj11'] = $ArgsIdEmpresaObj[0];
                    $binding[':IdEmpresaObj12'] = $ArgsIdEmpresaObj[1];
                }
                
                if(!empty($Args['FechaDesde'])){
                    $sql .= " AND CONVERT(date, pfca.FechaAlta, 103) >= CONVERT(date, :FechaDesde2, 103)";
                    $binding[':FechaDesde2'] = $Args['FechaDesde']. ' 00:00:00';
                }
                
                if(!empty($Args['FechaHasta'])){
                    $sql .= " AND CONVERT(date, pfca.FechaAlta, 103) <= CONVERT(date, :FechaHasta2, 103)";
                    $binding[':FechaHasta2'] = $Args['FechaHasta']. ' 00:00:00';
                }
                
                if (!empty($Args['Busqueda'])) {
                    $sql .= " AND (pf.Documento LIKE :Busqueda7 OR "
                    . "pf.PrimerNombre LIKE :Busqueda8 OR "
                    . "pf.SegundoNombre LIKE :Busqueda9 OR "
                    . "pf.PrimerApellido LIKE :Busqueda10 OR "
                    . "pf.SegundoApellido LIKE :Busqueda11)";

                    $binding[':Busqueda7'] = "%" . $Args['Busqueda'] . "%";
                    $binding[':Busqueda8'] = "%" . $Args['Busqueda'] . "%";
                    $binding[':Busqueda9'] = "%" . $Args['Busqueda'] . "%";
                    $binding[':Busqueda10'] = "%" . $Args['Busqueda'] . "%";
                    $binding[':Busqueda11'] = "%" . $Args['Busqueda'] . "%";
                }

            }
            
            if (!empty($Args['MostrarBajas'])) {
                if (!empty($Args['MostrarAltas'])) {
                    $sql .= " UNION ALL ";
                }
                
                $sql .= "SELECT 'func=AdmPersonas|Documento=' + pf.Documento + '|IdTipoDocumento=' + LTRIM(RTRIM(STR(pf.IdTipoDocumento))) AS ObjUrl,
                                CONVERT(varchar(10), pfcb.FechaBaja, 103) AS Fecha,
                                CONVERT(varchar(10), pfcb.FechaHoraBaja, 103) + ' ' + CONVERT(varchar(8), pfcb.FechaHoraBaja, 108) AS FechaHora,
                                CASE
                                    WHEN pf.Transito = 0 THEN 'Persona'
                                    ELSE 'Visita'
                                END AS Entidad,
                                dbo.Mask(pf.Documento, td.Mascara, 1, 1) AS Identificacion,
                                pf.PrimerNombre + ' ' + pf.SegundoNombre + ' ' + pf.PrimerApellido + ' ' + pf.SegundoApellido AS Detalle,
                                pf.Matricula,
                                pfcb.IdUsuarioBaja AS IdUsuario,
                                pfcb.NroContrato,
                                e.Nombre AS Empresa,
                                'Baja' AS Operacion,
                                c.Descripcion AS Categoria
                        FROM PersonasFisicasContratosBajas pfcb
                        INNER JOIN PersonasFisicas pf ON pfcb.Documento = pf.Documento AND pfcb.IdTipoDocumento = pf.IdTipoDocumento
                        INNER JOIN Personas p ON p.Documento = pf.Documento AND p.IdTipoDocumento = pf.IdTipoDocumento
                        INNER JOIN TiposDocumento td ON td.IdTipoDocumento = pf.IdTipoDocumento
                        INNER JOIN Categorias c ON c.IdCategoria = p.IdCategoria
                        LEFT JOIN Empresas e ON e.Documento = pfcb.DocEmpresa AND e.IdTipoDocumento = pfcb.TipoDocEmpresa
                        WHERE 1 = 1";

                /**
             * @todo
             */
            /*if (empty($UsuarioEsGestion)) {
                     $sql .= " AND e.Documento = :IdEmpresaObj13 AND e.IdTipoDocumento = :IdEmpresaObj14";
                    
                    $binding[':IdEmpresaObj13'] = $IdEmpresaObj[0];
                    $binding[':IdEmpresaObj14'] = $IdEmpresaObj[1];
                } else*/ if (!empty($Args['IdEmpresa'])) {
                    $sql .= " AND e.Documento = :IdEmpresaObj15 AND e.IdTipoDocumento = :IdEmpresaObj16";
                    
                    $binding[':IdEmpresaObj15'] = $ArgsIdEmpresaObj[0];
                    $binding[':IdEmpresaObj16'] = $ArgsIdEmpresaObj[1];
                }
                
                if(!empty($Args['FechaDesde'])){
                    $sql .= " AND CONVERT(date, pfcb.FechaBaja, 103) >= CONVERT(date, :FechaDesde3, 103)";
                    $binding[':FechaDesde3'] = $Args['FechaDesde']. ' 00:00:00';
                }
                
                if(!empty($Args['FechaHasta'])){
                    $sql .= " AND CONVERT(date, pfcb.FechaBaja, 103) <= CONVERT(date, :FechaHasta3, 103)";
                    $binding[':FechaHasta3'] = $Args['FechaHasta']. ' 00:00:00';
                }

                if (!empty($Args['Busqueda'])) {
                    $sql .= " AND (pf.Documento LIKE :Busqueda12 OR "
                            . "pf.PrimerNombre LIKE :Busqueda13 OR "
                            . "pf.SegundoNombre LIKE :Busqueda14 OR "
                            . "pf.PrimerApellido LIKE :Busqueda15 OR "
                            . "pf.SegundoApellido LIKE :Busqueda16)";

                    $binding[':Busqueda12'] = "%" . $Args['Busqueda'] . "%";
                    $binding[':Busqueda13'] = "%" . $Args['Busqueda'] . "%";
                    $binding[':Busqueda14'] = "%" . $Args['Busqueda'] . "%";
                    $binding[':Busqueda15'] = "%" . $Args['Busqueda'] . "%";
                    $binding[':Busqueda16'] = "%" . $Args['Busqueda'] . "%";
                }

            }
            
            //$sql .= self::busquedaWhereFromArgs($Args, 'PF', $emp, $empctr);
        }

        //
        // VEHÍCULOS
        //

        if (!empty($Args['Vehiculos'])) {
            $emp = 'v';

            if (!empty($sql)) {
                $sql .= ' UNION ALL ';
            }
            
            if (!empty($Args['MostrarAltas'])) {
                $sql .= "SELECT 'func=AdmVehiculos|Serie=' + v.Serie + '|Numero=' + LTRIM(RTRIM(STR(v.Numero))) AS ObjUrl,                         
                                CONVERT(varchar(10), vca.FechaAlta, 103) AS Fecha,
                                CONVERT(varchar(10), vca.FechaHoraAlta, 103) + ' ' + CONVERT(varchar(8), vca.FechaHoraAlta, 108) AS FechaHora,
                                'Vehículo' AS Entidad,
                                v.Serie + ' ' + RTRIM(LTRIM(STR(v.Numero))) AS Identificacion,
                                mv.Descripcion + ' ' + v.Modelo + ' (' + tv.Descripcion + ')' AS Detalle,
                                v.Matricula,
                                vca.IdUsuarioAlta AS IdUsuario,
                                vca.NroContrato,
                                e.Nombre AS Empresa,
                                'Alta' AS Operacion,
                                c.Descripcion AS Categoria
                        FROM VehiculosContratosAltas vca
                        INNER JOIN Vehiculos v ON v.Serie = vca.Serie AND v.Numero = vca.Numero
                        INNER JOIN MarcasVehiculos mv ON mv.IdMarcaVehic = v.IdMarcaVehic
                        INNER JOIN TiposVehiculos tv ON tv.IdTipoVehiculo = v.IdTipoVehiculo
                        INNER JOIN Categorias c ON c.IdCategoria = v.IdCategoria
                        LEFT JOIN Empresas e ON e.Documento = vca.DocEmpresa AND e.IdTipoDocumento = vca.TipoDocEmpresa
                        WHERE 1 = 1";

                /**
             * @todo
             */
            /*if (empty($UsuarioEsGestion)) {
                    $sql .= " AND e.Documento = :IdEmpresaObj17 AND e.IdTipoDocumento = :IdEmpresaObj18";
                    
                    $binding[':IdEmpresaObj17'] = $IdEmpresaObj[0];
                    $binding[':IdEmpresaObj18'] = $IdEmpresaObj[1];
                } else*/ if (!empty($Args['IdEmpresa'])) {
                    $sql .= " AND e.Documento = :IdEmpresaObj19 AND e.IdTipoDocumento = :IdEmpresaObj20";
                    
                    $binding[':IdEmpresaObj19'] = $ArgsIdEmpresaObj[0];
                    $binding[':IdEmpresaObj20'] = $ArgsIdEmpresaObj[1];
                }

                if(!empty($Args['FechaDesde'])){
                    $sql .= " AND CONVERT(date, vca.FechaAlta, 103) >= CONVERT(date, :FechaDesde4, 103)";
                    $binding[':FechaDesde4'] = $Args['FechaDesde']. ' 00:00:00';
                }
                
                if(!empty($Args['FechaHasta'])){
                    $sql .= " AND CONVERT(date, vca.FechaAlta, 103) <= CONVERT(date, :FechaHasta4, 103)";
                    $binding[':FechaHasta4'] = $Args['FechaHasta']. ' 00:00:00';
                }
                
                if (!empty($Args['Busqueda'])) {
                    $sql .= " AND (v.Serie LIKE :Busqueda17 OR "
                            . "STR(v.Numero) LIKE :Busqueda18 OR "
                            . "mv.Descripcion LIKE :Busqueda19 OR "
                            . "tv.Descripcion LIKE :Busqueda20)";

                    $binding[':Busqueda17'] = "%" . $Args['Busqueda'] . "%";
                    $binding[':Busqueda18'] = "%" . $Args['Busqueda'] . "%";
                    $binding[':Busqueda19'] = "%" . $Args['Busqueda'] . "%";
                    $binding[':Busqueda20'] = "%" . $Args['Busqueda'] . "%";
                }

            }
            
            if (!empty($Args['MostrarBajas'])) {
                if (!empty($Args['MostrarAltas'])) {
                    $sql .= " UNION ALL ";
                }
                
                $sql .= "SELECT 'func=AdmVehiculos|Serie=' + v.Serie + '|Numero=' + LTRIM(RTRIM(STR(v.Numero))) AS ObjUrl,                         
                                CONVERT(varchar(10), vcb.FechaBaja, 103) AS Fecha,
                                CONVERT(varchar(10), vcb.FechaHoraBaja, 103) + ' ' + CONVERT(varchar(8), vcb.FechaHoraBaja, 108) AS FechaHora,
                                'Vehículo' AS Entidad,
                                v.Serie + ' ' + RTRIM(LTRIM(STR(v.Numero))) AS Identificacion,
                                mv.Descripcion + ' ' + v.Modelo + ' (' + tv.Descripcion + ')' AS Detalle,
                                v.Matricula,
                                vcb.IdUsuarioBaja AS IdUsuario,
                                vcb.NroContrato,
                                e.Nombre AS Empresa,
                                'Baja' AS Operacion,
                                c.Descripcion AS Categoria
                        FROM VehiculosContratosBajas vcb
                        INNER JOIN Vehiculos v ON v.Serie = vcb.Serie AND v.Numero = vcb.Numero
                        INNER JOIN MarcasVehiculos mv ON mv.IdMarcaVehic = v.IdMarcaVehic
                        INNER JOIN TiposVehiculos tv ON tv.IdTipoVehiculo = v.IdTipoVehiculo
                        INNER JOIN Categorias c ON c.IdCategoria = v.IdCategoria
                        LEFT JOIN Empresas e ON e.Documento = vcb.DocEmpresa AND e.IdTipoDocumento = vcb.TipoDocEmpresa
                        WHERE 1 = 1";

                /**
             * @todo
             */
            /*if (empty($UsuarioEsGestion)) {
                    $sql .= " AND e.Documento = '" . $IdEmpresaObj[0] . "' AND e.IdTipoDocumento = " . $IdEmpresaObj[1];
                    
                    $binding[':IdEmpresaObj21'] = $IdEmpresaObj[0];
                    $binding[':IdEmpresaObj22'] = $IdEmpresaObj[1];
                } else*/ if (!empty($Args['IdEmpresa'])) {
                    $sql .= " AND e.Documento = :IdEmpresaObj23 AND e.IdTipoDocumento = :IdEmpresaObj24";
                    
                    $binding[':IdEmpresaObj23'] = $ArgsIdEmpresaObj[0];
                    $binding[':IdEmpresaObj24'] = $ArgsIdEmpresaObj[1];
                }

                if(!empty($Args['FechaDesde'])){
                    $sql .= " AND CONVERT(date, vcb.FechaBaja, 103) >= CONVERT(date, :FechaDesde5, 103)";
                    $binding[':FechaDesde5'] = $Args['FechaDesde']. ' 00:00:00';
                }
                
                if(!empty($Args['FechaHasta'])){
                    $sql .= " AND CONVERT(date, vcb.FechaBaja, 103) <= CONVERT(date, :FechaHasta5, 103)";
                    $binding[':FechaHasta5'] = $Args['FechaHasta']. ' 00:00:00';
                }

                if (!empty($Args['Busqueda'])) {
                    $sql .= " AND (v.Serie LIKE :Busqueda21 OR "
                            . "STR(v.Numero) LIKE :Busqueda22 OR "
                            . "mv.Descripcion LIKE :Busqueda23 OR "
                            . "tv.Descripcion LIKE :Busqueda24)";

                    $binding[':Busqueda21'] = "%" . $Args['Busqueda'] . "%";
                    $binding[':Busqueda22'] = "%" . $Args['Busqueda'] . "%";
                    $binding[':Busqueda23'] = "%" . $Args['Busqueda'] . "%";
                    $binding[':Busqueda24'] = "%" . $Args['Busqueda'] . "%";
                }
            }

            //$sql .= self::busquedaWhereFromArgs($Args, 'VEH', $emp);
        }

        //$sql .= " ORDER BY FechaHora DESC";
        
        $data = [];
        if (!empty($sql)){
            $data = DB::select($sql, $binding);
        }

        $output = $this->req->input('output', 'json');
        if ($output !== 'json') {
            $dataOutput = array_map(function($item) {
                return [
                    'Fecha' => $item->Fecha,
                    'Empresa' => $item->Empresa,
                    'NroContrato' => $item->NroContrato,
                    'Entidad' => $item->Entidad,
                    'Identificacion' => $item->Identificacion,
                    'Detalle' => $item->Detalle,
                    'Categoria' => $item->Categoria,
                    'Matricula' => $item->Matricula,
                    'Operacion' => $item->Operacion,
                    'FechaHora' => $item->FechaHora,
                    'IdUsuario' => $item->IdUsuario,
                ];
            },$data);
            return $this->exportAltasBajas($dataOutput, $output);
        }

        return $data;
    }

    private function exportAltasBajas(array $data, string $type) {
        $filename = 'FSAcceso-Consulta-Altas-Bajas-' . date('Ymd his');
        $headers = [
            'Fecha' => 'Fecha',
            'Empresa' => 'Empresa',
            'NroContrato' => 'Contrato',
            'Entidad' => 'Entidad',
            'Identificacion' => 'Identificación',
            'Detalle' => 'Detalle',
            'Categoria' => 'Categoria',
            'NombreArea' => 'Matricula',
            'Operacion' => 'Operación',
            'FechaHora' => 'Fecha/Hora',
            'IdUsuario' => 'Usuario',
        ];
        return FsUtils::export($type, $data, $headers, $filename);
    }

    public function contratos()
    {
        $sql = "";

        $empresas = $this->req->input('Empresas');
        $contratos = $this->req->input('Contratos');

        $empresas = array_values(array_filter($empresas, function ($v, $i) use ($empresas) { return array_search($v, $empresas) === $i; }, ARRAY_FILTER_USE_BOTH));
        $contratos = array_values(array_filter($contratos, function ($v, $i) use ($contratos) { return array_search($v, $contratos) === $i; }, ARRAY_FILTER_USE_BOTH));
        
        $sql .= "SELECT
                'Máquina' AS Entidad,
                maq.NroSerie AS Identificacion,
                marcas.Descripcion + ' ' + maq.Modelo + ' (' + tipos.Descripcion + ')' AS Detalle,
                maq.Matricula,
                CASE maq.Estado
                    WHEN 1 THEN 'Activo'
                    ELSE 'Inactivo'
                END AS Estado,
                emp.Nombre AS Empresa,
                mc.NroContrato
                FROM Maquinas maq
                INNER JOIN TiposMaquinas tipos ON maq.IdTipoMaq = tipos.IdTipoMaquina
                INNER JOIN MarcasMaquinas marcas ON maq.IdMarcaMaq = marcas.IdMarcaMaq
                LEFT JOIN Empresas emp ON maq.DocEmpresa = emp.Documento AND maq.TipoDocEmp = emp.IdTipoDocumento
                LEFT JOIN MaquinasContratos mc ON mc.NroSerie = maq.NroSerie
                WHERE maq.Baja = 0
                AND maq.DocEmpresa + '-' + LTRIM(RTRIM(STR(maq.TipoDocEmp))) IN (" . implode(', ', array_map(function () { return '?'; }, $empresas)) . ") 
                AND mc.NroContrato IN (" . implode(', ', array_map(function () { return '?'; }, $contratos)) . ")";

        $sql .= " UNION ALL SELECT
                'Persona' AS Entidad,
                dbo.Mask(p.Documento, td.Mascara, 1, 1) AS Identificacion,
                pf.PrimerNombre + ' ' + pf.SegundoNombre + ' ' + pf.PrimerApellido + ' ' + pf.SegundoApellido AS Detalle,
                pf.Matricula,
                CASE pf.Estado
                    WHEN 1 THEN 'Activo'
                    ELSE 'Inactivo'
                END AS Estado,
                e.Nombre AS Empresa,
                pfc.NroContrato
                FROM Personas p 
                INNER JOIN PersonasFisicas pf ON (p.Documento = pf.Documento AND p.IdTipoDocumento = pf.IdTipoDocumento)
                INNER JOIN TiposDocumento td ON (p.IdTipoDocumento = td.IdTipoDocumento)
                LEFT JOIN PersonasFisicasEmpresas pfe ON (pf.Documento = pfe.Documento AND pf.IdTipoDocumento = pfe.IdTipoDocumento)
                LEFT JOIN PersonasFisicasContratos pfc ON (pf.Documento = pfc.Documento AND pf.IdTipoDocumento = pfe.IdTipoDocumento)
                LEFT JOIN Empresas e ON (pfe.DocEmpresa = e.Documento AND pfe.TipoDocEmpresa = e.IdTipoDocumento)
                WHERE p.Baja = 0 
                AND pf.Transito = 0
                AND pfe.DocEmpresa + '-' + LTRIM(RTRIM(STR(pfe.TipoDocEmpresa))) IN (" . implode(', ', array_map(function () { return '?'; }, $empresas)) . ") 
                AND pfc.NroContrato IN (" . implode(', ', array_map(function () { return '?'; }, $contratos)) . ")";

        $sql .= " UNION ALL SELECT
                'Vehículo' AS Entidad,
                vei.Serie + ' ' + RTRIM(LTRIM(STR(vei.Numero))) AS Identificacion,
                marcas.Descripcion + ' ' + vei.Modelo + ' (' + tipos.Descripcion + ')' AS Detalle,
                vei.Matricula,
                CASE vei.Estado
                    WHEN 1 THEN 'Activo'
                    ELSE 'Inactivo'
                END AS Estado,
                emp.Nombre AS Empresa,
                vc.NroContrato
                FROM Vehiculos vei 
                INNER JOIN TiposVehiculos tipos ON vei.IdTipoVehiculo = tipos.IdTipoVehiculo
                INNER JOIN MarcasVehiculos marcas ON vei.IdMarcaVehic = marcas.IdMarcaVehic
                LEFT JOIN Empresas emp ON vei.DocEmpresa = emp.Documento AND vei.TipoDocEmp = emp.IdTipoDocumento
                LEFT JOIN VehiculosContratos vc ON vc.Serie = vei.Serie AND vc.Numero = vei.Numero
                WHERE vei.Baja = 0
                AND vei.DocEmpresa + '-' + LTRIM(RTRIM(STR(vei.TipoDocEmp))) IN (" . implode(', ', array_map(function () { return '?'; }, $empresas)) . ") 
                AND vc.NroContrato IN (" . implode(', ', array_map(function () { return '?'; }, $contratos)) . ")";

        $data = DB::select($sql, array_merge($empresas, $contratos, $empresas, $contratos, $empresas, $contratos)); /// x3
                
        return $this->responsePaginate($data);
    }

    public function actualizarDocumentos() {

        DB::transaction(function () {

            $args = (object)$this->req->all();
            
            $bindings = [];
            $bindings[':idTipoDoc'] = $args->IdTipoDoc;
            $bindings[':idTipoDoc1'] = $args->IdTipoDoc;
            $bindings[':idTipoDoc2'] = $args->IdTipoDoc;
            $bindings[':idTipoDoc3'] = $args->IdTipoDoc;
            $bindings[':nuevaFechaVto'] = $args->NuevaFechaVto;
            $bindings[':nuevaFechaVto1'] = $args->NuevaFechaVto;

            if (isset($args->Entidad) && $args->Entidad === 'PF') {

                foreach ($args->Objetos as $obj) {

                    $objId = FsUtils::explodeId($obj);
                    $bindings[':idTipoDocumento'] = $objId[1];
                    $bindings[':idTipoDocumento1'] = $objId[1];
                    $bindings[':idTipoDocumento2'] = $objId[1];
                    $bindings[':idTipoDocumento3'] = $objId[1];
                    $bindings[':idTipoDocumento4'] = $objId[1];
                    $bindings[':documento'] = $objId[0];
                    $bindings[':documento1'] = $objId[0];
                    $bindings[':documento2'] = $objId[0];
                    $bindings[':documento3'] = $objId[0];
                    $bindings[':documento4'] = $objId[0];

                    $sql = "IF (SELECT count(*) FROM TiposDocPFCategorias tdpfc WHERE tdpfc.IdCategoria = (SELECT IdCategoria FROM Personas p WHERE p.IdTipoDocumento = :idTipoDocumento AND p.Documento = :documento) and tdpfc.IdTipoDocPF = :idTipoDoc) > 0 "
                                . "IF (SELECT count(*) FROM PersonasFisicasDocs pfd WHERE pfd.IdTipoDocumento = :idTipoDocumento1 AND pfd.Documento = :documento1 AND pfd.IdTipoDocPF = :idTipoDoc1) = 0 "
                                    . "INSERT INTO PersonasFisicasDocs (nrodoc, vto, idTipoDocPF, documento, idTipoDocumento) VALUES((SELECT ISNULL(MAX(nrodoc), 0) + 1 AS NroDocMax FROM PersonasFisicasDocs where documento = :documento2 AND idTipoDocumento = :idTipoDocumento2), CONVERT(datetime, :nuevaFechaVto, 103), :idTipoDoc2, :documento3, :idTipoDocumento3) "
                                . "ELSE "
                                    . "UPDATE PersonasFisicasDocs SET vto = CONVERT(datetime, :nuevaFechaVto1, 103) WHERE idTipoDocPF = :idTipoDoc3 AND documento = :documento4 AND idTipoDocumento = :idTipoDocumento4";
                    
                    DB::statement($sql, $bindings);
                }
            }

            if(isset($args->Entidad) && $args->Entidad === 'MAQ') {

                foreach ($args->Objetos as $obj) {
                    $bindings[':obj'] = $obj;
                    $bindings[':obj1'] = $obj;
                    $bindings[':obj2'] = $obj;
                    $bindings[':obj3'] = $obj;
                    $bindings[':obj4'] = $obj;

                    $sql = "IF (SELECT count(*) FROM TiposDocMaqCategorias tdmc WHERE tdmc.IdCategoria = (SELECT IdCategoria FROM Maquinas m WHERE m.NroSerie = :obj) and tdmc.IdTipoDocMaq = :idTipoDoc) > 0 "
                        . "IF (SELECT count(*) FROM MaquinasDocs md WHERE md.NroSerie = :obj1 AND md.IdTipoDocMaq = :idTipoDoc1) = 0 "
                            . "INSERT INTO MaquinasDocs (nrodoc, vto, idTipoDocMaq, nroserie) VALUES((SELECT ISNULL(MAX(nrodoc), 0) + 1 AS NroDocMax FROM MaquinasDocs where nroserie = :obj2), CONVERT(datetime, :nuevaFechaVto, 103), :idTipoDoc2, :obj3) "
                        . "ELSE "
                            . "UPDATE MaquinasDocs SET vto = CONVERT(datetime, :nuevaFechaVto1, 103) WHERE idTipoDocMaq = :idTipoDoc3 AND nroserie = :obj4";

                    DB::statement($sql, $bindings);
                }
            }

            if(isset($args->Entidad) && $args->Entidad === 'VEH') {
                    
                foreach ($args->Objetos as $obj) {

                    $objId = FsUtils::explodeId($obj);
                    $bindings[':numero'] = $objId[1];
                    $bindings[':numero1'] = $objId[1];
                    $bindings[':numero2'] = $objId[1];
                    $bindings[':numero3'] = $objId[1];
                    $bindings[':numero4'] = $objId[1];
                    $bindings[':serie'] = $objId[0];
                    $bindings[':serie1'] = $objId[0];
                    $bindings[':serie2'] = $objId[0];
                    $bindings[':serie3'] = $objId[0];
                    $bindings[':serie4'] = $objId[0];
                
                    $sql = "IF (SELECT count(*) FROM TiposDocVehicCategorias tdvc WHERE tdvc.IdCategoria = (SELECT IdCategoria FROM Vehiculos v WHERE v.Serie = :serie AND v.Numero = :numero) and tdvc.IdTipoDocVehic = :idTipoDoc) > 0 "
                        . "IF (SELECT count(*) FROM VehiculosDocs pfd WHERE pfd.numero = :numero1 AND pfd.serie = :serie1 AND pfd.IdTipoDocVehic = :idTipoDoc1) = 0 "
                            . "INSERT INTO VehiculosDocs (nrodoc, vto, idTipoDocVehic, serie, numero) VALUES((SELECT ISNULL(MAX(nrodoc), 0) + 1 AS NroDocMax FROM VehiculosDocs where serie = :serie2 AND numero = :numero2), CONVERT(datetime, :nuevaFechaVto, 103), :idTipoDoc2, :serie3, :numero3) "
                        . "ELSE "
                            . "UPDATE VehiculosDocs SET vto = CONVERT(datetime, :nuevaFechaVto1, 103) WHERE idTipoDocVehic = :idTipoDoc3 AND serie = :serie4 AND numero = :numero4";
                    
                    DB::statement($sql, $bindings);
                }
            }
        });
    }
}
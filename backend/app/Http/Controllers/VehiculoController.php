<?php

namespace App\Http\Controllers;

use App\FsUtils;
use App\Models\Acceso;
use App\Models\BaseModel;
use App\Models\Categoria;
use App\Models\Contrato;
use App\Models\Documento;
use App\Models\Empresa;
use App\Models\Incidencia;
use App\Models\LogAuditoria;
use App\Models\Matricula;
use App\Models\TipoDocumentoVehic;
use App\Models\Vehiculo;
use App\Models\Verificacion;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use App\Exceptions\OnGuardException;
use App\Exceptions\SigeException;
use App\ImprimirMatricula;
use Exception;
use App\Integrations\OnGuard;
use App\Integrations\Sige;
use App\Models\EmpresasTransporte;
use App\Models\Usuario;

class VehiculoController extends Controller
{

    const FS_CHART_COLORS = ["#0998a5", "#094aa5", "#cc0000", "#6B8E23", "#ffa500", "#808080", "#8b008b", "#4682b4"];

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

    public function grafico()
    {
        $Args = $this->req->all();
        return [
            'porMarca' => $this->chartpormarca($Args),
            'porTipo' => $this->chartportipo($Args),
            'habilitados' => $this->charthabilitados($Args),
            'porCategoria' => $this->chartporcategoria($Args),
        ];
    }

    private static function chartpormarca($Args) {
        $binding = Array();

        $sql = "SELECT V.idMarcaVehic, MV.descripcion AS Nombre, COUNT(*) AS Cantidad
                FROM Vehiculos V INNER JOIN Empresas E ON V.docEmpresa = E.documento AND V.tipoDocEmp = E.idTipoDocumento
                INNER JOIN MarcasVehiculos MV ON V.idMarcaVehic = MV.idMarcaVehic
                WHERE V.baja = 0";

        if (!empty($Args['NroContrato'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM VehiculosContratos VC
                        WHERE V.serie = VC.serie
                        AND V.numero = VC.numero
                        AND VC.nroContrato = :NroContrato)";
            $binding[':NroContrato'] = $Args['NroContrato'];
        }
            
        if (!empty($Args['IdAcceso'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM VehiculosAccesos VA
                        WHERE V.serie = VA.serie
                        AND V.numero = VA.numero
                        AND VA.idAcceso = :IdAcceso) ";
            $binding[':IdAcceso'] = $Args['IdAcceso'];
        }
            
        if (!empty($Args['Activos']) && $Args['Activos']){
            $sql .= " AND V.estado = 1 AND E.estado = 1";
        }
            

        $sql .= " GROUP BY V.idMarcaVehic, MV.descripcion";
        $sql .= " ORDER BY Cantidad DESC";
        
        $obj = DB::select($sql, $binding);

        $datasets = array();
        $i = 0;

        foreach ($obj as $m) {
                $marca = array("label" => $m->Nombre, "color" => self::FS_CHART_COLORS[$i], "value" => array($m->Cantidad));

            $datasets[] = $marca;
            $i++;

            if ($i == count(self::FS_CHART_COLORS))
                $i = 0;
        }
        
        return $datasets;
    }

    public function chartpormarcadetalle() {
        $binding = Array();
        $Args = $this->req->all();

        $sql = "SELECT v.Serie,
                        v.Numero,
                        e.Nombre AS Empresa,
                        c.Descripcion AS Categoria,
                        tv.Descripcion AS Tipo,
                        mv.Descripcion AS Marca,
                        v.Modelo AS Modelo,
                        CASE v.Estado
                                        WHEN 1 THEN 'Activo'
                                        ELSE 'Inactivo'
                        END AS Estado
                FROM Vehiculos V 
                INNER JOIN Categorias c ON c.IdCategoria = v.IdCategoria
                INNER JOIN TiposVehiculos tv ON tv.IdTipoVehiculo = v.IdTipoVehiculo
                INNER JOIN MarcasVehiculos mv ON mv.IdMarcaVehic = v.IdMarcaVehic
                INNER JOIN Empresas E ON V.docEmpresa = E.documento AND V.tipoDocEmp = E.idTipoDocumento
                WHERE V.baja = 0";

        if (!empty($Args['NroContrato'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM VehiculosContratos VC
                        WHERE V.serie = VC.serie
                        AND V.numero = VC.numero
                        AND VC.nroContrato = :NroContrato)";
            $binding[':NroContrato'] = $Args['NroContrato'];
        }
            

        if (!empty($Args['IdAcceso'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM VehiculosAccesos VA
                        WHERE V.serie = VA.serie
                        AND V.numero = VA.numero
                        AND VA.idAcceso = :IdAcceso) ";
            $binding[':IdAcceso'] = $Args['IdAcceso'];
        }
            
        
        if (!empty($Args['Activos']) && $Args['Activos']){
            $sql .= " AND V.estado = 1 AND E.estado = 1";
        }
            

        $sql .= " ORDER BY Marca";
        
        return DB::select($sql, $binding);
    }

    private static function chartportipo($Args) {

        $binding = Array();

        $sql = "SELECT V.idTipoVehiculo, TV.descripcion AS Nombre, COUNT(*) AS Cantidad
                FROM Vehiculos V INNER JOIN Empresas E ON V.docEmpresa = E.documento AND V.tipoDocEmp = E.idTipoDocumento
                INNER JOIN TiposVehiculos TV ON V.idTipoVehiculo = TV.idTipoVehiculo
                WHERE V.baja = 0";

        if (!empty($Args['NroContrato'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM VehiculosContratos VC
                        WHERE V.serie = VC.serie
                        AND V.numero = VC.numero
                        AND VC.nroContrato = :NroContrato)";
            $binding[':NroContrato'] = $Args['NroContrato'];
        }
            

        if (!empty($Args['IdAcceso'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM VehiculosAccesos VA
                        WHERE V.serie = VA.serie
                        AND V.numero = VA.numero
                        AND VA.idAcceso = :IdAcceso) ";
            $binding[':IdAcceso'] = $Args['IdAcceso'];
        }
            
        
        if (!empty($Args['Activos']) && $Args['Activos']){
            $sql .= " AND V.estado = 1 AND E.estado = 1";
        }
            

        $sql .= " GROUP BY V.idTipoVehiculo, TV.descripcion";
        $sql .= " ORDER BY Cantidad DESC";
        
        $obj = DB::select($sql, $binding);

        $datasets = array();
        $i = 0;

        foreach ($obj as $m) {
                $marca = array("label" => $m->Nombre, "color" => self::FS_CHART_COLORS[$i], "value" => array($m->Cantidad));

            $datasets[] = $marca;
            $i++;

            if ($i == count(self::FS_CHART_COLORS))
                $i = 0;
        }
        
        return $datasets;
    }

    public function chartportipodetalle() {
        $binding = Array();
        $Args = $this->req->all();

        $sql = "SELECT v.Serie,
                        v.Numero,
                        e.Nombre AS Empresa,
                        c.Descripcion AS Categoria,
                        tv.Descripcion AS Tipo,
                        mv.Descripcion AS Marca,
                        v.Modelo AS Modelo,
                        CASE v.Estado
                            WHEN 1 THEN 'Activo'
                            ELSE 'Inactivo'
                        END AS Estado
                FROM Vehiculos V 
                INNER JOIN Categorias c ON c.IdCategoria = v.IdCategoria
                INNER JOIN TiposVehiculos tv ON tv.IdTipoVehiculo = v.IdTipoVehiculo
                INNER JOIN MarcasVehiculos mv ON mv.IdMarcaVehic = v.IdMarcaVehic
                INNER JOIN Empresas E ON V.docEmpresa = E.documento AND V.tipoDocEmp = E.idTipoDocumento
                WHERE V.baja = 0";

        if (!empty($Args['NroContrato'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM VehiculosContratos VC
                        WHERE V.serie = VC.serie
                        AND V.numero = VC.numero
                        AND VC.nroContrato = :NroContrato)";
            $binding[':NroContrato'] = $Args['NroContrato'];
        }
            

        if (!empty($Args['IdAcceso'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM VehiculosAccesos VA
                        WHERE V.serie = VA.serie
                        AND V.numero = VA.numero
                        AND VA.idAcceso = :IdAcceso) ";
            $binding[':IdAcceso'] = $Args['IdAcceso'];
        }
            
        
        if (!empty($Args['Activos']) && $Args['Activos']){
            $sql .= " AND V.estado = 1 AND E.estado = 1";
        }
            

        $sql .= " ORDER BY Tipo";
        
        return DB::select($sql, $binding);
    }
    
    private static function charthabilitados($Args) {
        $binding = Array();

        $sql = "SELECT ";

        $sql .= "(SELECT COUNT(*)
                    FROM Vehiculos V 
                    INNER JOIN Empresas E ON V.docEmpresa = E.documento AND V.tipoDocEmp = E.idTipoDocumento
                    WHERE V.baja = 0
                    AND V.estado = 1
                    AND E.estado = 1";

        if (!empty($Args['NroContrato'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM VehiculosContratos VC
                        WHERE V.serie = VC.serie
                        AND V.numero = VC.numero
                        AND VC.nroContrato = :NroContrato)";
            $binding[':NroContrato'] = $Args['NroContrato'];
        }
            
        if (!empty($Args['IdAcceso'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM VehiculosAccesos VA
                        WHERE V.serie = VA.serie
                        AND V.numero = VA.numero
                        AND VA.idAcceso = :IdAcceso) ";
            $binding[':IdAcceso'] = $Args['IdAcceso'];
        }

        $sql .= " ) AS Habilitados, ";

        $sql .= "(SELECT COUNT(*)
                    FROM Vehiculos V INNER JOIN Empresas E ON V.docEmpresa = E.documento AND V.tipoDocEmp = E.idTipoDocumento
                    WHERE V.baja = 0
                    AND (V.estado = 0 OR E.estado = 0)";

        if (!empty($Args['NroContrato'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM VehiculosContratos VC
                        WHERE V.serie = VC.serie
                        AND V.numero = VC.numero
                        AND VC.nroContrato = :NroContrato1)";
            $binding[':NroContrato1'] = $Args['NroContrato'];
        }
            

        if (!empty($Args['IdAcceso'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM VehiculosAccesos VA
                        WHERE V.serie = VA.serie
                        AND V.numero = VA.numero
                        AND VA.idAcceso = :IdAcceso1)";
            $binding[':IdAcceso1'] = $Args['IdAcceso'];
        }
            

        $sql .= " ) AS NoHabilitados";

        $obj = DB::selectOne($sql, $binding);

        return array(
            array(
                "value" => $obj->Habilitados,
                "color" => self::FS_CHART_COLORS[0],
                "label" => "Habilitados"
            ),
            array(
                "value" => $obj->NoHabilitados,
                "color" => self::FS_CHART_COLORS[1],
                "label" => "No Habilitados"
            ),
        );
    }
    
    public function charthabilitadosdetalle() {
        $binding = Array();
        $Args = $this->req->all();

        $sql = "SELECT  v.Serie,
                        v.Numero,
                        'Habilitado' AS Habilitado,
                        e.Nombre AS Empresa,
                        c.Descripcion AS Categoria,
                        tv.Descripcion AS Tipo,
                        mv.Descripcion AS Marca,
                        v.Modelo AS Modelo,
                        CASE v.Estado
                            WHEN 1 THEN 'Activo'
                            ELSE 'Inactivo'
                        END AS Estado
                    FROM Vehiculos V 
                    INNER JOIN Categorias c ON c.IdCategoria = v.IdCategoria
                    INNER JOIN TiposVehiculos tv ON tv.IdTipoVehiculo = v.IdTipoVehiculo
                    INNER JOIN MarcasVehiculos mv ON mv.IdMarcaVehic = v.IdMarcaVehic
                    INNER JOIN Empresas E ON V.docEmpresa = E.documento AND V.tipoDocEmp = E.idTipoDocumento
                    WHERE V.baja = 0
                    AND V.estado = 1
                    AND E.estado = 1";

        if (!empty($Args['NroContrato'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM VehiculosContratos VC
                        WHERE V.serie = VC.serie
                        AND V.numero = VC.numero
                        AND VC.nroContrato = :NroContrato)";
            $binding[':NroContrato'] = $Args['NroContrato'];
        }
            
        if (!empty($Args['IdAcceso'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM VehiculosAccesos VA
                        WHERE V.serie = VA.serie
                        AND V.numero = VA.numero
                        AND VA.idAcceso = :IdAcceso) ";
            $binding[':IdAcceso'] = $Args['IdAcceso'];
        }
            
        $sql .= " UNION ALL ";

        $sql .= "SELECT v.Serie,
                        v.Numero,
                        'No habilitado' AS Habilitado,
                        e.Nombre AS Empresa,
                        c.Descripcion AS Categoria,
                        tv.Descripcion AS Tipo,
                        mv.Descripcion AS Marca,
                        v.Modelo AS Modelo,
                        CASE v.Estado
                            WHEN 1 THEN 'Activo'
                            ELSE 'Inactivo'
                        END AS Estado
                    FROM Vehiculos V 
                    INNER JOIN Categorias c ON c.IdCategoria = v.IdCategoria
                    INNER JOIN TiposVehiculos tv ON tv.IdTipoVehiculo = v.IdTipoVehiculo
                    INNER JOIN MarcasVehiculos mv ON mv.IdMarcaVehic = v.IdMarcaVehic
                    INNER JOIN Empresas E ON V.docEmpresa = E.documento AND V.tipoDocEmp = E.idTipoDocumento
                    WHERE V.baja = 0
                    AND (V.estado = 0 OR E.estado = 0)";

        if (!empty($Args['NroContrato'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM VehiculosContratos VC
                        WHERE V.serie = VC.serie
                        AND V.numero = VC.numero
                        AND VC.nroContrato = :NroContrato1)";
            $binding[':NroContrato1'] = $Args['NroContrato'];
        }
            

        if (!empty($Args['IdAcceso'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM VehiculosAccesos VA
                        WHERE V.serie = VA.serie
                        AND V.numero = VA.numero
                        AND VA.idAcceso = :IdAcceso1)";
            $binding[':IdAcceso1'] = $Args['IdAcceso'];
        }
            

        return DB::select($sql, $binding);
    }

    private static function chartporcategoria($Args) {
        $binding = Array();

        $sql = "SELECT V.idCategoria, C.descripcion AS Nombre, COUNT(*) AS Cantidad
                FROM Vehiculos V INNER JOIN Empresas E ON V.docEmpresa = E.documento AND V.tipoDocEmp = E.idTipoDocumento
                INNER JOIN Categorias C ON V.idCategoria = C.idCategoria
                WHERE V.baja = 0";

        if (!empty($Args['NroContrato'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM VehiculosContratos VC
                        WHERE V.serie = VC.serie
                        AND V.numero = VC.numero
                        AND VC.nroContrato = :NroContrato)";
            $binding[':NroContrato'] = $Args['NroContrato'];
        }
        
        if (!empty($Args['IdAcceso'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM VehiculosAccesos VA
                        WHERE V.serie = VA.serie
                        AND V.numero = VA.numero
                        AND VA.idAcceso = :IdAcceso) ";
            $binding[':IdAcceso'] = $Args['IdAcceso'];
        }
            
        if (!empty($Args->Activos) && $Args->Activos){
            $sql .= " AND V.estado = 1 AND E.estado = 1";
        }
            
        $sql .= " GROUP BY V.idCategoria, C.descripcion";
        $sql .= " ORDER BY Cantidad DESC";
        
        $obj = DB::select($sql, $binding);

        $datasets = array();
        $i = 0;

        foreach ($obj as $m) {
                $marca = array("label" => $m->Nombre, "color" => self::FS_CHART_COLORS[$i], "value" => array($m->Cantidad));

            $datasets[] = $marca;
            $i++;

            if ($i == count(self::FS_CHART_COLORS))
                $i = 0;
        }
        
        return $datasets;
    }

    public function chartporcategoriadetalle() {

        $binding = Array();
        $Args = $this->req->all();

        $sql = "SELECT v.Serie,
                        v.Numero,
                        e.Nombre AS Empresa,
                        c.Descripcion AS Categoria,
                        tv.Descripcion AS Tipo,
                        mv.Descripcion AS Marca,
                        v.Modelo AS Modelo,
                        CASE v.Estado
                            WHEN 1 THEN 'Activo'
                            ELSE 'Inactivo'
                        END AS Estado
                FROM Vehiculos V 
                INNER JOIN Categorias c ON c.IdCategoria = v.IdCategoria
                INNER JOIN TiposVehiculos tv ON tv.IdTipoVehiculo = v.IdTipoVehiculo
                INNER JOIN MarcasVehiculos mv ON mv.IdMarcaVehic = v.IdMarcaVehic
                INNER JOIN Empresas E ON V.docEmpresa = E.documento AND V.tipoDocEmp = E.idTipoDocumento
                WHERE V.baja = 0";

        if (!empty($Args['NroContrato'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM VehiculosContratos VC
                        WHERE V.serie = VC.serie
                        AND V.numero = VC.numero
                        AND VC.nroContrato = :NroContrato)";
            $binding[':NroContrato'] = $Args['NroContrato'];
        }
            
        if (!empty($Args['IdAcceso'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM VehiculosAccesos VA
                        WHERE V.serie = VA.serie
                        AND V.numero = VA.numero
                        AND VA.idAcceso = :IdAcceso) ";
            $binding[':IdAcceso'] = $Args['IdAcceso'];
        }
            
        if (!empty($Args['Activos']) && $Args['Activos']){
            $sql .= " AND V.estado = 1 AND E.estado = 1";
        }
            
        $sql .= " ORDER BY Categoria";
        
        return DB::select($sql, $binding);
    }

    public function index()
    {
        $binding = [];
        $sql = "SELECT  DISTINCT
                CASE vei.Estado
                    WHEN 1 THEN 'active'
                    ELSE 'inactive'
                END AS FsRC,
                '3' AS SortLevel,
                CASE vei.Estado
                    WHEN 1 THEN 'Activo'
                    ELSE 'Inactivo'
                END AS Estado,
                vei.Serie, 
                vei.Numero, 
                vei.IdTipoVehiculo, 
                vei.IdMarcaVehic, 
                vei.Modelo, 
                vei.Matricula, 
                vei.TAG,
                vei.TransportaMadera,
                vei.Tara,
                tipos.Descripcion as Tipo, 
                marcas.Descripcion as Marca, 
                emp.Nombre as Empresa
        FROM Vehiculos vei 
        INNER JOIN TiposVehiculos tipos ON vei.IdTipoVehiculo = tipos.IdTipoVehiculo
        INNER JOIN MarcasVehiculos marcas ON vei.IdMarcaVehic = marcas.IdMarcaVehic
        LEFT JOIN Empresas emp ON vei.DocEmpresa = emp.Documento AND vei.TipoDocEmp = emp.IdTipoDocumento
        WHERE vei.Baja = 0
        AND NOT EXISTS (SELECT veit.Serie, veit.Numero FROM VehiculosTransac veit WHERE veit.Completada = 0 AND veit.Serie = vei.Serie AND veit.Numero = vei.Numero)";

        $usuarioGestion = $this->user->isGestion();
        $idEmpresa = $this->req->input('IdEmpresa') == null ? null : $this->req->input('IdEmpresa');

        if ($idEmpresa !== null && empty($usuarioGestion))
        {
            $idEmpresaObj = FsUtils::explodeId($idEmpresa);
            $sql .= " AND vei.DocEmpresa = :doc_empresa AND vei.TipoDocEmp = :tipo_doc_empresa ";
            $binding[':doc_empresa'] = $idEmpresaObj[0];
            $binding[':tipo_doc_empresa'] = $idEmpresaObj[1];

        }else if(!$this->user->isGestion() && !$this->req->input('selectVehiculo')){

            $empresa = Empresa::loadBySession($this->req);
            $sql .= " AND vei.DocEmpresa = :doc_empresa AND vei.TipoDocEmp = :tipo_doc_empresa ";
            $binding[':doc_empresa'] = $empresa->Documento;
            $binding[':tipo_doc_empresa'] = $empresa->IdTipoDocumento;
        }

        if (null !== ($busqueda = $this->req->input('Busqueda'))) {
            $sql .= " AND (LTRIM(RTRIM(vei.Serie)) + vei.Numero COLLATE Latin1_general_CI_AI LIKE REPLACE(:busqueda_1, ' ', '') COLLATE Latin1_general_CI_AI OR "
                    . "tipos.Descripcion COLLATE Latin1_general_CI_AI LIKE :busqueda_2 COLLATE Latin1_general_CI_AI OR "
                    . "marcas.Descripcion COLLATE Latin1_general_CI_AI LIKE :busqueda_3 COLLATE Latin1_general_CI_AI OR "
                    . "vei.Modelo COLLATE Latin1_general_CI_AI LIKE :busqueda_4 COLLATE Latin1_general_CI_AI OR "
                    . "CONVERT(varchar(18), vei.matricula) COLLATE Latin1_general_CI_AI LIKE :busqueda_5 COLLATE Latin1_general_CI_AI OR "
                    . "emp.Nombre COLLATE Latin1_general_CI_AI LIKE :busqueda_6 COLLATE Latin1_general_CI_AI)";

            $binding[':busqueda_1'] = '%' . $busqueda . '%';
            $binding[':busqueda_2'] = '%' . $busqueda . '%';
            $binding[':busqueda_3'] = '%' . $busqueda . '%';
            $binding[':busqueda_4'] = '%' . $busqueda . '%';
            $binding[':busqueda_5'] = '%' . $busqueda . '%';
            $binding[':busqueda_6'] = '%' . $busqueda . '%';
        }

        $sql .= " ORDER BY vei.Serie ASC";

        $page = (int)$this->req->input('page', 1);

        $items = DB::select($sql, $binding);

        $output = $this->req->input('output', 'json');
        
        if ($output !== 'json') {
            $dataOutput = array_map(function($item) {
                return [
                    'Serie' => $item->Serie,
                    'Numero' => $item->Numero,
                    'Tipo' => $item->Tipo,
                    'Estado' => $item->Estado,
                    'Marca' => $item->Marca,
                    'Modelo' => $item->Modelo,
                    'Empresa' => $item->Empresa ? $item->Empresa : '',
                    'Matricula' => $item->Matricula ? $item->Matricula : '',
                    'TAG' => $item->TAG
                ];
            },$items);

            $filename = 'FSAcceso-Vehiculos-' . date('Ymd his');
            
            $headers = [
                'Serie' => 'Serie',
                'Numero' => 'Numero',
                'Tipo' => 'Tipo',
                'Matricula' => 'Matrícula',
                'Estado' => 'Estado',
                'Marca' => 'Marca',
                'Empresa' => 'Empresa',
                'Modelo' => 'Modelo',
                'TAG' => 'Matrícula Alfanumérica',
            ];
            
            return FsUtils::export($output, $dataOutput, $headers, $filename);
        }

        $paginate = FsUtils::paginateArray($items, $this->req);
        
        return $this->responsePaginate($paginate->items(), $paginate->total(), $page);
    }

    public function busqueda()
    {
        $args = $this->req->all();

        $idEmpresaObj = [];

        if (!empty($args['IdEmpresa'])) {
            $idEmpresaObj = FsUtils::explodeId($args['IdEmpresa']);
        }

        $binding = [];

        $sql = "SELECT DISTINCT 'func=AdmVehiculos|Serie=' + v.Serie + '|Numero=' + LTRIM(RTRIM(STR(v.Numero))) AS ObjUrl,
                        v.Serie,
                        v.Numero,
                        e.Nombre AS Empresa,
                        v.Matricula,
                        CASE v.Estado WHEN 1 THEN 'Activo' ELSE 'Inactivo' END AS Estado,
                        c.Descripcion AS Categoria,
                        tv.Descripcion AS Tipo,
                        mv.Descripcion AS Marca,
                        v.Modelo AS Modelo,
                        v.Propietario,
                        vc.NroContrato AS NroContrato
                FROM Vehiculos v
                INNER JOIN Categorias c ON c.IdCategoria = v.IdCategoria
                INNER JOIN TiposVehiculos tv ON tv.IdTipoVehiculo = v.IdTipoVehiculo
                INNER JOIN MarcasVehiculos mv ON mv.IdMarcaVehic = v.IdMarcaVehic
                LEFT JOIN VehiculosContratos vc ON v.Serie = vc.Serie AND v.Numero = vc.Numero
                LEFT JOIN Empresas e ON e.Documento = v.DocEmpresa AND e.IdTipoDocumento = v.TipoDocEmp";

            $bs = 'v.Baja = 0';

            if (isset($args)) {
                $js = "";
                $ws = "";

                foreach ($args as $key => $value) {
                    switch ($key) {
                        case 'output':
                        case 'token':
                        case 'page':
                        case 'pageSize':
                            break;
                        case 'Baja':
                            if ($value == 1)
                                $bs = "v.Baja IN (0, 1)";
                        break;

                        case 'IdEmpresa':
                            $e = FsUtils::explodeId($value);
                            $ws .= (empty($ws) ? " WHERE " : " AND ") . "v.DocEmpresa = :docEmpresa AND v.TipoDocEmp = :tipoDocEmpresa";
                            $binding[':docEmpresa'] = $e[0];
                            $binding[':tipoDocEmpresa'] = $e[1];
                        break;

                        default:
                            switch ($key) {
                                case 'IdCategoria':
                                case 'IdMarcaVehic':
                                case 'IdTipoVehiculo':
                                    $keys = 'v.' . $key;
                                    $ws .= (empty($ws) ? " WHERE " : " AND ") . $keys . " = :".$key;
                                    $binding[':'.$key] = $value;
                                break;
                                default:
                                    $values = ':'.$key;
                                    $ws .= (empty($ws) ? " WHERE " : " AND ") . "v.".$key . " LIKE $values ";
                                    $binding[$values] = "%" . $value . "%";
                                break;
                            }
                        break;
                    }
                }
            }

        $usuarioGestion = $this->user->isGestion();

        if (empty($usuarioGestion)) {
            $ws .= (empty($ws) ? " WHERE " : " AND ") . "e.Documento = :Documento" . " AND " . "e.IdTipoDocumento = :idTipoDocumento";
            $binding[':Documento'] = $idEmpresaObj[0];
            $binding[':idTipoDocumento'] = $idEmpresaObj[1];
        }

        $sql .= $js . $ws . (empty($ws) ? " WHERE " : " AND ") . $bs;

        // return self::ejecutarSQLyPaginar($Usuario, $IdEmpresa, $sql, "SELECT * FROM #BusquedaMaquinas bm", $MaxFilas, $NroPagina, "#BusquedaMaquinas", "bm", "Marca, Modelo, NroSerie");

        $items = DB::select($sql, $binding);

        $output = isset($args['output']);
        
        if ($output !== 'json' && $output == true) {

            $output = $args['output'];
            
            $dataOutput = array_map(function($item) {
                return [
                    'Serie' => $item->Serie,
                    'Numero' => $item->Numero,
                    'Propietario' => $item->Propietario,
                    'Marca' => $item->Marca,
                    'Modelo' => $item->Modelo,
                    'Tipo' => $item->Tipo,
                    'Categoria' => $item->Categoria,
                    'Empresa' => $item->Empresa,
                    'Matricula' => $item->Matricula,
                    'NroContrato' => $item->NroContrato,
                    'Estado' => $item->Estado,
                ];
            },$items);

            $filename = 'FSAcceso-Vehiculos-Consulta-' . date('Ymd his');
            
            $headers = [
                'Serie' => 'Serie',
                'Numero' => 'Número',
                'Propietario' => 'Propietario',
                'Marca' => 'Marca',
                'Modelo' => 'Modelo',
                'Tipo' => 'Tipo',
                'Categoria' => 'Categoría',
                'Empresa' => 'Empresa',
                'Matricula' => 'Matrícula',
                'NroContrato' => 'Contrato',
                'Estado' => 'Estado'
            ];
            
            return FsUtils::export($output, $dataOutput, $headers, $filename);
        }

        $page = (int)$this->req->input('page', 1);

        $paginate = FsUtils::paginateArray($items, $this->req);
        
        return $this->responsePaginate($paginate->items(), $paginate->total(), $page);
    }

    public function show(string $serie, string $numero)
    {
        $entity = $this->show_interno($serie, $numero);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Vehículo no encontrado');
        }

        return $this->response($entity);
    }

    public function show_interno(string $serie, string $numero)
    {
        $entity = $this->showTransac($serie, $numero);
        
        if (!isset($entity)) {
            $entity = $this->showNoTransac($serie, $numero);
        }
        return $entity;
    }

    public function showTransac(string $serie, string $numero): ?object 
    {
        $binding  = [
            ':serie' => trim($serie),
            ':numero' => trim($numero)
        ];

        $sql = "SELECT TOP 1 
                    CASE v.Accion
                       WHEN 'A' THEN 'pending' 
                       WHEN 'M' THEN 'mod-pending'
                    END AS FsRC,
                    v.Archivo, 
                    v.Serie, 
                    v.Numero, 
                    v.IdCategoria, 
                    v.EnTransito, 
                    v.IdTipoVehiculo, 
                    tv.Descripcion as Tipo, 
                    v.IdMarcaVehic, 
                    mv.Descripcion as Marca, 
                    v.Modelo, 
                    v.DocEmpresa, 
                    v.TipoDocEmp, 
                    v.DocEmpresa + '-' + LTRIM(RTRIM(STR(v.TipoDocEmp))) as IdEmpresa,
                    v.Propietario,
                    v.Conductor, 
                    v.Matricula, 
                    v.TAG, 
                    v.Estado,
                    v.NotifEntrada,
                    v.EmailsEntrada,
                    v.NotifSalida,
                    v.EmailsSalida,
                    tv.Descripcion + ' ' + mv.Descripcion + ' ' + v.Modelo AS Detalle,
                    '' AS Observaciones
            FROM VehiculosTransac v 
            INNER JOIN TiposVehiculos tv ON v.IdTipoVehiculo = tv.IdTipoVehiculo
            INNER JOIN MarcasVehiculos mv ON v.IdMarcaVehic = mv.IdMarcaVehic
            WHERE v.Serie = :serie AND v.Numero = :numero AND v.Completada = 0";

        $entity = DB::selectOne($sql, $binding);

        if (isset($entity)) {
            $entity->Documentos = TipoDocumentoVehic::list($serie, $numero, 'Transac');
            $entity->Contratos = Contrato::loadByVehiculo($serie, $numero, 'Transac');

            $previous_entity = $this->showNoTransac($serie, $numero);
            if(isset($previous_entity)) {
                $entity->FsMV = $previous_entity;
            }
        }

        $entity = FsUtils::castProperties($entity, Vehiculo::$castProperties);
        
        return $entity;
    }

    public function showNoTransac(string $serie, string $numero): ?object 
    {
        $binding  = [
            ':serie' => trim($serie),
            ':numero' => trim($numero)
        ];

        $sql = "SELECT v.Archivo, 
                        v.Serie, 
                        v.Numero, 
                        v.IdCategoria, 
                        v.EnTransito, 
                        v.IdTipoVehiculo, 
                        tv.Descripcion as Tipo, 
                        v.IdMarcaVehic, 
                        mv.Descripcion as Marca, 
                        v.Modelo, 
                        v.DocEmpresa, 
                        v.TipoDocEmp, 
                        v.DocEmpresa + '-' + LTRIM(RTRIM(STR(v.TipoDocEmp))) as IdEmpresa,
                        e.Nombre AS Empresa,
                        e.Nombre AS NombreEmpresa,
                        vc.NroContrato,
                        v.Propietario,
                        v.Conductor, 
                        v.Matricula, 
                        v.TAG, 
                        CONVERT(varchar(10), v.VigenciaDesde, 103) AS FechaVigenciaDesde, 
                        CONVERT(varchar(5), v.VigenciaDesde, 108) AS HoraVigenciaDesde,
                        CONVERT(varchar(10), v.VigenciaHasta, 103) AS FechaVigenciaHasta, 
                        CONVERT(varchar(5), v.VigenciaHasta, 108) AS HoraVigenciaHasta,
                        v.VigenciaDesde,
                        v.VigenciaHasta,
                        v.Estado,
                        v.ControlLlegada,
                        v.IdOrigDest,
                        v.TransportaMadera,
                        CASE WHEN v.TransportaMadera = 1 THEN 'S' ELSE 'N' END AS TransportaMaderaSN,
                        v.Tara,
                        p.IdPais AS PaisEmpresa,
                        pa.Nombre AS NombrePaisEmpresa,
                        v.NotifEntrada,
                        v.EmailsEntrada,
                        v.NotifSalida,
                        v.EmailsSalida,
                        c.Descripcion AS Categoria,
                        c.Sige AS SincConSIGE,
                        c.CatLenel,
                        tv.Descripcion + ' ' + mv.Descripcion + ' ' + v.Modelo AS Detalle,
                        v.Baja,
                        Observaciones
                FROM Vehiculos v 
                INNER JOIN TiposVehiculos tv ON v.IdTipoVehiculo = tv.IdTipoVehiculo
                INNER JOIN MarcasVehiculos mv ON v.IdMarcaVehic = mv.IdMarcaVehic
                INNER JOIN Categorias c ON c.IdCategoria = v.IdCategoria
                LEFT JOIN Empresas e ON e.Documento = v.DocEmpresa AND e.IdTipoDocumento = v.TipoDocEmp
                LEFT JOIN Personas p ON e.Documento = p.Documento AND e.IdTipoDocumento = p.IdTipoDocumento
                LEFT JOIN Paises pa ON p.IdPais = pa.IdPais
                LEFT JOIN VehiculosContratos vc ON vc.Serie = v.Serie AND vc.Numero = v.Numero
                WHERE v.Serie = :serie AND v.Numero = :numero ";

        $entity = DB::selectOne($sql, $binding);

        if (isset($entity)) {
            $entity->Documentos = TipoDocumentoVehic::list($serie, $numero);
            $entity->Contratos = Contrato::loadByVehiculo($serie, $numero);
            $entity->Incidencias = Incidencia::loadByVehiculo($serie, $numero);
            $entity->Verificaciones = Verificacion::loadByVehiculo($serie, $numero);
            $entity->Accesos = Acceso::loadByVehiculo($serie, $numero);
        }

        $entity = FsUtils::castProperties($entity, Vehiculo::$castProperties);

        return $entity;
    }

    public function create(?object $args = null)
    {
        if (!isset($args)) {
            $args = (object)$this->req->all();
        }

        return DB::transaction(function () use ($args) {
            Vehiculo::exigirArgs($args, ['Serie', 'Numero', 'IdTipoVehiculo', 'IdCategoria', 'IdEmpresa', 'IdMarcaVehic', 'Modelo']); // , 'FechaVigenciaDesde'
            Vehiculo::comprobarArgs($args); // NO IMPLEMENTADO.

            $args->Serie = strtoupper($args->Serie);

            /// @todo $empresa = self::explodeIdEmpresa($args);
            $entity = $this->showNoTransac($args->Serie, $args->Numero);
            $transac = false;
            // Para integrar con OnGuard
            $matriculaFSA = Categoria::gestionaMatriculaEnFSA($args->IdCategoria);

            if (!isset($entity)) {
                if ($this->user->isGestion() || $args->NoTransac) {
                    if (!$matriculaFSA && env('INTEGRADO', 'false') === true) {
                        Vehiculo::obtenerMatriculaDesdeOnGuard($args);
                    }

                    Matricula::disponibilizar(isset($args->Matricula) ? $args->Matricula : null);
                    
                    $this->insertEntity($args);
                } else {
                    $this->abmEntityTransac($args, 'A');
                    $transac = true;
                }
            } else {
                if ($entity->Baja == 1) {
                    $this->update($args->Serie, $args->Numero, $args);

                    if (!$matriculaFSA && env('INTEGRADO', 'false') === true) {
                        Vehiculo::obtenerMatriculaDesdeOnGuard($args);
                    }

                    DB::update("UPDATE Vehiculos SET Baja = 0 WHERE Serie = ? AND Numero = ? ", [$args->Serie, $args->Numero]);
                        LogAuditoria::log(
                            Auth::id(),
                            Vehiculo::class,
                            LogAuditoria::FSA_METHOD_CREATE,
                            $args,
                            implode('', [$args->Serie, $args->Numero]),
                            sprintf('%s %s %s (%s)', $entity->IdTipoVehiculo, $entity->IdMarcaVehic, $entity->Modelo, Vehiculo::id($entity))
                        );
                    return null;
                } else {
                    throw new ConflictHttpException("El vehículo ya existe");
                }
            }

            if (!$transac && !empty($args->Estado)) {
                $this->activar_interno($args);
            } else if (empty($args->Estado)) {
                $this->desactivar_interno($args);
            }

            $onguard = !$transac && Categoria::sincConOnGuard($args->IdCategoria) && env('INTEGRADO', 'false') === true;
            $sige = !$transac && empty($args->NoSincSige) && Categoria::sincConSIGE($args->IdCategoria);

            if ($onguard || $sige) {
                try {
                    $detalle = $this->showNoTransac($args->Serie, $args->Numero);
                    if ($onguard) {
                        OnGuard::altaVehiculo(
                            Vehiculo::id($detalle),
                            $detalle->Marca,
                            $detalle->Modelo,
                            $detalle->Tipo,
                            $detalle->Categoria,
                            $detalle->Empresa,
                            $detalle->NroContrato,
                            (Categoria::gestionaMatriculaEnFSA($detalle->IdCategoria) ? $detalle->Matricula : null),
                            $detalle->Estado,
                            $detalle->CatLenel,
                            $detalle->VigenciaDesde,
                            $detalle->VigenciaHasta,
                            Categoria::gestionaMatriculaEnFSA($detalle->IdCategoria) ? OnGuard::CONTINGENCY_O : OnGuard::CONTINGENCY_U,
                            $detalle->TAG
                        );
                    }
                    if ($sige) {
                        Sige::altaCamion(
                            $detalle,
                            Vehiculo::id($detalle),
                            $detalle->Marca,
                            $detalle->Modelo,
                            $detalle->TransportaMaderaSN,
                            $detalle->Tara,
                            $detalle->DocEmpresa,
                            $detalle->TipoDocEmp,
                            $detalle->NombreEmpresa,
                            $detalle->PaisEmpresa,
                            $detalle->NombrePaisEmpresa
                        );
                    }
                } catch (SigeException $ex) {
                    OnGuard::bajaVehiculo(Vehiculo::id($detalle));
                    throw new ConflictHttpException($ex->getMessage());
                    // throw $ex;
                }
            }

            if (!$transac && !empty($args->Matricula)) {
                Vehiculo::logCambioMatricula($args, 'Alta');
            }

            return null;
        });
    }

    public function update(string $serie, string $numero, $args = null) {
        if (!isset($args)) {
            $args = (object)$this->req->all();
        }
        $args2 = clone $args;

        DB::transaction(function () use ($serie, $numero, $args, $args2) {
            Vehiculo::comprobarArgs($args);

            if ($this->user->isGestion()) {
                $entityTransac = $this->showTransac($serie, $numero);
                if (isset($entityTransac)) {
                    return $this->aprobar($serie, $numero);
                } else {
                    $entityNoTransac = $this->showNoTransac($serie, $numero);

                    if (empty($entityNoTransac)) {
                        if (empty($args->CreateIfNotExists)) {
                            throw new NotFoundHttpException('El vehículo que está intentando modificar no existe');
                        } else {
                            $this->create($args);
                            $args2 = $this->showNoTransac($serie, $numero);
                        }
                    }

                    /**
                     * Esto no tiene sentido, ¿para qué `NewArgs`? (!?)
                     * cls_vehiculo_mz.php:849
                     */
                    $matriculaFSA = Categoria::gestionaMatriculaEnFSA($args2->IdCategoria);
                    if (!$matriculaFSA && env('INTEGRADO', 'false') === true) {
                        $NewArgs = clone $args2;
                        Vehiculo::obtenerMatriculaDesdeOnGuard($args2);
                        if (!empty($NewArgs->FechaVigenciaDesde)) $args2->FechaVigenciaDesde = $NewArgs->FechaVigenciaDesde;
                        if (!empty($NewArgs->HoraVigenciaDesde)) $args2->HoraVigenciaDesde = $NewArgs->HoraVigenciaDesde;
                        if (!empty($NewArgs->FechaVigenciaHasta)) $args2->FechaVigenciaHasta = $NewArgs->FechaVigenciaHasta;
                        if (!empty($NewArgs->HoraVigenciaHasta)) $args2->HoraVigenciaHasta = $NewArgs->HoraVigenciaHasta;
                    }

                    $this->updateEntity($serie, $numero, $args2);
                }
            } else if (!empty($args->NoTransac)) {
                $entityNoTransac = $this->showNoTransac($serie, $numero);
                if (empty($entityNoTransac)) {
                    throw new NotFoundHttpException('El vehículo que está intentando modificar no existe');
                } 
                $this->updateEntityNoTransac($serie, $numero, $args);

                LogAuditoria::log(
                    Auth::id(),
                    Vehiculo::class,
                    LogAuditoria::FSA_METHOD_UPDATE,
                    $args,
                    implode('', [$args->Serie, $args->Numero]),
                    sprintf('%s %s %s (%s)', $args->IdTipoVehiculo, $args->IdMarcaVehic, $args->Modelo, Vehiculo::id($args))
                );
                return false;
            } else {
                return $this->abmEntityTransac($args, 'M');
            }

            // Si el método llegó hasta acá entonces $entityTransac no es Transac
            if (!empty($args->Estado)) {
                $this->activar_interno($args);
            } else if (empty($args->Estado)) {
                $this->desactivar_interno($args);
            }

            $onguard = Categoria::sincConOnGuard($args->IdCategoria) && env('INTEGRADO', 'false') === true;
            $sige = empty($args->NoSincSige) && Categoria::sincConSIGE($args->IdCategoria);

            $checkBaja = DB::selectOne('SELECT Baja FROM Vehiculos WHERE Serie = ? AND Numero = ?', [$args->Serie, $args->Numero]);
            $respawning = !empty($checkBaja->Baja);
            
            if ($respawning) {
                DB::update('UPDATE Vehiculos SET Baja = 0 WHERE Serie = ? AND Numero = ?', [$args->Serie, $args->Numero]);
            }

            $detalle = $this->showNoTransac($args->Serie, $args->Numero);
            
            try {
                if ($onguard) {
                    call_user_func_array([OnGuard::class, $respawning ? 'altaVehiculo' : 'modificacionVehiculo'], [
                        Vehiculo::id($detalle),
                        $detalle->Marca,
                        $detalle->Modelo,
                        $detalle->Tipo,
                        $detalle->Categoria,
                        $detalle->Empresa,
                        $detalle->NroContrato,
                        $detalle->Matricula,
                        $detalle->Estado,
                        $detalle->CatLenel,
                        $detalle->VigenciaDesde,
                        $detalle->VigenciaHasta,
                        OnGuard::CONTINGENCY_O,
                        $detalle->TAG,
                    ]);
                }
                if ($sige) {
                    call_user_func_array([Sige::class, $respawning ? 'altaCamion' : 'modificacionCamion'], [
                        $detalle,
                        Vehiculo::id($detalle),
                        $detalle->Marca,
                        $detalle->Modelo,
                        $detalle->TransportaMaderaSN,
                        $detalle->Tara,
                        $detalle->DocEmpresa,
                        $detalle->TipoDocEmp,
                        $detalle->NombreEmpresa,
                        $detalle->PaisEmpresa,
                        $detalle->NombrePaisEmpresa,
                    ]);
                }
            } catch (SigeException $ex) {
                if ($onguard) {
                    call_user_func_array([OnGuard::class, $respawning ? 'bajaVehiculo' : 'modificacionVehiculo'], [
                        Vehiculo::id($entityNoTransac), 
                        $entityNoTransac->Marca,
                        $entityNoTransac->Modelo,
                        $entityNoTransac->Tipo,
                        $entityNoTransac->Categoria,
                        $entityNoTransac->Empresa,
                        $entityNoTransac->NroContrato,
                        $entityNoTransac->Matricula,
                        $entityNoTransac->Estado,
                        $entityNoTransac->CatLenel,
                        $entityNoTransac->VigenciaDesde,
                        $entityNoTransac->VigenciaHasta,
                        OnGuard::CONTINGENCY_O,
                        $entityNoTransac->TAG,
                    ]);
                }
                throw new ConflictHttpException($ex->getMessage());
                // throw $ex;
            }

            LogAuditoria::log(
                Auth::id(),
                Vehiculo::class,
                LogAuditoria::FSA_METHOD_UPDATE,
                $args,
                implode('', [$args->Serie, $args->Numero]),
                sprintf('%s %s %s (%s)', $args->IdTipoVehiculo, $args->IdMarcaVehic, $args->Modelo, Vehiculo::id($args))
            );
        });
    }

    public function delete(string $serie, string $numero ) {

        DB::transaction(function () use ($serie, $numero) {

            $entity = $this->show($serie, $numero)->getData();
            
            $this->abmEntityTransac($entity, 'D', true);

            DB::update(
                'UPDATE Vehiculos '
                . 'SET Baja = 1, '
                . 'Matricula = NULL, '
                . 'FechaHoraBaja = GETDATE(), '
                . 'IdUsuarioBaja = ? '
                . 'WHERE Serie = ? '
                . 'AND Numero = ?',
            [Auth::id(), $serie, $numero]);

            $onguard = Categoria::sincConOnGuard($entity->IdCategoria) && env('INTEGRADO', 'false') === true;
            $sige = $entity->NoSincSige && Categoria::sincConSIGE($entity->IdCategoria) && env('INTEGRADO', 'false') === true;

            try {
                if ($onguard) {
                    OnGuard::bajaVehiculo(Vehiculo::id($entity));
                }
                if ($sige) {
                    Sige::bajaCamion($entity, Vehiculo::id($entity), $entity->Marca, $entity->Modelo, $entity->TransportaMaderaSN, $entity->Tara, $entity->DocEmpresa, $entity->TipoDocEmp, $entity->NombreEmpresa, $entity->PaisEmpresa, $entity->NombrePaisEmpresa);
                }
            } catch (SigeException $ex) {
                if ($onguard) {
                    OnGuard::altaVehiculo(
                        Vehiculo::id($entity),
                        $entity->Marca,
                        $entity->Modelo,
                        $entity->Tipo,
                        $entity->Categoria,
                        $entity->Empresa,
                        $entity->NroContrato,
                        Categoria::gestionaMatriculaEnFSA($entity->IdCategoria) ? $entity->Matricula : null,
                        $entity->Estado,
                        $entity->CatLenel,
                        $entity->VigenciaDesde,
                        $entity->VigenciaHasta,
                        Categoria::gestionaMatriculaEnFSA($entity->IdCategoria) ? OnGuard::CONTINGENCY_O : OnGuard::CONTINGENCY_U,
                        $entity->TAG
                    );
                }
                throw new ConflictHttpException($ex->getMessage());
                // throw $ex;
            }

            LogAuditoria::log(
                Auth::id(),
                Vehiculo::class,
                LogAuditoria::FSA_METHOD_DELETE,
                $entity,
                implode('', [$serie, $numero]),
                sprintf('%s %s %s (%s)', $entity->IdTipoVehiculo, $entity->IdMarcaVehic, $entity->Modelo, Vehiculo::id($entity))
            );
        });
    }

    public function activar(string $serie, string $numero, ?bool $forzarActivacion = false) {
        $entity = $this->showNoTransac($serie, $numero);
        $entity->ForzarActivacion = $forzarActivacion;
        $this->activar_interno($entity);
    }

    public function desactivar(string $serie, string $numero) {
        $entity = $this->showNoTransac($serie, $numero);
        $this->desactivar_interno($entity);
    }

    public function aprobar(string $serie, string $numero) {

        $entity = $this->showTransac($serie, $numero);

        if(!isset($entity)) {
            throw new NotFoundHttpException('La entidad no puede ser aprobada porque no existe una transacción');
        }
        $this->aprobar_interno($entity);
    }

    public function rechazar(string $serie, string $numero) {
        $entity = $this->showTransac($serie, $numero);

        if(!isset($entity)) {
            throw new NotFoundHttpException('No existe una transacción para la entidad que intenta rechazar');
        }
        $this->rechazar_interno($entity);
    }

    public function sincronizar(string $serie, string $numero) {
        $entity = $this->show_interno($serie, $numero);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Vehiculo no encontrado');
        }
        $this->update($serie, $numero, $entity);
    }

    public function cambiarIdentificador(string $serie, string $numero)
    {
        $nuevaSerie = $this->req->input('NuevoSerie');
        $nuevoNumero = $this->req->input('NuevoNumero');
        $nuevaSerie = strtoupper($nuevaSerie);
        
        $entity = $this->showNoTransac($serie, $numero);

        if (empty($entity)) {
            throw new NotFoundHttpException("El vehiculo no existe");
        }

        /*$yaVinculado = EmpresasTransporte::where('Serie', $serie)->where('Numero', $numero)->first();
        
        if (isset($yaVinculado)) {
            throw new ConflictHttpException('No se puede cambiar el identificador de un vehiculo que está asociado a un transporte.');
        }*/

        $entityTransac = $this->showTransac($serie, $numero);
       
        if (isset($entityTransac)) {
            throw new ConflictHttpException('No se puede cambiar el identificador de un vehiculo que tiene modificaciones pendientes de aprobacion');
        }

        $entityTransac = $this->show_interno($nuevaSerie, $nuevoNumero);

        if (isset($entityTransac)) {
            throw new ConflictHttpException('El identificador que acaba de ingresar ya se encuentra utilizado');
        }

        $tables = [
            ['Eventos', 'Serie|Numero'],
            ['EventosDuplicados', 'Serie|Numero'],
            ['HISTVehiculos', 'Serie|Numero'],
            ['HISTVehiculosActivos', 'Serie|Numero'],
            ['HISTVehiculosDocs', 'Serie|Numero'],
            ['HISTVehiculosDocsItems', 'Serie|Numero'],
            ['Vehiculos', 'Serie|Numero'],
            ['VehiculosAccesos', 'Serie|Numero'],
            ['VehiculosContratos', 'Serie|Numero'],
            ['VehiculosContratosAltas', 'Serie|Numero'],
            ['VehiculosContratosBajas', 'Serie|Numero'],
            ['VehiculosDocs', 'Serie|Numero'],
            ['VehiculosDocsItems', 'Serie|Numero'],
            ['VehiculosIncidencias', 'Serie|Numero'],
            ['VehiculosListaNegra', 'Serie|Numero'],
            ['VehiculosMatriculas', 'Serie|Numero'],
            ['VehiculosTransac', 'Serie|Numero'],
            ['VehiculosTransacContratos', 'Serie|Numero'],
            ['VehiculosTransacDocs', 'Serie|Numero'],
            ['VehiculosTransacDocsItems', 'Serie|Numero'],
            ['VehiculosVerificaciones', 'Serie|Numero'],
            ['EmpresasTransportes', 'Serie|Numero'],
        ];

        $args = (object)[
            'Serie' => $serie,
            'Numero' => $numero,
            'NuevaSerie' => $nuevaSerie,
            'NuevoNumero' => $nuevoNumero,
        ];

        DB::transaction(function () use ($tables, $args, $entity) {
            Vehiculo::cambiarIdentificador($tables, $args);
            $nuevaEntity = $this->show_interno($args->NuevaSerie, $args->NuevoNumero);
            
            $onGuard = Categoria::sincConOnGuard($entity->IdCategoria) && env('INTEGRADO', 'false') === true;
            $sige = Categoria::sincConOnGuard($entity->IdCategoria) && env('INTEGRADO', 'false') === true;

            try {
                if ($onGuard && env('INTEGRADO', 'false') === true) {
                    OnGuard::bajaVehiculo(Vehiculo::id($entity));
                    OnGuard::altaVehiculo(Vehiculo::id($nuevaEntity), $nuevaEntity->Marca, $nuevaEntity->Modelo, $nuevaEntity->Tipo, $nuevaEntity->Categoria, $nuevaEntity->Empresa, $nuevaEntity->NroContrato, (Categoria::gestionaMatriculaEnFSA($nuevaEntity->IdCategoria) ? $nuevaEntity->Matricula : null), $nuevaEntity->Estado, $nuevaEntity->CatLenel, $nuevaEntity->VigenciaDesde, $nuevaEntity->VigenciaHasta, Categoria::gestionaMatriculaEnFSA($nuevaEntity->IdCategoria) ? "O" : "U");
                }
                if ($sige && env('INTEGRADO', 'false') === true) {
                    Sige::bajaCamion($entity, Vehiculo::id($entity), $entity->Marca, $entity->Modelo, $entity->TransportaMaderaSN, $entity->Tara, $entity->DocEmpresa, $entity->TipoDocEmp, $entity->NombreEmpresa, $entity->PaisEmpresa, $entity->NombrePaisEmpresa);
                    Sige::altaCamion($nuevaEntity, Vehiculo::id($nuevaEntity), $nuevaEntity->Marca, $nuevaEntity->Modelo, $nuevaEntity->TransportaMaderaSN, $nuevaEntity->Tara, $nuevaEntity->DocEmpresa, $nuevaEntity->TipoDocEmp, $nuevaEntity->NombreEmpresa, $nuevaEntity->PaisEmpresa, $nuevaEntity->NombrePaisEmpresa);
                }
            } catch (SigeException $ex) {
                OnGuard::bajaVehiculo(Vehiculo::id($nuevaEntity));
                OnGuard::altaVehiculo(Vehiculo::id($entity), $entity->Marca, $entity->Modelo, $entity->Tipo, $entity->Categoria, $entity->Empresa, $entity->NroContrato, (Categoria::gestionaMatriculaEnFSA($entity->IdCategoria) ? $entity->Matricula : null), $entity->Estado, $entity->CatLenel, $entity->VigenciaDesde, $entity->VigenciaHasta, Categoria::gestionaMatriculaEnFSA($entity->IdCategoria) ? "O" : "U");
                throw new ConflictHttpException($ex->getMessage());
                // throw $ex;
            }

            LogAuditoria::log(
                Auth::id(),
                'vehiculo',
                'cambio de id',
                $args,
                implode('', [$args->Serie, $args->Numero]),
                sprintf('%s %s %s (%s)', $entity->IdTipoVehiculo, $entity->IdMarcaVehic, $entity->Modelo, $args->NuevaSerie . $args->NuevoNumero)
            );
        });
    }

    public function comprobarIdentificador(string $serie, string $numero)
    {
        return Vehiculo::comprobarIdentificador((object)[
            'Serie' => $serie,
            'Numero' => $numero,
        ]);
    }

    public function cambiarMatricula(string $serie, string $numero, ?int $matricula = null)
    {
        if (!isset($matricula)) {
            $matricula = $this->req->input('Matricula');
        }

        return DB::transaction(function () use ($serie, $numero, $matricula) {
            $entityTransac = $this->showTransac($serie, $numero);
            
            if (isset($entityTransac)) {
                throw new ConflictHttpException('No se puede cambiar la matrícula de un vehículo que tiene modificaciones pendientes de aprobación');
            }

            $entity = $this->showNoTransac($serie, $numero);

            if (!isset($entity)) {
                throw new NotFoundHttpException('Vehículo no encontrado');
            }

            Matricula::disponibilizar($matricula ? $matricula : null);

            DB::update(
                "UPDATE Vehiculos SET Matricula = ? WHERE Serie = ? AND Numero = ? ",
                [$matricula, $entity->Serie, $entity->Numero]
            );

            if (empty($matricula)) {
                $entity->Estado = 0;
                $this->desactivar_interno($entity);
            }

            // COMENTARIO DE CÓDIGO VIEJO - LO DEJO POR SI LLEGA A SUCEDER ALGUN ERROR RELACIONADO:

            // Debido a que primero se realiza una baja interna y esta intenta cambiar el BadgeStatus en OnGuard
            // Luego, intenta cambiar el BadgeId y si no existe, intenta borrarlo.
            // Pero ya que falla al principio, porque al no contar con matricula, la borra de la DB
            // y luego intenta enviar null como BadgeId a OnGuard. Entonces, falla, y ya no procede a darlo de baja.
            // Es por esto que en wsdesactivar_interno, pregunto si tiene Matricula y si no tiene borro el BadgeId de OnGuard.
            // No sé por qué antes no estaba dando error y si también estaba dando error,
            // no sé por qué no interrumpía el proceso y al final si lo bajaba en OnGuard.

            $onguard = Categoria::sincConOnGuard($entity->IdCategoria) && env('INTEGRADO', 'false') === true;
            $sige = Categoria::sincConSIGE($entity->IdCategoria);

            if ($onguard) {
                if (!empty($entity->Matricula)) {
                    OnGuard::cambiarTarjetaEntidadLenel(
                        Vehiculo::id($entity),
                        $entity->Matricula,
                        $entity->Estado,
                        $entity->CatLenel,
                        $entity->VigenciaDesde,
                        $entity->VigenciaHasta,
                        OnGuard::ENTIDAD_VEHICULO,
                        $entity->TAG
                    );
                } else {
                    OnGuard::bajarTarjetaEntidadLenel(Vehiculo::id($entity));
                }
            }

            Vehiculo::logCambioMatricula($entity, 'Cambio de Matrícula');

            LogAuditoria::log(
                Auth::id(),
                Vehiculo::class,
                LogAuditoria::FSA_METHOD_UPDATE,
                'cambio de matrícula',
                implode('', [$entity->Serie, $entity->Numero]),
                sprintf('%s %s %s (%s)', $entity->IdTipoVehiculo, $entity->IdMarcaVehic, $entity->Modelo, Vehiculo::id($entity))
            );
        });
    }

    // IMPRIMIR MATRICULA
    public function imprimirMatriculaEnBase64(string $serie, string $numero)
    {    
        $entity = $this->show_interno($serie, $numero);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Vehículo no encontrada');
        }
        $impresionMatricula = new ImprimirMatricula();
        $imagen = $impresionMatricula->imprimir($entity);
        return $imagen;
    }

    public function cambiarTag(string $serie, string $numero, ?string $tag = null)
    {
        if (!isset($tag)) {
            $tag = $this->req->input('TAG');
        }

        return DB::transaction(function () use ($serie, $numero, $tag) {
            $entityTransac = $this->showTransac($serie, $numero);
            
            if (isset($entityTransac)) {
                throw new ConflictHttpException('No se puede cambiar la matrícula de un vehículo que tiene modificaciones pendientes de aprobación');
            }

            $entity = $this->showNoTransac($serie, $numero);
            $entity->TAG = $tag;

            if (!isset($entity)) {
                throw new ConflictHttpException('Vehículo no encontrado');
            }

            Vehiculo::disponibilizarTag(isset($tag) ? $tag : null);

            DB::update(
                "UPDATE Vehiculos SET TAG = ? WHERE Serie = ? AND Numero = ? ",
                [$tag, $serie, $numero]
            );

            if (empty($tag)) {
                $entity->Estado = 0;
                $this->desactivar_interno($entity);
            }
    
            // No hace falta actualizar en SIGE, ya que la invocación ha
            // de venir desde SIGE (directa o indirectamente a través de un WS público).
            $onguard = Categoria::sincConOnGuard($entity->IdCategoria) && env('INTEGRADO', 'false') === true;
            $sige = false;

            if ($onguard) {
                if (!empty($tag)) {
                    OnGuard::cambiarTarjetaEntidadLenel(
                        Vehiculo::id($entity),
                        $entity->Matricula,
                        $entity->Estado,
                        $entity->CatLenel,
                        $entity->VigenciaDesde,
                        $entity->VigenciaHasta,
                        OnGuard::ENTIDAD_VEHICULO,
                        $entity->TAG
                    );
                } else {
                    OnGuard::bajarTarjetaEntidadLenel(Vehiculo::id($entity));
                }
            }

            LogAuditoria::log(
                Auth::id(),
                Vehiculo::class,
                'Cambio de TAG',
                $entity,
                implode('', [$entity->Serie, $entity->Numero]),
                sprintf('%s %s %s (%s)', $entity->IdTipoVehiculo, $entity->IdMarcaVehic, $entity->Modelo, Vehiculo::id($entity))
            );
        });
    }

    private function insertEntity(object $args)
    {
        if (!empty($args->IdEmpresa)) {
            $IdEmpresaObj = FsUtils::explodeId($args->IdEmpresa);
        }

        $entity = new Vehiculo((array)$args);
        $entity->Serie = trim($args->Serie);
        $entity->Numero = trim($args->Numero);
        $entity->IdTipoVehiculo = $args->IdTipoVehiculo;
        $entity->IdMarcaVehic = $args->IdMarcaVehic;
        $entity->Modelo = $args->Modelo;
        $entity->Propietario = @$args->Propietario;
        $entity->Conductor = @$args->Conductor;
        $entity->DocEmpresa = isset($IdEmpresaObj[0]) ? $IdEmpresaObj[0] : null;
        $entity->TipoDocEmp = isset($IdEmpresaObj[1]) ? $IdEmpresaObj[1] : null;
        $entity->IdCategoria = $args->IdCategoria;
        $entity->Estado = isset($args->Estado) ? $args->Estado : false;
        $entity->Foto = isset($args->Foto) ? $args->Foto : null;
        $entity->FechaHoraAlta = new Carbon;
        $entity->IdUsuarioAlta = Auth::id();
        $entity->EnTransito = @$args->EnTransito;
        $entity->Matricula = !empty($args->Matricula) ? $args->Matricula : null;

        if (!empty($args->FechaVigenciaDesde) && !empty($args->HoraVigenciaDesde)) {
            $entity->VigenciaDesde = FsUtils::fromHumanDatetime($args->FechaVigenciaDesde  . ' ' . substr($args->HoraVigenciaDesde, 0, 5) . ':00');
        }
        if (!empty($args->FechaVigenciaHasta) && !empty($args->HoraVigenciaHasta)) {
            $entity->VigenciaHasta = FsUtils::fromHumanDatetime($args->FechaVigenciaHasta  . ' ' . substr($args->HoraVigenciaHasta, 0, 5) . ':00');
        }

        $entity->NotifEntrada = isset($args->NotifEntrada) ? $args->NotifEntrada : null;
        $entity->NotifSalida = isset($args->NotifSalida) ? $args->NotifSalida : null;
        $entity->EmailsEntrada = isset($args->EmailsEntrada) ? $args->EmailsEntrada : null;
        $entity->EmailsSalida = isset($args->EmailsSalida) ? $args->EmailsSalida : null;
        $entity->NroContratoCompra = isset($args->NroContratoCompra) ? $args->NroContratoCompra : null;
        $entity->ControlLlegada = isset($args->ControlLlegada) ? $args->ControlLlegada : null;
        $entity->IdOrigDest = isset($args->IdOrigDest) ? $args->IdOrigDest : null;
        $entity->TransportaMadera = isset($args->TransportaMadera) ? $args->TransportaMadera : null;
        
        if (isset($entity->TransportaMadera) && $entity->TransportaMadera == false) {
            if (!isset($args->Tara)) {
                throw new ConflictHttpException("No ingreso una Tara");
            }
            $entity->Tara = isset($args->Tara) ? $args->Tara : null;
        }
        $entity->Observaciones = isset($args->Observaciones) ? $args->Observaciones : null;
        $entity->Baja = false;

        $entity->save();

        Contrato::createByVehiculo($args);
        Vehiculo::altaDocumentos($args);
        Verificacion::createByVehiculo($args);
        Incidencia::createByVehiculo($args);
        Acceso::createByVehiculo($args);
    }

    private function updateEntityNoTransac(string $serie, string $numero, object $args)
    {
        $entity = Vehiculo::where('Serie', $serie)->where('Numero', $numero)->firstOrFail();
        $entity->fill((array)$args);

        $entity->save();

        /*Contrato::createByVehiculo($args, true);
        Vehiculo::altaDocumentos($args, true);
        Verificacion::createByVehiculo($args, true);
        Incidencia::createByVehiculo($args, true);
        Acceso::createByVehiculo($args, true);*/
    }

    private function updateEntity(string $serie, string $numero, object $args)
    {
        $entity = Vehiculo::where('Serie', $serie)->where('Numero', $numero)->firstOrFail();
        $entity->fill((array)$args);

        if ($this->user->isGestion()) {
            if (!empty($args->FechaVigenciaDesde) && !empty($args->HoraVigenciaDesde) && !empty($args->FechaVigenciaHasta) && !empty($args->HoraVigenciaHasta)) {
                $entity->VigenciaDesde = FsUtils::fromHumanDatetime($args->FechaVigenciaDesde  . ' ' . substr($args->HoraVigenciaDesde, 0, 5) . ':00');
                $entity->VigenciaHasta = FsUtils::fromHumanDatetime($args->FechaVigenciaHasta  . ' ' . substr($args->HoraVigenciaHasta, 0, 5) . ':00');
            } else {
                if (!empty($args->VigenciaDesde) && !empty($args->HoraVigenciaDesde) && !empty($args->VigenciaHasta) && !empty($args->HoraVigenciaHasta)) {
                    $entity->VigenciaDesde = FsUtils::fromHumanDatetime($args->VigenciaDesde  . ' ' . substr($args->HoraVigenciaDesde, 0, 5) . ':00');
                    $entity->VigenciaHasta = FsUtils::fromHumanDatetime($args->VigenciaHasta  . ' ' . substr($args->HoraVigenciaHasta, 0, 5) . ':00');
                }
            }
            $entity->NotifEntrada = isset($args->NotifEntrada) ? $args->NotifEntrada : null;
            $entity->NotifSalida = isset($args->NotifSalida) ? $args->NotifSalida : null;
            $entity->EmailsEntrada = isset($args->EmailsEntrada) ? $args->EmailsEntrada : null;
            $entity->EmailsSalida = isset($args->EmailsSalida) ? $args->EmailsSalida : null;

            $entity->TransportaMadera = isset($args->TransportaMadera) ? $args->TransportaMadera : null;
        
            if (isset($entity->TransportaMadera) && $entity->TransportaMadera == false) {
                if (!isset($args->Tara) || trim($args->Tara) == "") {
                    throw new ConflictHttpException("No ingreso una Tara");
                }
                $entity->Tara = isset($args->Tara) ? $args->Tara : null;
            }

            $entity->Observaciones = isset($args->Observaciones) ? $args->Observaciones : null;
        }
        $entity->save();

        Contrato::createByVehiculo($args, true);
        Vehiculo::altaDocumentos($args, true);
        Verificacion::createByVehiculo($args, true);
        Incidencia::createByVehiculo($args, true);
        Acceso::createByVehiculo($args, true);
    }

    private function abmEntityTransac($args, $action = '', $reset = true) {
        // Verificamos si existe una Transaccion de Alta ya ingresada, si existe la cambiamos a modo A
        if ($action === 'M') {
            $result = DB::selectOne(
                "SELECT * FROM VehiculosTransac WHERE Accion = 'A' AND Completada = 0 AND Serie = ? AND Numero = ?",
                [$args->Serie, $args->Numero]
            );
            if(isset($result)) {
                $action = 'A';
            }
        }

        if ($action === 'A' && !$this->user->isGestion()) {
            unset($args->Matricula);
        }

        if ($reset || $action === 'D') {
            $ac = $action != "D" ? " Accion = '" . $action . "' AND " : "";
            DB::delete(
                'DELETE FROM VehiculosTransac WHERE ' .$ac . ' Completada = 0 ' . 'AND Serie = ? AND Numero = ?',
                [$args->Serie, $args->Numero]
            );
        }

        if ($action !== 'D') {
            $entityTransac = new Vehiculo;
            $entityTransac->setTable($entityTransac->getTable() . 'Transac');
            $entityTransac->AccionFechaHora = new Carbon;
            $entityTransac->Accion = $action;
            $entityTransac->AccionIdUsuario = $this->user->getKey();
            $entityTransac->Completada = 0;
            $entityTransac->FechaHoraAlta = new Carbon;
            $entityTransac->IdUsuarioAlta = $this->user->getKey();
            
            if (is_array($args)) {
                $entityTransac->Serie = array_key_exists('Serie', $args) && !empty($args['Serie']);
                $entityTransac->Numero = array_key_exists('Numero', $args) && !empty($args['Numero']);
                $entityTransac->EnTransito = array_key_exists('EnTransito', $args) && !empty($args['EnTransito']);
            } else {
                $entityTransac->Serie = $args->Serie;
                $entityTransac->Numero = $args->Numero;
                $entityTransac->IdTipoVehiculo = $args->IdTipoVehiculo;
                $entityTransac->IdMarcaVehic = $args->IdMarcaVehic;
                $entityTransac->Modelo = $args->Modelo;
                $entityTransac->Propietario = $args->Propietario;
                $entityTransac->Conductor = $args->Conductor;
                $entityTransac->EnTransito = isset($args->EnTransito);
                $entityTransac->Matricula = $args->Matricula;
                $entityTransac->IdCategoria = $args->IdCategoria;
                $entityTransac->Estado = isset($args->Estado) ? $args->Estado : false;
                $entityTransac->NotifEntrada = isset($args->NotifEntrada) ? $args->NotifEntrada : null;
                $entityTransac->NotifSalida = isset($args->NotifSalida) ? $args->NotifSalida : null;
                $entityTransac->EmailsEntrada = isset($args->EmailsEntrada) ? $args->EmailsEntrada : null;
                $entityTransac->EmailsSalida = isset($args->EmailsSalida) ? $args->EmailsSalida : null;
            }
            $entityTransac->save();
    
            Contrato::createByVehiculo($args, $reset, 'Transac');
            Vehiculo::altaDocumentos($args, $reset, 'Transac');
        }
    }

    private function activar_interno(object $args)
    {
        BaseModel::exigirArgs(FsUtils::classToArray($args), ['Serie', 'Numero', 'Estado']);
        $args->Estado = 1;
        Vehiculo::esActivable($args);

        // ¿Aún es necesario?
        // if (!mzcategoria::gestionaMatriculaEnFSA($obj)) {
        //     self::obtenerMatriculaDesdeOnGuard($obj);
        // }

        DB::transaction(function () use ($args) {
            DB::update(
                'UPDATE Vehiculos SET Estado = ? WHERE Serie = ? AND Numero = ? ',
                [$args->Estado, $args->Serie, $args->Numero]
            );

            $entity = $this->showNoTransac($args->Serie, $args->Numero);

            OnGuard::modificarTarjetaEntidadLenel(
                Vehiculo::id($entity),
                $entity->Matricula,
                1,
                $entity->CatLenel,
                $entity->VigenciaDesde,
                $entity->VigenciaHasta,
                OnGuard::ENTIDAD_VEHICULO,
                $entity->TAG
            );

            LogAuditoria::log(
                Auth::id(),
                Vehiculo::class,
                LogAuditoria::FSA_METHOD_ACTIVATE,
                $entity,
                implode('', [$entity->Serie, $entity->Numero]),
                sprintf('%s %s %s (%s)', $entity->IdTipoVehiculo, $entity->IdMarcaVehic, $entity->Modelo, Vehiculo::id($entity))
            );
        });
    }

    private function desactivar_interno(object $args)
    {
        BaseModel::exigirArgs(FsUtils::classToArray($args), ['Serie', 'Numero', 'Estado']);
        $args->Estado = 0;
        DB::transaction(function () use ($args) {
            DB::update(
                'UPDATE Vehiculos SET Estado = ? WHERE Serie = ? AND Numero = ? ',
                [$args->Estado, $args->Serie, $args->Numero]
            );

            OnGuard::deshabilitarEntidadLenel(Vehiculo::id($args));

            LogAuditoria::log(
                Auth::id(),
                Vehiculo::class,
                LogAuditoria::FSA_METHOD_DESACTIVATE,
                $args,
                implode('', [$args->Serie, $args->Numero]),
                sprintf('%s %s %s (%s)', $args->IdTipoVehiculo, $args->IdMarcaVehic, $args->Modelo, Vehiculo::id($args))
            );
        });
    }

    private function aprobar_interno(object $args)
    {
        return DB::transaction(function () use ($args) {
            $existeNoTransac = $this->showNoTransac($args->Serie, $args->Numero);

            if(!$existeNoTransac) {
                $this->insertEntity($args);
                $result = DB::update(
                    'UPDATE VehiculosTransac SET Completada = 1 WHERE Serie = ? AND Numero = ? AND Accion = \'A\' AND Completada = 0',
                    [$args->Serie, $args->Numero]
                );
            } else {
                $this->updateEntity($args->Serie, $args->Numero, $args);
                $result = DB::update(
                    'UPDATE VehiculosTransac SET Completada = 1 WHERE Serie = ? AND Numero = ? AND Accion = \'M\' AND Completada = 0',
                    [$args->Serie, $args->Numero]
                );
            }

            // No está desarrollado en FSAccesoWeb
            // if ((!$existeNoTransac && Categoria::sincConSIGE($args)) || ($existeNoTransac && @$existeNoTransac->SincConSIGE)) {
            //     if ($objExiste) {
            //         Sige::modificacion($Args);
            //     } else {
            //         Sige::alta($Args);
            //     }
            // }

            // if ($result != true) {
            //     throw new ConflictHttpException('Ocurrio un error al aprobar el Vehículo');
            // }

            return true;
            // return $result;
        });
    }

    private function rechazar_interno(object $args)
    {
        $return = DB::transaction(function () use ($args) {
            $existeTransac = $this->showTransac($args->Serie, $args->Numero);

            if (!$existeTransac) {
                throw new NotFoundHttpException('El vehiculo que esta intentando rechazar no existe');
            }
            
            DB::update(
                'UPDATE VehiculosTransac SET Completada = 2 WHERE Serie = ? AND Numero = ? AND Completada = 0',
                [$args->Serie, $args->Numero]
            );

            LogAuditoria::log(
                Auth::id(),
                Vehiculo::class,
                LogAuditoria::FSA_METHOD_REJECT,
                $args,
                implode('', [$args->Serie, $args->Numero]),
                sprintf('%s %s %s (%s)', $args->IdTipoVehiculo, $args->IdMarcaVehic, $args->Modelo, Vehiculo::id($args))
            );
            return true;
        });

        if ($return !== true) {
            throw new HttpException('Ocurrio un error al rechazar el Vehículo');
        }
    }

    public function subirFoto(string $serie, string $numero) {

        $transac = null!==$this->showTransac($serie, $numero);

        $table = null;
        if($this->user->Gestion && !$transac){
            $table = 'Vehiculos';
        }else{
            $table = 'VehiculosTransac';
        }
		$MaquinaDoc = DB::select("Select Archivo from " . $table . " WHERE Serie = :serie and Numero = :numero", [":serie" => $serie, ":numero" => $numero]);

        if(!empty($MaquinaDoc[0]->Archivo)){
            $pathName = storage_path('app/uploads/vehiculos/fotos/'.$MaquinaDoc[0]->Archivo);
            if (file_exists($pathName)) unlink($pathName);
        }
        
        
		$retornoUpdate = false;
        $file = $this->req->file('Archivo-file');
        $filename = 'Vehiculos-' . $serie . '-' . $numero . '-' . uniqid() . '.' . $file->getClientOriginalExtension();

        $retornoUpdate = DB::update("UPDATE ".$table." SET Archivo = :filenamee WHERE Serie = :serie and Numero = :numero", [":serie" => $serie, ":numero" => $numero, ":filenamee" => $filename]);

        $file->storeAs('uploads/vehiculos/fotos', $filename);

        if ($retornoUpdate) {
            $data = [
                'filename' => $filename,
            ];

            return $data;
        } else {
            throw new HttpException(409, 'Error al guardar el archivo');
        }
    }

    public function subirDocs() {

        $Args = $this->req->All();
        
        $file = $this->req->file('Archivo-file');
        $file->storeAs('uploads/vehiculos/docs', $Args['filename']);

        return true;
    }

    public function verArchivo($carpeta, $fileName){

        $adjunto = storage_path('app/uploads/vehiculos/'. $carpeta .'/'.$fileName);

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
}
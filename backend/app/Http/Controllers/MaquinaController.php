<?php

namespace App\Http\Controllers;

use App\FsUtils;
use App\ImprimirMatricula;
use App\Models\Acceso;
use App\Models\BaseModel;
use Carbon\Carbon;
use App\Models\Categoria;
use App\Models\Contrato;
use App\Models\Documento;
use App\Models\Empresa;
use App\Models\Incidencia;
use App\Models\LogAuditoria;
use App\Models\Maquina;
use App\Models\Matricula;
use App\Models\TipoDocumentoMaq;
use App\Models\Verificacion;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Models\Usuario;
use App\Exceptions\OnGuardException;
use App\Integrations\OnGuard;

class MaquinaController extends Controller 
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

        $sql = "SELECT M.idMarcaMaq, MM.descripcion AS Nombre, COUNT(*) AS Cantidad
                FROM Maquinas M INNER JOIN Empresas E ON M.docEmpresa = E.documento AND M.tipoDocEmp = E.idTipoDocumento
                INNER JOIN MarcasMaquinas MM ON M.idMarcaMaq = MM.idMarcaMaq
                WHERE M.baja = 0";

        if (!empty($Args['NroContrato'])){
            $sql .= " AND EXISTS (SELECT *
                     FROM MaquinasContratos MC
                     WHERE M.nroSerie = MC.nroSerie
                     AND MC.nroContrato = :NroContrato)";
            $binding[':NroContrato'] = $Args['NroContrato'];
        }
            

        if (!empty($Args['IdAcceso'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM MaquinasAccesos MA
                        WHERE M.nroSerie = MA.nroSerie
                        AND MA.idAcceso = :IdAcceso) ";
            $binding[':IdAcceso'] = $Args['IdAcceso'];
        }
            
        
        if (!empty($Args['Activos']) && $Args['Activos']){
            $sql .= " AND M.estado = 1 AND E.estado = 1";
        }

        $sql .= " GROUP BY M.idMarcaMaq, MM.descripcion";
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

    public static function chartpormarcadetalle() {

        $binding = Array();

        $sql = "SELECT m.NroSerie,
                        mm.Descripcion AS Marca,
                        m.Modelo AS Modelo,
                        tm.Descripcion AS Tipo,
                        c.Descripcion AS Categoria,
                        CASE m.Estado
                                WHEN 1 THEN 'Activo'
                                ELSE 'Inactivo'
                        END AS Estado,
                        e.Nombre AS Empresa
                FROM Maquinas M 
                INNER JOIN Categorias c ON c.IdCategoria = m.IdCategoria
                INNER JOIN TiposMaquinas tm ON tm.IdTipoMaquina = m.IdTipoMaq
                INNER JOIN MarcasMaquinas mm ON mm.IdMarcaMaq = m.IdMarcaMaq
                INNER JOIN Empresas e ON e.Documento = m.DocEmpresa AND e.IdTipoDocumento = m.TipoDocEmp
                WHERE M.baja = 0";

        if (!empty($Args['NroContrato'])){
            $sql .= " AND EXISTS (SELECT *
                     FROM MaquinasContratos MC
                     WHERE M.nroSerie = MC.nroSerie
                     AND MC.nroContrato = :NroContrato)";
            $binding[':NroContrato'] = $Args['NroContrato'];
        }
            

        if (!empty($Args['IdAcceso'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM MaquinasAccesos MA
                        WHERE M.nroSerie = MA.nroSerie
                        AND MA.idAcceso = :IdAcceso) ";
            $binding[':IdAcceso'] = $Args['IdAcceso'];
        }
            
        
        if (!empty($Args['Activos']) && $Args['Activos']){
            $sql .= " AND M.estado = 1 AND E.estado = 1";
        }
            

        $sql .= " ORDER BY Marca, NroSerie DESC";
        
        return DB::select($sql, $binding);
    }

    private static function chartportipo($Args) {

        $binding = Array();

        $sql = "SELECT M.idTipoMaq, TM.descripcion AS Nombre, COUNT(*) AS Cantidad
                FROM Maquinas M INNER JOIN Empresas E ON M.docEmpresa = E.documento AND M.tipoDocEmp = E.idTipoDocumento
                INNER JOIN TiposMaquinas TM ON M.idTipoMaq = TM.idTipoMaquina
                WHERE M.baja = 0";

        /*if (!empty($Args['NroContrato'])){
            $sql .= " AND EXISTS (SELECT *
                     FROM MaquinasContratos MC
                     WHERE M.nroSerie = MC.nroSerie
                     AND MC.nroContrato = :NroContrato)";
            $binding[':NroContrato'] = $Args['NroContrato'];
        }*/
            

        if (!empty($Args['IdAcceso'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM MaquinasAccesos MA
                        WHERE M.nroSerie = MA.nroSerie
                        AND MA.idAcceso = :IdAcceso) ";
            $binding[':IdAcceso'] = $Args['IdAcceso'];
        }
            
        
        if (!empty($Args['Activos']) && $Args['Activos']){
            $sql .= " AND M.estado = 1 AND E.estado = 1";
        }
            

        $sql .= " GROUP BY M.idTipoMaq, TM.descripcion";
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

        $Args = $this->req->all();
        $binding = Array();

        $sql = "SELECT m.NroSerie,
                        mm.Descripcion AS Marca,
                        m.Modelo AS Modelo,
                        tm.Descripcion AS Tipo,
                        c.Descripcion AS Categoria,
                        CASE m.Estado
                                WHEN 1 THEN 'Activo'
                                ELSE 'Inactivo'
                        END AS Estado,
                        e.Nombre AS Empresa
                FROM Maquinas M 
                INNER JOIN Categorias c ON c.IdCategoria = m.IdCategoria
                INNER JOIN TiposMaquinas tm ON tm.IdTipoMaquina = m.IdTipoMaq
                INNER JOIN MarcasMaquinas mm ON mm.IdMarcaMaq = m.IdMarcaMaq
                INNER JOIN Empresas e ON e.Documento = m.DocEmpresa AND e.IdTipoDocumento = m.TipoDocEmp
                WHERE M.baja = 0";

        if (!empty($Args['NroContrato'])){
            $sql .= " AND EXISTS (SELECT *
                     FROM MaquinasContratos MC
                     WHERE M.nroSerie = MC.nroSerie
                     AND MC.nroContrato = :NroContrato)";
            $binding[':NroContrato'] = $Args['NroContrato'];
        }
            
        if (!empty($Args['IdAcceso'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM MaquinasAccesos MA
                        WHERE M.nroSerie = MA.nroSerie
                        AND MA.idAcceso = :IdAcceso) ";
            $binding[':IdAcceso'] = $Args['IdAcceso'];
        }
            
        if (!empty($Args['Activos']) && $Args['Activos']){
            $sql .= " AND M.estado = 1 AND E.estado = 1";
        }
            
        $sql .= " ORDER BY Tipo, NroSerie DESC";
        
        return DB::select($sql, $binding);
    }
    
    private static function charthabilitados($Args) {
        
        $binding = Array();
        
        $sql = "SELECT ";

        $sql .= "(SELECT COUNT(*)
                    FROM Maquinas M INNER JOIN Empresas E ON M.docEmpresa = E.documento AND M.tipoDocEmp = E.idTipoDocumento
                    WHERE M.baja = 0
                    AND M.estado = 1
                    AND E.estado = 1";

        if (!empty($Args['NroContrato'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM MaquinasContratos MC
                        WHERE M.nroSerie = MC.nroSerie
                        AND MC.nroContrato = :NroContrato)";
            $binding[':NroContrato'] = $Args['NroContrato'];
        }
            

        if (!empty($Args['IdAcceso'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM MaquinasAccesos MA
                        WHERE M.nroSerie = MA.nroSerie
                        AND MA.idAcceso = :IdAcceso) ";
            $binding[':IdAcceso'] = $Args['IdAcceso'];
        }
            

        $sql .= " ) AS Habilitados,";

        $sql .= "(SELECT COUNT(*)
                    FROM Maquinas M INNER JOIN Empresas E ON M.docEmpresa = E.documento AND M.tipoDocEmp = E.idTipoDocumento
                    WHERE M.baja = 0
                    AND (M.estado = 0 OR E.estado = 0)";

        if (!empty($Args['NroContrato'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM MaquinasContratos MC
                        WHERE M.nroSerie = MC.nroSerie
                        AND MC.nroContrato = :NroContrato1)";
            $binding[':NroContrato1'] = $Args['NroContrato'];
        }
            

        if (!empty($Args['IdAcceso'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM MaquinasAccesos MA
                        WHERE M.nroSerie = MA.nroSerie
                        AND MA.idAcceso = :IdAcceso1)";
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

        $Args = $this->req->all();
        $binding = Array();

        $sql = "SELECT m.NroSerie,
                        mm.Descripcion AS Marca,
                        'Habilitado' AS Habilitado,
                        m.Modelo AS Modelo,
                        tm.Descripcion AS Tipo,
                        c.Descripcion AS Categoria,
                        CASE m.Estado
                                WHEN 1 THEN 'Activo'
                                ELSE 'Inactivo'
                        END AS Estado,
                        e.Nombre AS Empresa
                    FROM Maquinas M
                    INNER JOIN Categorias c ON c.IdCategoria = m.IdCategoria
                    INNER JOIN TiposMaquinas tm ON tm.IdTipoMaquina = m.IdTipoMaq
                    INNER JOIN MarcasMaquinas mm ON mm.IdMarcaMaq = m.IdMarcaMaq
                    INNER JOIN Empresas E ON M.docEmpresa = E.documento AND M.tipoDocEmp = E.idTipoDocumento
                    WHERE M.baja = 0
                    AND M.estado = 1
                    AND E.estado = 1";

        if (!empty($Args['NroContrato'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM MaquinasContratos MC
                        WHERE M.nroSerie = MC.nroSerie
                        AND MC.nroContrato = :NroContrato)";
            $binding[':NroContrato'] = $Args['NroContrato'];
        }
            

        if (!empty($Args['IdAcceso'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM MaquinasAccesos MA
                        WHERE M.nroSerie = MA.nroSerie
                        AND MA.idAcceso = :IdAcceso) ";
            $binding[':IdAcceso'] = $Args['IdAcceso'];
        }
            

        $sql .= " UNION ALL ";

        $sql .= "SELECT m.NroSerie,
                        mm.Descripcion AS Marca,
                        'No habilitado' AS Habilitado,
                        m.Modelo AS Modelo,
                        tm.Descripcion AS Tipo,
                        c.Descripcion AS Categoria,
                        CASE m.Estado
                                WHEN 1 THEN 'Activo'
                                ELSE 'Inactivo'
                        END AS Estado,
                        e.Nombre AS Empresa
                    FROM Maquinas M 
                    INNER JOIN Categorias c ON c.IdCategoria = m.IdCategoria
                    INNER JOIN TiposMaquinas tm ON tm.IdTipoMaquina = m.IdTipoMaq
                    INNER JOIN MarcasMaquinas mm ON mm.IdMarcaMaq = m.IdMarcaMaq
                    INNER JOIN Empresas E ON M.docEmpresa = E.documento AND M.tipoDocEmp = E.idTipoDocumento
                    WHERE M.baja = 0
                    AND (M.estado = 0 OR E.estado = 0)";

        if (!empty($Args['NroContrato'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM MaquinasContratos MC
                        WHERE M.nroSerie = MC.nroSerie
                        AND MC.nroContrato = :NroContrato1)";
            $binding[':NroContrato1'] = $Args['NroContrato'];
        }

        if (!empty($Args['IdAcceso'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM MaquinasAccesos MA
                        WHERE M.nroSerie = MA.nroSerie
                        AND MA.idAcceso = :IdAcceso1)";
            $binding[':IdAcceso1'] = $Args['IdAcceso'];
            
        }
        
        return DB::select($sql, $binding);
    }

    private static function chartporcategoria($Args) {

        $binding = Array();

        $sql = "SELECT M.idCategoria, C.descripcion AS Nombre, COUNT(*) AS Cantidad
                FROM Maquinas M INNER JOIN Empresas E ON M.docEmpresa = E.documento AND M.tipoDocEmp = E.idTipoDocumento
                INNER JOIN Categorias C ON M.idCategoria = C.idCategoria
                WHERE M.baja = 0";

        if (!empty($Args['NroContrato'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM MaquinasContratos MC
                        WHERE M.nroSerie = MC.nroSerie
                        AND MC.nroContrato =:NroContrato)";
            $binding[':NroContrato'] = $Args['NroContrato'];
        }
            
        if (!empty($Args['IdAcceso'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM MaquinasAccesos MA
                        WHERE M.nroSerie = MA.nroSerie
                        AND MA.idAcceso = :IdAcceso) ";
            $binding[':IdAcceso'] = $Args['IdAcceso'];
        }
            
        if (!empty($Args['Activos']) && $Args['Activos']){
            $sql .= " AND M.estado = 1";
        }
            
        $sql .= " GROUP BY M.idCategoria, C.descripcion";
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

        $Args = $this->req->all();
        $binding = Array();

        $sql = "SELECT m.NroSerie,
                        mm.Descripcion AS Marca,
                        m.Modelo AS Modelo,
                        tm.Descripcion AS Tipo,
                        c.Descripcion AS Categoria,
                        CASE m.Estado
                                WHEN 1 THEN 'Activo'
                                ELSE 'Inactivo'
                        END AS Estado,
                        e.Nombre AS Empresa
                FROM Maquinas M 
                INNER JOIN Categorias c ON c.IdCategoria = m.IdCategoria
                INNER JOIN TiposMaquinas tm ON tm.IdTipoMaquina = m.IdTipoMaq
                INNER JOIN MarcasMaquinas mm ON mm.IdMarcaMaq = m.IdMarcaMaq
                INNER JOIN Empresas e ON e.Documento = m.DocEmpresa AND e.IdTipoDocumento = m.TipoDocEmp
                WHERE M.baja = 0";

        if (!empty($Args['NroContrato'])){
            $sql .= " AND EXISTS (SELECT *
                     FROM MaquinasContratos MC
                     WHERE M.nroSerie = MC.nroSerie
                     AND MC.nroContrato = :NroContrato)";
            $binding[':NroContrato'] = $Args['NroContrato'];
        }
            
        if (!empty($Args['IdAcceso'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM MaquinasAccesos MA
                        WHERE M.nroSerie = MA.nroSerie
                        AND MA.idAcceso = :IdAcceso) ";
            $binding[':IdAcceso'] = $Args['IdAcceso'];
        }
        
        if (!empty($Args['Activos']) && $Args['Activos']){
            $sql .= " AND M.estado = 1 AND E.estado = 1";
        }
            
        $sql .= " ORDER BY Categoria, NroSerie DESC";
        
        return DB::select($sql, $binding);
    }

    public function index()
    {
        $binding = [];
        $sql = "SELECT
                    CASE maq.Estado
                        WHEN 1 THEN 'active'
                        ELSE 'inactive'
                    END AS FsRC,
                    CASE maq.Estado
                        WHEN 1 THEN 'Activo'
                        ELSE 'Inactivo'
                    END AS Estado,
                    maq.NroSerie, 
                    marcas.Descripcion as Marca, 
                    maq.Modelo, 
                    maq.Propietario, 
                    maq.Conductor, 
                    maq.Matricula, 
                    maq.DocEmpresa, 
                    maq.TipoDocEmp, 
                    maq.IdCategoria, 
                    tipos.Descripcion as Tipo, 
                    emp.Nombre as Empresa
                FROM Maquinas maq 
                INNER JOIN TiposMaquinas tipos ON maq.IdTipoMaq = tipos.IdTipoMaquina
                INNER JOIN MarcasMaquinas marcas ON maq.IdMarcaMaq = marcas.IdMarcaMaq
                LEFT JOIN Empresas emp ON maq.DocEmpresa = emp.Documento AND maq.TipoDocEmp = emp.IdTipoDocumento
                WHERE maq.Baja = 0
                AND NOT EXISTS (SELECT maqt.NroSerie FROM MaquinasTransac maqt WHERE maqt.Completada = 0 AND maqt.NroSerie = maq.NroSerie)";

        /**
         * @todo isGestion() Si no es usuario Gestion.
         */
        $usuarioGestion = $this->user->isGestion();
        $idEmpresa = $this->req->input('IdEmpresa') == null ? null : $this->req->input('IdEmpresa');

        if ($idEmpresa !== null && empty($usuarioGestion))
        {
            $idEmpresaObj = FsUtils::explodeId($idEmpresa);
            $sql .= "AND maq.DocEmpresa = :doc_Empresa AND maq.TipoDocEmp = :tipo_doc_empresa";
            $binding[':doc_Empresa'] = $idEmpresaObj[0];
            $binding[':doc_Empresa'] = $idEmpresaObj[1];
        }

        if (null !== ($busqueda = $this->req->input('Busqueda'))) {
            $sql .= " AND (REPLACE(REPLACE(REPLACE(maq.NroSerie, '.', ''), '-', ''), ' ', '') COLLATE Latin1_general_CI_AI LIKE REPLACE(REPLACE(REPLACE(:busqueda_1, ' ', ''), '-', ''), '.', '') COLLATE Latin1_general_CI_AI OR "
                        . "tipos.Descripcion COLLATE Latin1_general_CI_AI LIKE :busqueda_2 COLLATE Latin1_general_CI_AI OR "
                        . "marcas.Descripcion COLLATE Latin1_general_CI_AI LIKE :busqueda_3 COLLATE Latin1_general_CI_AI OR "
                        . "maq.Propietario COLLATE Latin1_general_CI_AI LIKE :busqueda_4 COLLATE Latin1_general_CI_AI OR "
                        . "maq.Conductor COLLATE Latin1_general_CI_AI LIKE :busqueda_5 COLLATE Latin1_general_CI_AI OR "
                        . "CONVERT(varchar(18), maq.matricula) COLLATE Latin1_general_CI_AI LIKE :busqueda_6 COLLATE Latin1_general_CI_AI OR "
                        . "emp.Nombre COLLATE Latin1_general_CI_AI LIKE :busqueda_7 COLLATE Latin1_general_CI_AI)";
            $binding[':busqueda_1'] = '%' . $busqueda . '%';
            $binding[':busqueda_2'] = '%' . $busqueda . '%';
            $binding[':busqueda_3'] = '%' . $busqueda . '%';
            $binding[':busqueda_4'] = '%' . $busqueda . '%';
            $binding[':busqueda_5'] = '%' . $busqueda . '%';
            $binding[':busqueda_6'] = '%' . $busqueda . '%';
            $binding[':busqueda_7'] = '%' . $busqueda . '%';
        }

        // if ($this->req->input('MostrarEliminados', 'false') === 'false') {
        //     $sql .= " AND maq.Baja = 1 ";
        // }

        $sql .= "ORDER BY marcas.Descripcion";

        $page = (int)$this->req->input('page', 1);

        $items = DB::select($sql, $binding);

        $output = $this->req->input('output', 'json');

        if ($output !== 'json') {
            $dataOutput = array_map(function($item) {
                return [
                    'NroSerie' => $item->NroSerie,
                    'Estado' => $item->Estado,
                    'Tipo' => $item->Tipo,
                    'Matricula' => $item->Matricula,
                    'Marca' => $item->Marca,
                    'Modelo' => $item->Modelo,
                    'Empresa' => $item->Empresa
                ];
            },$items);

            $filename = 'FSAcceso-Maquinas-' . date('Ymd his');
            
            $headers = [
                'NroSerie' => 'Nro. de Serie',
                'Estado' => 'Estado',
                'Tipo' => 'Tipo',
                'Matricula' => 'Matrícula',
                'Marca' => 'Marca',
                'Modelo' => 'Modelo',
                'Empresa' => 'Empresa'
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

        $sql = "SELECT DISTINCT 'func=AdmMaquinas|NroSerie=' + m.NroSerie AS ObjUrl,
                        m.NroSerie,
                        e.Nombre AS Empresa,
                        CASE m.Estado WHEN 1 THEN 'Activo' ELSE 'Inactivo' END AS Estado,
                        c.Descripcion AS Categoria,
                        tm.Descripcion AS Tipo,
                        mm.Descripcion AS Marca,
                        m.Modelo AS Modelo,
                        m.Propietario,
                        mc.NroContrato AS NroContrato
            FROM Maquinas m
            INNER JOIN Categorias c ON c.IdCategoria = m.IdCategoria
            INNER JOIN TiposMaquinas tm ON tm.IdTipoMaquina = m.IdTipoMaq
            INNER JOIN MarcasMaquinas mm ON mm.IdMarcaMaq = m.IdMarcaMaq
            LEFT JOIN MaquinasContratos mc ON m.NroSerie = mc.NroSerie
            LEFT JOIN Empresas e ON e.Documento = m.DocEmpresa AND e.IdTipoDocumento = m.TipoDocEmp";

            $bs = 'm.Baja = 0';

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
                                $bs = "m.Baja IN (0, 1)";
                        break;

                        case 'IdEmpresa':
                            $e = FsUtils::explodeId($value);
                            $ws .= (empty($ws) ? " WHERE " : " AND ") . "m.DocEmpresa = :docEmpresa AND m.TipoDocEmp = :tipoDocEmpresa";
                            $binding[':docEmpresa'] = $e[0];
                            $binding[':tipoDocEmpresa'] = $e[1];
                        break;

                        default:
                            switch ($key) {
                                case 'IdCategoria':
                                case 'IdMarcaMaq':
                                case 'IdTipoMaq':
                                    $keys = 'm.' . $key;
                                    $ws .= (empty($ws) ? " WHERE " : " AND ") . $keys . " = :".$key;
                                    $binding[':'.$key] = $value;
                                    break;
                                default:
                                    $values = ':'.$key;
                                    $ws .= (empty($ws) ? " WHERE " : " AND ") . "m.".$key . " LIKE $values ";
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

        $page = (int)$this->req->input('page', 1);
        $items = DB::select($sql, $binding);

        $output = isset($args['output']);

        if ($output !== 'json' && $output == true) {

            $output = $args['output'];

            $dataOutput = array_map(function($item) {
                return [
                    'NroSerie' => $item->NroSerie,
                    'Marca' => $item->Marca,
                    'Modelo' => $item->Modelo,
                    'Tipo' => $item->Tipo,
                    'Categoria' => $item->Categoria,
                    'Empresa' => $item->Empresa,
                    'NroContrato' => $item->NroContrato,
                    'Estado' => $item->Estado
                ];
            },$items);

            $filename = 'FSAcceso-Maquinas-Consulta-' . date('Ymd his');
            
            $headers = [
                'NroSerie' => 'Nro. de Serie',
                'Marca' => 'Marca',
                'Modelo' => 'Modelo',
                'Tipo' => 'Tipo',
                'Categoria' => 'Categoría',
                'Empresa' => 'Empresa',
                'NroContrato' => 'Contrato',
                'Estado' => 'Estado',
            ];

            return FsUtils::export($output, $dataOutput, $headers, $filename);
        }

        $paginate = FsUtils::paginateArray($items, $this->req);
        
        return $this->responsePaginate($paginate->items(), $paginate->total(), $page);
    }
    
    public function show(string $nroSerie)
    {
        $entity = $this->show_interno($nroSerie);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Maquina no encontrada');
        }
        return $this->response($entity);
    }

    private function show_interno(string $nroSerie)
    {
        $entity = $this->showTransac($nroSerie);
        
        if (!isset($entity)) {
            $entity = $this->showNoTransac($nroSerie);
        }
        return $entity;
    }

    private function showTransac(string $nroSerie): ?object
    {
        $binding =  [$nroSerie];
        $sql = "SELECT TOP 1
                    CASE maqt.Accion WHEN 'A' 
                        THEN 'pending' WHEN 'M' 
                        THEN 'mod-pending'
                    END AS FsRC, 
                    maqt.NroSerie, 
                    maqt.IdTipoMaq,
                    maqt.IdMarcaMaq,
                    maqt.Modelo,
                    maqt.Propietario,
                    maqt.Conductor,
                    maqt.Matricula,
                    maqt.DocEmpresa,
                    maqt.TipoDocEmp,
                    maqt.IdCategoria,
                    maqt.Estado,
                    maqt.Foto,
                    maqt.Archivo,
                    maqt.FechaHoraAlta,
                    maqt.IdUsuarioAlta,
                    maqt.VigenciaDesde,
                    maqt.VigenciaHasta,
                    maqt.NotifEntrada,
                    maqt.EmailsEntrada,
                    maqt.NotifSalida,
                    maqt.EmailsSalida,
                    maqt.Aviso,
                    maqt.EnTransito,
                    maqt.NroContratoCompra,
                    maqt.DocEmpresa + '-' + LTRIM(RTRIM(STR(maqt.TipoDocEmp))) as IdEmpresa,
                    tipos.Descripcion + ' ' + marcas.Descripcion + ' ' + maqt.Modelo AS Detalle,
                    '' AS Observaciones
                FROM MaquinasTransac maqt
                LEFT JOIN TiposMaquinas tipos ON maqt.IdTipoMaq = tipos.IdTipoMaquina
                LEFT JOIN MarcasMaquinas marcas ON maqt.IdMarcaMaq = marcas.IdMarcaMaq
                WHERE maqt.NroSerie = ? AND maqt.Completada = 0";

        $entity = DB::selectOne($sql, $binding);

        if (isset($entity)) {
            $entity->Documentos = TipoDocumentoMaq::list($nroSerie, 'Transac');
            $entity->Contratos = Contrato::loadByMaquina($nroSerie, 'Transac');

            $previous_entity = $this->showNoTransac($nroSerie);
            if(isset($previous_entity)) {
                $entity->FsMV = $previous_entity;
            }
        }
        
        $entity = FsUtils::castProperties($entity, Maquina::$castProperties);

        return $entity;
    }

    private function showNoTransac(string $nroSerie): ?object
    {
        $binding = [$nroSerie];

        $sql = "SELECT 
            FsRC = CASE maq.Estado 
                WHEN 1 THEN 'active' 
                ELSE 'inactive' 
            END, 
            maq.NroSerie,
            maq.IdTipoMaq,
            tipos.Descripcion AS Tipo,
            maq.IdMarcaMaq,
            marcas.Descripcion AS Marca,
            maq.Modelo,
            maq.Propietario,
            maq.Conductor,
            maq.Matricula,
            maq.DocEmpresa,
            maq.TipoDocEmp,
            maq.DocEmpresa + '-' + LTRIM(RTRIM(STR(maq.TipoDocEmp))) as IdEmpresa,
            e.Nombre AS Empresa,
            mc.NroContrato,
            maq.IdCategoria,
            maq.Estado,
            maq.Foto,
            maq.Archivo,
            maq.FechaHoraAlta,
            maq.IdUsuarioAlta,
            CONVERT(varchar(10), maq.VigenciaDesde, 103) AS FechaVigenciaDesde,
            CONVERT(varchar(5), maq.VigenciaDesde, 108) AS HoraVigenciaDesde,
            CONVERT(varchar(10), maq.VigenciaHasta, 103) AS FechaVigenciaHasta,
            CONVERT(varchar(5), maq.VigenciaHasta, 108) AS HoraVigenciaHasta,
            maq.VigenciaDesde,
            maq.VigenciaHasta,
            maq.NotifEntrada,
            maq.EmailsEntrada,
            maq.NotifSalida,
            maq.EmailsSalida,
            maq.Aviso,
            maq.EnTransito,
            maq.NroContratoCompra,
            maq.Baja,
            c.Descripcion AS Categoria,
            c.CatLenel,
            tipos.Descripcion + ' ' + marcas.Descripcion + ' ' + maq.Modelo AS Detalle,
            Observaciones
        FROM Maquinas maq
        INNER JOIN TiposMaquinas tipos ON maq.IdTipoMaq = tipos.IdTipoMaquina
        INNER JOIN MarcasMaquinas marcas ON maq.IdMarcaMaq = marcas.IdMarcaMaq
        INNER JOIN Categorias c ON c.IdCategoria = maq.IdCategoria
        LEFT JOIN Empresas e ON e.Documento = maq.DocEmpresa AND e.IdTipoDocumento = maq.TipoDocEmp
        LEFT JOIN MaquinasContratos mc ON maq.NroSerie = mc.NroSerie
        WHERE maq.NroSerie = ? ";

        $entity = DB::selectOne($sql, $binding);

        if (isset($entity)) {
            $entity->Documentos = TipoDocumentoMaq::list($nroSerie);
            $entity->Contratos = Contrato::loadByMaquina($nroSerie);
            $entity->Incidencias = Incidencia::loadByMaquina($nroSerie);
            $entity->Verificaciones = Verificacion::loadByMaquina($nroSerie);
            $entity->Accesos = Acceso::loadByMaquina($nroSerie);
        }

        $entity = FsUtils::castProperties($entity, Maquina::$castProperties);

        return $entity;
    }

    public function create()
    {
        return DB::transaction(function () {
            $args = (object)$this->req->all();
            Maquina::exigirArgs($args, ['NroSerie', 'IdTipoMaq', 'IdCategoria', 'IdEmpresa', 'IdMarcaMaq', 'Modelo', 'FechaVigenciaDesde', 'FechaVigenciaHasta' ]);
            Maquina::comprobarArgs($args); // no implementado de momento y no se utiliza en código original

            Matricula::disponibilizar(isset($args->Matricula) ? $args->Matricula : null);

            /// @todo $empresa = self::explodeIdEmpresa($args);
            $entity = $this->showNoTransac($args->NroSerie);
            $transac = false;

            if (!isset($entity)) {
                if ($this->user->isGestion()) {
                    $this->insertEntity($args);
                } else {
                    $this->abmEntityTransac($args, 'A');
                    $transac = true;
                }
            } else {
                if ($entity->Baja == 1) {
                    $this->update($args->NroSerie);
                    DB::update("UPDATE Maquinas SET Baja = 0 WHERE NroSerie = ?", [$args->NroSerie]);
                        LogAuditoria::log(
                            Auth::id(),
                            'maquina',
                            LogAuditoria::FSA_METHOD_CREATE,
                            $args,
                            $entity->NroSerie,
                            sprintf('%s %s %s (%s)', $entity->IdTipoMaq, $entity->IdMarcaMaq, $entity->Modelo, $entity->NroSerie)
                        );
                    return null;
                } else {
                    throw new ConflictHttpException("La máquina ya existe");
                }
            }

            if (!$transac && !empty($args->Estado)) {
                $this->activar_interno($args);
            } else if (empty($args->Estado)) {
                $this->desactivar_interno($args);
            }

            $onguard = !$transac && Categoria::sincConOnGuard($args->IdCategoria) && env('INTEGRADO', 'false') === true;

            if ($onguard) {
                $detalle = $this->showNoTransac($args->NroSerie);
                OnGuard::altaMaquina(
                    Maquina::id($detalle),
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
                    Categoria::gestionaMatriculaEnFSA($detalle->IdCategoria) ? OnGuard::CONTINGENCY_O : OnGuard::CONTINGENCY_U
                );
            }

            if (!$transac && isset($args->Matricula)) {
                Maquina::logCambioMatricula($args, 'Alta');
            }
            return null;
        });
    }

    public function update(string $nroSerie, object $args = null)
    {
        DB::transaction(function () use ($nroSerie, $args)
        {
            if (!isset($args)) {
                $args = (object)$this->req->all();
            }

            Maquina::comprobarArgs($args);
            
            /// @todo $empresa = self::explodeIdEmpresa($Args);
            $transac = false;
            
            if ($this->user->isGestion()) {
                $entityTransac = $this->showTransac($nroSerie);
                if (isset($entityTransac)) {
                    return $this->aprobar($nroSerie);
                }
                $this->updateEntity($nroSerie, $args);
            } else {
                $this->abmEntityTransac($args, 'M');
                $transac = true;
            }

            if (!$transac && !empty($args->Estado)) {
                $this->activar_interno($args);
            } else if (empty($args->Estado)) {
                $this->desactivar_interno($args);
            }

            $onguard = !$transac && Categoria::sincConOnGuard($args->IdCategoria) && env('INTEGRADO', 'false') === true;
            $checkBaja = DB::selectOne('SELECT Baja FROM Maquinas WHERE NroSerie = ? ', [$nroSerie]);
            $respawning = !empty($checkBaja->Baja);
            $method = $respawning ? 'alta' : 'modificacion';

            if ($respawning) {
                DB::update('UPDATE Maquinas SET Baja = 0 WHERE  NroSerie = ?', [$nroSerie]);
            }

            if ($onguard) {
                $detalle = $this->showNoTransac($args->NroSerie);
                call_user_func_array([OnGuard::class, $respawning ? 'altaMaquina' : 'modificacionMaquina'], [
                    Maquina::id($detalle),
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
                    OnGuard::CONTINGENCY_O
                ]);
            }

            LogAuditoria::log(
                Auth::id(),
                Maquina::class,
                LogAuditoria::FSA_METHOD_UPDATE,
                $args,
                $args->NroSerie,
                sprintf('%s %s %s (%s)', $args->IdTipoMaq, $args->IdMarcaMaq, $args->Modelo, $args->NroSerie)
            );
        });
    }

    public function delete(string $nroSerie)
    {
        DB::transaction(function () use ($nroSerie) {
            $entity = $this->show($nroSerie)->getData();
            $this->abmEntityTransac($entity, 'D', true);

            DB::update(
                'UPDATE Maquinas '
                . 'SET Baja = 1, '
                . 'Matricula = NULL, '
                . 'FechaHoraBaja = GETDATE(), '
                . 'IdUsuarioBaja = ? '
                . 'WHERE NroSerie = ?',
            [Auth::id(), $nroSerie]);

            $onguard = Categoria::sincConOnGuard($entity->IdCategoria) && env('INTEGRADO', 'false') === true;
            if ($onguard) {
                OnGuard::bajaMaquina(Maquina::id($entity));
            }

            LogAuditoria::log(
                Auth::id(),
                Maquina::class,
                LogAuditoria::FSA_METHOD_DELETE,
                $entity,
                $nroSerie,
                sprintf('%s %s %s (%s)', $entity->IdTipoMaq, $entity->IdMarcaMaq, $entity->Modelo, $entity->NroSerie)
            );
        });
    }

    public function activar(string $nroSerie) {
        $entity = $this->showNoTransac($nroSerie);
        $this->activar_interno($entity);
    }

    public function desactivar(string $nroSerie)
    {
        $entity = $this->showNoTransac($nroSerie);
        $this->desactivar_interno($entity);
    }

    public function aprobar(string $nroSerie) {
        $entity = $this->showTransac($nroSerie);

        if (!isset($entity)) {
            throw new NotFoundHttpException('La entidad no puede ser aprobada porque no existe una transacción');
        }

        $this->aprobar_interno($entity);
    }

    public function rechazar(string $nroSerie) {
        $entity = $this->showTransac($nroSerie);

        if(!isset($entity)) {
            throw new NotFoundHttpException('No existe una transacción para la entidad que intenta rechazar');
        }

        $this->rechazar_interno($entity);
    }

    public function sincronizar(string $nroSerie) {
        $entity = $this->show_interno($nroSerie);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Maquina no encontrada');
        }

        $this->update($nroSerie, $entity);
    }

    public function cambiarIdentificador(string $nroSerie)
    {
        $nuevoNroSerie = $this->req->input('NuevoNroSerie');
        $entity = $this->showNoTransac($nroSerie);

        if (!isset($entity)) {
            throw new NotFoundHttpException('La máquina no existe');
        }

        $entityTransac = $this->showTransac($nroSerie);
        
        if (isset($entityTransac)) {
            throw new ConflictHttpException('No se puede cambiar el identificador de una maquina que tiene modificaciones pendientes de aprobacion');
        }

        $entityTransac = $this->show_interno($nuevoNroSerie);

        if (isset($entityTransac)) {
            throw new ConflictHttpException('El Nro de Serie. que acaba de ingresar ya se encuentra utilizado');
        }

        $tables = [
            ['Eventos', 'NroSerie'],
            ['EventosDuplicados', 'NroSerie'],
            ['HISTMaquinas', 'NroSerie'],
            ['HISTMaquinasActivos', 'NroSerie'],
            ['HISTMaquinasDocs', 'NroSerie'],
            ['HISTMaquinasDocsItems', 'NroSerie'],
            ['Maquinas', 'NroSerie'],
            ['MaquinasAccesos', 'NroSerie'],
            ['MaquinasContratos', 'NroSerie'],
            ['MaquinasContratosAltas', 'NroSerie'],
            ['MaquinasContratosBajas', 'NroSerie'],
            ['MaquinasDocs', 'NroSerie'],
            ['MaquinasDocsItems', 'NroSerie'],
            ['MaquinasIncidencias', 'NroSerie'],
            ['MaquinasListaNegra', 'NroSerie'],
            ['MaquinasMatriculas', 'NroSerie'],
            ['MaquinasTransac', 'NroSerie'],
            ['MaquinasTransacContratos', 'NroSerie'],
            ['MaquinasTransacDocs', 'NroSerie'],
            ['MaquinasTransacDocsItems', 'NroSerie'],
            ['MaquinasVerificaciones', 'NroSerie']
        ];

        $args = (object)[
            'NroSerie' => $nroSerie,
            'NuevoNroSerie' => $nuevoNroSerie
        ];

        DB::transaction(function () use ($tables, $args, $entity) {
            Maquina::cambiarIdentificador($tables, $args);
            $nuevaEntity = $this->show_interno($args->nuevoNroSerie);

            $onGuard = Categoria::sincConOnGuard(0) && env('INTEGRADO', 'false') === true;

            if ($onGuard) {
                OnGuard::bajaMaquina(Maquina::id($entity));
                OnGuard::altaMaquina(Maquina::id($nuevaEntity), $nuevaEntity->Marca, $nuevaEntity->Modelo, $nuevaEntity->Tipo, $nuevaEntity->Categoria, $nuevaEntity->Empresa, $nuevaEntity->NroContrato, (Categoria::gestionaMatriculaEnFSA($nuevaEntity->IdCategoria) ? $nuevaEntity->Matricula : null), $nuevaEntity->Estado, $nuevaEntity->CatLenel, $nuevaEntity->VigenciaDesde, $nuevaEntity->VigenciaHasta, Categoria::gestionaMatriculaEnFSA($nuevaEntity->IdCategoria) ? "O" : "U");
            }

            LogAuditoria::log(
                Auth::id(),
                Maquina::class,
                'cambiar identificador',
                $args,
                $args->NroSerie,
                sprintf('%s %s %s (%s)', $entity->IdTipoMaq, $entity->IdMarcaMaq, $entity->Modelo, $args->NroSerie)
            );
        });
    }

    public function comprobarIdentificador(string $nroSerie)
    {
        return Maquina::comprobarIdentificador((object)[
            'NroSerie' => $nroSerie,
        ]);
    }

    public function cambiarMatricula(string $nroSerie)
    {
        $matricula = $this->req->input('Matricula');

        return DB::transaction(function () use ($nroSerie, $matricula) {
            $entityTransac = $this->showTransac($nroSerie);
            
            if (isset($entityTransac)) {
                throw new ConflictHttpException('No se puede cambiar la matricula de una maquina que tiene modificaciones pendientes de aprobacion');
            }

            $entity = $this->showNoTransac($nroSerie);

            if (!isset($entity)) {
                throw new ConflictHttpException('Máquina no encontrada');
            }

            Matricula::disponibilizar(isset($matricula) ? $matricula : null);

            DB::update(
                'UPDATE Maquinas 
                SET Matricula = ? 
                WHERE NroSerie = ?',
                [$matricula, $nroSerie]
            );

            $onguard = Categoria::sincConOnGuard($entity->IdCategoria) && env('INTEGRADO', 'false') === true;

            if ($onguard) {
                $detalle = $this->showNoTransac($nroSerie);
                OnGuard::cambiarTarjetaEntidadLenel(
                    Maquina::id($detalle),
                    $detalle->Matricula,
                    $detalle->Estado,
                    $detalle->CatLenel,
                    $detalle->VigenciaDesde,
                    $detalle->VigenciaHasta,
                    OnGuard::ENTIDAD_MAQUINA
                );
            }

            Maquina::logCambioMatricula($entity, "Cambio de Matrícula");

            LogAuditoria::log(
                Auth::id(),
                Maquina::class,
                LogAuditoria::FSA_METHOD_UPDATE,
                'cambio de matrícula',
                $entity->NroSerie,
                sprintf('%s %s %s (%s)', $entity->IdTipoMaq, $entity->IdMarcaMaq, $entity->Modelo, $entity->NroSerie)
            );
        });
    }

    // IMPRIMIR MATRICULA
    public function imprimirMatriculaEnBase64(string $nroSerie)
    {    
        $entity = $this->show_interno($nroSerie);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Maquina no encontrada');
        }

        $impresionMatricula = new ImprimirMatricula();
        $imagen = $impresionMatricula->imprimir($entity);
        return $imagen;
    }

    private function insertEntity(object $args)
    {
        if (!empty($args->IdEmpresa)) {
            $IdEmpresaObj = FsUtils::explodeId($args->IdEmpresa);
        }

        $maquina = new Maquina((array)$args);
        $maquina->NroSerie = $args->NroSerie;
        $maquina->IdTipoMaq = $args->IdTipoMaq;
        $maquina->IdMarcaMaq = $args->IdMarcaMaq;
        $maquina->Modelo = $args->Modelo;
        $maquina->Propietario = $args->Propietario;
        $maquina->Conductor = $args->Conductor;
        $maquina->DocEmpresa = $IdEmpresaObj[0];
        $maquina->TipoDocEmp = $IdEmpresaObj[1];
        $maquina->IdCategoria = $args->IdCategoria;
        $maquina->FechaHoraAlta = new Carbon;
        $maquina->IdUsuarioAlta = Auth::id();
        $maquina->EnTransito = $args->EnTransito;
        $maquina->NroContratoCompra = isset($args->NroContratoCompra) ? $args->NroContratoCompra : null;
        $maquina->Matricula = $args->Matricula;

        if (!empty($args->FechaVigenciaDesde) && !empty($args->HoraVigenciaDesde)) {
            $maquina->VigenciaDesde = FsUtils::fromHumanDatetime($args->FechaVigenciaDesde  . ' ' . $args->HoraVigenciaDesde . ':00');
        }
        if (!empty($args->FechaVigenciaHasta) && !empty($args->HoraVigenciaHasta)) {
            $maquina->VigenciaHasta = FsUtils::fromHumanDatetime($args->FechaVigenciaHasta  . ' ' . $args->HoraVigenciaHasta . ':00');
        }

        $maquina->NotifEntrada = isset($args->NotifEntrada) ? $args->NotifEntrada : null;
        $maquina->NotifSalida = isset($args->NotifSalida) ? $args->NotifSalida : null;
        $maquina->EmailsEntrada = isset($args->EmailsEntrada) ? $args->EmailsEntrada : null;
        $maquina->EmailsSalida = isset($args->EmailsSalida) ? $args->EmailsSalida : null;
        $maquina->Observaciones = isset($args->Observaciones) ? $args->Observaciones : null;
        $maquina->Baja = false;

        $maquina->save();

        Empresa::createByMaquina($args);
        Maquina::altaDocumentos($args);
        Verificacion::createByMaquina($args);
        Incidencia::createByMaquina($args);
        Acceso::createByMaquina($args);
    }

    private function updateEntity(string $nroSerie, object $args) {
        try {
            $args->NroSerie = $nroSerie;

            $entity = Maquina::where('NroSerie', $nroSerie)->firstOrFail();
            $entity->fill((array)$args);

            if (!empty($args->IdEmpresa)) {
                $IdEmpresaObj = FsUtils::explodeId($args->IdEmpresa);
            }

            if ($this->user->isGestion()) {
                if (!empty($args->FechaVigenciaDesde) && !empty($args->HoraVigenciaDesde) && !empty($args->FechaVigenciaHasta) && !empty($args->HoraVigenciaHasta)) {
                        $entity->VigenciaDesde = FsUtils::fromHumanDatetime($args->FechaVigenciaDesde  . ' ' . $args->HoraVigenciaDesde . ':00');
                        $entity->VigenciaHasta = FsUtils::fromHumanDatetime($args->FechaVigenciaHasta  . ' ' . $args->HoraVigenciaHasta . ':00');
                } else {
                    if (!empty($args->VigenciaDesde) && !empty($args->HoraVigenciaDesde) && !empty($args->VigenciaHasta) && !empty($args->HoraVigenciaHasta)) {
                        $entity->VigenciaDesde = FsUtils::fromHumanDatetime($args->VigenciaDesde  . ' ' . $args->HoraVigenciaDesde . ':00');
                        $entity->VigenciaHasta = FsUtils::fromHumanDatetime($args->VigenciaHasta  . ' ' . $args->HoraVigenciaHasta . ':00');
                    }
                }

                $entity->DocEmpresa = $IdEmpresaObj[0];
                $entity->TipoDocEmp = $IdEmpresaObj[1];
                $entity->NotifEntrada = isset($args->NotifEntrada) ? $args->NotifEntrada : null;
                $entity->NotifSalida = isset($args->NotifSalida) ? $args->NotifSalida : null;
                $entity->EmailsEntrada = isset($args->EmailsEntrada) ? $args->EmailsEntrada : null;
                $entity->EmailsSalida = isset($args->EmailsSalida) ? $args->EmailsSalida : null;
                $entity->Observaciones = isset($args->Observaciones) ? $args->Observaciones : null;
            }
            $entity->save();

            Empresa::createByMaquina($args, true);
            Maquina::altaDocumentos($args, true);
            Verificacion::createByMaquina($args, true);
            Incidencia::createByMaquina($args, true);
            Acceso::createByMaquina($args, true);

        } catch (ModelNotFoundException $ex) {
            throw new NotFoundHttpException('Maquina no encontrada');
        }
    }

    private function abmEntityTransac(object $args, $action = '', $reset = true)
    {
        if ($action === 'M') {
            $result = DB::selectOne(
                "SELECT * FROM MaquinasTransac WHERE NroSerie = ? AND Accion = 'A' AND Completada = 0",
                [$args->NroSerie]
            );
            if(isset($result)) {
                $action = 'A';
            }
        }

        if ($action === 'A' && !$this->user->isGestion()) {
            unset($args->Matricula);
        }

        if ($reset || $action === 'D') {
            $ac = $action != "D" ? " AND Accion = '" . $action . "' " : "";
            DB::delete('DELETE FROM MaquinasTransac WHERE NroSerie = ? ' . $ac . ' AND Completada = 0', [$args->NroSerie]);
        }

        if (isset($args) && $action != 'D') {

            if (!empty($args->IdEmpresa)) {
                $IdEmpresaObj = FsUtils::explodeId($args->IdEmpresa);
            }

            $entityTransac = new Maquina((array)$args);
            $entityTransac->setTable($entityTransac->getTable() . 'Transac');
            $entityTransac->Accion = $action;
            $entityTransac->AccionFechaHora = new Carbon;
            $entityTransac->AccionIdUsuario = $this->user->getKey();
            $entityTransac->Completada = 0;
            $entityTransac->NroSerie = $args->NroSerie;
            $entityTransac->IdTipoMaq = $args->IdTipoMaq;
            $entityTransac->IdMarcaMaq = $args->IdMarcaMaq;
            $entityTransac->Modelo = $args->Modelo;
            $entityTransac->Propietario = $args->Propietario;
            $entityTransac->Conductor = $args->Conductor;
            $entityTransac->DocEmpresa = $IdEmpresaObj[0];
            $entityTransac->TipoDocEmp = $IdEmpresaObj[1];
            $entityTransac->IdCategoria = $args->IdCategoria;
            
            $entityTransac->FechaHoraAlta = new Carbon;
            $entityTransac->IdUsuarioAlta = Auth::id();
            $entityTransac->EnTransito = $args->EnTransito;
            $entityTransac->NroContratoCompra = isset($args->NroContratoCompra) ? $args->NroContratoCompra : null;
            $entityTransac->Matricula = $args->Matricula;
            $entityTransac->NotifEntrada = isset($args->NotifEntrada) ? $args->NotifEntrada : null;
            $entityTransac->NotifSalida = isset($args->NotifSalida) ? $args->NotifSalida : null;
            $entityTransac->EmailsEntrada = isset($args->EmailsEntrada) ? $args->EmailsEntrada : null;
            $entityTransac->EmailsSalida = isset($args->EmailsSalida) ? $args->EmailsSalida : null;
    
            $entityTransac->save();

            Empresa::createByMaquina($args, $reset, 'Transac');
            Maquina::altaDocumentos($args, $reset, 'Transac');
        }
    }

    private function activar_interno(object $args)
    {
        BaseModel::exigirArgs(FsUtils::classToArray($args), ['NroSerie', 'Estado']);
        $args->Estado = 1;
        Maquina::esActivable($args);

        DB::transaction(function () use ($args) {
            DB::update(
                'UPDATE Maquinas SET Estado = ? WHERE NroSerie = ? ',
                [$args->Estado, $args->NroSerie]
            );

            $entity = $this->showNoTransac($args->NroSerie);

            OnGuard::modificarTarjetaEntidadLenel(
                Maquina::id($entity),
                $entity->Matricula,
                OnGuard::ESTADO_ACTIVO,
                $entity->CatLenel,
                $entity->VigenciaDesde,
                $entity->VigenciaHasta,
                OnGuard::ENTIDAD_MAQUINA
            );

            LogAuditoria::log(
                Auth::id(),
                Maquina::class,
                LogAuditoria::FSA_METHOD_ACTIVATE,
                $args,
                $args->NroSerie,
                sprintf('%s %s %s (%s)', $args->IdTipoMaq, $args->IdMarcaMaq, $args->Modelo, $args->NroSerie)
            );
        });
    }

    private function desactivar_interno(object $args) {
        BaseModel::exigirArgs(FsUtils::classToArray($args), ['NroSerie', 'Estado']);
        $args->Estado = 0;
        DB::transaction(function () use ($args) {
            DB::update(
                'UPDATE Maquinas SET Estado = ? WHERE NroSerie = ?',
                [$args->Estado, $args->NroSerie]
            );

            OnGuard::deshabilitarEntidadLenel(Maquina::id($args));

            LogAuditoria::log(
                Auth::id(),
                Maquina::class,
                LogAuditoria::FSA_METHOD_DESACTIVATE,
                $args,
                $args->NroSerie,
                sprintf('%s %s %s (%s)', $args->IdTipoMaq, $args->IdMarcaMaq, $args->Modelo, $args->NroSerie)
            );
        });
    }

    private function aprobar_interno(object $args) {

        $return = DB::transaction(function () use ($args) {
            
            /// @todo $empresa = self::explodeIdEmpresa($Args);
            $existeNoTransac = $this->showNoTransac($args->NroSerie);

            if(!$existeNoTransac) {
                $this->insertEntity($args);
                DB::update(
                    'UPDATE MaquinasTransac SET Completada = 1 WHERE NroSerie = ? AND Accion = \'A\' AND Completada = 0',
                    [$args->NroSerie]
                );
            } else {
                $this->updateEntity($args->NroSerie, $args);
                DB::update(
                    "UPDATE MaquinasTransac SET Completada = 1 WHERE NroSerie = ? AND Accion = \'M\' AND Completada = 0",
                    [$args->NroSerie]
                );
            }

            LogAuditoria::log(
                Auth::id(),
                Maquina::class,
                LogAuditoria::FSA_METHOD_APPROVE,
                $args,
                $args->NroSerie,
                sprintf('%s %s %s (%s)', $args->IdTipoMaq, $args->IdMarcaMaq, $args->Modelo, $args->NroSerie)
            );
            return true;
        });

        if ($return !== true) {
            throw new HttpException('Ocurrio un error al aprobar la maquina');
        }
    }

    private function rechazar_interno(object $args) {
        $return = DB::transaction(function () use ($args) {
            $existeTransac = $this->showTransac($args->NroSerie);

            if (!$existeTransac) {
                throw new NotFoundHttpException('La maquina que esta intentando rechazar no existe');
            }
            
            DB::update(
                'UPDATE MaquinasTransac SET Completada = 2 WHERE NroSerie = ? AND Completada = 0',
                [$args->NroSerie]
            );

            LogAuditoria::log(
                Auth::id(),
                Maquina::class,
                LogAuditoria::FSA_METHOD_REJECT,
                $args,
                $args->NroSerie,
                sprintf('%s %s %s (%s)', $args->IdTipoMaq, $args->IdMarcaMaq, $args->Modelo, $args->NroSerie)
            );

            return true;
        });

        if ($return !== true) {
            throw new HttpException('Ocurrio un error al rechazar la maquina');
        }
    }

    /**
     * @todo normalizarArchivo, método para el manejo de la foto.
     */

    public function subirFoto(string $nroSerie) {

        $transac = null!==$this->showTransac($nroSerie);

        $table = null;
        if($this->user->Gestion && !$transac){
            $table = 'Maquinas';
        }else{
            $table = 'MaquinasTransac';
        }
		$MaquinaDoc = DB::select("Select Archivo from " . $table . " WHERE NroSerie = :nroSerie", [":nroSerie" => $nroSerie]);

        if(!empty($MaquinaDoc[0]->Archivo)){
            $pathName = storage_path('app/uploads/maquinas/fotos/'.$MaquinaDoc[0]->Archivo);
            if (file_exists($pathName)) unlink($pathName);
        }
        
        
		$retornoUpdate = false;
        $file = $this->req->file('Archivo-file');
        $filename = 'Maquinas-' . $nroSerie . '-' . uniqid() . '.' . $file->getClientOriginalExtension();

        $retornoUpdate = DB::update("UPDATE ".$table." SET Archivo = :filenamee WHERE NroSerie = :nroSerie", [":nroSerie" => $nroSerie, ":filenamee" => $filename]);

        $file->storeAs('uploads/maquinas/fotos', $filename);

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
        $file->storeAs('uploads/maquinas/docs', $Args['filename']);

        return true;
    }

    public function verArchivo($carpeta, $fileName){

        $adjunto = storage_path('app/uploads/maquinas/'. $carpeta .'/'.$fileName);

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
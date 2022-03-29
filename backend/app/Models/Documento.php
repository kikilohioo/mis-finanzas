<?php

namespace App\Models;

use App\FsUtils;
use Illuminate\Support\Facades\DB;

class Documento extends BaseModel
{
    public static $castProperties = [
        'Id' => 'integer',
        'IdTipoDocPF' => 'integer',
        'IdTipoDocEmp' => 'integer',
        'IdTipoDocMaq' => 'integer',
        'IdTipoDocVehic' => 'integer',
        'IdTipoDocumento' => 'integer',
        'Vto' => 'date',
    ];

    public static function index(object $Args): array
    {
        $AttrSuffix = isset($Args->AttrSuffix) ? $Args->AttrSuffix : static::getAttrSuffix();

        if (!empty($AttrSuffix)) {
            $lcsuffix = strtolower($AttrSuffix);
            $tablePreffix = isset($Args->TablePreffix) ? $Args->TablePreffix : static::getTablePreffix();

            $binding = [];
            
            switch ($AttrSuffix) {
                case 'Emp':
                case 'PF':
                    if (isset($Args->IdEmpresa) && !empty($Args->IdEmpresa)) {
                        $emp = FsUtils::explodeId($Args->IdEmpresa);
                        $Args->Documento = $emp[0];
                        $Args->IdTipoDocumento = $emp[1];
                    } else if (isset($Args->IdPersonaFisica) && !empty($Args->IdPersonaFisica)) {
                        $pf = FsUtils::explodeId($Args->IdPersonaFisica);
                        $Args->Documento = $pf[0];
                        $Args->IdTipoDocumento = $pf[1];
                    } else if (isset($Args->IdPersonaFisicaTransac) && !empty($Args->IdPersonaFisicaTransac)) {
                        $pf = FsUtils::explodeId($Args->IdPersonaFisicaTransac);
                        $Args->Documento = $pf[0];
                        $Args->IdTipoDocumento = $pf[1];
                    }
                    $binding[':Cat_Documento'] = $Args->Documento;
                    $binding[':Cat_IdTipoDocumento'] = $Args->IdTipoDocumento;
                    $PKs = $lcsuffix . "d.Documento, " . $lcsuffix . "d.IdTipoDocumento, ";
                    $leftJoin = "LEFT JOIN ". $tablePreffix ."DocsItems di on  " . $lcsuffix . "d.nroDoc = di.nroDoc and " . $lcsuffix . "d.Documento = di.Documento and  " . $lcsuffix . "d.IdTipoDocumento = di.IdTipoDocumento";
                    $whereClause = " WHERE Documento = :Cat_Documento AND IdTipoDocumento = :Cat_IdTipoDocumento";
                    break;
                case 'Maq':
                    $PKs = $lcsuffix . "d.NroSerie, ";
                    $whereClause = " WHERE NroSerie = :Cat_NroSerie";
                    $leftJoin = "LEFT JOIN ". $tablePreffix ."DocsItems di on " . $lcsuffix . "d.nroDoc = di.nroDoc and " . $lcsuffix . "d.NroSerie = di.NroSerie";
                    $binding[':Cat_NroSerie'] = $Args->NroSerie;
                    break;
                case 'Vehic':
                    $PKs = $lcsuffix . "d.Serie, " . $lcsuffix . "d.Numero, ";
                    $whereClause = " WHERE Serie = :Cat_Serie AND Numero = :Cat_Numero";
                    $binding[':Cat_Serie'] = $Args->Serie;
                    $binding[':Cat_Numero'] = $Args->Numero;
                    $leftJoin = "LEFT JOIN ". $tablePreffix ."DocsItems di on " . $lcsuffix . "d.nroDoc = di.nroDoc and " . $lcsuffix . "d.Numero = di.Numero and " . $lcsuffix . "d.Serie = di.Serie";
                    break;
            }

            $sql = "SELECT DISTINCT " . $PKs . "
                        " . $lcsuffix . "d.NroDoc,
                        " . $lcsuffix . "d.IdTipoDoc" . $AttrSuffix . ", 
                        td" . $lcsuffix . ".Obligatorio, 
                        " . $lcsuffix . "d.Identificador,
                        " . $lcsuffix . "d.Vto,
                        " . $lcsuffix . "d.Categoria,
                        " . $lcsuffix . "d.Observacion,
                        td" . $lcsuffix . ".TieneVto, 
                        td" . $lcsuffix . ".Nombre,
                        td" . $lcsuffix . ".Nombre AS Tipo,
                        di.Archivo
                     FROM " . $tablePreffix . "Docs " . $lcsuffix . "d 
                     " .$leftJoin. "
                     INNER JOIN TiposDoc" . $AttrSuffix . " td" . $lcsuffix . " ON (" . $lcsuffix . "d.IdTipoDoc" . $AttrSuffix . " = td" . $lcsuffix . ".IdTipoDoc" . $AttrSuffix . ") 
                     INNER JOIN TiposDoc" . $AttrSuffix . "Categorias td" . $lcsuffix . "c ON (td" . $lcsuffix . "c.IdTipoDoc" . $AttrSuffix . " = td" . $lcsuffix . ".IdTipoDoc" . $AttrSuffix . " AND td" . $lcsuffix . "c.IdCategoria = (SELECT TOP 1 IdCategoria From " . $tablePreffix . " " . $whereClause . "))";
            
            list($whereSql, $whereBinding) = FsUtils::whereFromArgs($Args, $lcsuffix . 'd.', [
                'IdCategoria', 'Obligatorio', 'AttrSuffix', 'TablePreffix', 'IdEmpresa', 'IdPersonaFisica', 'IdPersonaFisicaTransac',
            ]);

            if (!empty($whereSql)) {
                $sql .= ' WHERE ' . $whereSql;
                $binding = array_merge($binding, $whereBinding);
            }

            // 1
            if (property_exists($Args,'output') && $Args->output !== 'json') {

            }
 
            return $docs = DB::Select($sql, $binding);

            /**
             * @todo Falta método `self::show()`.
             */
            
            for ($i = 0; $i < count($docs); $i++) {
                $docs[$i]->TablePreffix = $tablePreffix;
                $docs[$i] = static::show($docs[$i]);
            }
            
            return $docs;
            
        } else if (isset($Args->TipoListado)) {
            return self::search($Args);
        } else if (isset($Args->Entidad)) {
            if ($Args->Entidad == 'EMP') {
                $sql = 'SELECT IdTipoDocEmp AS IdTipoDoc, Nombre FROM TiposDocEmp';
            } else if ($Args->Entidad == 'MAQ') {
                $sql = 'SELECT IdTipoDocMaq AS IdTipoDoc, Nombre FROM TiposDocMaq';
            } else if ($Args->Entidad == 'PF') {
                $sql = 'SELECT IdTipoDocPF AS IdTipoDoc, Nombre FROM TiposDocPF';
            } else if ($Args->Entidad == 'VEH') {
                $sql = 'SELECT IdTipoDocVehic AS IdTipoDoc, Nombre FROM TiposDocVehic';
            }

            $sql .= ' ORDER BY Nombre';
            
            return DB::select($sql);
            
        } 

        throw new \Exception;
    }

    public static function search(object $Args)
    {
        $sql = "SELECT * FROM ({{SQL}}) vd ORDER BY {{ORDER_BY}}";
        $orderBy = 'Entidad, Detalle';
        $subSql = '';
        $binding = [];

        switch ($Args->TipoListado) {
            case 'Documentos':
                throw new \Exception('Not implemented');
                // break;
            case 'Faltantes':
                list($subSql, $binding) = self::searchFaltantes($Args);
                $filename = 'FSAcceso-Documentos-Faltantes-Consulta' . date('Ymd his');
                $headers = [
                    'Entidad' => 'Entidad',
                    'DocumentoFaltante' => 'Documento Faltante',
                    'Obligatorio' => 'Obligatorio',
                    'Vto' => 'Vencimiento',
                    'Identificacion' => 'Identificacion',
                    'Detalle' => 'Detalle',
                    'Empresa' => 'Empresa',
                    'Contrato' => 'Contrato',
                    'NroContrato' => 'Número Contrato',
                    'Estado' => 'Estado',
                ];
                break;
            case 'Vencimientos':
                list($subSql, $binding) = self::searchVencimiento($Args);
                $orderBy = 'NumeroDiasRestantes, Entidad, Detalle';
                $filename = 'FSAcceso-Documentos-Con-Vencimientos-Consulta' . date('Ymd his');
                $headers = [
                    'Entidad' => 'Entidad',
                    'DocumentoFaltante' => 'Documento Faltante',
                    'Vto' => 'Vencimiento',
                    'DiasRestantes' => 'Dias Restantes',
                    'Identificacion' => 'Identificacion',
                    'Detalle' => 'Detalle',
                    'Empresa' => 'Empresa',
                    'Contrato' => 'Contrato',
                    'Estado' => 'Estado',
                ];
                break;
        }

        $sql = str_replace(['{{SQL}}', '{{ORDER_BY}}'], [$subSql, $orderBy], $sql);
        
        return([
            'filename' => $filename,
            'data' => DB::select($sql, $binding),
            'headers' => $headers
        ]);
    }

    /// BUSQUEDA POR FALTANTES
    private static function searchFaltantes(object $args): array
    {
        /**
         * @var Usuario $user
         */
        $user = auth()->user();
        $binding = [];

        if (empty($user->isGestion())) {
            $empresa = Empresa::loadBySession(request());
        }

        #region EMPRESA
        if (!isset($args->Entidad) || $args->Entidad === 'EMP') {

            $sql = "SELECT DISTINCT 'func=AdmEmpresas/Documento=' + e.Documento + '/IdTipoDocumento=' + LTRIM(RTRIM(STR(e.IdTipoDocumento))) AS ObjUrl,
                    'Empresa' AS Entidad,
                    tde.Nombre AS DocumentoFaltante,
                    Obligatorio = CASE tde.Obligatorio WHEN 1 THEN 'Sí' ELSE 'No' END,
                    Vto = CASE tde.TieneVto WHEN 1 THEN 'Sí' ELSE 'No' END,
                    dbo.Mask(e.Documento, td.Mascara, 1, 1) AS Identificacion,
                    e.Nombre AS Detalle,
                    e.Nombre AS Empresa,
                    ec.NroContrato AS Contrato,
                    ec.NroContrato,
                    Estado = CASE e.Estado WHEN 1 THEN 'Activo' ELSE 'Inactivo' END
            FROM Empresas e
            INNER JOIN Personas p ON p.Documento = e.Documento AND p.IdTipoDocumento = e.IdTipoDocumento
            INNER JOIN TiposDocumento td ON td.IdTipoDocumento = e.IdTipoDocumento
            INNER JOIN TiposDocEmpCategorias tdec ON tdec.IdCategoria = p.IdCategoria
            INNER JOIN TiposDocEmp tde ON tde.IdTipoDocEmp = tdec.IdTipoDocEmp
            LEFT JOIN EmpresasContratos ec ON ec.Documento = e.Documento AND ec.IdTipoDocumento = e.IdTipoDocumento 
            WHERE p.Baja = 0 AND tdec.IdTipoDocEmp NOT IN
            (
                SELECT DISTINCT ed.IdTipoDocEmp 
                FROM EmpresasDocs ed
                WHERE ed.Documento = e.Documento AND ed.IdTipoDocumento = e.IdTipoDocumento
            )";

            if (empty($user->isGestion())) {
                $empresa = Empresa::loadBySession(request());
                $sql .= ' AND e.Documento = ? AND e.IdTipoDocumento = ?';
                $binding[] = $empresa->Documento;
                $binding[] = $empresa->IdTipoDocumento;
            }
            if (isset($args->IdEmpresa)) {
                $empresa = FsUtils::explodeId($args->IdEmpresa);
                $sql .= ' AND e.Documento = ? AND e.IdTipoDocumento = ?';
                $binding[] = $empresa[0];
                $binding[] = $empresa[1];
            }
            if (isset($args->NroContrato)) {
                $sql .= " AND ec.NroContrato LIKE ?";
                $binding[] = "% " . $args->NroContrato . " %";
            } else if (isset($args->conNroContrato)) {
                $sql .= " AND ec.NroContrato IS NOT NULL AND LEN(ec.NroContrato) > 0";
            }

            if (isset($args->OcultarVencidos)) {
                $sql .= " AND ed.Vto >= GETDATE()";
            }
            if (isset($args->Vencen)) {
                $sql .= " AND (DATEDIFF(dd, GETDATE(), ed.Vto) BETWEEN 0 AND ?)";
                $binding[] = (int)$args->Dias - 1;
            }

            if (isset($args->Obligatorio) && $args->Obligatorio === "true") {
                $sql .= " AND tde.Obligatorio = 1";
            }
            if (isset($args->TieneVto) && $args->TieneVto === "true") {
                $sql .= " AND tde.TieneVto = 1";
            }
        }
        #endregion

        #region MAQUINAS
        if (!isset($args->Entidad) || $args->Entidad == 'MAQ') {

            if (!empty($sql)) {
                $sql .= ' UNION ALL ';
            } else {
                $sql = "";
            }

            $sql .= "SELECT DISTINCT 'func=AdmMaquinas|NroSerie=' + m.NroSerie AS ObjUrl,
                            'Maquina' AS Entidad,
                            tdm.Nombre AS DocumentoFaltante,
                            Obligatorio = CASE tdm.Obligatorio WHEN 1 THEN 'Sí' ELSE 'No' END,
                            Vto = CASE tdm.TieneVto WHEN 1 THEN 'Sí' ELSE 'No' END,
                            m.NroSerie AS Identificacion,
                            mm.Descripcion + ' ' + m.Modelo + ' (' + tm.Descripcion + ')' AS Detalle,
                            e.Nombre AS Empresa,
                            mc.NroContrato + ' (' + e.Nombre + ')' AS Contrato,
                            mc.NroContrato,
                            Estado = CASE m.Estado WHEN 1 THEN 'Activo' ELSE 'Inactivo' END
                    FROM Maquinas m
                    INNER JOIN MarcasMaquinas mm ON mm.IdMarcaMaq = m.IdMarcaMaq
                    INNER JOIN TiposMaquinas tm ON tm.IdTipoMaquina = m.IdTipoMaq
                    INNER JOIN TiposDocMaqCategorias tdmc ON tdmc.IdCategoria = m.IdCategoria
                    INNER JOIN TiposDocMaq tdm ON tdm.IdTipoDocMaq = tdmc.IdTipoDocMaq
                    LEFT JOIN Empresas e ON e.Documento = m.DocEmpresa AND e.IdTipoDocumento = m.TipoDocEmp
                    LEFT JOIN MaquinasContratos mc ON mc.DocEmpCont = e.Documento AND mc.IdTipoDocCont = e.IdTipoDocumento 
                    WHERE m.Baja = 0 AND tdmc.IdTipoDocMaq NOT IN
                    (
                        SELECT DISTINCT md.IdTipoDocMaq 
                        FROM MaquinasDocs md
                        WHERE md.NroSerie = m.NroSerie
                    )";

            if (empty($user->isGestion())) {
                $empresa = Empresa::loadBySession(request());
                $sql .= ' AND e.Documento = ? AND e.IdTipoDocumento = ?';
                $binding[] = $empresa->Documento;
                $binding[] = $empresa->IdTipoDocumento;
            } 
            if (isset($args->IdEmpresa)) {
                $empresa = FsUtils::explodeId($args->IdEmpresa);
                $sql .= " AND m.DocEmpresa = ? AND m.TipoDocEmp = ? ";
                $binding[] = $empresa[0];
                $binding[] = $empresa[1];
            }
            if (isset($args->NroContrato)) {
                $sql .= " AND mc.NroContrato LIKE ?";
                $binding[] = "% " . $args->NroContrato . " %";
            } else if (isset($args->ConNroContrato)) {
                $sql .= " AND mc.NroContrato IS NOT NULL AND LEN(mc.NroContrato) > 0";
            }

            if (isset($args->OcultarVencidos)) {
                $sql .= " AND md.Vto >= GETDATE()";
            }
            if (isset($args->Vencen)) {
                $sql .= " AND (DATEDIFF(dd, GETDATE(), md.Vto) BETWEEN 0 AND ?)";
                $binding[] = (int)$args->Dias - 1;
            }

            if (isset($args->Obligatorio) && $args->Obligatorio === "true") {
                $sql .= " AND tdm.Obligatorio = 1";
            }
            if (isset($args->TieneVto) && $args->TieneVto === "true") {
                $sql .= " AND tdm.TieneVto = 1";
            }
        }
        #endregion

        #region PERSONAS FÍSICAS
        if (!isset($args->Entidad) || $args->Entidad == 'PF') {

            if (!empty($sql)) {
                $sql .= ' UNION ALL ';
            } else {
                $sql = "";
            }

            $sql .= "SELECT DISTINCT 'func=AdmPersonas|Documento=' + pf.Documento + '|IdTipoDocumento=' + LTRIM(RTRIM(STR(pf.IdTipoDocumento))) AS ObjUrl,
                            'Persona' AS Entidad,
                            tdpf.Nombre AS DocumentoFaltante,
                            Obligatorio = CASE tdpf.Obligatorio WHEN 1 THEN 'Sí' ELSE 'No' END,
                            Vto = CASE tdpf.TieneVto WHEN 1 THEN 'Sí' ELSE 'No' END,
                            dbo.Mask(pf.Documento, td.Mascara, 1, 1) AS Identificacion,
                            pf.PrimerNombre + ' ' + pf.SegundoNombre + ' ' + pf.PrimerApellido + ' ' + pf.SegundoApellido AS Detalle,
                            e.Nombre AS Empresa,
                            pfc.NroContrato + ' (' + pfce.Nombre + ')' AS Contrato,
                            pfc.NroContrato,
                            Estado = CASE pf.Estado WHEN 1 THEN 'Activo' ELSE 'Inactivo' END
                    FROM PersonasFisicas pf
                    INNER JOIN Personas p ON p.Documento = pf.Documento AND p.IdTipoDocumento = pf.IdTipoDocumento
                    INNER JOIN TiposDocumento td ON td.IdTipoDocumento = pf.IdTipoDocumento
                    INNER JOIN TiposDocPFCategorias tdpfc ON tdpfc.IdCategoria = p.IdCategoria
                    INNER JOIN TiposDocPF tdpf ON tdpf.IdTipoDocPF = tdpfc.IdTipoDocPF
                    LEFT JOIN PersonasFisicasEmpresas pfe ON pfe.Documento = pf.Documento AND pfe.IdTipoDocumento = pf.IdTipoDocumento AND pfe.FechaBaja IS NULL
                    LEFT JOIN Empresas e ON e.Documento = pfe.DocEmpresa AND e.IdTipoDocumento = pfe.TipoDocEmpresa
                    LEFT JOIN PersonasFisicasContratos pfc ON pfc.Documento = pf.Documento AND pfc.IdTipoDocumento = pf.IdTipoDocumento AND pfc.DocEmpresa = pfe.DocEmpresa AND pfc.TipoDocEmpresa = pfe.TipoDocEmpresa
                    LEFT JOIN Empresas pfce ON pfce.Documento = pfc.DocEmpCont AND e.IdTipoDocumento = pfc.IdTipoDocCont
                    WHERE p.Baja = 0 AND tdpfc.IdTipoDocPF NOT IN
                    (
                        SELECT DISTINCT pfd.IdTipoDocPF
                        FROM PersonasFisicasDocs pfd
                        WHERE pfd.Documento = pf.Documento AND pfd.IdTipoDocumento = pf.IdTipoDocumento
                    )";

            if (empty($user->isGestion())) {
                $sql .= ' AND pfe.DocEmpresa = ? AND pfe.TipoDocEmpresa = ?';
                $binding[] = $empresa->Documento;
                $binding[] = $empresa->IdTipoDocumento;
            }
            if (isset($args->IdEmpresa)) {
                $empresa = FsUtils::explodeId($args->IdEmpresa);
                $sql .= " AND pf.DocEmpresa = ? AND pf.TipoDocEmpresa = ? ";
                $binding[] = $empresa[0];
                $binding[] = $empresa[1];
            }
            if (isset($args->NroContrato)) {
                $sql .= " AND pfc.NroContrato LIKE ?";
                $binding[] = "% " . $args->NroContrato . " %";
            } else if (isset($args->ConNroContrato)) {
                $sql .= " AND pfc.NroContrato IS NOT NULL AND LEN(pfc.NroContrato) > 0";
            }

            if (isset($args->OcultarVencidos)) {
                $sql .= " AND pfd.Vto >= GETDATE()";
            }
            if (isset($args->Vencen)) {
                $sql .= " AND (DATEDIFF(dd, GETDATE(), pfd.Vto) BETWEEN 0 AND ?)";
                $binding[] = (int)$args->Dias - 1;
            }

            if (isset($args->Obligatorio) && $args->Obligatorio === "true") {
                $sql .= " AND tdpf.Obligatorio = 1";
            }
            if (isset($args->TieneVto) && $args->TieneVto === "true") {
                $sql .= " AND tdpf.TieneVto = 1";
            }
        }
        #endregion

        #region VEHICULOS
        if (!isset($args->Entidad) || $args->Entidad == 'VEH') {

            if (!empty($sql)) {
                $sql .= ' UNION ALL ';
            } else {
                $sql = "";
            }

            $sql .= "SELECT DISTINCT 'func=AdmVehiculos|Serie=' + v.Serie + '|Numero=' + LTRIM(RTRIM(STR(v.Numero))) AS ObjUrl,                         
                            'Vehículo' AS Entidad,
                            tdv.Nombre AS DocumentoFaltante,
                            Obligatorio = CASE tdv.Obligatorio WHEN 1 THEN 'Sí' ELSE 'No' END,
                            Vto = CASE tdv.TieneVto WHEN 1 THEN 'Sí' ELSE 'No' END,
                            v.Serie + ' ' + RTRIM(LTRIM(STR(v.Numero))) AS Identificacion,
                            mv.Descripcion + ' ' + v.Modelo + ' (' + tv.Descripcion + ')' AS Detalle,
                            '' AS Empresa,
                            vc.NroContrato + ' (' + e.Nombre + ')' AS Contrato,
                            vc.NroContrato,
                            Estado = CASE v.Estado WHEN 1 THEN 'Activo' ELSE 'Inactivo' END
                    FROM Vehiculos v
                    INNER JOIN MarcasVehiculos mv ON mv.IdMarcaVehic = v.IdMarcaVehic
                    INNER JOIN TiposVehiculos tv ON tv.IdTipoVehiculo = v.IdTipoVehiculo
                    INNER JOIN TiposDocVehicCategorias tdvc ON tdvc.IdCategoria = v.IdCategoria
                    INNER JOIN TiposDocVehic tdv ON tdv.IdTipoDocVehic = tdvc.IdTipoDocVehic
                    LEFT JOIN Empresas e ON e.Documento = v.DocEmpresa AND e.IdTipoDocumento = v.TipoDocEmp
                    LEFT JOIN VehiculosContratos vc ON vc.DocEmpCont = e.Documento AND vc.IdTipoDocCont = e.IdTipoDocumento 
                    WHERE v.Baja = 0 AND tdvc.IdTipoDocVehic NOT IN
                    (
                        SELECT DISTINCT vd.IdTipoDocVehic
                        FROM VehiculosDocs vd
                        WHERE vd.Serie = v.Serie AND vd.Numero = v.Numero
                    )";

            if (empty($user->Gestion)) {
                $sql .= ' AND e.Documento = ? AND e.IdTipoDocumento = ?';
                $binding[] = $empresa->Documento;
                $binding[] = $empresa->IdTipoDocumento;
            }
            if (!empty($args->IdEmpresa)) {
                $empresa = FsUtils::explodeId($args->IdEmpresa);
                $sql .= " AND v.DocEmpresa = ? AND v.TipoDocEmp = ? ";
                $binding[] = $empresa[0];
                $binding[] = $empresa[1];
            }
            if (isset($args->NroContrato)) {
                $sql .= " AND vc.NroContrato LIKE ?";
                $binding[] = "% " . $args->NroContrato . " %";
            } else if (isset($args->ConNroContrato)) {
                $sql .= " AND vc.NroContrato IS NOT NULL AND LEN(vc.NroContrato) > 0";
            }
        }

        if (isset($args->OcultarVencidos)) {
            $sql .= " AND vd.Vto >= GETDATE()";
        }
        if (isset($args->Vencen)) {
            $sql .= " AND (DATEDIFF(dd, GETDATE(), vd.Vto) BETWEEN 0 AND ?)";
            $binding[] = (int)$args->Dias - 1;
        }

        if (isset($args->Obligatorio) && $args->Obligatorio === "true") {
            $sql .= " AND tdv.Obligatorio = 1";
        }
        if (isset($args->TieneVto) && $args->TieneVto === "true") {
            $sql .= " AND tdv.TieneVto = 1";
        }

        #endregion
        return [$sql, $binding];
    }
    
    /// BUSQUEDA POR VENCIMIENTO
    private static function searchVencimiento(object $Args): array
    {
        /**
         * @var Usuario $user
         */
        $user = auth()->user();
        $sqls = [];
        $binding = [];

        if (!$user->isGestion()) {
            $empresa = Empresa::loadBySession(request());
        }

        #region Empresa 
        if (!isset($Args->Entidad) || $Args->Entidad === 'EMP') {
            $sqlAux = '';

            $sql = "SELECT DISTINCT 'func=AdmEmpresas|Documento=' + e.Documento + '|IdTipoDocumento=' + LTRIM(RTRIM(STR(e.IdTipoDocumento))) AS ObjUrl,
                            'Empresa' AS Entidad,
                            dbo.Mask(e.Documento, td.Mascara, 1, 1) AS Identificacion,
                            e.Nombre AS Detalle,
                            e.Nombre AS Empresa,
                            CASE WHEN ec.NroContrato IS NOT NULL THEN ec.NroContrato ELSE ec1.NroContrato END AS Contrato,
                            tde.Nombre AS DocumentoFaltante,
                            ed.Vto,
                            DATEDIFF(dd, GETDATE(), ed.Vto) AS NumeroDiasRestantes,
                            DiasRestantes = CASE WHEN DATEDIFF(dd, GETDATE(), ed.Vto) > 0 THEN LTRIM(RTRIM(STR(DATEDIFF(dd, GETDATE(), ed.Vto)))) ELSE CASE WHEN DATEDIFF(dd, GETDATE(), ed.Vto) = 0 THEN 'Vence hoy' ELSE 'Vencido' END END,
                            Estado = CASE e.Estado WHEN 1 THEN 'Activo' ELSE 'Inactivo' END
                    FROM EmpresasDocs ED
                    INNER JOIN Empresas E ON ED.idTipoDocumento = E.idTipoDocumento AND ED.documento = E.documento
                    INNER JOIN Personas P ON P.idTipoDocumento = E.idTipoDocumento AND P.documento = E.documento
                    INNER JOIN TiposDocumento TD ON P.IdTipoDocumento = td.IdTipoDocumento                  
                    INNER JOIN TiposDocEmp TDE ON ED.idTipoDocEmp = TDE.idTipoDocEmp
                    LEFT JOIN EmpresasContratos ec ON ec.Documento = e.Documento AND ec.IdTipoDocumento = e.IdTipoDocumento
                    LEFT JOIN EmpresasContratos ec1 ON ec1.DocEmpCont = e.Documento AND ec1.IdTipoDocCont = e.IdTipoDocumento ";

            #region WHERE 
            $sql .= "WHERE NOT ED.vto IS NULL AND TDE.tieneVto = 1 AND P.baja = 0";

            if (!$user->isGestion()) {
                $sql .= ' AND e.Documento = ? AND e.IdTipoDocumento = ?';
                $binding[] = $empresa->Documento;
                $binding[] = $empresa->IdTipoDocumento;
            }

            if (isset($Args->OcultarVencidos)) {
                $sqlAux = "DATEDIFF(dd, GETDATE(), ed.Vto) >= 0";
            }
            if (isset($Args->OcultarVencidos) && isset($Args->DiasRestantes)) {
                $sqlAux .= " AND ";
            }
            if (isset($Args->DiasRestantes)) {
                $sqlAux .= "DATEDIFF(dd, GETDATE(), ed.Vto) <= ?";
                $binding[] = $Args->DiasRestantes;
            }
            $sql .= !empty($sqlAux) ? " AND (" . $sqlAux . ")" : "";

            if (isset($Args->IdEmpresa)) {
                $IdEmpresa = FsUtils::explodeId($Args->IdEmpresa);
                $sql .= " AND e.Documento = ? AND e.IdTipoDocumento = ?";
                $binding[] = $IdEmpresa[0];
                $binding[] = $IdEmpresa[1];
            }
            
            if (isset($Args->NroContrato)) {
                $sql .= " AND (ec.NroContrato LIKE ? OR ec1.NroContrato LIKE ?)";
                $binding[] = '%' . $Args->NroContrato . '%';
                $binding[] = '%' . $Args->NroContrato . '%';
            } else if (isset($Args->ConNroContrato)) {
                $sql .= " AND ec.NroContrato IS NOT NULL AND LEN(ec.NroContrato) > 0";
            }
            #endregion

            $sqls[] = $sql;
        }
        #endregion

        #region Maquinas 
        if (!isset($Args->Entidad) || $Args->Entidad == 'MAQ') {
            $sqlAux = '';
            
            $sql = "SELECT DISTINCT 'func=AdmMaquinas|NroSerie=' + m.NroSerie AS ObjUrl,
                            'Máquina' AS Entidad,
                            m.NroSerie AS Identificacion,
                            mm.Descripcion + ' ' + m.Modelo + ' (' + tm.Descripcion + ')' AS Detalle,
                            emp.Nombre AS Empresa,
                            mc.NroContrato + ' (' + emp.Nombre + ')' AS Contrato,
                            tdm.Nombre AS DocumentoFaltante,
                            md.Vto,
                            DATEDIFF(dd, GETDATE(), md.Vto) AS NumeroDiasRestantes,
                            DiasRestantes = CASE WHEN DATEDIFF(dd, GETDATE(), md.Vto) > 0 THEN LTRIM(RTRIM(STR(DATEDIFF(dd, GETDATE(), md.Vto)))) ELSE CASE WHEN DATEDIFF(dd, GETDATE(), md.Vto) = 0 THEN 'Vence hoy' ELSE 'Vencido' END END,
                            Estado = CASE m.Estado WHEN 1 THEN 'Activo' ELSE 'Inactivo' END
                    FROM MaquinasDocs MD INNER JOIN Maquinas M ON MD.nroSerie = M.nroSerie
                    INNER JOIN TiposDocMaq TDM ON MD.idTipoDocMaq = TDM.idTipoDocMaq
                    INNER JOIN MarcasMaquinas MM ON M.idMarcaMaq = MM.idMarcaMaq
                    INNER JOIN TiposMaquinas TM ON M.idTipoMaq = TM.idTipoMaquina
                    INNER JOIN Empresas EMP ON M.tipoDocEmp = EMP.idTipoDocumento AND M.docEmpresa = EMP.documento
                    LEFT JOIN MaquinasContratos mc ON mc.NroSerie = m.NroSerie ";
            
            #region WHERE 
            $sql .= "WHERE NOT MD.vto IS NULL AND TDM.tieneVto = 1 AND M.baja = 0 ";

            if (!$user->isGestion()) {
                $sql .= " AND m.DocEmpresa = ? AND m.TipoDocEmp = ?";
                $binding[] = $empresa->Documento;
                $binding[] = $empresa->IdTipoDocumento;
            }

            if (isset($Args->OcultarVencidos)) {
                $sqlAux = "DATEDIFF(dd, GETDATE(), md.Vto) >= 0";
            }
            if (isset($Args->OcultarVencidos) && isset($Args->DiasRestantes)) {
                $sqlAux .= " AND ";
            }
            if (isset($Args->DiasRestantes)) {
                $sqlAux .= "DATEDIFF(dd, GETDATE(), md.Vto) <= ?";
                $binding[] = $Args->DiasRestantes;
            }
            $sql .= !empty($sqlAux) ? " AND (" . $sqlAux . ")" : "";
            
            if (isset($Args->IdEmpresa)) {
                $IdEmpresa = FsUtils::explodeId($Args->IdEmpresa);
                $sql .= " AND emp.Documento = ? AND emp.IdTipoDocumento = ?";
                $binding[] = $IdEmpresa[0];
                $binding[] = $IdEmpresa[1];
            }
            
            if (isset($Args->NroContrato)) {
                $sql .= " AND mc.NroContrato LIKE ?";
                $binding[] = '%' . $Args->NroContrato . '%';
            } else if (isset($Args->ConNroContrato)) {
                $sql .= " AND mc.NroContrato IS NOT NULL AND LEN(mc.NroContrato) > 0";
            }
            #endregion

            $sqls[] = $sql;
        }
        #endregion

        #region Personas Fisicas 
        if (!isset($Args->Entidad) || $Args->Entidad == 'PF') {
            $sqlAux = '';

            $sql = "SELECT DISTINCT 
                        'func=AdmPersonas|Documento=' + pf.Documento + '|IdTipoDocumento=' + LTRIM(RTRIM(STR(pf.IdTipoDocumento))) AS ObjUrl,
                        'Persona' AS Entidad,
                        dbo.Mask(pf.Documento, td.Mascara, 1, 1) AS Identificacion,
                        pf.NombreCompleto AS Detalle,
                        emp.Nombre AS Empresa,
                        pfc.NroContrato + ' (' + pfce.Nombre + ')' AS Contrato,
                        tdpf.Nombre AS DocumentoFaltante,
                        pfd.Vto,
                        DATEDIFF(dd, GETDATE(), pfd.Vto) AS NumeroDiasRestantes,
                        DiasRestantes = CASE WHEN DATEDIFF(dd, GETDATE(), pfd.Vto) > 0 THEN LTRIM(RTRIM(STR(DATEDIFF(dd, GETDATE(), pfd.Vto)))) ELSE CASE WHEN DATEDIFF(dd, GETDATE(), pfd.Vto) = 0 THEN 'Vence hoy' ELSE 'Vencido' END END,
                        Estado = CASE PF.Estado WHEN 1 THEN 'Activo' ELSE 'Inactivo' END
                    FROM PersonasFisicasDocs PFD 
                    INNER JOIN PersonasFisicas PF ON PFD.idTipoDocumento = PF.idTipoDocumento AND PFD.documento = PF.documento AND PF.Transito = 0
                    INNER JOIN TiposDocumento TD ON PF.idTipoDocumento = TD.idTipoDocumento
                    INNER JOIN Personas P ON P.idTipoDocumento = PF.idTipoDocumento AND P.documento = PF.documento
                    INNER JOIN TiposDocPF TDPF ON PFD.idTipoDocPF = TDPF.idTipoDocPF
                    INNER JOIN PersonasFisicasEmpresas PFE ON PFE.Documento = PF.Documento AND PFE.IdTipoDocumento = PF.IdTipoDocumento AND (PFE.FechaBaja IS NULL OR PFE.FechaBaja >= GETDATE())
                    INNER JOIN Empresas EMP ON PFE.tipoDocEmpresa = EMP.idTipoDocumento AND PFE.docEmpresa = EMP.documento
                    LEFT JOIN PersonasFisicasContratos pfc ON pfc.Documento = pf.Documento AND pfc.IdTipoDocumento = pf.IdTipoDocumento AND pfc.DocEmpresa = pfe.DocEmpresa AND pfc.TipoDocEmpresa = pfe.TipoDocEmpresa
                    LEFT JOIN Empresas pfce ON pfce.Documento = pfc.DocEmpCont AND pfce.IdTipoDocumento = pfc.IdTipoDocCont
                    WHERE NOT PFD.vto IS NULL
                    AND TDPF.tieneVto = 1
                    AND P.baja = 0";
            
            #region WHERE 
            if (!$user->isGestion()) {
                $sql .= " AND pfe.DocEmpresa = ? AND pfe.TipoDocEmpresa = ?";
                $binding[] = $empresa->Documento;
                $binding[] = $empresa->IdTipoDocumento;
            }

            if (isset($Args->OcultarVencidos)) {
                $sqlAux = "DATEDIFF(dd, GETDATE(), pfd.Vto) >= 0";
            }
            if (isset($Args->OcultarVencidos) && isset($Args->DiasRestantes)) {
                $sqlAux .= " AND ";
            }
            if (isset($Args->DiasRestantes)) {
                $sqlAux .= "DATEDIFF(dd, GETDATE(), pfd.Vto) <= ?";
                $binding[] = $Args->DiasRestantes;
            }
            $sql .= !empty($sqlAux) ? " AND (" . $sqlAux . ")" : "";

            if (isset($Args->IdEmpresa)) {
                $IdEmpresa = FsUtils::explodeId($Args->IdEmpresa);
                $sql .= " AND emp.Documento = ? AND emp.IdTipoDocumento = ?";
                $binding[] = $IdEmpresa[0];
                $binding[] = $IdEmpresa[1];
            }

            if (isset($Args->NroContrato)) {
                $sql .= " AND pfc.NroContrato LIKE ?";
                $binding[] = '%' . $Args->NroContrato . '%';
            } else if (isset($Args->ConNroContrato)) {
                $sql .= " AND pfc.NroContrato IS NOT NULL AND LEN(pfc.NroContrato) > 0";
            }
            #endregion
            
            $sqls[] = $sql;

            $sqlAux = '';

            $sql = "SELECT DISTINCT 
                        'func=AdmPersonas|Documento=' + pf.Documento + '|IdTipoDocumento=' + LTRIM(RTRIM(STR(pf.IdTipoDocumento))) AS ObjUrl,
                        'Persona' AS Entidad,
                        dbo.Mask(pf.Documento, td.Mascara, 1, 1) AS Identificacion,
                        pf.PrimerNombre + ' ' + pf.SegundoNombre + ' ' + pf.PrimerApellido + ' ' + pf.SegundoApellido AS Detalle,
                        emp.Nombre AS Empresa,
                        pfc.NroContrato + ' (' + pfce.Nombre + ')' AS Contrato,
                        'Documento de Identidad' AS DocumentoFaltante,
                        pf.fechaVtoDoc AS Vto,
                        DATEDIFF(dd, GETDATE(), pf.fechaVtoDoc) AS NumeroDiasRestantes,
                        DiasRestantes = CASE WHEN DATEDIFF(dd, GETDATE(), pf.fechaVtoDoc) > 0 THEN LTRIM(RTRIM(STR(DATEDIFF(dd, GETDATE(), pf.fechaVtoDoc)))) ELSE CASE WHEN DATEDIFF(dd, GETDATE(), pf.fechaVtoDoc) = 0 THEN 'Vence hoy' ELSE 'Vencido' END END,
                        Estado = CASE PF.Estado WHEN 1 THEN 'Activo' ELSE 'Inactivo' END
                    FROM PersonasFisicas PF 
                    INNER JOIN TiposDocumento TD ON PF.idTipoDocumento = TD.idTipoDocumento
                    INNER JOIN Personas P ON P.idTipoDocumento = PF.idTipoDocumento AND P.documento = PF.documento
                    INNER JOIN PersonasFisicasEmpresas PFE ON PFE.Documento = PF.Documento AND PFE.IdTipoDocumento = PF.IdTipoDocumento AND (PFE.FechaBaja IS NULL OR PFE.FechaBaja >= GETDATE())
                    INNER JOIN Empresas EMP ON PFE.tipoDocEmpresa = EMP.idTipoDocumento AND PFE.docEmpresa = EMP.documento
                    LEFT JOIN PersonasFisicasContratos pfc ON pfc.Documento = pf.Documento AND pfc.IdTipoDocumento = pf.IdTipoDocumento AND pfc.DocEmpresa = pfe.DocEmpresa AND pfc.TipoDocEmpresa = pfe.TipoDocEmpresa
                    LEFT JOIN Empresas pfce ON pfce.Documento = pfc.DocEmpCont AND pfce.IdTipoDocumento = pfc.IdTipoDocCont
                    WHERE P.baja = 0 
                    AND PF.Transito = 0 ";
            
            #region WHERE 
            if (!$user->isGestion()) {
                $sql .= " AND pfe.DocEmpresa = ? AND pfe.TipoDocEmpresa = ?";
                $binding[] = $empresa->Documento;
                $binding[] = $empresa->IdTipoDocumento;
            }

            if (isset($Args->OcultarVencidos)) {
                $sqlAux = "DATEDIFF(dd, GETDATE(), pf.fechaVtoDoc) >= 0";
            }
            if (isset($Args->OcultarVencidos) && isset($Args->DiasRestantes)) {
                $sqlAux .= " AND ";
            }
            if (isset($Args->DiasRestantes)) {
                $sqlAux .= "DATEDIFF(dd, GETDATE(), PF.fechaVtoDoc) <= ?";
                $binding[] = $Args->DiasRestantes;
            }
            $sql .= !empty($sqlAux) ? " AND (" . $sqlAux . ")" : "";
            
            if (isset($Args->IdEmpresa)) {
                $IdEmpresa = FsUtils::explodeId($Args->IdEmpresa);
                $sql .= " AND emp.Documento = ? AND emp.IdTipoDocumento = ?";
                $binding[] = $IdEmpresa[0];
                $binding[] = $IdEmpresa[1];
            }

            if (isset($Args->NroContrato)) {
                $sql .= " AND pfc.NroContrato LIKE ?";
                $binding[] = '%' . $Args->NroContrato . '%';
            } else if (isset($Args->ConNroContrato)) {
                $sql .= " AND pfc.NroContrato IS NOT NULL AND LEN(pfc.NroContrato) > 0";
            }
            #endregion

            $sqls[] = $sql;
        }
        #endregion

        #region Vehiculos
        if (!isset($Args->Entidad) || $Args->Entidad === 'VEH')
        {
            $sqlAux = '';

            $sql = "SELECT DISTINCT 'func=AdmVehiculos|Serie=' + v.Serie + '|Numero=' + LTRIM(RTRIM(STR(v.Numero))) AS ObjUrl, 
                                'Vehículo' AS Entidad,
                                v.Serie + ' ' + RTRIM(LTRIM(STR(v.Numero))) AS Identificacion,
                                mv.Descripcion + ' ' + v.Modelo + ' (' + tv.Descripcion + ')' AS Detalle,
                                emp.Nombre AS Empresa,
                                vc.NroContrato + ' (' + emp.Nombre + ')' AS Contrato,
                                tdv.Nombre AS DocumentoFaltante,
                                vd.Vto,
                                DATEDIFF(dd, GETDATE(), vd.Vto) AS NumeroDiasRestantes,
                                DiasRestantes = CASE WHEN DATEDIFF(dd, GETDATE(), vd.Vto) > 0 THEN LTRIM(RTRIM(STR(DATEDIFF(dd, GETDATE(), vd.Vto)))) ELSE CASE WHEN DATEDIFF(dd, GETDATE(), vd.Vto) = 0 THEN 'Vence hoy' ELSE 'Vencido' END END,
                                Estado = CASE v.Estado WHEN 1 THEN 'Activo' ELSE 'Inactivo' END
                        FROM VehiculosDocs VD INNER JOIN Vehiculos V ON VD.serie = V.serie AND VD.numero = V.numero
                        INNER JOIN TiposDocVehic TDV ON VD.idTipoDocVehic = TDV.idTipoDocVehic
                        INNER JOIN MarcasVehiculos MV ON V.idMarcaVehic = MV.idMarcaVehic
                        INNER JOIN TiposVehiculos TV ON V.idTipoVehiculo = TV.idTipoVehiculo
                        INNER JOIN Empresas EMP ON V.tipoDocEmp = EMP.idTipoDocumento AND V.docEmpresa = EMP.documento";
            
            if (!empty($Args->NroContrato)) {
                $sql .= " INNER JOIN VehiculosContratos vc ON vc.Serie = v.Serie AND vc.Numero = v.Numero ";
            } else {
                $sql .= " LEFT JOIN VehiculosContratos vc ON vc.Serie = v.Serie AND vc.Numero = v.Numero ";
            }
            
            #region WHERE 
            $sql .= "WHERE NOT VD.vto IS NULL AND TDV.tieneVto = 1 AND V.baja = 0 ";

            if (!$user->isGestion()) {
                $sql .= " AND v.DocEmpresa = ? AND v.TipoDocEmp = ?";
                $binding[] = $empresa->Documento;
                $binding[] = $empresa->IdTipoDocumento;
            }

            if (isset($Args->OcultarVencidos)) {
                $sqlAux = "DATEDIFF(dd, GETDATE(), vd.Vto) >= 0";
            }
            if (isset($Args->OcultarVencidos) && isset($Args->DiasRestantes)) {
                $sqlAux .= " AND ";
            }
            if (isset($Args->DiasRestantes)) {
                $sqlAux .= "DATEDIFF(dd, GETDATE(), vd.Vto) <= ?";
                $binding[] = $Args->DiasRestantes;
            }
            $sql .= !empty($sqlAux) ? " AND (" . $sqlAux . ")" : "";

            if (isset($Args->IdEmpresa)) {
                $IdEmpresa = FsUtils::explodeId($Args->IdEmpresa);
                $sql .= " AND emp.Documento = ? AND emp.IdTipoDocumento = ?";
                $binding[] = $IdEmpresa[0];
                $binding[] = $IdEmpresa[1];
            }

            if (isset($Args->NroContrato)) {
                $sql .= " AND vc.NroContrato LIKE ?";
                $binding[] = '%' . $Args->NroContrato . '%';
            } else if (isset($Args->ConNroContrato)) {
                $sql .= " AND Vc.NroContrato IS NOT NULL AND LEN(vc.NroContrato) > 0";
            }
            #endregion
            
            $sqls[] = $sql;
        }
        #endregion
        
        $sql = implode(' UNION ALL ', $sqls);
        return [$sql, $binding];
    }
}
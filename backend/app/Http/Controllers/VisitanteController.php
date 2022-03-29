<?php

namespace App\Http\Controllers;

use App\Mails\Visitante\AltaMailSolicitante;
use App\Mails\Visitante\AprobarMailSolicitante;
use App\Mails\Visitante\RechazarMailSolicitante;

use App\FsUtils;
use App\ImprimirMatricula;
use App\Integrations\OnGuard;
use App\Models\Acceso;
use App\Models\BaseModel;
use App\Models\Categoria;
use App\Models\Documento;
use App\Models\Empresa;
use App\Models\LogAuditoria;
use App\Models\Matricula;
use App\Models\Visitante;
use App\Models\Persona;
use App\Models\TipoDocumentoVis;
use Carbon\Carbon;
use Exception;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class VisitanteController extends Controller {
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
        $items = array_merge($this->listadoNoTransac(), $this->listadoTransac());

        usort($items, function ($a, $b) {
            return $a->NombreCompleto > $b->NombreCompleto;
        });

        $output = $this->req->input('output', 'json');

        if ($output !== 'json') {
            $dataOutput = array_map(function($item) {
                return [
                    'Estado' => $item->Estado,
                    'Documento' => $item->Documento,
                    'Nombre' => $item->NombreCompleto ? $item->NombreCompleto : '',
                    'Matricula' => $item->Matricula ? $item->Matricula : '',
                ];
            },$items);

            $filename = 'FSAcceso-Visitante-' . date('Ymd his');
            
            $headers = [
                'Estado' => 'Estado',
                'Documento' => 'Documento',
                'Nombre' => 'Nombre',
                'Matricula' => 'Matrícula',
            ];
            
            return FsUtils::export($output, $dataOutput, $headers, $filename);
        }

        $page = (int)$this->req->input('page', 1);

        $paginate = FsUtils::paginateArray($items, $this->req);

        return $this->responsePaginate($paginate->items(), $paginate->total(), $page);
    }

    private function listadoNoTransac()
    {
        $bindings = [];
        $sql = "SELECT  CASE pf.Estado
                            WHEN 1 THEN 'active'
                            ELSE 'inactive'
                        END AS FsRC,
                        CASE pf.Estado
                            WHEN 1 THEN 'Activo'
                            ELSE 'Inactivo'
                        END AS Estado,
                        '' AS AccionRemotaToken,
                        p.Documento, 
                        dbo.Mask(p.Documento, td.Mascara, 1, 1) AS DocumentoMasked,
                        p.IdTipoDocumento, 
                        td.Descripcion TipoDocumento, 
                        pf.NombreCompleto,
                        pf.Sexo, 
                        e.Nombre AS NombreEmpresaVisit, 
                        '' AS CorreoSolicitante,
                        p.IdCategoria, 
                        c.Descripcion AS Categoria, 
                        pf.Matricula, 
                        pf.VigenciaDesde, 
                        pf.VigenciaHasta,
                        '' AS EmpresaSolicitante,
                        '' AS NombreEmpresa,
                        '' AS NombreVisitante,
                        '' AS NombrePersonaVisit,
                        pf.Autorizante
                FROM Personas p 
                INNER JOIN PersonasFisicas pf ON p.Documento = pf.Documento AND p.IdTipoDocumento = pf.IdTipoDocumento
                INNER JOIN TiposDocumento td ON p.IdTipoDocumento = td.IdTipoDocumento
                INNER JOIN Categorias c ON p.IdCategoria = c.IdCategoria
                LEFT JOIN Empresas e ON pf.DocEmpresaVisit = e.Documento AND pf.TipoDocEmpresaVisit = e.IdTipoDocumento
                WHERE pf.Transito = 1 
                AND p.Baja = 0
                AND NOT EXISTS (SELECT DISTINCT
                                    pft.Documento, 
                                    pft.IdTipoDocumento 
                                FROM PersonasFisicasTransac pft 
                                WHERE pft.Transito = 1
                                AND pft.Completada = 0
                                AND pft.Documento = pf.Documento
                                AND pft.IdTipoDocumento = pf.IdTipoDocumento)";

        if (!$this->user->isGestion()) {
            $empresa = Empresa::loadBySession($this->req); // se lo envía desde el index, cuando soy un Solicitante por ejemplo.
            $sql .= " AND pft.DocEmpresaVisit = ':doc_empresa AND pft.TipoDocEmpresaVisit = :tipo_doc_empresa";
            $bindings[':doc_empresa'] = $empresa->Documento;
            $bindings[':tipo_doc_empresa'] = $empresa->IdTipoDocumento;
        }

        if (null !== ($busqueda = $this->req->input('Busqueda'))) {
            $sql .= " AND (REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(pf.Documento, '_', ''), '-', ''), ';', ''), ',', ''), ':', ''), '.', '') COLLATE Latin1_general_CI_AI LIKE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(:Busqueda, '_', ''), '-', ''), ';', ''), ',', ''), ':', ''), '.', '') COLLATE Latin1_general_CI_AI OR "
                    . "pf.NombreCompleto COLLATE Latin1_general_CI_AI LIKE :Busqueda2 COLLATE Latin1_general_CI_AI OR "
                    . "CONVERT(varchar(18), pf.matricula) COLLATE Latin1_general_CI_AI LIKE :Busqueda3 COLLATE Latin1_general_CI_AI OR "
                    . "e.Nombre COLLATE Latin1_general_CI_AI LIKE :Busqueda4 COLLATE Latin1_general_CI_AI)";

            $bindings[':Busqueda'] = "%" . $busqueda . "%";
            $bindings[':Busqueda2'] = "%" . $busqueda . "%";
            $bindings[':Busqueda3'] = "%" . $busqueda . "%";
            $bindings[':Busqueda4'] = "%" . $busqueda . "%";
        }

        return DB::select($sql, $bindings);
    }

    private function listadoTransac()
    {
        $bindings = [];
        $sql = "SELECT CASE pft.Completada
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
                pft.AccionRemotaToken,
                pft.Documento,
                dbo.Mask(pft.Documento, td.Mascara, 1, 1) AS DocumentoMasked,
                pft.IdTipoDocumento, 
                td.Descripcion TipoDocumento, 
                pft.NombreCompleto,
                pft.Sexo, 
                e.Nombre AS NombreEmpresaVisit, 
                pft.CorreoSolicitante,
                pft.IdCategoria, 
                c.Descripcion AS Categoria, 
                pft.Matricula, 
                pft.VigenciaDesde, 
                pft.VigenciaHasta,
                pft.EmpresaSolicitante,
                pft.NombreEmpresa,
                pft.NombreVisitante,
                pft.NombrePersonaVisit,
                pft.Autorizante
            FROM PersonasFisicasTransac pft
            INNER JOIN TiposDocumento td ON (pft.IdTipoDocumento = td.IdTipoDocumento)
            LEFT JOIN Categorias c ON (pft.IdCategoria = c.IdCategoria)
            LEFT JOIN Empresas e ON pft.DocEmpresaVisit = e.Documento AND pft.TipoDocEmpresaVisit = e.IdTipoDocumento
            WHERE pft.Transito = 1
            AND pft.Completada = 0";

        if (!$this->user->isGestion()) {
            $empresa = Empresa::loadBySession($this->req); // se lo envía desde el index, cuando soy un Solicitante por ejemplo.
            $sql .= " AND pft.DocEmpresaVisit = :doc_empresa AND pft.TipoDocEmpresaVisit = :tipo_doc_empresa";
            $bindings[':doc_empresa'] = $empresa->Documento;
            $bindings[':tipo_doc_empresa'] = $empresa->IdTipoDocumento;
        }

        if (null !== ($busqueda = $this->req->input('Busqueda'))) {
            $sql .= " AND (REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(pft.Documento, '_', ''), '-', ''), ';', ''), ',', ''), ':', ''), '.', '') COLLATE Latin1_general_CI_AI LIKE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(:Busqueda, '_', ''), '-', ''), ';', ''), ',', ''), ':', ''), '.', '') COLLATE Latin1_general_CI_AI OR "
                    . "pft.NombreCompleto COLLATE Latin1_general_CI_AI LIKE :Busqueda2 COLLATE Latin1_general_CI_AI OR "
                    . "CONVERT(varchar(18), pft.matricula) COLLATE Latin1_general_CI_AI LIKE :Busqueda3 COLLATE Latin1_general_CI_AI OR "
                    . "e.Nombre COLLATE Latin1_general_CI_AI LIKE :Busqueda4 COLLATE Latin1_general_CI_AI)";
            
            $bindings[':Busqueda'] = "%" . $busqueda . "%";
            $bindings[':Busqueda2'] = "%" . $busqueda . "%";
            $bindings[':Busqueda3'] = "%" . $busqueda . "%";
            $bindings[':Busqueda4'] = "%" . $busqueda . "%";
        }

        return DB::select($sql, $bindings);
    }

    public function show(int $idTipoDocumento, string $documento)
    {
        $entity = $this->show_interno($idTipoDocumento, $documento);
        if (!isset($entity)) {
            throw new NotFoundHttpException('Visitante no encontrado');
        }
        return $this->response($entity);
    }

    private function show_interno(int $idTipoDocumento, string $documento)
    {
        $entity = $this->showTransac($idTipoDocumento, $documento);
        if (!isset($entity)) {
            $entity = $this->showNoTransac($idTipoDocumento, $documento);
        }

        $entity = FsUtils::castProperties($entity, Visitante::$castProperties);

        return $entity;
    }

    private function showTransac(int $idTipoDocumento, string $documento): ?object
    {
        // if (null == ($tokenAccionRemota = $this->req->input('AccionRemotaToken'))) {
        //     throw new NotFoundHttpException("Campo AccionTokenRemota necesario");
        // }
        // $tokenAccionRemota = $this->req->input('AccionRemotaToken');

        $binding = [
            // ':accion_remota_token' => $tokenAccionRemota,
            ':documento' => $documento,
            ':id_tipo_documento' => $idTipoDocumento
        ];
        $sql = "SELECT TOP 1 
                        'pending' AS FsRC,
                        pft.AccionRemotaToken,
                        pft.AccionAprobador,
                        pft.Documento,
                        dbo.Mask(pft.Documento, td.Mascara, 1, 1) AS DocumentoMasked,
                        pft.IdTipoDocumento,
                        td.Descripcion AS TipoDocumentoNombre,
                        td.Mascara AS MascaraTipoDocumento,
                        pft.Archivo,
                        pft.Archivo2,
                        pft.Archivo3,
                        pft.PrimerNombre,
                        pft.SegundoNombre,
                        pft.PrimerApellido,
                        pft.SegundoApellido,
                        pft.NombreCompleto,
                        pft.IdCategoria,
                        c.Descripcion AS CategoriaNombre,
                        pft.Sexo,
                        pft.DocEmpresaVisit + '-' + RTRIM(LTRIM(STR(pft.TipoDocEmpresaVisit))) as IdEmpresaVisit,
                        pft.Autorizante,
                        e.Nombre AS NombreEmpresaVisit,
                        pft.VigenciaDesde, 
                        pft.VigenciaHasta,
                        CONVERT(varchar(10), pft.VigenciaDesde, 103) AS FechaVigenciaDesde,
                        CONVERT(varchar(5), pft.VigenciaDesde, 108) AS HoraVigenciaDesde,
                        CONVERT(varchar(10), pft.VigenciaHasta, 103) AS FechaVigenciaHasta,
                        CONVERT(varchar(5), pft.VigenciaHasta, 108) AS HoraVigenciaHasta,
                        pft.Matricula, 
                        pft.Estado,
                        pft.NotifEntrada,
                        pft.NotifSalida,
                        pft.EmailsEntrada,
                        pft.EmailsSalida,
                        pft.IdSector,
                        es.Nombre AS NombreSector,
                        pft.EmpresaSolicitante,
                        pft.CorreoSolicitante,
                        pft.NombreVisitante,
                        pft.NombreEmpresa,
                        pft.NombrePersonaVisit,                   
                        pft.MotivoVisita,
                        pft.VehiculoVisita, 
                        pft.NombreCompleto AS Detalle
                 FROM PersonasFisicasTransac pft 
                 INNER JOIN TiposDocumento td ON td.IdTipoDocumento = pft.IdTipoDocumento
                 LEFT JOIN Categorias c ON  c.IdCategoria = pft.IdCategoria
                 LEFT JOIN Empresas e ON e.Documento = pft.DocEmpresaVisit AND e.IdTipoDocumento = pft.TipoDocEmpresaVisit
                 LEFT JOIN EmpresasSectores es ON es.Documento = e.Documento AND es.IdTipoDocumento = e.IdTipoDocumento AND es.IdSector = pft.IdSector
                 WHERE pft.Documento = :documento
                 AND pft.IdTipoDocumento = :id_tipo_documento 
                 AND pft.Completada = 0 
                 ORDER BY AccionFechaHora DESC";

                // pft.AccionRemotaToken = :accion_remota_token
                // AND

        $entity = DB::selectOne($sql, $binding);

        if (isset($entity)) {
            $entity->Accesos = Acceso::loadByVisitante($documento, $idTipoDocumento, null); // $tokenAccionRemota
            // $entity->Documentos = TipoDocumentoVis::list($documento, $idTipoDocumento, 'Transac');

            $entityNoTransac = $this->showNoTransac($idTipoDocumento, $documento);
            if (isset($entityNoTransac)) {
                $entity->FsMV = $entityNoTransac;
            }
        }

        $entity = FsUtils::castProperties($entity, Visitante::$castProperties);
        return $entity;
    }
    
    public function showNoTransac(int $idTipoDocumento, string $documento): ?object
    {
        $binding = [
            ':documento' => $documento,
            ':id_tipo_documento' => $idTipoDocumento
        ];

        $sql = "SELECT  p.Documento,
                        dbo.Mask(pf.Documento, td.Mascara, 1, 1) AS DocumentoMasked,
                        p.IdTipoDocumento,
                        td.Descripcion AS TipoDocumentoNombre,
                        td.Mascara AS MascaraTipoDocumento,
                        pf.Archivo,
                        pf.Archivo2,
                        pf.Archivo3,
                        pf.PrimerNombre,
                        pf.SegundoNombre,
                        pf.PrimerApellido,
                        pf.SegundoApellido,
                        pf.NombreCompleto,
                        p.IdCategoria,
                        c.Descripcion AS Categoria,
                        c.Descripcion AS CategoriaNombre,
                        pf.Sexo,
                        pf.DocEmpresaVisit + '-' + RTRIM(LTRIM(STR(pf.TipoDocEmpresaVisit))) as IdEmpresaVisit,
                        pf.Autorizante,
                        e.Nombre AS Empresa,
                        e.Nombre AS NombreEmpresaVisit,
                        pf.NombrePersonaVisit,
                        pf.VigenciaDesde, 
                        pf.VigenciaHasta,
                        CONVERT(varchar(10), pf.VigenciaDesde, 103) AS FechaVigenciaDesde,
                        CONVERT(varchar(5), pf.VigenciaDesde, 108) AS HoraVigenciaDesde,
                        CONVERT(varchar(10), pf.VigenciaHasta, 103) AS FechaVigenciaHasta,
                        CONVERT(varchar(5), pf.VigenciaHasta, 108) AS HoraVigenciaHasta,
                        pf.Matricula, 
                        pf.Estado,
                        pf.NotifEntrada,
                        pf.NotifSalida,
                        pf.EmailsEntrada,
                        pf.EmailsSalida,
                        pf.IdSector,
                        es.Nombre AS NombreSector,
                        pf.EmpresaSolicitante,
                        pf.CorreoSolicitante,
                        pf.NombreVisitante,
                        pf.NombreEmpresa,
                        pf.NombrePersonaVisit,                   
                        pf.MotivoVisita,
                        pf.VehiculoVisita,
                        c.Descripcion AS Categoria,
                        c.CatLenel, 
                        pf.NombreCompleto AS Detalle
                 FROM Personas p
                 INNER JOIN PersonasFisicas pf ON p.Documento = pf.Documento AND p.IdTipoDocumento = pf.IdTipoDocumento
                 INNER JOIN TiposDocumento td ON td.IdTipoDocumento = p.IdTipoDocumento
                 INNER JOIN Categorias c ON c.IdCategoria = p.IdCategoria
                 LEFT JOIN Empresas e ON e.Documento = pf.DocEmpresaVisit AND e.IdTipoDocumento = pf.TipoDocEmpresaVisit
                 LEFT JOIN EmpresasSectores es ON es.Documento = e.Documento AND es.IdTipoDocumento = e.IdTipoDocumento AND es.IdSector = pf.IdSector
                 WHERE p.Documento = :documento 
                 AND p.IdTipoDocumento = :id_tipo_documento";

        $entity = DB::selectOne($sql, $binding);

        if (isset($entity)) {
            $entity->Accesos = Acceso::loadByPersonaFisica($documento, $idTipoDocumento);
            // $entity->Documentos = TipoDocumentoVis::list($documento, $idTipoDocumento);
            $entity->HistMatricula = $this->historicoMatricula($idTipoDocumento, $documento);
        }
        return $entity;
    }

    public function comboAutorizante(int $idCategoria)
    {
        if (!empty($idCategoria)) {
            $autorizantes = DB::select('SELECT Nombre, Categorias, Orden FROM VisitantesAutorizantes ORDER BY Orden ASC, Nombre ASC');
            $autorizantes = array_filter($autorizantes, function($autorizante) use ($idCategoria) {
                $categorias = array_map('trim', explode(',', $autorizante->Categorias));
                return in_array($idCategoria, $categorias);
            });

            return array_values($autorizantes);
        }

        return [];
    }

    public function create()
    {
        return DB::transaction(function () {

            $args = (object)$this->req->all();

            Visitante::comprobarArgs($args);
            Matricula::disponibilizar(isset($args->Matricula) ? $args->Matricula : null);
            $args->AccionRemotaToken = Str::random(32);
            $args->Documento = FsUtils::unmask($args->DocumentoMasked);

            try {
                $this->abmEntityTransac($args, 'A', true);
            } catch(Exception $ex) {
                throw new ConflictHttpException("Ocurrió un error al dar de alta la visita - " .  $ex->getMessage());
            }

            if ($this->user->isGestion()) {
                $args->NoMail = true;
                try {
                    $this->aprobar($args->IdTipoDocumento, $args->Documento);
                } catch(Exception $ex) {
                    $args->MotivoRechazo = $ex->getMessage();
                    // $this->rechazar($args->IdTipoDocumento, $args->Documento);
                    $this->rechazar_interno($args);
                    throw $ex;
                }
            } else {
                $entity = $this->showTransac($args->IdTipoDocumento, $args->Documento);

                if (!isset($entity)) {
                    throw new NotFoundHttpException("La visita que esta intentando crear no existe");
                }

                if (!empty($entity->CorreoSolicitante)) {
                    Mail::to($entity->CorreoSolicitante)->send(new AltaMailSolicitante($entity));
                }
            }

            LogAuditoria::log(
                Auth::id(),
                Visitante::class,
                LogAuditoria::FSA_METHOD_CREATE,
                $args,
                implode('-', [$args->Documento, $args->IdTipoDocumento]),
                sprintf('%s (%s-%s)', implode(' ', [@$args->PrimerNombre, @$args->SegundoNombre, @$args->PrimerApellido, @$args->SegundoApellido]), $args->Documento, $args->IdTipoDocumento)
            );
            return null;
        });
    }

    public function update(int $idTipoDocumento, string $documento, $args = null)
    {
        DB::transaction(function () use ($idTipoDocumento, $documento, $args) {
            if (!isset($args)) {
                $args = (object)$this->req->all();
            }
            Visitante::comprobarArgs($args);
            $transac = false;

            $entityTransac = $this->showTransac($idTipoDocumento, $documento);

            if (!isset($entityTransac)) {
                $this->aprobarUpdateEntity($args->IdTipoDocumento, $args->Documento, $args);

                if (!$transac && !empty($args->Estado)) {
                    $this->activar_interno_sql($args);
                } else {
                    $this->desactivar_interno_sql($args);
                }

                $onguard = !$transac && Categoria::sincConOnGuard($args->IdCategoria) && env('INTEGRADO', 'false') === true;
                $checkBaja = DB::selectOne('SELECT Baja FROM Personas WHERE Documento = ? AND IdTipoDocumento = ?', [$documento, $idTipoDocumento]);
                $respawning = !empty($checkBaja->Baja);

                if ($respawning) {
                    DB::update('UPDATE Personas SET Baja = 0 WHERE Documento = ? AND IdTipoDocumento = ?', [$documento, $idTipoDocumento]);
                }

                if ($onguard) {
                    $detalle = $this->showNoTransac($idTipoDocumento, $documento);
                    call_user_func_array([OnGuard::class, $respawning ? 'altaVisitante' : 'modificacionVisitante'], [
                        $documento,
                        implode(' ', [$detalle->PrimerNombre, $detalle->SegundoNombre]),
                        implode(' ', [$detalle->PrimerApellido, $detalle->SegundoApellido]),
                        $detalle->NombreEmpresa,
                        $detalle->Empresa,
                        $detalle->Matricula,
                        $detalle->Estado,
                        $detalle->CatLenel,
                        $detalle->VigenciaDesde,
                        $detalle->VigenciaHasta,
                    ]);
                }

                LogAuditoria::log(
                    Auth::id(),
                    Visitante::class,
                    $respawning
                        ? LogAuditoria::FSA_METHOD_CREATE
                        : LogAuditoria::FSA_METHOD_UPDATE,
                    $args,
                    [$documento, $idTipoDocumento],
                    sprintf('%s (%s-%s)', implode(' ', [@$args->PrimerNombre, @$args->SegundoNombre, @$args->PrimerApellido, @$args->SegundoApellido]), $documento, $idTipoDocumento)
                );
            } else if ($this->user->isGestion()) {
                return $this->aprobar($idTipoDocumento, $documento);
            } else {
                $this->abmEntityTransac($args, 'M');
                $transac = true;

                LogAuditoria::log(
                    Auth::id(),
                    Visitante::class,
                    LogAuditoria::FSA_METHOD_UPDATE,
                    $args,
                    [$documento, $idTipoDocumento],
                    sprintf('%s (%s-%s)', implode(' ', [@$args->PrimerNombre, @$args->SegundoNombre, @$args->PrimerApellido, @$args->SegundoApellido]), $documento, $idTipoDocumento)
                );
            }
        });
    }

    public function aprobar(int $idTipoDocumento, string $documento)
    {
        $entity = $this->showTransac($idTipoDocumento, $documento);

        if (!isset($entity)) {
            throw new NotFoundHttpException('La visita que esta intentando aprobar no existe');
        }

        $this->aprobar_interno($entity);
    }

    public function rechazar(int $idTipoDocumento, string $documento, ?string $motivoRechazo = null)
    {
        $entity = $this->showTransac($idTipoDocumento, $documento);

        if (!isset($entity)) {
            throw new NotFoundHttpException('La visita que esta intentando rechazar no existe');
        }

        if (isset($motivoRechazo)) {
            $entity->MotivoRechazo = $motivoRechazo;
        }

        $this->rechazar_interno($entity);
    }

    public function activar(int $idTipoDocumento, string $documento) {
        $entity = $this->showNoTransac($idTipoDocumento, $documento);
        $this->activar_interno($entity);
    }

    public function desactivar(int $idTipoDocumento, string $documento) {
        $entity = $this->showNoTransac($idTipoDocumento, $documento);
        $this->desactivar_interno($entity);
    }

    public function cambiarIdentificador(int $idTipoDocumento, string $documento)
    {
        $args = (object)$this->req->all();

        $entity = $this->showNoTransac($idTipoDocumento, $documento);

        if (!isset($entity)) {
            throw new NotFoundHttpException('El Visitante no existe');
        }

        $entityTransac = $this->showTransac($idTipoDocumento,$documento);
        if (isset($entityTransac)) {
            throw new ConflictHttpException("No se puede cambiar el identificador de un visitante que tiene modificaciones pendientes de aprobacion");
        }

        $entityTransac = $this->show_interno($args->NuevoIdTipoDocumento, $args->NuevoDocumento);
        if (isset($entityTransac)) {
            throw new ConflictHttpException("El identificador que acaba de ingresar ya se encuentra utilizado");
        }

        $tables = [
            ['Eventos', 'Documento|IdTipoDocumento'],
            ['Personas', 'Documento|IdTipoDocumento'],
            ['PersonasFisicas', 'Documento|IdTipoDocumento'],
            ['PersonasFisicasEmpresas', 'Documento|IdTipoDocumento'],
        ];

        $args = (object)[
            'Documento' => $documento,
            'IdTipoDocumento' => $idTipoDocumento,
            'NuevoIdTipoDocumento' => $args->NuevoIdTipoDocumento,
            'NuevoDocumento' => $args->NuevoDocumento
        ];

        DB::transaction(function () use ($tables, $args, $entity) {
            Visitante::cambiarIdentificador($tables, $args);

            $onGuard = Categoria::sincConOnGuard(0) && env('INTEGRADO', 'false') === true;

            if ($onGuard) {
                $detalle = $this->show_interno($args->NuevoIdTipoDocumento, $args->NuevoDocumento);
                OnGuard::bajaVisitante($args->Documento);
                OnGuard::altaVisitante(
                    $detalle->Documento,
                    strtoupper(implode(' ', [$detalle->PrimerNombre, $detalle->SegundoNombre])),
                    strtoupper(implode(' ', [$detalle->PrimerApellido, $detalle->SegundoApellido])),
                    $detalle->Categoria,
                    $detalle->Empresa ?? '',
                    $detalle->Matricula ?? '',
                    $detalle->Estado,
                    $detalle->CatLenel,
                    $detalle->VigenciaDesde,
                    $detalle->VigenciaHasta
                );
            }

            /**
             * @todo Ver lo de OnGuard
             */
            // $onGuard = Categoria::sincConOnGuard(0) && env('INTEGRADO', 'false') === true;

            // if ($onGuard) {
            //     $detalle = $this->showNoTransac($args->NuevoIdTipoDocumento, $args->NuevoDocumento);
            //     OnGuard::bajaVisitante($entity->Documento);
            //     OnGuard::altaVisitante(
            //         $detalle->Documento,
            //         implode(' ', [$detalle->PrimerNombre, $detalle->SegundoNombre]),
            //         implode(' ', [$detalle->PrimerNombre, $detalle->SegundoNombre]),
            //         $detalle->Categoria,
            //         // $detalle->EmpresaVisitante,
            //         $detalle->Empresa,
            //         $detalle->Matricula,
            //         $detalle->Estado ? OnGuard::ESTADO_ACTIVO : OnGuard::ESTADO_INACTIVO,
            //         $detalle->CatLenel,
            //         $detalle->VigenciaDesde,
            //         $detalle->VigenciaHasta,
            //     );
            // }

            LogAuditoria::log(
                Auth::id(),
                Visitante::class,
                'cambiar id',
                $args,
                implode('-', [$args->Documento, $args->IdTipoDocumento]),
                sprintf('%s (%s)', $entity->NombreCompleto, implode('-', [$args->Documento, $args->IdTipoDocumento]))
            );
        });
    }

    public function cambiarMatricula(int $idTipoDocumento, string $documento)
    {
        $matricula = $this->req->input('Matricula');
        if (empty($matricula)) {
            throw new HttpException(400, 'Debe indicar un número de matrícula');
        }

        return DB::transaction(function () use ($idTipoDocumento, $documento, $matricula) {
            $entityTransac = $this->showTransac($idTipoDocumento, $documento);
            
            if (isset($entityTransac)) {
                throw new ConflictHttpException('No se puede cambiar la matricula de un visitante que tiene modificaciones pendientes de aprobacion');
            }

            $entity = $this->showNoTransac($idTipoDocumento, $documento);

            Matricula::disponibilizar($matricula ? $matricula : null);

            DB::update(
                "UPDATE PersonasFisicas SET Matricula = ? WHERE Documento = ? AND IdTipoDocumento = ? ",
                [$matricula, $entity->Documento, $entity->IdTipoDocumento]
            );

            $onguard = Categoria::sincConOnGuard($entity->IdCategoria) && env('INTEGRADO', 'false') === true;
            if ($onguard) {
                OnGuard::modificarTarjetaEntidadLenel(
                    $entity->Documento,
                    $matricula,
                    $entity->Estado,
                    $entity->CatLenel,
                    $entity->VigenciaDesde,
                    $entity->VigenciaHasta,
                    OnGuard::ENTIDAD_VISITANTE
                );
            }

            Visitante::logCambioMatricula($entity, "Cambio de Matrícula"); //logCambioMatricula($entity, "Cambio de Matrícula");

            LogAuditoria::log(
                Auth::id(),
                Visitante::class,
                LogAuditoria::FSA_METHOD_UPDATE,
                'cambiar matrícula',
                implode('-', [$documento, $idTipoDocumento]),
                sprintf('%s (%s)', $entity->NombreCompleto, implode('-', [$documento, $idTipoDocumento]))
            );
        });
    }
    // IMPRIMIR MATRICULA
    public function imprimirMatriculaEnBase64(int $idTipoDocumento, string $documento)
    {
        $entity = $this->show_interno($idTipoDocumento, $documento);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Visitante no encontrado');
        }

        $impresionMatricula = new ImprimirMatricula();
        $imagen = $impresionMatricula->imprimir($entity);
        return $imagen;
    }

    public function busqueda()
    {
        $args = $this->req->all();

        $bindings = [];

        $sql = "SELECT DISTINCT 'func=AdmVisitantes|Documento=' + pf.Documento + '|IdTipoDocumento=' + LTRIM(RTRIM(STR(pf.IdTipoDocumento))) AS ObjUrl,
                        p.Documento,
                        td.Descripcion AS TipoDocumento,
                        c.Descripcion AS Categoria,
                        SexoDesc = CASE WHEN pf.Sexo = 1 THEN 'Masculino' ELSE 'Femenino' END,
                        pf.PrimerNombre,
                        pf.SegundoNombre,
                        pf.PrimerApellido,
                        pf.SegundoApellido,
                        pf.NombreCompleto,
                        pf.Matricula
            FROM Personas p 
            INNER JOIN PersonasFisicas pf ON p.Documento = pf.Documento AND p.IdTipoDocumento = pf.IdTipoDocumento AND pf.Transito = 1
            INNER JOIN TiposDocumento td ON p.IdTipoDocumento = td.IdTipoDocumento
            INNER JOIN Categorias c ON p.IdCategoria = c.IdCategoria
            LEFT JOIN Empresas pfe ON pfe.Documento = pf.DocEmpresa AND pfe.IdTipoDocumento = pf.TipoDocEmpresa";

        $bs = 'p.Baja = 0';
        $js = "";
        $ws = "";

        if (!empty($args)) {
            $i = 0;

            foreach ($args as $key => $value)
            {
                $i++;
                switch ($key)
                {
                    case 'output':
                    case 'token':
                    case 'page':
                    case 'pageSize':
                        break;
                    case 'Baja':
                        if ($value == 1) {
                            $bs = 'p.Baja IN (0, 1)';
                        }
                    break;
                    default:
                        switch ($key) {
                            case 'Documento':
                            case 'IdCategoria':
                            case 'IdPais':
                            case 'IdDepartamento':
                                $bindings[':IdDepartamento'] = $value;
                                $key = 'p.' . $key;
                                $ws .= (empty($ws) ? " WHERE " : " AND ") . $key . " = :IdDepartamento";
                            break;
                            case 'IdPaisNac':
                            case 'IdDepartamentoNac':
                                $bindings[':documento'] = $value;
                                $key = 'pf.' . $key;
                                $ws .= (empty($ws) ? " WHERE " : " AND ") . $key . " = :documento";
                            break;
                            default:
                                $bindings[':value'.$i] = "%" . $value . "%";
                                $ws .= (empty($ws) ? " WHERE " : " AND ") . $key . " LIKE :value";
                            break;
                        }
                    break;
                }
            }
        }

        $sql .= $js . $ws . (empty($ws) ? " WHERE " : " AND ") . $bs;

        if (!$this->user->isGestion()) {
            $empresa = Empresa::loadBySession($this->req);
            $bindings[':doc_empresa'] = $empresa->Documento;
            $bindings[':tipo_doc_empresa'] = $empresa->IdTipoDocumento;
            $sql .= " AND pfe.DocEmpresaVisit = :doc_empresa AND pfe.TipoDocEmpresaVisit = :tipo_doc_empresa AND pfe.FechaBaja IS NULL";
        }

        $sql .= " order by PrimerNombre, SegundoNombre, PrimerApellido, SegundoApellido";

        $page = (int)$this->req->input('page', 1);
        
        $items = DB::select($sql, $bindings);

        $output = isset($args['output']);

        if ($output !== 'json' && $output == true) {

            $output = $args['output'];

            $dataOutput = array_map(function($item) {
                return [
                    'Documento' => $item->Documento,
                    'TipoDocumento' => $item->TipoDocumento,
                    'Nombre' => $item->NombreCompleto,
                    'Categoria' => $item->Categoria,
                    'Empresa' => '', // Analizar y ver qué empresa se refiere, a la de visitante ?.. en la consulta no la filtra.
                    'Matricula' => $item->Matricula,
                    'Sexo' => $item->SexoDesc
                ];
            },$items);

            $filename = 'FSAcceso-Visitantes-Consulta-' . date('Ymd his');

            $headers = [
                'Documento' => 'Documento',
                'TipoDocumento' => 'Tipo de Documento',
                'Nombre' => 'Nombre',
                'Categoria' => 'Categoría',
                'Empresa' => 'Empresa',
                'Matricula' => 'Matrícula',
                'Sexo' => 'Sexo',
            ];
            
            return FsUtils::export($output, $dataOutput, $headers, $filename);
        }

        $paginate = FsUtils::paginateArray($items, $this->req);
        
        return $this->responsePaginate($paginate->items(), $paginate->total(), $page);
    }
    
    public function sincronizar(int $idTipoDocumento, string $documento)
    {
        $entity = $this->show_interno($idTipoDocumento, $documento);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Visitante no encontrado');
        }
        $this->update($idTipoDocumento, $documento, $entity);
    }

    public function wsaltapublica($args) {
        try {
            $this->chequearinduccion($args);
        }
        catch (HttpException $ex) {
            if (empty($args->HizoInduccion)) {
                throw new HttpException('Las respuestas son incorrectas');
            }
        }

        $args->AltaPublica = true;
        
        if (empty($args->Documentos)) {
            $args->Documentos = [];
        }

        $args->Documentos[] = (object)array(
            'IdTipoDocPF' => env('INDUCCION_IDTIPODOC'),
            'Nombre' => 'Inducción',
            'Vto' => Carbon::now()->addMonths(2),
            'Observacion' => 'Documento dado de alta a través del formulario público',
        );

        return $this->create($args);
    }

    public function chequearinduccion($args)
    {
        $bindings = [
            ':documento' => $args->Documento,
            ':idTipoDocumento' => $args->IdTipoDocumento,
            ':induccionTipoDoc' => env('INDUCCION_IDTIPODOC')
        ];

        $sql = "SELECT
                    p.Baja,
                    (SELECT 1
                    FROM PersonasFisicasDocs pfd 
                    WHERE pfd.Documento = pf.Documento 
                    AND pfd.Documento = pf.Documento 
                    AND pfd.IdTipoDocPF = :induccionTipoDoc
                    AND pfd.Vto >= CONVERT(date, GETDATE(), 103)) AS InduccionOk
                FROM Personas p
                LEFT JOIN PersonasFisicas pf ON p.Documento = pf.Documento AND p.IdTipoDocumento = pf.IdTipoDocumento
                LEFT JOIN Empresas e ON p.Documento = e.Documento AND p.IdTipoDocumento = e.IdTipoDocumento
                WHERE p.Documento = :documento AND p.IdTipoDocumento = :idTipoDocumento";

        $entity = DB::selectOne($sql, $bindings);

        if(isset($entity)) {
            $obj = $this->show($entity->IdTipoDocumento, $entity->Documento);

            if (!empty($obj->InduccionOk)) {
                return true;
            }
            else {
                throw new HttpException('La visita requiere inducción');
            }
        } else {
            throw new HttpException("Persona no encontrada");
        }
    }

    public function comprobarIdentificador(int $idTipoDocumento, string $documento)
    {
        return Visitante::comprobarIdentificador((object)[
            'Documento' => $documento,
            'IdTipoDocumento' => $idTipoDocumento,
        ]);
    }

    public function delete(int $idTipoDocumento, string $documento)
    {
        DB::transaction(function () use ($idTipoDocumento, $documento) {

            $entity = $this->showTransac($idTipoDocumento, $documento);
            if (isset($entity)) {
                $this->abmEntityTransac($entity, 'D', true);
            }

            DB::update(
                'UPDATE PersonasFisicas '
                    . 'SET FechaHoraBaja = GETDATE(), '
                    . 'IdUsuarioBaja = ? '
                    . 'WHERE Documento = ? AND IdTipoDocumento = ?',
                [Auth::id(), $documento, $idTipoDocumento]
            );

            DB::update(
                'UPDATE Personas '
                    . 'SET Baja = 1, '
                    . 'FechaHoraBaja = GETDATE(), '
                    . 'IdUsuarioBaja = ? '
                    . 'WHERE Documento = ? AND IdTipoDocumento = ?',
                [Auth::id(), $documento, $idTipoDocumento]
            );

            OnGuard::bajaVisitante($documento);

            /**
             * @todo AGREGAR Lenel
            */
            // Si lo de Lenel se ejecutó correctamente sigue por el else {wsdetallenotransac y OnGuard::altaVisitante}

            LogAuditoria::log(
                Auth::id(),
                Visitante::class,
                LogAuditoria::FSA_METHOD_DELETE,
                $entity,
                [$documento, $idTipoDocumento],
                sprintf('%s (%s-%s)', implode(' ', [@$entity->PrimerNombre, @$entity->SegundoNombre, @$entity->PrimerApellido, @$entity->SegundoApellido]), $documento, $idTipoDocumento)
            );
        });
    }

    private function abmEntityTransac(object $args, $action = '', $reset = true)
    {
        if ($action === 'M') {
            $result = DB::selectOne(
                'SELECT 1 FROM PersonasFisicasTransac WHERE Documento = ? AND IdTipoDocumento = ? AND Accion = \'A\' AND Completada = 0',
                [$args->Documento, $args->IdTipoDocumento]
            );
            if (isset($result)) {
                $action = 'A';
            }
        }

        if ($action === 'A' && !$this->user->isGestion()) {
            unset($args->Matricula);
        }

        if ($reset || $action === 'D') {
            $ac = $action != "D" ? " AND Accion = '" . $action . "' " : "";
            DB::delete('DELETE FROM PersonasFisicasTransac WHERE Documento = ? AND IdTipoDocumento = ? ' . $ac . ' AND Completada = 0', [$args->Documento, $args->IdTipoDocumento]);
            DB::delete('DELETE FROM PersonasFisicasTransacAccesos WHERE Documento = ? AND IdTipoDocumento = ?', [$args->Documento, $args->IdTipoDocumento]);
        }

        if (isset($args) && $action !== 'D') {
            $idEmpresaVisit = FsUtils::explodeId($args->IdEmpresaVisit);

            if (empty(trim($args->NombreEmpresa))) {
                $empresa = DB::selectOne('SELECT Nombre FROM Empresas WHERE Documento = ? AND IdTipoDocumento = ?',[$idEmpresaVisit[0], $idEmpresaVisit[1]]);
                $args->NombreEmpresa = $empresa->Nombre;
            }

            $entityTransac = new Visitante;
            $entityTransac->setTable($entityTransac->getTable() . 'Transac');
            $entityTransac->fill((array)$args);

            $entityTransac->Documento = $args->Documento;
            $entityTransac->IdTipoDocumento = $args->IdTipoDocumento;
            $entityTransac->IdCategoria = $args->IdCategoria;
            $entityTransac->Accion = $action;
            $entityTransac->AccionFechaHora = new Carbon;
            $entityTransac->AccionIdUsuario = $this->user->getKey();

            $entityTransac->Completada = 0;

            $entityTransac->NombreCompleto = implode(' ', [@$args->PrimerNombre, @$args->SegundoNombre, @$args->PrimerApellido, @$args->SegundoApellido]);
            $entityTransac->Transito = 1;
            $entityTransac->VigenciaDesde = isset($args->FechaVigenciaDesde) ? FsUtils::fromHumanDatetime($args->FechaVigenciaDesde  . ' ' . $args->HoraVigenciaDesde . ':00') : null;
            $entityTransac->VigenciaHasta = isset($args->FechaVigenciaDesde) ? FsUtils::fromHumanDatetime($args->FechaVigenciaHasta  . ' ' . $args->HoraVigenciaHasta . ':00') : null;

            $entityTransac->DocEmpresaVisit = $idEmpresaVisit[0];
            $entityTransac->TipoDocEmpresaVisit = $idEmpresaVisit[1];
            $entityTransac->IdSector = isset($args->IdSector) ? $args->IdSector : null;
            
            $entityTransac->save();

            if (!empty($args->Accesos)) {
                foreach ($args->Accesos as $acceso) {
                    DB::insert(
                        "INSERT INTO PersonasFisicasTransacAccesos (IdTipoDocumento, Documento, AccionFechaHora, IdAcceso) VALUES (?, ?, ?, ?)",
                        [$args->IdTipoDocumento, $args->Documento, new Carbon, $acceso]
                    );
                }
            }
            Visitante::altaDocumentos($args, $reset, 'Transac');
        }
    }

    private function historicoMatricula(int $idTipoDocumento, string $documento) {
        $binding = [
            ':doc_Tipo' => $documento . '-' . $idTipoDocumento
        ];

        $sql_logs = "SELECT CONVERT(varchar(20), FechaHora, 103) + ' ' + CONVERT(varchar(20), FechaHora, 108) AS FechaHora, Observacion
                 FROM LogActividades la
                WHERE la.EntidadId = :doc_Tipo
                ORDER BY la.FechaHora";

        $logsActividad = DB::select($sql_logs, $binding);

        if (isset($logsActividad)) {
            $matriculas = [];

            foreach ($logsActividad as $log) {
                $detalle = json_decode($log->Observacion);

                if (is_object($detalle)) {
                    if (empty($matriculas)) {
                        if (isset($detalle->Matricula)) {
                            $matriculas[] = (object)array(
                                'FechaDesde' => $log->FechaHora,
                                'FechaHasta' => '',
                                'Matricula' => $detalle->Matricula
                            );
                        }
                    } else if (isset($detalle->Matricula)) {
                        $i = count($matriculas) - 1;
                        if ($matriculas[$i]->Matricula != $detalle->Matricula) {
                            $matriculas[$i]->FechaHasta = $log->FechaHora;
                            $matriculas[] = (object)array(
                                'FechaDesde' => $log->FechaHora,
                                'FechaHasta' => '',
                                'Matricula' => $detalle->Matricula
                            );
                        }
                    }
                }
            }
            return $matriculas;
        }
    }

    private function aprobar_interno(object $args)
    {
        $return = DB::transaction(function () use ($args) {
            $entity = $this->showNoTransac($args->IdTipoDocumento, $args->Documento);

            if (!$entity) {
                // wsaprobaralta_sql - Equivale a este método
                $this->aprobarInsertEntity($args);
            } else {
                $this->aprobarUpdateEntity($args->IdTipoDocumento, $args->Documento, $args);
                // wsaprobarmodificacion_sql - Equivale a este método
            }

            Acceso::createByPersonaFisica($args, true);
            // Visitante::altaDocumentos($entity);   // ???

            // DB::update(
            //     'UPDATE PersonasFisicasTransac SET Completada = 1 WHERE AccionRemotaToken = ?',
            //     [$args->AccionRemotaToken]
            // );

            DB::update(
                'UPDATE PersonasFisicasTransac SET Completada = 1 WHERE IdTipoDocumento = ? AND Documento = ?',
                [$args->IdTipoDocumento, $args->Documento]
            );

            if (isset($args->Matricula)) {
                Matricula::disponibilizar($args->Matricula);
                DB::update(
                    'UPDATE PersonasFisicas SET Matricula = ? WHERE IdTipoDocumento = ? AND Documento = ?',
                    [$args->Matricula, $args->IdTipoDocumento, $args->Documento]
                );
            }

            if (!empty($args->Estado)) {
                $this->activar_interno_sql($args);
            } else if (empty($args->Estado)) {
                $this->desactivar_interno_sql($args);
            }

            $onguard = !$transac && Categoria::sincConOnGuard($args->IdCategoria) && env('INTEGRADO', 'false') === true;
            if ($onguard) {
                $detalle = $this->showNoTransac($args->IdTipoDocumento, $args->Documento);
                OnGuard::altaVisitante(
                    $detalle->Documento,
                    strtoupper(implode(' ', [$detalle->PrimerNombre, $detalle->SegundoNombre])),
                    strtoupper(implode(' ', [$detalle->PrimerApellido, $detalle->SegundoApellido])),
                    $detalle->NombreEmpresa ?? '',
                    $detalle->Empresa ?? '',
                    $detalle->Matricula ?? '',
                    $detalle->Estado,
                    $detalle->CatLenel,
                    $detalle->VigenciaDesde,
                    $detalle->VigenciaHasta
                );
            }

            // DB::update('UPDATE PersonasFisicasTransac SET Completada = 2 WHERE AccionRemotaToken = ?', [$args->AccionRemotaToken]);

            // onguard integration
            // $onguard = Categoria::sincConOnGuard($args->IdCategoria) && env('INTEGRADO', 'false') === true;

            /**
             * @todo OnGuard: Falta desarrollar bien la funcionalidad, considerar cls_visitante_mz.
             */
            // if ($onguard) {
            //     $detalle = $this->showNoTransac($args->IdTipoDocumento, $args->Documento);
            //     if (!isset($entity)) {
            //         OnGuard::altaVisitante(
            //             $detalle->Documento,
            //             implode(' ', [$detalle->PrimerNombre, $detalle->SegundoNombre]),
            //             implode(' ', [$detalle->PrimerApellido, $detalle->SegundoApellido]),
            //             // $detalle ->IdCategoria, // Código viejo aparece.
            //             $detalle->EmpresaVisitante, // este dato no aparece.
            //             $detalle->Empresa,
            //             $detalle->Matricula,
            //             $detalle->Estado,
            //             $detalle->CatLenel,
            //             $detalle->VigenciaDesde,
            //             $detalle->VigenciaHasta
            //         );
            //     } else {
            //         OnGuard::modificacionVisitante(
            //             $detalle->Documento,
            //             implode(' ', [$detalle->PrimerNombre, $detalle->SegundoNombre]),
            //             implode(' ', [$detalle->PrimerApellido, $detalle->SegundoApellido]),
            //             // $detalle ->IdCategoria, // Código viejo aparece.
            //             $detalle->EmpresaVisitante, // este dato no aparece.
            //             $detalle->Empresa,
            //             $detalle->Matricula,
            //             $detalle->Estado,
            //             $detalle->CatLenel,
            //             $detalle->VigenciaDesde,
            //             $detalle->VigenciaHasta
            //         );
            //     }
            // }
            // luego de lo de onguard
            // DB::update('UPDATE PersonasFisicasTransac SET Completada = 2 WHERE AccionRemotaToken = ?', [$args->AccionRemotaToken]);
            // if (/* lenel */) {
            //     // Si hay error lenel no hace nada, sino pregunto
            // } else {
            //     if (!isset($entity)) { 
            //         OnGuard::bajaVisitante($args->Documento);
            //     } else {
            //         OnGuard::modificacionVisitante(
            //             $entity->Documento,
            //             implode(' ', [$entity->PrimerNombre, $entity->SegundoNombre]),
            //             implode(' ', [$entity->PrimerApellido, $entity->SegundoApellido]),
            //             // $entity->IdCategoria, // Código viejo aparece.
            //             $detalle->EmpresaVisitante, // este dato no aparece.
            //             $entity->Empresa,
            //             $entity->Matricula,
            //             $entity->Estado,
            //             $entity->CatLenel,
            //             $entity->VigenciaDesde,
            //             $entity->VigenciaHasta
            //         );
            //     }
            // }

            // if (empty($args->NoMail)) {
            //     $entity = $this->showNoTransac($args->IdTipoDocumento, $args->Documento);

            //     Mail::to($this->entity->CorreoSolicitante)->send(new AprobarMailSolicitante($entity, $this->user));
                
            //     // ENVIO DE MAIL AUTORIZANTE, NO IMPLEMENTADO - Method: wsaltamailautorizante -
            // }

            LogAuditoria::log(
                Auth::id(),
                Visitante::class,
                LogAuditoria::FSA_METHOD_APPROVE,
                $args,
                [$args->Documento, $args->IdTipoDocumento],
                sprintf('%s (%s-%s)', implode(' ', [@$args->PrimerNombre, @$args->SegundoNombre, @$args->PrimerApellido, @$args->SegundoApellido]), $args->Documento, $args->IdTipoDocumento)
            );
            return true;
        });

        if ($return !== true) {
            throw new HttpException('Ocurrió un error al aprobar el visitante');
        }
    }

    private function rechazar_interno(object $args)
    {
        BaseModel::exigirArgs((array)$args, ['MotivoRechazo']);

        $return = DB::transaction(function () use($args) {
            $entity = $this->showTransac($args->IdTipoDocumento, $args->Documento);

            if (!$entity) {
                throw new NotFoundHttpException("El Visitante que esta intentando rechazar no existe");
            }

            $entity->MotivoRechazo = $args->MotivoRechazo;
            
            $return = DB::update(
                'UPDATE PersonasFisicasTransac SET Completada = 2 WHERE AccionRemotaToken = ?',
                [$args->AccionRemotaToken]
            );

            if ($return == true) {
                if ($entity->CorreoSolicitante) {
                    Mail::to($entity->CorreoSolicitante)->send(new RechazarMailSolicitante($entity, $this->user));
                } else {
                    throw new NotFoundHttpException("No se pudo obtener el correo del solicitante");
                }
                // ENVIO DE MAIL AL AUTORIZANTE, NO IMPLEMENTADO - Method: wsrechazarmailautorizante -
            }

            LogAuditoria::log(
                Auth::id(),
                Visitante::class,
                LogAuditoria::FSA_METHOD_REJECT,
                $args,
                [$args->Documento, $args->IdTipoDocumento],
                sprintf('%s (%s-%s)', implode(' ', [@$args->PrimerNombre, @$args->SegundoNombre, @$args->PrimerApellido, @$args->SegundoApellido]), $args->Documento, $args->IdTipoDocumento)
            );
            return true;
        });
        if ($return !== true) {
            throw new HttpException("Ocurrió un error al rechazar la visita");
        }
    }

    private function aprobarInsertEntity(object $args) {
        $empresa = FsUtils::explodeId($args->IdEmpresaVisit);

        /** 
         * @todo Qué es el OverwriteMatricula ....
         */
        if (!empty($args->Matricula) && !empty($args->OverwriteMatricula)) {
            DB::update('UPDATE PersonasFisicas SET Matricula = NULL WHERE Transito = 1 AND Matricula = ?', [$args->Matricula]);
        }

        $persona                    = new Persona((array)$args);
        $persona->Documento         = $args->Documento;
        $persona->IdTipoDocumento   = $args->IdTipoDocumento;
        $persona->IdCategoria       = $args->IdCategoria;
        $persona->FechaHoraAlta     = new Carbon;
        $persona->IdUsuarioAlta     = Auth::id();
        $persona->Baja              = false;

        $persona->save();

        $entity                         = new Visitante((array)$args);
        $entity->Documento              = $args->Documento;
        $entity->IdTipoDocumento        = $args->IdTipoDocumento;
        $entity->NombreCompleto         = implode(' ', [@$args->PrimerNombre, @$args->SegundoNombre, @$args->PrimerApellido, @$args->SegundoApellido]);
        $entity->DocEmpresa             = $empresa[0];
        $entity->TipoDocEmpresa         = $empresa[1];
        $entity->DocEmpresaVisit        = $empresa[0];
        $entity->TipoDocEmpresaVisit    = $empresa[1];
        $entity->IdSector               = empty($args->IdSector) ? null : $args->IdSector;
        $entity->VigenciaDesde          = isset($args->VigenciaDesde) ? FsUtils::strToDateByPattern(substr($args->VigenciaDesde, 0, 16))->format('Y-m-d H:i:s') : null;
        $entity->VigenciaHasta          = isset($args->VigenciaHasta) ? FsUtils::strToDateByPattern(substr($args->VigenciaHasta, 0, 16))->format('Y-m-d H:i:s') : null;
        $entity->Transito               = 1;

        if ($this->user->isGestion()) {
            $entity->Matricula          = @$args->Matricula;
            $entity->NotifEntrada       = @$args->NotifEntrada;
            $entity->NotifSalida        = @$args->NotifSalida;
            $entity->EmailsEntrada      = @$args->EmailsEntrada;
            $entity->EmailsSalida       = @$args->EmailsSalida;
        }
        
        $entity->save();
    }

    private function aprobarUpdateEntity(int $idTipoDocumento, string $documento, object $args) {
        $empresa = FsUtils::explodeId($args->IdEmpresaVisit);

        /** 
         * @todo Qué es el OverwriteMatricula ....
         */
        if (!empty($args->Matricula) && !empty($args->OverwriteMatricula)) {
            DB::update('UPDATE PersonasFisicas SET Matricula = NULL WHERE Transito = 1 AND Matricula = ?', [$args->Matricula]);
        }

        $persona = Persona::where('Documento', $documento)
            ->where('IdTipoDocumento', $idTipoDocumento)
            ->firstOrFail();

        $persona->fill((array)$args);
        $persona->Baja = false;
        $persona->save();

        $entity = Visitante::where('Documento', $documento)
            ->where('IdTipoDocumento', $idTipoDocumento)
            ->firstOrFail();

        $entity->fill((array)$args);
        $entity->NombreCompleto = implode(' ', [@$args->PrimerNombre, @$args->SegundoNombre, @$args->PrimerApellido, @$args->SegundoApellido]);
        $entity->DocEmpresa             = $empresa[0];
        $entity->TipoDocEmpresa         = $empresa[1];
        $entity->DocEmpresaVisit        = $empresa[0];
        $entity->TipoDocEmpresaVisit    = $empresa[1];
        $entity->IdSector               = empty($args->IdSector) ? null : $args->IdSector;
        $entity->VigenciaDesde          = isset($args->VigenciaDesde) ? FsUtils::strToDateByPattern(substr($args->VigenciaDesde, 0, 16))->format('Y-m-d H:i:s') : null;
        $entity->VigenciaHasta          = isset($args->VigenciaHasta) ? FsUtils::strToDateByPattern(substr($args->VigenciaHasta, 0, 16))->format('Y-m-d H:i:s') : null;
        $entity->Transito               = 1;

        if ($this->user->isGestion()) {
            $entity->Matricula          = @$args->Matricula;
            $entity->NotifEntrada       = @$args->NotifEntrada;
            $entity->NotifSalida        = @$args->NotifSalida;
            $entity->EmailsEntrada      = @$args->EmailsEntrada;
            $entity->EmailsSalida       = @$args->EmailsSalida;
        }

        $entity->save();

        // Visitante::altaDocumentos($args, true);
    }

    private function activar_interno(object $args) {
        BaseModel::exigirArgs(FsUtils::classToArray($args), ['Documento', 'IdTipoDocumento', 'Estado']);
        $args->Estado = 1;
        DB::transaction(function ($args) {
            $this->activar_interno_sql($args);

            $detalle = $this->showNoTransac($args->IdTipoDocumento, $args->Documento);
            /**
             * @todo Testear OnGuard... 
             */
            OnGuard::modificarTarjetaEntidadLenel($detalle->Documento, $detalle->Matricula, OnGuard::ESTADO_ACTIVO, $detalle->CatLenel, $detalle->VigenciaDesde, $detalle->VigenciaHasta, OnGuard::ENTIDAD_PERSONA);

            LogAuditoria::log(
                Auth::id(),
                Visitante::class,
                LogAuditoria::FSA_METHOD_ACTIVATE,
                $args,
                [$args->Documento, $args->IdTipoDocumento],
                sprintf('%s (%s-%s)', implode(' ', [@$args->PrimerNombre, @$args->SegundoNombre, @$args->PrimerApellido, @$args->SegundoApellido]), $args->Documento, $args->IdTipoDocumento)
            );
        });
    }

    private function desactivar_interno(object $args) {
        BaseModel::exigirArgs((array)$args, ['Documento','IdTipoDocumento','Estado']);
        $args->Estado = 0;
        DB::transaction(function ($args) {
            $this->desactivar_interno_sql($args);

            $detalle = $this->showNoTransac($args->IdTipoDocumento, $args->Documento);
            /**
             * @todo Testear OnGuard... 
             */
            OnGuard::modificarTarjetaEntidadLenel($detalle->Documento, $detalle->Matricula, OnGuard::ESTADO_INACTIVO, $detalle->CatLenel, $detalle->VigenciaDesde, $detalle->VigenciaHasta, OnGuard::ENTIDAD_PERSONA);

            LogAuditoria::log(
                Auth::id(),
                Visitante::class,
                LogAuditoria::FSA_METHOD_DESACTIVATE,
                $args,
                [$args->Documento, $args->IdTipoDocumento],
                sprintf('%s (%s-%s)', implode(' ', [@$args->PrimerNombre, @$args->SegundoNombre, @$args->PrimerApellido, @$args->SegundoApellido]), $args->Documento, $args->IdTipoDocumento)
            );
        });
    }

    private function activar_interno_sql(object $args) {
        BaseModel::exigirArgs((array)$args, ['Documento','IdTipoDocumento','Estado']);
        Visitante::esActivable($args);
        DB::update(
            'UPDATE PersonasFisicas SET Estado = ? WHERE Documento = ? AND IdTipoDocumento = ?',
            [$args->Estado, $args->Documento, $args->IdTipoDocumento]
        );
    }

    private function desactivar_interno_sql(object $args) {
        BaseModel::exigirArgs((array)$args, ['Documento','IdTipoDocumento','Estado']);
        DB::update(
            'UPDATE PersonasFisicas SET Estado = ? WHERE Documento = ? AND IdTipoDocumento = ?', 
            [$args->Estado, $args->Documento, $args->IdTipoDocumento]
        );
    }
}
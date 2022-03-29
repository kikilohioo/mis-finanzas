<?php

namespace App\Http\Controllers;

use App\FsUtils;
use App\ImprimirMatricula;

use App\Models\Acceso;
use App\Models\BaseModel;
use App\Models\Capacitacion;
use App\Models\Cargo;
use App\Models\Categoria;
use App\Models\Empresa;
use App\Models\Incidencia;
use App\Models\LogAuditoria;
use App\Models\Matricula;
use App\Models\Persona;
use App\Models\PersonaFisica;
use App\Models\Usuario;
use App\Models\Contrato;
use App\Models\DerechoAdmision;
use App\Models\TipoDocumentoPF;
use App\Models\DisenhoMatricula;

use App\Integrations\OnGuard;

use Carbon\Carbon;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Mockery\Undefined;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PersonaFisicaController extends Controller
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

    private static $availableFields = ['DocAntigeno', 'DocPCRIngresoPais', 'DocPCRSeptimoDia'];

    public function __construct(Request $req)
    {
        $this->req = $req;
        $this->user = auth()->user();
    }

    public function index()
    {
        $items = array_merge($this->listadoTransac(), $this->listadoNoTransac());

        usort($items, function ($a, $b) {
            return $a->NombreCompleto > $b->NombreCompleto;
        });

        $output = $this->req->input('output', 'json');

        if ($output !== 'json') {
           
            $dataOutput = array_map(function ($item) {
                return [
                'Documento' => $item->Documento,
                'Estado' => $item->Estado,
                'NombreCompleto' => $item->NombreCompleto ? $item->NombreCompleto : '',
                'Matricula' => $item->Matricula ? $item->Matricula : '',
                'Empresa' => $item->Empresa ? $item->Empresa : ''
                ];
            }, $items);

            $filename = 'FSAcceso-Personas' . date('Ymd his');

            $headers = [
                'Documento' => 'Documento',
                'Estado' => 'Estado',
                'NombreCompleto' => 'Nombre Completo',
                'Matricula' => 'Matrícula',
                'Empresa' => 'Empresa'
            ];

            return FsUtils::export($output, $dataOutput, $headers, $filename);
        }

        $page = (int)$this->req->input('page', 1);

        $paginate = FsUtils::paginateArray($items, $this->req);

        return $this->responsePaginate($paginate->items(), $paginate->total(), $page);
    }

    private function listadoTransac()
    {
        $binding = [];
        $sql = "SELECT 
                    CASE pft.Completada
                        WHEN 0 THEN CASE pft.Accion
                            WHEN 'A' THEN 'pending'
                            WHEN 'M' THEN 'mod-pending'
                        END
                        WHEN 2 THEN 'rejected'
                    END AS FsRC,
                    CASE pft.Completada
                        WHEN 0 THEN CASE pft.Accion
                            WHEN 'A' THEN '1'
                            WHEN 'M' THEN '2'
                        END
                        ELSE '4'
                    END AS SortLevel,
                    CASE pft.Estado
                        WHEN 1 THEN 'Activo'
                        ELSE CASE pft.Accion
                            WHEN 'A' THEN 'Pendiente'
                            WHEN 'M' THEN 'Inactivo'
                        END
                    END AS Estado,
                    pft.Documento,
                    dbo.Mask(pft.Documento, td.Mascara, 1, 1) AS DocumentoMasked,
                    pft.IdTipoDocumento,
                    pft.PrimerNombre,
                    pft.SegundoNombre,
                    pft.PrimerApellido,
                    pft.SegundoApellido,
                    pft.NombreCompleto,
                    pft.IdCategoria,
                    pft.IdPais,
                    pft.IdDepartamento,
                    pft.Ciudad,
                    pft.Localidad,
                    pft.Direccion,
                    pft.Email,
                    pft.FechaVtoDoc,
                    pft.Matricula,
                    pft.Sexo,
                    pft.FechaNac,
                    pft.Extranjero,
                    pft.IdPaisNac,
                    pft.IdDepartamentoNac,
                    td.Descripcion AS TipoDocumento,
                    c.Descripcion AS Categoria,
                    e.Nombre AS Empresa
                FROM PersonasFisicasTransac pft
                INNER JOIN TiposDocumento td ON (pft.IdTipoDocumento = td.IdTipoDocumento)
                INNER JOIN Categorias c ON (pft.IdCategoria = c.IdCategoria)
                LEFT JOIN PersonasFisicasTransacEmpresas pfte ON (pft.Documento = pfte.Documento 
                                                                    AND pft.IdTipoDocumento = pfte.IdTipoDocumento
                                                                    AND pfte.FechaAlta <= GETDATE()
                                                                    AND (pfte.FechaBaja IS NULL OR pfte.FechaBaja > GETDATE()))
                LEFT JOIN Empresas e ON (pfte.DocEmpresa = e.Documento AND pfte.TipoDocEmpresa = e.IdTipoDocumento)
                WHERE pft.Transito = 0
                AND pft.Completada = 0";

        if (!$this->user->isGestion()) {
            $empresa = Empresa::loadBySession($this->req);
            $sql .= " AND pfte.DocEmpresa = :doc_empresa AND pfte.TipoDocEmpresa = :tipo_doc_empresa";
            $binding[':doc_empresa'] = $empresa->Documento;
            $binding[':tipo_doc_empresa'] = $empresa->IdTipoDocumento;
        }

        if (null !== ($busqueda = $this->req->input('Busqueda'))) {
            $sql .= " AND (REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(pft.Documento, '_', ''), '-', ''), ';', ''), ',', ''), ':', ''), '.', '') COLLATE Latin1_general_CI_AI LIKE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(:busqueda_1, '_', ''), '-', ''), ';', ''), ',', ''), ':', ''), '.', '') COLLATE Latin1_general_CI_AI OR "
                . "pft.NombreCompleto COLLATE Latin1_general_CI_AI LIKE :busqueda_2 COLLATE Latin1_general_CI_AI OR "
                . "CONVERT(varchar(18), pft.matricula) COLLATE Latin1_general_CI_AI LIKE :busqueda_3 COLLATE Latin1_general_CI_AI OR "
                . "e.Nombre COLLATE Latin1_general_CI_AI LIKE :busqueda_4 COLLATE Latin1_general_CI_AI)";
            $binding[':busqueda_1'] = '%' . $busqueda . '%';
            $binding[':busqueda_2'] = '%' . $busqueda . '%';
            $binding[':busqueda_3'] = '%' . $busqueda . '%';
            $binding[':busqueda_4'] = '%' . $busqueda . '%';
        }

        $sql .= " ORDER BY NombreCompleto";

        return DB::select($sql, $binding);
    }
    
    public function indexNoTransac()
    {
        $items = $this->listadoNoTransac();
        $page = (int)$this->req->input('page', 1);
        $paginate = FsUtils::paginateArray($items, $this->req);
        return $this->responsePaginate($paginate->items(), $paginate->total(), $page);
    }

    private function listadoNoTransac()
    {
        $binding = [];
        $sql = "SELECT DISTINCT
                    CASE pf.Estado
                        WHEN 1 THEN 'active'
                        ELSE 'inactive'
                    END AS FsRC,
                    '3' AS SortLevel,
                    CASE pf.Estado
                        WHEN 1 THEN 'Activo'
                        ELSE 'Inactivo'
                    END AS Estado,
                    p.Documento,
                    dbo.Mask(p.Documento, td.Mascara, 1, 1) AS DocumentoMasked,
                    p.IdTipoDocumento,
                    pf.PrimerNombre,
                    pf.SegundoNombre,
                    pf.PrimerApellido,
                    pf.SegundoApellido,
                    pf.NombreCompleto,
                    p.IdCategoria,
                    p.IdPais,
                    p.IdDepartamento,
                    p.Ciudad,
                    p.Localidad,
                    p.Direccion,
                    p.Email,
                    pf.FechaVtoDoc,
                    pf.Matricula,
                    pf.Sexo,
                    pf.FechaNac,
                    pf.Extranjero,
                    pf.IdPaisNac,
                    pf.IdDepartamentoNac,
                    td.Descripcion AS TipoDocumento,
                    c.Descripcion AS Categoria,
                    e.Nombre AS Empresa
                FROM Personas p
                INNER JOIN PersonasFisicas pf ON (p.Documento = pf.Documento AND p.IdTipoDocumento = pf.IdTipoDocumento)
                INNER JOIN TiposDocumento td ON (p.IdTipoDocumento = td.IdTipoDocumento)
                INNER JOIN Categorias c ON (p.IdCategoria = c.IdCategoria)
                LEFT JOIN PersonasFisicasEmpresas pfe ON (pf.Documento = pfe.Documento AND pf.IdTipoDocumento = pfe.IdTipoDocumento
                                                            AND pfe.FechaAlta <= GETDATE()
                                                            AND (pfe.FechaBaja IS NULL OR pfe.FechaBaja > GETDATE()))
                LEFT JOIN Empresas e ON (pf.DocEmpresa = e.Documento AND pf.TipoDocEmpresa = e.IdTipoDocumento)
                WHERE pf.Transito = 0
                AND NOT EXISTS (SELECT pft.Documento, pft.IdTipoDocumento 
                                FROM PersonasFisicasTransac pft 
                                WHERE pft.Completada = 0
                                AND pft.Transito = 0
                                AND pft.Documento = pf.Documento
                                AND pft.IdTipoDocumento = pf.IdTipoDocumento)";

        if (!$this->user->isGestion() && !$this->req->input('Chofer')) {
            $empresa = Empresa::loadBySession($this->req);
            $sql .= " AND pf.DocEmpresa = :doc_empresa AND pf.TipoDocEmpresa = :tipo_doc_empresa";
            $binding[':doc_empresa'] = $empresa->Documento;
            $binding[':tipo_doc_empresa'] = $empresa->IdTipoDocumento;
        }

        if (null !== ($busqueda = $this->req->input('Busqueda'))) {
            $sql .= " AND (REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(pf.Documento, '_', ''), '-', ''), ';', ''), ',', ''), ':', ''), '.', '') COLLATE Latin1_general_CI_AI LIKE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(:busqueda_1, '_', ''), '-', ''), ';', ''), ',', ''), ':', ''), '.', '') COLLATE Latin1_general_CI_AI OR "
                . "pf.NombreCompleto COLLATE Latin1_general_CI_AI LIKE :busqueda_2 COLLATE Latin1_general_CI_AI OR "
                . "e.Nombre COLLATE Latin1_general_CI_AI LIKE :busqueda_3 COLLATE Latin1_general_CI_AI OR "
                . "CONVERT(varchar(18), pf.matricula) COLLATE Latin1_general_CI_AI LIKE :busqueda_4 COLLATE Latin1_general_CI_AI OR "
                . "e.Nombre COLLATE Latin1_general_CI_AI LIKE :busqueda_5 COLLATE Latin1_general_CI_AI)";
            $binding[':busqueda_1'] = '%' . $busqueda . '%';
            $binding[':busqueda_2'] = '%' . $busqueda . '%';
            $binding[':busqueda_3'] = '%' . $busqueda . '%';
            $binding[':busqueda_4'] = '%' . $busqueda . '%';
            $binding[':busqueda_5'] = '%' . $busqueda . '%';
        }

        if(null !== $this->req->input('Chofer')){
            
            $arrChofer = explode(',', FsUtils::getParams('Chofer'));
            $i = 0;
            $sql .= " AND p.IdCategoria IN(";
            foreach($arrChofer as $value){
                $i++;
                $sql .= ":paramChofer".$i;
                if(count($arrChofer) > $i){
                    $sql .= ",";
                }
                $binding[':paramChofer'.$i] = $value;
            }

            $sql .= ")";
        }

        if ($this->req->input('MostrarEliminados', 'false') === 'false') {
            $sql .= " AND p.Baja = 0 ";
        }

        $sql .= " ORDER BY NombreCompleto";
        
        return DB::select($sql, $binding);
    }

    public function show(int $idTipoDocumento, string $documento)
    {
        $entity = $this->show_interno($idTipoDocumento, $documento);
        if (!isset($entity)) {
            throw new NotFoundHttpException('Persona no encontrada');
        }
        return $this->response($entity);
    }

    private function show_interno(int $idTipoDocumento, string $documento)
    {
        $entity = $this->showTransac($idTipoDocumento, $documento);
        if (!isset($entity)) {
            $entity = $this->showNoTransac($idTipoDocumento, $documento);
        }

        return $entity;
    }

    private function showNoTransac(int $idTipoDocumento, string $documento): ?object
    {
        $binding = [
            ':documento' => $documento,
            ':id_tipo_documento' => $idTipoDocumento,
        ];
        $sql = "SELECT
                    p.*,
                    dbo.Mask(p.Documento, td.Mascara, 1, 1) AS DocumentoMasked,
                    pf.*,
                    FsRC = CASE pf.Estado WHEN 1 THEN 'active' ELSE 'inactive' END,
                    td.Mascara AS MascaraTipoDocumento,
                    STUFF(
                        (SELECT ', ' + cr.Descripcion
                         FROM PersonasFisicasCargos pfc
                         INNER JOIN Cargos cr ON cr.IdCargo = pfc.idCargo AND cr.Baja = 0
                         WHERE pfc.documento = pf.Documento AND pfc.idTipoDocumento = pf.IdTipoDocumento AND pfc.fechaHasta IS NULL
                         ORDER BY cr.Descripcion
                         FOR XML PATH (''))
                    , 1, 2, '') AS Cargo,
                    c.Descripcion AS Categoria,
                    c.CatLenel,
                    c.ContratistaDisponible AS CategoriaContratistaDisponible,
                    e.Nombre AS Empresa,
                    CONVERT(varchar(10), pf.VigenciaDesde, 103) AS FechaVigenciaDesde, 
                    CONVERT(varchar(5), pf.VigenciaDesde, 108) AS HoraVigenciaDesde,
                    CONVERT(varchar(10), pf.VigenciaHasta, 103) AS FechaVigenciaHasta, 
                    CONVERT(varchar(5), pf.VigenciaHasta, 108) AS HoraVigenciaHasta,
                    pfc.NroContrato, 
                    pf.NombreCompleto AS Detalle,
                    pf.Observaciones,
                    pf.EstadoObservacion,
                    '' AS UltimoEventoFechaHora,
                    '' AS UltimoEventoAcceso,
                    '' AS UltimoEventoEquipo
                FROM Personas p
                INNER JOIN PersonasFisicas pf ON p.Documento = pf.Documento AND p.IdTipoDocumento = pf.IdTipoDocumento
                INNER JOIN TiposDocumento td ON td.IdTipoDocumento = p.IdTipoDocumento
                INNER JOIN Categorias c ON p.IdCategoria = c.IdCategoria
                LEFT JOIN PersonasFisicasEmpresas pfe ON pf.Documento = pfe.Documento AND pf.IdTipoDocumento = pfe.IdTipoDocumento AND (pfe.FechaBaja IS NULL OR pfe.FechaBaja > GETDATE())
                LEFT JOIN Empresas e ON pfe.DocEmpresa = e.Documento AND pfe.TipoDocEmpresa = e.IdTipoDocumento
                LEFT JOIN PersonasFisicasContratos pfc ON pf.Documento = pfc.Documento AND pf.IdTipoDocumento = pfc.IdTipoDocumento AND e.Documento = pfc.DocEmpCont AND e.IdTipoDocumento = pfc.IdTipoDocCont
                WHERE p.Documento = :documento AND p.IdTipoDocumento = :id_tipo_documento";

        $entity = DB::selectOne($sql, $binding);

        if (isset($entity)) {
            #region Obtengo las dependencias de la persona 
            $entity->Cargos = Cargo::loadByPersonaFisica($documento, $idTipoDocumento);
            $entity->Documentos = TipoDocumentoPF::list($documento, $idTipoDocumento);
            $entity->Empresas = Empresa::loadByPersonaFisica($documento, $idTipoDocumento);
            
            foreach ($entity->Empresas as &$e) {
                $e->Contratos = Contrato::loadByPersonaFisica($documento, $idTipoDocumento, $e->DocEmpresa, $e->TipoDocEmpresa, $e->FechaAlta);
                if ($entity->DocEmpresa == $e->DocEmpresa && $entity->TipoDocEmpresa == $e->TipoDocEmpresa) {
                    $e->IdSector = $entity->IdSector;
                }
            }
            
            $entity->Incidencias = Incidencia::loadByPersonaFisica($documento, $idTipoDocumento);
            $entity->Capacitaciones = Capacitacion::loadByPersonaFisica($documento, $idTipoDocumento);
            $entity->Accesos = Acceso::loadByPersonaFisica($documento, $idTipoDocumento);
            #endregion

            #region Obtengo el último evento de la persona 
            $sqlUltimoEvento = "SELECT TOP 1 CONVERT(VARCHAR(10), e.FechaHora, 120) AS FechaRaw,
                CONVERT(VARCHAR(10), e.FechaHora, 103) AS Fecha,
                CONVERT(VARCHAR(10), e.FechaHora, 108) AS Hora,
                a.Descripcion AS Acceso,
                eq.Nombre AS Equipo
            FROM Eventos e
                INNER JOIN Accesos a ON e.IdAcceso = a.IdAcceso
                INNER JOIN Equipos eq ON e.IdEquipo = eq.IdEquipo
            WHERE e.Documento = :documento
                AND e.IdTipoDocumento = :id_tipo_documento
            ORDER BY e.FechaHora DESC";
            $ultimoEvento = DB::selectOne($sqlUltimoEvento, $binding);
            if (isset($ultimoEvento)) {
                $entity->UltimoEventoFechaHora = implode(' ', [$ultimoEvento->Fecha, $ultimoEvento->Hora]);
                $entity->UltimoEventoAcceso = $ultimoEvento->Acceso;
                $entity->UltimoEventoEquipo = $ultimoEvento->Equipo;
                $ultimoEventoDiff = (new \Datetime($ultimoEvento->FechaRaw))->diff(new \Datetime);
                $entity->UltimoEventoMarcarRojo = $ultimoEventoDiff->days > 12;
            } else {
                $entity->UltimoEventoMarcarRojo = false;
            }
            #endregion   
        }

        $entity = FsUtils::castProperties($entity, PersonaFisica::$castProperties);

        return $entity;
    }

    private function showTransac(int $idTipoDocumento, string $documento): ?object
    {
        $binding = [
            ':documento' => $documento,
            ':id_tipo_documento' => $idTipoDocumento,
        ];
        $sql = "SELECT 
                    pft.*,
                    dbo.Mask(pft.Documento, td.Mascara, 1, 1) AS DocumentoMasked,
                    CASE pft.Accion WHEN 'A' THEN 'pending' WHEN 'M' THEN 'mod-pending' END AS FsRC,
                    td.Mascara AS MascaraTipoDocumento, pft.NombreCompleto AS Detalle,
                    '' AS Observaciones,
                    '' AS EstadoObservacion,
                    '' AS UltimoEventoFechaHora,
                    '' AS UltimoEventoAcceso,
                    '' AS UltimoEventoEquipo
                FROM PersonasFisicasTransac pft
                INNER JOIN TiposDocumento td ON td.IdTipoDocumento = pft.IdTipoDocumento
                WHERE pft.Documento = :documento 
                AND pft.IdTipoDocumento = :id_tipo_documento
                AND pft.Completada = 0 
                ORDER BY AccionFechaHora DESC";

        $entity = DB::selectOne($sql, $binding);

        if (isset($entity)) {
            $entity->Cargos = Cargo::loadByPersonaFisicaTransac($documento, $idTipoDocumento);
            $entity->Documentos = TipoDocumentoPF::list($documento, $idTipoDocumento, 'Transac');
            $entity->Empresas = Empresa::loadByPersonaFisica($documento, $idTipoDocumento, 'Transac');
            foreach ($entity->Empresas as &$e) {
                $e->Contratos = Contrato::loadByPersonaFisica($documento, $idTipoDocumento, $e->DocEmpresa, $e->TipoDocEmpresa, $e->FechaAlta, 'Transac');
            }
            $entity->Capacitaciones = Capacitacion::loadByPersonaFisicaTransac($documento, $idTipoDocumento);

            $a = $this->showNoTransac($idTipoDocumento, $documento);
            if (isset($a)) {
                $entity->FsMV = $a;
            }
        }

        $entity = FsUtils::castProperties($entity, PersonaFisica::$castProperties);

        return $entity;
    }

    public function create()
    {
        return DB::transaction(function () {
            $args = (object)$this->req->all();
            if ($this->user->EsContratista) {
                PersonaFisica::exigirArgs($args, ['DocumentoMasked', 'IdTipoDocumento', 'IdCategoria', 'PrimerNombre', 'PrimerApellido', 'Sexo']);
            } else {
                PersonaFisica::exigirArgs($args, ['DocumentoMasked', 'IdTipoDocumento', 'FechaVtoDoc', 'IdCategoria', 'PrimerNombre', 'PrimerApellido', 'Sexo', 'IdPais', 'IdDepartamento', 'Ciudad']);
                
                if (!isset($args->IdPais)) {
                    throw new NotFoundHttpException("No ingreso el País. Campo obligatorio");
                }
                if (!isset($args->IdDepartamento)) {
                    throw new NotFoundHttpException("No ingreso el Departamento. Campo obligatorio");
                }
            }

            if (empty($args->Estado)) {
                $args->Estado = 0;
            }
            
            PersonaFisica::comprobarArgs($args);

            Matricula::disponibilizar(isset($args->Matricula) ? $args->Matricula : null);

            $args->Documento = FsUtils::unmask($this->req->input('DocumentoMasked'));
            $entity = $this->showNoTransac($args->IdTipoDocumento, $args->Documento);
            $transac = false;

            $args->PrimerNombre = strtoupper($args->PrimerNombre);
            $args->SegundoNombre = strtoupper($args->SegundoNombre);
            $args->PrimerApellido = strtoupper($args->PrimerApellido);
            $args->SegundoApellido = strtoupper($args->SegundoApellido);

            if (!isset($entity)) {
                if ($this->user->isGestion() || $this->user->EsContratista) {
                    $this->insertEntity($args);
                } else {
                    $this->abmEntityTransac($args, 'A');
                    $transac = true;
                }
            } else {
                if ($entity->Baja == 1) {
                    $this->update($args->IdTipoDocumento, $args->Documento);
                    DB::update("UPDATE Personas SET Baja = 0 WHERE Documento = ? AND IdTipoDocumento = ?", [$args->Documento, $args->IdTipoDocumento]);
                    LogAuditoria::log(
                        implode('-', [$args->Documento, $args->IdTipoDocumento]),
                        'personafisica',
                        'alta',
                        $args,
                        implode('-', $entity->Documento, $entity->IdTipoDocumento),
                        sprintf('%s (%s)', $entity->NombreCompleto, implode('-', $entity->Documento, $entity->IdTipoDocumento))
                    );
                    return null;
                } else {
                    throw new ConflictHttpException("La persona ya existe");
                }
            }

            if (!$transac && !empty($args->Estado)) {
                $this->activar_interno($args);
            } else if (empty($args->Estado)) {
                $this->desactivar_interno_sql($args);
            }

            $onguard = !$transac && Categoria::sincConOnGuard($args->IdCategoria) && env('INTEGRADO', 'false') === true;
            if ($onguard) {
                $detalle = $this->showNoTransac($args->IdTipoDocumento, $args->Documento);;
                OnGuard::altaPersona(
                    $detalle->Documento,
                    strtoupper(implode(' ', [$detalle->PrimerNombre, $detalle->SegundoNombre])),
                    strtoupper(implode(' ', [$detalle->PrimerApellido, $detalle->SegundoApellido])),
                    $detalle->Cargo ?? '',
                    $detalle->Categoria,
                    $detalle->Empresa ?? '',
                    $detalle->NroContrato ?? '',
                    @$detalle->Transporte,
                    $detalle->Ciudad ?? '',
                    $detalle->Direccion ?? '',
                    $detalle->Email ?? '',
                    $detalle->Matricula, // ?? ''
                    $detalle->Estado,
                    $detalle->CatLenel,
                    $detalle->VigenciaDesde,
                    $detalle->VigenciaHasta,
                    Categoria::gestionaMatriculaEnFSA($detalle->IdCategoria) ? OnGuard::CONTINGENCY_O : OnGuard::CONTINGENCY_U
                );
            }
            
            if (!$transac && !empty($args->Matricula)) {
                PersonaFisica::logCambioMatricula($args, "Alta");
            }

            return null;
        });
    }

    private function insertEntity(object $args)
    {   

        $persona                    = new Persona((array)$args);
        $persona->Documento         = $args->Documento;
        $persona->IdTipoDocumento   = $args->IdTipoDocumento;
        $persona->FechaHoraAlta     = new Carbon;
        $persona->Baja              = false;
        $persona->save();

        $entity                     = new PersonaFisica((array)$args);
        $entity->Documento          = $args->Documento;
        $entity->IdTipoDocumento    = $args->IdTipoDocumento;
        
        $entity->NombreCompleto     = implode(' ', [@$args->PrimerNombre, @$args->SegundoNombre, @$args->PrimerApellido, @$args->SegundoApellido]);
        $entity->DocRecibida        = isset($args->FechaDocRec) && !empty($args->FechaDocRec);
        $entity->TarjetaLista       = isset($args->FechaTarjLista) && !empty($args->FechaTarjLista);
        $entity->TarjetaEnt         = isset($args->FechaTarjEnt) && !empty($args->FechaTarjEnt);
        $entity->FechaVtoDoc        = isset($args->FechaVtoDoc) ? FsUtils::fromHumanDate($args->FechaVtoDoc) : null;
        $entity->VigenciaDesde      = isset($args->VigenciaDesde) ? FsUtils::fromHumanDate($args->VigenciaDesde) : null;
        $entity->VigenciaHasta      = isset($args->VigenciaHasta) ? FsUtils::fromHumanDate($args->VigenciaHasta) : null;
        $entity->FechaNac           = isset($args->FechaNac) ? FsUtils::fromHumanDate($args->FechaNac) : null;
        $entity->FechaDocRec        = isset($args->FechaDocRec) ? FsUtils::fromHumanDate($args->FechaDocRec) : null;
        $entity->FechaTarjLista     = isset($args->FechaTarjLista) ? FsUtils::fromHumanDate($args->FechaTarjLista) : null;
        $entity->FechaTarjEnt       = isset($args->FechaTarjEnt) ? FsUtils::fromHumanDate($args->FechaTarjEnt) : null;
        $entity->Transito           = 0;

        $entity->IdEstadoActividad  = isset($args->IdEstadoActividad) ? $args->IdEstadoActividad : null;
        $entity->FechaEstActividad  = isset($args->FechaEstActividad) ? FsUtils::fromHumanDate($args->FechaEstActividad) : null;
        $entity->NotifEntrada       = isset($args->NotifEntrada) ? $args->NotifEntrada : null;
        $entity->NotifSalida        = isset($args->NotifSalida) ? $args->NotifSalida : null;
        $entity->EmailsEntrada      = isset($args->EmailsEntrada) ? $args->EmailsEntrada : null;
        $entity->EmailsSalida       = isset($args->EmailsSalida) ? $args->EmailsSalida : null;
        $entity->Observaciones      = isset($args->Observaciones) ? $args->Observaciones : null;
        $entity->AdministraEquipos  = isset($args->AdministraEquipos) ? $args->AdministraEquipos : null;
        
        $entity->CelularContacto            = $args->CelularContacto;
        $entity->TransporteDesdeAeropuerto  = $args->TransporteDesdeAeropuerto;
        $entity->IdPaisOrigen               = $args->IdPaisOrigen;
        $entity->FechaVuelo                 = $args->FechaVuelo ? FsUtils::fromHumanDate($args->FechaVuelo) : null;
        $entity->FechaArribo                = $args->FechaArribo ? FsUtils::fromHumanDate($args->FechaArribo) : null;
        $entity->FechaRetorno               = $args->FechaRetorno ? FsUtils::fromHumanDate($args->FechaRetorno) : null;

        if (!$this->user->EsContratista) {
            $entity->AntigenoEnPlanta       = $args->AntigenoEnPlanta;
            $entity->ResultadoAntgEnPlanta  = $args->ResultadoAntgEnPlanta;
            $entity->FechaAntigenoEnPlanta  = $args->FechaAntigenoEnPlanta ? FsUtils::fromHumanDate($args->FechaAntigenoEnPlanta) : null;
        }

        $entity->FechaDosis1                    = $args->FechaDosis1 ? FsUtils::fromHumanDate($args->FechaDosis1) : null;
        $entity->FechaDosis2                    = $args->FechaDosis2 ? FsUtils::fromHumanDate($args->FechaDosis2) : null;
        $entity->FechaDosis3                    = $args->FechaDosis3 ? FsUtils::fromHumanDate($args->FechaDosis3) : null;
        $entity->FechaPositivo                  = $args->FechaPositivo ? FsUtils::fromHumanDate($args->FechaPositivo) : null;
        $entity->FechaPCRSeptimoDia             = $args->FechaPCRSeptimoDia ? FsUtils::fromHumanDate($args->FechaPCRSeptimoDia) : null;
        $entity->FechaHabilitadoAIngresarPlanta = $args->FechaHabilitadoAIngresarPlanta ? FsUtils::fromHumanDate($args->FechaHabilitadoAIngresarPlanta) : null;
        $entity->PCRIngresoPais                 = $args->PCRIngresoPais ? FsUtils::fromHumanDate($args->PCRIngresoPais) : null;  
        
        $entity->CuarentenaOblig        = $args->CuarentenaOblig;
        $entity->Vacunado               = $args->Vacunado;
        $entity->InmunizacionVigente    = $args->InmunizacionVigente;
        $entity->CicloCompleto          = $args->CicloCompleto;

        $entity->Dosis1                 = $args->Dosis1;
        $entity->LaboratorioDosis1      = $args->LaboratorioDosis1;
        $entity->Dosis2                 = $args->Dosis2;
        $entity->LaboratorioDosis2      = $args->LaboratorioDosis2;
        $entity->Dosis3                 = $args->Dosis3;
        $entity->LaboratorioDosis3      = $args->LaboratorioDosis3;
        $entity->FuePositivo            = $args->FuePositivo;
        $entity->PermUYMenorA7Dias      = $args->PermUYMenorA7Dias;
        $entity->PCRSeptimoDia          = $args->PCRSeptimoDia;
        $entity->ResultadoPCRSeptimoDia = $args->ResultadoPCRSeptimoDia;
        $entity->IdAlojamiento          = $args->IdAlojamiento;
        $entity->IdTransportista        = $args->IdTransportista;
        $entity->SeguroSalud            = $args->SeguroSalud;
        $entity->AlojamientoNroUnidad   = $args->AlojamientoNroUnidad;
        $entity->Credencial             = $args->Credencial;

        if ($this->user->EsContratista) {
            $empresa = Empresa::loadBySession($this->req);
            $entity->DocEmpresa = $empresa->Documento;  
            $entity->TipoDocEmpresa = $empresa->IdTipoDocumento;  
        }
            
        $entity->save();
        
        if ($this->user->EsContratista) {
            $args->Empresas = [
                [
                    'IdEmpresa' => $empresa->Documento.'-'.$empresa->IdTipoDocumento,
                    'FechaAlta' => FsUtils::strToDate()->format('d/m/Y'),
                    'FechaBaja' => '01/01/2050',
                    'Observaciones' => null,
                    'Contratos' => []

                ]
            ];
            Empresa::createByPersonaFisica($args);
        } else {
            Cargo::createByPersonaFisica($args);
            Empresa::createByPersonaFisica($args);
            PersonaFisica::altaDocumentos($args);
            Capacitacion::createByPersonaFisica($args);
            Incidencia::createByPersonaFisica($args);
            Acceso::createByPersonaFisica($args);
        }
    }

    public function update(int $idTipoDocumento, string $documento, $Args = null)
    {
        DB::transaction(function () use ($idTipoDocumento, $documento, $Args) {
            if (!isset($Args)) {
                $Args = (object)$this->req->all();
            }

            if ($this->user->EsContratista) {
                PersonaFisica::exigirArgs($Args, ['DocumentoMasked', 'IdTipoDocumento', 'IdCategoria', 'PrimerNombre', 'PrimerApellido', 'Sexo']);
            } else {
                PersonaFisica::exigirArgs($Args, ['DocumentoMasked', 'IdTipoDocumento', 'FechaVtoDoc', 'IdCategoria', 'PrimerNombre', 'PrimerApellido', 'Sexo', 'IdPais', 'IdDepartamento', 'Ciudad']);
            }

            PersonaFisica::comprobarArgs($Args);

            $empresa = Empresa::loadBySession($this->req);
            $transac = false;

            $Args->PrimerNombre = strtoupper($Args->PrimerNombre);
            $Args->SegundoNombre = strtoupper($Args->SegundoNombre);
            $Args->PrimerApellido = strtoupper($Args->PrimerApellido);
            $Args->SegundoApellido = strtoupper($Args->SegundoApellido);

            if ($this->user->isGestion()) {
                $entityTransac = $this->showTransac($idTipoDocumento, $documento);
                if (isset($entityTransac)) {
                    return $this->aprobar($idTipoDocumento, $documento);
                }
                $this->updateEntity($idTipoDocumento, $documento, $Args);
            } else if ($this->user->EsContratista) {
                $this->updateEntity($idTipoDocumento, $documento, $Args);

                LogAuditoria::log(
                    Auth::id(),
                    PersonaFisica::class,
                    LogAuditoria::FSA_METHOD_UPDATE,
                    $Args,
                    [$documento, $idTipoDocumento],
                    sprintf('%s (%s-%s)', implode(' ', [@$Args->PrimerNombre, @$Args->SegundoNombre, @$Args->PrimerApellido, @$Args->SegundoApellido]), $documento, $idTipoDocumento)
                );

                $checkBaja = DB::selectOne('SELECT Baja FROM Personas WHERE Documento = ? AND IdTipoDocumento = ?', [$documento, $idTipoDocumento]);
                $respawning = !empty($checkBaja->Baja);

                if ($respawning) {
                    DB::update('UPDATE Personas SET Baja = 0 WHERE Documento = ? AND IdTipoDocumento = ?', [$documento, $idTipoDocumento]);
                }

                return;
            }else {
                $this->abmEntityTransac($Args, 'M');
                $transac = true;
            }

            if (!$transac && !empty($Args->Estado)) {
                try {
                    DerechoAdmision::comprobarDocumentoEnLista($documento);
                }
                catch (ConflictHttpException $err) {
                    $message = 'El sistema no permite el registro de esta persona. '
                        . 'Por favor, comuníquese con el administrador del sistema.';

                    if (!empty($this->user->isAdmin())) {
                        $message = 'Una persona con el mismo documento se encuentra dentro de la lista de derecho de admisión. '
                            . 'Para activar a esta persona, primero debe eliminarla de la lista de derecho de admisión.';
                    }

                    throw new ConflictHttpException($message);
                }
                $this->activar_interno($Args);
            } else if (empty($Args->Estado)) {
                $this->desactivar_interno_sql($Args);
            }

            $onguard = !$transac && Categoria::sincConOnGuard($Args->IdCategoria) && env('INTEGRADO', 'false') === true;
            $checkBaja = DB::selectOne('SELECT Baja FROM Personas WHERE Documento = ? AND IdTipoDocumento = ?', [$documento, $idTipoDocumento]);
            $respawning = !empty($checkBaja->Baja);

            if ($respawning) {
                DB::update('UPDATE Personas SET Baja = 0 WHERE Documento = ? AND IdTipoDocumento = ?', [$documento, $idTipoDocumento]);
                if (isset($Args->Matricula) && !empty($Args->Matricula)) {
                    DB::update('UPDATE PersonasFisicas SET Matricula = ? WHERE Documento = ? AND IdTipoDocumento = ?', [$Args->Matricula, $documento, $idTipoDocumento]);
                }
            }

            if ($onguard) {
                $detalle = $this->showNoTransac($idTipoDocumento, $documento);
                call_user_func_array([OnGuard::class, $respawning ? 'altaPersona' : 'modificacionPersona'], [
                    $documento,
                    strtoupper(implode(' ', [$detalle->PrimerNombre, $detalle->SegundoNombre])),
                    strtoupper(implode(' ', [$detalle->PrimerApellido, $detalle->SegundoApellido])),
                    $detalle->Cargo ?? '',
                    $detalle->Categoria,
                    $detalle->Empresa,
                    $detalle->NroContrato ?? '',
                    @$detalle->Transporte,
                    $detalle->Ciudad,
                    $detalle->Direccion,
                    $detalle->Email,
                    $detalle->Matricula,
                    $detalle->Estado,
                    $detalle->CatLenel,
                    $detalle->VigenciaDesde,
                    $detalle->VigenciaHasta,
                ]);
            }

            if ($respawning && isset($Args->Matricula) && !empty($Args->Matricula)) {
                PersonaFisica::logCambioMatricula($Args);
            }

            LogAuditoria::log(
                Auth::id(),
                PersonaFisica::class,
                $respawning
                    ? LogAuditoria::FSA_METHOD_CREATE
                    : LogAuditoria::FSA_METHOD_UPDATE,
                $Args,
                [$documento, $idTipoDocumento],
                sprintf('%s (%s-%s)', implode(' ', [@$Args->PrimerNombre, @$Args->SegundoNombre, @$Args->PrimerApellido, @$Args->SegundoApellido]), $documento, $idTipoDocumento)
            );
        });
    }

    private function updateEntity(int $idTipoDocumento, string $documento, object $args)
    {
        $args->Documento = $documento;
        $args->IdTipoDocumento = $idTipoDocumento;

        $persona = Persona::where('Documento', $documento)
            ->where('IdTipoDocumento', $idTipoDocumento)
            ->firstOrFail();

        $persona->fill((array)$args);
        $persona->IdDepartamento = $args->IdDepartamento;
        $persona->save();

        $entity = PersonaFisica::where('Documento', $documento)
            ->where('IdTipoDocumento', $idTipoDocumento)
            ->firstOrFail();

        $entity->fill((array)$args);
        $entity->NombreCompleto         = strtoupper(implode(' ', [@$args->PrimerNombre, @$args->SegundoNombre, @$args->PrimerApellido, @$args->SegundoApellido]));
        $entity->DocRecibida            = isset($args->FechaDocRec) && !empty($args->FechaDocRec);
        $entity->TarjetaLista           = isset($args->FechaTarjLista) && !empty($args->FechaTarjLista);
        $entity->TarjetaEnt             = isset($args->FechaTarjEnt) && !empty($args->FechaTarjEnt);
        $entity->FechaVtoDoc            = isset($args->FechaVtoDoc) ? FsUtils::fromHumanDate($args->FechaVtoDoc) : null;
        $entity->VigenciaDesde          = isset($args->VigenciaDesde) ? FsUtils::fromHumanDate($args->VigenciaDesde) : null;
        $entity->VigenciaHasta          = isset($args->VigenciaHasta) ? FsUtils::fromHumanDate($args->VigenciaHasta) : null;
        $entity->FechaNac               = isset($args->FechaNac) ? FsUtils::fromHumanDate($args->FechaNac) : null;
        $entity->FechaDocRec            = isset($args->FechaDocRec) ? FsUtils::fromHumanDate($args->FechaDocRec) : null;
        $entity->FechaTarjLista         = isset($args->FechaTarjLista) ? FsUtils::fromHumanDate($args->FechaTarjLista) : null;
        $entity->FechaTarjEnt           = isset($args->FechaTarjEnt) ? FsUtils::fromHumanDate($args->FechaTarjEnt) : null;
        $entity->Transito               = 0;
        
        $entity->EsPGP               = $args->EsPGP;

        if ($this->user->isGestion()) {
            $entity->IdEstadoActividad  = @$args->IdEstadoActividad;
            $entity->FechaEstActividad  = @$args->FechaEstActividad;
            $entity->NotifEntrada       = @$args->NotifEntrada;
            $entity->NotifSalida        = @$args->NotifSalida;
            $entity->EmailsEntrada      = @$args->EmailsEntrada;
            $entity->EmailsSalida       = @$args->EmailsSalida;
            $entity->Observaciones      = @$args->Observaciones;
            $entity->AdministraEquipos  = @$args->AdministraEquipos;

            $entity->AntigenoEnPlanta       = $args->AntigenoEnPlanta;
            $entity->ResultadoAntgEnPlanta  = $args->ResultadoAntgEnPlanta;
            $entity->FechaAntigenoEnPlanta  = isset($args->FechaAntigenoEnPlanta) ? FsUtils::fromHumanDate($args->FechaAntigenoEnPlanta) : null;
        }

        $entity->FechaDosis1                    = $args->FechaDosis1 ? FsUtils::fromHumanDate($args->FechaDosis1) : null;
        $entity->FechaDosis2                    = $args->FechaDosis2 ? FsUtils::fromHumanDate($args->FechaDosis2) : null;;
        $entity->FechaDosis3                    = $args->FechaDosis3 ? FsUtils::fromHumanDate($args->FechaDosis3) : null;
        $entity->FechaPositivo                  = $args->FechaPositivo ? FsUtils::fromHumanDate($args->FechaPositivo) : null;
        $entity->FechaPCRSeptimoDia             = $args->FechaPCRSeptimoDia ? FsUtils::fromHumanDate($args->FechaPCRSeptimoDia) : null;
        $entity->FechaHabilitadoAIngresarPlanta = $args->FechaHabilitadoAIngresarPlanta ? FsUtils::fromHumanDate($args->FechaHabilitadoAIngresarPlanta) : null;
        $entity->PCRIngresoPais                 = $args->PCRIngresoPais ? FsUtils::fromHumanDate($args->PCRIngresoPais) : null;
        
        $entity->CuarentenaOblig        = $args->CuarentenaOblig;
        $entity->Vacunado               = $args->Vacunado;
        $entity->InmunizacionVigente    = $args->InmunizacionVigente;
        $entity->CicloCompleto          = $args->CicloCompleto;

        $entity->IdPaisNac              = $args->IdPaisNac;
        $entity->Dosis1                 = $args->Dosis1;
        $entity->LaboratorioDosis1      = $args->LaboratorioDosis1;
        $entity->Dosis2                 = $args->Dosis2;
        $entity->LaboratorioDosis2      = $args->LaboratorioDosis2;
        $entity->Dosis3                 = $args->Dosis3;
        $entity->LaboratorioDosis3      = $args->LaboratorioDosis3;
        $entity->FuePositivo            = $args->FuePositivo;
        $entity->PermUYMenorA7Dias      = $args->PermUYMenorA7Dias;
        $entity->PCRSeptimoDia          = $args->PCRSeptimoDia;
        $entity->ResultadoPCRSeptimoDia = $args->ResultadoPCRSeptimoDia;
        $entity->Credencial             = $args->Credencial;

        $entity->CelularContacto            = $args->CelularContacto;
        $entity->TransporteDesdeAeropuerto  = $args->TransporteDesdeAeropuerto;
        $entity->IdPaisOrigen               = $args->IdPaisOrigen;
        $entity->FechaVuelo                 = $args->FechaVuelo ? FsUtils::fromHumanDate($args->FechaVuelo) : null;
        $entity->FechaArribo                = $args->FechaArribo ? FsUtils::fromHumanDate($args->FechaArribo) : null;
        $entity->FechaRetorno               = $args->FechaRetorno ? FsUtils::fromHumanDate($args->FechaRetorno) : null;

        $entity->IdAlojamiento          = $args->IdAlojamiento;
        $entity->IdTransportista        = $args->IdTransportista;
        $entity->SeguroSalud            = $args->SeguroSalud;
        $entity->AlojamientoNroUnidad   = $args->AlojamientoNroUnidad;

        $entity->save();
        
        if (!$this->user->EsContratista) {

            Cargo::createByPersonaFisica($args, true);
            Empresa::createByPersonaFisica($args, true);
            PersonaFisica::altaDocumentos($args, true);
            Capacitacion::createByPersonaFisica($args, true);
            Incidencia::createByPersonaFisica($args, true);
            Acceso::createByPersonaFisica($args, true);
        }
    }

    public function createDoc(int $idTipoDocumento, string $documento) {

		$retornoUpdate = false;
        
        $Args = $this->req->All();
        $pathName = storage_path('app/personasFisicas/docsVacunas/'.$Args['filename']);
        if (file_exists($pathName)) unlink($pathName);

        if (!in_array($Args['atr'], self::$availableFields)) {
            throw new BadRequestHttpException($Args['atr'] . ' no es un campo válido para subir archivos.');
        }

        $file = $this->req->file('importarAdjunto');
        $filename = $Args['atr'] . '-' . $idTipoDocumento . '-' . $documento . '.' . $file->getClientOriginalExtension();

        $file->storeAs('personasFisicas/docsVacunas', $filename);

        $retornoUpdate = DB::update("update PersonasFisicas set " .  $Args['atr'] . " = :filenamee where IdTipoDocumento= :idTipoDocumento and Documento = :documento", 
                        [":filenamee" => $filename, ":idTipoDocumento" => $idTipoDocumento, ":documento" => $documento]);

        if ($retornoUpdate) {

            LogAuditoria::log(
                Auth::id(),
                PersonaFisica::class,
                LogAuditoria::FSA_METHOD_CREATE,
                $Args,
                [$documento, $idTipoDocumento],
                sprintf('%s (%s-%s)', @$this->user->Nombre, $documento, $idTipoDocumento)
            );

            return $filename;
        } else {
            throw new HttpException(409, 'Error al guardar el archivo');
        }
    }

    public function deleteDoc(int $idTipoDocumento, string $documento) {

        $Args = $this->req->All();

        $retornoUpdate = DB::update("update PersonasFisicas set " .  $Args['atr'] . " = null where IdTipoDocumento= :idTipoDocumento and Documento = :documento", 
                        [":idTipoDocumento" => $idTipoDocumento, ":documento" => $documento]);

        if ($retornoUpdate) {

            $pathName = storage_path('app/personasFisicas/docsVacunas/'.$Args['filename']);

            if (file_exists($pathName)) unlink($pathName);

            LogAuditoria::log(
                Auth::id(),
                PersonaFisica::class,
                LogAuditoria::FSA_METHOD_DELETE,
                $Args,
                [$documento, $idTipoDocumento],
                sprintf('%s (%s-%s)', @$this->user->Nombre, $documento, $idTipoDocumento)
            );


            return true;
        } else {
            throw new HttpException(409, 'Error al eliminar el archivo');
        }
    }

    public function verDoc($fileName){

        $adjunto = storage_path('app/personasFisicas/docsVacunas/'.$fileName);

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

        /// En teoría, no deberían venir estos campos en la solicitud
        unset($args->Payroll, $args->Observaciones, $args->AdministraEquipos);

        if ($action === 'A' && !$this->user->isGestion()) {
            unset($args->Matricula);
        }

        if ($reset || $action === 'D') {
            $ac = $action != "D" ? " AND Accion = '" . $action . "' " : "";
            DB::delete('DELETE FROM PersonasFisicasTransac WHERE Documento = ? AND IdTipoDocumento = ? ' . $ac . ' AND Completada = 0', [$args->Documento, $args->IdTipoDocumento]);
            DB::delete('DELETE FROM PersonasFisicasTransacCapacitaciones WHERE Documento = ? AND IdTipoDocumento = ?', [$args->Documento, $args->IdTipoDocumento]);
            DB::delete('DELETE FROM PersonasFisicasTransacCargos WHERE Documento = ? AND IdTipoDocumento = ?', [$args->Documento, $args->IdTipoDocumento]);
            DB::delete('DELETE FROM PersonasFisicasTransacContratos WHERE Documento = ? AND IdTipoDocumento = ?', [$args->Documento, $args->IdTipoDocumento]);
            DB::delete('DELETE FROM PersonasFisicasTransacDocsItems WHERE Documento = ? AND IdTipoDocumento = ?', [$args->Documento, $args->IdTipoDocumento]);
            DB::delete('DELETE FROM PersonasFisicasTransacDocs WHERE Documento = ? AND IdTipoDocumento = ?', [$args->Documento, $args->IdTipoDocumento]);
            DB::delete('DELETE FROM PersonasFisicasTransacEmpresas WHERE Documento = ? AND IdTipoDocumento = ?', [$args->Documento, $args->IdTipoDocumento]);
        }

        if ($action !== 'D') {
            $entityTransac = new PersonaFisica;
            $entityTransac->setTable($entityTransac->getTable() . 'Transac');
            $entityTransac->Documento = $args->Documento;
            $entityTransac->IdTipoDocumento = $args->IdTipoDocumento;
            $entityTransac->IdCategoria = $args->IdCategoria;
            $entityTransac->fill((array)$args);
            $entityTransac->Accion = $action;
            $entityTransac->AccionFechaHora = new Carbon;
            $entityTransac->AccionIdUsuario = $this->user->getKey();
            $entityTransac->Completada = 0;
            $entityTransac->Matricula = null;
            $entityTransac->NombreCompleto = implode(' ', [@$args->PrimerNombre, @$args->SegundoNombre, @$args->PrimerApellido, @$args->SegundoApellido]);
            $entityTransac->FechaVtoDoc = isset($args->FechaVtoDoc) ? FsUtils::fromHumanDate($args->FechaVtoDoc) : null;
            $entityTransac->DocRecibida = !empty($args->FechaDocRec);
            $entityTransac->TarjetaLista = !empty($args->FechaTarjLista);
            $entityTransac->TarjetaEnt = !empty($args->TarjetaEnt);
            $entityTransac->save();
            

            Cargo::createByPersonaFisica($args, $reset, 'Transac');
            Empresa::createByPersonaFisica($args, $reset, 'Transac');
            PersonaFisica::altaDocumentos($args, $reset, 'Transac');
            Capacitacion::createByPersonaFisica($args, $reset, 'Transac');
        }
    }

    public function activar(int $idTipoDocumento, string $documento)
    {
        try {
            DerechoAdmision::comprobarDocumentoEnLista($documento);
        } catch (ConflictHttpException $err) {
            $message = 'El sistema no permite el registro de esta persona.<br />'
                . 'Por favor, comuníquese con el administrador del sistema.';

            if (!empty($this->user->isAdmin())) {
                $message = 'Una persona con el mismo documento se encuentra dentro de la lista de derecho de admisión.<br />'
                    . 'Para activar a esta persona, primero debe eliminarla de la lista de derecho de admisión.';
            }

            throw new ConflictHttpException($message);
        }
        $entity = $this->showNoTransac($idTipoDocumento, $documento);
        $this->activar_interno($entity);
    }

    private function activar_interno(object $args)
    {
        BaseModel::exigirArgs(FsUtils::classToArray($args), ['Documento', 'IdTipoDocumento', 'Estado']);
        $args->Estado = 1;
        PersonaFisica::esActivable($args);

        DB::transaction(function () use ($args) {
            DB::update(
                'UPDATE PersonasFisicas SET Estado = ?, EstadoObservacion = NULL WHERE Documento = ? AND IdTipoDocumento = ?',
                [$args->Estado, $args->Documento, $args->IdTipoDocumento]
            );

            $detalle = $this->showNoTransac($args->IdTipoDocumento, $args->Documento);
            OnGuard::modificarTarjetaEntidadLenel($detalle->Documento, $detalle->Matricula, OnGuard::ESTADO_ACTIVO, $detalle->CatLenel, $detalle->VigenciaDesde, $detalle->VigenciaHasta, OnGuard::ENTIDAD_PERSONA);

            LogAuditoria::log(
                Auth::id(),
                PersonaFisica::class,
                LogAuditoria::FSA_METHOD_ACTIVATE,
                $args,
                [$args->Documento, $args->IdTipoDocumento],
                sprintf('%s (%s-%s)', implode(' ', [@$args->PrimerNombre, @$args->SegundoNombre, @$args->PrimerApellido, @$args->SegundoApellido]), $args->Documento, $args->IdTipoDocumento)
            );
        });
    }

    public function desactivar(int $idTipoDocumento, string $documento)
    {
        $entity = $this->showNoTransac($idTipoDocumento, $documento);
        $this->desactivar_interno($entity);
    }

    private function desactivar_interno(object $args)
    {
        BaseModel::exigirArgs((array)$args, ['Documento', 'IdTipoDocumento', 'Estado']);
        $args->Estado = 0;
        DB::transaction(function () use ($args) {
            $this->desactivar_interno_sql($args);

            $detalle = $this->showNoTransac($args->IdTipoDocumento, $args->Documento);
            OnGuard::modificarTarjetaEntidadLenel($detalle->Documento, $detalle->Matricula, OnGuard::ESTADO_INACTIVO, $detalle->CatLenel, $detalle->VigenciaDesde, $detalle->VigenciaHasta, OnGuard::ENTIDAD_PERSONA);

            LogAuditoria::log(
                Auth::id(),
                PersonaFisica::class,
                LogAuditoria::FSA_METHOD_DESACTIVATE,
                $args,
                [$args->Documento, $args->IdTipoDocumento],
                sprintf('%s (%s-%s)', implode(' ', [@$args->PrimerNombre, @$args->SegundoNombre, @$args->PrimerApellido, @$args->SegundoApellido]), $args->Documento, $args->IdTipoDocumento)
            );
        });
    }

    private function desactivar_interno_sql(object $args)
    {
        BaseModel::exigirArgs((array)$args, ['Documento', 'IdTipoDocumento', 'Estado']);
        DB::update(
            'UPDATE PersonasFisicas SET Estado = ?, EstadoObservacion = NULL WHERE Documento = ? AND IdTipoDocumento = ?',
            [$args->Estado, $args->Documento, $args->IdTipoDocumento]
        );
    }

    public function aprobar(int $idTipoDocumento, string $documento)
    {
        $entity = $this->showTransac($idTipoDocumento, $documento);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Persona no encontrada');
        }

        $this->aprobar_interno($entity);
    }

    private function aprobar_interno(object $args)
    {
        $return = DB::transaction(function () use ($args) {
            $existeNoTransac = $this->showNoTransac($args->IdTipoDocumento, $args->Documento);

            if (!$existeNoTransac) {
                $this->insertEntity($args);
                DB::update(
                    'UPDATE PersonasFisicasTransac SET Completada = 1 WHERE Documento = ? AND IdTipoDocumento = ? AND Accion = \'A\' AND Completada = 0',
                    [$args->Documento, $args->IdTipoDocumento]
                );
            } else {
                $this->updateEntity($args->IdTipoDocumento, $args->Documento, $args);
                DB::update(
                    'UPDATE PersonasFisicasTransac SET Completada = 1 WHERE Documento = ? AND IdTipoDocumento = ? AND Accion = \'M\' AND Completada = 0',
                    [$args->Documento, $args->IdTipoDocumento]
                );
            }

            LogAuditoria::log(
                Auth::id(),
                PersonaFisica::class,
                LogAuditoria::FSA_METHOD_APPROVE,
                $args,
                [$args->Documento, $args->IdTipoDocumento],
                sprintf('%s (%s-%s)', implode(' ', [@$args->PrimerNombre, @$args->SegundoNombre, @$args->PrimerApellido, @$args->SegundoApellido]), $args->Documento, $args->IdTipoDocumento)
            );

            return true;
        });

        if ($return !== true) {
            throw new HttpException('Ocurrió un error al aprobar la persona');
        }
    }

    public function rechazar(int $idTipoDocumento, string $documento)
    {
        $entity = $this->show_interno($idTipoDocumento, $documento);
        $this->rechazar_interno($entity);
    }

    private function rechazar_interno(object $args)
    {
        $return = DB::transaction(function () use ($args) {
            $existeNoTransac = $this->showTransac($args->IdTipoDocumento, $args->Documento);

            if (!$existeNoTransac) {
                throw new NotFoundHttpException('La persona que esta intentando rechazar no existe');
            }
            
            DB::update(
                'UPDATE PersonasFisicasTransac SET Completada = 2 WHERE Documento = ? AND IdTipoDocumento = ? AND Completada = 0',
                [$args->Documento, $args->IdTipoDocumento]
            );

            LogAuditoria::log(
                Auth::id(),
                PersonaFisica::class,
                LogAuditoria::FSA_METHOD_REJECT,
                $args,
                [$args->Documento, $args->IdTipoDocumento],
                sprintf('%s (%s-%s)', implode(' ', [@$args->PrimerNombre, @$args->SegundoNombre, @$args->PrimerApellido, @$args->SegundoApellido]), $args->Documento, $args->IdTipoDocumento)
            );

            return true;
        });

        if ($return !== true) {
            throw new HttpException('Ocurrió un error al rechazar la persona');
        }
    }

    public function cambiarIdentificador(int $idTipoDocumento, string $documento)
    {
        $args = (object)$this->req->all();

        $entity = $this->showNoTransac($idTipoDocumento, $documento);
        
        if (!isset($entity)) {
            throw new NotFoundHttpException('La persona no existe');
        }

        $entityTransac = $this->showTransac($idTipoDocumento, $documento);
        if (isset($entityTransac)) {
            throw new ConflictHttpException('No se puede cambiar el identificador de una persona que tiene modificaciones pendientes de aprobación');
        }

        $entityTransac = $this->show_interno($args->NuevoIdTipoDocumento, $args->NuevoDocumento);

        if (isset($entityTransac)) {
            throw new ConflictHttpException('El nuevo documento se encuentra utilizado por otra persona');
        }

        $tables = [
                ['Eventos', 'Documento|IdTipoDocumento'],
                // ['EventosDuplicados', 'Documento|IdTipoDocumento'],
                ['HISTPersonas', 'Documento|IdTipoDocumento'],
                ['HISTPersonasFisicas', 'Documento|IdTipoDocumento'],
                ['HISTPersonasFisicasActivos', 'Documento|IdTipoDocumento'],
                ['HISTPersonasFisicasDocs', 'Documento|IdTipoDocumento'],
                ['HISTPersonasFisicasDocsItems', 'Documento|IdTipoDocumento'],
                ['HISTPersonasFisicasEmpresas', 'Documento|IdTipoDocumento'],
                ['LogProceso', 'DocEmpresa|TipoDocEmpresa'],
                ['Personas', 'Documento|IdTipoDocumento'],
                ['PersonasFisicas', 'Documento|IdTipoDocumento'],
                ['PersonasFisicasAccesos', 'Documento|IdTipoDocumento'],
                ['PersonasFisicasCapacitaciones', 'Documento|IdTipoDocumento'],
                ['PersonasFisicasCargos', 'Documento|IdTipoDocumento'],
                ['PersonasFisicasContratos', 'Documento|IdTipoDocumento'],
                ['PersonasFisicasContratosAltas', 'Documento|IdTipoDocumento'],
                ['PersonasFisicasContratosBajas', 'Documento|IdTipoDocumento'],
                ['PersonasFisicasDocs', 'Documento|IdTipoDocumento'],
                ['PersonasFisicasDocsItems', 'Documento|IdTipoDocumento'],
                ['PersonasFisicasEmpresas', 'Documento|IdTipoDocumento'],
                ['PersonasFisicasEstadosActividad', 'Documento|IdTipoDocumento'],
                ['PersonasFisicasHuellas', 'Documento|IdTipoDocumento'],
                ['PersonasFisicasListaNegra', 'Documento|IdTipoDocumento'],
                ['PersonasFisicasMatriculas', 'Documento|IdTipoDocumento'],
                ['PersonasFisicasNiveles', 'Documento|IdTipoDocumento'],
                ['PersonasFisicasNivelesESP', 'Documento|IdTipoDocumento'],
                ['PersonasFisicasNivelesRestricciones', 'Documento|IdTipoDocumento'],
                ['PersonasFisicasNivelesRestriccionesESP', 'Documento|IdTipoDocumento'],
                ['PersonasFisicasRutas', 'Documento|IdTipoDocumento'],
                ['PersonasFisicasTransac', 'Documento|IdTipoDocumento'],
                ['PersonasFisicasTransacAccesos', 'Documento|IdTipoDocumento'],
                ['PersonasFisicasTransacCapacitaciones', 'Documento|IdTipoDocumento'],
                ['PersonasFisicasTransacCargos', 'Documento|IdTipoDocumento'],
                ['PersonasFisicasTransacContratos', 'Documento|IdTipoDocumento'],
                ['PersonasFisicasTransacDocs', 'Documento|IdTipoDocumento'],
                ['PersonasFisicasTransacDocsItems', 'Documento|IdTipoDocumento'],
                ['PersonasFisicasTransacEmpresas', 'Documento|IdTipoDocumento'],
                ['TMPPresHoras', 'Documento|IdTipoDocumento'],
                ['TMPPresPersonas', 'Documento|IdTipoDocumento'],
                ['UsuariosPersonasFisicas', 'Documento|IdTipoDocumento'],
        ];

        $args = (object)[
            'Documento' => $documento,
            'IdTipoDocumento' => $idTipoDocumento,
            'NuevoDocumento' => $args->NuevoDocumento,
            'NuevoIdTipoDocumento' => $args->NuevoIdTipoDocumento,
        ];

        DB::transaction(function () use ($tables, $args, $entity) {
            PersonaFisica::cambiarIdentificador($tables, $args);

            $onGuard = Categoria::sincConOnGuard(0) && env('INTEGRADO', 'false') === true;

            if ($onGuard) {
                $detalle = $this->show_interno($args->NuevoIdTipoDocumento, $args->NuevoDocumento);
                OnGuard::bajaPersona($args->Documento);
                OnGuard::altaPersona(
                    $detalle->Documento,
                    strtoupper(implode('', [$detalle->PrimerNombre, $detalle->SegundoNombre])),
                    strtoupper(implode('', [$detalle->PrimerNombre, $detalle->SegundoNombre])), 
                    $detalle->Cargo ?? '', 
                    $detalle->Categoria, 
                    $detalle->Empresa, 
                    $detalle->NroContrato ?? '', 
                    @$detalle->Transporte ?? '', 
                    $detalle->Ciudad ?? '', 
                    $detalle->Direccion ?? '', 
                    $detalle->Email ?? '', 
                    $detalle->Matricula, 
                    $detalle->Estado ? OnGuard::ESTADO_ACTIVO : OnGuard::ESTADO_INACTIVO, 
                    $detalle->CatLenel, 
                    $detalle->VigenciaDesde, 
                    $detalle->VigenciaHasta, 
                    Categoria::gestionaMatriculaEnFSA($detalle->IdCategoria) ? OnGuard::CONTINGENCY_O : OnGuard::CONTINGENCY_U
                );
            }
            
            LogAuditoria::log(
                Auth::id(),
                'personafisica',
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
        
        DB::transaction(function () use ($idTipoDocumento, $documento) {
            $matricula = $this->req->input('Matricula');
            $entityTransac = $this->showTransac($idTipoDocumento, $documento);
            
            if (isset($entityTransac)) {
                throw new ConflictHttpException('No se puede cambiar la matricula de una persona que tiene modificaciones pendientes de aprobacion');
            }

            $entity = $this->showNoTransac($idTipoDocumento, $documento);

            Matricula::disponibilizar($matricula);

            DB::update(
                "UPDATE PersonasFisicas SET Matricula = ? WHERE Documento = ? AND IdTipoDocumento = ?",
                [$matricula, $entity->Documento, $entity->IdTipoDocumento]
            );

            $onguard = Categoria::sincConOnGuard($entity->IdCategoria) && env('INTEGRADO', 'false') === true;
            if ($onguard) {
                OnGuard::cambiarTarjetaEntidadLenel(
                    $entity->Documento,
                    $matricula,
                    $entity->Estado,
                    $entity->CatLenel,
                    $entity->VigenciaDesde,
                    $entity->VigenciaHasta,
                    OnGuard::ENTIDAD_PERSONA
                );
            }

            PersonaFisica::logCambioMatricula($entity, "Cambio de Matrícula");

            LogAuditoria::log(
                Auth::id(),
                PersonaFisica::class,
                LogAuditoria::FSA_METHOD_UPDATE,
                'cambiar matrícula',
                [$documento, $idTipoDocumento],
                sprintf('%s (%s)', $entity->NombreCompleto, implode('-', [$documento, $idTipoDocumento]))
            );
        });
    }

    // IMPRIMIR MATRICULA
    public function imprimirMatriculaEnBase64(int $idTipoDocumento, string $documento)
    {    
        $entity = $this->show_interno($idTipoDocumento, $documento);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Persona no encontrada');
        }

        $impresionMatricula = new ImprimirMatricula();
        $imagen = $impresionMatricula->imprimir($entity);
        return $imagen;
    }

    public function comprobarIdentificador(int $idTipoDocumento, string $documento)
    {
        return PersonaFisica::comprobarIdentificador((object)[
            'Documento' => $documento,
            'IdTipoDocumento' => $idTipoDocumento,
        ]);
    }

    public function sincronizar(int $idTipoDocumento, string $documento)
    {
        $entity = $this->show_interno($idTipoDocumento, $documento);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Persona no encontrada');
        }

        $this->update($idTipoDocumento, $documento, $entity);
    }

    public function delete(int $idTipoDocumento, string $documento)
    {
        DB::transaction(function () use ($idTipoDocumento, $documento) {
            $entity = $this->show_interno($idTipoDocumento, $documento);

            if (!isset($entity)) {
                throw new NotFoundHttpException('Persona no encontrada');
            }

            $this->abmEntityTransac($entity, 'D', true);

            DB::update(
                'UPDATE PersonasFisicas '
                    . 'SET Estado = 0, '
                    . 'Matricula = NULL, '
                    . 'FechaHoraBaja = GETDATE(), '
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

            OnGuard::bajaPersona($documento);

            LogAuditoria::log(
                Auth::id(),
                PersonaFisica::class,
                LogAuditoria::FSA_METHOD_DELETE,
                $entity,
                [$documento, $idTipoDocumento],
                sprintf('%s (%s-%s)', $entity->NombreCompleto, $documento, $idTipoDocumento)
            );
        });
    }
    
    public function graficos()
    {
        $Args = $this->req->all();
            
        return array(
            "nacionalesExtranjeros" => $this->chartnacionalesextranjeros($Args),
            "porPaisDepartamento" => $this->chartporpaisdepartamento($Args),
            "lugarNacimiento" => $this->chartlugarnacimiento($Args),
            "habilitados" => $this->charthabilitados($Args),
           "porCategoria" => $this->chartporcategoria($Args),
            "porSexo" => $this->chartporsexo($Args),
            "porEdad" => $this->chartporedad($Args),
        );

    }

    private function chartnacionalesextranjeros($Args) {
        $sql = "";
        
        $binding = Array();
        //$UsuarioEsGestion = $this->user->isGestion();
        
        $ArgsIdEmpresaObj = Array();
        if(!empty($Args['IdEmpresa'])){
            $ArgsIdEmpresaObj = FsUtils::explodeId($Args['IdEmpresa']);
        }

        $sqlWhere = '';
        $sqlWhere1 = '';
        
        if (!empty($Args['NroContrato'])) {
            $sqlWhere .= " AND EXISTS (SELECT *
                        FROM PersonasFisicasContratos PFC
                        WHERE PF.documento = PFC.documento
                        AND PF.idTipoDocumento = PFC.idTipoDocumento
                        AND PFC.nroContrato = :NroContrato)";

            $sqlWhere1 .= " AND EXISTS (SELECT *
                        FROM PersonasFisicasContratos PFC
                        WHERE PF.documento = PFC.documento
                        AND PF.idTipoDocumento = PFC.idTipoDocumento
                        AND PFC.nroContrato = :NroContrato1)";

            $binding[':NroContrato'] = $Args['NroContrato'];
            $binding[':NroContrato1'] = $Args['NroContrato'];
        }

        if (!empty($Args['IdAcceso'])) {
            $sqlWhere .= " AND EXISTS (SELECT *
                        FROM PersonasFisicasAccesos PFA
                        WHERE PF.documento = PFA.documento
                        AND PF.idTipoDocumento = PFA.idTipoDocumento
                        AND PFA.idAcceso = :IdAcceso)";

            $sqlWhere .= " AND EXISTS (SELECT *
                        FROM PersonasFisicasAccesos PFA
                        WHERE PF.documento = PFA.documento
                        AND PF.idTipoDocumento = PFA.idTipoDocumento
                        AND PFA.idAcceso = :IdAcceso1)";

            $binding[':IdAcceso'] = $Args['IdAcceso'];
            $binding[':IdAcceso1'] = $Args['IdAcceso'];
        }

        if (!empty($Args['Activos']) && $Args['Activos']) {
            $sqlWhere .= " AND PF.estado = 1 AND E.estado = 1";
            $sqlWhere1 .= " AND PF.estado = 1 AND E.estado = 1";
        }

        if(!empty($Args['IdEmpresa'])) {            
            $sqlWhere .= " AND PFE.docEmpresa = :IdEmpresaObj
                            AND PFE.tipoDocEmpresa = :IdEmpresaObj1";

            $sqlWhere1 .= " AND PFE.docEmpresa = :IdEmpresaObj2
                            AND PFE.tipoDocEmpresa = :IdEmpresaObj3";

            $binding[':IdEmpresaObj'] = $ArgsIdEmpresaObj[0];
            $binding[':IdEmpresaObj1'] = $ArgsIdEmpresaObj[1];
            $binding[':IdEmpresaObj2'] = $ArgsIdEmpresaObj[0];
            $binding[':IdEmpresaObj3'] = $ArgsIdEmpresaObj[1];
        }

        if (!empty($Args['IdCategoria']) && $Args['IdCategoria']) {
            $sqlWhere .= " AND P.IdCategoria IN (";
            $sqlWhere1 .= " AND P.IdCategoria IN (";
            $i = 0;
            $cantidad = count($Args['IdCategoria']);
            foreach($Args['IdCategoria'] as $idCategoria){
                $i++;
                $binding[':IdCategoria'.$i] = $idCategoria;
                $sqlWhere .= ':IdCategoria'.$i;

                $binding[':IdCategoria999'.$i] = $idCategoria;
                $sqlWhere1 .= ':IdCategoria999'.$i;
                if($cantidad != $i){
                    $sqlWhere .= ',';
                    $sqlWhere1 .= ',';
                }
            }
            $sqlWhere .= ') ';
            $sqlWhere1 .= ') ';
        }
        
        $sql = "SELECT 
                    (SELECT
                    COUNT(*)
                    FROM PersonasFisicas PF 
                    INNER JOIN Personas P ON PF.documento = P.documento AND PF.idTipoDocumento = P.idTipoDocumento
                    INNER JOIN Empresas E ON PF.docEmpresa = E.documento AND PF.tipoDocEmpresa = E.idTipoDocumento
                    INNER JOIN PersonasFisicasEmpresas PFE ON PFE.documento = PF.documento AND PFE.idTipoDocumento = PF.idTipoDocumento
                                                                AND PFE.DocEmpresa = E.Documento AND PFE.TipoDocEmpresa = E.IdTipoDocumento
                    WHERE PF.extranjero = 1
                    AND P.baja = 0
                    AND PF.transito = 0 " . $sqlWhere . ") AS Extranjeros," ;

        $sql .= "   (SELECT 
                    COUNT(*)
                    FROM PersonasFisicas PF 
                    INNER JOIN Personas P ON PF.documento = P.documento AND PF.idTipoDocumento = P.idTipoDocumento
                    INNER JOIN Empresas E ON PF.docEmpresa = E.documento AND PF.tipoDocEmpresa = E.idTipoDocumento
                    INNER JOIN PersonasFisicasEmpresas PFE ON PFE.documento = PF.documento AND PFE.idTipoDocumento = PF.idTipoDocumento
                                                                AND PFE.DocEmpresa = E.Documento AND PFE.TipoDocEmpresa = E.IdTipoDocumento
                    WHERE (PF.extranjero IS NULL OR PF.extranjero = 0)
                    AND P.baja = 0
                    AND PF.transito = 0 " . $sqlWhere1 . ") AS Nacionales";

        //$obj = self::detalle($Usuario, $IdEmpresa, $sql);

        $obj = DB::selectOne($sql, $binding);
        return array(
            array(
                "value" => $obj->Extranjeros,
                "color" => self::FS_CHART_COLORS[0],
                "label" => "Extranjeros"
            ),
            array(
                "value" => $obj->Nacionales,
                "color" => self::FS_CHART_COLORS[1],
                "label" => "Nacionales"
            ),
        );
    }

    public function chartnacionalesextranjerosdetalle() {

        $Args = $this->req->all();

        $sqlWhere = '';
        $binding = Array();

        if (!empty($Args['NroContrato'])) {
            $sqlWhere .= " AND EXISTS (SELECT *
                        FROM PersonasFisicasContratos PFC
                        WHERE PF.documento = PFC.documento
                        AND PF.idTipoDocumento = PFC.idTipoDocumento
                        AND PFC.nroContrato = :NroContrato)";
            $binding[':NroContrato'] = $Args['NroContrato'];
        }
        if (!empty($Args['IdAcceso'])) {
            $sqlWhere .= " AND EXISTS (SELECT *
                        FROM PersonasFisicasAccesos PFA
                        WHERE PF.documento = PFA.documento
                        AND PF.idTipoDocumento = PFA.idTipoDocumento
                        AND PFA.idAcceso = :IdAcceso)";
            $binding[':IdAcceso'] = $Args['IdAcceso'];
        }
        if (!empty($Args['Activos']) && $Args['Activos']) {
            $sqlWhere .= " AND PF.estado = 1 AND E.estado = 1";
        }
        if(!empty($Args['IdEmpresa'])) {
            $IdEmpresaObj = FsUtils::explodeId($Args['IdEmpresa']);
            
            $sqlWhere .= " AND PFE.docEmpresa = :IdEmpresaObj
                            AND PFE.tipoDocEmpresa = :IdEmpresaObj1";
            $binding[':IdEmpresaObj'] = $IdEmpresaObj[0];
            $binding[':IdEmpresaObj1'] = $IdEmpresaObj[1];
        }

        if (!empty($Args['IdCategoria']) && $Args['IdCategoria']) {
            $sqlWhere .= " AND P.IdCategoria IN (";
            $i = 0;
            $cantidad = count($Args['IdCategoria']);
            foreach($Args['IdCategoria'] as $idCategoria){
                $i++;
                $binding[':IdCategoria'.$i] = $idCategoria;
                $sqlWhere .= ':IdCategoria'.$i;

                if($cantidad != $i){
                    $sqlWhere .= ',';
                }
            }
            $sqlWhere .= ') ';
        }
        
        $sql = "SELECT dbo.Mask(pf.Documento, td.Mascara, 1, 1) AS DocumentoMasked,
                        'Extranjero' AS Procedencia,
                        PF.NombreCompleto AS Detalle,
                        pf.Matricula,
                        CASE pf.Estado
                            WHEN 1 THEN 'Activo'
                            ELSE 'Inactivo'
                        END AS Estado,
                        e.Nombre AS Empresa
                    FROM PersonasFisicas PF 
                    INNER JOIN Personas P ON PF.documento = P.documento AND PF.idTipoDocumento = P.idTipoDocumento
                    INNER JOIN TiposDocumento td ON (pf.IdTipoDocumento = td.IdTipoDocumento)
                    INNER JOIN Empresas E ON PF.docEmpresa = E.documento AND PF.tipoDocEmpresa = E.idTipoDocumento
                    INNER JOIN PersonasFisicasEmpresas PFE ON PFE.documento = PF.documento AND PFE.idTipoDocumento = PF.idTipoDocumento
                                                                AND PFE.DocEmpresa = E.Documento AND PFE.TipoDocEmpresa = E.IdTipoDocumento
                    WHERE PF.extranjero = 1
                    AND P.baja = 0
                    AND PF.transito = 0 " . $sqlWhere;
        
        //$sql .= " UNION ALL ";

        $sql1 = "   SELECT dbo.Mask(pf.Documento, td.Mascara, 1, 1) AS DocumentoMasked,
                        'Nacional' AS Procedencia,
                        PF.NombreCompleto AS Detalle,
                        pf.Matricula,
                        CASE pf.Estado
                            WHEN 1 THEN 'Activo'
                            ELSE 'Inactivo'
                        END AS Estado,
                        e.Nombre AS Empresa
                    FROM PersonasFisicas PF 
                    INNER JOIN Personas P ON PF.documento = P.documento AND PF.idTipoDocumento = P.idTipoDocumento
                    INNER JOIN TiposDocumento td ON (pf.IdTipoDocumento = td.IdTipoDocumento)
                    INNER JOIN Empresas E ON PF.docEmpresa = E.documento AND PF.tipoDocEmpresa = E.idTipoDocumento
                    INNER JOIN PersonasFisicasEmpresas PFE ON PFE.documento = PF.documento AND PFE.idTipoDocumento = PF.idTipoDocumento
                                                                AND PFE.DocEmpresa = E.Documento AND PFE.TipoDocEmpresa = E.IdTipoDocumento
                    WHERE (PF.extranjero IS NULL OR PF.extranjero = 0)
                    AND P.baja = 0
                    AND PF.transito = 0 " . $sqlWhere;
       
       return array_merge(DB::select($sql, $binding), DB::select($sql1, $binding));
    }

    private function chartporpaisdepartamento($Args) {

        if (empty($Args['IdPais'])) {
            return $this->chartlugarnacimiento($Args, 'BAR');
        }

        $sqlWhere = '';
        $binding = Array();

        if (!empty($Args['NroContrato'])) {
            $sqlWhere .= " AND EXISTS (SELECT *
                        FROM PersonasFisicasContratos PFC
                        WHERE PF.documento = PFC.documento
                        AND PF.idTipoDocumento = PFC.idTipoDocumento
                        AND PFC.nroContrato = :NroContrato)";
            $binding[':NroContrato'] = $Args['NroContrato'];
        }

        if (!empty($Args['IdAcceso'])) {
            $sqlWhere .= " AND EXISTS (SELECT *
                        FROM PersonasFisicasAccesos PFA
                        WHERE PF.documento = PFA.documento
                        AND PF.idTipoDocumento = PFA.idTipoDocumento
                        AND PFA.idAcceso = :IdAcceso)";
            $binding[':IdAcceso'] = $Args['IdAcceso'];
        }

        if (!empty($Args['Activos']) && $Args['Activos']) {
            $sqlWhere .= " AND PF.estado = 1"; //AND E.estado = 1
        }

        if (!empty($Args['IdCategoria']) && $Args['IdCategoria']) {
            $sqlWhere .= " AND P.IdCategoria IN (";
            $i = 0;
            $cantidad = count($Args['IdCategoria']);
            foreach($Args['IdCategoria'] as $idCategoria){
                $i++;
                $binding[':IdCategoria'.$i] = $idCategoria;
                $sqlWhere .= ':IdCategoria'.$i;

                if($cantidad != $i){
                    $sqlWhere .= ',';
                }
            }
            $sqlWhere .= ') ';
        }

        if(!empty($Args['IdEmpresa'])) {
            $IdEmpresaObj = FsUtils::explodeId($Args['IdEmpresa']);
            
            $sqlWhere .= " AND PFE.docEmpresa = :IdEmpresaObj
                            AND PFE.tipoDocEmpresa = :IdEmpresaObj1";
            $binding[':IdEmpresaObj'] = $IdEmpresaObj[0];
            $binding[':IdEmpresaObj1'] = $IdEmpresaObj[1];
        }

        if (empty($Args['IdDepartamento'])) {

            if (!empty($Args['Lugar']) && $Args['Lugar']) {
                $sql = "SELECT PF.idDepartamentoNac, D.nombre AS Nombre, COUNT(*) AS Cantidad";
            } else {
                $sql = "SELECT P.idDepartamento, D.nombre AS Nombre, COUNT(*) AS Cantidad";
            }

            $sql .= " FROM PersonasFisicas PF INNER JOIN Personas P ON PF.documento = P.documento AND PF.idTipoDocumento = P.idTipoDocumento ";

            if (!empty($Args['Baja']) && !$Args['Baja']) {
                $sql .= " INNER JOIN Empresas E ON PF.docEmpresa = E.documento AND PF.tipoDocEmpresa = E.idTipoDocumento ";
            }
            
            if (!empty($Args['IdEmpresa'])) {
                $sql .= " INNER JOIN PersonasFisicasEmpresas PFE ON PFE.documento = PF.documento AND PFE.idTipoDocumento = PF.idTipoDocumento";
            }

            if (!empty($Args['Lugar']) && $Args['Lugar']) {
                $sql .= " INNER JOIN Departamentos D ON PF.idPaisNac = D.idPais AND PF.idDepartamentoNac = D.idDepartamento";
            } else {
                $sql .= " INNER JOIN Departamentos D ON P.idPais = D.idPais AND P.idDepartamento = D.idDepartamento";
            }

            $sql .= " WHERE NOT PF.idPaisNac IS NULL
                        AND NOT P.idPais IS NULL";

            if (!empty($Args['Baja']) && !$Args['Baja']) {
                $sql .= " AND P.baja = 0";
            }

            if (!empty($Args['IdPais'])) {
                if (!empty($Args['Lugar']) && $Args['Lugar']) {
                    $sql .= "  AND PF.idPaisNac = :IdPais";
                    $binding[':IdPais'] = $Args['IdPais'];
                } else {
                    $sql .= " AND P.idPais = :IdPais";
                    $binding[':IdPais'] = $Args['IdPais'];
                }
            }

            $sql .= " AND PF.transito = 0";

            $sql .= $sqlWhere;

            if (!empty($Args['Lugar']) && $Args['Lugar']) {
                $sql .= " GROUP BY PF.idDepartamentoNac, D.nombre ";
            } else {
                $sql .= " GROUP BY P.idDepartamento, D.nombre ";
            }
            $sql .= " ORDER BY Cantidad DESC";
        } else {

            $sql = "SELECT
                    CASE P.ciudad
                        WHEN '' THEN 'Sin especificar'
                        WHEN 'Sin especificar' THEN 'Sin especificar'
                        ELSE P.ciudad
                    END AS Nombre,
                    COUNT(*) AS Cantidad
                    FROM PersonasFisicas PF 
                    INNER JOIN Personas P ON PF.documento = P.documento AND PF.idTipoDocumento = P.idTipoDocumento";

            if (!empty($Args['Baja']) && !$Args['Baja']) {
                $sql .= " INNER JOIN Empresas E ON PF.docEmpresa = E.documento AND PF.tipoDocEmpresa = E.idTipoDocumento
                            WHERE NOT PF.idPaisNac IS NULL
                            AND NOT P.idPais IS NULL
                            AND P.baja = 0";
            } else {
                $sql .= " WHERE NOT PF.idPaisNac IS NULL
                            AND NOT P.idPais IS NULL";
            }
            if (!empty($Args['IdPais'])) {
                if (!empty($Args['Lugar']) && $Args['Lugar']) {
                    $sql .= " AND PF.idPaisNac = :IdPais1";
                    $binding[':IdPais1'] = $Args['IdPais'];
                } else {
                    $sql .= " AND P.idPais = :IdPais1";
                    $binding[':IdPais1'] = $Args['IdPais'];
                }
            }
            if (!empty($Args['IdDepartamento'])) {
                if (!empty($Args['Lugar']) && $Args['Lugar']) {
                    $sql .= " AND PF.idDepartamentoNac = :IdDepartamento1";
                    $binding[':IdDepartamento1'] = $Args['IdPais'];
                } else {
                    $sql .= " AND P.idDepartamento = :IdDepartamento1";
                    $binding[':IdDepartamento1'] = $Args['IdPais'];
                }
            }

            $sql .= " AND PF.transito = 0";

            $sql .= $sqlWhere;

            $sql .= " GROUP BY P.ciudad ";
            $sql .= " ORDER BY Cantidad DESC ";
        }

        $obj = DB::select($sql, $binding);

        $datasets = array();
        $i = 0;

        foreach ($obj as $p) {

            // para gráfico BAR
            $pais = array("label" => $p->Nombre, "fillColor" => self::FS_CHART_COLORS[$i], "data" => array($p->Cantidad));

            $datasets[] = $pais;
            $i++;

            if ($i == count(self::FS_CHART_COLORS))
                $i = 0;
        }

        // para gráfico BAR
        return array(
            "labels" => array(
                "Por país / Departamento"
            ),
            "datasets" => $datasets,
        );
    }

    public function chartporpaisdepartamentodetalle() {

        $Args = $this->req->all();
        $sqlWhere = '';
        $binding = Array();


        if (!empty($Args['NroContrato'])) {
            $sqlWhere .= " AND EXISTS (SELECT *
                        FROM PersonasFisicasContratos PFC
                        WHERE PF.documento = PFC.documento
                        AND PF.idTipoDocumento = PFC.idTipoDocumento
                        AND PFC.nroContrato = :NroContrato)";
            $binding[':NroContrato'] = $Args['NroContrato'];
        }
        if (!empty($Args['IdAcceso'])) {
            $sqlWhere .= " AND EXISTS (SELECT *
                        FROM PersonasFisicasAccesos PFA
                        WHERE PF.documento = PFA.documento
                        AND PF.idTipoDocumento = PFA.idTipoDocumento
                        AND PFA.idAcceso = :IdAcceso)";
            $binding[':IdAcceso'] = $Args['IdAcceso'];
        }
        if (!empty($Args['Activos']) && $Args['Activos']) {
            $sqlWhere .= " AND PF.estado = 1"; //AND E.estado = 1
        }

        if (!empty($Args['IdCategoria']) && $Args['IdCategoria']) {
            $sqlWhere .= " AND P.IdCategoria IN (";
            $i = 0;
            $cantidad = count($Args['IdCategoria']);
            foreach($Args['IdCategoria'] as $idCategoria){
                $i++;
                $binding[':IdCategoria'.$i] = $idCategoria;
                $sqlWhere .= ':IdCategoria'.$i;

                if($cantidad != $i){
                    $sqlWhere .= ',';
                }
            }
            $sqlWhere .= ') ';
        }

        if(!empty($Args['IdEmpresa'])) {
            $IdEmpresaObj = FsUtils::explodeId($Args['IdEmpresa']);
            
            $sqlWhere .= " AND PFE.docEmpresa = :IdEmpresaObj
                            AND PFE.tipoDocEmpresa = :IdEmpresaObj1";
            $binding[':IdEmpresaObj'] = $IdEmpresaObj[0];
            $binding[':IdEmpresaObj1'] = $IdEmpresaObj[1];
        }

        if (empty($Args['IdDepartamento'])) {

            if (!empty($Args['Lugar']) && $Args['Lugar']) {
                $sql = "SELECT PF.idDepartamentoNac,
                                dbo.Mask(pf.Documento, td.Mascara, 1, 1) AS DocumentoMasked,
                                D.nombre AS Procedencia,
                                PF.NombreCompleto AS Detalle,
                                pf.Matricula,
                                CASE pf.Estado
                                        WHEN 1 THEN 'Activo'
                                        ELSE 'Inactivo'
                                END AS Estado,
                                e.Nombre AS Empresa";
            } else {
                $sql = "SELECT P.idDepartamento,
                                dbo.Mask(pf.Documento, td.Mascara, 1, 1) AS DocumentoMasked,
                                D.nombre AS Procedencia,
                                PF.NombreCompleto AS Detalle,
                                pf.Matricula,
                                CASE pf.Estado
                                        WHEN 1 THEN 'Activo'
                                        ELSE 'Inactivo'
                                END AS Estado,
                                e.Nombre AS Empresa";
            }

            $sql .= " FROM PersonasFisicas PF INNER JOIN Personas P ON PF.documento = P.documento AND PF.idTipoDocumento = P.idTipoDocumento
                        INNER JOIN TiposDocumento td ON (pf.IdTipoDocumento = td.IdTipoDocumento)
                        LEFT JOIN PersonasFisicasEmpresas PFE ON PFE.documento = PF.documento AND PFE.idTipoDocumento = PF.idTipoDocumento";

            
            /// inconsistencia, aparece varias veces.
            if (!empty($Args['Baja']) && !$Args['Baja']) {
                $sql .= " INNER JOIN Empresas E ON PF.docEmpresa = E.documento AND PF.tipoDocEmpresa = E.idTipoDocumento ";
            } else {
                $sql .= " LEFT JOIN Empresas E ON PF.docEmpresa = E.documento AND PF.tipoDocEmpresa = E.idTipoDocumento ";
            }

            if (!empty($Args['Lugar']) && $Args['Lugar']) {
                $sql .= " INNER JOIN Departamentos D ON PF.idPaisNac = D.idPais AND PF.idDepartamentoNac = D.idDepartamento";
            } else {
                $sql .= " INNER JOIN Departamentos D ON P.idPais = D.idPais AND P.idDepartamento = D.idDepartamento";
            }

            $sql .= " WHERE NOT PF.idPaisNac IS NULL
                        AND NOT P.idPais IS NULL";

            if (!empty($Args['Baja']) && !$Args['Baja']) {
                $sql .= " AND P.baja = 0";
            }

            if (!empty($Args['IdPais'])) {
                if (!empty($Args['Lugar']) && $Args['Lugar']) {
                    $sql .= "  AND PF.idPaisNac = :IdPais";
                    $binding[':IdPais'] = $Args['IdPais'];
                } else {
                    $sql .= " AND P.idPais = :IdPais";
                    $binding[':IdPais'] = $Args['IdPais'];
                }
            }

            $sql .= " AND PF.transito = 0";

            $sql .= $sqlWhere;

            $sql .= " ORDER BY D.Nombre";
        } else {

            $sql = "SELECT dbo.Mask(pf.Documento, td.Mascara, 1, 1) AS DocumentoMasked,
                            P.Ciudad AS Procedencia,
                            PF.NombreCompleto AS Detalle,
                            pf.Matricula,
                            CASE pf.Estado
                                    WHEN 1 THEN 'Activo'
                                    ELSE 'Inactivo'
                            END AS Estado,
                            e.Nombre AS Empresa,
                            CASE P.ciudad
                                WHEN '' THEN 'Sin especificar'
                                WHEN 'Sin especificar' THEN 'Sin especificar'
                                ELSE P.ciudad
                            END AS Procedencia
                    FROM PersonasFisicas PF 
                    INNER JOIN Personas P ON PF.documento = P.documento AND PF.idTipoDocumento = P.idTipoDocumento
                    INNER JOIN TiposDocumento td ON (pf.IdTipoDocumento = td.IdTipoDocumento)
                    LEFT JOIN PersonasFisicasEmpresas PFE ON PFE.documento = PF.documento AND PFE.idTipoDocumento = PF.idTipoDocumento";

            if (!empty($Args['Baja']) && !$Args['Baja']) {
                $sql .= " INNER JOIN Empresas E ON PF.docEmpresa = E.documento AND PF.tipoDocEmpresa = E.idTipoDocumento
                            WHERE NOT PF.idPaisNac IS NULL
                            AND NOT P.idPais IS NULL
                            AND P.baja = 0";
            } else {
                $sql .= " LEFT JOIN Empresas E ON PF.docEmpresa = E.documento AND PF.tipoDocEmpresa = E.idTipoDocumento
                            WHERE NOT PF.idPaisNac IS NULL
                            AND NOT P.idPais IS NULL";
            }
            if (!empty($Args['IdPais'])) {
                if (!empty($Args['Lugar']) && $Args['Lugar']) {
                    $sql .= " AND PF.idPaisNac = :IdPais";
                    $binding[':IdPais'] = $Args['IdPais'];
                } else {
                    $sql .= " AND P.idPais = :IdPais";
                    $binding[':IdPais'] = $Args['IdPais'];
                }
            }
            if (!empty($Args['IdDepartamento'])) {
                if (!empty($Args['Lugar']) && $Args['Lugar']) {
                    $sql .= " AND PF.idDepartamentoNac = :IdDepartamento";
                    $binding[':IdDepartamento'] = $Args['IdDepartamento'];
                } else {
                    $sql .= " AND P.idDepartamento = :IdDepartamento";
                    $binding[':IdDepartamento'] = $Args['IdDepartamento'];
                }
            }

            $sql .= " AND PF.transito = 0";

            $sql .= $sqlWhere;

            $sql .= " ORDER BY P.Ciudad";
        }
        //return $sql;
        return DB::select($sql, $binding);
    }

    private function chartlugarnacimiento($Args, $chartType = 'PIE') {

        $binding = Array();

        $sql = "SELECT PF.idPaisNac, PA.Nombre, COUNT(*) AS Cantidad
                FROM PersonasFisicas PF INNER JOIN Personas P ON PF.documento = P.documento AND PF.idTipoDocumento = P.idTipoDocumento
                INNER JOIN Empresas E ON PF.docEmpresa = E.documento AND PF.tipoDocEmpresa = E.idTipoDocumento
                INNER JOIN Paises PA ON PF.idPaisNac = PA.idPais
                LEFT JOIN PersonasFisicasEmpresas PFE ON PFE.documento = PF.documento AND PFE.idTipoDocumento = PF.idTipoDocumento
                WHERE NOT PF.idPaisNac IS NULL
                AND P.baja = 0
                AND PF.transito = 0";

        if (!empty($Args['Activos']) && $Args['Activos']){
            $sql .= " AND PF.estado = 1 AND E.estado = 1";
        }
            

        if (!empty($Args['NroContrato'])){
            $sql .= " AND EXISTS (SELECT *
            FROM PersonasFisicasContratos PFC
            WHERE PF.documento = PFC.documento
            AND PF.idTipoDocumento = PFC.idTipoDocumento
            AND PFC.nroContrato = :NroContrato)";

            $binding[':NroContrato'] = $Args['NroContrato'];
        }

        if (!empty($Args['IdCategoria']) && $Args['IdCategoria']) {
            $sql .= " AND P.IdCategoria IN (";
            $i = 0;
            $cantidad = count($Args['IdCategoria']);
            foreach($Args['IdCategoria'] as $idCategoria){
                $i++;
                $binding[':IdCategoria'.$i] = $idCategoria;
                $sql .= ':IdCategoria'.$i;

                if($cantidad != $i){
                    $sql .= ',';
                }
            }
            $sql .= ') ';
        }
           
        if (!empty($Args['IdAcceso'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM PersonasFisicasAccesos PFA
                        WHERE PF.documento = PFA.documento
                        AND PF.idTipoDocumento = PFA.idTipoDocumento
                        AND PFA.idAcceso = :IdAcceso)";

            $binding[':IdAcceso'] = $Args['IdAcceso'];
        }

        if(!empty($Args['IdEmpresa'])) {
            $IdEmpresaObj = FsUtils::explodeId($Args['IdEmpresa']);
            
            $sql .= " AND PFE.docEmpresa = :IdEmpresaObj
                            AND PFE.tipoDocEmpresa = :IdEmpresaObj1";
            $binding[':IdEmpresaObj'] = $IdEmpresaObj[0];
            $binding[':IdEmpresaObj1'] = $IdEmpresaObj[1];
        }

        $sql .= " GROUP BY PF.idPaisNac, PA.Nombre";
        $sql .= " ORDER BY Cantidad DESC";

        $obj = DB::select($sql, $binding);

        $datasets = array();
        $i = 0;

        foreach ($obj as $p) {

            if ($chartType === 'BAR')
                $pais = array("label" => $p->Nombre, "fillColor" => self::FS_CHART_COLORS[$i], "data" => array($p->Cantidad));

            if ($chartType === 'PIE')
                $pais = array("label" => $p->Nombre, "color" => self::FS_CHART_COLORS[$i], "value" => array($p->Cantidad));

            $datasets[] = $pais;
            $i++;

            if ($i == count(self::FS_CHART_COLORS))
                $i = 0;
        }

        if ($chartType === 'BAR') {
            return array(
                "labels" => array(
                    "Países"
                ),
                "datasets" => $datasets,
            );
        }
        if ($chartType === 'PIE') {
            return $datasets;
        }

        return $datasets;
    }

    public function chartlugarnacimientodetalle() {

        $Args = $this->req->all();
        $binding = Array();

        $sql = "SELECT dbo.Mask(pf.Documento, td.Mascara, 1, 1) AS DocumentoMasked,
                        PF.NombreCompleto AS Detalle,
                        pf.Matricula,
                        CASE pf.Estado
                            WHEN 1 THEN 'Activo'
                            ELSE 'Inactivo'
                        END AS Estado,
                        e.Nombre AS Empresa,
                        PA.Nombre AS Procedencia
                FROM PersonasFisicas PF INNER JOIN Personas P ON PF.documento = P.documento AND PF.idTipoDocumento = P.idTipoDocumento
                INNER JOIN TiposDocumento td ON (pf.IdTipoDocumento = td.IdTipoDocumento)
                INNER JOIN Empresas E ON PF.docEmpresa = E.documento AND PF.tipoDocEmpresa = E.idTipoDocumento
                INNER JOIN Paises PA ON PF.idPaisNac = PA.idPais
                LEFT JOIN PersonasFisicasEmpresas PFE ON PFE.documento = PF.documento AND PFE.idTipoDocumento = PF.idTipoDocumento
                WHERE NOT PF.idPaisNac IS NULL
                AND P.baja = 0
                AND PF.transito = 0";

        if (!empty($Args['Activos']) && $Args['Activos']){
            $sql .= " AND PF.estado = 1 AND E.estado = 1";
        }
            

        if (!empty($Args['NroContrato'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM PersonasFisicasContratos PFC
                        WHERE PF.documento = PFC.documento
                        AND PF.idTipoDocumento = PFC.idTipoDocumento
                        AND PFC.nroContrato = :NroContrato)";
            $binding[':NroContrato'] = $Args['NroContrato'];  
        }
                                  

        if (!empty($Args['IdAcceso'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM PersonasFisicasAccesos PFA
                        WHERE PF.documento = PFA.documento
                        AND PF.idTipoDocumento = PFA.idTipoDocumento
                        AND PFA.idAcceso = :IdAcceso)";
            $binding[':IdAcceso'] = $Args['IdAcceso'];
        }

        if (!empty($Args['IdCategoria']) && $Args['IdCategoria']) {
            $sql .= " AND P.IdCategoria IN (";
            $i = 0;
            $cantidad = count($Args['IdCategoria']);
            foreach($Args['IdCategoria'] as $idCategoria){
                $i++;
                $binding[':IdCategoria'.$i] = $idCategoria;
                $sql .= ':IdCategoria'.$i;

                if($cantidad != $i){
                    $sql .= ',';
                }
            }
            $sql .= ') ';
        }

        if(!empty($Args['IdEmpresa'])) {
            $IdEmpresaObj = FsUtils::explodeId($Args['IdEmpresa']);
            
            $sql .= " AND PFE.docEmpresa = :IdEmpresaObj
                            AND PFE.tipoDocEmpresa = :IdEmpresaObj1";
            $binding[':IdEmpresaObj'] = $IdEmpresaObj[0];
            $binding[':IdEmpresaObj1'] = $IdEmpresaObj[1];
        }

        $sql .= " ORDER BY PA.Nombre, DocumentoMasked";

        return DB::select($sql, $binding);
    }

    private function charthabilitados($Args) {

        $binding = Array();
        $sql = "SELECT ";

        $sql .= "(SELECT COUNT(*)
                    FROM PersonasFisicas PF INNER JOIN Personas P ON PF.documento = P.documento AND PF.idTipoDocumento = P.idTipoDocumento
                    INNER JOIN Empresas E ON PF.docEmpresa = E.documento AND PF.tipoDocEmpresa = E.idTipoDocumento
                    LEFT JOIN PersonasFisicasEmpresas PFE ON PFE.documento = PF.documento AND PFE.idTipoDocumento = PF.idTipoDocumento
                    WHERE P.baja = 0
                    AND PF.transito = 0
                    AND PF.estado = 1
                    AND E.estado = 1";
        
        if (!empty($Args['IdPais'])){
            $sql .= " AND PF.idPaisNac = :IdPais";
            $binding[':IdPais'] = $Args['IdPais'];
        }
            

        if (!empty($Args['NroContrato'])){
            $sql .= " AND EXISTS (SELECT *
			 FROM PersonasFisicasContratos PFC
			 WHERE PF.documento = PFC.documento
			 AND PF.idTipoDocumento = PFC.idTipoDocumento
			 AND PFC.nroContrato = :NroContrato)";
            $binding[':NroContrato'] = $Args['NroContrato'];
        }

        if (!empty($Args['IdAcceso'])){
            $sql .= " AND EXISTS (SELECT *
			FROM PersonasFisicasAccesos PFA
			WHERE PF.documento = PFA.documento
			AND PF.idTipoDocumento = PFA.idTipoDocumento
			AND PFA.idAcceso = :IdAcceso) ";

            $binding[':IdAcceso'] = $Args['IdAcceso'];
        }
        
        if (!empty($Args['IdCategoria']) && $Args['IdCategoria']) {
            $sql .= " AND P.IdCategoria IN (";
            $i = 0;
            $cantidad = count($Args['IdCategoria']);
            foreach($Args['IdCategoria'] as $idCategoria){
                $i++;
                $binding[':IdCategoria'.$i] = $idCategoria;
                $sql .= ':IdCategoria'.$i;

                if($cantidad != $i){
                    $sql .= ',';
                }
            }
            $sql .= ') ';
        }

        if(!empty($Args['IdEmpresa'])) {
            $IdEmpresaObj = FsUtils::explodeId($Args['IdEmpresa']);
            
            $sql .= " AND PFE.docEmpresa = :IdEmpresaObj
                            AND PFE.tipoDocEmpresa = :IdEmpresaObj1";
            $binding[':IdEmpresaObj'] = $IdEmpresaObj[0];
            $binding[':IdEmpresaObj1'] = $IdEmpresaObj[1];
        }

        $sql .= " ) AS Habilitados,";

        $sql .= "(SELECT COUNT(*)
                    FROM PersonasFisicas PF INNER JOIN Personas P ON PF.documento = P.documento AND PF.idTipoDocumento = P.idTipoDocumento
                    INNER JOIN Empresas E ON PF.docEmpresa = E.documento AND PF.tipoDocEmpresa = E.idTipoDocumento
                    LEFT JOIN PersonasFisicasEmpresas PFE ON PFE.documento = PF.documento AND PFE.idTipoDocumento = PF.idTipoDocumento
                    WHERE P.baja = 0
                    AND PF.transito = 0
                    AND (PF.estado = 0 OR E.estado = 0)";

        if (!empty($Args['IdPais'])){
            $sql .= " AND PF.idPaisNac = :IdPais1";
            $binding[':IdPais1'] = $Args['IdPais'];
        }

        if (!empty($Args['NroContrato'])){
            $sql .= " AND EXISTS (SELECT *
			 FROM PersonasFisicasContratos PFC
			 WHERE PF.documento = PFC.documento
			 AND PF.idTipoDocumento = PFC.idTipoDocumento
			 AND PFC.nroContrato = :NroContrato1)";
             $binding[':NroContrato1'] = $Args['NroContrato'];
        }

        if (!empty($Args['IdAcceso'])){
            $sql .= " AND EXISTS (SELECT *
			FROM PersonasFisicasAccesos PFA
			WHERE PF.documento = PFA.documento
			AND PF.idTipoDocumento = PFA.idTipoDocumento
			AND PFA.idAcceso = :IdAcceso2)";
            $binding[':IdAcceso2'] = $Args['IdAcceso'];
        }

        if (!empty($Args['IdCategoria']) && $Args['IdCategoria']) {
            $sql .= " AND P.IdCategoria IN (";
            $i = 0;
            $cantidad = count($Args['IdCategoria']);
            foreach($Args['IdCategoria'] as $idCategoria){
                $i++;
                $binding[':IdCategoria999'.$i] = $idCategoria;
                $sql .= ':IdCategoria999'.$i;

                if($cantidad != $i){
                    $sql .= ',';
                }
            }
            $sql .= ') ';
        }

        if(!empty($Args['IdEmpresa'])) {
            $IdEmpresaObj = FsUtils::explodeId($Args['IdEmpresa']);
            
            $sql .= " AND PFE.docEmpresa = :IdEmpresaObj2
                            AND PFE.tipoDocEmpresa = :IdEmpresaObj3";
            $binding[':IdEmpresaObj2'] = $IdEmpresaObj[0];
            $binding[':IdEmpresaObj3'] = $IdEmpresaObj[1];
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

        $sql = "SELECT dbo.Mask(pf.Documento, td.Mascara, 1, 1) AS DocumentoMasked,
                        'Habilitado' AS Habilitado,
                        PF.NombreCompleto AS Detalle,
                        pf.Matricula,
                        CASE pf.Estado
                            WHEN 1 THEN 'Activo'
                            ELSE 'Inactivo'
                        END AS Estado,
                        e.Nombre AS Empresa
                    FROM PersonasFisicas PF INNER JOIN Personas P ON PF.documento = P.documento AND PF.idTipoDocumento = P.idTipoDocumento
                    INNER JOIN TiposDocumento td ON (pf.IdTipoDocumento = td.IdTipoDocumento)
                    INNER JOIN Empresas E ON PF.docEmpresa = E.documento AND PF.tipoDocEmpresa = E.idTipoDocumento
                    LEFT JOIN PersonasFisicasEmpresas PFE ON PFE.documento = PF.documento AND PFE.idTipoDocumento = PF.idTipoDocumento
                    WHERE P.baja = 0
                    AND PF.transito = 0
                    AND PF.estado = 1
                    AND E.estado = 1";
        
        if (!empty($Args['IdPais'])){        
            $sql .= " AND PF.idPaisNac = :IdPais";
            $binding[':IdPais'] = $Args['IdPais'];
        }

        if (!empty($Args['NroContrato'])){
            $sql .= " AND EXISTS (SELECT *
                    FROM PersonasFisicasContratos PFC
                    WHERE PF.documento = PFC.documento
                    AND PF.idTipoDocumento = PFC.idTipoDocumento
                    AND PFC.nroContrato = :NroContrato)";
            $binding[':NroContrato'] = $Args['NroContrato'];
        }
            
        if (!empty($Args['IdAcceso'])){
            $sql .= " AND EXISTS (SELECT *
                    FROM PersonasFisicasAccesos PFA
                    WHERE PF.documento = PFA.documento
                    AND PF.idTipoDocumento = PFA.idTipoDocumento
                    AND PFA.idAcceso = :IdAcceso) ";
            $binding[':IdAcceso'] = $Args['IdAcceso'];
        }

        if (!empty($Args['IdCategoria']) && $Args['IdCategoria']) {
            $sql .= " AND P.IdCategoria IN (";
            $i = 0;
            $cantidad = count($Args['IdCategoria']);
            foreach($Args['IdCategoria'] as $idCategoria){
                $i++;
                $binding[':IdCategoria'.$i] = $idCategoria;
                $sql .= ':IdCategoria'.$i;

                if($cantidad != $i){
                    $sql .= ',';
                }
            }
            $sql .= ') ';
        }
        
        if(!empty($Args['IdEmpresa'])) {
            $IdEmpresaObj = FsUtils::explodeId($Args['IdEmpresa']);
            
            $sql .= " AND PFE.docEmpresa = :IdEmpresaObj
                            AND PFE.tipoDocEmpresa = :IdEmpresaObj1";
            $binding[':IdEmpresaObj'] = $IdEmpresaObj[0];
            $binding[':IdEmpresaObj1'] = $IdEmpresaObj[1];
        }

        $sql .= " UNION ALL ";

        $sql .= "SELECT dbo.Mask(pf.Documento, td.Mascara, 1, 1) AS DocumentoMasked,
                        'No habilitado' AS Habilitado,
                        PF.NombreCompleto AS Detalle,
                        pf.Matricula,
                        CASE pf.Estado
                            WHEN 1 THEN 'Activo'
                            ELSE 'Inactivo'
                        END AS Estado,
                        e.Nombre AS Empresa
                    FROM PersonasFisicas PF INNER JOIN Personas P ON PF.documento = P.documento AND PF.idTipoDocumento = P.idTipoDocumento
                    INNER JOIN TiposDocumento td ON (pf.IdTipoDocumento = td.IdTipoDocumento)
                    INNER JOIN Empresas E ON PF.docEmpresa = E.documento AND PF.tipoDocEmpresa = E.idTipoDocumento
                    LEFT JOIN PersonasFisicasEmpresas PFE ON PFE.documento = PF.documento AND PFE.idTipoDocumento = PF.idTipoDocumento
                    WHERE P.baja = 0
                    AND PF.transito = 0
                    AND (PF.estado = 0 OR E.estado = 0)";

        if (!empty($Args['IdPais'])){
            $sql .= " AND PF.idPaisNac = :IdPais1";
            $binding[':IdPais1'] = $Args['IdPais'];
        }
            

        if (!empty($Args['NroContrato'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM PersonasFisicasContratos PFC
                        WHERE PF.documento = PFC.documento
                        AND PF.idTipoDocumento = PFC.idTipoDocumento
                        AND PFC.nroContrato = :NroContrato1)";
            $binding[':NroContrato1'] = $Args['NroContrato'];
        }
          
        if (!empty($Args['IdAcceso'])){
            $sql .= " AND EXISTS (SELECT *
                    FROM PersonasFisicasAccesos PFA
                    WHERE PF.documento = PFA.documento
                    AND PF.idTipoDocumento = PFA.idTipoDocumento
                    AND PFA.idAcceso = :IdAcceso1)";
            $binding[':IdAcceso1'] = $Args['IdAcceso'];
        }

        if (!empty($Args['IdCategoria']) && $Args['IdCategoria']) {
            $sql .= " AND P.IdCategoria IN (";
            $i = 0;
            $cantidad = count($Args['IdCategoria']);
            foreach($Args['IdCategoria'] as $idCategoria){
                $i++;
                $binding[':IdCategoria'.$i] = $idCategoria;
                $sql .= ':IdCategoria'.$i;

                if($cantidad != $i){
                    $sql .= ',';
                }
            }
            $sql .= ') ';
        }
        
        if(!empty($Args['IdEmpresa'])) {
            $IdEmpresaObj = FsUtils::explodeId($Args['IdEmpresa']);
            
            $sql .= " AND PFE.docEmpresa = :IdEmpresaObj2
                            AND PFE.tipoDocEmpresa = :IdEmpresaObj3";
            $binding[':IdEmpresaObj2'] = $IdEmpresaObj[0];
            $binding[':IdEmpresaObj3'] = $IdEmpresaObj[1];
        }

        return DB::select($sql, $binding);
    }

    private function chartporcategoria($Args) {

        $binding = Array();

        $sql = "SELECT P.idCategoria, C.descripcion AS Nombre, COUNT(*) AS Cantidad
                FROM PersonasFisicas PF INNER JOIN Personas P ON PF.documento = P.documento AND PF.idTipoDocumento = P.idTipoDocumento
                INNER JOIN Empresas E ON PF.docEmpresa = E.documento AND PF.tipoDocEmpresa = E.idTipoDocumento
                INNER JOIN Categorias C ON P.idCategoria = C.idCategoria
                LEFT JOIN PersonasFisicasEmpresas PFE ON PFE.documento = PF.documento AND PFE.idTipoDocumento = PF.idTipoDocumento
                WHERE NOT PF.idPaisNac IS NULL
                AND P.baja = 0";

        if (!empty($Args['NroContrato'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM PersonasFisicasContratos PFC
                        WHERE PF.documento = PFC.documento
                        AND PF.idTipoDocumento = PFC.idTipoDocumento
                        AND PFC.nroContrato = :NroContrato)";
            $binding[':NroContrato'] = $Args['NroContrato'];
        }
            

        if (!empty($Args['IdAcceso'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM PersonasFisicasAccesos PFA
                        WHERE PF.documento = PFA.documento
                        AND PF.idTipoDocumento = PFA.idTipoDocumento
                        AND PFA.idAcceso = :IdAcceso) ";
            $binding[':IdAcceso'] = $Args['IdAcceso'];
        }
        
        if (!empty($Args['Activos']) && $Args['Activos']){
            $sql .= " AND PF.estado = 1";
        }

        if (!empty($Args['IdCategoria']) && $Args['IdCategoria']) {
            $sql .= " AND P.IdCategoria IN (";
            $i = 0;
            $cantidad = count($Args['IdCategoria']);
            foreach($Args['IdCategoria'] as $idCategoria){
                $i++;
                $binding[':IdCategoria'.$i] = $idCategoria;
                $sql .= ':IdCategoria'.$i;

                if($cantidad != $i){
                    $sql .= ',';
                }
            }
            $sql .= ') ';
        }
        
        if(!empty($Args['IdEmpresa'])) {
            $IdEmpresaObj = FsUtils::explodeId($Args['IdEmpresa']);
            
            $sql .= " AND PFE.docEmpresa = :IdEmpresaObj
                            AND PFE.tipoDocEmpresa = :IdEmpresaObj1";
            $binding[':IdEmpresaObj'] = $IdEmpresaObj[0];
            $binding[':IdEmpresaObj1'] = $IdEmpresaObj[1];
        }

        $sql .= " GROUP BY P.idCategoria, C.descripcion";
        $sql .= " ORDER BY Cantidad DESC";
        
        $obj = DB::select($sql, $binding);

        //$obj = self::listado($Usuario, $IdEmpresa, $sql);

        $datasets = array();
        $i = 0;

        foreach ($obj as $c) {
                $categoria = array("label" => $c->Nombre, "color" => self::FS_CHART_COLORS[$i], "value" => array($c->Cantidad));

            $datasets[] = $categoria;
            $i++;

            if ($i == count(self::FS_CHART_COLORS))
                $i = 0;
        }
        
        return $datasets;
    }

    public function chartporcategoriadetalle() {

        $Args = $this->req->all();
        $binding = Array();

        $sql = "SELECT dbo.Mask(pf.Documento, td.Mascara, 1, 1) AS DocumentoMasked,
                        C.descripcion AS Categoria,
                        PF.NombreCompleto AS Detalle,
                        pf.Matricula,
                        CASE pf.Estado
                                WHEN 1 THEN 'Activo'
                                ELSE 'Inactivo'
                        END AS Estado,
                        e.Nombre AS Empresa
                FROM PersonasFisicas PF INNER JOIN Personas P ON PF.documento = P.documento AND PF.idTipoDocumento = P.idTipoDocumento
                INNER JOIN Empresas E ON PF.docEmpresa = E.documento AND PF.tipoDocEmpresa = E.idTipoDocumento
                INNER JOIN Categorias C ON P.idCategoria = C.idCategoria
                INNER JOIN TiposDocumento td ON (pf.IdTipoDocumento = td.IdTipoDocumento)
                LEFT JOIN PersonasFisicasEmpresas PFE ON PFE.documento = PF.documento AND PFE.idTipoDocumento = PF.idTipoDocumento
                WHERE NOT PF.idPaisNac IS NULL
                AND P.baja = 0";

        if (!empty($Args['NroContrato'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM PersonasFisicasContratos PFC
                        WHERE PF.documento = PFC.documento
                        AND PF.idTipoDocumento = PFC.idTipoDocumento
                        AND PFC.nroContrato = :NroContrato)";
            $binding[':NroContrato'] = $Args['NroContrato'];
        }

        if (!empty($Args['IdAcceso'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM PersonasFisicasAccesos PFA
                        WHERE PF.documento = PFA.documento
                        AND PF.idTipoDocumento = PFA.idTipoDocumento
                        AND PFA.idAcceso = :IdAcceso) ";
            $binding[':IdAcceso'] = $Args['IdAcceso'];
        }
        
        if (!empty($Args['Activos']) && $Args['Activos']){
            $sql .= " AND PF.estado = 1";
        }

        if (!empty($Args['IdCategoria']) && $Args['IdCategoria']) {
            $sql .= " AND P.IdCategoria IN (";
            $i = 0;
            $cantidad = count($Args['IdCategoria']);
            foreach($Args['IdCategoria'] as $idCategoria){
                $i++;
                $binding[':IdCategoria'.$i] = $idCategoria;
                $sql .= ':IdCategoria'.$i;

                if($cantidad != $i){
                    $sql .= ',';
                }
            }
            $sql .= ') ';
        }

        if(!empty($Args['IdEmpresa'])) {
            $IdEmpresaObj = FsUtils::explodeId($Args['IdEmpresa']);
            
            $sql .= " AND PFE.docEmpresa = :IdEmpresaObj
                            AND PFE.tipoDocEmpresa = :IdEmpresaObj1";
            $binding[':IdEmpresaObj'] = $IdEmpresaObj[0];
            $binding[':IdEmpresaObj1'] = $IdEmpresaObj[1];
        }
            
        $sql .= " ORDER BY C.Descripcion, DocumentoMasked";
        
        return DB::select($sql, $binding);

    }

    private function chartporsexo($Args) {
        
        $binding = Array();

        $sql = "SELECT Nombre = CASE WHEN pf.Sexo = 1 THEN 'Masculino' WHEN pf.Sexo IN (0,2) THEN 'Femenino' ELSE 'Sin especificar'  END,
                COUNT(*) AS Cantidad
                FROM PersonasFisicas PF INNER JOIN Personas P ON PF.documento = P.documento AND PF.idTipoDocumento = P.idTipoDocumento
                INNER JOIN Empresas E ON PF.docEmpresa = E.documento AND PF.tipoDocEmpresa = E.idTipoDocumento
                LEFT JOIN PersonasFisicasEmpresas PFE ON PFE.documento = PF.documento AND PFE.idTipoDocumento = PF.idTipoDocumento
                WHERE P.baja = 0
                AND PF.transito = 0";

        if (!empty($Args['NroContrato'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM PersonasFisicasContratos PFC
                        WHERE PF.documento = PFC.documento
                        AND PF.idTipoDocumento = PFC.idTipoDocumento
                        AND PFC.nroContrato = :NroContrato)";
            $binding[':NroContrato'] = $Args['NroContrato'];
        }

        if (!empty($Args['IdAcceso'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM PersonasFisicasAccesos PFA
                        WHERE PF.documento = PFA.documento
                        AND PF.idTipoDocumento = PFA.idTipoDocumento
                        AND PFA.idAcceso = :IdAcceso) ";
            $binding[':IdAcceso'] = $Args['IdAcceso'];
        }

        if (!empty($Args['Activos']) && $Args['Activos']){
            $sql .= " AND PF.estado = 1 AND E.estado = 1";
        }

        if (!empty($Args['IdCategoria']) && $Args['IdCategoria']) {
            $sql .= " AND P.IdCategoria IN (";
            $i = 0;
            $cantidad = count($Args['IdCategoria']);
            foreach($Args['IdCategoria'] as $idCategoria){
                $i++;
                $binding[':IdCategoria'.$i] = $idCategoria;
                $sql .= ':IdCategoria'.$i;

                if($cantidad != $i){
                    $sql .= ',';
                }
            }
            $sql .= ') ';
        }

        if(!empty($Args['IdEmpresa'])) {
            $IdEmpresaObj = FsUtils::explodeId($Args['IdEmpresa']);
            
            $sql .= " AND PFE.docEmpresa = :IdEmpresaObj
                            AND PFE.tipoDocEmpresa = :IdEmpresaObj1";
            $binding[':IdEmpresaObj'] = $IdEmpresaObj[0];
            $binding[':IdEmpresaObj1'] = $IdEmpresaObj[1];
        }
           
        $sql .= " GROUP BY PF.sexo";
        
        //$obj = self::listado($Usuario, $IdEmpresa, $sql);

        $obj = DB::select($sql, $binding);

        $datasets = array();
        $i = 0;

        foreach ($obj as $c) {
                $categoria = array("label" => $c->Nombre, "color" => self::FS_CHART_COLORS[$i], "value" => array($c->Cantidad));

            $datasets[] = $categoria;
            $i++;

            if ($i == count(self::FS_CHART_COLORS))
                $i = 0;
        }
        
        return $datasets;
    }
    
    public function chartporsexodetalle() {

        $Args = $this->req->all();
        $binding = Array();

        $sql = "SELECT Sexo = CASE 
                                WHEN pf.Sexo = 1 THEN 'Masculino' 
                                WHEN pf.Sexo IN (0,2) THEN 'Femenino' 
                                ELSE 'Sin especificar'  
                        END,
                        dbo.Mask(pf.Documento, td.Mascara, 1, 1) AS DocumentoMasked,
                        PF.NombreCompleto AS Detalle,
                        pf.Matricula,
                        CASE pf.Estado
                                WHEN 1 THEN 'Activo'
                                ELSE 'Inactivo'
                        END AS Estado,
                        e.Nombre AS Empresa
                FROM PersonasFisicas PF 
                INNER JOIN Personas P ON PF.documento = P.documento AND PF.idTipoDocumento = P.idTipoDocumento
                INNER JOIN Empresas E ON PF.docEmpresa = E.documento AND PF.tipoDocEmpresa = E.idTipoDocumento
                INNER JOIN TiposDocumento td ON (pf.IdTipoDocumento = td.IdTipoDocumento)
                LEFT JOIN PersonasFisicasEmpresas PFE ON PFE.documento = PF.documento AND PFE.idTipoDocumento = PF.idTipoDocumento
                WHERE P.baja = 0
                AND PF.transito = 0";

        if (!empty($Args['NroContrato'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM PersonasFisicasContratos PFC
                        WHERE PF.documento = PFC.documento
                        AND PF.idTipoDocumento = PFC.idTipoDocumento
                        AND PFC.nroContrato = :NroContrato)";
            $binding[':NroContrato'] = $Args['NroContrato'];
        }
            

        if (!empty($Args['IdAcceso'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM PersonasFisicasAccesos PFA
                        WHERE PF.documento = PFA.documento
                        AND PF.idTipoDocumento = PFA.idTipoDocumento
                        AND PFA.idAcceso = :IdAcceso) ";
            $binding[':IdAcceso'] = $Args['IdAcceso'];
        }
            
        
        if (!empty($Args['Activos']) && $Args['Activos']){
            $sql .= " AND PF.estado = 1 AND E.estado = 1";
        }

        if (!empty($Args['IdCategoria']) && $Args['IdCategoria']) {
            $sql .= " AND P.IdCategoria IN (";
            $i = 0;
            $cantidad = count($Args['IdCategoria']);
            foreach($Args['IdCategoria'] as $idCategoria){
                $i++;
                $binding[':IdCategoria'.$i] = $idCategoria;
                $sql .= ':IdCategoria'.$i;

                if($cantidad != $i){
                    $sql .= ',';
                }
            }
            $sql .= ') ';
        }
        
        if(!empty($Args['IdEmpresa'])) {
            $IdEmpresaObj = FsUtils::explodeId($Args['IdEmpresa']);
            
            $sql .= " AND PFE.docEmpresa = :IdEmpresaObj
                            AND PFE.tipoDocEmpresa = :IdEmpresaObj1";
            $binding[':IdEmpresaObj'] = $IdEmpresaObj[0];
            $binding[':IdEmpresaObj1'] = $IdEmpresaObj[1];
        }

        $sql .= " ORDER BY Sexo";
        
        return DB::select($sql, $binding);
    }

    private function chartporedad($Args) {

        $binding = Array();

        $sql = "SELECT 
                    info.RangoEdad AS Nombre,
                    COUNT(*) AS Cantidad
                FROM (
                    SELECT 
                        PF.Documento,
                        PF.IdTipoDocumento,
                        RangoEdad = CASE 
                            WHEN (CONVERT(int, CONVERT(char(8), GETDATE(), 112)) - CONVERT(char(8), pf.FechaNac, 112)) / 10000 < 30 THEN 'Inferior a 30 años' 
                            WHEN (CONVERT(int, CONVERT(char(8), GETDATE(), 112)) - CONVERT(char(8), pf.FechaNac, 112)) / 10000 BETWEEN 30 AND 40 THEN 'Entre 30 y 40 años' 
                            WHEN (CONVERT(int, CONVERT(char(8), GETDATE(), 112)) - CONVERT(char(8), pf.FechaNac, 112)) / 10000 BETWEEN 41 AND 50 THEN 'Entre 41 y 50 años' 
                            WHEN (CONVERT(int, CONVERT(char(8), GETDATE(), 112)) - CONVERT(char(8), pf.FechaNac, 112)) / 10000 BETWEEN 51 AND 60 THEN 'Entre 51 y 60 años' 
                            WHEN (CONVERT(int, CONVERT(char(8), GETDATE(), 112)) - CONVERT(char(8), pf.FechaNac, 112)) / 10000 BETWEEN 61 AND 70 THEN 'Entre 61 y 70 años' 
                            WHEN (CONVERT(int, CONVERT(char(8), GETDATE(), 112)) - CONVERT(char(8), pf.FechaNac, 112)) / 10000 > 70 THEN 'Superior a 70 años' 
                            ELSE 'Sin especificar'  
                        END
                    FROM PersonasFisicas PF 
                    INNER JOIN Personas P ON PF.documento = P.documento AND PF.idTipoDocumento = P.idTipoDocumento
                    INNER JOIN Empresas E ON PF.docEmpresa = E.documento AND PF.tipoDocEmpresa = E.idTipoDocumento
                    LEFT JOIN PersonasFisicasEmpresas PFE ON PFE.documento = PF.documento AND PFE.idTipoDocumento = PF.idTipoDocumento
                    WHERE P.baja = 0
                    AND PF.transito = 0";

        if (!empty($Args['NroContrato'])){
            $sql .= " AND EXISTS (SELECT *
                    FROM PersonasFisicasContratos PFC
                    WHERE PF.documento = PFC.documento
                    AND PF.idTipoDocumento = PFC.idTipoDocumento
                    AND PFC.nroContrato = :NroContrato)";
            $binding[':NroContrato'] = $Args['NroContrato'];
        }
            

        if (!empty($Args['IdAcceso'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM PersonasFisicasAccesos PFA
                        WHERE PF.documento = PFA.documento
                        AND PF.idTipoDocumento = PFA.idTipoDocumento
                        AND PFA.idAcceso = :IdAcceso) ";
            $binding[':IdAcceso'] = $Args['IdAcceso'];
        }
        
        if (!empty($Args['Activos']) && $Args['Activos']){
            $sql .= " AND PF.estado = 1 AND E.estado = 1";
        }

        if (!empty($Args['IdCategoria']) && $Args['IdCategoria']) {
            $sql .= " AND P.IdCategoria IN (";
            $i = 0;
            $cantidad = count($Args['IdCategoria']);
            foreach($Args['IdCategoria'] as $idCategoria){
                $i++;
                $binding[':IdCategoria'.$i] = $idCategoria;
                $sql .= ':IdCategoria'.$i;

                if($cantidad != $i){
                    $sql .= ',';
                }
            }
            $sql .= ') ';
        }

        if(!empty($Args['IdEmpresa'])) {
            $IdEmpresaObj = FsUtils::explodeId($Args['IdEmpresa']);
            
            $sql .= " AND PFE.docEmpresa = :IdEmpresaObj
                            AND PFE.tipoDocEmpresa = :IdEmpresaObj1";
            $binding[':IdEmpresaObj'] = $IdEmpresaObj[0];
            $binding[':IdEmpresaObj1'] = $IdEmpresaObj[1];
        }

        $sql .= ") info GROUP BY info.RangoEdad";
        
        //$obj = self::listado($Usuario, $IdEmpresa, $sql);
        $obj = DB::select($sql, $binding);

        $datasets = array();
        $i = 0;

        foreach ($obj as $c) {
            $categoria = array("label" => $c->Nombre, "color" => self::FS_CHART_COLORS[$i], "value" => array($c->Cantidad));

            $datasets[] = $categoria;
            $i++;

            if ($i == count(self::FS_CHART_COLORS))
                $i = 0;
        }
        
        return $datasets;
    }

    public function chartporedaddetalle() {

        $Args = $this->req->all();
        $binding = Array();

        $sql = "SELECT  (CONVERT(int, CONVERT(char(8), GETDATE(), 112)) - CONVERT(char(8), pf.FechaNac, 112)) / 10000 AS Edad,
                        dbo.Mask(pf.Documento, td.Mascara, 1, 1) AS DocumentoMasked,
                        PF.NombreCompleto AS Detalle,
                        pf.Matricula,
                        CASE pf.Estado
                            WHEN 1 THEN 'Activo'
                            ELSE 'Inactivo'
                        END AS Estado,
                        e.Nombre AS Empresa
                FROM PersonasFisicas PF 
                INNER JOIN Personas P ON PF.documento = P.documento AND PF.idTipoDocumento = P.idTipoDocumento
                INNER JOIN Empresas E ON PF.docEmpresa = E.documento AND PF.tipoDocEmpresa = E.idTipoDocumento
                INNER JOIN TiposDocumento td ON (pf.IdTipoDocumento = td.IdTipoDocumento)
                WHERE P.baja = 0
                AND PF.transito = 0";

        if (!empty($Args['NroContrato'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM PersonasFisicasContratos PFC
                        WHERE PF.documento = PFC.documento
                        AND PF.idTipoDocumento = PFC.idTipoDocumento
                        AND PFC.nroContrato = :NroContrato)";
            $binding['NroContrato'] = $Args['NroContrato'];
        }
            
        if (!empty($Args['IdAcceso'])){
            $sql .= " AND EXISTS (SELECT *
                        FROM PersonasFisicasAccesos PFA
                        WHERE PF.documento = PFA.documento
                        AND PF.idTipoDocumento = PFA.idTipoDocumento
                        AND PFA.idAcceso = :IdAcceso) ";
            $binding['IdAcceso'] = $Args['IdAcceso'];
        }
            
        if (!empty($Args['Activos']) && $Args['Activos']){
            $sql .= " AND PF.estado = 1 AND E.estado = 1";
        }

        if (!empty($Args['IdCategoria']) && $Args['IdCategoria']) {
            $sql .= " AND P.IdCategoria IN (";
            $i = 0;
            $cantidad = count($Args['IdCategoria']);
            foreach($Args['IdCategoria'] as $idCategoria){
                $i++;
                $binding[':IdCategoria'.$i] = $idCategoria;
                $sql .= ':IdCategoria'.$i;

                if($cantidad != $i){
                    $sql .= ',';
                }
            }
            $sql .= ') ';
        }

        $sql .= " ORDER BY PF.FechaNac";
        
        return DB::select($sql, $binding);
    }

    public function busqueda()
    {
        $Args = $this->req->All();

        $bindings = [];
        
        $sql = "SELECT DISTINCT ObjUrl = CASE pf.Transito
                                    WHEN 0 THEN 'func=AdmPersonas|Documento=' + pf.Documento + '|IdTipoDocumento=' + LTRIM(RTRIM(STR(pf.IdTipoDocumento)))
                                    ELSE 'func=AdmVisitantes|Documento=' + pf.Documento + '|IdTipoDocumento=' + LTRIM(RTRIM(STR(pf.IdTipoDocumento)))
                                END,
                                dbo.Mask(p.Documento, td.Mascara, 1, 1) as Documento,
                                p.IdTipoDocumento,
                                td.Descripcion AS TipoDocumento,
                                c.Descripcion AS Categoria,
                                SexoDesc = CASE WHEN pf.Sexo = 1 THEN 'Masculino' WHEN pf.Sexo IN (0,2) THEN 'Femenino'  END,
                                pf.PrimerNombre,
                                pf.SegundoNombre,
                                pf.PrimerApellido,
                                pf.SegundoApellido,
                                pf.NombreCompleto,
                                pf.Matricula,
                                pf.VigenciaDesde,
                                pf.VigenciaHasta,
                                Estado = CASE WHEN pf.Estado = 1 THEN 'Activo' ELSE 'Inactivo' END,
                                e.Nombre AS Empresa,
                                pfc.NroContrato AS NroContrato,
                                pfe.FechaAlta AS FechaIngreso,
                                STUFF((
                                    SELECT ', ' + cr.Descripcion
                                    FROM PersonasFisicasCargos pfc
                                    INNER JOIN Cargos cr ON cr.IdCargo = pfc.idCargo AND cr.Baja = 0
                                    WHERE pfc.documento = pf.Documento AND pfc.idTipoDocumento = pf.IdTipoDocumento AND pfc.fechaHasta IS NULL
                                    ORDER BY cr.Descripcion
                                    FOR XML PATH ('')), 1, 2, '') AS Cargo,
                                pa.Nombre AS IdPais,
                                d.Nombre AS IdDepartamento,
                                p.Ciudad,
                                p.Localidad,
                                p.Direccion,
                                p.Email,
                                Extranjero = CASE WHEN pf.Extranjero = 1 THEN 'Si' ELSE 'No' END,
                                pan.Nombre AS IdPaisNac,
                                dn.Nombre AS IdDepartamentoNac,
                                pf.FechaNac,
                                (CONVERT(int, CONVERT(char(8), GETDATE(), 112)) - CONVERT(char(8), pf.FechaNac, 112)) / 10000 AS Edad
                    FROM Personas p
                    INNER JOIN PersonasFisicas pf ON p.Documento = pf.Documento AND p.IdTipoDocumento = pf.IdTipoDocumento AND pf.Transito = 0
                    INNER JOIN TiposDocumento td ON p.IdTipoDocumento = td.IdTipoDocumento
                    INNER JOIN Categorias c ON p.IdCategoria = c.IdCategoria
                    LEFT JOIN PersonasFisicasEmpresas pfe ON pfe.Documento = pf.Documento AND pfe.IdTipoDocumento = pf.IdTipoDocumento AND (pfe.FechaBaja IS NULL OR pfe.FechaBaja > GETDATE())
                    LEFT JOIN Empresas e ON e.Documento = pfe.DocEmpresa AND e.IdTipoDocumento = pfe.TipoDocEmpresa
                    LEFT JOIN PersonasFisicasContratos pfc ON pf.Documento = pfc.Documento AND pf.IdTipoDocumento = pfc.IdTipoDocumento AND e.Documento = pfc.DocEmpresa AND e.IdTipoDocumento = pfc.TipoDocEmpresa
                    LEFT JOIN Paises pa ON p.IdPais = pa.IdPais
                    LEFT JOIN Departamentos d ON p.IdPais = d.IdPais AND p.IdDepartamento = d.IdDepartamento
                    LEFT JOIN Paises pan ON pf.IdPaisNac = pan.IdPais
                    LEFT JOIN Departamentos dn ON pf.IdPaisNac = dn.IdPais AND pf.IdDepartamentoNac = dn.IdDepartamento 
                    WHERE 1 = 1 ";

        $bs = 'p.Baja = 0';
        $js = "";
        $ws = "";
        
        if (!empty($Args)) {
            
            $i = 0;
            foreach ($Args as $key => $value) {
                $i++;

                switch ($key) {
                    case 'Baja':
                        if ($value == 'true') //  == 1
                            $bs = 'p.Baja IN (0, 1)';
                        break;
                        
                    case 'OcultarExtranjeros':
                        if ($value == 'true') //  == 1
                            $ws .= ' AND pf.Extranjero != 1';
                        break;

                    case 'FechaNac':
                        $bindings[':FechaNac'] = "CONVERT(date, ' ". $value ." 00:00:00', 103)";
                        $ws .= ' AND FechaNac = :FechaNac';
                        break;

                    default:

                        switch ($key) {
                            case 'output':
                            case 'token':
                            case 'Activos':
                            case 'Matricula':
                            case 'IdEmpresa':
                            case 'IdTipoDocumento':
                            case 'Inactivos':
                            case 'NroContrato':
                            case 'page':
                            case 'pageSize':
                                break;
                            case 'Documento':
                            case 'IdCategoria':
                            case 'IdPais':
                            case 'IdDepartamento':
                                $bindings[':IdDepartamento'] = $value;
                                $key = 'p.' . $key;
                                $ws .= (" AND ") . $key . " = :IdDepartamento";
                                break;
                            case 'IdPaisNac':
                            case 'IdDepartamentoNac':
                                $bindings[':IdDepartamentoNac'] = $value;
                                $key = 'pf.' . $key;
                                $ws .= (" AND ") . $key . " = :IdDepartamentoNac";
                                break;
                            default:
                                $bindings[':value'.$i] = "%" . $value . "%";
                                $ws .= (" AND ") . $key . " LIKE :value". $i;
                                break;
                        }
                        break;
                }
            }
        }

        $sql .= $js . $ws . (" AND ") . $bs;
        
        if (!empty($Args['IdTipoDocumento'])) {
            $sql .= " AND pf.IdTipoDocumento = " . $Args['IdTipoDocumento'];
        }
        
        $estados = array();
        if (!empty($Args['Activos'])) $estados[] = "1";
        if (!empty($Args['Inactivos'])) $estados[] = "0";
        if (count($estados) > 0) $sql .=  " AND pf.Estado IN(" . implode (", ", $estados) . ")";
        
        if (!empty($Args['Matricula'])) {
            $bindings[':Matricula'] = "%" . $Args['Matricula'] . "%";
            $sql .= " AND pf.Matricula LIKE :Matricula";
        }
        
        if (!empty($Args['IdEmpresa'])) {
            $IdEmpresaObj = FsUtils::explodeId($Args['IdEmpresa']);

            $bindings[':IdEmpresaObj0'] = $IdEmpresaObj[0];
            $bindings[':IdEmpresaObj1'] = $IdEmpresaObj[1];
            $sql .= " AND pfe.DocEmpresa = :IdEmpresaObj0 AND pfe.TipoDocEmpresa = IdEmpresaObj1";
        }
        
        if (!empty($Args['NroContrato'])) {
            $sql .= " AND pfc.NroContrato = :NroContrato";
            $bindings[':NroContrato'] = $Args['NroContrato'];
        }
        
        if (!$this->user->isGestion()) {
            $empresa = Empresa::loadBySession($this->req);
            $bindings[':doc_empresa'] = $empresa->Documento;
            $bindings[':tipo_doc_empresa'] = $empresa->IdTipoDocumento;
            $sql .= " AND pfe.DocEmpresa = :doc_empresa AND pfe.TipoDocEmpresa = :tipo_doc_empresa AND pfe.FechaBaja IS NULL";
        }
        
        if (!empty($Args['Documento'])) {
            //. "dbo.Mask(pft.Documento, td.Mascara, 1, 1) COLLATE Latin1_general_CI_AI LIKE '%" . $Args->Busqueda . "%' COLLATE Latin1_general_CI_AI OR "
            //. "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(pft.Documento, '_', ''), '-', ''), ';', ''), ',', ''), ':', ''), '.', '') COLLATE Latin1_general_CI_AI LIKE '%" . $Args->Busqueda . "%' COLLATE Latin1_general_CI_AI OR "            
        }

        $sql .= " order by PrimerNombre, SegundoNombre, PrimerApellido, SegundoApellido";

        $page = (int)$this->req->input('page', 1);
        $items = DB::select($sql, $bindings);

        $output = isset($Args['output']); // $this->req->input('output', 'json');

        if($output !== 'json' && $output == true) {

            $output = $Args['output'];

            $dataOutput = array_map(function($item) {
                return [
                    'Documento' => $item->Documento,
                    'TipoDocumento' => $item->TipoDocumento,
                    'NombreCompleto' => $item->NombreCompleto,
                    'Categoria' => $item->Categoria,
                    'Estado' => $item->Estado,
                    'Empresa' => $item->Empresa,
                    'NroContrato' => $item->NroContrato,
                    'Cargo' => $item->Cargo,
                    'Ingreso' => $item->FechaIngreso,
                    'Matricula' => $item->Matricula,
                    'FechaDesactivacion' => $item->VigenciaHasta,
                    'Sexo' => $item->SexoDesc,
                    'Edad' => $item->Edad,
                    'Pais' => $item->IdPais,
                    'Departamento' => $item->IdDepartamento,
                    'Ciudad' => $item->Ciudad,
                    'Localidad' => $item->Localidad,
                    'Direccion' => $item->Direccion,
                    'Email' => $item->Email,
                ];
            }, $items);

            $filename = 'FSAcceso-Personas-Consulta-' . date('Ymd his');

            $headers = [
                'Documento' => 'Documento',
                'TipoDocumento' => 'TipoDocumento',
                'NombreCompleto' => 'Nombre',
                'Categoria' => 'Categoría',
                'Estado' => 'Estado',
                'Empresa' => 'Empresa',
                'NroContrato' => 'Contrato',
                'Cargo' => 'Cargo',
                'Ingreso' => 'Ingreso',
                'Matricula' => 'Matrícula',
                'FechaDesactivacion' => 'Fecha de desactivación',
                'Sexo' => 'Sexo',
                'Edad' => 'Edad',
                'Pais' => 'País',
                'Departamento' => 'Departamento',
                'Ciudad' => 'Ciudad',
                'Localidad' => 'Localidad',
                'Direccion' => 'Dirección',
                'Email' => 'Correo electrónico',
            ];

            return FsUtils::export($output, $dataOutput, $headers, $filename);
        }

        $paginate = FsUtils::paginateArray($items, $this->req);

        return $this->responsePaginate($paginate->items(), $paginate->total(), $page);
    }

    public function subirFoto(int $idTipoDocumento, string $documento) {

        $transac = null!==$this->showTransac($idTipoDocumento, $documento);

        $table = null;
        if($this->user->Gestion && !$transac){
            $table = 'PersonasFisicas';
        }else{
            $table = 'personasFisicasTransac';
        }
		$PersonaFisicaDoc = DB::select("Select Archivo from ".$table." WHERE Documento = :documento AND IdTipoDocumento = :idTipoDocumento",
            [":documento" => $documento, ":idTipoDocumento" => $idTipoDocumento]);

        if(!empty($PersonaFisicaDoc[0]->Archivo)){
            $pathName = storage_path('app/uploads/personas-fisicas/fotos/'.$PersonaFisicaDoc[0]->Archivo);
            if (file_exists($pathName)) unlink($pathName);
        }
        
        
		$retornoUpdate = false;
        $file = $this->req->file('Archivo-file');
        $filename = 'PersonaFisica-' . $idTipoDocumento . '-' . $documento . '-' . uniqid() . '.' . $file->getClientOriginalExtension();

        $retornoUpdate = DB::update("UPDATE ".$table." SET Archivo = :filenamee WHERE Documento = :documento AND IdTipoDocumento = :idTipoDocumento",
            [":documento" => $documento, ":idTipoDocumento" => $idTipoDocumento, ":filenamee" => $filename]);

        $file->storeAs('uploads/personas-fisicas/fotos', $filename);

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
        $file->storeAs('uploads/personas-fisicas/docs', $Args['filename']);

        return true;
    }

    public function verFoto($fileName){

        $adjunto = storage_path('app/uploads/personas-fisicas/fotos/'.$fileName);

        if (isset($adjunto)) {
            $extension = explode(".",strrev($fileName))[0];
            $extension = strrev($extension);
            header('Content-Type: image/'.$extension);
            echo file_get_contents($adjunto);
        }
    }

    public function verArchivo($carpeta, $fileName){

        $adjunto = storage_path('app/uploads/personas-fisicas/'. $carpeta .'/'.$fileName);

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

    public function cargarLocalidades($value)
    {
        $value = '%' . str_replace('%20', '%', $value) . '%';
        $personas =  DB::select("SELECT DISTINCT Localidad FROM Personas WHERE Localidad LIKE ?", [$value]);
        return array_map(function ($persona) { return $persona->Localidad; }, $personas);
    }

    public function cargarCiudades($value)
    {
        $value = '%' . str_replace('%20', '%', $value) . '%';
        $personas =  DB::select("SELECT DISTINCT Ciudad FROM Personas WHERE Ciudad LIKE ?", [$value]);
        return array_map(function ($persona) { return $persona->Ciudad; }, $personas);
    }

}
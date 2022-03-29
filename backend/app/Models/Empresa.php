<?php

namespace App\Models;
use Illuminate\Support\Facades\DB;
use App\FsUtils;
use App\Traits\Contratado;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class Empresa extends BaseModel
{   
    use Contratado;
    protected $table = 'Empresas';
    protected $primaryKey = ['Documento', 'IdTipoDocumento'];
    protected $fillable = [
        'Nombre',
        'Alias',
    ];

    public $incrementing = false;

    public static $castProperties = [
        'Estado' => 'boolean',
        'IdTipoDocumento' => 'integer',
        'IdCategoria' => 'integer',
        'IdPais' => 'integer',
        'IdDepartamento' => 'integer',
        'IdPaisNac' => 'integer',
        'IdDepartamentoNac' => 'integer',
        'IdEstadoActividad' => 'integer',
        'Sexo' => 'integer',
        'CantTrabajadores' => 'integer',
        'CantTrabajadoresAloj' => 'integer',
        'RealizaVisitas' => 'boolean',
        'RecibeVisitas' => 'boolean',
        'ExcluirR7' => 'boolean',
        'ExcluirR30' => 'boolean',
        'Extranjera' => 'boolean',
        'CategoriaContratistaDisponible' => 'boolean',
        'ProtocoloCovidEstado' => 'integer',
        'Sectores' => [
            'IdTipoDocumento' => 'integer',
            'IdSector' => 'integer',
        ],
        'Documentos' => [
            'IdTipoDocumento' => 'integer',
            'NroDoc' => 'integer',
            'IdTipoDocEmp' => 'integer',
            'Obligatorio' => 'boolean',
            'Vto' => 'date',
            'TieneVto' => 'boolean',
        ],
        'ContrataA' => [
            'VigenciaDesde' => 'date',
            'VigenciaHasta' => 'date',
        ],
        'ContratadaPor' => [
            'VigenciaDesdeCont' => 'date',
            'VigenciaHastaCont' => 'date',
        ],
    ];

    public function getName(): string
    {
        return sprintf('%s (%s)', $this->Nombre, implode('-', [$this->Documento, $this->IdTipoDocumento]));
    }

    public function getKeyAlt(): string
    {
        return implode('-', [$this->Documento, $this->IdTipoDocumento]);
    }

    /**
     * Cargar empresas para una persona
     * @param string $documento
     * @param int $idTipoDocumento
     * @return array
     */
    public static function loadByPersonaFisica($documento, $idTipoDocumento, string $modifier = ''): array
    {
        $sql = "SELECT pfe.DocEmpresa, pfe.TipoDocEmpresa, pfe.DocEmpresa + '-' + LTRIM(RTRIM(STR(pfe.TipoDocEmpresa))) AS IdEmpresa, e.Nombre AS Empresa, pfe.FechaAlta, pfe.FechaBaja, pfe.Observaciones
            FROM PersonasFisicas" . $modifier . "Empresas pfe
            INNER JOIN Empresas e ON (pfe.DocEmpresa = e.Documento AND pfe.TipoDocEmpresa = e.IdTipoDocumento)
            WHERE pfe.Documento = ? AND pfe.IdTipoDocumento = ? ORDER BY pfe.FechaBaja DESC";
        return DB::select($sql, [$documento, $idTipoDocumento]);
    }
    
    /**
     * Cargar empresas que contrata
     * @param string $documento
     * @param int $idTipoDocumento
     * @return array
     */
    public static function loadContrataA($documento, $idTipoDocumento): array
    {
        $binding = [
            ':documento' => $documento,
            ':id_tipo_documento' => $idTipoDocumento,
        ];

        $sql = "SELECT ec.DocEmpCont + '-' + LTRIM(RTRIM(STR(ec.IdTipoDocCont))) AS IdEmpresa,
            e.Nombre AS NombreEmpresa,
            ec.NroContrato,
            ec.FechaDesde AS VigenciaDesde,
            ec.FechaHasta AS VigenciaHasta,
            EstadoContrato = CASE WHEN ec.FechaHasta <= GETDATE() THEN 'Vencido' ELSE 'Vigente' END
        FROM Empresas e
        INNER JOIN EmpresasContratos ec ON e.Documento = ec.DocEmpCont AND e.IdTipoDocumento = ec.IdTipoDocCont
        WHERE ec.Documento = :documento AND ec.IdTipoDocumento = :id_tipo_documento";

        return DB::select($sql, $binding);
    }

    /**
     * Cargar empresas por las que es contratada
     * @param string $documento
     * @param int $idTipoDocumento
     * @return array
     */
    public static function loadContratadaPor($documento, $idTipoDocumento): array
    {
        $binding = [
            ':documento' => $documento,
            ':id_tipo_documento' => $idTipoDocumento,
        ];

        $sql = "SELECT ec.Documento + '-' + LTRIM(RTRIM(STR(ec.IdTipoDocumento))) AS IdEmpresaCont,
                       e.Nombre AS NombreEmpresaCont,
                       ec.NroContrato As NroContratoCont,
                       ec.FechaDesde As VigenciaDesdeCont,
                       ec.FechaHasta As VigenciaHastaCont,
                       EstadoContrato = CASE WHEN ec.FechaHasta <= GETDATE() THEN 'Vencido' ELSE 'Vigente' END
                FROM EmpresasContratos ec
                INNER JOIN Empresas e ON e.Documento = ec.Documento AND e.IdTipoDocumento = ec.IdTipoDocumento
                WHERE ec.DocEmpCont = :documento AND ec.IdTipoDocCont = :id_tipo_documento";

        return DB::select($sql, $binding);
    }

    public static function EmpresasAlojamientos($documento, $idTipoDocumento): array
    {
        $binding = [
            ':documento' => $documento,
            ':id_tipo_documento' => $idTipoDocumento,
        ];
         
        $sql = "SELECT  a.nombre as NombreAlojamiento, ta.nombre as NombreTipoAlojamiento, ea.IdEmpresasAlojamiento, 
                        ea.Documento, ea.IdTipoDocumento, ea.IdAlojamiento, ea.IdTipoAlojamiento, 
                        CONVERT(VARCHAR(10), ea.FechaDesde, 103) as FechaDesde, CONVERT(VARCHAR(10), ea.FechaHasta, 103) as FechaHasta,
                        CASE WHEN ea.Direccion IS NOT NULL THEN ea.Direccion ELSE a.Direccion END AS Direccion, 
                        CASE WHEN ea.Localidad IS NOT NULL THEN ea.Localidad ELSE a.Localidad END AS Localidad, 
                        CASE WHEN ea.TipoReserva = 1 THEN 11 ELSE 10 END AS TipoReserva, a.RequiereUnidad,
                        ea.CantidadPersonas, ea.Ubicacion, ta.Casa as TipoAlojamientoEsCasa
                FROM EmpresasAlojamientos ea
                INNER JOIN Empresas e ON e.Documento = ea.Documento AND e.IdTipoDocumento = ea.IdTipoDocumento
                INNER JOIN TiposAlojamientos ta ON ea.IdTipoAlojamiento = ta.IdTipoAlojamiento
                LEFT JOIN Alojamientos a ON ea.IdAlojamiento = a.IdAlojamiento
                WHERE ea.Documento = :documento AND ea.IdTipoDocumento = :id_tipo_documento";

        return DB::select($sql, $binding);
    }

    public static function EmpresasTransportes($documento, $idTipoDocumento): array
    {
        $binding = [
            ':documento' => $documento,
            ':id_tipo_documento' => $idTipoDocumento,
        ];

        $sql = "SELECT  et.IdEmpresaTransporte, et.Documento, et.IdTipoDocumento, et.Serie, et.Numero, et.DocumentoChofer1, 
                        et.IdTipoDocumentoChofer1, et.DocumentoChofer2, et.IdTipoDocumentoChofer2, et.DocumentoChofer1, 
                        CASE WHEN et.TipoReserva = 1 THEN 11 ELSE 10 END AS TipoReserva,
                        LTRIM(RTRIM(v.Serie))+v.Numero+ ' - '+emp.Nombre+' ('+mv.descripcion+' - '+v.Modelo+')' AS DatosVehiculo,
                        pfc1.NombreCompleto+' ('+pfc1.Documento+')' AS NombreCompletoChofer1, pfc2.NombreCompleto+' ('+pfc2.Documento+')' AS NombreCompletoChofer2
                FROM EmpresasTransportes et
                INNER JOIN PersonasFisicas pfc1 ON pfc1.Documento = et.DocumentoChofer1 and pfc1.IdTipoDocumento = et.IdTipoDocumentoChofer1
                LEFT JOIN PersonasFisicas pfc2 ON pfc2.Documento = et.DocumentoChofer2 and pfc2.IdTipoDocumento = et.IdTipoDocumentoChofer2
                INNER JOIN Vehiculos v ON et.Serie = v.Serie AND et.Numero = v.Numero
                LEFT JOIN Empresas emp ON v.DocEmpresa = emp.Documento AND v.TipoDocEmp = emp.IdTipoDocumento
                INNER JOIN MarcasVehiculos mv ON v.IdMarcaVehic = mv.IdMarcaVehic
                WHERE et.Documento = :documento AND et.IdTipoDocumento = :id_tipo_documento";

        return DB::select($sql, $binding);
    }
    
    public static function EmpresasContactos($documento, $idTipoDocumento): array
    {
        $binding = [
            ':documento' => $documento,
            ':id_tipo_documento' => $idTipoDocumento,
        ];

        $sql = "SELECT  tc.Nombre as NombreTipoContacto, ec.Documento, ec.IdTipoDocumento, 
                        ec.IdTipoContacto, ec.Nombre, ec.Celular, ec.Email
                FROM EmpresasContactos ec
                INNER JOIN Empresas e ON e.Documento = ec.Documento AND e.IdTipoDocumento = ec.IdTipoDocumento
                INNER JOIN TiposContacto tc ON ec.IdTipoContacto = tc.IdTipoContacto
                WHERE ec.Documento = :documento AND ec.IdTipoDocumento = :id_tipo_documento";

        return DB::select($sql, $binding);
    }

    public static function createByPersonaFisica(object $Args, bool $reset = false, string $modifier = ''): void
    {
        $statements = [];

        if (!empty($Args->Empresas)) {

            $empresas = [
                'DocEmpresa' => [],
                'TipoDocEmpresa' => [],
                'FechaAlta' => []
            ];

            foreach ($Args->Empresas as $empresa) {
                $empresa = (object)$empresa;
                $IdEmpresaObj = FsUtils::explodeId($empresa->IdEmpresa);

                $e = $reset && DB::selectOne("SELECT COUNT(*) AS Cantidad "
                    . "FROM PersonasFisicas" . $modifier . "Empresas "
                    . "WHERE Documento = ? "
                    . "AND IdTipoDocumento = ? "
                    . "AND DocEmpresa = ? "
                    . "AND TipoDocEmpresa = ? "
                    . "AND CONVERT(date, FechaAlta, 103) = CONVERT(date, ?, 103)",
                [
                    $Args->Documento,
                    $Args->IdTipoDocumento,
                    $IdEmpresaObj[0],
                    $IdEmpresaObj[1],
                    $empresa->FechaAlta,
                ])->Cantidad > 0;

                if ($e) {
                    $statements[] = [
                        "UPDATE PersonasFisicas" . $modifier . "Empresas SET "
                            . "FechaAlta = CONVERT(date, ?, 103), "
                            . "FechaBaja = CONVERT(date, ?, 103), "
                            . "Observaciones = ? "
                            . "WHERE Documento = ? "
                            . "AND IdTipoDocumento = ? "
                            . "AND DocEmpresa = ? "
                            . "AND TipoDocEmpresa = ? "
                            . "AND CONVERT(date, FechaAlta, 103) = CONVERT(date, ?, 103)",
                        [
                            $empresa->FechaAlta,
                            @$empresa->FechaBaja,
                            @$empresa->Observaciones,
                            $Args->Documento,
                            $Args->IdTipoDocumento,
                            $IdEmpresaObj[0],
                            $IdEmpresaObj[1],
                            $empresa->FechaAlta,
                        ]
                    ];
                } else {
                    $statements[] = [
                        "INSERT INTO PersonasFisicas" . $modifier . "Empresas "
                            . "(Documento, IdTipoDocumento, DocEmpresa, TipoDocEmpresa, FechaAlta, FechaBaja, Observaciones) "
                            . "VALUES (?, ?, ?, ?, CONVERT(date, ?, 103), CONVERT(date, ?, 103), ?)",
                        [
                            $Args->Documento, $Args->IdTipoDocumento, $IdEmpresaObj[0],
                            $IdEmpresaObj[1], $empresa->FechaAlta, @$empresa->FechaBaja,
                            @$empresa->Observaciones,
                        ]
                    ];
                }

                if (!empty($empresa->Contratos)) {
                    $contratos = [
                        'DocEmpresa' => [],
                        'TipoDocEmpresa' => [],
                        'DocEmpContratista' => [],
                        'TipoDocEmpContratista' => [],
                        'NroContrato' => [],
                    ];

                    foreach ($empresa->Contratos as $contrato) {
                        $contrato = (object)$contrato;
                        if (!empty($contrato->IdEmpContratista) && !empty($contrato->NroContrato)) {

                            $IdEmpContratistaObj = FsUtils::explodeId($contrato->IdEmpContratista);

                            if (!empty($empresa->FechaBaja) && empty($contrato->FechaBajaContrato)) {
                                $contrato->FechaBajaContrato = $empresa->FechaBaja;
                            }

                            $ec = $reset && DB::selectOne("SELECT COUNT(*) AS Cantidad "
                                    . "FROM PersonasFisicas" . $modifier . "Contratos "
                                    . "WHERE Documento = ? "
                                    . "AND IdTipoDocumento = ? "
                                    . "AND DocEmpresa = ? "
                                    . "AND TipoDocEmpresa = ? "
                                    . "AND DocEmpCont = ? "
                                    . "AND IdTipoDocCont = ? "
                                    . "AND NroContrato = ?",
                                [
                                    $Args->Documento,
                                    $Args->IdTipoDocumento,
                                    $IdEmpresaObj[0],
                                    $IdEmpresaObj[1],
                                    $IdEmpContratistaObj[0],
                                    $IdEmpContratistaObj[1],
                                    $contrato->NroContrato,
                                ])->Cantidad > 0;

                            if (
                                !in_array("'" . $IdEmpresaObj[0] . "'", $contratos["DocEmpresa"]) ||
                                !in_array($IdEmpresaObj[1], $contratos["TipoDocEmpresa"]) ||
                                !in_array("'" . $IdEmpContratistaObj[0] . "'", $contratos["DocEmpContratista"]) ||
                                !in_array($IdEmpContratistaObj[1], $contratos["TipoDocEmpContratista"]) ||
                                !in_array("'" . $contrato->NroContrato . "'", $contratos["NroContrato"])
                            ) {

                                if (
                                    empty($contrato->FechaBajaContrato) ||
                                    FsUtils::strToDateByPattern($contrato->FechaBajaContrato) > new Carbon
                                ) {
                                    if ($ec) {
                                        $statements[] = [
                                            "UPDATE PersonasFisicas" . $modifier . "Contratos SET "
                                                . "FechaAlta = CONVERT(date, ?, 103) "
                                                . "WHERE Documento = ? "
                                                . "AND IdTipoDocumento = ? "
                                                . "AND DocEmpresa = ? "
                                                . "AND TipoDocEmpresa = ? "
                                                . "AND DocEmpCont = ? "
                                                . "AND IdTipoDocCont = ? "
                                                . "AND NroContrato = ?",
                                            [
                                                $contrato->FechaAltaContrato,
                                                $Args->Documento,
                                                $Args->IdTipoDocumento,
                                                $IdEmpresaObj[0],
                                                $IdEmpresaObj[1],
                                                $IdEmpContratistaObj[0],
                                                $IdEmpContratistaObj[1],
                                                $contrato->NroContrato,
                                            ]
                                        ];
                                    } else {
                                        $statements[] = [
                                            "INSERT INTO PersonasFisicas" . $modifier . "Contratos "
                                                . "(Documento, IdTipoDocumento, DocEmpresa, TipoDocEmpresa, DocEmpCont, IdTipoDocCont, NroContrato, FechaAlta, IdUsuarioAlta) "
                                                . "VALUES (?, ?, ?, ?, ?, ?, ?, CONVERT(date, ?, 103), ?)",
                                            [
                                                $Args->Documento,
                                                $Args->IdTipoDocumento,
                                                $IdEmpresaObj[0],
                                                $IdEmpresaObj[1],
                                                $IdEmpContratistaObj[0],
                                                $IdEmpContratistaObj[1],
                                                $contrato->NroContrato,
                                                $contrato->FechaAltaContrato,
                                                Auth::id(),
                                            ]
                                        ];

                                        if (empty($modifier)) {
                                            $statements[] = [
                                                "INSERT INTO PersonasFisicas" . $modifier . "ContratosAltas "
                                                    . "(Documento, IdTipoDocumento, Matricula, DocEmpresa, TipoDocEmpresa, DocEmpCont, IdTipoDocCont, NroContrato, FechaAlta, FechaHoraAlta, IdUsuarioAlta) "
                                                    . "VALUES (?, ?, ?, ?, ?, ?, ?, ?, CONVERT(date, ?, 103), GETDATE(), ?)",
                                                [
                                                    $Args->Documento,
                                                    $Args->IdTipoDocumento,
                                                    $Args->Matricula,
                                                    $IdEmpresaObj[0],
                                                    $IdEmpresaObj[1],
                                                    $IdEmpContratistaObj[0],
                                                    $IdEmpContratistaObj[1],
                                                    $contrato->NroContrato,
                                                    $contrato->FechaAltaContrato,
                                                    Auth::id(),
                                                ]
                                            ];
                                        }
                                    }
                                } else {
                                    $statements[] = [
                                        "DELETE FROM PersonasFisicas" . $modifier . "Contratos "
                                            . "WHERE Documento = ? "
                                            . "AND IdTipoDocumento = ? "
                                            . "AND DocEmpresa = ? "
                                            . "AND TipoDocEmpresa = ? "
                                            . "AND DocEmpCont = ? "
                                            . "AND IdTipoDocCont = ? "
                                            . "AND NroContrato = ?",
                                        [
                                            $Args->Documento,
                                            $Args->IdTipoDocumento,
                                            $IdEmpresaObj[0],
                                            $IdEmpresaObj[1],
                                            $IdEmpContratistaObj[0],
                                            $IdEmpContratistaObj[1],
                                            $contrato->NroContrato,
                                        ]
                                    ];

                                    if (empty($modifier)) {
                                        $statements[] = [
                                            "INSERT INTO PersonasFisicas" . $modifier . "ContratosBajas "
                                                . "(Documento, IdTipoDocumento, Matricula, DocEmpresa, TipoDocEmpresa, DocEmpCont, IdTipoDocCont, NroContrato, FechaBaja, FechaHoraBaja, IdUsuarioBaja) "
                                                . "VALUES (?, ?, ?, ?, ?, ?, ?, ?, CONVERT(date, ?, 103), GETDATE(), ?)",
                                            [
                                                $Args->Documento,
                                                $Args->IdTipoDocumento,
                                                $Args->Matricula,
                                                $IdEmpresaObj[0],
                                                $IdEmpresaObj[1],
                                                $IdEmpContratistaObj[0],
                                                $IdEmpContratistaObj[1],
                                                $contrato->NroContrato,
                                                $contrato->FechaBajaContrato,
                                                Auth::id(),
                                            ]
                                        ];
                                    }
                                }

                                $contratos["DocEmpresa"][] = $IdEmpresaObj[0];
                                $contratos["TipoDocEmpresa"][] = $IdEmpresaObj[1];
                                $contratos["DocEmpContratista"][] = $IdEmpContratistaObj[0];
                                $contratos["TipoDocEmpContratista"][] = $IdEmpContratistaObj[1];
                                $contratos["NroContrato"][] = $contrato->NroContrato;
                            }
                            sleep(1);
                        }
                    }

                    if (!empty($contratos["DocEmpresa"])) {
                        $added = "";
                        $contratos_bindings = [$Args->Documento, $Args->IdTipoDocumento];
                        for ($i = 0; $i < count($contratos["DocEmpresa"]); $i++) {
                            $contratos_bindings[] = $contratos["DocEmpresa"][$i];
                            $contratos_bindings[] = $contratos["TipoDocEmpresa"][$i];
                            $contratos_bindings[] = $contratos["DocEmpContratista"][$i];
                            $contratos_bindings[] = $contratos["TipoDocEmpContratista"][$i];
                            $contratos_bindings[] = $contratos["NroContrato"][$i];
                            $added .= empty($added) ? "" : " OR ";
                            $added .= "(DocEmpresa = ? AND TipoDocEmpresa = ? AND DocEmpCont = ? AND IdTipoDocCont = ? AND NroContrato = ?)";
                        }

                        $todelete = DB::select("SELECT * "
                            . "FROM PersonasFisicas" . $modifier . "Contratos "
                            . "WHERE Documento = ? "
                            . "AND IdTipoDocumento = ? "
                            . "AND NOT (" . $added . ")", $contratos_bindings);

                        foreach ($todelete as $d) {
                            array_unshift($statements, [
                                "DELETE "
                                    . "FROM PersonasFisicas" . $modifier . "Contratos "
                                    . "WHERE Documento = ? "
                                    . "AND IdTipoDocumento = ? "
                                    . "AND DocEmpresa = ? "
                                    . "AND TipoDocEmpresa = ? "
                                    . "AND DocEmpCont = ? "
                                    . "AND IdTipoDocCont = ? "
                                    . "AND NroContrato = ?",
                                [
                                    $Args->Documento,
                                    $Args->IdTipoDocumento,
                                    $d->DocEmpresa,
                                    $d->TipoDocEmpresa,
                                    $d->DocEmpCont,
                                    $d->IdTipoDocCont,
                                    $d->NroContrato,
                                ]
                            ]);
                        }
                    }
                } else {
                    if (empty($empresa->FechaBaja) || FsUtils::strToDateByPattern($empresa->FechaBaja) > new Carbon) {
                        $statements[] = [
                            "DELETE FROM PersonasFisicas" . $modifier . "Contratos "
                                . "WHERE Documento = ? "
                                . "AND IdTipoDocumento = ? "
                                . "AND DocEmpresa = ? "
                                . "AND TipoDocEmpresa = ?",
                            [
                                $Args->Documento,
                                $Args->IdTipoDocumento,
                                $IdEmpresaObj[0],
                                $IdEmpresaObj[1],
                            ]
                        ];
                    }
                }

                $empresas["DocEmpresa"][] = $IdEmpresaObj[0]; // "'" . $IdEmpresaObj[0] . "'";
                $empresas["TipoDocEmpresa"][] = $IdEmpresaObj[1];
                $empresas["FechaAlta"][] = $empresa->FechaAlta;
                $empresas["FechaBaja"][] = @$empresa->FechaBaja;
            }

            if (!empty($empresas["DocEmpresa"])) {
                $empresas_bindings = [$Args->Documento, $Args->IdTipoDocumento];
                $added = "";
                for ($i = 0; $i < count($empresas["DocEmpresa"]); $i++) {
                    $added .= empty($added) ? "" : " OR ";
                    $added .= "(DocEmpresa = ? AND TipoDocEmpresa = ? AND FechaAlta = CONVERT(datetime, ?, 103)";
                    $empresas_bindings[] = $empresas["DocEmpresa"][$i];
                    $empresas_bindings[] = $empresas["TipoDocEmpresa"][$i];
                    $empresas_bindings[] = $empresas["FechaAlta"][$i];
                    if (!empty($empresas["FechaBaja"][$i])) {
                        $empresas_bindings[] = $empresas["FechaBaja"][$i];
                        $added .= " AND FechaBaja = CONVERT(datetime, ?, 103)";
                    }
                    $added .= ")";
                }

                $todelete = DB::select("SELECT *, CONVERT(VARCHAR(10), FechaAlta, 103) AS FechaAlta, CONVERT(VARCHAR(10), FechaBaja, 103) AS FechaBaja "
                    . "FROM PersonasFisicas" . $modifier . "Empresas "
                    . "WHERE Documento = ? "
                    . "AND IdTipoDocumento = ? "
                    . "AND NOT(" . $added . ")", $empresas_bindings);

                foreach ($todelete as $d) {
                    $empresas_bindings = [
                        $Args->Documento,
                        $Args->IdTipoDocumento,
                        $d->DocEmpresa,
                        $d->TipoDocEmpresa,
                        $d->FechaAlta,
                    ];
                    $b = '';
                    if (!empty($d->FechaBaja)) {
                        $b = "AND FechaBaja = CONVERT(datetime, ?, 103) ";
                        $empresas_bindings[] = $d->FechaBaja;
                    }
                    array_unshift($statements, [
                        "DELETE "
                            . "FROM PersonasFisicas" . $modifier . "Empresas "
                            . "WHERE Documento = ? "
                            . "AND IdTipoDocumento = ? "
                            . "AND DocEmpresa = ? "
                            . "AND TipoDocEmpresa = ? "
                            . "AND FechaAlta = CONVERT(datetime, ?, 103) "
                            . $b,
                        $empresas_bindings
                    ]);
                }
            }
        } else {
            if ($modifier === 'Transac') {
                throw new ConflictHttpException('Debe agregar al menos una empresa');
            }
            $statements[] = ["DELETE FROM PersonasFisicas" . $modifier . "Contratos WHERE Documento = ? AND IdTipoDocumento = ?", [$Args->Documento, $Args->IdTipoDocumento]];
            $statements[] = ["DELETE FROM PersonasFisicas" . $modifier . "Empresas WHERE Documento = ? AND IdTipoDocumento = ?", [$Args->Documento, $Args->IdTipoDocumento]];
        }

        foreach ($statements as $statement) {
            list($sql, $binding) = $statement;
            if (!DB::statement($sql, $binding)) {
                throw new \Exception(sprintf('Ocurrió un error al ejecutar la sentencia %s con los siguientes datos (%s)', $sql, implode(', ', $binding)));
            }
        }
    }

    public static function createByMaquina(object $Args, bool $reset = false, string $modifier = '') {
        
        $statements = [];

        if (!empty($Args->Contratos))
        {
            $IdEmpresaObj = FsUtils::explodeId($Args->IdEmpresa);
            $contratos = [
                'DocEmpresa' => [],
                'TipoDocEmpresa' => [],
                'DocEmpContratista' => [],
                'TipoDocEmpContratista' => [],
                'NroContrato' => [],
            ];

            foreach ($Args->Contratos as $contrato) {
                $contrato = (object)$contrato;
                $IdEmpContratistaObj = FsUtils::explodeId($contrato->IdEmpContratista);

                $ec = $reset && DB::selectOne("SELECT COUNT(*) AS Cantidad "
						. "FROM Maquinas" . $modifier . "Contratos "
						. "WHERE NroSerie = ? "
							. "AND DocEmpresa = ? "
							. "AND TipoDocEmpresa = ? "
							. "AND DocEmpCont = ? "
							. "AND IdTipoDocCont = ? "
							. "AND NroContrato = ?",
						[
							$Args->NroSerie,
							$IdEmpresaObj[0],
							$IdEmpresaObj[1],
							$IdEmpContratistaObj[0],
							$IdEmpContratistaObj[1],
							$contrato->NroContrato,
						])->Cantidad > 0;

				if (
					!in_array("'" . $IdEmpresaObj[0] . "'", $contratos["DocEmpresa"]) ||
					!in_array($IdEmpresaObj[1], $contratos["TipoDocEmpresa"]) ||
					!in_array("'" . $IdEmpContratistaObj[0] . "'", $contratos["DocEmpContratista"]) ||
					!in_array($IdEmpContratistaObj[1], $contratos["TipoDocEmpContratista"]) ||
					!in_array("'" . $contrato->NroContrato . "'", $contratos["NroContrato"])
				) {
                    if ($ec) {
                        if (
                            empty($contrato->FechaBajaContrato) ||
                            FsUtils::strToDateByPattern($contrato->FechaBajaContrato) > new Carbon
                        ) {
							$statements[] = [
								"UPDATE Maquinas" . $modifier . "Contratos SET "
								. "FechaAlta = CONVERT(date, ?, 103) "
								. "WHERE NroSerie = ? "
								. "AND DocEmpresa = ? "
								. "AND TipoDocEmpresa = ? "
								. "AND DocEmpCont = ? "
								. "AND IdTipoDocCont = ? "
								. "AND NroContrato = ? ",
								[
									$contrato->FechaAltaContrato,
									$Args->NroSerie,
									$IdEmpresaObj[0],
									$IdEmpresaObj[1],
									$IdEmpContratistaObj[0],
									$IdEmpContratistaObj[1],
									$contrato->NroContrato,
								]
							];
						} else {
							$statements[] = [
								"DELETE FROM Maquinas" . $modifier . "Contratos "
								. "WHERE NroSerie = ? "
								. "AND DocEmpresa = ? "
								. "AND TipoDocEmpresa = ? "
								. "AND DocEmpCont = ? "
								. "AND IdTipoDocCont = ? "
								. "AND NroContrato = ? ",
								[
									$Args->NroSerie,
									$IdEmpresaObj[0],
									$IdEmpresaObj[1],
									$IdEmpContratistaObj[0],
									$IdEmpContratistaObj[1],
									$contrato->NroContrato,
								]
							];

							$statements[] = [
								"INSERT INTO Maquinas" . $modifier . "ContratosBajas "
								. "(NroSerie,
									Matricula,
									DocEmpresa,
									TipoDocEmpresa,
									DocEmpCont,
									IdTipoDocCont,
									NroContrato,
									FechaBaja,
									FechaHoraBaja,
									IdUsuarioBaja) "
									. "VALUES (?, ?, ?, ?, ?, ?, ?, CONVERT(DATETIME, ?, 103), GETDATE(), ?)",
								[
									$Args->NroSerie,
									$Args->Matricula,
									$IdEmpresaObj[0],
									$IdEmpresaObj[1],
									$IdEmpContratistaObj[0],
									$IdEmpContratistaObj[1],
									$contrato->NroContrato,
									@$contrato->FechaBajaContrato,
									Auth::id(),
								]
							];
						}
					} else {
						$statements[] = [
							"INSERT INTO Maquinas" . $modifier . "Contratos"
							.	"(NroSerie,
								DocEmpresa,
								TipoDocEmpresa,
								DocEmpCont,
								IdTipoDocCont,
								NroContrato,
								FechaAlta,
								IdUsuarioAlta) "
							. "VALUES (?, ?, ?, ?, ?, ?, CONVERT(DATETIME, ?, 103), ?)",
							[
								$Args->NroSerie,
								$IdEmpresaObj[0],
								$IdEmpresaObj[1],
								$IdEmpContratistaObj[0],
								$IdEmpContratistaObj[1],
								$contrato->NroContrato,
								$contrato->FechaAltaContrato,
								Auth::id(),
							]
						];

						$sql[] =  [
							"INSERT INTO Maquinas" . $modifier . "ContratosAltas"
							.	"(NroSerie,
								Matricula,
								DocEmpresa,
								TipoDocEmpresa,
								DocEmpCont,
								IdTipoDocCont,
								NroContrato,
								FechaAlta,
								FechaHoraAlta,
								IdUsuarioAlta) "
							. "VALUES (?, ?, ?, ?, ?, ?, ?, CONVERT(DATETIME, ?, 103), GETDATE(), ?)",
							[
								$Args->NroSerie,
								$Args->Matricula,
								$IdEmpresaObj[0],
								$IdEmpresaObj[1],
								$IdEmpContratistaObj[0],
								$IdEmpContratistaObj[1],
								$contrato->NroContrato,
								$contrato->FechaAltaContrato,
								Auth::id(),
							]
						];
					}

					$contratos["DocEmpresa"][] = $IdEmpresaObj[0];
					$contratos["TipoDocEmpresa"][] = $IdEmpresaObj[1];
					$contratos["DocEmpContratista"][] = $IdEmpContratistaObj[0];
					$contratos["TipoDocEmpContratista"][] = $IdEmpContratistaObj[1];
					$contratos["NroContrato"][] = $contrato->NroContrato;
				}
				sleep(1);
            }

            if (!empty($contratos["DocEmpresa"])) {
                $added = "";
                $contratos_bindings = [$Args->NroSerie];
                for ($i = 0; $i < count($contratos["DocEmpresa"]); $i++) {
                    $contratos_bindings[] = $contratos["DocEmpresa"][$i];
                    $contratos_bindings[] = $contratos["TipoDocEmpresa"][$i];
                    $contratos_bindings[] = $contratos["DocEmpContratista"][$i];
                    $contratos_bindings[] = $contratos["TipoDocEmpContratista"][$i];
                    $contratos_bindings[] = $contratos["NroContrato"][$i];

                    $added .= empty($added) ? "" : " OR ";
                    $added .= "(DocEmpresa = ? AND TipoDocEmpresa = ? AND DocEmpCont = ? AND IdTipoDocCont = ? AND NroContrato = ?)";
                }
                
                $todelete = DB::select("SELECT * "
                . "FROM Maquinas" . $modifier . "Contratos "
                . "WHERE NroSerie = ? "
                . "AND NOT (" . $added . ")", $contratos_bindings);
                
                foreach ($todelete as $d) {
                    array_unshift($statements, [
                        "DELETE "
                            . "FROM Maquinas" . $modifier . "Contratos "
                            . "WHERE NroSerie = ? "
                            . "AND DocEmpresa = ? "
                            . "AND TipoDocEmpresa = ? "
                            . "AND DocEmpCont = ? "
                            . "AND IdTipoDocCont = ? "
                            . "AND NroContrato = ? ",
                        [
                            $Args->NroSerie,
                            $d->DocEmpresa,
                            $d->TipoDocEmpresa,
                            $d->DocEmpCont,
                            $d->IdTipoDocCont,
                            $d->NroContrato
                        ]
                    ]);
                }
            }
        }
		else {
            $statements[] = ["DELETE FROM Maquinas" . $modifier . "Contratos WHERE NroSerie = ?", [$Args->NroSerie]];
        }

        foreach ($statements as $statement) {
            list($sql, $binding) = $statement;
            if (!DB::statement($sql, $binding)) {
                throw new \Exception(sprintf('Ocurrió un error al ejecutar la sentencia %s con los siguientes datos (%s)', $sql, implode(', ', $binding)));
            }
        }
	}

    public static function loadBySession(\Illuminate\Http\Request $request): ?self
    {
        $idEmpresa = $request->headers->get('X-FS-EMPRESA');
        if (!isset($idEmpresa)) {
            $idEmpresa = $request->input('idEmpresa');
        }

        $token = $request->headers->get('X-FS-TOKEN');
        if (!isset($token)) {
            $token = $request->input('token');
        }
        
        if (!isset($idEmpresa)) {
            return null;
        }
        
        $idEmpresa = FsUtils::explodeId($idEmpresa);

        $idUsuario = DB::selectOne('SELECT IdUsuario FROM UsuariosSesiones WHERE Token LIKE ?', [$token])->IdUsuario;

        $check = DB::select('SELECT * FROM UsuariosEmpresas WHERE Documento = ? AND IdTipoDocumento = ? AND IdUsuario = ?', [$idEmpresa[0], $idEmpresa[1], $idUsuario]);

        if (count($check) === 0) {
            throw new UnauthorizedHttpException('No tiene permitido actuar como la empresa seleccionada');
        }

        $empresa = Empresa::where('Documento', $idEmpresa[0])->where('IdTipoDocumento', $idEmpresa[1])->first();
        
        if (!isset($empresa)) {
            throw new UnauthorizedHttpException('No tiene permitido actuar como la empresa seleccionada');
        }

        return $empresa;
    }
}

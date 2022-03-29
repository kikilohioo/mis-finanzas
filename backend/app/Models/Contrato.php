<?php

namespace App\Models;

use App\FsUtils;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Contrato
{
    public static function loadByMaquina($nroSerie, $modifier = ''): array
    {
        $sql = "SELECT  mc.DocEmpCont + '-' + LTRIM(RTRIM(STR(mc.IdTipoDocCont))) AS IdEmpContratista,
                        mc.DocEmpCont + '-' + LTRIM(RTRIM(STR(mc.IdTipoDocCont))) + '-' + mc.NroContrato AS IdContrato,
                        e.Nombre AS Contratista,
                        mc.NroContrato AS NroContrato,
                        mc.FechaAlta AS FechaAltaContrato
                FROM Maquinas" . $modifier . "Contratos mc
                INNER JOIN Empresas e ON e.Documento = mc.DocEmpCont AND e.IdTipoDocumento = mc.IdTipoDocCont
                WHERE mc.NroSerie = ? ";
        
        $entity_result = DB::select($sql, [$nroSerie]);

        if(isset($entity_result)) {
            return $entity_result;
        }
        return [];
    }

    public static function loadByVehiculo($serie, $numero, $modifier = ''): array
    {
        $binding = [];
        $sql = "SELECT  vc.DocEmpCont + '-' + LTRIM(RTRIM(STR(vc.IdTipoDocCont))) AS IdEmpContratista,
                        vc.DocEmpCont + '-' + LTRIM(RTRIM(STR(vc.IdTipoDocCont))) + '-' + vc.NroContrato AS IdContrato,
                        e.Nombre AS Contratista,
                        vc.NroContrato AS NroContrato,
                        vc.FechaAlta AS FechaAltaContrato
                FROM Vehiculos" . $modifier . "Contratos vc
                INNER JOIN Empresas e ON e.Documento = vc.DocEmpCont AND e.IdTipoDocumento = vc.IdTipoDocCont
                WHERE vc.Serie = :serie AND vc.Numero = :numero ";
        $binding[':serie'] = $serie;
        $binding[':numero'] = $numero;

        $entity_result = DB::select($sql, $binding);

        if (isset($entity_result)) {
            return $entity_result;
        }
        return [];
    }

    public static function loadByPersonaFisica($documento, $idTipoDocumento, $docEmpresa, $tipoDocEmpresa, $fechaAlta, $modifier = ''): array
    {
        $fechaAlta = FsUtils::strToDateByPattern($fechaAlta)->format(FsUtils::DDMMYYHHMMSS);

        $emp = DB::selectOne(
            "SELECT COUNT(*) AS Cantidad "
                . "FROM PersonasFisicas" . $modifier . "Empresas pfe "
                . "WHERE pfe.Documento = ? "
                . "AND pfe.IdTipoDocumento = ? "
                . "AND pfe.DocEmpresa = ? "
                . "AND pfe.TipoDocEmpresa = ? "
                . "AND pfe.FechaAlta = CONVERT(datetime, ?, 103) "
                . "AND (pfe.FechaBaja IS NULL OR pfe.FechaBaja >= GETDATE())",
            [$documento, $idTipoDocumento, $docEmpresa, $tipoDocEmpresa, $fechaAlta]
        );

        if ($emp->Cantidad > 0) {
            $sql = "SELECT DISTINCT
                pfc.DocEmpCont + '-' + LTRIM(RTRIM(STR(pfc.IdTipoDocCont))) AS IdEmpContratista,
                pfc.DocEmpCont + '-' + LTRIM(RTRIM(STR(pfc.IdTipoDocCont))) + '-' + pfc.NroContrato AS IdContrato,
                e.Nombre AS Contratista,
                pfc.NroContrato AS NroContrato,
                pfc.FechaAlta AS FechaAltaContrato
            FROM PersonasFisicas" . $modifier . "Contratos pfc
            INNER JOIN PersonasFisicasEmpresas pfe  ON pfe.DocEmpresa = pfc.DocEmpresa AND pfe.TipoDocEmpresa = pfc.TipoDocEmpresa AND pfe.FechaAlta = CONVERT(datetime, ?, 103)
            INNER JOIN Empresas e ON e.Documento = pfe.DocEmpresa AND e.IdTipoDocumento = pfe.TipoDocEmpresa
            WHERE pfc.Documento = ? AND pfc.IdTipoDocumento = ?
            AND pfc.DocEmpresa = ? AND pfc.TipoDocEmpresa = ?";

            return DB::select($sql, [$fechaAlta, $documento, $idTipoDocumento, $docEmpresa, $tipoDocEmpresa]);
        }

        return [];
    }

    //// Obtener Contratos para IMPRIMIR MATRÍCULA
    public static function obtenerContratos($documento, $idTipoDocumento, $docEmpresa, $tipoDocEmpresa) {
        $contratos = DB::select(
            'SELECT NroContrato, CASE WHEN e.Baja = 0 AND pfc.FechaAlta <= GETDATE() THEN 1 ELSE 0 END AS EstaActivo FROM PersonasFisicasContratos pfc
            INNER JOIN Personas e ON pfc.DocEmpresa = e.Documento and pfc.TipoDocEmpresa = e.IdTipoDocumento
            WHERE pfc.Documento = ? AND pfc.IdTipoDocumento = ? AND pfc.DocEmpresa = ? AND pfc.TipoDocEmpresa = ? AND e.Baja = 0', 
            [$documento, $idTipoDocumento, $docEmpresa, $tipoDocEmpresa]
        );
        return $contratos;
    }

    public static function createByVehiculo(object $Args, bool $reset = false, string $modifier = '')
    {
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
                        . "FROM Vehiculos" . $modifier . "Contratos "
                        . "WHERE Serie = ? "
                            . "AND Numero = ? "
                            . "AND DocEmpresa = ? "
                            . "AND TipoDocEmpresa = ? "
                            . "AND DocEmpCont = ? "
                            . "AND IdTipoDocCont = ? "
                            . "AND NroContrato = ?",
                        [
                            $Args->Serie,
                            $Args->Numero,
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
                                "UPDATE Vehiculos" . $modifier . "Contratos SET "
                                . "FechaAlta = CONVERT(date, ?, 103) "
                                . "WHERE Serie = ? "
                                . "AND Numero = ? "
                                . "AND DocEmpresa = ? "
                                . "AND TipoDocEmpresa = ? "
                                . "AND DocEmpCont = ? "
                                . "AND IdTipoDocCont = ? "
                                . "AND NroContrato = ? ",
                                [
                                    $contrato->FechaAltaContrato,
                                    $Args->Serie,
                                    $Args->Numero,
                                    $IdEmpresaObj[0],
                                    $IdEmpresaObj[1],
                                    $IdEmpContratistaObj[0],
                                    $IdEmpContratistaObj[1],
                                    $contrato->NroContrato,
                                ]
                            ];
                        } else {
                            $statements[] = [
                                "DELETE FROM Vehiculos" . $modifier . "Contratos "
                                . "WHERE Serie = ? "
                                . "AND Numero = ? "
                                . "AND DocEmpresa = ? "
                                . "AND TipoDocEmpresa = ? "
                                . "AND DocEmpCont = ? "
                                . "AND IdTipoDocCont = ? "
                                . "AND NroContrato = ? ",
                                [
                                    $Args->Serie,
                                    $Args->Numero,
                                    $IdEmpresaObj[0],
                                    $IdEmpresaObj[1],
                                    $IdEmpContratistaObj[0],
                                    $IdEmpContratistaObj[1],
                                    $contrato->NroContrato,
                                ]
                            ];

                            $statements[] = [
                                "INSERT INTO Vehiculos" . $modifier . "ContratosBajas " 
                                . "(Serie,
                                    Numero,
                                    Matricula,
                                    DocEmpresa,
                                    TipoDocEmpresa,
                                    DocEmpCont,
                                    IdTipoDocCont,
                                    NroContrato,
                                    FechaBaja,
                                    FechaHoraBaja,
                                    IdUsuarioBaja) "
                                    . "VALUES (?, ?, ?, ?, ?, ?, ?, ?, CONVERT(date, ?, 103), GETDATE(), ?)",
                                [
                                    $Args->Serie,
                                    $Args->Numero,
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
                            "INSERT INTO Vehiculos" . $modifier . "Contratos " 
                            . "(Serie,
                            Numero,
                            DocEmpresa,
                            TipoDocEmpresa,
                            DocEmpCont,
                            IdTipoDocCont,
                            NroContrato,
                            FechaAlta,
                            IdUsuarioAlta) "
                            . "VALUES (?, ?, ?, ?, ?, ?, ?, CONVERT(date, ?, 103), ?);",
                            [
                                $Args->Serie,
                                $Args->Numero,
                                $IdEmpresaObj[0],
                                $IdEmpresaObj[1],
                                $IdEmpContratistaObj[0],
                                $IdEmpContratistaObj[1],
                                $contrato->NroContrato,
                                FsUtils::strToDateByPattern($contrato->FechaAltaContrato)->format(FsUtils::DDMMYY),
                                Auth::id(),
                            ]
                        ];

                        $statements[] = [
                            "INSERT INTO Vehiculos" . $modifier . "ContratosAltas " 
                            . "(Serie,
                            Numero,
                            Matricula,
                            DocEmpresa,
                            TipoDocEmpresa,
                            DocEmpCont,
                            IdTipoDocCont,
                            NroContrato,
                            FechaAlta,
                            FechaHoraAlta,
                            IdUsuarioAlta) "
                            . "VALUES (?, ?, ?, ?, ?, ?, ?, ?, CONVERT(date, ?, 103), GETDATE(), ?)",
                            [
                                $Args->Serie,
                                $Args->Numero,
                                $Args->Matricula,
                                $IdEmpresaObj[0],
                                $IdEmpresaObj[1],
                                $IdEmpContratistaObj[0],
                                $IdEmpContratistaObj[1],
                                $contrato->NroContrato,
                                FsUtils::strToDateByPattern($contrato->FechaAltaContrato)->format(FsUtils::DDMMYY), // $contrato->FechaAltaContrato // 20220105 // RE: Tag
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
                $contratos_bindings = [$Args->Serie, $Args->Numero];
                $added = "";
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
                    . "FROM Vehiculos" . $modifier . "Contratos "
                    . "WHERE Serie = ? "
                    . "AND Numero = ? "
                    . "AND NOT (" . $added . ")", $contratos_bindings);
                
                foreach ($todelete as $d) {
                    array_unshift($statements, [
                        "DELETE "
                            . "FROM Vehiculos" . $modifier . "Contratos "
                            . "WHERE Serie = ? "
                            . "AND Numero = ? "
                            . "AND DocEmpresa = ? "
                            . "AND TipoDocEmpresa = ? "
                            . "AND DocEmpCont = ? "
                            . "AND IdTipoDocCont = ? "
                            . "AND NroContrato = ? ",
                        [
                            $Args->Serie,
                            $Args->Numero,
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
            $statements[] = ["DELETE FROM Vehiculos" . $modifier . "Contratos WHERE Serie = ? AND Numero = ? ", [$Args->Serie, $Args->Numero]];
        }

        foreach ($statements as $statement) {
            list($sql, $binding) = $statement;
            if (!DB::statement($sql, $binding)) {
                throw new \Exception(sprintf('Ocurrió un error al ejecutar la sentencia %s con los siguientes datos (%s)', $sql, implode(', ', $binding)));
            }
        }
    }
}
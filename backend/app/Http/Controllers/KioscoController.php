<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PDO;

class KioscoController extends Controller
{

    /**
     * @var Request
     */
    public $req;

    public function __construct(Request $req)
    {
        $this->req = $req;
    }

    public function index()
    {
        // try {
            $conn = new PDO(
                'sqlsrv:server='.env('DB_KIOSCO_SERVER').';database='.env('DB_KIOSCO_SCHEMA'),
                env('DB_KIOSCO_USER'),
                env('DB_KIOSCO_PASS'),
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_WARNING]
            );
        // } catch (\Exception $err) {
        //     Log::error()
        //     file_put_contents('C:\\fs-v3-kiosco.log', '[' . date('Y-m-d H:i:s') . '] Exception: ' . $err->getMessage() . PHP_EOL, FILE_APPEND);
        //     exit;
        // }

        $sql = "SELECT *, "
            . "CASE WHEN ISNULL(Persona_NombreCompleto, '') = '' THEN NULL ELSE Persona_NombreCompleto + ' (' + Persona_Documento + ')' END AS Persona, "
            . "CASE WHEN ISNULL(Vehiculo_Serie, '') = '' THEN NULL ELSE Vehiculo_Serie + ' ' + RIGHT('0000' + CAST(Vehiculo_Numero AS VARCHAR), 4) END AS Vehiculo "
            . "FROM Solicitudes ";

        $where = [];
        $binding = [];
        
        if (array_key_exists('FechaDesde', $_GET) && array_key_exists('FechaHasta', $_GET)) {
            if (!empty($_GET['FechaDesde']) && !empty($_GET['FechaHasta'])) {
                $binding[] = $_GET['FechaDesde'] . ' 00:00:00';
                $binding[] = $_GET['FechaHasta'] . ' 23:59:59';
                $where[] = "FechaHoraInicio BETWEEN CONVERT(datetime, ?, 120) AND CONVERT(datetime, ?, 120)";
            }
        }
        if (array_key_exists('SoloFinalizados', $_GET) && !empty($_GET['SoloFinalizados'])) {
            $where[] = "FinalizaCorrectamente = 1";
        }
        if (array_key_exists('PersonaDocumento', $_GET) && !empty($_GET['PersonaDocumento'])) {
            $binding[] = $_GET['PersonaDocumento'];
            $where[] = "Persona_Documento LIKE ?";
        }
        if (array_key_exists('PersonaMatricula', $_GET) && !empty($_GET['PersonaMatricula'])) {
            $binding[] = $_GET['PersonaMatricula'];
            $where[] = "Persona_Matricula LIKE ?";
        }
        if (array_key_exists('VehiculoSerieNumero', $_GET) && !empty($_GET['VehiculoSerieNumero'])) {
            $binding[] = $_GET['VehiculoSerieNumero'];
            $where[] = "CONCAT(Vehiculo_Serie, Vehiculo_Numero) LIKE ?";
        }
        if (array_key_exists('EmpresaDocumento', $_GET) && !empty($_GET['EmpresaDocumento'])) {
            $binding[] = $_GET['EmpresaDocumento'];
            $where[] = "Persona_Empresa_Documento LIKE ?";
        }
        if (array_key_exists('IdPila', $_GET) && !empty($_GET['IdPila'])) {
            $binding[] = $_GET['IdPila'];
            $where[] = "QR_IdPila LIKE ?";
        }
        if (array_key_exists('IdProducto', $_GET) && !empty($_GET['IdProducto'])) {
            $binding[] = $_GET['IdProducto'];
            $where[] = "QR_IdProducto LIKE ?";
        }
        if (array_key_exists('IdProveeduria', $_GET) && !empty($_GET['IdProveeduria'])) {
            $binding[] = $_GET['IdProveeduria'];
            $where[] = "QR_IdProveeduria LIKE ?";
        }
        if (array_key_exists('NumeroRemito', $_GET) && !empty($_GET['NumeroRemito'])) {
            $binding[] = $_GET['NumeroRemito'];
            $where[] = "NumeroRemito LIKE ?";
        }
        if (array_key_exists('IdTerminal', $_GET) && !empty($_GET['IdTerminal'])) {
            $binding[] = $_GET['IdTerminal'];
            $where[] = "IdTerminal LIKE ?";
        }
        if (count($where) > 0) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $sql .= " ORDER BY FechaHoraInicio DESC";

        $stmt = $conn->prepare($sql);
        $stmt->execute($binding);
        $data = $stmt->fetchAll();

        $data = array_map(function ($item) {
            switch ($item['IdTerminal']) {
                case 'ebc64ba1-635b-428d-8a5f-b0d4a8bf7e62':
                    $item['Terminal'] = 'Terminal #1';
                    break;
                case 'd0138a7b-08cc-42cb-b989-4fbf55a3e837':
                    $item['Terminal'] = 'Terminal #2';
                    break;
            }
            return $item;
        }, $data);
        return array_values($data);
    }
    
}
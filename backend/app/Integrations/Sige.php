<?php

namespace App\Integrations;

use \Exception;
use \SoapFault;
use \SoapClient;
use \SoapParam;
use Illuminate\Support\Facades\Log;
use App\Exceptions\SigeException;
use App\Models\LogAuditoria;

class Sige implements IIntegration
{
    #region Constants 
    const NAME = 'SIGE';

    const ENTIDAD = 'Camion';
    #endregion

    #region Public methods 
    public static function altaCamion(
        $camion,
        $documento,
        $marca,
        $modelo,
        $camionMadera,
        $taraCamion,
        $docEmpresa,
        $tipoDocEmpresa,
        $nombreEmpresa,
        $paisEmpresa,
        $nombrePaisEmpresa): array
    {
        /*
         * WS Response:
         * array (size=2)
         * 'Error' => string 'S' (length=1)
         * 'Errordescripcion' => string 'Comunicarse con central de transporte para que le asignen la tara que corresponda en SGF. Luego volver a dar de alta el camiÃ³n' (length=127)
         */
        $r = self::call("SIGE_GrabarCamiones.Execute", array(
            new SoapParam("A", "ns1:Operacion"),
            new SoapParam($documento, "ns1:Matricula"),
            new SoapParam($tipoDocEmpresa, "ns1:Tipodocempresatransportista"),
            new SoapParam($docEmpresa, "ns1:Nrodocempresatransportista"),
            new SoapParam($paisEmpresa, "ns1:Codpaisempresatransportista"),
            new SoapParam($nombrePaisEmpresa, "ns1:Nompaisempresatransportista"),
            new SoapParam($nombreEmpresa, "ns1:Nomempresatransportista"),
            new SoapParam($camionMadera, "ns1:Camiondemadera"),
            new SoapParam($taraCamion, "ns1:Taracamion"),
            new SoapParam($marca, "ns1:Marca"),
            new SoapParam($modelo, "ns1:Modelo"),
        ));

        $logData = (object)[
            'DocEmpresa'       => $docEmpresa,
            'TipoDocEmpresa'   => $tipoDocEmpresa,
            'Serie'            => $camion->Serie,
            'Numero'           => $camion->Numero,
            'Categoria'        => $camion->Propietario,
            'Marca'            => $marca,
            'Modelo'           => $modelo,
            'Propietario'      => $camion->Propietario,
            'Activo'           => @$camion->Estado ?? 0,
            'Matricula'        => $camion->Matricula,
            'TransportaMadera' => $camionMadera,
            'Tara'             => $taraCamion
        ];

        LogAuditoria::log(
            LogAuditoria::FSA_USER_DEFAULT,
            implode('.', [self::NAME, self::ENTIDAD]),
            LogAuditoria::FSA_METHOD_CREATE,
            $logData,
            $documento,
            sprintf('%s %s %s (%s)', self::ENTIDAD, $marca, $modelo, $documento)
        );

        return $r;
    }

    public static function modificacionCamion(
        $camion,
        $documento,
        $marca,
        $modelo,
        $camionMadera,
        $taraCamion,
        $docEmpresa,
        $tipoDocEmpresa,
        $nombreEmpresa,
        $paisEmpresa,
        $nombrePaisEmpresa): array
    {
        $r = self::call("SIGE_GrabarCamiones.Execute", array(
            new SoapParam("M", "ns1:Operacion"),
            new SoapParam($documento, "ns1:Matricula"),
            new SoapParam($tipoDocEmpresa, "ns1:Tipodocempresatransportista"),
            new SoapParam($docEmpresa, "ns1:Nrodocempresatransportista"),
            new SoapParam($paisEmpresa, "ns1:Codpaisempresatransportista"),
            new SoapParam($nombrePaisEmpresa, "ns1:Nompaisempresatransportista"),
            new SoapParam($nombreEmpresa, "ns1:Nomempresatransportista"),
            new SoapParam($camionMadera, "ns1:Camiondemadera"),
            new SoapParam($taraCamion, "ns1:Taracamion"),
            new SoapParam($marca, "ns1:Marca"),
            new SoapParam($modelo, "ns1:Modelo"),
        ));

        $logData = (object)[
            'DocEmpresa'       => $docEmpresa,
            'TipoDocEmpresa'   => $tipoDocEmpresa,
            'Serie'            => $camion->Serie,
            'Numero'           => $camion->Numero,
            'Categoria'        => $camion->Propietario,
            'Marca'            => $marca,
            'Modelo'           => $modelo,
            'Propietario'      => $camion->Propietario,
            'Activo'           => @$camion->Estado ?? 0,
            'Matricula'        => $camion->Matricula,
            'TransportaMadera' => $camionMadera,
            'Tara'             => $taraCamion
        ];

        LogAuditoria::log(
            LogAuditoria::FSA_USER_DEFAULT,
            implode('.', [self::NAME, self::ENTIDAD]),
            LogAuditoria::FSA_METHOD_UPDATE,
            $logData,
            $documento,
            sprintf('%s %s %s (%s)', self::ENTIDAD, $marca, $modelo, $documento)
        );
        
        return $r;
    }
    
    public static function bajaCamion(
        $camion,
        $documento,
        $marca,
        $modelo,
        $camionMadera,
        $taraCamion,
        $docEmpresa,
        $tipoDocEmpresa,
        $nombreEmpresa,
        $paisEmpresa,
        $nombrePaisEmpresa): array
    {
        $r = self::call("SIGE_GrabarCamiones.Execute", array(
            new SoapParam("B", "ns1:Operacion"),
            new SoapParam($documento, "ns1:Matricula"),
            new SoapParam($tipoDocEmpresa, "ns1:Tipodocempresatransportista"),
            new SoapParam($docEmpresa, "ns1:Nrodocempresatransportista"),
            new SoapParam($paisEmpresa, "ns1:Codpaisempresatransportista"),
            new SoapParam($nombrePaisEmpresa, "ns1:Nompaisempresatransportista"),
            new SoapParam($nombreEmpresa, "ns1:Nomempresatransportista"),
            new SoapParam($camionMadera, "ns1:Camiondemadera"),
            new SoapParam($taraCamion, "ns1:Taracamion"),
            new SoapParam($marca, "ns1:Marca"),
            new SoapParam($modelo, "ns1:Modelo"),
        ));
        
        LogAuditoria::log(
            LogAuditoria::FSA_USER_DEFAULT,
            implode('.', [self::NAME, self::ENTIDAD]),
            LogAuditoria::FSA_METHOD_DELETE,
            [],
            $documento,
            sprintf('%s %s %s (%s)', self::ENTIDAD, $marca, $modelo, $documento)
        );

        return $r;
    }
    #endregion

    #region Private methods 
    private static function call(string $function, array $arguments): array
    {
        try {
            $soap = new SoapClient(null, array(
                "location" => env('SIGE_WS_URI'),
                "uri" => env('SIGE_WS_NAMESPACE'),
                "encoding" => "UTF-8",
                "use" => SOAP_LITERAL,
                "trace" => true
            ));

            $response = $soap->__soapCall($function, $arguments);
        }
        catch (SoapFault $ex) {
            $t = "SoapException";
            $o = $ex;
            $q = $function;
            $r = $ex->faultstring;
        }
        catch (Exception $ex) {
            $t = "Exception";
            $o = $ex;
            $q = $function;
            $r = $ex->getMessage();
        }

        if (!isset($response)) {
            Log::error("[SIGE] API Error: " . $r, [
                'Type' => $t,
                'Exception' => $o,
                'Request' => $q,
                'Response' => $r
            ]);

            throw new SigeException("SIGE API Error: " . $r, -5163);
        }

        Log::info('[SIGE] Call ' . $function, [
            "call" => $function,
            "xml" => $soap->__getLastRequest(),
            "response" => $response,
            "args" => $arguments
        ]);

        $response = (array)$response;

        if ($response["Error"] == "N") {
            return $response;
        } else if (isset($response["Errordescripcion"])) {
            throw new SigeException("SIGE API Error: " . $response["Errordescripcion"], -5163);
        } else if (isset($response["faultcode"])) {
            throw new SigeException("SIGE API Fault: " . $response["faultstring"], -5163);
        } else {
            throw new SigeException("SIGE API Generic Error: " . json_encode($response), -5163);
        }
    }
    #endregion
}
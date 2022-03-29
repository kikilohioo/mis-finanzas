<?php

namespace App\WebServices;

use App\FsUtils;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\VehiculoController;
use App\Integrations\OnGuard;
use App\Models\LogAuditoria;
use App\Models\TipoDocumentoVehic;
use App\Models\Contrato as ContratoModel;
use App\Models\Incidencia;
use App\Models\Verificacion;
use App\Models\Acceso;
use App\Models\Vehiculo;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use stdClass;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WSDL\Annotation\WebMethod;
use WSDL\Annotation\WebResult;
use WSDL\Annotation\WebParam;
use WSDL\Annotation\WebService;
use WSDL\Annotation\SoapBinding;

/**
 * @WebService(
 *  targetNamespace="FSAcceso/FsPublicServer",
 *  ns="FSAcceso",
 *  location="https://fsgestion.montesdelplata.com.uy/api/webservice"
 * )
 * @SoapBinding(use="ENCODED")
 */
class FsPublicServer
{

    /**
     * @var Request
     */
    private $req;

    public function __construct()
    {
        $this->req = request();
        $this->req->headers->set('X-FS-Token', env('FSA_PUBLIC_TOKEN'));
    }
    
    /**
     * @WebMethod
     * @WebParam(param="string $Documento")
     * @WebParam(param="string $IdTipoDocumento")
     * @WebResult(param="string $Retorno")
     */
    public function fsEstadoPersona($Documento, $IdTipoDocumento)
    {
        throw new \Exception('asd');
        return 'OK: ' . $Documento . '-' . $IdTipoDocumento;
    }

    /**
     * @WebMethod
     * @WebParam(param="string $Serie")
     * @WebParam(param="int $Numero")
     * @WebResult(param="string $Retorno")
     */
    function fsCategoriaVehiculo($Serie, $Numero) {      
        $vars = get_defined_vars();
        $Args = self::sanitizeArgs($vars);
        
        try {
            self::exigirArgs($Args, ['Serie', 'Numero']);
            $detalle = DB::selectOne('SELECT * FROM Vehiculos WHERE Serie = ? AND Numero = ?', [$Args->Serie, $Args->Numero]);
            if (!$detalle) {
                throw new Exception('El vehículo no existe');
            }
            $idCategoria = $detalle->IdCategoria;
            if (is_numeric($idCategoria) && $idCategoria != 0) {
                return $idCategoria;
            }
            throw new Exception('El vehículo no tiene categoría asociada');
        } catch (Exception $ex) {
            return 0;
        }
    }

    /**
     * @WebMethod
     * @WebParam(param="string $Operacion")
     * @WebParam(param="string $Serie")
     * @WebParam(param="int $Numero")
     * @WebParam(param="int $IdTipoVehiculo")
     * @WebParam(param="int $IdMarcaVehic")
     * @WebParam(param="int $IdCategoria")
     * @WebParam(param="string $Modelo")
     * @WebParam(param="string $IdEmpresa")
     * @WebParam(param="date $FechaVigenciaDesde")
     * @WebParam(param="time $HoraVigenciaDesde")
     * @WebParam(param="date $FechaVigenciaHasta")
     * @WebParam(param="time $HoraVigenciaHasta")
     * @WebParam(param="string $IdExterno")
     * @WebParam(param="int $Matricula")
     * @WebParam(param="string $Tara")
     * @WebParam(param="string $TransportaMadera")
     * @WebParam(param="Contrato[] $Contratos")
     * @WebResult(param="string $Retorno")
     */
    public function fsVehiculo(
        $Operacion,
        $Serie,
        $Numero,
        $IdTipoVehiculo,
        $IdMarcaVehic,
        $IdCategoria,
        $Modelo,
        $IdEmpresa,
        $FechaVigenciaDesde,
        $HoraVigenciaDesde,
        $FechaVigenciaHasta,
        $HoraVigenciaHasta,
        $IdExterno,
        $Matricula,
        $Tara,
        $TransportaMadera,
        $Contratos = null
    ) {
        $vars = get_defined_vars();
        $Args = self::sanitizeArgs($vars);
                
        try {
            if (isset($Args->Contratos)) {
                $Args->Contratos = $Args->Contratos->Contrato;
            }
            
            self::exigirArgs($Args, [
                'Operacion', 
                'Serie', 
                'Numero', 
                'IdTipoVehiculo', 
                'IdMarcaVehic',
                'IdCategoria',
                'Modelo',
            ]);

            if (!empty($Args->Matricula)) {
                self::exigirArgs($Args, [
                    'VigenciaDesde',
                    'VigenciaHasta',
                ]);
            }

            if (!empty($Args->DocEmpresa)) {
                self::exigirArgs($Args, [
                    'IdTipoDocEmpresa',
                    'FechaAltaEmpresa',
                ]);
            }
            
            if (!empty($Args->Contratos)) {
                foreach ($Args->Contratos as $contrato) {
                    self::exigirArgs($contrato, [
                        'IdEmpContratista',
                        'NroContrato',
                        'FechaAltaContrato',
                    ]);
                    $IdEmp = fs_explode_id($contrato->IdEmpContratista);

                    $contrato->DocEmpCont = $IdEmp[0];
                    $contrato->IdTipoDocCont = $IdEmp[1];
                }
            }
            
            $Args->Estado = 0;
            
            $operaciones = [
                'A' => 'fsVehiculoAlta',
                'B' => 'fsVehiculoBaja',
                'M' => 'fsVehiculoModificacion',
            ];
            
            if (!array_key_exists($Args->Operacion, $operaciones)) {
                throw new Exception('Operación no válida');
            }
            
            return $this->{$operaciones[$Args->Operacion]}($Args);
        } catch (Exception $ex) {
            Log::error($ex->getMessage(), $ex->getTrace());
            return $ex->getMessage();
        }
    }
    
    /**
     * @WebMethod
     * @WebParam(param="string $Oper")
     * @WebParam(param="string $DocEmpresa")
     * @WebParam(param="int $TipoDocEmpresa")
     * @WebParam(param="string $Serie")
     * @WebParam(param="string $Numero")
     * @WebParam(param="int $Categoria")
     * @WebParam(param="int $Marca")
     * @WebParam(param="string $Modelo")
     * @WebParam(param="string $Propietario")
     * @WebParam(param="int $Activo")
     * @WebParam(param="string $TransportaMadera")
     * @WebParam(param="int $Tara")
     * @WebResult(param="string $Retorno")
     */
    public function fsVehiculoSIGE(
        $Oper,
        $DocEmpresa,
        $TipoDocEmpresa,
        $Serie,
        $Numero,
        $Categoria,
        $Marca,
        $Modelo,
        $Propietario,
        $Activo,
        $TransportaMadera,
        $Tara
    ) {
        $vars = get_defined_vars();
        $Args = self::sanitizeArgs($vars);
        
        try {
            self::exigirArgs($Args, [
                'Oper', 
                'DocEmpresa', 
                'TipoDocEmpresa', 
                'Serie', 
                'Numero', 
                // 'Categoria', 
                'Marca', 
                // 'Modelo',
            ]);
            
            $v = DB::selectOne('SELECT * FROM Vehiculos WHERE Serie = ? AND Numero = ?', [$Args->Serie, $Args->Numero]);
            if (!isset($v)) {
                $v = new \stdClass;
            }

            $v->NoSincSIGE = true; // TEMP
            $v->Serie = $Args->Serie;
            $v->Numero = $Args->Numero;
            $v->Estado = @$Args->Activo ?? 0;
            $v->IdTipoVehiculo = 2; // Camión

            if ($Args->Oper === 'A') {
                if (in_array(@$Args->Categoria, [env('SIGE_TEMPORAL_CATEGORY')])) {
                    $v->IdCategoria = $Args->Categoria;
                } else {
                    $v->IdCategoria = env('SIGE_DEFAULT_CATEGORY');
                }
                $v->IdMarcaVehic = $Args->Marca;
                $v->NombreMarca = DB::selectOne('SELECT * FROM MarcasVehiculos WHERE IdMarcaVehic = ?', [$Args->Marca])->Descripcion;
            }

            if (empty($v->IdCategoria)) {
                if (in_array(@$Args->Categoria, [env('SIGE_DEFAULT_CATEGORY'), env('SIGE_TEMPORAL_CATEGORY')])) {
                    $v->IdCategoria = $Args->Categoria;
                } else {
                    $v->IdCategoria = env('SIGE_DEFAULT_CATEGORY');
                }
            }
            if (empty($v->IdMarcaVehic)) {
                $v->IdMarcaVehic = 166;
            }
            
            $v->Modelo = empty($Args->Modelo) ? 'N/C Modelo' : $Args->Modelo;
            $v->Propietario = @$Args->Propietario;
            $v->TransportaMadera = $Args->TransportaMadera;
            $v->Tara = $Args->Tara;
            $v->DocEmpresa = ltrim($Args->DocEmpresa, '0');
            $v->TipoDocEmpresa = $Args->TipoDocEmpresa;
            $v->IdEmpresa = $Args->DocEmpresa . '-' . $Args->TipoDocEmpresa;

            $v->CreateIfNotExists = true;
            
            if (empty($v->TransportaMadera) && empty($v->Tara)) {
                $ignoraTara = (is_numeric(env('SIGE_TEMPORAL_CATEGORY')) && (env('SIGE_TEMPORAL_CATEGORY') > 0) && $v->IdCategoria == env('SIGE_TEMPORAL_CATEGORY'));
                if (!$ignoraTara) {
                    throw new Exception('Si el vehículo no transporta madera debe ingresar su tara (WS)');
                }
            }
            
            $operaciones = [
                'A' => 'fsVehiculoAlta',
                'B' => 'fsVehiculoBaja',
                'M' => 'fsVehiculoModificacion',
            ];
            
            $operacionesLog = [
                'A' => 'alta',
                'B' => 'baja',
                'M' => 'modificacion',
            ];
            
            if (!array_key_exists($Args->Oper, $operaciones)) {
                throw new Exception('Operación no válida');
            }
            
            if (!DB::selectOne('SELECT COUNT(*) AS Cantidad FROM Empresas WHERE Documento = ? AND IdTipoDocumento = ?', [$v->DocEmpresa, $v->TipoDocEmpresa])) {
                throw new Exception('No existe empresa con Documento = ' . $v->DocEmpresa . ' y TipoDocumento = ' . $v->TipoDocEmpresa, -1);
            }

            if (!empty($v) && $Args->Oper === 'M') {
                $v->Documentos = TipoDocumentoVehic::list($Args->Serie, $Args->Numero);
                $v->Contratos = ContratoModel::loadByVehiculo($Args->Serie, $Args->Numero);
                $v->Incidencias = Incidencia::loadByVehiculo($Args->Serie, $Args->Numero);
                $v->Verificaciones = Verificacion::loadByVehiculo($Args->Serie, $Args->Numero);
                $v->Accesos = Acceso::loadByVehiculo($Args->Serie, $Args->Numero);
            }
            
            $r = $this->{$operaciones[$Args->Oper]}($v);

            $log = (object)[
                'DocEmpresa'       => $v->DocEmpresa,
                'TipoDocEmpresa'   => $v->TipoDocEmp,
                'Serie'            => $v->Serie,
                'Numero'           => $v->Numero,
                'Categoria'        => @$v->Categoria,
                'Marca'            => @$v->Marca,
                'Modelo'           => $v->Modelo,
                'Propietario'      => $v->Propietario,
                'Matricula'        => $v->Matricula,
                'Activo'           => $v->Estado,
                'TransportaMadera' => $v->TransportaMadera,
                'Tara'             => $v->Tara
            ];

            LogAuditoria::log(
                Auth::id(),
                'SIGE.Camion',
                $operacionesLog[$Args->Oper],
                $log,
                implode('', [$log->Serie, $log->Numero]),
                sprintf('%s %s (%s)', $log->Marca, $log->Modelo, Vehiculo::id($log))
            );
            
            return $r;    
        } catch (Exception $ex) {
            Log::error($ex->getMessage(), $ex->getTrace());
            return $ex->getMessage();
        }
    }
    
    /**
     * @WebMethod
     * @WebParam(param="string $Oper")
     * @WebParam(param="string $Serie")
     * @WebParam(param="string $Numero")
     * @WebParam(param="string $TAG")
     * @WebParam(param="int $BadgeStatus")
     * @WebParam(param="string $ActivateDate")
     * @WebParam(param="string $DeactivateDate")
     * @WebParam(param="int $ForzarActivacion")
     * @WebResult(param="string $Retorno")
     */
    public function fsVehiculoSIGETagTelepeaje(
        $Oper,
        $Serie,
        $Numero,
        $TAG,
        $BadgeStatus,
        $ActivateDate,
        $DeactivateDate
    ) {
        $vars = get_defined_vars();
        $Args = self::sanitizeArgs($vars);
        
        try {
            return DB::transaction(function () use ($Args) {
                self::exigirArgs($Args, [
                    'Oper',
                    'Serie',
                    'Numero',
                    'TAG',
                    'ActivateDate',
                    'DeactivateDate',
                ]);
                
                $Args->Serie = trim($Args->Serie);
                $Args->Numero = trim($Args->Numero);
    
                $v = app(VehiculoController::class)->show_interno($Args->Serie, $Args->Numero);
                
                if (empty($v)) {
                    // throw new Exception('El vehículo no existe', -1);
                    if (!is_numeric(env('SIGE_TEMPORAL_CATEGORY')) || !(env('SIGE_TEMPORAL_CATEGORY') > 0)) { // !defined('SIGE_ANOTHER_CATEGORY') || 
                        throw new Exception('Constante "SIGE_TEMPORAL_CATEGORY" no definida');
                    }
                    $response = $this->fsVehiculoSIGE($Args->Oper, 2, 2, $Args->Serie, $Args->Numero, env('SIGE_TEMPORAL_CATEGORY'), 166, 'Marca_SIGE', 'Propietario_SIGE', 0, 'N', 0);
                    $v = app(VehiculoController::class)->show_interno($Args->Serie, $Args->Numero);
                    if (empty($v)) {
                        throw new Exception('El vehículo no existe. Se intentó crear pero ocurrió un error');
                    }
                }
    
                $Args->IdCategoria = $v->IdCategoria;
                
                $v->NoSincSIGE = true;
                
                $cambioMatricula = false;
    
                if (empty($v->Matricula)) {
                    // $v->Matricula = Vehiculo::getNextBadgeID('TagVehiculoMin');
                    $v->Matricula = Vehiculo::getNextBetweenBadgeID('Tag');
                    DB::update('UPDATE Vehiculos SET Matricula = ? WHERE Serie = ? AND Numero = ?', [$v->Matricula, $v->Serie, $v->Numero]);
                }
                
                if ($Args->Oper == 'A' || $Args->Oper == 'M') {
                    if ($v->TAG != $Args->TAG) {
                        $cambioMatricula = true;
                    }
                    
                    $v->TAG = null;
                    $v->FechaVigenciaDesde = FsUtils::strToDateByPattern($Args->ActivateDate)->format(FsUtils::DDMMYY);
                    $v->HoraVigenciaDesde = FsUtils::strToDateByPattern($Args->ActivateDate)->format(FsUtils::HHMM);
                    $v->FechaVigenciaHasta = FsUtils::strToDateByPattern($Args->DeactivateDate)->format(FsUtils::DDMMYY);
                    $v->HoraVigenciaHasta = FsUtils::strToDateByPattern($Args->DeactivateDate)->format(FsUtils::HHMM);
                    $v->Estado = 0;
    
                    app(VehiculoController::class)->update($v->Serie, $v->Numero, $v);
                    
                    if ($cambioMatricula) { //  || !empty($Args->TAG)
                        $v->TAG = $Args->TAG;
                        app(VehiculoController::class)->cambiarTag($v->Serie, $v->Numero, $v->TAG);
                    }
    
                    $v->ForzarActivacion = false;
                    if ($v->IdCategoria == env('SIGE_TEMPORAL_CATEGORY') || $Args->ForzarActivacion === 1) {
                        $Args->BadgeStatus = 1;
                        $v->Estado = 1;
                        $v->ForzarActivacion = 1;
                    }
                    
                    if ($Args->BadgeStatus == '1') {
                        app(VehiculoController::class)->activar($v->Serie, $v->Numero, $v->ForzarActivacion);
                    } else {
                        // fs_standard_log('fsa_mz_public_server.php > ' . json_encode([$Args, $v]));
                        // fs_standard_log('fsa_mz_public_server.php > ' . json_encode([mzvehiculo::id($v) , $v->Matricula, 0, $v->CatLenel, $v->VigenciaDesde, $v->VigenciaHasta, "V", $v->TAG]));
                        $v = app(VehiculoController::class)->show_interno($Args->Serie, $Args->Numero);
                        OnGuard::modificarTarjetaEntidadLenel(Vehiculo::id($v), $v->Matricula, 0, $v->CatLenel, $v->VigenciaDesde, $v->VigenciaHasta, OnGuard::ENTIDAD_VEHICULO, $v->TAG);
                        app(VehiculoController::class)->desactivar($v->Serie, $v->Numero);
                    }
                } else {
                    $v->TAG = null;
                    $v->FechaVigenciaDesde = null;
                    $v->HoraVigenciaDesde = null;
                    $v->FechaVigenciaHasta = null;
                    $v->HoraVigenciaHasta = null;
                    $v->Estado = 0;
                    
                    app(VehiculoController::class)->cambiarTag($v->Serie, $v->Numero, $v->TAG);
                    app(VehiculoController::class)->update($v->Serie, $v->Numero, $v);
                }
    
                $operacionesLog = [
                    'A' => 'Asignación de Tag',
                    'M' => 'Modificación de Tag',
                    'B' => 'Eliminación de Tag'
                ];
                
                $log = (object)[
                    'DocEmpresa'       => $v->DocEmpresa,
                    'TipoDocEmpresa'   => $v->TipoDocEmp,
                    'Serie'            => $v->Serie,
                    'Numero'           => $v->Numero,
                    'Categoria'        => @$v->Categoria,
                    'Marca'            => @$v->Marca,
                    'Modelo'           => $v->Modelo,
                    'Propietario'      => $v->Propietario,
                    'Matricula'        => $v->Matricula,
                    'Activo'           => $v->Estado,
                    'TransportaMadera' => $v->TransportaMadera,
                    'Tara'             => $v->Tara,
                    'TAG'              => $v->TAG,
                ];
                
                LogAuditoria::log(
                    Auth::id(),
                    'SIGE.Camion',
                    $operacionesLog[$Args->Oper],
                    $log,
                    implode('', [$log->Serie, $log->Numero]),
                    sprintf('%s %s (%s)', $log->Marca, $log->Modelo, Vehiculo::id($log))
                );
                
                if (is_numeric($v->Matricula) && !empty($v->Matricula)) {
                    return 'OK-' . $v->Matricula;
                }
    
                throw new Exception('Ocurrió un error al momento de asignar el TAG Alfanumérico');
            });
        } catch (Exception $ex) {
            Log::error($ex->getMessage(), $ex->getTrace());
            return $ex->getMessage();
        }
    }
    
    /**
     * @WebMethod
     * @WebParam(param="string $Oper")
     * @WebParam(param="string $Serie")
     * @WebParam(param="string $Numero")
     * @WebParam(param="int $TAG")
     * @WebParam(param="int $BadgeStatus")
     * @WebParam(param="string $ActivateDate")
     * @WebParam(param="string $DeactivateDate")
     * @WebResult(param="string $Retorno")
     */
    public function fsVehiculoSIGETag(
        $Oper,
        $Serie,
        $Numero,
        $TAG,
        $BadgeStatus,
        $ActivateDate,
        $DeactivateDate
    ) {
        $vars = get_defined_vars();
        $Args = self::sanitizeArgs($vars);
        
        try {
            self::exigirArgs($Args, [
                'Oper', 
                'Serie', 
                'Numero', 
                'TAG', 
                'ActivateDate', 
                'DeactivateDate',
            ]);

            $Args->Matricula = $Args->TAG;
            
            $Args->Serie = trim($Args->Serie);
            $Args->Numero = trim($Args->Numero);

            $v = app(VehiculoController::class)->show_interno($Args->Serie, $Args->Numero);
            
            if (empty($v)) {
                throw new Exception('El vehículo no existe');
            }
            
            $v->NoSincSIGE = true;
            
            $cambioMatricula = false;
            
            if ($Args->Oper == 'A' || $Args->Oper == 'M') {
                if ($v->Matricula != $Args->Matricula) {
                    $cambioMatricula = true;
                }
                
                $v->Matricula = null;
                $v->FechaVigenciaDesde = FsUtils::strToDateByPattern($Args->ActivateDate, FsUtils::DDMMYY);
                $v->HoraVigenciaDesde = FsUtils::strToDateByPattern($Args->ActivateDate, FsUtils::HHMM);
                $v->FechaVigenciaHasta = FsUtils::strToDateByPattern($Args->DeactivateDate, FsUtils::DDMMYY);
                $v->HoraVigenciaHasta = FsUtils::strToDateByPattern($Args->DeactivateDate, FsUtils::HHMM);
                $v->Estado = 0;

                app(VehiculoController::class)->update($Args->Serie, $Args->Numero, $v);
                
                if ($cambioMatricula) {
                    $v->Matricula = $Args->Matricula;
                    app(VehiculoController::class)->cambiarMatricula($Args->Serie, $Args->Numero, $v->Matricula);
                }
                
                if (!empty($Args->BadgeStatus)) {
                    app(VehiculoController::class)->activar($Args->Serie, $Args->Numero);
                } else {
                    app(VehiculoController::class)->desactivar($Args->Serie, $Args->Numero);
                }
            } else {
                $v->Matricula = null;
                // $v->TAG = null;
                $v->FechaVigenciaDesde = null;
                $v->HoraVigenciaDesde = null;
                $v->FechaVigenciaHasta = null;
                $v->HoraVigenciaHasta = null;
                $v->Estado = 0;
                
                app(VehiculoController::class)->cambiarMatricula($Args->Serie, $Args->Numero, $v->Matricula);
                app(VehiculoController::class)->update($Args->Serie, $Args->Numero, $v);
            }

            $operacionesLog = [
                'A' => 'Asignación de Tag',
                'M' => 'Modificación de Tag',
                'B' => 'Eliminación de Tag'
            ];
            
            $log = (object)[
                'DocEmpresa'       => $v->DocEmpresa,
                'TipoDocEmpresa'   => $v->TipoDocEmp,
                'Serie'            => $v->Serie,
                'Numero'           => $v->Numero,
                'Categoria'        => @$v->Categoria,
                'Marca'            => @$v->Marca,
                'Modelo'           => $v->Modelo,
                'Propietario'      => $v->Propietario,
                'Matricula'        => $v->Matricula,
                'Activo'           => $v->Estado,
                'TransportaMadera' => $v->TransportaMadera,
                'Tara'             => $v->Tara
            ];
            
            LogAuditoria::log(
                Auth::id(),
                'SIGE.Camion',
                $operacionesLog[$Args->Oper],
                $log,
                implode('', [$log->Serie, $log->Numero]),
                sprintf('%s %s (%s)', $log->Marca, $log->Modelo, Vehiculo::id($log))
            );
            
            return 'OK';
        } catch (Exception $ex) {
            Log::error($ex->getMessage(), $ex->getTrace());
            return $ex->getMessage();
        }
    }
    
    /**
     * Instancia el controlador de vehículo para dar de alta un registro.
     * @param object $Args Datos del vehículo
     * @return string Resultado de la acción
     */
    private function fsVehiculoAlta($Args): string
    {
        try {
            app(VehiculoController::class)->create($Args);
            return 'OK';
        } catch (NotFoundHttpException $ex) {
            Log::error('Error: Vehículo no encontrado - ' . $ex->getMessage(), $ex->getTrace());
            return 'Error: Vehículo no encontrado';
        } catch (Exception $ex) {
            Log::error($ex->getMessage(), $ex->getTrace());
            if ($ex->getMessage() === 'El vehículo ya existe') {
                return '2 - El vehículo ya existe';
            }
            return 'Error: ' . $ex->getMessage();
        }
    }
    
    /**
     * Instancia el controlador de vehículo para actualizar un registro.
     * @param object $Args Datos del vehículo
     * @return string Resultado de la acción
     */
    private function fsVehiculoModificacion($Args): string
    {
        try {
            app(VehiculoController::class)->update($Args->Serie, $Args->Numero, $Args);
            return "OK";
        } catch (NotFoundHttpException $ex) {
            Log::error('Error: Vehículo no encontrado - ' . $ex->getMessage(), $ex->getTrace());
            return 'Error: Vehículo no encontrado';
        } catch (Exception $ex) {
            Log::error($ex->getMessage(), $ex->getTrace());
            return 'Error: ' . $ex->getMessage();
        }
    }
    
    /**
     * Instancia el controlador de vehículo para dar de baja un registro.
     * @param object $Args Datos del vehículo
     * @return string Resultado de la acción
     */
    private function fsVehiculoBaja($Args): string
    {
        try {
            app(VehiculoController::class)->delete($Args->Serie, $Args->Numero);
            return 'OK';
        } catch (NotFoundHttpException $ex) {
            Log::error('Error: Vehículo no encontrado - ' . $ex->getMessage(), $ex->getTrace());
            return 'Error: Vehículo no encontrado';
        } catch (Exception $ex) {
            Log::error($ex->getMessage(), $ex->getTrace());
            return 'Error: ' . $ex->getMessage();
        }
    }

    /**
     * @WebMethod
     * @WebParam(param="Evento[] $Eventos")
     * @WebResult(param="string $Retorno")
     */
    public function fsGrabarEventos($Eventos)
    {
        $success = 0;
        $error = 0;
        
        foreach ($Eventos as $vars) {
            $vars->TipoOperacion = (int)$vars->TipoOperacion;
            $Args = self::sanitizeArgs($vars);
            try {
                self::exigirArgs($Args, array('Documento', 'FechaHora', 'TipoOperacion', 'IdEquipo'));
                if (!empty($Args->Documento)) {
                    $equipo = DB::selectOne('SELECT * FROM Equipos WHERE IdEquipo = ?', [$Args->IdEquipo]);

                    if (!isset($equipo)) {
                        throw new Exception('No existe el equipo con ID ' . $Args->IdEquipo);
                    }

                    $persona = DB::selectOne('SELECT TOP 1 * FROM PersonasFisicas pf '
                        . 'INNER JOIN TiposDocumento td ON td.IdTipoDocumento = pf.IdTipoDocumento '
                        . 'WHERE dbo.Mask(pf.Documento, td.Mascara, 1, 1) = dbo.Mask(?, td.Mascara, 1, 1)', [$Args->Documento]);

                    if (!isset($persona)) {
                        throw new Exception('No existe una persona con el documento ingresado (' . $Args->Documento . ')');
                    }

                    if (empty($persona->Matricula)) {
                        throw new Exception('La persona ingresada no tiene una matrícula asociada (' . $persona->Documento . ')');
                    }

                    $Args->NroReloj = $Args->IdEquipo;
                    $Args->Matricula = $persona->Matricula;
                    $Args->Accion = !empty($Args->TipoOperacion) ? 1 : 0;
                    $Args->Observaciones = 'Lat: ' . $Args->Latitud . '; Long: ' . $Args->Longitud;

                    app(EventoController::class)->create($Args);
                    
                    $log = [$Args->NroReloj, $Args->Matricula, $Args->Accion, $Args->FechaHora];
                    $id = implode(', ', $log);

                    LogAuditoria::log(
                        Auth::id(),
                        'evento',
                        'GrabarEvento',
                        $log,
                        '',
                        sprintf('Evento cargado correctamente (%s)', $id)
                    );
                    
                    $success++;
                }
            } catch (Exception $ex) {
                $Args->Error = $ex->getMessage();
                $log = [@$Args->IdEquipo, @$Args->Matricula, @$Args->Accion, @$Args->FechaHora];
                $id = implode(', ', $log);
                LogAuditoria::log(
                    Auth::id(),
                    'evento',
                    'GrabarEvento',
                    $log,
                    '',
                    sprintf('Error: %s (%s)', $ex->getMessage(), $id)
                );
                Log::notice(sprintf('Error: %s', $ex->getMessage()), (array)$Args);
                $error++;
            }
            
        }
        
        return 'OK: ' . $success . '; ERROR: ' . $error;
    }

    /**
     * Comprueba que los `$ArgsEsperados` se encuentren dentro de `$Args`.
     * @throws Exception El o los campos no se encuentran
     */
    private static function exigirArgs($Args, $ArgsEsperados)
    {
        foreach ($ArgsEsperados as $ArgEsperado) {
            if (is_string($ArgEsperado)) {
               if (!isset($Args->{$ArgEsperado})) {
                   throw new Exception('Campo ' . $ArgEsperado . ' no encontrado');
               }
            } else if (is_array($ArgEsperado)) {
                $allEmpty = true;
                foreach ($ArgEsperado as $ArgOpcional) {
                    $allEmpty = $allEmpty && empty($Args->{$ArgOpcional});
                }
                if ($allEmpty) {
                    throw new Exception('Campos ' . implode(', ', $ArgEsperado) . ' no encontrados');
                }
            }
        }
    }
    
    /**
     * Convierte los datos recibidos desde el WS a
     * tipo de datos que espera la aplicación.
     */
    private static function sanitizeArgs($arrArgs)
    {
        $Args = new stdClass;
        
        $booleanValues = [
            'S' => true,
            'N' => false,
        ];
        
        $dateFields = [
            'FechaVtoDoc', 
            'VigenciaDesde',
            'VigenciaHasta',
            'FechaVigenciaDesde',
            'FechaVigenciaHasta',
            'FechaAltaEmpresa',
            'FechaAlta',
            'FechaBaja',
            'FechaAltaContrato',
            'FechaBajaContrato',
            'Vencimiento',
        ];
        
        $dateTimeFields = [
            'FechaHora',
        ];
        
        $booleanFields = [
            'TransportaMadera',
        ];

        foreach ($arrArgs as $k => $v) {
            if (is_array($v)) {
                $Args->{$k} = [];
                foreach ($v as $v_i) {
                    $Args->{$k}[] = self::sanitizeArgs((array)$v_i);
                }
            } else if (is_object($v)) {
                $Args->{$k} = self::sanitizeArgs((array)$v);
            } else if (!empty($v) || $v === 0 || $v === false) {
                if (in_array($k, $dateFields)) {
                    $v = FsUtils::strToDateByPattern($v)->format(FsUtils::DDMMYY);
                } else if (in_array($k, $dateTimeFields)) {
                    $v = FsUtils::strToDateByPattern($v)->format(FsUtils::DDMMYYHHMMSS);
                } else if (in_array($k, $booleanFields)) {
                    $v = $booleanValues[strtoupper($v)];
                }
                $Args->{$k} = $v;
            }
        }
        return $Args;
    }

}

class Contrato {
    /**
     * @var string
     */
    public $IdEmpContratista;
    /**
     * @var string
     */
    public $NroContrato;
    /**
     * @var date
     */
    public $FechaAltaContrato;
    /**
     * @var date
     */
    public $FechaBajaContrato;
    /**
     * @var string
     */
    public $Observaciones;
}

class Evento {
    /**
     * @var string
     */
    public $Documento;
    /**
     * @var string
     */
    public $FechaHora;
    /**
     * @var int
     */
    public $TipoOperacion;
    /**
     * @var int
     */
    public $IdEquipo;
    /**
     * @var float
     */
    public $Latitud;
    /**
     * @var float
     */
    public $Longitud;
}
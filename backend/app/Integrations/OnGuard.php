<?php

namespace App\Integrations;

use App\Exceptions\OnGuardException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\LogAuditoria;
use \Exception;

class OnGuard implements IIntegration
{

    #region Constants 
    const NAME = 'OnGuard';

    const ESTADO_ACTIVO = 1;
    const ESTADO_INACTIVO = 2;

    const ENTIDAD_PERSONA = 'PF';
    const ENTIDAD_VISITANTE = 'PFV';
    const ENTIDAD_VEHICULO = 'V';
    const ENTIDAD_MAQUINA = 'M';

    const MAX_ATTEMPS = 2;
    const SLEEP_BETWEEN_ATTEMPS = 5;

    const CONTINGENCY_O = 'O';
    const CONTINGENCY_U = 'U';

    const FN_AGREGAR_TARJETA_ENTIDAD_LENEL = 'agregarTarjetaEntidadLenel';
    const FN_AGREGAR_TARJETA_VISITANTE_LENEL = 'agregarTarjetaVisitanteLenel';
    const FN_MODIFICAR_TARJETA_ENTIDAD_LENEL = 'modificarTarjetaEntidadLenel';
    const FN_CAMBIAR_TARJETA_ENTIDAD_LENEL = 'cambiarTarjetaEntidadLenel';
    const FN_BAJAR_TARJETA_LENEL = 'bajarTarjetaEntidadLenel';
    const FN_DESHABILITAR_TARJETA_LENEL = 'deshabilitarEntidadLenel';
    const FN_OBTENER_TARJETA_ENTIDAD_LENEL = 'obtenerTarjetaEntidadLenel';
    const FN_AGREGAR_PERSONA_LENEL = 'agregarPersonaLenel';
    const FN_MODIFICAR_PERSONA_LENEL = 'modificarPersonaLenel';
    const FN_BAJAR_PERSONA_LENEL = 'bajarEntidadLenel';
    const FN_AGREGAR_MAQUINA_LENEL = 'agregarMaquinaLenel';
    const FN_MODIFICAR_MAQUINA_LENEL = 'modificarMaquinaLenel';
    const FN_BAJAR_MAQUINA_LENEL = 'bajarEntidadLenel';
    const FN_AGREGAR_VEHICULO_LENEL = 'agregarVehiculoLenel';
    const FN_MODIFICAR_VEHICULO_LENEL = 'modificarVehiculoLenel';
    const FN_BAJAR_VEHICULO_LENEL = 'bajarEntidadLenel';
    const FN_AGREGAR_VISITANTE_LENEL = 'agregarVisitanteLenel';
    const FN_MODIFICAR_VISITANTE_LENEL = 'modificarVisitanteLenel';
    const FN_BAJAR_VISITANTE_LENEL = 'bajarVisitanteLenel';
    #endregion

    #region Public methods 
    #region Tarjeta 
    public static function agregarTarjetaEntidadLenel(
        string $documento,
        int $matricula,
        int $estado,
        int $tipo,
        string $fechaDesde,
        string $fechaHasta,
        string $entidad,
        ?string $tag = ''
    ) {
        $tipo = self::comprobarCategoria($entidad, $tipo);
        $estado = $estado == self::ESTADO_ACTIVO ? self::ESTADO_ACTIVO : self::ESTADO_INACTIVO;
        $res = '';

        /**
         * Compruebo si la vigencia es durante el mismo día.
         * Debido a que OnGuard no permite una vigencia para el mismo dia,
         * es que desde FSAcceso incrementamos en un día la vigencia.
         */
        if ($fechaDesde === $fechaHasta) {
            $fechaHastaObj = new \DateTime(str_replace('/', '-', $fechaHasta));
            $fechaHastaObj->modify('+1 day');
            $fechaHasta = $fechaHastaObj->format('d/m/Y');
        }

        if ($entidad === self::ENTIDAD_VISITANTE) {
            $function = self::FN_AGREGAR_TARJETA_VISITANTE_LENEL;
            $args = [$documento, $matricula, $estado, $tipo, $fechaDesde, $fechaHasta, self::CONTINGENCY_O, &$res];
        } else {
            $function = self::FN_AGREGAR_TARJETA_ENTIDAD_LENEL;
            $args = [$documento, $matricula, $estado, $tipo, $fechaDesde, $fechaHasta, self::CONTINGENCY_O, $tag, &$res];
        }

        return self::call($function, $args, count($args) - 1);
    }

    public static function modificarTarjetaEntidadLenel(
        string $documento,
        int $matricula,
        int $estado,
        int $tipo,
        string $fechaDesde,
        string $fechaHasta,
        string $entidad,
        ?string $tag = ''
    ) {
        $tipo = self::comprobarCategoria($entidad, $tipo);
        $estado = $estado == self::ESTADO_ACTIVO ? self::ESTADO_ACTIVO : self::ESTADO_INACTIVO;

        /**
         * Compruebo si la vigencia es durante el mismo día.
         * Debido a que OnGuard no permite una vigencia para el mismo dia,
         * es que desde FSAcceso incrementamos en un día la vigencia.
         */
        if ($fechaDesde === $fechaHasta) {
            $fechaHastaObj = new \DateTime(str_replace('/', '-', $fechaHasta));
            $fechaHastaObj->modify('+1 day');
            $fechaHasta = $fechaHastaObj->format('d/m/Y');
        }

        if ($entidad === self::ENTIDAD_VISITANTE) {
            return self::agregarTarjetaEntidadLenel($documento, $matricula, $estado, $tipo, $fechaDesde, $fechaHasta, $entidad, $tag);
        }

        $response = '';
        $function = self::FN_MODIFICAR_TARJETA_ENTIDAD_LENEL;
        $args = [
            $documento,
            $matricula,
            $estado,
            $tipo,
            $fechaDesde,
            $fechaHasta,
            true,
            self::CONTINGENCY_O,
            $tag ?? '',
            &$response,
        ];
        return self::call($function, $args, count($args) - 1);
    }

    public static function cambiarTarjetaEntidadLenel(
        $documento, 
        $nroTarjeta, 
        $estado, 
        $tipo, 
        $fechaDesde, 
        $fechaHasta, 
        $entidad, 
        $tag = ''
    ) {
        self::comprobarCategoria($entidad, $tipo);
        
        $estado = $estado == self::ESTADO_ACTIVO ? self::ESTADO_ACTIVO : self::ESTADO_INACTIVO;

        $resp = '';
        //Function cambiarTarjetaEntidadLenel(ByVal documento As String, ByVal nroTarjeta As String, ByVal estado As String, ByVal Tipo As String, ByVal fechaDesde As String, ByVal fechaHasta As String, ByRef sRespuesta As String) As Boolean
        return self::call(self::FN_CAMBIAR_TARJETA_ENTIDAD_LENEL, [
            $documento,
            $nroTarjeta,
            $estado,
            $tipo,
            $fechaDesde,
            $fechaHasta,
            $tag,
            &$resp           
        ], 7);
    }

    public static function obtenerTarjetaEntidadLenel(string $documento) {
        $tarjetaJSON = self::call(self::FN_OBTENER_TARJETA_ENTIDAD_LENEL, [$documento], -1, 0, true);
        
        if (!empty($tarjetaJSON)) {
            return json_decode($tarjetaJSON);
        }
        
        return null;
    }
    
    public static function bajarTarjetaEntidadLenel($documento) {
        $resp = '';
        // ?Function bajarTarjetaEntidadLenel(ByVal documento As String, ByRef sRespuesta As String) As Boolean Implements IFSLenel.bajarTarjetaEntidadLenel
        return self::call(self::FN_BAJAR_TARJETA_LENEL, [
            $documento,
            &$resp
        ], 1);
    }

    public static function deshabilitarEntidadLenel($documento) {
        $resp = '';
        // Function deshabilitarEntidadLenel(ByVal documento As String, ByRef sRespuesta As String) As Boolean
        return self::call(self::FN_DESHABILITAR_TARJETA_LENEL, [
            $documento,
            &$resp
        ], 1);
    }
    #endregion

    #region Persona 
    /**
     * Dar de alta una persona en OnGuard
     * 
     * @param string $documento
     * @param string $nombres
     * @param string $apellidos
     * @param string $cargo
     * @param string $categoria Nombre de la Categoria
     * @param string $empresa
     * @param string $contrato
     * @param mixed $transporte
     * @param string|null $ciudad
     * @param string|null $direccion
     * @param string|null $correo
     * @param int $matricula Número de matrícula asignado al visitante (badge_id en OnGuard)
     * @param int $estado Estado del visitante en OnGuard
     * @param int $tipo Tipo de entidad correspondiente en OnGuard (catLenel)
     * @param string $fechaDesde Fecha de activación
     * @param string $fechaHasta Fecha de desactivación
     * @param string $contingencia Especifíca el método de contingencia ante un problema
     * 
     * @return bool Indica si la persona fue dada de alta en OnGuard
     */
    public static function altaPersona(
        string $documento,
        string $nombres,
        string $apellidos,
        ?string $cargo = '',
        string $categoria,
        ?string $empresa = '',
        string $contrato = '',
        $transporte = 0,
        ?string $ciudad = '',
        ?string $direccion = '',
        ?string $correo = '',
        int $matricula = null,
        int $estado = 0,
        int $tipo = 0,
        string $fechaDesde = null,
        string $fechaHasta = null,
        string $contingencia = self::CONTINGENCY_O
    ): bool
    {
        $categoria = self::comprobarCategoria(self::ENTIDAD_PERSONA, $categoria);
        $response = '';
        $args = [
            $nombres,
            $apellidos,
            $documento,
            $empresa,
            $ciudad,
            $categoria,
            $direccion,
            $cargo,
            $contrato,
            $correo,
            $transporte,
            $contingencia,
            &$response,
        ];
        $res = self::call(self::FN_AGREGAR_PERSONA_LENEL, $args, count($args) - 1);

        if (!!$res && !empty($matricula)) {
            if (self::agregarTarjetaEntidadLenel($documento, $matricula, $estado, $tipo, $fechaDesde, $fechaHasta, self::ENTIDAD_PERSONA)) {
                LogAuditoria::log(
                    LogAuditoria::FSA_USER_DEFAULT,
                    implode('.', [self::NAME, self::ENTIDAD_PERSONA]),
                    self::FN_AGREGAR_PERSONA_LENEL,
                    [],
                    $documento,
                    sprintf('%s (%s)', implode(' ', [$nombres, $apellidos]), $documento)
                );
                return true;
            }
            else {
                self::bajaPersona($documento);
                return false;
            }
        }

        return !!$res;
    }

    /**
     * Modificar una persona en OnGuard
     * 
     * @param string $documento
     * @param string $nombres
     * @param string $apellidos
     * @param string $cargo
     * @param string $categoria Nombre de la Categoria
     * @param string $empresa
     * @param string $contrato
     * @param mixed $transporte
     * @param string|null $ciudad
     * @param string|null $direccion
     * @param string|null $correo
     * @param int $matricula Número de matrícula asignado al visitante (badge_id en OnGuard)
     * @param int $estado Estado del visitante en OnGuard
     * @param int $tipo Tipo de entidad correspondiente en OnGuard (catLenel)
     * @param string $fechaDesde Fecha de activación
     * @param string $fechaHasta Fecha de desactivación
     * @param string $contingencia Especifíca el método de contingencia ante un problema
     * 
     * @return bool Indica si la persona fue dada de alta en OnGuard
     */
    public static function modificacionPersona(
        string $documento,
        string $nombres,
        string $apellidos,
        ?string $cargo = '',
        string $categoria,
        ?string $empresa = '',
        string $contrato = '',
        $transporte = 0,
        ?string $ciudad = '',
        ?string $direccion = '',
        ?string $correo = '',
        int $matricula = null,
        int $estado = 0,
        int $tipo = 0,
        string $fechaDesde = null,
        string $fechaHasta = null,
        string $contingencia = self::CONTINGENCY_O
    ): bool
    {
        $categoria = self::comprobarCategoria(self::ENTIDAD_PERSONA, $categoria);
        $response = '';
        $args = [
            $nombres,
            $apellidos,
            $documento,
            $empresa,
            $ciudad,
            $categoria,
            $direccion,
            $cargo,
            $contrato,
            $correo,
            $transporte,
            // $contingencia,
            &$response,
        ];
        $res = self::call(self::FN_MODIFICAR_PERSONA_LENEL, $args, count($args) - 1);

        if (!!$res && !empty($matricula)) {
            if (self::modificarTarjetaEntidadLenel($documento, $matricula, $estado, $tipo, $fechaDesde, $fechaHasta, self::ENTIDAD_PERSONA)) {
                LogAuditoria::log(
                    LogAuditoria::FSA_USER_DEFAULT,
                    implode('.', [self::NAME, self::ENTIDAD_PERSONA]),
                    self::FN_MODIFICAR_PERSONA_LENEL,
                [],
                    $documento,
                    sprintf('%s (%s)', implode(' ', [$nombres, $apellidos]), $documento)
                );
                return true;
            } else {
                return false;
            }
        }

        return !!$res;
    }

    public static function bajaPersona(string $documento): bool
    {
        $response = '';
        self::call(self::FN_BAJAR_PERSONA_LENEL, [$documento, &$response], 1);
        LogAuditoria::log(LogAuditoria::FSA_USER_DEFAULT, implode('.', [self::NAME, self::ENTIDAD_PERSONA]), self::FN_BAJAR_PERSONA_LENEL, [], $documento, $documento);
        return true;
    }
    #endregion

    #region Vehiculo 
    public static function altaVehiculo(
        $documento,
        $marca,
        $modelo, 
        $tipoVehiculo,
        $categoria,
        $empresa,
        $contrato,
        $nroTarjeta = null,
        $estado = 0,
        $tipo = 0,
        $fechaDesde = null,
        $fechaHasta = null,
        $resolvConf = self::CONTINGENCY_O,
        $tag = ''
    )
    {
        self::comprobarCategoria(self::ENTIDAD_VEHICULO, $categoria);

        $resp = "";

        $data = [
            $modelo,
            $marca,
            $documento,
            $empresa,
            $categoria,
            $tipoVehiculo,
            $contrato,
            $resolvConf,
            &$resp
        ];

        // Function agregarVehiculoLenel(ByVal modelo As String, ByVal marca As String, ByVal documento As String, ByVal empresa As String, ByVal Categoria As String, ByVal Tipo As String, ByVal contrato As String, ByRef sRespuesta As String) As Boolean
        $success = self::call(self::FN_AGREGAR_VEHICULO_LENEL, $data, count($data) - 1);

        Log::info('[ONGUARD] fsa_ext_onguard::altaVehiculo » $data => ' . json_encode($data));
        Log::info('[ONGUARD] fsa_ext_onguard::altaVehiculo » $success => ' . json_encode($success));
        Log::info('[ONGUARD] fsa_ext_onguard::altaVehiculo » $nroTarjeta => ' . json_encode($nroTarjeta));

        if ($success && !empty($nroTarjeta)) {
            Log::info('[ONGUARD] fsa_ext_onguard::altaVehiculo » Se procede a dar de alta la tarjeta');
            if (self::agregarTarjetaEntidadLenel($documento, $nroTarjeta, $estado, $tipo, $fechaDesde, $fechaHasta, self::ENTIDAD_VEHICULO, $tag)) {
                Log::info('[ONGUARD] fsa_ext_onguard::altaVehiculo » Se dio de alta correctamente');
                
                LogAuditoria::log(
                    LogAuditoria::FSA_USER_DEFAULT,
                    implode('.', [self::NAME, self::ENTIDAD_VEHICULO]),
                    self::FN_AGREGAR_VEHICULO_LENEL,
                    $data,
                    $documento,
                    sprintf('%s %s %s (%s)', $tipoVehiculo, $marca, $modelo, $documento)
                );

                return true;
            } else {
                Log::error('[ONGUARD] fsa_ext_onguard::altaVehiculo » No se pudo dar de alta correctamente. Se procede a dar de baja');
                self::bajaVehiculo($documento);
                throw new OnGuardException('No se pudo dar de alta correctamente. Se procede a dar de baja');
            }
        } else {
            Log::info('[ONGUARD] fsa_ext_onguard::altaVehiculo » Nro Tarjeta vacío');
            return $success;
        }
    }

    public static function modificacionVehiculo(
        $documento,
        $marca,
        $modelo,
        $tipoVehiculo,
        $categoria,
        $empresa,
        $contrato,
        $nroTarjeta = null,
        $estado = 0,
        $tipo = 0,
        $fechaDesde = null,
        $fechaHasta = null,
        $resolvConf = self::CONTINGENCY_O,
        $tag = ''
    )
    {
        self::comprobarCategoria(self::ENTIDAD_VEHICULO, $categoria);

        $resp = '';

        $data = [
            $modelo,
            $marca,
            $documento,
            $empresa,
            $categoria,
            $tipoVehiculo,
            $contrato,
            &$resp
        ];

        // Function modificarVehiculoLenel(ByVal modelo As String, ByVal marca As String, ByVal documento As String, ByVal empresa As String, ByVal Categoria As String, ByVal Tipo As String, ByVal contrato As String, ByRef sRespuesta As String) As Boolean Implements IFSLenel.modificarVehiculoLenel
        $success = self::call(self::FN_MODIFICAR_VEHICULO_LENEL, $data, 7);

        if ($success && !empty($nroTarjeta)) {
            if (self::modificarTarjetaEntidadLenel($documento, $nroTarjeta, $estado, $tipo, $fechaDesde, $fechaHasta, self::ENTIDAD_VEHICULO, $tag)) {
                LogAuditoria::log(
                    LogAuditoria::FSA_USER_DEFAULT,
                    implode('.', [self::NAME, self::ENTIDAD_VEHICULO]),
                    self::FN_MODIFICAR_VEHICULO_LENEL,
                    $data,
                    $documento,
                    sprintf('%s %s %s (%s)', $tipoVehiculo, $marca, $modelo, $documento)
                );
                return true;
            } else {
                //self::bajaVehiculo($documento);
                //return false;
                return true;
            }
        } else {
            return $success;
        }
    }

    public static function bajaVehiculo($documento)
    {
        $resp = "";
        // Function bajarEntidadLenel(ByVal documento As String, ByRef sRespuesta As String) As Boolean Implements IFSLenel.bajarEntidadLenel
        self::call(self::FN_BAJAR_VEHICULO_LENEL, [
            $documento,
            &$resp
        ], 1);

        LogAuditoria::log(LogAuditoria::FSA_USER_DEFAULT, implode('.', [self::NAME, self::ENTIDAD_VEHICULO]), self::FN_BAJAR_VEHICULO_LENEL, [], $documento, $documento);

        return true;
    }
    #endregion
    
    #region Maquinas 
    public static function altaMaquina(
        $documento, 
        $marca, 
        $modelo, 
        $tipoMaquina, 
        $categoria, 
        $empresa, 
        $contrato, 
        $nroTarjeta = null, 
        $estado = 0, 
        $tipo = 0, 
        $fechaDesde = null, 
        $fechaHasta = null, 
        $resolvConf = self::CONTINGENCY_O
    )
    {
        self::comprobarCategoria(self::ENTIDAD_MAQUINA, $categoria);

        // Si estoy intentando dar de alta una máquina inactiva y su Nro Serie
        // está siendo utilizado en OnGuard entonces tiro un error
        // if (empty($estado) && self::existeEntidadLenel($documento)) {
        //     throw new Exception("Existe una máquina en OnGuard con el documento ingresado", -13231);
        // }

        $resp = "";

        $data = [
            $modelo,
            $marca,
            $documento,
            $empresa,
            $categoria,
            $tipoMaquina,
            $contrato,
            $resolvConf,
            &$resp
        ];
        // ?Public Function agregarMaquinaLenel(ByVal modelo As String, ByVal marca As String, ByVal documento As String, ByVal empresa As String, ByVal Categoria As String, ByVal Tipo As String, ByVal contrato As String, ByRef sRespuesta As String) As Boolean Implements IFSLenel.agregarMaquinaLenel
        $success = self::call(self::FN_AGREGAR_MAQUINA_LENEL, $data, count($data) - 1);

        if ($success && !empty($nroTarjeta)) {
            if (self::agregarTarjetaEntidadLenel($documento, $nroTarjeta, $estado, $tipo, $fechaDesde, $fechaHasta, self::ENTIDAD_MAQUINA)) {
                LogAuditoria::log(
                    LogAuditoria::FSA_USER_DEFAULT,
                    implode('.', [self::NAME, self::ENTIDAD_MAQUINA]),
                    self::FN_AGREGAR_MAQUINA_LENEL,
                    $data,
                    $documento,
                    sprintf('%s %s %s (%s)', $tipoMaquina, $marca, $modelo, $documento)
                );

                return true;
            } else {
                self::bajaMaquina($documento);
                return false;
            }
        } else {
            return $success;
        }
    }

    public static function modificacionMaquina(
        $documento,
        $marca, 
        $modelo, 
        $tipoMaquina, 
        $categoria, 
        $empresa, 
        $contrato, 
        $nroTarjeta = null, 
        $estado = 0, 
        $tipo = 0, 
        $fechaDesde = null, 
        $fechaHasta = null, 
        $resolvConf = self::CONTINGENCY_O
    )
    {
        self::comprobarCategoria(self::ENTIDAD_MAQUINA, $categoria);

        $resp = "";

        $data = [
            $modelo,
            $marca,
            $documento,
            $empresa,
            $categoria,
            $tipoMaquina,
            $contrato,
            &$resp
        ];

        // Function modificarMaquinaLenel(ByVal modelo As String, ByVal marca As String, ByVal documento As String, ByVal empresa As String, ByVal Categoria As String, ByVal Tipo As String, ByVal contrato As String, ByRef sRespuesta As String) As Boolean Implements IFSLenel.modificarMaquinaLenel
        $success = self::call(self::FN_MODIFICAR_MAQUINA_LENEL, $data, count($data) - 1);

        if ($success && !empty($nroTarjeta)) {
            if (self::modificarTarjetaEntidadLenel($documento, $nroTarjeta, $estado, $tipo, $fechaDesde, $fechaHasta, "M")) {
                LogAuditoria::log(
                    LogAuditoria::FSA_USER_DEFAULT,
                    implode('.', [self::NAME, self::ENTIDAD_MAQUINA]),
                    self::FN_MODIFICAR_MAQUINA_LENEL,
                    $data,
                    $documento,
                    sprintf('%s %s %s (%s)', $tipoMaquina, $marca, $modelo, $documento)
                );

                return true;
            } else {
                //self::bajaMaquina($documento);
                //return false;
                return true;
            }
        } else {
            return $success;
        }
    }

    public static function bajaMaquina($documento)
    {
        $resp = "";
        // Function bajarEntidadLenel(ByVal documento As String, ByRef sRespuesta As String) As Boolean Implements IFSLenel.bajarEntidadLenel
        $r = self::call(self::FN_BAJAR_MAQUINA_LENEL, [
            $documento,
            &$resp
        ], 1);

        LogAuditoria::log(LogAuditoria::FSA_USER_DEFAULT, implode('.', [self::NAME, self::ENTIDAD_MAQUINA]), self::FN_BAJAR_MAQUINA_LENEL, [], $documento, $documento);

        return $r;
    }
    #endregion

    #region Visitante 
    /**
     * Dar de alta un visitante en OnGuard
     * 
     * @param string $documento Documento del visitante
     * @param string $nombres Nombres del visitante
     * @param string $apellidos Apellidos del visitante
     * @param string $categoria Categoria en OnGuard
     * @param string $empresa Empresa del visitante
     * @param int $matricula Número de matrícula asignado al visitante (badge_id en OnGuard)
     * @param int $estado Estado del visitante en OnGuard
     * @param int $tipo Tipo de entidad correspondiente en OnGuard
     * @param string $fechaDesde Fecha de activación
     * @param string $fechaHasta Fecha de desactivación
     * @param string $contingencia Especifíca el método de contingencia ante un problema
     * 
     * @return bool Indica si el visitante fue dado de alta en OnGuard
     */
    public static function altaVisitante(
        string $documento,
        string $nombres,
        string $apellidos,
        string $empresaVisitante, // Antes era $categoria.
        string $empresa,
        int $matricula = null,
        int $estado = 0,
        int $tipo = 0,
        string $fechaDesde = null,
        string $fechaHasta = null,
        string $contingencia = self::CONTINGENCY_O
    ): bool
    {
        // $categoria = self::comprobarCategoria($app, self::ENTIDAD_VISITANTE, $categoria);
        $response = '';
        $args = [
            $nombres,
            $apellidos,
            $documento,
            $empresa,
            $empresaVisitante, // Antes era $categoria.
            &$response,
        ];
        $res = self::call(self::FN_AGREGAR_VISITANTE_LENEL, $args, count($args) - 1);

        if (!!$res && !empty($matricula)) {
            if (self::agregarTarjetaEntidadLenel($documento, $matricula, $estado, $tipo, $fechaDesde, $fechaHasta, self::ENTIDAD_VISITANTE)) {
                LogAuditoria::log(
                    LogAuditoria::FSA_USER_DEFAULT,
                    implode('.', [self::NAME, self::ENTIDAD_VISITANTE]),
                    self::FN_AGREGAR_VISITANTE_LENEL,
                    $args,
                    $documento,
                    sprintf('%s %s (%s)', $nombres, $apellidos, $documento)
                );
                return true;
            } else {
                self::bajaVisitante($documento);
                return false;
            }
        }

        return !!$res;
    }

    public static function modificacionVisitante(
        string $documento,
        string $nombres,
        string $apellidos,
        string $empresaVisitante, // Antes era $categoria.
        string $empresa,
        int $matricula = null,
        int $estado = 0,
        int $tipo = 0,
        string $fechaDesde = null,
        string $fechaHasta = null,
        string $contingencia = self::CONTINGENCY_O
    ): bool
    {
        // $categoria = self::comprobarCategoria($app, self::ENTIDAD_VISITANTE, $categoria);
        $response = '';
        $args = [
            $nombres,
            $apellidos,
            $documento,
            $empresa,
            $empresaVisitante, // Antes era $categoria.
            &$response,
        ];
        $res = self::call(self::FN_MODIFICAR_VISITANTE_LENEL, $args, count($args) - 1);

        if (!!$res && !empty($matricula)) {
            if (self::modificarTarjetaEntidadLenel($documento, $matricula, $estado, $tipo, $fechaDesde, $fechaHasta, self::ENTIDAD_VISITANTE)) {
                LogAuditoria::log(
                    LogAuditoria::FSA_USER_DEFAULT,
                    implode('.', [self::NAME, self::ENTIDAD_VISITANTE]),
                    self::FN_MODIFICAR_VISITANTE_LENEL,
                    $args,
                    $documento,
                    sprintf('%s %s (%s)', $nombres, $apellidos, $documento)
                );

                return true;
            } else {
                // self::bajaVisitante($app, $documento);
                // return false;
                return true; // No comprendo por qué esta forzada la respuesta
            }
        }

        return !!$res;
    }

    public static function bajaVisitante(string $documento): bool
    {
        $response = '';
        $args = [
            $documento,
            &$response,
        ];

        if (self::call(self::FN_BAJAR_VISITANTE_LENEL, $args, count($args) - 1)) {
           LogAuditoria::log(
                LogAuditoria::FSA_USER_DEFAULT,
                implode('.', [self::NAME, self::ENTIDAD_VISITANTE]),
                self::FN_BAJAR_VISITANTE_LENEL,
                $args,
                $documento,
                $documento
            );

            return true;
        }
        return false;
    }
    #endregion
    #endregion

    #region Private methods 
    /**
     * Llamar función de FSLenel
     * 
     * @param string $function Función de FSLenel
     * @param array $args Argumentos que se pasaran a la función
     * @param int $iRes Índice de la respuesta en los argumentos
     * @param int $attempts Número actual de intentos
     * @param bool $returnsValue Indica si se espera una respuesta o no
     * 
     * @return string|bool En caso de consulta devuelve una cadena de texto y en caso de acción devuelve true
     * 
     * @throws OnGuardException En caso de que FSLenel devuelve un error
     * @throws Exception Para errores ajenos a OnGuard
     */
    private static function call(string $function, array $args, int $iRes, int $attempts = 0, bool $returnsValue = false)
    {
        if (env('INTEGRADO', 'false') !== true) {
            return true;
        }

        if ($iRes >= 0) {
            $args[$iRes] = '[OUT]';
        }

        $lenelArgs = self::parseArgs($args);

        Log::info('LenelArgs: ' . json_encode($lenelArgs));
        $cmd = env('ONGUARD_PATH');
        if (empty($cmd)) {
            throw new Exception('Variable de entorno "ONGUARD_PATH" no definida');
        }
        $cmdArgs = $function . ' "' . implode('" "', $lenelArgs) . '"';

        if (env('ONGUARD_REMOTE', 'false') === true) {
            $exec = 'ssh ' . env('ONGUARD_SSH', '') . ' \'"' . $cmd . '" ' . $cmdArgs . '\'';
        } else {
            $exec = implode(' ', [$cmd, $cmdArgs]);
        }
        Log::info('LenelCmd: ' . $exec);
        $output = shell_exec($exec);

        $resCode = substr($output, 0, strpos($output, ':'));
        $resValue = trim(substr($output, strpos($output, ':') + 1));

        Log::info('LenelRes: ' . '[' . $resCode . '] ' . $resValue);

        if ($returnsValue) {
            return $resValue;
        } else if ($resCode === 'OK') {
            return true;
        } else if (++$attempts < self::MAX_ATTEMPS) {
            sleep(self::SLEEP_BETWEEN_ATTEMPS);
            Log::warning('LenelWarn: Retrying... Attempt #' . $attempts);
            return self::call($function, $args, $iRes, $attempts, $returnsValue);
        } else {
            $err = utf8_encode($resValue);
            if (empty($err)) {
                $err = shell_exec(implode(' ', [$cmd, 'obtenerUltimoError']));
            }
            if (empty($err)) {
                $err = 'Unspecified OnGuard API Error';
            }
            if ($err === 'False') {
                $err = 'Error al instanciar API de OnGuard';
            }
            Log::error('LenelError: ' . $err);
            throw new OnGuardException($err);
        }
    }

    /**
     * Obtiene la categoria por defecto de OnGuard en caso de no ser establecida
     * 
     * @param string $entidad Entidad que se envía a OnGuard
     * @param int|string|null $categoria Categoría de OnGuard
     * 
     * @return int|string|null Categoría por defecto o categoría indicada
     * 
     * @throws OnGuardException
     */
    private static function comprobarCategoria(string $entidad, &$categoria = null)
    {
        if (!empty($categoria)) {
            return $categoria;
        }
        $param = [
            self::ENTIDAD_MAQUINA => 'LNLCodMaquina',
            self::ENTIDAD_PERSONA => 'LNLCodFijo',
            self::ENTIDAD_VISITANTE => 'LNLCodTransito',
            self::ENTIDAD_VEHICULO => 'LNLCodVehiculo',
        ];
        $result = DB::selectOne('SELECT Valor FROM Parametros WHERE IdParametro = ?', [$param[$entidad]]);
        if (empty($result) || empty($result->Valor)) {
            throw new OnGuardException('No se pudo obtener el valor de la categoría por defecto para la entidad');
        }
        return $categoria = $result->Valor;
    }

    /**
     * Parsea los argumentos que se enviaran a FSLenel
     * 
     * @param array $args Argumentos que se enviaran a FSLenel
     * 
     * @return array Argumentos parseados
     */
    private static function parseArgs(array $args): array
    {
        $argumentsArrObj = new \ArrayObject($args);
        $decoded = $argumentsArrObj->getArrayCopy();
        array_walk($decoded, function (&$value) {
            if (is_string($value)) {
                $search = array('\\');
                $replace = array('\\\\');

                $value = str_replace($search, $replace, $value);
                $value = utf8_decode($value);
                $value = trim($value);
            }

            if (empty($value) &&
            $value !== false &&
            $value !== 0) {
                $value = "[NULL]";
            }
        });
        return $decoded;
    }
    #endregion

}
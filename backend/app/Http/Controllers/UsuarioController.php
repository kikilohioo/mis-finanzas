<?php

namespace App\Http\Controllers;

use App\FsUtils;
use App\Mails\Usuario\UsuarioCreadoMail;
use App\Mails\Usuario\UsuarioCreadoCambiarContraseniaMail;
use App\Mails\Usuario\RecuperarContraseniaMail;
use App\Models\LogAuditoria;
use App\Models\Usuario;
use App\Models\UsuarioRestaurarContrasena;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class UsuarioController extends Controller
{

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

    protected function esPTC()
    {
        // $class = new static($this->req);
        // return $class instanceof \App\Http\Controllers\PTC\UsuarioController;
        return $this instanceof \App\Http\Controllers\PTC\UsuarioController;
    }

    protected function exigirArgsSegunABM($Args)
    {
        $rolSOL = false;

        if (!empty($Args['PTCRoles'])) {
            foreach ($Args['PTCRoles'] as $r) {
                if (!empty($r)) {
                    if ($r == 'SOL') {
                        $rolSOL = true;
                        break;
                    }
                }
            }
        }

        if ($this->esPTC()) {
            Usuario::exigirArgs($Args, array(array("PTCGestion", "PTCAreas"), "PTCRoles", "PTC"));

            if ($rolSOL) {
                Usuario::exigirArgs($Args, ["Empresas"]);
            }
        } else {
            Usuario::exigirArgs($Args, array(array("Gestion", "Empresas"), "Funciones"));
        }

        if ($rolSOL && empty($Args["Empresas"])) {
            throw new HttpException(409, "Debe seleccionarse al menos una empresa");
        }
    }

    protected function chequearArgs(&$Args)
    {
        if ($this->esPTC()) {
            $Args['EsPTC'] = 1;

            $rolNoSOL = false;

            if (!empty($Args['PTCRoles'])) {
                foreach ($Args['PTCRoles'] as $r) {
                    if (!empty($r)) {
                        if ($r != 'SOL') {
                            $rolNoSOL = true;
                            break;
                        }
                    }
                }
            }

            // Con al menos un rol que no sea solicitante el usuario adquiere
            // el atributo Gestión
            $Args['Gestion'] = $rolNoSOL ? 1 : 0;

            if (!empty($Args['EsPTC']) && empty(array_filter($Args['Funciones'], function ($value){ if(strpos($value, "chkPTC") !== false){return true;}}))) {
                throw new HttpException(409, "Los usuarios de Permisos de Trabajo deben tener al menos una función de Permisos de Trabajo");
            } else if (empty($Args['EsPTC']) && !empty(array_filter($Args['Funciones'], function ($value){ if(strpos($value, "chkPTC") !== false){return true;}}))) {
                throw new HttpException(409, "El usuario no puede tener funciones de Permisos de Trabajo si no es usuario de Permisos de Trabajo");
            }

            return false;
        } else {
            $Args['PTCGestion'] = $Args['Gestion'];
            $Args['PTCAdministrador'] = $Args['Administrador'];
        }

        if (!empty($Args['Contrasenia']) && $Args['Contrasenia'] != $Args['ConfContrasenia']) {
            throw new HttpException(409, "La contrasena y su confirmacion no coinciden");
        }
    }

    public function index()
    {
        $bindings = [];

        $Args = $this->req->All();
        $Usuario = $this->user;

        $sql = "SELECT  DISTINCT
                        u.IdUsuario, 
                        u.Nombre,
                        u.Email,
                        Estado = CASE u.Estado WHEN 1 THEN 'Activo' ELSE 'Inactivo' END,
                        SoloLectura = CASE u.SoloLectura WHEN 1 THEN 'Sí' ELSE 'No' END,
                        Administrador = CASE u.Administrador WHEN 1 THEN 'Sí' ELSE 'No' END,
                        Gestion = CASE u.Gestion WHEN 1 THEN 'Sí' ELSE 'No' END,
                        PTCAdministrador = CASE u.PTCAdministrador WHEN 1 THEN 'Sí' ELSE 'No' END,
                        PTCGestion = CASE u.PTCGestion WHEN 1 THEN 'Sí' ELSE 'No' END,
                        Roles = (STUFF(
                                    (SELECT ', ' + pr.Nombre
                                        FROM PTCRoles pr
                                        INNER JOIN PTCRolesUsuarios pru ON pru.Codigo = pr.Codigo
                                        WHERE pru.IdUsuario = u.IdUsuario
                                        ORDER BY pr.Nombre
                                        FOR XML PATH (''))
                                , 1, 2, '')),
                        Areas = CASE
                                    WHEN u.PTCGestion = 1 THEN
                                        '[Todas]'
                                    ELSE
                                        (STUFF(
                                            (SELECT ', ' + pa.Nombre
                                                FROM PTCAreas pa
                                                INNER JOIN PTCAreasUsuarios pau ON pau.IdArea = pa.IdArea
                                                WHERE pau.IdUsuario = u.IdUsuario
                                                ORDER BY pa.Nombre
                                                FOR XML PATH (''))
                                        , 1, 2, ''))
                                    END,
                        Empresa = CASE 
                                    WHEN u.Gestion = 1 THEN 
                                        '[Todas]'
                                    ELSE 
                                        (STUFF(
                                            (SELECT ', ' + e.Nombre
                                                FROM UsuariosEmpresas ue
                                                INNER JOIN Empresas e ON ue.Documento = e.Documento AND ue.IdTipoDocumento = e.IdTipoDocumento
                                                WHERE ue.IdUsuario = u.IdUsuario
                                                ORDER BY e.Nombre
                                                FOR XML PATH (''))
                                        , 1, 2, ''))
                                    END,
                        u.EstadoObservacion
                FROM Usuarios u left join UsuariosEmpresas ue2 on u.IdUsuario = ue2.IdUsuario
                WHERE u.Baja = 0";

        if(isset($Args['IdEmpresa']) && !empty($Args['IdEmpresa'])){
            $IdEmpresa = fsUtils::explodeId($Args['IdEmpresa']);
            $bindings[':documento'] = $IdEmpresa[0];
            $bindings[':idTipoDocumento'] = $IdEmpresa[1];
            $sql .= "and (u.Gestion = 1 or ue2.Documento = :documento and ue2.IdTipoDocumento = :idTipoDocumento)";
        }

        if ($this->esPTC()) {
            $sql .= " AND u.PTC = :EsPTC";
            $bindings[':EsPTC'] = $Usuario->PTC;
        }

        if (!empty($Args['Busqueda'])) {

                $sql .= " AND    (u.IdUsuario COLLATE Latin1_general_CI_AI LIKE :Busqueda1 COLLATE Latin1_general_CI_AI OR
                                u.Nombre COLLATE Latin1_general_CI_AI LIKE :Busqueda2 COLLATE Latin1_general_CI_AI)";
                $bindings[':Busqueda1'] = "%" . $Args['Busqueda'] . "%";
                $bindings[':Busqueda2'] = "%" . $Args['Busqueda'] . "%";
        }

        if (!empty($Args['CustomCheckbox'])) {
            $sql .= " AND NOT EXISTS (SELECT 1 FROM UsuariosPersonasFisicas upf WHERE upf.IdUsuario = u.IdUsuario); ";
        }

        $registros = DB::select($sql, $bindings);

        $output = $this->req->input('output', 'json');

        if ($output !== 'json') {
            $dataOutput = array_map(function($item) {
                return [
                    'IdUsuario' => $item->IdUsuario,
                    'Nombre' => $item->Nombre,
                    'Estado' => $item->Estado,
                    'SoloLectura' => $item->SoloLectura,
                    'Gestion' => $item->Gestion,
                    'Administrador' => $item->Administrador
                ];
            }, $registros);

            return $this->export($dataOutput, $output);
        }
        
        $page = (int)$this->req->input('page', 1);        
        $paginate = FsUtils::paginateArray($registros, $this->req);
        return $this->responsePaginate($paginate->items(), $paginate->total(), $page);
    }

    private function export(array $data, string $type)
    {
        $filename = 'FSAcceso-Usuarios-' . date('Ymd-his');
        $headers = [
            'IdUsuario' => 'Nombre de Usuario',
            'Nombre' => 'Nombre',
            'Estado' => 'Estado',
            'SoloLectura' => 'Sólo Lectura',
            'Gestion' => 'Gestión',
            'Administrador' => 'Administrador',
        ];

        return FsUtils::export($type, $data, $headers, $filename);
    }

    public function create($Args = [])
    {
        if(empty($Args)){
            $Args = $this->req->All();
        }
        
        if(!empty($Args['IdUsuario'])){
            $Args['IdUsuario'] = strtolower($Args['IdUsuario']);
        }
        
        $this->exigirArgsSegunABM($Args);
        $this->chequearArgs($Args);

        if (empty($Args['Email']) || !filter_var($Args['Email'], FILTER_VALIDATE_EMAIL)) {
            throw new HttpException(409, 'Debe ingresar un correo válido');
        }

        $obj = $this->show($Args['IdUsuario'], [], true);

        if (isset($obj)) {
            
            if ($obj['Baja'] == 1) {
                // Si está dado de baja lo "revivo"
                $this->update($obj['IdUsuario']);

                $entity = Usuario::find($Args['IdUsuario']);

                if (!isset($entity)) {
                    throw new NotFoundHttpException('Usuario no encontrado');
                }

                $entity->Baja = false;
                $entity->PTC = $Args['EsPTC'] ?: false;
                $entity->save();
            } else {
                $m = "El nombre de usuario está siendo utilizado por otro usuario de FSAcceso.";

                if ($this->esPTC()) {
                    $m .= " Para poder utilizar este usuario en Permisos de Trabajo comuníquese con un administrador de FSAcceso para habilitar dicha opción.";
                }

                throw new HttpException(409, $m);
            }
        }

        DB::transaction(function () use ($Args) {
            
            $entity = new Usuario($Args);
            $entity->Contrasenia = !$Args['CambiarContrasenia'] ? md5($this->req->input('Contrasenia')) : Str::random(16);
            $entity->PTC = $this->req->input('PTC');
            $entity->Baja = false;

            $entity->save();

            $this->altaEmpresas_sql($Args);
            $this->altaParametros_sql($Args);
            $this->altaFunciones_sql($Args);

            if ($this->esPTC()) {
                $this->altaAreas_sql($Args);
                $this->altaRoles_sql($Args);
                $this->altaNotificaciones_sql($Args);

                if (!empty($Args['PF'])) {
                    $idPF = FsUtils::explodeId($Args['PF']);
                    $detalle = DB::select('SELECT IdUsuario FROM UsuariosPersonasFisicas WHERE Documento = :idPF0 AND IdTipoDocumento = :idPF1 AND IdUsuario != :IdUsuario', [':idPF0' => $idPF[0], ':idPF1' => $idPF[1], ':IdUsuario' => $Args['IdUsuario']]);

                    if (!empty($detalle)) {
                        if (is_array($detalle)) $detalle = $detalle[0];
                    } else {
                        $detalle = null;
                    }

                    if ($detalle) {
                        throw new HttpException(409, 'La persona física está siendo utilizada por ' . $detalle->IdUsuario);
                    }

                    DB::table('UsuariosPersonasFisicas')->insert([
                        'IdUsuario' => $Args['IdUsuario'],
                        'Documento' => $idPF[0],
                        'IdTipoDocumento' => $idPF[1],
                    ]);
                }
            }

            if (!$Args['CambiarContrasenia']) {
                $this->altaMail($Args);
            } else {
                $tokensEnUso = [];
                do {
                    $continue = false;
                    $token = Str::random(32);
        
                    if (!in_array($token, $tokensEnUso)) {
                        $tokenEnUso = UsuarioRestaurarContrasena::where('Id', $token)->first();
                        if (isset($tokenEnUso)) {
                            $tokensEnUso[] = $tokenEnUso;
                        } else {
                            $continue = true;
                        }
                    }
                } while (!$continue);
        
                try {
                    $usuarioTokenContrasena = new UsuarioRestaurarContrasena;
                    $usuarioTokenContrasena->Id = $token;
                    $usuarioTokenContrasena->IdUsuario = $Args['IdUsuario'];
                    $usuarioTokenContrasena->Desde = new \DateTime;
        
                    $tiempoExpira = env('TOKEN_CONTRASENA_EXPIRA');
                    $fechaHoraExpira = date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s') . ' +' . $tiempoExpira . ' seconds'));
                    $usuarioTokenContrasena->Hasta = strtotime($fechaHoraExpira);
                    $usuarioTokenContrasena->Usado = false;
        
                    $usuarioTokenContrasena->save();
                
                    $this->altaMailCambiarContrasenia($Args, $usuarioTokenContrasena->Id);
                } catch (HttpException $err) {
                    if (env('APP_DEBUG') === true) {
                        return $this->responseInternalError($err->getMessage());
                    }
                    return $this->responseInternalError("Ocurrió un error interno");
                }
            }
           

            LogAuditoria::log(
                Auth::id(),
                Usuario::class,
                LogAuditoria::FSA_METHOD_CREATE,
                $Args,
                $Args['IdUsuario'],
                $Args['Nombre']
            );

        });
    }

    protected function altaMailCambiarContrasenia($Args, $token)
    {
        try {
            Mail::to($Args['Email'])->send(new UsuarioCreadoCambiarContraseniaMail($Args, $token));
        } catch (\Exception $err) {
            Log::error('Error al enviar mail de cambio de contraseña a ' . $Args['IdUsuario']);
        }
    }

    protected function altaMail($Args)
    {
        try {
            Mail::to($Args['Email'])->send(new UsuarioCreadoMail($Args));
        } catch (\Exception $err) {
            Log::error('Error al enviar usuario y contraseña a ' . $Args['IdUsuario']);
        }
    }

    public function update(string $idUsuario)
    {
        $Args = $this->req->All();

        $this->exigirArgsSegunABM($Args);
        $this->chequearArgs($Args);

        if (empty($Args['Email']) || !filter_var($Args['Email'], FILTER_VALIDATE_EMAIL)) {
            throw new HttpException(409, 'Debe ingresar un correo válido');
        }

        $u = $this->show($idUsuario)->getData();

        DB::transaction(function () use ($u, $Args, $idUsuario) {
            $bindings = [];
            $sql = "UPDATE Usuarios SET ";

            if (!empty($Args['Contrasenia'])) {
                $bindings[':Contrasenia'] = md5($Args['Contrasenia']);
                $sql .= "Contrasenia = :Contrasenia,";
            }
            if (empty($Args['Email']) || !filter_var($Args['Email'], FILTER_VALIDATE_EMAIL)) {
                throw new HttpException(409, 'Debe ingresar un correo válido');
            }

            $bindings[':Nombre'] = $Args['Nombre'];
            $bindings[':Email'] = $Args['Email'];
            $bindings[':Estado'] = $Args['Estado'];
            $bindings[':Gestion'] = $Args['Gestion'];
            $bindings[':EsPTC'] = !empty($Args['EsPTC']) || $this->esPTC() ? 1 : 0;
            $bindings[':EsContratista'] = $Args['EsContratista'];
            $bindings[':EsTercerizado'] = $Args['EsTercerizado'];

            $sql .= " EsTercerizado = :EsTercerizado, EsContratista = :EsContratista, Nombre = :Nombre, Email = :Email, Estado = :Estado, PTC = :EsPTC, Gestion = :Gestion, ";

            if ($u->Estado != $Args['Estado']) {
                $sql .= "EstadoObservacion = null, ";
            }

            if ($this->esPTC()) {
                $bindings[':Administrador'] = $u->Administrador;
                $bindings[':PTCGestion'] = $Args['PTCGestion'];
                $bindings[':RecibeNotificaciones'] = $Args['RecibeNotificaciones'];
                $sql .= " Administrador = :Administrador, PTCGestion = :PTCGestion, RecibeNotificaciones = :RecibeNotificaciones, ";
            } else {
                $bindings[':Administrador'] = $Args['Administrador'];
                $bindings[':SoloLectura'] = $Args['SoloLectura'];
                $bindings[':ApruebaVisitas'] = !empty($Args['ApruebaVisitas']);
                $sql .= " Administrador = :Administrador, SoloLectura = :SoloLectura, ApruebaVisitas = :ApruebaVisitas, ";
            }

            $bindings[':PTCAdministrador'] = !empty($Args['PTCAdministrador']);
            $bindings[':IdUsuario'] = $idUsuario;
            $sql .= " PTCAdministrador = :PTCAdministrador WHERE IdUsuario = :IdUsuario ";

            $this->altaEmpresas_sql($Args, true);
            $this->altaParametros_sql($Args, true);
            $this->altaFunciones_sql($Args, true);

            if ($this->esPTC()) {
                $this->altaAreas_sql($Args, true);
                $this->altaRoles_sql($Args, true);
                $this->altaNotificaciones_sql($Args, true);

                if (!empty($Args['PF'])) {
                    $idPF = FsUtils::explodeId($Args['PF']);
    
                    $bindings1 = [];
                    $bindings1[':idPF0'] = $idPF[0];
                    $bindings1[':idPF1'] = $idPF[1];
                    $bindings1[':IdUsuario'] = $idUsuario;
    
                    $detalle = DB::select('SELECT IdUsuario FROM UsuariosPersonasFisicas WHERE Documento = :idPF0 AND IdTipoDocumento = :idPF1 AND IdUsuario != :IdUsuario', $bindings1);
    
                    if (!empty($detalle)) {
                        if (is_array($detalle)) $detalle = $detalle[0];
                    } else {
                        $detalle = null;
                    }
    
                    if ($detalle) {
                        throw new HttpException(409, 'La persona física está siendo utilizada por ' . $detalle->IdUsuario);
                    }
    
                    $cantidad = DB::table('UsuariosPersonasFisicas')->where('IdUsuario', $idUsuario)->count();
    
                    if ($cantidad > 0) {
                        DB::table('UsuariosPersonasFisicas')
                            ->where('IdUsuario', $idUsuario)
                            ->update(['Documento' => $idPF[0], 'IdTipoDocumento' => $idPF[1]]);
                    } else {
                        DB::table('UsuariosPersonasFisicas')->insert([
                            'IdUsuario' => $idUsuario,
                            'Documento' => $idPF[0],
                            'IdTipoDocumento' => $idPF[1],
                        ]);
                    }
                } else {
                    DB::table('UsuariosPersonasFisicas')->where('IdUsuario', '=', $idUsuario)->delete();
                }
            }

            DB::statement($sql, $bindings);

            LogAuditoria::log(
                Auth::id(),
                Usuario::class,
                LogAuditoria::FSA_METHOD_UPDATE,
                $Args,
                $idUsuario,
                $Args['Nombre']
            );

        });
    }

    protected function actualizar($Args)
    {

        DB::transaction(function () use ($Args) {

            if (!empty($Args->IdUltEmpresa)) {
                $bindings = [];
                $IdUltEmpresa = FsUtils::explodeId($Args->IdUltEmpresa);

                $bindings[':IdUltEmpresa0'] = $IdUltEmpresa[0];
                $bindings[':IdUltEmpresa1'] = $IdUltEmpresa[1];
                $bindings[':IdUsuario']     = $Args->IdUsuario;

                $sql = "UPDATE Usuarios SET UltimaEmpresaDocumento = :IdUltEmpresa0, UltimaEmpresaIdTipoDocumento = :IdUltEmpresa1 WHERE IdUsuario = :IdUsuario";

                DB::update($sql, $bindings);
            }

            if (!empty($Args->Idioma)) {
                $bindings = [];
                $bindings[':IdUsuario'] = $Args->IdUsuario;

                $sql = "DELETE FROM UsuariosParametros WHERE IdUsuario = :IdUsuario AND IdParametro = 'Idioma'";
                DB::delete($sql, $bindings);

                $bindings[':Idioma'] = $Args->Idioma;

                $sql = "INSERT INTO UsuariosParametros (IdUsuario, IdParametro, Valor) VALUES (:IdUsuario, 'Idioma', :Idioma)";
                DB::insert($sql, $bindings);

                LogAuditoria::log(
                    Auth::id(),
                    Usuario::class,
                    LogAuditoria::FSA_METHOD_CREATE,
                    $Args,
                    $Args->IdUsuario,
                    $Args->Nombre
                );
            }

        });

    }

    public function show(string $idUsuario, array $Args = [], $NoException = false)
    {
        if (empty($Args)) {
            $Args = $this->req->all();
        }

        $bindings = [$idUsuario];

        $sql = "SELECT
                    u.IdUsuario, 
                    u.Contrasenia AS Contrasena,
                    u.Nombre,
                    u.Estado, 
                    u.SoloLectura, 
                    u.Gestion,
                    u.ApruebaVisitas,
                    u.Administrador, 
                    u.Email,
                    u.UltimaEmpresaDocumento + '-' + LTRIM(RTRIM(STR(u.UltimaEmpresaIdTipoDocumento))) AS IdUltEmpresa,
                    u.PTC AS EsPTC,
                    u.EsTercerizado,
                    u.EsContratista,
                    u.PTCGestion,
                    u.PTCAdministrador,
                    u.RecibeNotificaciones,
                    u.Baja,
                    (SELECT upf.Documento + '-' + LTRIM(RTRIM(STR(upf.IdTipoDocumento))) FROM UsuariosPersonasFisicas upf WHERE upf.IdUsuario = u.IdUsuario) AS PF
                FROM Usuarios u
                WHERE u.IdUsuario = ?";
        
        $obj = DB::selectOne($sql, $bindings);
        
        if (empty($obj)) {
            if (!$NoException) {
                throw new NotFoundHttpException("El usuario no existe");
            }
            return null;
        }

        if (empty($obj->Gestion)) {
            $obj->Empresas = $this->listadoEmpresas($obj);
            $IdUltEmpresaExiste = false;

            foreach ($obj->Empresas as $empresa) {
                if ($empresa->IdEmpresa == $obj->IdUltEmpresa) {
                    $IdUltEmpresaExiste = true;
                    break;
                }
            }

            if (!empty($obj->Empresas) && !$IdUltEmpresaExiste) {
                $obj->IdUltEmpresa = $obj->Empresas[0]->IdEmpresa;

                // Actualizo IdUltEmpresa en la BD
                $this->actualizar($obj);
            }
        }
        
        if (empty($obj->PTCGestion)) {
            $obj->PTCAreas = $this->listadoAreas($obj);
        }

        if (!empty($obj->EsPTC)) {
            $obj->PTCRoles = $this->listadoRoles($obj);
            $obj->PTCRolesNotificaciones = $this->listadoNotificaciones($obj);

            if ($this->esPTC()) {
                $rolSOL = false;
    
                if (!empty($obj->PTCRoles)) {
                    foreach ($obj->PTCRoles as $r) {
                        if (!empty($r)) {
                            if ($r->Codigo == 'SOL') {
                                $rolSOL = true;
                                break;
                            }
                        }
                    }
                }
    
                if ($rolSOL) {
                    $obj->Empresas = $this->listadoEmpresas($obj);
                }
            }
        }

        if (empty($Args['NoCargarParametros'])) {
            $obj->Parametros = $this->listadoParametros($obj);
            $obj->Funciones = $this->listadoFunciones($obj);
        }

        if ($NoException) {
            return (array)$obj;
        }

        return $this->response($obj);
    }

    protected function altaEmpresas_sql($Args, $reset = false)
    {
        if ($reset) {
            $bindings = [];
            $bindings[':IdUsuario'] = $Args['IdUsuario'];
            $sql = "DELETE FROM UsuariosEmpresas WHERE IdUsuario = :IdUsuario";

            DB::delete($sql, $bindings);
        }

        if (!empty($Args['Empresas'])) {
            foreach ($Args['Empresas'] as $e) {
                if (is_object($e) || is_array($e)) {
                    $e = $e['IdEmpresa'];
                }

                $idemp = FsUtils::explodeId($e);

                $bindings = [];
                $bindings[':IdUsuario'] = $Args['IdUsuario'];
                $bindings[':idemp0'] = $idemp[0];
                $bindings[':idemp1'] = $idemp[1];

                $sql = "INSERT INTO UsuariosEmpresas (IdUsuario, Documento, IdTipoDocumento) 
                        VALUES (:IdUsuario, :idemp0, :idemp1)";

                DB::insert($sql, $bindings);
            }
        }
    }

    protected function altaParametros_sql($Args, $reset = false)
    {

        if ($reset) {
            $bindings = [];
            $bindings[':IdUsuario'] = $Args['IdUsuario'];
            $sql = "DELETE FROM UsuariosParametros WHERE IdUsuario = :IdUsuario";

            DB::delete($sql, $bindings);
        }

        if (!empty($Args['Parametros'])) {
            foreach ($Args['Parametros'] as $k => $v) {

                $bindings = [];
                $bindings[':IdUsuario'] = $Args['IdUsuario'];
                $bindings[':k'] = $k;
                $bindings[':v'] = $v;

                $sql = "INSERT INTO UsuariosParametros (IdUsuario, IdParametro, Valor) 
                        VALUES (:IdUsuario, :k, :v)";

                DB::insert($sql, $bindings);
            }
        }
    }

    protected function altaFunciones_sql($Args, $reset = false)
    {
        if ($reset) {
            $bindings[':IdUsuario'] = $Args['IdUsuario'];
            $sql = "DELETE uf FROM UsuariosFunciones uf INNER JOIN Funciones f ON f.IdFuncion = uf.IdFuncion WHERE uf.IdUsuario = :IdUsuario";

            if ($this->esPTC()) {
                $sql .= " AND f.PTC = 1 ";
            }

            DB::delete($sql, $bindings);
        }

        if (!empty($Args['Funciones'])) {
            foreach ($Args['Funciones'] as $f) {
                $idFuncion = is_array($f) ? $f['IdFuncion'] : $f;

                if ($idFuncion === 'chkAdmEmpresaContratista' && $Args['EsContratista'] !== true) {
                    continue;
                }

                $bindings = [
                    ':IdUsuario' => $Args['IdUsuario'],
                    ':IdFuncion' => $idFuncion,
                ];
                $sql = "INSERT INTO UsuariosFunciones (IdUsuario, IdFuncion) VALUES (:IdUsuario, :IdFuncion)";
                DB::insert($sql, $bindings);
            }
        }
        
    }

    protected function altaAreas_sql($Args, $reset = false)
    {
        if ($reset) {
            $bindings = [];
            $bindings[':IdUsuario'] = $Args['IdUsuario'];
            $sql = "DELETE FROM PTCAreasUsuarios WHERE IdUsuario = :IdUsuario";

            DB::delete($sql, $bindings);
        }

        if (!empty($Args['PTCAreas'])) {
            foreach ($Args['PTCAreas'] as $a) {
                if (!empty($a)) {
                    $bindings = [];
                    $bindings[':IdUsuario'] = $Args['IdUsuario'];
                    $bindings[':a'] = $a;

                    $sql = "INSERT INTO PTCAreasUsuarios (IdUsuario, IdArea) 
                            VALUES (:IdUsuario, :a)";

                    DB::insert($sql, $bindings);
                }
            }
        }
    }

    protected function altaRoles_sql($Args, $reset = false)
    {
        if ($reset) {
            $bindings = [];
            $bindings[':IdUsuario'] = $Args['IdUsuario'];
            $sql = "DELETE FROM PTCRolesUsuarios WHERE IdUsuario = :IdUsuario";

            DB::delete($sql, $bindings);
        }

        if (!empty($Args['PTCRoles'])) {
            foreach ($Args['PTCRoles'] as $r) {
                if (!empty($r)) {
                    $bindings = [];
                    $bindings[':IdUsuario'] = $Args['IdUsuario'];
                    $bindings[':r'] = $r;

                    $sql = "INSERT INTO PTCRolesUsuarios (IdUsuario, Codigo) 
                            VALUES (:IdUsuario, :r)";

                    DB::insert($sql, $bindings);
                }
            }
        }else{
            throw new HttpException(409, 'Debe seleccionar al menos un rol');
        }
    }

    protected function altaNotificaciones_sql($Args, $reset = false)
    {
        if ($reset) {
            $bindings = [];
            $bindings[':IdUsuario'] = $Args['IdUsuario'];
            $sql = "UPDATE PTCRolesUsuarios SET RecibeNotificaciones = 0 WHERE IdUsuario = :IdUsuario";

            DB::update($sql, $bindings);
        }

        if (!empty($Args['PTCRolesNotificaciones'])) {
            foreach ($Args['PTCRolesNotificaciones'] as $r) {
                if (!empty($r)) {
                    $tieneRol = false;

                    foreach ($Args['PTCRoles'] as $rol) {
                        if (!empty($rol) && $rol == $r) {
                            $tieneRol = true;
                            break;
                        }
                    }

                    if ($tieneRol) {
                        $bindings = [];
                        $bindings[':IdUsuario'] = $Args['IdUsuario'];
                        $bindings[':r'] = $r;

                        $sql = "UPDATE PTCRolesUsuarios SET RecibeNotificaciones = 1 WHERE IdUsuario = :IdUsuario AND Codigo = :r";

                        DB::update($sql, $bindings);
                    }
                }
            }
        }
    }

    protected function listadoEmpresas($Args)
    {
        $bindings = [];
        $sql = "SELECT p.Documento + '-' + LTRIM(RTRIM(STR(p.IdTipoDocumento))) AS IdEmpresa,
                       e.Nombre,
                       e.MdP
                FROM Empresas e
                INNER JOIN Personas p ON p.Documento = e.Documento AND p.IdTipoDocumento = e.IdTipoDocumento ";

        $f = false;

        if ($this->esPTC()) {
            $rolSOL = false;

            if (!empty($Args->PTCRoles)) {
                foreach ($Args->PTCRoles as $r) {
                    if (!empty($r)) {
                        if ($r->Codigo == 'SOL') {
                            $rolSOL = true;
                            break;
                        }
                    }
                }
            }

            $f = $rolSOL;
        } else {
            if (empty($Args->Gestion)) {
                $f = true;
            }
        }

        if ($f) {
            $sql .= "INNER JOIN UsuariosEmpresas ue ON e.Documento = ue.Documento AND e.IdTipoDocumento = ue.IdTipoDocumento ";
            $sql .= "WHERE ue.IdUsuario = :IdUsuario";
            $bindings[':IdUsuario'] = $Args->IdUsuario;
        }

        $sql .= (strpos($sql, 'WHERE') === false ? " WHERE " : " AND ") . " p.Baja = 0";

        $sql .= " ORDER BY e.Nombre";

        return DB::select($sql, $bindings);
    }

    protected function listadoAreas($Args)
    {
        $bindings = [];

        $sql = "SELECT a.IdArea, a.Nombre FROM PTCAreas a ";

        if (empty($Args->PTCGestion)) {
            $sql .= "INNER JOIN PTCAreasUsuarios au ON a.IdArea = au.IdArea ";
            $sql .= "WHERE au.IdUsuario = :IdUsuario";
            $bindings[':IdUsuario'] = $Args->IdUsuario;
        }

        return DB::select($sql, $bindings);
    }

    protected function listadoRoles($Args)
    {
        $bindings = [];

        $sql = "SELECT r.Codigo, r.Nombre FROM PTCRoles r
                INNER JOIN PTCRolesUsuarios ru ON ru.Codigo = r.Codigo
                WHERE ru.IdUsuario = :IdUsuario";
        $bindings[':IdUsuario'] = $Args->IdUsuario;

        return DB::select($sql, $bindings);
    }

    protected function listadoNotificaciones($Args)
    {
        $bindings = [];

        $sql = "SELECT r.Codigo, r.Nombre FROM PTCRoles r 
                INNER JOIN PTCRolesUsuarios ru ON ru.Codigo = r.Codigo 
                WHERE ru.IdUsuario = :IdUsuario
                AND ru.RecibeNotificaciones = 1";
        $bindings[':IdUsuario'] = $Args->IdUsuario;

        return DB::select($sql, $bindings);
    }

    protected function listadoFunciones($Args)
    {
        $bindings = [];

        $sql = 'SELECT DISTINCT f.IdFuncion, f.Descripcion, f.Grupo, f.Entity, f.Menu, f.Info FROM Funciones f';

        if (empty($Args->Administrador) && empty($Args->PTCAdministrador)) {
            $bindings[':IdUsuario'] = $Args->IdUsuario;

            $sql .= " INNER JOIN UsuariosFunciones uf ON f.IdFuncion = uf.IdFuncion AND uf.IdUsuario = :IdUsuario";
        } else if (!empty($Args->PTCAdministrador)) {
            $bindings[':IdUsuario'] = $Args->IdUsuario;
            $sql .= " INNER JOIN UsuariosFunciones uf ON (f.IdFuncion = uf.IdFuncion AND uf.IdUsuario = :IdUsuario) OR (f.PTC = 1)";
        }

        $sql .= " WHERE f.Grupo IS NOT NULL ";

        if (empty($Args->Gestion)) {
            $sql .= " AND f.Gestion = 0 ";
        }

        $sql .= ' ORDER BY f.Descripcion';

        return DB::select($sql, $bindings);
    }

    protected function listadoParametros($Args)
    {
        $bindings = [];
        $bindings[':IdUsuario'] = $Args->IdUsuario;
        $sql = "SELECT IdParametro, Valor FROM UsuariosParametros WHERE IdUsuario = :IdUsuario";
        return DB::select($sql, $bindings);
    }

    protected function listadoareasptc($Args)
    {
        $bindings = [];

        $sql = "SELECT IdArea FROM PTCAreasUsuarios 
                WHERE IdUsuario = :IdUsuario";
        $bindings[':IdUsuario'] = $Args->IdUsuario;

        return DB::select($sql, $bindings);
    }

    public function delete(string $idUsuario)
    {

        $retorno = DB::transaction(function () use ($idUsuario) {
            $entity = Usuario::find($idUsuario);

            if (!isset($entity)) {
                throw new NotFoundHttpException('Usuario no encontrado');
            }

            $entity->Baja = true;
            $entity->save();

            DB::table('UsuariosPersonasFisicas')->where('IdUsuario', '=', $idUsuario)->delete();

            LogAuditoria::log(
                Auth::id(),
                Usuario::class,
                LogAuditoria::FSA_METHOD_DELETE,
                $idUsuario,
                $idUsuario,
                $entity->Nombre
            );

            return true;
        });

        if ($retorno != true) {
            throw new HttpException(409, $retorno);
        }
    }

    // Se comenta ya que NO ESTA SIENDO IMPLEMENTADO ACTUALMENTE - 03/09/2021
    // Se usa el que esta definido en el AuthConntroller -> RecoveryPassword()

    // public function recuperarContrasena(string $idUsuario)
    // {

    //     $obj = Usuario::find($idUsuario);

    //     if (isset($obj)) {
    //         // Genero token y transac
    //         $t = Str::random(16);

    //         $retorno = DB::transaction(function () use ($obj, $t) {
    //             // Marco los tokens anteriores como usados por las dudas
    //             DB::table('UsuariosRestContrasena')
    //                 ->where('IdUsuario', '=', $obj->IdUsuario)
    //                 ->update(['Usado' => 1]);
    //             // Genero un nuevo token en la bd

    //             $currentDateTime = Carbon::now();
    //             $newDateTime = Carbon::now()->addMinutes(30);

    //             DB::table('UsuariosRestContrasena')
    //                 ->insert([
    //                     'Id' => $t,
    //                     'IdUsuario' => $obj->IdUsuario,
    //                     'Desde' => $currentDateTime,
    //                     'Hasta' => $newDateTime,
    //                     'Usado' => 0
    //                 ]);

    //             return true;
    //         });

    //         if ($retorno === true) {
    //             // Armo el correo
    //             $obj->t = $t;
    //             Mail::to($obj->Email)->send(new RecuperarContraseniaMail($obj));
    //         } else {
    //             throw new HttpException(409, "Ocurrió un error al intentar inicializar el proceso de restablecimiento de contraseña");
    //         }
    //     } else {
    //         throw new HttpException(409, "No se encontró al usuario");
    //     }
    // }

    public function restablecerContrasena()
    {
        $Args = $this->req->all();

        Usuario::exigirArgs($Args, array("IdUsuario", "Password", "Token"));

        $obj = DB::selectone("SELECT IdUsuario,
                    CASE WHEN GETDATE() <= Hasta THEN 1 ELSE 0 END AS TokenValido,
                    CASE WHEN Usado = 1 THEN 1 ELSE 0 END AS TokenUsado
                    FROM UsuariosRestContrasena
                    WHERE IdUsuario = :IdUsuario
                    AND Id = :Token", [':IdUsuario' => $Args['IdUsuario'], ':Token' => $Args['Token']]);
               
        // Si existe la combinación de usuario-token
        if (!empty($obj)) {
            // Si el token aún no venció
            if (!empty($obj->TokenValido) && empty($obj->TokenUsado)) {
                // cambio la pass
                $retorno = DB::transaction(function () use ($Args) {
                    $entity = Usuario::find($Args['IdUsuario']);

                    if (!isset($entity)) {
                        throw new NotFoundHttpException('Usuario no encontrado');
                    }

                    $entity->Contrasenia = $Args['Password'];
                    $entity->save();

                    //Pongo en "usado" al token                    
                    DB::table('UsuariosRestContrasena')
                        ->where('Id', '=', $Args['Token'])
                        ->where('IdUsuario', '=', $Args['IdUsuario'])
                        ->update(['Usado' => 1]);

                    return true;
                });

                if ($retorno != true) {
                    throw new HttpException(409, "Ocurrió un error al intentar cambiar la contraseña");
                }
            } else {
                if (empty($obj->TokenValido)) {
                    throw new HttpException(409, "El token está vencido");
                } else if (!empty($obj->TokenUsado)) {
                    throw new HttpException(409, "El token ya fue usado");
                }
            }
        } else {
            throw new HttpException(409, "El token no es válido");
        }
    }

    public function cambiarIdentificador()
    {
        $Args = $this->req->all();
        $ArgsNuevo = ["IdUsuario" => strtolower($Args['NuevoIdUsuario']), "IdAnt" => $Args['IdUsuario']];
        $detalle = $this->show($Args['IdUsuario'], $Args)->getData();
        
        if (empty($ArgsNuevo['IdUsuario'])) {
            throw new HttpException(409, "El Nombre de Usuario no puede ser vacío");
        }

        if (empty($detalle)) {
            throw new HttpException(409, "El usuario no existe");
        }

        $comprobarDisponibilidad = $this->show($Args['NuevoIdUsuario'], $ArgsNuevo, true);
        if (!empty($comprobarDisponibilidad)) {
            throw new HttpException(409, "El Nombre de Usuario que acaba de ingresar ya se encuentra utilizado");
        }

        $t = array(
            array('LogActividades', 'IdUsuario'),
            array('LogErrores', 'IdUsuario'),
            array('LogUsuarios', 'IdUsuario'),
            array('LogSesiones', 'IdUsuario'),
            array('PTCAreasUsuarios', 'IdUsuario'),
            array('PTCRolesUsuarios', 'IdUsuario'),
            array('Sesiones', 'IdUsuario'),
            array('Usuarios', 'IdUsuario'),
            array('UsuariosEmpresas', 'IdUsuario'),
            array('UsuariosFunciones', 'IdUsuario'),
            array('UsuariosParametros', 'IdUsuario'),
            array('UsuariosPersonasFisicas', 'IdUsuario'),
            array('UsuariosRestContrasena', 'IdUsuario'),
            array('UsuariosSesiones', 'IdUsuario'),
        );

        $retorno = $this->cambiarIdentificadorSql($t, $ArgsNuevo);

        if ($retorno) {
            LogAuditoria::log(
                Auth::id(),
                Usuario::class,
                'Cambiar Id',
                $ArgsNuevo,
                $Args['IdUsuario'],
                $ArgsNuevo['IdUsuario']
            );

            return true;
        }



        throw new HttpException(409, "No se pudo cambiar el identificador del usuario");
    }

    public function cambiarIdentificadorSql(array $tables, $args)
    {
        // Deshabilito temporalmente la comprobación de claves foráneas en las tablas
        foreach ($tables as $table) {
            DB::statement("ALTER TABLE " . $table[0] . " NOCHECK CONSTRAINT ALL;");
        }

        // Hago el update
        foreach ($tables as $table) {
            $c = explode(', ', $table[1]);

            foreach ($c as $columns) {
                $cn = explode('|', $columns);

                DB::statement(
                    "UPDATE " . $table[0] . " SET " . $cn[0] . " = ? WHERE " . $cn[0] . " = ?",
                    [
                        $args['IdUsuario'],
                        $args['IdAnt'],
                    ]
                );
            }
        }

        // Habilito la comprobación de claves foráneas instantaneamente
        foreach ($tables as $table) {
            DB::statement("ALTER TABLE " . $table[0] . " CHECK CONSTRAINT ALL;");
        }

        return true;
    }

    public function desactivacionMasiva()
    {

        $Args = $this->req->all();
        Usuario::exigirArgs($Args, ['IdUsuarios']);

        $Args = (object) $Args;

        $estadoObservacion = property_exists($Args, 'EstadoObservacion') ? $Args->EstadoObservacion : '';

        foreach ($Args->IdUsuarios as $idUsuario) {
            $retorno = DB::table('Usuarios')
                ->where('IdUsuario', '=', $idUsuario)
                ->update(
                    [
                        'Estado' => 0,
                        'EstadoObservacion' => $estadoObservacion
                    ]
                );

            if ($retorno) {
                LogAuditoria::log(
                    Auth::id(),
                    Usuario::class,
                    LogAuditoria::FSA_METHOD_UPDATE,
                    $Args,
                    $idUsuario,
                    $idUsuario
                );
            } else {
                throw new HttpException(409, 'No se pudo desactivar al usuario ' . $idUsuario);
            }
        }
        return true;
    }

    public function changePassword()
    {
        $Args = $this->req->all();

        if (strlen($Args['Contrasenia']) < 6) {
            throw new HttpException(409, 'Ingrese una contraseña con más de 6 caracteres');
        } else if ($Args['Contrasenia'] !== $Args['ConfContrasenia']) {
            throw new HttpException(409, 'La contraseña y su confirmación no coinciden');
        }

        $Usuario = $this->user;

        $detalle = $this->show($Usuario['IdUsuario'])->getData();

        if (!$detalle) {
            throw new HttpException(409, 'Ocurrió un error al cambiar la contraseña (1)');
        }

        $Contrasenia = md5($Args['Contrasenia']);

        $Usuario->Contrasenia = $Contrasenia;
        $Usuario->save();

        LogAuditoria::log(
            Auth::id(),
            Usuario::class,
            'Cambio de contraseña',
            $Args,
            $Usuario->IdUsuario,
            $Usuario->Nombre
        );

        return true;
    }

    public function resendAlta()
    {
        return null;

        $Args = $this->req->all();
        $Usuario = $this->user;

        if ($Usuario['IdUsuario'] === 'fusionar') {
            $obj = $this->show($Args['IdUsuario'], [])->getData();
            $obj = json_decode($obj);
            $Contrasenia = md5($Args['Contrasenia']);

            $entity = Usuario::find($Args['IdUsuario']);
            $entity->Contrasenia = $Contrasenia;
            $entity->save();

            $newObj = (object) [
                'IdUsuario' => $obj->IdUsuario,
                'Nombre' => $obj->Nombre,
                'Contrasenia' => $Args['Contrasenia'],
                'Email' => $obj->Email,
            ];
            return $this->altamail($newObj);
        }
    }
}

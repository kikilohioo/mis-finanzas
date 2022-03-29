<?php

namespace App\Http\Controllers;

use App\Models\Sesion;
use App\Models\Usuario;
use App\Mails\Usuario\RecuperarContraseniaMail;
use App\Models\LogAuditoria;
use App\Models\UsuarioRestaurarContrasena;
use DateTime;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AuthController extends Controller
{
    /**
     * @var Request
     */
    private $req;

    /**
     * @var array
     */
    private $errMessages = [
        'IdUsuario.required' => 'Debe indicar el nombre de usuario',
        'Contrasenna.required' => 'Debe indicar su contraseña',
        'token.required' => "Token obligatorio",
        'Correo.required' => "Debe indicar el correo del usuario",
        'Correo.email' => "Formato de Correo inválido",
    ];

    public function __construct(Request $req)
    {
        $this->req = $req;
    }

    protected static function jwt(Usuario $user, int $iat, int $exp): string
    {
        $payload = [
            'iss' => 'fsgestion-web',
            'sub' => $user->IdUsuario,
            'iat' => $iat,
            'exp' => $exp,
        ];
        return JWT::encode($payload, env('JWT_SECRET'));
    }

    public function ResetPassword()
    {
        $validator = Validator::make($this->req->all(), [
            'Token' => 'required',
            'New_contrasenna' => 'required',
            'Repeat_contrasenna' => 'required|same:New_contrasenna',
        ]);

        if ($validator->fails()) {
            return $this->responseError($validator->errors()->first(), $validator->errors());
        }

        $usuarioTokenContrasena = UsuarioRestaurarContrasena::with('Usuario')
            ->where('Id', $this->req->input('Token'))
            ->where('Usado', false)
            ->where('Hasta', '>=', new \DateTime)
            ->whereHas('Usuario', function ($query) {
                return $query->where('Baja', false);
            })->first();

        if (!isset($usuarioTokenContrasena)) {
            throw new ConflictHttpException("Token inválido");
        }

        try {
            DB::transaction(function () use ($usuarioTokenContrasena)
            {
                $usuarioTokenContrasena->Usuario->Contrasenia = md5($this->req->input('New_contrasenna'));
                $usuarioTokenContrasena->Usado = true;

                $usuarioTokenContrasena->Usuario->auditar = false;
                $usuarioTokenContrasena->Usuario->save();
                $usuarioTokenContrasena->save();

                LogAuditoria::log(
                    $this->req->ip(),
                    UsuarioRestaurarContrasena::class,
                    'Reset Password',
                    $usuarioTokenContrasena,
                    $usuarioTokenContrasena->Id,
                    $usuarioTokenContrasena->IdUsuario
                );
            });
        } catch (HttpException $err) {
            if (env('APP_DEBUG') === true) {
                return $this->responseInternalError($err->getMessage());
            }
            return $this->responseInternalError("Error interno, intentelo en unos minutos");
        }
        return $this->response(['message' => "Contraseña reseteada con éxito"]);
    }
    
    public function RecoveryPassword()
    {
        $validator = Validator::make($this->req->all(), [
            'Usuario' => 'required',
        ], $this->errMessages);

        if ($validator->fails()) {
            return $this->responseError($validator->errors()->first(), $validator->errors());
        }

        $query = Usuario::query();
        $usuario = $query->where('IdUsuario', trim($this->req->input('Usuario')))->where('Baja', false)->first();

        if(!isset($usuario)) {
            sleep(1);
            throw new NotFoundHttpException("Usuario no encontrado, asegurese de haber escrito correctamente su usuario");
        }
        if (empty($usuario->Email)) {
            sleep(1);
            throw new NotFoundHttpException("No se puede restablecer la contraseña del usuario: $usuario->Nombre, porque no tiene un correo electrónico asociado");
        }

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
            $usuarioTokenContrasena->IdUsuario = $usuario->IdUsuario;
            $usuarioTokenContrasena->Desde = new \DateTime;

            $tiempoExpira = env('TOKEN_CONTRASENA_EXPIRA');
            $fechaHoraExpira = date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s') . ' +' . $tiempoExpira . ' seconds'));
            $usuarioTokenContrasena->Hasta = strtotime($fechaHoraExpira);
            $usuarioTokenContrasena->Usado = false;

            $usuarioTokenContrasena->save();
            
            Mail::to($usuario->Email)->bcc(env('MAIL_FROM_ADDRESS'))->send(new RecuperarContraseniaMail($usuario, $usuarioTokenContrasena->Id));
        
        } catch (HttpException $err) {
            if (env('APP_DEBUG') === true) {
                return $this->responseInternalError($err->getMessage());
            }
            return $this->responseInternalError("Ocurrió un error interno");
        }
        return $this->response(['message' => "Correo enviado satisfactoriamente"]);
    }

    public function login()
    {
        $validator = Validator::make($this->req->all(), [
            'IdUsuario' => 'required',
            // 'Contrasenna' => 'required',
        ], $this->errMessages);

        if ($validator->fails()) {
            return $this->responseError($validator->errors()->first(), $validator->errors());
        }

        $user = Usuario::auth($this->req->input('IdUsuario'), $this->req->input('Contrasenna'));

        if (!isset($user)) {
            throw new HttpException(401, 'Usuario o contraseña incorrectos');
        }

        $empresas = [];
        if (!$user->isGestion() || (!$user->Administrador && $user->PTC)) {
            $empresas = DB::select('SELECT e.Documento, e.IdTipoDocumento, e.Documento + \'-\' + LTRIM(RTRIM(STR(e.IdTipoDocumento))) AS IdEmpresa, e.Nombre FROM Empresas e INNER JOIN UsuariosEmpresas ue ON e.Documento = ue.Documento AND e.IdTipoDocumento = ue.IdTipoDocumento WHERE ue.IdUsuario LIKE ?', [$user->getKey()]);
            if (!$user->isGestion() && count($empresas) === 0) {
                throw new HttpException(401, 'Su usuario no tiene empresas asociadas');
            }
        }
        
        $inactiveParameter = 86400; /// 24 hours
        $iat = time();
        $exp = ($iat + $inactiveParameter);
        $token = self::jwt($user, $iat, $exp);
        // $expTime = (new DateTime())->setTimestamp($exp);

        $session = new Sesion;
        $session->IdUsuario = $user->IdUsuario;
        $session->Token = $token;
        $session->DireccionIP = $this->req->ip();
        $session->ForzarCierre = false;
        $session->Estado = true;
        $session->FechaHora = new DateTime;
        // $session->FechaHoraExpira = $expTime;
        $session->UltimaActividad = new DateTime;
        $session->save();

        $session->makeVisible(['Token']);

        return $this->response([
            'Token' => $token,
            'Usuario' => $user,
            'Empresas' => $empresas,
        ]);
    }
    
    public function check()
    {
        return null;
    }
}
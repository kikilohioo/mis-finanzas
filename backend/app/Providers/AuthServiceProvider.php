<?php

namespace App\Providers;

use App\Models\Sesion;
use App\Models\Usuario;
use App\User;
use DateTime;
use Exception;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Boot the authentication services for the application.
     *
     * @return void
     */
    public function boot()
    {
        $this->app['auth']->viaRequest('api', function ($request) {
            $token = $request->headers->get('X-FS-TOKEN');
            if (!isset($token)) {
                $token = $request->input('token');
            }

            if (!isset($token)) {
                throw new HttpException(401, 'Token not provided');
            }

            if ($token === env('FSA_PUBLIC_TOKEN')) {
                return $this->createPublicSession();
            }

            $credentials = JWT::decode($token, env('JWT_SECRET'), ['HS256']);
            $session = Sesion::where('Token', $token)->first();
            if (!isset($session)) {
                throw new HttpException(401, 'Invalid session (1)');
            }
            if ($session->Estado !== true) {
                throw new HttpException(401, 'Invalid session (2)');
            }
            if ($session->FechaHoraExpire > new DateTime) {
                throw new HttpException(401, 'Invalid session (3)');
            }
            if (!isset($session->Usuario)) {
                throw new HttpException(401, 'Invalid session (4)');
            }

            $empresa = \App\Models\Empresa::loadBySession($request);
            
            return $session->Usuario;
        });
    }

    private function createPublicSession()
    {
        $user = Usuario::findOrFail('fsa');
        $session = new Sesion;
        $session->IdUsuario = $user->IdUsuario;
        $session->Token = env('FSA_PUBLIC_TOKEN');
        $session->DireccionIP = '127.0.0.1';
        $session->ForzarCierre = false;
        $session->Estado = true;
        $session->FechaHora = new DateTime;
        // $session->FechaHoraExpira = $expTime;
        $session->UltimaActividad = new DateTime;
        $session->save();

        $session->refresh();

        return $session->Usuario;
    }
}

<?php

namespace App\Listeners;

use App\Events\LogAuditoriaEvent;
use App\Models\LogAuditoria;
use App\Models\Sesion;
use App\Models\UsuarioRestaurarContrasena;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

class LogAuditoriaListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  LogAuditoriaEvent  $event
     * @return void
     */
    public function handle(LogAuditoriaEvent $event)
    {
        $model = $event->model;
        if (!$model->auditar) {
            return;
        }
        if (isset($model) && !($model instanceof LogAuditoria)) {
            if (!$model->auditar) {
                return;
            }

            $idUsuario = null;

            if ($model instanceof Sesion) {
                $idUsuario = LogAuditoria::FSA_USER_DEFAULT;
                $method = LogAuditoria::FSA_METHOD_LOGIN;
                $entidadDesc = $model->IdUsuario;
                $model->makeHidden(['Token']);
            } else if ($model instanceof UsuarioRestaurarContrasena) {
                $idUsuario = LogAuditoria::FSA_USER_DEFAULT;
                $method = 'recoverypassword';
                $entidadDesc = $model->IdUsuario;
                $model->makeHidden(['Id']);
            }
            if (App::environment('testing')) {
                if ($model instanceof \App\Models\Usuario) {
                    $idUsuario = LogAuditoria::FSA_USER_DEFAULT;
                }
            }

            if (!isset($idUsuario)) {
                try {
                    $idUsuario = Auth::id();
                } catch (HttpException $err) {
                    $idUsuario = LogAuditoria::FSA_USER_DEFAULT;
                }
                if (!isset($idUsuario)) { /// @todo Controlar qué pasa si no hay usuario autenticado.
                    throw new HttpException(401, 'Usuario no disponible, consulte con el administrador del sistema.');
                }
            }

            if (!isset($method)) {
                $method = LogAuditoria::FSA_METHOD_CREATE;
                if (!$model->wasRecentlyCreated) {
                    $method = LogAuditoria::FSA_METHOD_UPDATE;
                    if (isset($model->changes)) {
                        if (array_key_exists('Baja', $model->changes)) {
                            $method = $model->attributes['Baja'] === true
                                ? LogAuditoria::FSA_METHOD_DELETE
                                : LogAuditoria::FSA_METHOD_CREATE;
                        }
                        else if (count($model->changes) === 1) {
                            if (array_key_exists('Estado', $model->changes)) {
                                $method = $model->attributes['Estado'] === true
                                    ? LogAuditoria::FSA_METHOD_ACTIVATE
                                    : LogAuditoria::FSA_METHOD_DESACTIVATE;
                            }
                            else if (array_key_exists('Activo', $model->changes)) {
                                $method = $model->attributes['Activo'] === true
                                    ? LogAuditoria::FSA_METHOD_ACTIVATE
                                    : LogAuditoria::FSA_METHOD_DESACTIVATE;
                            }
                        }
                    }
                }
            }

            $entidadClass = explode('\\', get_class($model));
            $entidad = array_pop($entidadClass);
            if (empty($entidadDesc)) {
                $entidadDesc = $model->getName();
            }
            /*if (!isset($entidadDesc)) {
                $entidadDesc = $model->getName() == $model->getKey()
                    ? $model->getName()
                    : (empty($model->getKey())
                        ? $model->getName()
                        : $model->getName() . ' (' . $model->getKey() . ')');
            }*/

            LogAuditoria::log($idUsuario, $entidad, $method, $model->jsonSerialize(), $model->getKeyAlt(), $entidadDesc);
        }
    }
}

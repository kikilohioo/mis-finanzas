<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        AuthorizationException::class,
        HttpException::class,
        ModelNotFoundException::class,
        ValidationException::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Throwable  $exception
     * @return void
     *
     * @throws \Exception
     */
    public function report(Throwable $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $exception)
    {
        if ($exception instanceof HttpException) {
            $message = $exception->getMessage();
            if (empty($message) && isset(Response::$statusTexts[$exception->getStatusCode()])) {
                $message = Response::$statusTexts[$exception->getStatusCode()];
            }
            $response = ['message' => $message, 'error' => $message, 'status' => $exception->getStatusCode()];
            if (env('APP_DEBUG', false)) {
                $response['trace'] = explode("\n", $exception->getTraceAsString());
            }

            if (in_array($exception->getStatusCode(), [403, 404])) {
                Log::notice($exception);
            } else if ($exception->getStatusCode() < 500) {
                Log::info($exception);
            }

            return response()->json($response, $exception->getStatusCode());
        } else if ($exception instanceof ModelNotFoundException) {
            $message = 'No se pudo encontrar el registro seleccionado';
            $response = ['message' => $message, 'error' => $message, 'status' => 404];
            if (env('APP_DEBUG', false)) {
                $response['trace'] = explode("\n", $exception->getTraceAsString());
            }
            Log::notice($exception);
            return response()->json($response, 404);
        }

        $message = $exception->getMessage();
        $response = ['message' => $message, 'error' => $message, 'status' => 500];
        if (env('APP_DEBUG', false)) {
            $response['trace'] = explode("\n", $exception->getTraceAsString());
        }

        if (!env('APP_DEBUG', false)) {
            $errorNo = self::publishErrorLog($exception);
            return response()->json([
                'message' => sprintf('La solicitud no ha podido ser procesada correctamente. '
                    . 'Favor de indicar el código de error %s al administrador del sistema.', $errorNo),
                'error' => sprintf('La solicitud no ha podido ser procesada correctamente. '
                    . 'Favor de indicar el código de error %s al administrador del sistema.', $errorNo)
            ], 500);
        }
        return response()->json($response, 500);

        // } else if (!env('APP_DEBUG')) {
        //     return response()->json([
        //         'message' => $exception->getMessage(),
        //         'code' => $exception->getCode(),
        //         'file' => $exception->getFile() . ':' . $exception->getLine(),
        //     ], 500);
        // }
        // return parent::render($request, $exception);
    }

    private static function publishErrorLog(Throwable $exception): string
    {
        $uniqid = strtoupper(uniqid('FS-'));
        $user = '-';
        try {
            $user = Auth::id();
        } catch (\Exception $err) { }
        Log::error(sprintf('[%s] (%s) %s', $uniqid, $user, $exception->getMessage()));
        return $uniqid;
    }
}

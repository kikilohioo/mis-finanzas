<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Firebase\JWT\ExpiredException;
use Illuminate\Contracts\Auth\Factory as Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

class Authenticate
{
    /**
     * The authentication guard factory instance.
     *
     * @var \Illuminate\Contracts\Auth\Factory
     */
    protected $auth;

    /**
     * Create a new middleware instance.
     *
     * @param  \Illuminate\Contracts\Auth\Factory  $auth
     * @return void
     */
    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        try {
            if ($this->auth->guard($guard)->guest()) {
                return response('Unauthorized.', 401);
            }
        } catch (ExpiredException $ex) {
            throw new HttpException(401, 'Provided token is expired');
            // return response()->json([
            //     'message' => 'Provided token is expired',
            // ], 401);
        } catch (HttpException $ex) {
            throw $ex;
        } catch (Exception $ex) {
            throw new HttpException(401, 'An error while decoding token');
            // return response()->json([
            //     'message' => 'An error while decoding token',
            //     'exception' => $ex->getMessage(),
            // ], 401);
        }

        return $next($request);
    }
}

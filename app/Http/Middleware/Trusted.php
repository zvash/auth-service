<?php

namespace App\Http\Middleware;

use Closure;
use App\Action;
use App\Service;
use Illuminate\Contracts\Auth\Factory as Auth;

class Trusted
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
     * @param  \Illuminate\Contracts\Auth\Factory $auth
     * @return void
     */
    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     */

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $user = $this->auth->user();
        $serviceToken = $request->header('Service-Token', null);
        $serviceIsValid = Service::where('token', $serviceToken)->count() > 0;
        if (
            $serviceIsValid ||
            ($user && $user->hasRole('admin'))
        ) {
            return $next($request);
        }

        return response('Unauthorized', 401);
    }
}

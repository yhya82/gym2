<?php

namespace App\Http\Middleware;

use App\Models\ApplicationSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplySessionTimeout
{
    /**
     * Applies Security Center > Session timeout in place of the .env
     * SESSION_LIFETIME.
     *
     * Must run before StartSession (it's prepended to the web group in
     * bootstrap/app.php): the session handler reads session.lifetime when
     * the session starts, both to expire idle sessions and to set the
     * cookie's lifetime.
     */
    public function handle(Request $request, Closure $next): Response
    {
        config(['session.lifetime' => ApplicationSetting::securityPolicy()['session_timeout_minutes']]);

        return $next($request);
    }
}

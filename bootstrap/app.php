<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Laravel's handler internally ignores AuthorizationException by
        // default (it's an "expected", already-clean-403 exception, not an
        // error) — reportable() below would silently never fire without
        // this, since shouldReport() short-circuits before it's reached.
        $exceptions->stopIgnoring(AuthorizationException::class);

        // Every permission denial funnels through AuthorizationException —
        // Policy denials, FormRequest::authorize() failures, and
        // EnsureUserHasRole's route-group check all throw this same type —
        // so this single hook is the one place that needs to log it. Purely
        // a side effect: returning nothing (not false) leaves the default
        // 403 rendering untouched.
        $exceptions->reportable(function (AuthorizationException $e) {
            if ($user = Auth::user()) {
                app(AuditLogger::class)->log(
                    'permission-denied',
                    'auth',
                    "{$user->name} was denied: {$e->getMessage()} (".Request::method().' '.Request::path().').',
                    $user->id,
                );
            }
        });
    })->create();

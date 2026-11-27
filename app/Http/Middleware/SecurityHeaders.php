<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Baseline browser protections for every page.
     *
     * - X-Frame-Options: no other site can put this app in a frame (stops
     *   clickjacking — e.g. tricking an Admin into clicking "Deactivate").
     * - X-Content-Type-Options: browsers trust the declared content type
     *   instead of guessing, so an uploaded file can't be run as a script.
     * - Referrer-Policy: other sites only ever see the origin, never a full
     *   URL (which can carry member ids, search terms or reset tokens).
     * - Permissions-Policy: the app needs no camera, microphone or location.
     * - Strict-Transport-Security: once reached over HTTPS, browsers refuse
     *   plain HTTP for a year. Sent only on HTTPS, so local http:// dev works.
     *
     * Also strips X-Powered-By, which advertised the exact PHP version. (Set
     * expose_php = Off in production's php.ini as well — PHP adds it before
     * Laravel runs.)
     *
     * No Content-Security-Policy yet: Livewire/Alpine rely on inline scripts
     * and expression evaluation, so a CSP needs nonces and testing of every
     * page — worth doing, but as its own change.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        $response->headers->remove('X-Powered-By');
        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        return $response;
    }
}

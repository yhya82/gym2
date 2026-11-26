<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsCompliant
{
    /**
     * Holds a user whose password failed the current policy at login
     * (users.must_change_password) on the "Set a new password" screen
     * until they replace it.
     *
     * Applied to every signed-in route group in routes/web.php, and
     * registered as Livewire persistent middleware (AppServiceProvider) so
     * a component already open on one of those pages can't be used to
     * carry on working either.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->must_change_password) {
            return $next($request);
        }

        // A Livewire update has no page to redirect; refusing it is enough,
        // since the next full page load lands on the change screen. A plain
        // 403 rather than AuthorizationException: this isn't a permission
        // denial, and logging it as one only cluttered the audit trail.
        if ($request->hasHeader('X-Livewire')) {
            abort(403, 'You must set a new password before continuing.');
        }

        return redirect()->route('password.change-required');
    }
}

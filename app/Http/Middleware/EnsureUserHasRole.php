<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Gates an entire route group by role at the routing layer — e.g.
     * Route::middleware('role:admin')->group(...) for /settings/*, /plans/*,
     * /users/*. Per-resource actions still go through their Policy; this is
     * for routes with no single model instance to check against, or where a
     * whole area (not just individual abilities) is role-restricted.
     *
     * Accepts multiple roles (role:admin,receptionist) for routes shared by
     * more than one role but still closed to guests.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role->value, $roles, true)) {
            abort(403);
        }

        return $next($request);
    }
}

<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    /**
     * Silently skips logging when no user can be determined — the only
     * caller that hits this is the expiry cron, and "membership expired"
     * isn't in §19.1's list of actions that must be logged, so this guard is
     * exactly the right boundary rather than something to work around (e.g.
     * with a synthetic "system" user).
     *
     * $userId lets a caller supply the acting user explicitly rather than
     * relying on Auth::id() — needed for logout, where the Logout event
     * carries its own $event->user precisely because the session may already
     * be cleared (Auth::id() no longer reliable) by the time listeners run.
     */
    public function log(string $action, string $module, string $description, ?int $userId = null): void
    {
        $userId ??= Auth::id();

        if ($userId === null) {
            return;
        }

        AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'module' => $module,
            'description' => $description,
            'ip_address' => Request::ip(),
        ]);
    }

    /**
     * Human-readable "field: old → new" diff of a model's dirty attributes,
     * for update actions where the description should say what changed.
     */
    public function describeChanges(Model $model, array $except = ['updated_at']): string
    {
        return collect($model->getChanges())
            ->except($except)
            ->map(function ($new, $key) use ($model) {
                $old = $model->getOriginal($key) ?? 'null';

                return "{$key}: {$old} → {$new}";
            })
            ->implode(', ');
    }
}

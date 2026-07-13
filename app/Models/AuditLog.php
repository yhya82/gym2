<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    // Append-only, immutable log: no updated_at, no delete path at all.
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'action',
        'module',
        'description',
        'ip_address',
    ];

    /**
     * withTrashed: a log entry must still correctly attribute its action to
     * a staff member even after they're later deactivated (soft-deleted) —
     * history shouldn't misreport a deactivated user's past actions as
     * belonging to no one.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * §14-style search by acting user's name, action, or description — the
     * audit viewer's free-text box.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('description', 'like', "%{$term}%")
                ->orWhere('action', 'like', "%{$term}%")
                ->orWhereHas('user', function (Builder $u) use ($term) {
                    $u->withTrashed()->where('name', 'like', "%{$term}%");
                });
        });
    }

    public function scopeModule(Builder $query, ?string $module): Builder
    {
        if (! $module) {
            return $query;
        }

        return $query->where('module', $module);
    }
}

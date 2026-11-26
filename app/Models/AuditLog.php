<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class AuditLog extends Model
{
    use MassPrunable;

    // Append-only, immutable log: no updated_at. The database enforces it
    // too — trg_audit_logs_no_update / trg_audit_logs_no_early_delete
    // reject any UPDATE, and any DELETE of an entry younger than RETENTION.
    const UPDATED_AT = null;

    /**
     * How long entries are kept. model:prune (scheduled daily, see
     * routes/console.php) removes anything older; the delete trigger
     * refuses anything newer, whoever asks.
     */
    public const RETENTION_MONTHS = 12;

    protected $fillable = [
        'user_id',
        'action',
        'module',
        'description',
        'ip_address',
    ];

    /**
     * Entries past retention. One extra day of margin over the trigger's
     * cutoff, so a few hours of clock difference between PHP and the
     * database can never make a scheduled prune trip the trigger.
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subMonths(self::RETENTION_MONTHS)->subDay());
    }

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

    public function scopeAction(Builder $query, ?string $action): Builder
    {
        if (! $action) {
            return $query;
        }

        return $query->where('action', $action);
    }

    public function scopeByUser(Builder $query, int|string|null $userId): Builder
    {
        if (! $userId) {
            return $query;
        }

        return $query->where('user_id', $userId);
    }

    /**
     * Inclusive date range, by calendar day. Either end may be left open.
     */
    public function scopeBetweenDates(Builder $query, ?Carbon $from, ?Carbon $to): Builder
    {
        return $query
            ->when($from, fn (Builder $q) => $q->where('created_at', '>=', $from->copy()->startOfDay()))
            ->when($to, fn (Builder $q) => $q->where('created_at', '<=', $to->copy()->endOfDay()));
    }
}

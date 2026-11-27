<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
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
        'subject_type',
        'subject_id',
        'description',
        'changes',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }

    /**
     * The record this entry is about. Archived (soft-deleted) records are
     * included: an archived member's history should still link to them.
     * Per type, because Payment and Subscription have no withTrashed().
     */
    public function subject(): MorphTo
    {
        $withTrashed = fn (Builder $q) => $q->withTrashed();

        return $this->morphTo()->constrain([
            Member::class => $withTrashed,
            User::class => $withTrashed,
            Plan::class => $withTrashed,
            Expense::class => $withTrashed,
            ExpenseCategory::class => $withTrashed,
        ]);
    }

    /** Written by the app itself (e.g. the expiry cron), not a person. */
    public function isSystem(): bool
    {
        return $this->user_id === null;
    }

    public function actorName(): string
    {
        return $this->isSystem() ? __('System') : (string) $this->user?->name;
    }

    /**
     * Where the subject can be viewed, or null if it has no page of its own
     * (or no longer exists). Payments and subscriptions open their member.
     */
    public function subjectUrl(): ?string
    {
        $subject = $this->subject;

        return match (true) {
            $subject instanceof Member => route('members.show', $subject),
            $subject instanceof Payment, $subject instanceof Subscription => $subject->member_id ? route('members.show', $subject->member_id) : null,
            $subject instanceof User => route('users.show', $subject),
            $subject instanceof Plan && ! $subject->trashed() => route('plans.index'),
            $subject instanceof Expense && ! $subject->trashed() => route('expenses.index'),
            default => null,
        };
    }

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

    /**
     * Sign-ins, sign-outs and failed logins outnumber every real change, so
     * the Audit Log hides them unless asked. They stay one click away, and
     * a "module = Authentication" filter always shows them.
     */
    public function scopeWithoutSignIns(Builder $query, bool $hide = true): Builder
    {
        return $hide ? $query->where('module', '!=', 'auth') : $query;
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
     * Inclusive date range, by calendar day in whichever timezone the dates
     * carry (the gym's own, from the Audit Log). Either end may be left open.
     * Converted to UTC because that's what created_at is stored in, and the
     * query builder writes a Carbon's wall-clock time as-is.
     */
    public function scopeBetweenDates(Builder $query, ?Carbon $from, ?Carbon $to): Builder
    {
        return $query
            ->when($from, fn (Builder $q) => $q->where('created_at', '>=', $from->copy()->startOfDay()->utc()))
            ->when($to, fn (Builder $q) => $q->where('created_at', '<=', $to->copy()->endOfDay()->utc()));
    }
}

<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;

class AuditLogger
{
    /**
     * Records one entry for the signed-in user (or $userId).
     *
     * Silently skips when no user can be determined: an unauthenticated
     * request has nobody to attribute the action to. Work the app does on
     * its own (the expiry cron) goes through system() instead, which is
     * attributed to "System" on purpose rather than by accident.
     *
     * $userId lets a caller name the acting user explicitly rather than
     * relying on Auth::id(). Logout needs it: the Logout event carries its
     * own $event->user because the session may already be cleared by the
     * time listeners run.
     *
     * $subject is the record the entry is about (linked from the Audit
     * Log). $changes is the {field: {old, new}} map from changesFor().
     */
    public function log(
        string $action,
        string $module,
        string $description,
        ?int $userId = null,
        ?Model $subject = null,
        array $changes = [],
    ): void {
        $userId ??= Auth::id();

        if ($userId === null) {
            return;
        }

        $this->write($userId, $action, $module, $description, $subject, $changes);
    }

    /**
     * An entry for something the app did by itself, with no person behind
     * it — shown as "System" in the Audit Log.
     */
    public function system(
        string $action,
        string $module,
        string $description,
        ?Model $subject = null,
        array $changes = [],
    ): void {
        $this->write(null, $action, $module, $description, $subject, $changes);
    }

    /**
     * Human-readable "field: old → new" diff of a model's changed
     * attributes, for the entry's one-line description (and its CSV
     * export, and free-text search).
     */
    public function describeChanges(Model $model, array $except = ['updated_at']): string
    {
        return collect($this->changesFor($model, $except))
            ->map(fn (array $c, string $key) => "{$key}: ".($c['old'] ?? 'null').' → '.($c['new'] ?? 'null'))
            ->implode(', ');
    }

    /**
     * The same diff, structured, for the Before/After table: {field: {old,
     * new}}. Run from an "updated" observer, getOriginal() still holds the
     * pre-save values (Eloquent syncs originals only after the event).
     *
     * @return array<string, array{old: ?string, new: ?string}>
     */
    public function changesFor(Model $model, array $except = ['updated_at']): array
    {
        return collect($model->getChanges())
            ->except($except)
            ->map(fn ($new, $key) => [
                'old' => $this->stringify($model->getOriginal($key)),
                'new' => $this->stringify($new),
            ])
            ->all();
    }

    private function write(?int $userId, string $action, string $module, string $description, ?Model $subject, array $changes): void
    {
        // A console run has no browser; the IP Laravel reports there
        // (127.0.0.1) would be misleading, so neither is stored.
        $web = ! app()->runningInConsole() || app()->runningUnitTests();

        AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'module' => $module,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'changes' => $changes ?: null,
            'ip_address' => $web && $userId !== null ? Request::ip() : null,
            'user_agent' => $web && $userId !== null ? Str::limit((string) Request::userAgent(), 250, '') ?: null : null,
        ]);
    }

    /**
     * A changed attribute backed by a native PHP enum (e.g. Member::status)
     * arrives as the enum instance itself; dates arrive as Carbon. Both are
     * flattened to the plain value a person would recognise.
     */
    private function stringify(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            $value instanceof \BackedEnum => (string) $value->value,
            // A date-only column (expense_date) is midnight: show just the day.
            $value instanceof \DateTimeInterface => $value->format($value->format('H:i:s') === '00:00:00' ? 'Y-m-d' : 'Y-m-d H:i:s'),
            is_bool($value) => $value ? 'yes' : 'no',
            is_array($value) => json_encode($value),
            default => (string) $value,
        };
    }
}

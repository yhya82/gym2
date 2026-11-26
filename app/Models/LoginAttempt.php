<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginAttempt extends Model
{
    use MassPrunable;

    // Append-only: written by RecordLoginAttempt, never edited.
    const UPDATED_AT = null;

    /**
     * Same retention as the audit log, so the Security Center's login
     * counters cover the same window as the events behind them.
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subMonths(AuditLog::RETENTION_MONTHS));
    }

    protected $fillable = [
        'user_id',
        'email',
        'ip_address',
        'successful',
    ];

    protected function casts(): array
    {
        return [
            'successful' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}

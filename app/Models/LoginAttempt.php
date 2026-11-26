<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginAttempt extends Model
{
    // Append-only: written by RecordLoginAttempt, never edited.
    const UPDATED_AT = null;

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

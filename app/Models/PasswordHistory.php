<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PasswordHistory extends Model
{
    const UPDATED_AT = null;

    /**
     * Rows kept per user. This is the Security Center's maximum "password
     * history" setting, so raising the setting later still has a full
     * history to check against.
     */
    public const MAX_KEPT = 24;

    protected $fillable = [
        'user_id',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

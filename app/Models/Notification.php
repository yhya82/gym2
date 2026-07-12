<?php

namespace App\Models;

use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    // Fan-out model: one row per recipient, written directly by event
    // listeners — this is not Laravel's built-in polymorphic notifications
    // table/system (see User::appNotifications() for why the name differs).
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'type',
        'message',
        'read_status',
    ];

    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'read_status' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

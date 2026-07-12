<?php

namespace App\Models;

use App\Observers\PaymentObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([PaymentObserver::class])]
class Payment extends Model
{
    use HasFactory;

    // No SoftDeletes, no delete path at all: payment history is immutable
    // (Rule 3 — payment history cannot be deleted).

    protected $fillable = [
        'member_id',
        'subscription_id',
        'amount',
        'payment_date',
        'received_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * §14 — Payments search by member name. Includes archived members'
     * payments too — payment history must stay findable regardless of the
     * member's archive status (§11 — "preserve payment history").
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->whereHas('member', function (Builder $q) use ($term) {
            $q->withTrashed()->where('full_name', 'like', "%{$term}%");
        });
    }
}

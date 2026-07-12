<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use App\Observers\MemberObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy([MemberObserver::class])]
class Member extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'full_name',
        'phone_number',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => MembershipStatus::class,
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * The member's most recent subscription — since renewal always expires
     * the prior one first (MembershipRenewalService), the latest by
     * created_at is always the current period, active or not.
     */
    public function currentSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', MembershipStatus::Active);
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', MembershipStatus::Expired);
    }

    /**
     * "Archived" is never a stored status value — it's derived entirely from
     * soft delete, so this just reads as the symmetric counterpart to
     * active()/expired() rather than introducing a third status value.
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->onlyTrashed();
    }

    /**
     * §14 — Members search by name or phone.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('full_name', 'like', "%{$term}%")
                ->orWhere('phone_number', 'like', "%{$term}%");
        });
    }
}

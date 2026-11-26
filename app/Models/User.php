<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone_number',
        'password',
        'role',
        'profile_image',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'locked_until' => 'datetime',
            'must_change_password' => 'boolean',
        ];
    }

    /**
     * Display-only: "+220 835190199" rather than the stored
     * "+220835190199" — same formatting as Member::phoneNumberFormatted().
     */
    protected function phoneNumberFormatted(): Attribute
    {
        return Attribute::get(
            fn () => $this->phone_number
                ? substr($this->phone_number, 0, -9).' '.substr($this->phone_number, -9)
                : null,
        );
    }

    public function createdMembers(): HasMany
    {
        return $this->hasMany(Member::class, 'created_by');
    }

    public function receivedPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'received_by');
    }

    /**
     * Our custom in-app notification rows. Named distinctly from notifications()
     * because Notifiable's HasDatabaseNotifications trait already defines that
     * method against Laravel's own polymorphic notifications table, which this
     * app doesn't use — Notifiable is kept only for Breeze's mail-based auth
     * notifications (e.g. password reset).
     */
    public function appNotifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function passwordHistories(): HasMany
    {
        return $this->hasMany(PasswordHistory::class);
    }

    public function loginAttempts(): HasMany
    {
        return $this->hasMany(LoginAttempt::class);
    }

    /**
     * Whether the account is currently locked out by the login lockout
     * policy (LoginForm).
     */
    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    /**
     * Every place a password gets set (Admin user management, profile
     * update, password reset) saves through this model, so recording the
     * hash here means none of them can forget to.
     */
    protected static function booted(): void
    {
        // created/updated rather than saved: wasRecentlyCreated stays true
        // for the rest of an instance's life, so a saved() hook couldn't
        // tell "just created" from "created earlier, saved again now".
        static::created(fn (User $user) => $user->recordPasswordHistory());

        static::updated(function (User $user) {
            if (! $user->wasChanged('password')) {
                return;
            }

            $user->recordPasswordHistory();

            // Every path that sets a password validates it against the
            // current policy first, so a new password always satisfies a
            // pending "must change" requirement.
            if ($user->must_change_password) {
                $user->forceFill(['must_change_password' => false])->saveQuietly();
            }
        });
    }

    private function recordPasswordHistory(): void
    {
        $this->passwordHistories()->create(['password' => $this->password]);

        $stale = $this->passwordHistories()
            ->latest('id')
            ->skip(PasswordHistory::MAX_KEPT)
            ->take(PHP_INT_MAX)
            ->pluck('id');

        if ($stale->isNotEmpty()) {
            PasswordHistory::whereKey($stale)->delete();
        }
    }

    /**
     * §14 — Users search by name or email.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%");
        });
    }
}

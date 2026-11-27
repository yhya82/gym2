<?php

namespace App\Models;

use App\Enums\Theme;
use App\Observers\ApplicationSettingObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

#[ObservedBy([ApplicationSettingObserver::class])]
class ApplicationSetting extends Model
{
    protected $table = 'application_settings';

    // Singleton row: id is always 1 (enforced by a CHECK constraint), never
    // auto-generated, so Eloquent shouldn't treat it as an auto-increment key.
    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'application_name',
        'logo',
        'location',
        'email',
        'phone',
        'currency',
        'timezone',
        'default_theme',
        'password_min_length',
        'password_require_uppercase',
        'password_require_lowercase',
        'password_require_number',
        'password_require_symbol',
        'lockout_attempts',
        'lockout_minutes',
        'password_history',
        'session_timeout_minutes',
    ];

    /**
     * The Security Center's policy fields and the values they fall back to.
     * These match the column defaults in the migration, which reproduce the
     * app's behavior from before the policy was configurable.
     */
    public const SECURITY_DEFAULTS = [
        'password_min_length' => 8,
        'password_require_uppercase' => false,
        'password_require_lowercase' => false,
        'password_require_number' => false,
        'password_require_symbol' => false,
        'lockout_attempts' => 5,
        'lockout_minutes' => 10,
        'password_history' => 0,
        'session_timeout_minutes' => 120,
    ];

    protected function casts(): array
    {
        return [
            'default_theme' => Theme::class,
            'password_min_length' => 'integer',
            'password_require_uppercase' => 'boolean',
            'password_require_lowercase' => 'boolean',
            'password_require_number' => 'boolean',
            'password_require_symbol' => 'boolean',
            'lockout_attempts' => 'integer',
            'lockout_minutes' => 'integer',
            'password_history' => 'integer',
            'session_timeout_minutes' => 'integer',
        ];
    }

    /**
     * The current security policy as a plain array, always complete.
     *
     * Read from login, password validation and session middleware — code
     * that also runs during installs, migrations and console commands,
     * where the settings row (or these columns) may not exist yet. Rather
     * than let that break logins, any missing value falls back to
     * SECURITY_DEFAULTS.
     *
     * @return array<string, int|bool>
     */
    public static function securityPolicy(): array
    {
        try {
            $settings = static::current();
        } catch (\Throwable) {
            return self::SECURITY_DEFAULTS;
        }

        $policy = [];

        foreach (self::SECURITY_DEFAULTS as $key => $default) {
            $value = $settings->getAttribute($key);
            $policy[$key] = $value === null ? $default : (is_bool($default) ? (bool) $value : (int) $value);
        }

        return $policy;
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('application_settings'));
    }

    /**
     * Settings render on nearly every page (sidebar branding, theme), so this
     * is cached rather than queried per request.
     */
    public static function current(): self
    {
        return Cache::rememberForever('application_settings', fn () => static::findOrFail(1));
    }

    /**
     * A browser-usable URL for a file on the "media" disk (see the note at
     * the top of config/filesystems.php).
     *
     * Asks the disk what it can do rather than assuming: the R2 bucket is
     * private, so it gets a short-lived signed URL; the local public disk
     * can't sign, and doesn't need to, so it gets a plain /storage/... URL.
     * That's what lets the same code run locally and in production.
     */
    public static function urlFor(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        try {
            $disk = Storage::disk('media');

            return $disk->providesTemporaryUrls()
                ? $disk->temporaryUrl($path, now()->addMinutes(10))
                : $disk->url($path);
        } catch (\Throwable $e) {
            // Misconfigured/unreachable storage shouldn't take down every
            // page that renders the logo — fall back to "no logo" so callers
            // show the default icon instead.
            return null;
        }
    }
}

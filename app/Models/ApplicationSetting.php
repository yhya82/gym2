<?php

namespace App\Models;

use App\Enums\Theme;
use App\Observers\ApplicationSettingObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

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
    ];

    protected function casts(): array
    {
        return [
            'default_theme' => Theme::class,
        ];
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
}

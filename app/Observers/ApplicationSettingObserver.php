<?php

namespace App\Observers;

use App\Models\ApplicationSetting;
use App\Services\AuditLogger;

class ApplicationSettingObserver
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    // Only updated() — the singleton row is seeded once, never created or
    // deleted via user action (§20 — "Only Admin can access settings").
    public function updated(ApplicationSetting $setting): void
    {
        $changes = $this->audit->describeChanges($setting);

        if ($changes === '') {
            return;
        }

        $this->audit->log('update', 'settings', "Updated application settings ({$changes}).");
    }
}

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
    //
    // Password-policy fields are logged separately under the "security"
    // module, so they show in the Security Center's event feed rather than
    // being mixed in with branding/contact edits.
    public function updated(ApplicationSetting $setting): void
    {
        $securityKeys = array_keys(ApplicationSetting::SECURITY_DEFAULTS);

        $generalExcept = ['updated_at', ...$securityKeys];
        $general = $this->audit->describeChanges($setting, $generalExcept);

        if ($general !== '') {
            $this->audit->log('update', 'settings', "Updated application settings ({$general}).",
                changes: $this->audit->changesFor($setting, $generalExcept));
        }

        $nonSecurityKeys = array_diff(array_keys($setting->getChanges()), $securityKeys);
        $security = $this->audit->describeChanges($setting, $nonSecurityKeys);

        if ($security !== '') {
            $this->audit->log('update-policy', 'security', "Updated the password policy ({$security}).",
                changes: $this->audit->changesFor($setting, $nonSecurityKeys));
        }
    }
}

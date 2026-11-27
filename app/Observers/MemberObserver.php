<?php

namespace App\Observers;

use App\Models\Member;
use App\Services\AuditLogger;

class MemberObserver
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Handle the Member "created" event.
     *
     * Unambiguous — a Member row is only ever created by
     * MemberRegistrationService::register(), so this always means "Member
     * creation" per §19.1, with no risk of misattributing another action.
     */
    public function created(Member $member): void
    {
        $this->audit->log('create', 'members', "Registered member \"{$member->full_name}\".", subject: $member);
    }

    /**
     * Handle the Member "updated" event.
     *
     * Unlike created()/deleted(), a Member update is genuinely ambiguous —
     * it can come from a renewal (status flip) or the expiry cron (status
     * flip, no authenticated user), as well as a genuine profile edit. Both
     * of those known, unambiguous call sites wrap their own update in
     * Member::withoutEvents() to suppress this generic handler — renewal
     * logs its own precise "renew" entry instead (see
     * MembershipRenewalService), and the cron logs nothing at all, since
     * "membership expired" isn't in §19.1's list. What reaches this handler
     * is therefore always a genuine edit.
     */
    public function updated(Member $member): void
    {
        $diff = $this->audit->changesFor($member);
        $changes = $this->audit->describeChanges($member);

        if ($changes === '') {
            return;
        }

        $this->audit->log('update', 'members', "Updated member \"{$member->full_name}\" ({$changes}).", subject: $member, changes: $diff);
    }

    /**
     * Handle the Member "deleted" event (soft delete = archive, §11).
     */
    public function deleted(Member $member): void
    {
        $this->audit->log('archive', 'members', "Archived member \"{$member->full_name}\".", subject: $member);
    }

    /**
     * Undoing an archive (Admin-only, MemberPolicy::restore) is as much a
     * change to who counts as a member as archiving was.
     */
    public function restored(Member $member): void
    {
        $this->audit->log('restore', 'members', "Restored archived member \"{$member->full_name}\".", subject: $member);
    }
}

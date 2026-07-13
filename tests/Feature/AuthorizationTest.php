<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_receptionist_is_forbidden_from_admin_only_routes(): void
    {
        $receptionist = User::factory()->create(['role' => UserRole::Receptionist]);

        $this->actingAs($receptionist)->get('/plans')->assertForbidden();
        $this->actingAs($receptionist)->get('/payments')->assertForbidden();
        $this->actingAs($receptionist)->get('/users')->assertForbidden();
        $this->actingAs($receptionist)->get('/settings')->assertForbidden();
    }

    public function test_admin_can_access_admin_only_routes(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get('/plans')->assertOk();
        $this->actingAs($admin)->get('/payments')->assertOk();
        $this->actingAs($admin)->get('/users')->assertOk();
        $this->actingAs($admin)->get('/settings')->assertOk();
    }

    /**
     * §3.2 Restricted Actions: archiving/restoring a member and viewing the
     * Archived filter are Admin-only, even though the Members module itself
     * is shared with the Receptionist.
     */
    public function test_receptionist_cannot_archive_or_restore_a_member(): void
    {
        $receptionist = User::factory()->create(['role' => UserRole::Receptionist]);
        $member = Member::factory()->create();
        $archived = Member::factory()->create();
        $archived->delete();

        $this->actingAs($receptionist)->delete("/members/{$member->id}")->assertForbidden();
        $this->actingAs($receptionist)->post("/members/{$archived->id}/restore")->assertForbidden();
    }

    public function test_admin_can_archive_and_restore_a_member(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $member = Member::factory()->create();

        $this->actingAs($admin)->delete("/members/{$member->id}")->assertRedirect(route('members.index'));
        $this->assertTrue($member->fresh()->trashed());

        $this->actingAs($admin)->post("/members/{$member->id}/restore")->assertRedirect(route('members.show', $member));
        $this->assertFalse($member->fresh()->trashed());
    }

    public function test_receptionist_can_still_view_and_create_members(): void
    {
        $receptionist = User::factory()->create(['role' => UserRole::Receptionist]);
        $member = Member::factory()->create();

        $this->actingAs($receptionist)->get('/members')->assertOk();
        $this->actingAs($receptionist)->get("/members/{$member->id}")->assertOk();
        $this->actingAs($receptionist)->get('/members/create')->assertOk();
    }

    public function test_admin_cannot_deactivate_their_own_account(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->delete("/users/{$admin->id}")->assertForbidden();
        $this->assertFalse($admin->fresh()->trashed());
    }

    /**
     * The guard is on the resulting count of active Admins, not on who's
     * acting. In the running app this branch is only reachable via
     * self-deactivation (the role:admin middleware means only an Admin can
     * ever reach /users/*, and if exactly one Admin exists, they're the only
     * one who could be acting) — so this checks the policy directly, as a
     * defense-in-depth guarantee independent of that routing detail.
     */
    public function test_policy_blocks_deactivating_the_last_admin_regardless_of_actor(): void
    {
        $lastAdmin = User::factory()->create(['role' => UserRole::Admin]);
        $anotherAdmin = User::factory()->create(['role' => UserRole::Admin]);
        $anotherAdmin->delete();

        $this->assertSame(1, User::where('role', UserRole::Admin)->count());
        $this->assertFalse($anotherAdmin->can('delete', $lastAdmin));
    }

    public function test_admin_can_deactivate_a_receptionist_or_a_non_last_admin(): void
    {
        $actingAdmin = User::factory()->create(['role' => UserRole::Admin]);
        $otherAdmin = User::factory()->create(['role' => UserRole::Admin]);
        $receptionist = User::factory()->create(['role' => UserRole::Receptionist]);

        $this->actingAs($actingAdmin)->delete("/users/{$receptionist->id}")->assertRedirect(route('users.index'));
        $this->assertTrue($receptionist->fresh()->trashed());

        $this->actingAs($actingAdmin)->delete("/users/{$otherAdmin->id}")->assertRedirect(route('users.index'));
        $this->assertTrue($otherAdmin->fresh()->trashed());
    }
}

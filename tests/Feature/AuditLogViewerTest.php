<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogViewerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_the_audit_log(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'create',
            'module' => 'members',
            'description' => 'Registered member "Jane Doe".',
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->actingAs($admin)->get('/audit-logs');

        $response->assertOk();
        $response->assertSee('Jane Doe', false);
    }

    public function test_receptionist_is_forbidden_from_the_audit_log(): void
    {
        $receptionist = User::factory()->create(['role' => UserRole::Receptionist]);

        $this->actingAs($receptionist)->get('/audit-logs')->assertForbidden();
    }

    public function test_search_filters_by_description_action_or_user_name(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'name' => 'Alice Admin']);
        AuditLog::create(['user_id' => $admin->id, 'action' => 'create', 'module' => 'members', 'description' => 'Registered member "Jane Doe".']);
        AuditLog::create(['user_id' => $admin->id, 'action' => 'update', 'module' => 'plans', 'description' => 'Updated plan "Monthly".']);

        $response = $this->actingAs($admin)->get('/audit-logs?search=Jane');

        $response->assertSee('Registered member', false);
        $response->assertDontSee('Updated plan "Monthly"', false);
    }

    public function test_module_filter_narrows_results(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        AuditLog::create(['user_id' => $admin->id, 'action' => 'create', 'module' => 'members', 'description' => 'Member entry']);
        AuditLog::create(['user_id' => $admin->id, 'action' => 'update', 'module' => 'plans', 'description' => 'Plan entry']);

        $response = $this->actingAs($admin)->get('/audit-logs?module=plans');

        $response->assertSee('Plan entry', false);
        $response->assertDontSee('Member entry', false);
    }

    /**
     * A log entry must keep attributing its action to a staff member even
     * after that account is later deactivated — the belongsTo relation is
     * intentionally withTrashed() for exactly this.
     */
    public function test_a_deactivated_users_past_actions_still_show_their_name(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $staff = User::factory()->create(['role' => UserRole::Receptionist, 'name' => 'Former Employee']);
        AuditLog::create(['user_id' => $staff->id, 'action' => 'create', 'module' => 'members', 'description' => 'Registered a member']);
        $staff->delete();

        $response = $this->actingAs($admin)->get('/audit-logs');

        $response->assertSee('Former Employee', false);
        $response->assertDontSee('Deleted user', false);
    }
}

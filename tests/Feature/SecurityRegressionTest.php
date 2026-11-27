<?php

namespace Tests\Feature;

use App\Enums\MembershipStatus;
use App\Enums\UserRole;
use App\Livewire\MemberForm;
use App\Livewire\MemberIndex;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Services\MemberRegistrationService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression tests for the holes found by the security / logic audit. Each
 * one was a failing attack before its fix.
 */
class SecurityRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    private function receptionist(): User
    {
        return User::factory()->create(['role' => UserRole::Receptionist]);
    }

    private function register(User $staff, string $name = 'Jane Doe', ?string $start = null, string $phone = '+220835190199'): Member
    {
        $this->actingAs($staff);

        return app(MemberRegistrationService::class)->register(
            $name, $phone, Plan::factory()->create(['price' => '500.00', 'duration_days' => 30]),
            $start ? Carbon::parse($start) : now(), '100.00', $staff,
        );
    }

    // --------------------------------------------- archived members (A1–A3)

    public function test_a_receptionist_cannot_list_archived_members_through_the_url(): void
    {
        $this->register($this->admin(), 'Archived Person')->delete();

        $this->actingAs($this->receptionist())
            ->get('/members?status=archived')
            ->assertOk()
            ->assertDontSee('Archived Person');

        Livewire::withQueryParams(['status' => 'archived'])
            ->actingAs($this->receptionist())
            ->test(MemberIndex::class)
            ->assertSet('status', 'all')
            ->assertDontSee('Archived Person');
    }

    public function test_an_admin_still_sees_archived_members_through_the_url(): void
    {
        $this->register($this->admin(), 'Archived Person')->delete();

        $this->actingAs($this->admin())
            ->get('/members?status=archived')
            ->assertSee('Archived Person');
    }

    public function test_an_admin_can_open_an_archived_members_history_but_a_receptionist_cannot(): void
    {
        $admin = $this->admin();
        $member = $this->register($admin, 'Archived Person');
        $member->delete();

        $this->actingAs($admin)->get("/members/{$member->id}")
            ->assertOk()
            ->assertSee('Archived Person')
            ->assertSee('Restore member');

        $this->actingAs($this->receptionist())->get("/members/{$member->id}")->assertForbidden();
    }

    // -------------------------------------------------- free reactivation (B1)

    public function test_editing_the_start_date_cannot_reactivate_an_expired_member(): void
    {
        $member = $this->register($this->admin(), start: now()->subDays(60)->toDateString());
        $member->currentSubscription->update(['status' => MembershipStatus::Expired]);
        $member->update(['status' => MembershipStatus::Expired]);
        $payments = Payment::count();

        Livewire::actingAs($this->receptionist())
            ->test(MemberForm::class)
            ->call('loadForEdit', $member->id)
            ->set('start_date', now()->toDateString())
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(MembershipStatus::Expired, $member->fresh()->status);
        $this->assertSame(MembershipStatus::Expired, $member->currentSubscription()->first()->status);
        $this->assertSame($payments, Payment::count());
    }

    public function test_a_backdated_correction_can_still_end_an_active_membership(): void
    {
        $member = $this->register($this->admin());

        Livewire::actingAs($this->admin())
            ->test(MemberForm::class)
            ->call('loadForEdit', $member->id)
            ->set('start_date', now()->subDays(60)->toDateString())
            ->call('save');

        $this->assertSame(MembershipStatus::Expired, $member->fresh()->status);
    }

    // ------------------------------------------------- money input (B2–B6, B9)

    public function test_amounts_must_be_plain_two_decimal_numbers(): void
    {
        $admin = $this->admin();
        $subscriptionId = $this->register($admin)->currentSubscription->id;

        foreach (['1e2', '10.005', '-5', '100000000'] as $bad) {
            $this->actingAs($admin)->post('/payments', ['subscription_id' => $subscriptionId, 'amount' => $bad])
                ->assertSessionHasErrors('amount');
        }

        // And the service refuses them too, for any caller that skips validation.
        $this->expectException(\InvalidArgumentException::class);
        app(PaymentService::class)->record($this->register($admin, 'Other', phone: '+220835190188')->currentSubscription, '10.005', $admin);
    }

    public function test_plan_price_and_duration_are_bounded_by_their_columns(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/plans', ['plan_name' => 'Huge', 'duration_days' => 30, 'price' => '1000000000'])
            ->assertSessionHasErrors('price');
        $this->actingAs($admin)->post('/plans', ['plan_name' => 'Forever', 'duration_days' => 70000, 'price' => '10'])
            ->assertSessionHasErrors('duration_days');
    }

    public function test_payments_cannot_be_dated_in_the_future(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/payments', [
            'subscription_id' => $this->register($admin)->currentSubscription->id,
            'amount' => '50.00',
            'payment_date' => now()->addDay()->toDateString(),
        ])->assertSessionHasErrors('payment_date');
    }

    public function test_payments_cannot_be_recorded_for_archived_members(): void
    {
        $member = $this->register($this->admin());
        $subscriptionId = $member->currentSubscription->id;
        $member->delete();

        $this->actingAs($this->receptionist())
            ->post('/payments', ['subscription_id' => $subscriptionId, 'amount' => '20.00'])
            ->assertSessionHasErrors(['amount' => 'This member is archived — restore them before recording a payment.']);

        $this->assertSame(1, Payment::where('subscription_id', $subscriptionId)->count());
    }

    // ------------------------------------------------------------ export (C1)

    public function test_member_csv_export_neutralises_spreadsheet_formulas(): void
    {
        $admin = $this->admin();
        $this->register($admin, '=1+1');

        $csv = $this->actingAs($admin)->get('/members/export')->streamedContent();
        $nameCell = str_getcsv(explode("\n", trim($csv))[1])[0];

        $this->assertSame("'=1+1", $nameCell);
    }

    // ----------------------------------------------------------- sessions (D1)

    /**
     * A real session login (not actingAs(), which pins one user object in
     * memory across requests), with the guard's cached user cleared between
     * requests — the way each real request starts fresh.
     */
    private function sessionLogin(User $user): void
    {
        $this->withSession([Auth::guard('web')->getName() => $user->id]);
    }

    private function freshRequest(): void
    {
        $this->app['auth']->forgetGuards();
    }

    public function test_a_session_stays_valid_while_the_password_is_unchanged(): void
    {
        $user = User::factory()->create();
        $this->sessionLogin($user);

        $this->get('/dashboard')->assertOk();
        $this->freshRequest();
        $this->get('/dashboard')->assertOk();
    }

    public function test_a_password_change_elsewhere_ends_this_session(): void
    {
        $user = User::factory()->create();
        $this->sessionLogin($user);
        $this->get('/dashboard')->assertOk();

        // The password changes from another device, or an Admin resets it.
        User::whereKey($user->id)->first()->forceFill(['password' => Hash::make('changed-elsewhere')])->save();
        $this->freshRequest();

        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    // ------------------------------------------------------------ headers (E1)

    public function test_responses_carry_security_headers_and_no_php_version(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeaderMissing('X-Powered-By');
        // Only over HTTPS, so local http:// development keeps working.
        $response->assertHeaderMissing('Strict-Transport-Security');
    }
}

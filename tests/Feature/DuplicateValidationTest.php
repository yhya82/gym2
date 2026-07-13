<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Member;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DuplicateValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_plan_with_a_duplicate_name_fails_validation_instead_of_500ing(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Plan::factory()->create(['plan_name' => 'Monthly']);

        $response = $this->actingAs($admin)->post('/plans', [
            'plan_name' => 'Monthly',
            'duration_days' => 30,
            'price' => 50,
        ]);

        $response->assertSessionHasErrors('plan_name');
        $this->assertSame(1, Plan::where('plan_name', 'Monthly')->count());
    }

    public function test_updating_a_plan_to_a_name_used_by_another_plan_fails_validation(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Plan::factory()->create(['plan_name' => 'Monthly']);
        $weekly = Plan::factory()->create(['plan_name' => 'Weekly']);

        $response = $this->actingAs($admin)->put("/plans/{$weekly->id}", [
            'plan_name' => 'Monthly',
            'duration_days' => $weekly->duration_days,
            'price' => $weekly->price,
        ]);

        $response->assertSessionHasErrors('plan_name');
        $this->assertSame('Weekly', $weekly->fresh()->plan_name);
    }

    /**
     * A plan can reuse a name once the original is archived — the DB's
     * generated-column uniqueness only applies to active rows, and the
     * validation rule is deliberately scoped to match.
     */
    public function test_creating_a_plan_with_an_archived_plans_former_name_succeeds(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $original = Plan::factory()->create(['plan_name' => 'Monthly']);
        $original->delete();

        $response = $this->actingAs($admin)->post('/plans', [
            'plan_name' => 'Monthly',
            'duration_days' => 30,
            'price' => 50,
        ]);

        $response->assertRedirect(route('plans.index'));
        $this->assertDatabaseHas('plans', ['plan_name' => 'Monthly', 'deleted_at' => null]);
    }

    public function test_registering_a_member_with_a_phone_number_already_in_use_fails_validation_instead_of_500ing(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $plan = Plan::factory()->create(['price' => 50]);
        Member::factory()->create(['phone_number' => '+2207001234']);

        $response = $this->actingAs($admin)->post('/members', [
            'full_name' => 'Duplicate Phone',
            'phone_number' => '+2207001234',
            'plan_id' => $plan->id,
            'start_date' => now()->toDateString(),
            'payment_amount' => 10,
        ]);

        $response->assertSessionHasErrors('phone_number');
        $this->assertSame(1, Member::where('phone_number', '+2207001234')->count());
    }

    public function test_editing_a_member_to_a_phone_number_already_in_use_fails_validation_via_the_form_component(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Member::factory()->create(['phone_number' => '+2207001234']);
        $editing = Member::factory()->create(['phone_number' => '+2207005678']);

        Livewire::actingAs($admin)
            ->test(\App\Livewire\MemberForm::class)
            ->call('loadForEdit', $editing->id)
            ->set('full_name', $editing->full_name)
            ->set('phone_number', '+2207001234')
            ->call('save')
            ->assertHasErrors('phone_number');

        $this->assertSame('+2207005678', $editing->fresh()->phone_number);
    }
}

<?php

namespace Tests\Feature\Auth;

use App\Models\ApplicationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class LoginPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        ApplicationSetting::current()->update(['lockout_attempts' => 3, 'lockout_minutes' => 10]);
        User::factory()->create(['email' => 'staff@example.com']);
    }

    private function login(string $email, string $password)
    {
        return Volt::test('pages.auth.login')
            ->set('form.email', $email)
            ->set('form.password', $password)
            ->call('login');
    }

    public function test_invalid_credentials_say_how_many_attempts_are_left(): void
    {
        $this->login('staff@example.com', 'wrong')
            ->assertHasErrors(['form.credentials' => 'Invalid credentials. 2 attempts left before this account is locked.']);

        $this->login('staff@example.com', 'wrong')
            ->assertHasErrors(['form.credentials' => 'Invalid credentials. 1 attempt left before this account is locked.']);
    }

    public function test_an_unknown_email_gets_exactly_the_same_messages(): void
    {
        // Same wording, same countdown, same lock — the page never reveals
        // whether an email belongs to an account.
        $this->login('nobody@example.com', 'wrong')
            ->assertHasErrors(['form.credentials' => 'Invalid credentials. 2 attempts left before this account is locked.']);
        $this->login('nobody@example.com', 'wrong')
            ->assertHasErrors(['form.credentials' => 'Invalid credentials. 1 attempt left before this account is locked.']);
        $this->login('nobody@example.com', 'wrong')
            ->assertHasErrors(['form.locked' => trans('auth.locked')]);
    }

    public function test_the_lockout_message_has_no_time_in_it(): void
    {
        $this->login('staff@example.com', 'wrong');
        $this->login('staff@example.com', 'wrong');

        $component = $this->login('staff@example.com', 'wrong')
            ->assertHasErrors(['form.locked' => 'This account is locked after too many failed attempts. Try again later or ask an Admin to unlock it.'])
            ->assertHasNoErrors('form.credentials')
            ->assertSee('This account is locked');

        $this->assertDoesNotMatchRegularExpression('/\d+\s*(minute|second)/i', $component->errors()->first('form.locked'));

        // Even the correct password gets the same banner while locked.
        $this->login('staff@example.com', 'password')->assertHasErrors('form.locked');
        $this->assertGuest();
    }

    public function test_each_validation_error_belongs_to_its_own_field(): void
    {
        $this->login('', '')->assertHasErrors([
            'form.email' => 'The email field is required.',
            'form.password' => 'The password field is required.',
        ]);

        $this->login('not-an-email', 'x')
            ->assertHasErrors(['form.email' => 'Enter a valid email address.'])
            ->assertHasNoErrors('form.password');
    }

    public function test_the_password_is_cleared_after_any_error_but_the_email_is_kept(): void
    {
        $this->login('staff@example.com', 'wrong')
            ->assertSet('form.password', '')
            ->assertSet('form.email', 'staff@example.com');

        $this->login('not-an-email', 'something')
            ->assertSet('form.password', '')
            ->assertSet('form.email', 'not-an-email');
    }

    public function test_errors_render_under_their_fields_with_both_fields_marked_for_bad_credentials(): void
    {
        $this->login('staff@example.com', 'wrong')
            ->assertSeeHtml('id="password-error"')
            ->assertDontSeeHtml('id="email-error"')
            // Both fields start out invalid (red + shake) for bad credentials.
            ->assertSeeHtml('x-data="{ invalid: true }"')
            ->assertSeeHtml('x-data="{ invalid: true, show: false }"');
    }

    public function test_the_password_field_has_a_show_hide_toggle(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Show password')
            ->assertSeeHtml('aria-controls="password"');
    }

    public function test_a_successful_login_still_works_and_resets_the_countdown(): void
    {
        $this->login('staff@example.com', 'wrong');

        $this->login('staff@example.com', 'password')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        auth()->logout();

        $this->login('staff@example.com', 'wrong')
            ->assertHasErrors(['form.credentials' => 'Invalid credentials. 2 attempts left before this account is locked.']);
    }
}

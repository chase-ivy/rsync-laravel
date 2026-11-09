<?php

namespace Tests\Feature\Auth;

use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Step 3 — behaviour of registration.php, now in Laravel.
 */
class RegisterTest extends TestCase
{
    use RefreshDatabase;

    private function form(array $overrides = []): array
    {
        return array_merge([
            'email' => 'kobe@gmail.com',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
            'role' => 'tenant',
        ], $overrides);
    }

    public function test_page_shows_role_choice_without_a_plan(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('Create account')
            ->assertSee('name="_token"', false)
            ->assertSee('<select name="role" required>', false)
            ->assertDontSee('Selected plan');
    }

    public function test_plan_link_registers_as_landlord(): void
    {
        $this->get('/register?role=landlord&plan=Professional')
            ->assertOk()
            ->assertSee('Selected plan')
            ->assertSee('Professional')
            ->assertSee('<input type="hidden" name="role" value="landlord">', false)
            ->assertDontSee('<select name="role" required>', false);
    }

    public function test_successful_registration_redirects_to_login_like_before(): void
    {
        Event::fake([Registered::class]);

        $this->post('/register', $this->form())
            ->assertRedirect(route('login') . '?registered=1');

        $user = DB::table('users')->where('email', 'kobe@gmail.com')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->name);   // name and birthday are filled in later on the Account page
        $this->assertNull($user->age);
        $this->assertSame('tenant', $user->role);
        $this->assertNull($user->email_verified_at);
        $this->assertTrue(Hash::check('Secret123', $user->password));
        // Same bcrypt format as the old app. Cost comes from BCRYPT_ROUNDS (10 in .env; tests use 4 for speed).
        $this->assertSame(
            sprintf('$2y$%02d$', config('hashing.bcrypt.rounds')),
            substr($user->password, 0, 7)
        );
        $this->assertGuest();
        Event::assertDispatched(Registered::class);
    }

    public function test_new_user_can_log_in(): void
    {
        $this->post('/register', $this->form());

        $this->post('/login', ['email' => 'kobe@gmail.com', 'password' => 'Secret123'])
            ->assertRedirect(route('tenant.dashboard'));
    }

    public function test_duplicate_email_is_rejected_even_with_different_capitals(): void
    {
        $this->post('/register', $this->form());

        $this->post('/register', $this->form(['email' => 'KOBE@Gmail.com']))
            ->assertOk()
            ->assertSee('Email already exists.')
            ->assertSee('value="KOBE@Gmail.com"', false);

        $this->assertSame(1, DB::table('users')->count());
    }

    public function test_invalid_role_is_rejected(): void
    {
        $this->post('/register', $this->form(['role' => 'admin']))
            ->assertOk()
            ->assertSee('<div class="err">', false);

        $this->assertSame(0, DB::table('users')->count());
    }

    public function test_missing_or_bad_fields_are_rejected(): void
    {
        foreach ([['email' => ''], ['email' => 'not-an-email'], ['password' => ''], ['role' => '']] as $bad) {
            $this->post('/register', $this->form($bad))
                ->assertOk()
                ->assertSee('<div class="err">', false);
        }

        $this->assertSame(0, DB::table('users')->count());
    }

    public function test_form_values_are_escaped(): void
    {
        $this->post('/register', $this->form(['email' => '"><script>x</script>']))
            ->assertOk()
            ->assertDontSee('<script>x</script>', false);
    }

    public function test_register_asks_only_for_email_password_and_role(): void
    {
        $this->get('/register')
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="password_confirmation"', false)
            ->assertSee('name="role"', false)
            ->assertDontSee('name="name"', false)
            ->assertDontSee('name="age"', false);
    }

    public function test_password_must_be_strong(): void
    {
        $weak = [
            'Short1' => 'at least 8 characters',
            'secret123' => 'uppercase and one lowercase',
            'SECRET123' => 'uppercase and one lowercase',
            'SecretPass' => 'at least one number',
        ];
        foreach ($weak as $password => $message) {
            $this->post('/register', $this->form(['password' => $password, 'password_confirmation' => $password]))
                ->assertOk()
                ->assertSee($message);
        }
        $this->post('/register', $this->form(['password_confirmation' => 'Different123']))->assertSee('The passwords do not match.');

        $this->assertSame(0, DB::table('users')->count());
    }

    public function test_login_page_links_to_register(): void
    {
        $this->get('/login')->assertSee('href="' . route('register') . '"', false);
    }

    public function test_old_registration_address_redirects_and_keeps_the_plan(): void
    {
        $response = $this->get('/registration.php?role=landlord&plan=Starter')->assertStatus(301);

        $target = parse_url($response->headers->get('Location'));
        parse_str($target['query'] ?? '', $query);
        $this->assertSame('/register', $target['path']);
        $this->assertSame('Starter', $query['plan'] ?? null);
        $this->assertSame('landlord', $query['role'] ?? null);
    }

    // ---- Plan choice for landlords and sublessors ----

    public function test_plan_choice_is_on_the_page_but_hidden_until_landlord_or_sublessor(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('id="plan-field" hidden', false)
            ->assertSee('<select name="plan" id="plan" disabled>', false)
            ->assertSee('Professional — ₱399/mo');
    }

    public function test_landlord_and_sublessor_must_choose_a_plan(): void
    {
        foreach (['landlord', 'sublessor'] as $role) {
            $this->post('/register', $this->form(['role' => $role, 'email' => "$role@gmail.com"]))
                ->assertOk()
                ->assertSee('Please choose a plan.')
                // the plan choice stays open so the user can fix it
                ->assertDontSee('id="plan-field" hidden', false)
                ->assertSee('<select name="plan" id="plan" required>', false);
        }

        $this->assertSame(0, DB::table('users')->count());
    }

    public function test_landlord_and_sublessor_with_a_plan_are_registered(): void
    {
        $this->post('/register', $this->form(['role' => 'landlord', 'plan' => 'Starter', 'email' => 'l@gmail.com']))
            ->assertRedirect(route('login') . '?registered=1');
        $this->post('/register', $this->form(['role' => 'sublessor', 'plan' => 'Enterprise', 'email' => 's@gmail.com']))
            ->assertRedirect(route('login') . '?registered=1');

        $this->assertSame('landlord', DB::table('users')->where('email', 'l@gmail.com')->value('role'));
        $this->assertSame('sublessor', DB::table('users')->where('email', 's@gmail.com')->value('role'));
    }

    public function test_unknown_plan_is_rejected(): void
    {
        $this->post('/register', $this->form(['role' => 'landlord', 'plan' => 'Free Forever']))
            ->assertOk()
            ->assertSee('<div class="err">', false);

        $this->assertSame(0, DB::table('users')->count());
    }

    public function test_tenant_does_not_need_a_plan(): void
    {
        $this->post('/register', $this->form(['role' => 'tenant']))
            ->assertRedirect(route('login') . '?registered=1');
    }

    public function test_error_keeps_the_chosen_role_and_plan_in_the_dropdowns(): void
    {
        $this->post('/register', $this->form(['role' => 'sublessor', 'plan' => 'Professional', 'email' => 'bad']))
            ->assertOk()
            ->assertSee('<select name="role" required>', false)
            ->assertSee('<option value="sublessor" selected>', false)
            ->assertSee('<option value="Professional" selected>', false)
            ->assertDontSee('Selected plan');
    }

    public function test_error_after_a_pricing_button_keeps_the_locked_plan(): void
    {
        $this->post('/register', $this->form(['role' => 'landlord', 'plan' => 'Enterprise', 'plan_locked' => '1', 'email' => 'bad']))
            ->assertOk()
            ->assertSee('Selected plan')
            ->assertSee('<input type="hidden" name="plan" value="Enterprise">', false)
            ->assertDontSee('<select name="role" required>', false);
    }

    public function test_pricing_link_with_an_unknown_plan_shows_the_normal_page(): void
    {
        $this->get('/register?role=landlord&plan=Hacker')
            ->assertOk()
            ->assertDontSee('Selected plan')
            ->assertDontSee('Hacker')
            ->assertSee('<select name="role" required>', false);
    }
}

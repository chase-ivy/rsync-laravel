<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Step 2 — behaviour of login.php / logout.php / oopPHP::login(), now in Laravel.
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    /** Create a user exactly like the legacy app did: password_hash(PASSWORD_DEFAULT), cost 10. */
    private function makeUser(string $email, string $role, string $password = 'secret123'): int
    {
        return DB::table('users')->insertGetId([
            'name' => ucfirst($role) . ' User',
            'age' => 21,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT, ['cost' => 10]),
            'role' => $role,
        ], 'user_id');
    }

    public function test_login_page_shows_the_form_with_csrf_token(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Sign in')
            ->assertSee('name="_token"', false)
            ->assertDontSee("alert('Invalid login credentials.')", false);
    }

    public function test_tenant_goes_to_tenant_dashboard(): void
    {
        $this->makeUser('kobe@gmail.com', 'tenant');

        $this->post('/login', ['email' => 'kobe@gmail.com', 'password' => 'secret123'])
            ->assertRedirect(route('tenant.dashboard'));
        $this->assertAuthenticated();
    }

    public function test_landlord_goes_to_landlord_dashboard(): void
    {
        $this->makeUser('mae@gmail.com', 'landlord');

        $this->post('/login', ['email' => 'mae@gmail.com', 'password' => 'secret123'])
            ->assertRedirect(route('landlord.dashboard'));
    }

    public function test_sublessor_goes_to_home(): void
    {
        $this->makeUser('sub@gmail.com', 'sublessor');

        $this->post('/login', ['email' => 'sub@gmail.com', 'password' => 'secret123'])
            ->assertRedirect(route('home'));
    }

    public function test_email_is_case_insensitive_like_the_old_database(): void
    {
        $this->makeUser('kobe@gmail.com', 'tenant');

        $this->post('/login', ['email' => 'KOBE@Gmail.com', 'password' => 'secret123'])
            ->assertRedirect(route('tenant.dashboard'));
    }

    public function test_wrong_password_shows_the_legacy_alert_and_stays_logged_out(): void
    {
        $this->makeUser('kobe@gmail.com', 'tenant');

        $this->post('/login', ['email' => 'kobe@gmail.com', 'password' => 'wrong'])
            ->assertOk()
            ->assertSee("alert('Invalid login credentials.')", false);
        $this->assertGuest();
    }

    public function test_unknown_email_shows_the_same_alert(): void
    {
        $this->post('/login', ['email' => 'nobody@gmail.com', 'password' => 'x'])
            ->assertOk()
            ->assertSee("alert('Invalid login credentials.')", false);
        $this->assertGuest();
    }

    public function test_login_does_not_rewrite_the_stored_password_hash(): void
    {
        $id = $this->makeUser('kobe@gmail.com', 'tenant');
        $before = DB::table('users')->where('user_id', $id)->value('password');

        $this->post('/login', ['email' => 'kobe@gmail.com', 'password' => 'secret123']);

        $this->assertSame($before, DB::table('users')->where('user_id', $id)->value('password'));
    }

    public function test_session_id_changes_after_login(): void
    {
        $this->makeUser('kobe@gmail.com', 'tenant');
        $this->get('/login');
        $before = session()->getId();

        $this->post('/login', ['email' => 'kobe@gmail.com', 'password' => 'secret123']);

        $this->assertNotSame($before, session()->getId());
    }

    public function test_logout_by_link_and_by_form(): void
    {
        $this->makeUser('kobe@gmail.com', 'tenant');

        foreach (['get', 'post'] as $method) {
            $this->post('/login', ['email' => 'kobe@gmail.com', 'password' => 'secret123']);
            $this->assertAuthenticated();

            $this->$method('/logout')->assertRedirect(route('login'));
            $this->assertGuest();
        }
    }

    public function test_tenant_dashboard_is_only_for_tenants(): void
    {
        $this->get('/tenant/dashboard')->assertRedirect(route('login'));

        $this->makeUser('mae@gmail.com', 'landlord');
        $this->post('/login', ['email' => 'mae@gmail.com', 'password' => 'secret123']);
        $this->get('/tenant/dashboard')->assertRedirect(route('login'));
        $this->get('/logout');

        $this->makeUser('kobe@gmail.com', 'tenant');
        $this->post('/login', ['email' => 'kobe@gmail.com', 'password' => 'secret123']);
        $this->get('/tenant/dashboard')->assertOk()->assertSee('Your Dashboard');
    }

    public function test_landlord_dashboard_is_only_for_landlords(): void
    {
        $this->get('/landlord/dashboard')->assertRedirect(route('login'));

        $this->makeUser('kobe@gmail.com', 'tenant');
        $this->post('/login', ['email' => 'kobe@gmail.com', 'password' => 'secret123']);
        $this->get('/landlord/dashboard')->assertRedirect(route('login'));
    }

    public function test_password_reset_message_is_shown_once(): void
    {
        $this->withSession(['reset_success' => 'Password updated. Please sign in.'])
            ->get('/login')
            ->assertSee('Password updated. Please sign in.');

        $this->get('/login')->assertDontSee('Password updated. Please sign in.');
    }

    public function test_old_php_addresses_redirect(): void
    {
        $this->get('/login.php')->assertRedirect('/login')->assertStatus(301);
        $this->get('/logout.php')->assertRedirect('/logout')->assertStatus(301);
    }
}
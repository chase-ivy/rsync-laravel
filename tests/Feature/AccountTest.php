<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Account page (details, email verification, password) and the logged-in navigation links.
 */
class AccountTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email = 'kobe@gmail.com', string $role = 'tenant', bool $verified = true): User
    {
        $user = User::create(['name' => 'Kobe', 'age' => 21, 'email' => $email, 'password' => Hash::make('secret123'), 'role' => $role]);
        if ($verified) {
            $user->markEmailAsVerified();
        }

        return $user;
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/account')->assertRedirect(route('login'));
        $this->post('/account/profile', ['name' => 'X'])->assertRedirect(route('login'));
        $this->post('/account/password', [])->assertRedirect(route('login'));
    }

    public function test_every_role_can_open_the_account_page(): void
    {
        foreach (['tenant', 'landlord', 'sublessor', 'admin'] as $i => $role) {
            $this->actingAs($this->user("u{$i}@gmail.com", $role))
                ->get('/account')
                ->assertOk()
                ->assertSee('My Account')
                ->assertSee("u{$i}@gmail.com")
                ->assertSee('Change password');
        }
    }

    public function test_page_shows_verification_status(): void
    {
        $this->actingAs($this->user('a@gmail.com'))->get('/account')->assertSee('Verified')->assertDontSee('Resend verification link');
        $this->actingAs($this->user('b@gmail.com', 'tenant', false))->get('/account')->assertSee('Not verified')->assertSee('Resend verification link');
    }

    public function test_resend_link_from_account_page(): void
    {
        Notification::fake();
        $user = $this->user('b@gmail.com', 'tenant', false);

        $this->actingAs($user)->from('/account')->post(route('verification.send'))->assertRedirect('/account');
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_update_name_and_age(): void
    {
        $user = $this->user();

        $this->actingAs($user)->from('/account')
            ->post('/account/profile', ['name' => 'Kobe Bryant', 'age' => 22, 'email' => 'kobe@gmail.com'])
            ->assertRedirect('/account')
            ->assertSessionHas('msg', 'Your details were saved.');

        $user->refresh();
        $this->assertSame('Kobe Bryant', $user->name);
        $this->assertSame(22, $user->age);
        $this->assertTrue($user->hasVerifiedEmail());   // same email: still verified
    }

    public function test_email_change_needs_the_current_password(): void
    {
        $user = $this->user();

        $this->actingAs($user)->from('/account')
            ->post('/account/profile', ['name' => 'Kobe', 'age' => 21, 'email' => 'new@gmail.com', 'current_password' => 'wrong'])
            ->assertRedirect('/account')
            ->assertSessionHasErrorsIn('profile', ['current_password']);

        $this->assertSame('kobe@gmail.com', $user->fresh()->email);
    }

    public function test_email_change_must_be_verified_again(): void
    {
        Notification::fake();
        $user = $this->user();

        $this->actingAs($user)->from('/account')
            ->post('/account/profile', ['name' => 'Kobe', 'age' => 21, 'email' => 'new@gmail.com', 'current_password' => 'secret123'])
            ->assertRedirect('/account');

        $user->refresh();
        $this->assertSame('new@gmail.com', $user->email);
        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_email_already_used_is_rejected(): void
    {
        $this->user('taken@gmail.com');
        $user = $this->user();

        $this->actingAs($user)->from('/account')
            ->post('/account/profile', ['name' => 'Kobe', 'age' => 21, 'email' => 'TAKEN@gmail.com', 'current_password' => 'secret123'])
            ->assertSessionHasErrorsIn('profile', ['email']);

        $this->assertSame('kobe@gmail.com', $user->fresh()->email);
    }

    public function test_change_password(): void
    {
        $user = $this->user();

        $this->actingAs($user)->from('/account')
            ->post('/account/password', ['current_password' => 'secret123', 'password' => 'newpass456', 'password_confirmation' => 'newpass456'])
            ->assertRedirect('/account')
            ->assertSessionHas('msg', 'Your password was changed.');

        $this->assertTrue(Hash::check('newpass456', $user->fresh()->password));
    }

    public function test_change_password_rejects_bad_input(): void
    {
        $user = $this->user();
        $post = fn (array $data) => $this->actingAs($user)->from('/account')->post('/account/password', $data);

        $post(['current_password' => 'wrong', 'password' => 'newpass456', 'password_confirmation' => 'newpass456'])
            ->assertSessionHasErrorsIn('password', ['current_password']);
        $post(['current_password' => 'secret123', 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertSessionHasErrorsIn('password', ['password']);
        $post(['current_password' => 'secret123', 'password' => 'newpass456', 'password_confirmation' => 'different1'])
            ->assertSessionHasErrorsIn('password', ['password']);

        $this->assertTrue(Hash::check('secret123', $user->fresh()->password));
    }

    public function test_dashboards_have_an_account_link(): void
    {
        $this->actingAs($this->user('t@gmail.com'))->get('/tenant/dashboard')
            ->assertSeeInOrder(['Account', 'Browse Rooms', 'Logout']);
        $this->actingAs($this->user('l@gmail.com', 'landlord'))->get('/landlord/dashboard')
            ->assertSeeInOrder(['Account', 'Maintenance', 'Logout']);
    }

    public function test_logged_in_tenant_browsing_rooms_sees_dashboard_account_and_logout(): void
    {
        $room = Room::create(['room_name' => 'Room 201', 'price' => 4500, 'description' => 'x', 'max_occupants' => 2]);
        $tenant = $this->user('t@gmail.com');

        foreach (['/rooms', '/rooms/' . $room->room_id, '/'] as $url) {
            $this->actingAs($tenant)->get($url)
                ->assertSee(route('tenant.dashboard'))
                ->assertSee(route('account'))
                ->assertSee(route('logout'))
                ->assertDontSee('>Login</a>', false);
        }
    }

    public function test_guests_still_see_login(): void
    {
        $this->get('/rooms')->assertSee('>Login</a>', false)->assertDontSee(route('account'));
    }
}
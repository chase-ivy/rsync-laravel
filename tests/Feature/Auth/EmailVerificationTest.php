<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Part C3 — email verification (new feature).
 */
class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attrs = []): User
    {
        return User::create(array_merge([
            'name' => 'Kobe', 'age' => 21, 'email' => 'kobe@gmail.com',
            'password' => Hash::make('secret123'), 'role' => 'tenant',
        ], $attrs));
    }

    private function link(User $user, ?string $email = null): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->user_id,
            'hash' => sha1($email ?? $user->email),
        ]);
    }

    public function test_registering_sends_a_verification_email_and_says_so(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'Kobe', 'age' => 21, 'email' => 'kobe@gmail.com', 'password' => 'secret123', 'role' => 'tenant',
        ]);

        $user = User::where('email', 'kobe@gmail.com')->first();
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->assertFalse($user->hasVerifiedEmail());

        $this->get('/login?registered=1')->assertSee('We sent a verification link to your email.');
    }

    public function test_the_link_verifies_the_email_without_being_logged_in(): void
    {
        $user = $this->user();

        $this->get($this->link($user))
            ->assertRedirect(route('login'));

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->get('/login')->assertSee('Your email is verified. You can now sign in.');
    }

    public function test_the_link_while_logged_in_goes_back_to_the_dashboard(): void
    {
        $user = $this->user();

        $this->actingAs($user)->get($this->link($user))
            ->assertRedirect(route('tenant.dashboard'));

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_a_changed_or_unsigned_link_does_nothing(): void
    {
        $user = $this->user();

        $this->get($this->link($user, 'someone-else@gmail.com'))->assertForbidden();        // wrong hash
        $this->get('/email/verify/' . $user->user_id . '/' . sha1($user->email))->assertForbidden(); // no signature
        $this->get($this->link($user) . 'x')->assertForbidden();                              // tampered

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_an_expired_link_does_nothing(): void
    {
        $user = $this->user();
        $link = $this->link($user);

        $this->travel(61)->minutes();

        $this->get($link)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_unverified_user_can_log_in_and_gets_a_link_at_most_every_10_minutes(): void
    {
        Notification::fake();
        $user = $this->user();

        $this->post('/login', ['email' => 'kobe@gmail.com', 'password' => 'secret123'])
            ->assertRedirect(route('tenant.dashboard'));
        $this->get('/tenant/dashboard')->assertOk()->assertSee('Please verify your email');
        $this->get('/logout');

        $this->post('/login', ['email' => 'kobe@gmail.com', 'password' => 'secret123']);   // again right away
        Notification::assertSentToTimes($user, VerifyEmail::class, 1);
    }

    public function test_verified_user_gets_no_email_and_no_banner(): void
    {
        Notification::fake();
        $user = $this->user();
        $user->markEmailAsVerified();

        $this->post('/login', ['email' => 'kobe@gmail.com', 'password' => 'secret123']);
        $this->get('/tenant/dashboard')->assertOk()->assertDontSee('Please verify your email');

        Notification::assertNothingSent();
    }

    public function test_resend_button_sends_a_new_link(): void
    {
        Notification::fake();
        $user = $this->user();

        $this->actingAs($user)->from('/tenant/dashboard')
            ->post('/email/verification-notification')
            ->assertRedirect('/tenant/dashboard');

        Notification::assertSentTo($user, VerifyEmail::class);
        $this->get('/tenant/dashboard')->assertSee('A new verification link has been sent to kobe@gmail.com.');
    }

    public function test_verified_middleware_blocks_actions_until_verified(): void
    {
        // A pretend action route, protected the way real actions (apply, pay, ...) will be.
        Route::middleware(['web', 'auth', 'verified'])->post('/_test-action', fn () => 'done');
        $user = $this->user();

        $this->actingAs($user)->post('/_test-action')->assertRedirect(route('verification.notice'));
        $this->get(route('verification.notice'))->assertOk()->assertSee('Verify your email')->assertSee('kobe@gmail.com');

        $user->markEmailAsVerified();
        $this->actingAs($user->fresh())->post('/_test-action')->assertOk()->assertSee('done');
    }

    public function test_verification_pages_need_login(): void
    {
        $this->get('/email/verify')->assertRedirect(route('login'));
        $this->post('/email/verification-notification')->assertRedirect(route('login'));
    }

    public function test_the_email_contains_a_working_link(): void
    {
        Notification::fake();
        $user = $this->user();
        $user->sendEmailVerificationNotification();

        Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $mail) use ($user) {
            $url = $mail->toMail($user)->actionUrl;
            $this->get($url)->assertRedirect(route('login'));
            return true;
        });
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }
}
<?php

namespace Tests\Feature\Auth;

use App\Models\PasswordReset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Part C4 — forgot password with a 6-digit OTP.
 */
class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::create([
            'name' => 'Kobe', 'age' => 21, 'email' => 'kobe@gmail.com',
            'password' => Hash::make('old-password'), 'role' => 'tenant',
        ]);
    }

    /** Runs step 1 and returns the code that was saved. */
    private function requestCode(string $email = 'kobe@gmail.com'): string
    {
        $this->post('/forgot-password', ['email' => $email])->assertRedirect(route('password.otp'));

        return PasswordReset::latest('id')->value('otp');
    }

    public function test_page_looks_like_the_old_one(): void
    {
        $this->get('/forgot-password')
            ->assertOk()
            ->assertSee('Forgot your password?')
            ->assertSee('name="_token"', false)
            ->assertSee('Send OTP');
    }

    public function test_unknown_email_shows_the_old_message(): void
    {
        $this->post('/forgot-password', ['email' => 'nobody@gmail.com'])
            ->assertOk()
            ->assertSee('No account found with that email.')
            ->assertSee('value="nobody@gmail.com"', false);

        $this->assertSame(0, PasswordReset::count());
    }

    public function test_sends_a_6_digit_code_that_expires_in_10_minutes(): void
    {
        $this->user();
        Event::fake([MessageSent::class]);

        $otp = $this->requestCode('KOBE@gmail.com');   // capitals still find the account

        $this->assertMatchesRegularExpression('/^\d{6}$/', $otp);
        $reset = PasswordReset::first();
        $this->assertSame('kobe@gmail.com', $reset->email);
        $this->assertFalse($reset->used);
        $this->assertEqualsWithDelta(now()->addMinutes(10)->timestamp, $reset->expires_at->timestamp, 5);

        Event::assertDispatched(MessageSent::class, function (MessageSent $e) use ($otp) {
            return $e->message->getSubject() === 'RSYNC — Your Password Reset OTP'
                && str_contains($e->message->getTextBody(), "Your OTP code is: {$otp}")
                && $e->message->getTo()[0]->getAddress() === 'kobe@gmail.com';
        });
    }

    public function test_asking_again_replaces_the_old_code(): void
    {
        $this->user();
        $this->requestCode();
        $this->requestCode();

        $this->assertSame(1, PasswordReset::count());
    }

    public function test_full_flow_changes_the_password_and_shows_the_login_message(): void
    {
        $user = $this->user();
        $otp = $this->requestCode();

        $this->get('/verify-otp')->assertOk()->assertSee('kobe@gmail.com');
        $this->post('/verify-otp', ['otp' => $otp])->assertRedirect(route('password.reset'));
        $this->get('/reset-password')->assertOk()->assertSee('Choose a new password');

        $this->post('/reset-password', ['password' => 'new-password', 'password_confirmation' => 'new-password'])
            ->assertRedirect(route('login'));

        $this->get('/login')->assertSee('Password updated. Please sign in.');
        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
        $this->assertTrue(PasswordReset::first()->used);
        $this->assertTrue($user->fresh()->hasVerifiedEmail(), 'getting the code proves the email');

        $this->post('/login', ['email' => 'kobe@gmail.com', 'password' => 'new-password'])
            ->assertRedirect(route('tenant.dashboard'));
    }

    public function test_wrong_code_is_rejected(): void
    {
        $this->user();
        $otp = $this->requestCode();
        $wrong = $otp === '000000' ? '111111' : '000000';

        $this->post('/verify-otp', ['otp' => $wrong])->assertOk()->assertSee('Invalid or expired OTP.');
        $this->get('/reset-password')->assertRedirect(route('password.request'));
    }

    public function test_expired_code_is_rejected(): void
    {
        $this->user();
        $otp = $this->requestCode();

        $this->travel(11)->minutes();

        $this->post('/verify-otp', ['otp' => $otp])->assertOk()->assertSee('Invalid or expired OTP.');
    }

    public function test_five_wrong_codes_kill_the_code(): void
    {
        $this->user();
        $otp = $this->requestCode();
        $wrong = $otp === '000000' ? '111111' : '000000';

        for ($i = 1; $i <= 5; $i++) {
            $response = $this->post('/verify-otp', ['otp' => $wrong]);
        }
        $response->assertSee('Too many wrong codes. Please request a new OTP.');

        // Even the right code no longer works.
        $this->post('/verify-otp', ['otp' => $otp])->assertOk()->assertSee('Please request a new OTP.');
        $this->get('/reset-password')->assertRedirect(route('password.request'));
    }

    public function test_code_works_only_once(): void
    {
        $this->user();
        $otp = $this->requestCode();
        $this->post('/verify-otp', ['otp' => $otp]);
        $this->post('/reset-password', ['password' => 'new-password', 'password_confirmation' => 'new-password']);

        $this->withSession(['reset_email' => 'kobe@gmail.com'])
            ->post('/verify-otp', ['otp' => $otp])
            ->assertSee('Invalid or expired OTP.');
    }

    public function test_passwords_must_match_and_not_be_empty(): void
    {
        $user = $this->user();
        $this->post('/verify-otp', ['otp' => $this->requestCode()]);

        $this->post('/reset-password', ['password' => 'aaa', 'password_confirmation' => 'bbb'])
            ->assertOk()->assertSee('Passwords do not match.');
        $this->post('/reset-password', ['password' => '', 'password_confirmation' => ''])
            ->assertOk()->assertSee('Please enter a new password.');

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_steps_cannot_be_skipped(): void
    {
        $user = $this->user();

        $this->get('/verify-otp')->assertRedirect(route('password.request'));
        $this->post('/verify-otp', ['otp' => '123456'])->assertRedirect(route('password.request'));
        $this->get('/reset-password')->assertRedirect(route('password.request'));
        $this->post('/reset-password', ['password' => 'x', 'password_confirmation' => 'x'])
            ->assertRedirect(route('password.request'));

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_dev_code_is_shown_only_locally_with_the_log_mailer(): void
    {
        $this->user();
        // Pretending to be "local"/"production" switches off the test-only CSRF bypass, so skip that check here.
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);

        config(['mail.default' => 'log']);
        $this->app['env'] = 'local';
        $otp = $this->requestCode();
        $this->get('/verify-otp')->assertSee("DEV: your code is <strong>{$otp}</strong>", false);

        config(['mail.default' => 'array']);   // a "real" mailer that works
        $this->app['env'] = 'production';
        $this->requestCode();
        $this->get('/verify-otp')->assertDontSee('DEV: your code is');
    }

    public function test_if_the_email_cannot_be_sent_the_user_is_told(): void
    {
        $this->user();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 9]);  // nothing listens there

        // Live server: friendly message, no code left behind.
        $this->app['env'] = 'production';
        $this->post('/forgot-password', ['email' => 'kobe@gmail.com'])
            ->assertOk()
            ->assertSee("We couldn't send the email right now. Please try again later.");
        $this->assertSame(0, PasswordReset::count());

        // Own PC: like the old app's DEV FALLBACK, the code is shown on screen instead.
        $this->app['env'] = 'local';
        $this->post('/forgot-password', ['email' => 'kobe@gmail.com'])->assertRedirect(route('password.otp'));
        $this->get('/verify-otp')->assertSee('DEV: your code is');
    }

    public function test_old_addresses_redirect(): void
    {
        $this->get('/forgot_password.php')->assertRedirect('/forgot-password');
        $this->get('/verify_otp.php')->assertRedirect('/verify-otp');
    }
}
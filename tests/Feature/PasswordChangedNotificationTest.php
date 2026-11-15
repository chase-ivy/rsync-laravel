<?php

namespace Tests\Feature;

use App\Models\PasswordReset;
use App\Models\User;
use App\Notifications\PasswordChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

/**
 * Feature 1: an email "Your RSYNC password was changed" after a password change
 * (Account page and Forgot password). A failed email never undoes the change.
 */
class PasswordChangedNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $user = User::create(['name' => 'Kobe Bryant', 'age' => 21, 'email' => 'kobe@gmail.com', 'password' => Hash::make('OldPass123'), 'role' => 'tenant']);
        $user->markEmailAsVerified();

        return $user;
    }

    public function test_account_page_password_change_sends_the_email(): void
    {
        Notification::fake();
        $user = $this->user();

        $this->actingAs($user)->from('/account')->post('/account/password', [
            'current_password' => 'OldPass123', 'password' => 'NewPass456', 'password_confirmation' => 'NewPass456',
        ])->assertSessionHas('msg', 'Your password was changed.');

        Notification::assertSentTo($user, PasswordChanged::class);
        Notification::assertCount(1);
    }

    public function test_no_email_when_the_change_fails(): void
    {
        Notification::fake();
        $user = $this->user();

        $this->actingAs($user)->from('/account')->post('/account/password', [
            'current_password' => 'wrong', 'password' => 'NewPass456', 'password_confirmation' => 'NewPass456',
        ])->assertSessionHasErrors('current_password', null, 'password');
        $this->post('/account/password', ['current_password' => 'OldPass123', 'password' => 'short', 'password_confirmation' => 'short']);

        Notification::assertNothingSent();
        $this->assertTrue(Hash::check('OldPass123', $user->fresh()->password));
    }

    public function test_forgot_password_reset_sends_the_email(): void
    {
        $user = $this->user();
        $this->post('/forgot-password', ['email' => 'kobe@gmail.com']);
        $otp = PasswordReset::latest('id')->value('otp');
        $this->post('/verify-otp', ['otp' => $otp])->assertRedirect(route('password.reset'));

        Notification::fake();   // after the OTP email, so only the confirmation is counted
        $this->post('/reset-password', ['password' => 'Brand1New', 'password_confirmation' => 'Brand1New'])
            ->assertRedirect(route('login'));

        Notification::assertSentTo($user, PasswordChanged::class);
        $this->assertTrue(Hash::check('Brand1New', $user->fresh()->password));
    }

    public function test_email_text_has_the_manila_time_and_the_reset_link(): void
    {
        $user = $this->user();
        $mail = (new PasswordChanged(Carbon::parse('2026-11-15 02:30:00', 'UTC')))->toMail($user);

        $this->assertSame('Your RSYNC password was changed', $mail->subject);
        $text = implode("\n", array_merge($mail->introLines, $mail->outroLines));
        $this->assertStringContainsString('November 15, 2026 at 10:30 AM', $text);   // UTC+8
        $this->assertStringContainsString("If this wasn't you, reset your password now", $text);
        $this->assertSame(route('password.request'), $mail->actionUrl);

        $html = (string) $mail->render();
        $this->assertStringContainsString(route('password.request'), $html);
    }

    public function test_a_failed_email_does_not_undo_the_change(): void
    {
        $user = $this->user();
        // Make every notification fail, like Brevo being down.
        $this->app->instance(\Illuminate\Contracts\Notifications\Dispatcher::class, new class {
            public function send($n, $x) { throw new RuntimeException('mail down'); }
            public function sendNow($n, $x, $c = null) { throw new RuntimeException('mail down'); }
        });

        $this->actingAs($user)->from('/account')->post('/account/password', [
            'current_password' => 'OldPass123', 'password' => 'NewPass456', 'password_confirmation' => 'NewPass456',
        ])->assertRedirect('/account')->assertSessionHas('msg', 'Your password was changed.');

        $this->assertTrue(Hash::check('NewPass456', $user->fresh()->password));
    }
}

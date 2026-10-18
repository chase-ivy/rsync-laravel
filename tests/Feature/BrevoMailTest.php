<?php

namespace Tests\Feature;

use App\Models\PasswordReset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Part H3 — emails go out through Brevo's web API (MAIL_MAILER=brevo).
 * No real email is sent: Http::fake() stands in for Brevo.
 */
class BrevoMailTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://api.brevo.com/v3/smtp/email';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'mail.default' => 'brevo',
            'services.brevo.key' => 'xkeysib-test',
            'mail.from.address' => 'rsync.notifications@gmail.com',
            'mail.from.name' => 'RSYNC',
        ]);
    }

    public function test_verification_email_is_sent_through_brevo(): void
    {
        Http::fake([self::API => Http::response(['messageId' => '<abc@brevo>'], 201)]);

        $this->post('/register', ['name' => 'Juan', 'age' => 21, 'email' => 'juan@gmail.com', 'password' => 'secret123',
            'role' => 'tenant', 'gender' => 'Male'])->assertRedirect();

        Http::assertSent(function (HttpRequest $r) {
            return $r->url() === self::API
                && $r->method() === 'POST'
                && $r->hasHeader('api-key', 'xkeysib-test')
                && $r['sender'] === ['email' => 'rsync.notifications@gmail.com', 'name' => 'RSYNC']
                && $r['to'][0]['email'] === 'juan@gmail.com'
                && $r['subject'] === 'Verify your email address'
                && str_contains($r['htmlContent'], '/email/verify/')
                && str_contains($r['textContent'], '/email/verify/');
        });
    }

    public function test_password_reset_code_is_sent_through_brevo(): void
    {
        Http::fake([self::API => Http::response(['messageId' => '<abc@brevo>'], 201)]);
        User::create(['name' => 'Juan', 'age' => 21, 'email' => 'juan@gmail.com', 'password' => Hash::make('x'), 'role' => 'tenant']);

        $this->post('/forgot-password', ['email' => 'juan@gmail.com'])->assertRedirect(route('password.otp'));

        $code = PasswordReset::firstOrFail()->otp;
        Http::assertSent(fn (HttpRequest $r) => $r['to'][0]['email'] === 'juan@gmail.com'
            && str_contains($r['htmlContent'] ?? $r['textContent'], (string) $code));
    }

    public function test_brevo_error_is_handled_like_any_mail_failure(): void
    {
        Http::fake([self::API => Http::response(['code' => 'unauthorized', 'message' => 'Key not found'], 401)]);
        User::create(['name' => 'Juan', 'age' => 21, 'email' => 'juan@gmail.com', 'password' => Hash::make('x'), 'role' => 'tenant']);
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
        $this->app['env'] = 'production';

        $this->post('/forgot-password', ['email' => 'juan@gmail.com'])
            ->assertOk()
            ->assertSee("We couldn't send the email right now. Please try again later.");
        $this->assertSame(0, PasswordReset::count());
    }
}
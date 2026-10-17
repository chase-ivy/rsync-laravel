<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PasswordReset;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Forgot password with a 6-digit code (OTP).
 *
 * Step 1 (email)        — same as forgot_password.php.
 * Step 2 (code)         — verify_otp.php was linked from the old app but never existed; built new.
 * Step 3 (new password) — built new; ends with the "Password updated" message login.php already shows.
 */
class ForgotPasswordController extends Controller
{
    /** Wrong codes allowed before the code stops working. */
    private const MAX_ATTEMPTS = 5;

    // ---------- Step 1: email ----------

    /** GET /forgot-password */
    public function showEmailForm()
    {
        return view('auth.forgot-password', ['error' => null]);
    }

    /** POST /forgot-password — same flow as forgot_password.php. */
    public function sendCode(Request $request)
    {
        $email = trim((string) $request->input('email', ''));

        // Case-insensitive like the old MariaDB database.
        $user = User::whereRaw('LOWER(email) = LOWER(?)', [$email])->first();

        if (! $user) {
            return view('auth.forgot-password', ['error' => 'No account found with that email.']);
        }

        // Delete old codes for this email, then make a new one that expires in 10 minutes.
        PasswordReset::whereRaw('LOWER(email) = LOWER(?)', [$user->email])->delete();

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        PasswordReset::create([
            'email' => $user->email,
            'otp' => $otp,
            'expires_at' => now()->addMinutes(10),
            'used' => false,
        ]);

        $request->session()->put('reset_email', $user->email);
        $request->session()->forget(['reset_attempts', 'reset_verified', 'dev_otp']);

        // Same email text as the old app.
        try {
            Mail::raw(
                "Hello {$user->name},\n\n"
                . "Your OTP code is: {$otp}\n\n"
                . "This code expires in 10 minutes.\n"
                . "If you did not request this, ignore this email.\n\n"
                . '— RSYNC Team',
                fn ($message) => $message->to($user->email)->subject('RSYNC — Your Password Reset OTP')
            );
            $sent = true;
        } catch (Throwable $e) {
            report($e);   // written to storage/logs/laravel.log
            $sent = false;
        }

        // Old app's "DEV FALLBACK": on your own PC, show the code on screen when emails
        // only go to the log file, or when sending failed.
        if (app()->isLocal() && (! $sent || config('mail.default') === 'log')) {
            $request->session()->put('dev_otp', $otp);
        } elseif (! $sent) {
            PasswordReset::where('email', $user->email)->delete();
            $request->session()->forget('reset_email');

            return view('auth.forgot-password', ['error' => "We couldn't send the email right now. Please try again later."]);
        }

        return redirect()->route('password.otp');
    }

    // ---------- Step 2: code ----------

    /** GET /verify-otp */
    public function showCodeForm(Request $request)
    {
        if (! $request->session()->has('reset_email')) {
            return redirect()->route('password.request');
        }

        return view('auth.verify-otp', [
            'email' => $request->session()->get('reset_email'),
            'devOtp' => $request->session()->get('dev_otp'),
            'error' => null,
        ]);
    }

    /** POST /verify-otp */
    public function checkCode(Request $request)
    {
        $email = $request->session()->get('reset_email');
        if (! $email) {
            return redirect()->route('password.request');
        }

        $code = trim((string) $request->input('otp', ''));
        $reset = PasswordReset::where('email', $email)->where('used', false)->latest('id')->first();

        $valid = $reset
            && $reset->expires_at->isFuture()
            && preg_match('/^\d{6}$/', $code)
            && hash_equals($reset->otp, $code);

        if (! $valid) {
            // Too many wrong tries → the code stops working (stops guessing all 1,000,000 codes).
            $attempts = $request->session()->increment('reset_attempts');
            if ($reset && $attempts >= self::MAX_ATTEMPTS) {
                $reset->update(['used' => true]);
            }

            return view('auth.verify-otp', [
                'email' => $email,
                'devOtp' => $request->session()->get('dev_otp'),
                'error' => $attempts >= self::MAX_ATTEMPTS
                    ? 'Too many wrong codes. Please request a new OTP.'
                    : 'Invalid or expired OTP.',
            ]);
        }

        $request->session()->put('reset_verified', $email);
        $request->session()->forget(['reset_attempts', 'dev_otp']);

        return redirect()->route('password.reset');
    }

    // ---------- Step 3: new password ----------

    /** GET /reset-password */
    public function showResetForm(Request $request)
    {
        if (! $request->session()->has('reset_verified')) {
            return redirect()->route('password.request');
        }

        return view('auth.reset-password', ['error' => null]);
    }

    /** POST /reset-password */
    public function reset(Request $request)
    {
        $email = $request->session()->get('reset_verified');
        if (! $email) {
            return redirect()->route('password.request');
        }

        $password = (string) $request->input('password', '');
        if ($password === '') {
            return view('auth.reset-password', ['error' => 'Please enter a new password.']);
        }
        if ($password !== (string) $request->input('password_confirmation', '')) {
            return view('auth.reset-password', ['error' => 'Passwords do not match.']);
        }

        $user = User::where('email', $email)->first();
        $reset = PasswordReset::where('email', $email)->where('used', false)->latest('id')->first();

        // The code must still be valid (not used, not expired) at the moment of saving.
        if (! $user || ! $reset || ! $reset->expires_at->isFuture()) {
            $request->session()->forget(['reset_email', 'reset_verified']);

            return redirect()->route('password.request');
        }

        $user->password = Hash::make($password);
        // Getting the code proves they own the email, so it counts as verified too.
        if (! $user->hasVerifiedEmail()) {
            $user->email_verified_at = now();
        }
        $user->save();

        $reset->update(['used' => true]);

        $request->session()->forget(['reset_email', 'reset_verified', 'reset_attempts', 'dev_otp']);
        $request->session()->put('reset_success', 'Password updated. Please sign in.');

        return redirect()->route('login');
    }
}
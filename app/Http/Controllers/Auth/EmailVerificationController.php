<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;

/**
 * Email verification (new feature, not in the old app).
 *
 * - notice: "Check your email" page; shown when an unverified user tries a protected action.
 * - verify: the link from the email. Works even if the user is not logged in.
 * - send:   "Resend link" button.
 */
class EmailVerificationController extends Controller
{
    /** GET /email/verify */
    public function notice(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route($user->homeRoute());
        }

        return view('auth.verify-email', ['email' => $user->email]);
    }

    /** GET /email/verify/{id}/{hash} — the link is signed and expires after 60 minutes. */
    public function verify(Request $request, string $id, string $hash)
    {
        $user = User::find($id);

        // The hash proves the link was made for this email address.
        abort_unless($user && hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        // Clicked while logged in as this user → back to their page.
        if ($request->user()?->is($user)) {
            return redirect()->route($user->homeRoute())->with('status', 'Your email is verified. Thank you!');
        }

        return redirect()->route('login')->with('status', 'Your email is verified. You can now sign in.');
    }

    /** POST /email/verification-notification — limited to 6 per minute. */
    public function send(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route($user->homeRoute());
        }

        $user->sendEmailVerificationNotification();

        return back()->with('status', 'A new verification link has been sent to ' . $user->email . '.');
    }
}
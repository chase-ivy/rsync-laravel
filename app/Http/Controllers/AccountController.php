<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Account page (new): every logged-in user can see and edit their details,
 * check or resend email verification, and change their password, all on one page.
 */
class AccountController extends Controller
{
    /** GET /account */
    public function show(Request $request): View
    {
        return view('account.show', ['user' => $request->user()]);
    }

    /** POST /account/profile: name, age and email. Changing the email needs the current password. */
    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validateWithBag('profile', [
            'name' => ['required', 'string', 'max:100'],
            'age' => ['required', 'integer', 'min:1', 'max:120'],
            'email' => ['required', 'email', 'max:100'],
            'current_password' => ['nullable', 'string'],
        ], [
            'name.required' => 'Please enter your name.',
            'age.required' => 'Please enter your age.',
            'email.required' => 'Please enter your email.',
            'email.email' => 'Please enter a valid email address.',
        ]);

        $email = trim($data['email']);
        $emailChanged = mb_strtolower($email) !== mb_strtolower($user->email);

        if ($emailChanged) {
            // Same rule as registration: one account per email, upper/lower case ignored.
            $taken = User::whereRaw('LOWER(email) = LOWER(?)', [$email])
                ->where('user_id', '!=', $user->user_id)
                ->exists();
            if ($taken) {
                return back()->withErrors(['email' => 'That email is already used by another account.'], 'profile')->withInput();
            }
            // Protects the account if someone else is using a logged-in computer.
            if (! Hash::check((string) ($data['current_password'] ?? ''), $user->password)) {
                return back()->withErrors(['current_password' => 'Enter your current password to change your email.'], 'profile')->withInput();
            }
        }

        $user->name = $data['name'];
        $user->age = (int) $data['age'];

        if ($emailChanged) {
            $user->email = $email;
            $user->email_verified_at = null;   // the new address must be verified again
        }
        $user->save();

        if (! $emailChanged) {
            return back()->with('msg', 'Your details were saved.');
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            report($e);
            return back()->with('msg', "err:Email changed, but we couldn't send the verification link right now. Use \"Resend link\" below.");
        }

        return back()->with('msg', "Email changed. We sent a verification link to {$email}.");
    }

    /** POST /account/password */
    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validateWithBag('password', [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'max:100', 'confirmed'],
        ], [
            'current_password.required' => 'Please enter your current password.',
            'password.required' => 'Please enter a new password.',
            'password.min' => 'The new password must be at least 8 characters.',
            'password.confirmed' => 'The new passwords do not match.',
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Your current password is incorrect.'], 'password');
        }
        if (Hash::check($data['password'], $user->password)) {
            return back()->withErrors(['password' => 'The new password must be different from the current one.'], 'password');
        }

        $user->password = Hash::make($data['password']);
        $user->save();

        // New session id, so an old copied session cookie stops working.
        $request->session()->regenerate();

        return back()->with('msg', 'Your password was changed.');
    }
}
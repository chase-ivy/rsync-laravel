<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\PrivateFiles;
use App\Support\RoomPhotos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

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

    /** POST /account/photo: upload or replace the profile picture. */
    public function updatePhoto(Request $request): RedirectResponse
    {
        $request->validateWithBag('photo', [
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'photo.required' => 'Please choose a picture.',
            'photo.image' => 'The profile picture must be an image.',
            'photo.mimes' => 'The picture must be JPG, PNG or WEBP.',
            'photo.max' => 'The picture must be 5 MB or smaller.',
        ]);

        $user = $request->user();
        $name = 'avatar_' . $user->user_id . '_' . $this->fileName($request->file('photo'));

        try {
            RoomPhotos::store($request->file('photo'), $name);
        } catch (\Throwable $e) {
            report($e);
            return back()->with('msg', "err:We couldn't upload the picture right now. Please try again.");
        }

        $old = $user->profile_photo;
        $user->forceFill(['profile_photo' => $name])->save();
        if ($old) {
            RoomPhotos::delete($old);   // fine if it is already gone
        }

        return back()->with('msg', 'Your profile picture was updated.');
    }

    /** POST /account/photo/remove */
    public function removePhoto(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->profile_photo) {
            RoomPhotos::delete($user->profile_photo);
            $user->forceFill(['profile_photo' => null])->save();
        }

        return back()->with('msg', 'Your profile picture was removed.');
    }

    /** POST /account/permit: landlords and sublessors upload their business permit for the admin to review. */
    public function uploadPermit(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isOwner(), 403);

        $request->validateWithBag('permit', [
            'permit' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ], [
            'permit.required' => 'Please choose the file of your business permit.',
            'permit.mimes' => 'The permit must be a picture (JPG, PNG, WEBP) or a PDF.',
            'permit.max' => 'The file must be 5 MB or smaller.',
        ]);

        $name = 'permit_' . $user->user_id . '_' . $this->fileName($request->file('permit'));

        try {
            PrivateFiles::store($request->file('permit'), $name);
        } catch (\Throwable $e) {
            report($e);
            return back()->with('msg', "err:We couldn't upload the permit right now. Please try again.");
        }

        $old = $user->business_permit;
        // A new permit goes back to the admin for review (also after a rejection).
        $user->forceFill([
            'business_permit' => $name,
            'business_permit_uploaded_at' => now(),
            'verified' => false,
            'verification_rejected_at' => null,
        ])->save();
        if ($old) {
            PrivateFiles::delete($old);
        }

        return back()->with('msg', 'Business permit uploaded. An admin will review it.');
    }

    /** GET /account/permit/{id}: shows a business permit. Only its owner and admins may open it. */
    public function permit(Request $request, int $id): Response
    {
        $viewer = $request->user();
        abort_unless((int) $viewer->user_id === $id || $viewer->role === 'admin', 403);

        $owner = User::find($id);
        $file = $owner?->business_permit ? PrivateFiles::get($owner->business_permit) : null;
        abort_unless($file, 404);

        return response($file['body'], 200, [
            'Content-Type' => $file['type'],
            'Content-Disposition' => 'inline; filename="business-permit-' . $id . '"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Unique, safe file name that keeps the extension: 6650f1c2a3b4d.jpg */
    private function fileName(UploadedFile $file): string
    {
        $ext = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin');

        return uniqid() . '.' . preg_replace('/[^a-z0-9]/', '', $ext);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\PasswordRules;
use App\Support\PrivateFiles;
use App\Support\RoomPhotos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Account page: every logged-in user can see and edit their details (after verifying their email),
 * check or resend email verification, and change their password, all on one page.
 * The email address can't be changed: it identifies the account.
 */
class AccountController extends Controller
{
    /** GET /account */
    public function show(Request $request): View
    {
        return view('account.show', ['user' => $request->user()]);
    }

    /**
     * POST /account/profile: personal details. The email can't be changed here: it identifies the account.
     * Everyone: name, birthday, contact number. Tenants also: emergency contact and social media link.
     */
    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();
        $isTenant = $user->role === 'tenant';

        // "0917 123 4567" and "0917-123-4567" are saved as "09171234567".
        foreach (['contact_number', 'emergency_contact_number'] as $field) {
            if ($request->filled($field)) {
                $request->merge([$field => preg_replace('/[\s\-()]/', '', (string) $request->input($field))]);
            }
        }

        $phone = ['regex:/^(09\d{9}|\+639\d{9})$/'];
        $rules = [
            'name' => ['required', 'string', 'min:2', 'max:100', 'regex:/^[\pL][\pL\s.\'-]*$/u'],
            'birthday' => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'contact_number' => array_merge(['required'], $phone),
        ];
        if ($isTenant) {
            $rules += [
                'emergency_contact_name' => ['required', 'string', 'min:2', 'max:100', 'regex:/^[\pL][\pL\s.\'-]*$/u'],
                'emergency_contact_relationship' => ['required', 'in:' . implode(',', User::RELATIONSHIPS)],
                'emergency_contact_number' => array_merge(['required', 'different:contact_number'], $phone),
                'social_media_url' => ['nullable', 'url:http,https', 'max:255'],
            ];
        }

        $data = $request->validateWithBag('profile', $rules, [
            'name.required' => 'Please enter your full name.',
            'name.min' => 'The name must be at least 2 characters.',
            'name.max' => 'The name must be 100 characters or fewer.',
            'name.regex' => 'The name may only contain letters, spaces, periods, apostrophes and hyphens.',
            'birthday.required' => 'Please enter your birthday.',
            'birthday.before' => 'The birthday must be a date in the past.',
            'birthday.after' => 'Please enter a real birthday.',
            'contact_number.required' => 'Please enter your contact number.',
            'contact_number.regex' => 'Enter a mobile number like 09171234567 or +639171234567.',
            'emergency_contact_name.required' => 'Please enter the name of your emergency contact.',
            'emergency_contact_name.min' => 'The emergency contact name must be at least 2 characters.',
            'emergency_contact_name.regex' => 'The emergency contact name may only contain letters, spaces, periods, apostrophes and hyphens.',
            'emergency_contact_relationship.required' => 'Please choose how this person is related to you.',
            'emergency_contact_relationship.in' => 'Please choose how this person is related to you.',
            'emergency_contact_number.required' => 'Please enter the number of your emergency contact.',
            'emergency_contact_number.regex' => 'Enter a mobile number like 09171234567 or +639171234567.',
            'emergency_contact_number.different' => 'The emergency contact number must be different from your own number.',
            'social_media_url.url' => 'Paste the full link to your profile, starting with https://',
        ]);

        $data['name'] = preg_replace('/\s+/', ' ', trim($data['name']));
        $user->forceFill($data + [
            // The old age column is kept up to date for older pages and reports.
            'age' => \Illuminate\Support\Carbon::parse($data['birthday'])->age,
        ])->save();

        return back()->with('msg', 'Your details were saved.');
    }

    /** POST /account/password */
    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validateWithBag('password', [
            'current_password' => ['required', 'string'],
            'password' => PasswordRules::rules(),
        ], [
            'current_password.required' => 'Please enter your current password.',
        ] + PasswordRules::messages());

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

    /** POST /account/valid-id: tenants upload a government or school ID. Private, like the business permit. */
    public function uploadValidId(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->role === 'tenant', 403);

        $request->validateWithBag('validid', [
            'valid_id' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ], [
            'valid_id.required' => 'Please choose a photo or PDF of your valid ID.',
            'valid_id.mimes' => 'The ID must be a picture (JPG, PNG, WEBP) or a PDF.',
            'valid_id.max' => 'The file must be 5 MB or smaller.',
        ]);

        $name = 'validid_' . $user->user_id . '_' . $this->fileName($request->file('valid_id'));

        try {
            PrivateFiles::store($request->file('valid_id'), $name);
        } catch (\Throwable $e) {
            report($e);
            return back()->with('msg', "err:We couldn't upload the ID right now. Please try again.");
        }

        $old = $user->valid_id;
        $user->forceFill(['valid_id' => $name, 'valid_id_uploaded_at' => now()])->save();
        if ($old) {
            PrivateFiles::delete($old);
        }

        return back()->with('msg', 'Your valid ID was uploaded.');
    }

    /** GET /account/valid-id/{id}: the tenant, admins, and the tenant's landlord (current or applied to) may open it. */
    public function validId(Request $request, int $id): Response
    {
        $owner = User::find($id);
        abort_unless($owner && $owner->privateFilesVisibleTo($request->user()), 403);

        $file = $owner->valid_id ? PrivateFiles::get($owner->valid_id) : null;
        abort_unless($file, 404);

        return $this->inline($file, 'valid-id-' . $id);
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

        return $this->inline($file, 'business-permit-' . $id);
    }

    /** Shows a private file in the browser, never cached. */
    private function inline(array $file, string $name): Response
    {
        return response($file['body'], 200, [
            'Content-Type' => $file['type'],
            'Content-Disposition' => 'inline; filename="' . $name . '"',
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

@extends('layouts.public', ['css' => 'resources/css/account.css'])

@section('title', 'Account · RSYNC')

@section('fonts')
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<link href="https://unpkg.com/boxicons@latest/css/boxicons.min.css" rel="stylesheet">
@endsection

@section('content')
@php
    $home = $user->homeRoute();
    $msg = session('msg');
    $isErr = $msg && str_starts_with($msg, 'err:');
    $msg = $isErr ? substr($msg, 4) : $msg;
    $pe = $errors->getBag('profile');
    $pw = $errors->getBag('password');
    $ph = $errors->getBag('photo');
    $pm = $errors->getBag('permit');
    $vi = $errors->getBag('validid');
    $verified = $user->hasVerifiedEmail();
    $isTenant = $user->role === 'tenant';
    $rawName = $user->getRawOriginal('name');
@endphp

<nav>
    <span>RS<em>Y</em>NC</span>
    <div class="nav-actions">
        <a href="{{ route($home) }}">{{ $home === 'home' ? 'Home' : 'Dashboard' }}</a>
        <a href="{{ route('logout') }}">Logout</a>
    </div>
</nav>

<div class="wrap">
    <div class="top-bar">
        <h1>My Account</h1>
        <a href="{{ route($home) }}" class="back"><i class="bx bx-arrow-back"></i> Back to {{ $home === 'home' ? 'Home' : 'Dashboard' }}</a>
    </div>

    @if ($msg)<div class="toast {{ $isErr ? 'err' : '' }}">{{ $msg }}</div>@endif
    @if (session('status'))<div class="toast">{{ session('status') }}</div>@endif

    @if ($verified && ! $user->hasCompleteProfile())
        <div class="toast warn">Please complete your details below{{ $isTenant ? ' before applying for a room' : '' }}.</div>
    @endif

    {{-- ── Email verification (first, because everything else waits for it) ── --}}
    <section class="card">
        <div class="card-head">
            <h2><i class="bx bx-envelope"></i> Email</h2>
            @if ($verified)
                <span class="pill ok"><i class="bx bx-check-circle"></i> Verified</span>
            @else
                <span class="pill warn"><i class="bx bx-error-circle"></i> Not verified</span>
            @endif
        </div>
        <p class="text"><strong>{{ $user->email }}</strong></p>
        @if ($verified)
            <p class="text muted">Verified on {{ $user->email_verified_at->format('M j, Y') }}. Your email is your account ID, so it can't be changed.</p>
        @else
            <p class="text">We sent a link to this address. Open it to verify your email. After that you can fill in your details, apply for rooms and submit requests.</p>
            <p class="text muted">No email? Check your Spam folder, or send a new link. Your email is your account ID, so it can't be changed.</p>
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button type="submit" class="btn btn-amber"><i class="bx bx-send"></i> Resend verification link</button>
            </form>
        @endif
    </section>

    @if ($verified)
    {{-- ── Profile picture ── --}}
    <section class="card">
        <div class="card-head">
            <h2><i class="bx bx-camera"></i> Profile picture</h2>
        </div>
        <div class="photo-row">
            @include('partials.avatar', ['u' => $user, 'size' => 84])
            <div class="photo-body">
                <p class="text">
                    @if ($isTenant)
                        Add a clear photo of your face so your landlord can recognize you.
                    @else
                        Add a clear photo of your face so your tenants and the RSYNC team can recognize you.
                    @endif
                </p>
                <form method="POST" action="{{ route('account.photo') }}" enctype="multipart/form-data" class="upload-row">
                    @csrf
                    <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required>
                    <button type="submit" class="btn btn-dark">{{ $user->profile_photo ? 'Change picture' : 'Upload picture' }}</button>
                </form>
                @if ($ph->has('photo'))<p class="f-err">{{ $ph->first('photo') }}</p>@endif
                <p class="hint">JPG, PNG or WEBP, up to 5 MB.</p>
                @if ($user->profile_photo)
                    <form method="POST" action="{{ route('account.photo.remove') }}" class="inline-form">
                        @csrf
                        <button type="submit" class="link-btn">Remove picture</button>
                    </form>
                @endif
            </div>
        </div>
    </section>

    {{-- ── Personal details (+ emergency contact and social media for tenants) ── --}}
    <section class="card">
        <div class="card-head">
            <h2><i class="bx bx-user"></i> Personal details</h2>
            <span class="role">{{ ucfirst($user->role) }}</span>
        </div>
        <form method="POST" action="{{ route('account.profile') }}">
            @csrf
            <div class="field">
                <label for="name">Full name</label>
                <input type="text" id="name" name="name" minlength="2" maxlength="100" required placeholder="e.g. Juan Dela Cruz" value="{{ old('name', $rawName) }}">
                @if ($pe->has('name'))<p class="f-err">{{ $pe->first('name') }}</p>@endif
            </div>
            <div class="row2">
                <div class="field">
                    <label for="birthday">Birthday</label>
                    <input type="date" id="birthday" name="birthday" required max="{{ now()->subDay()->toDateString() }}" value="{{ old('birthday', $user->birthday?->toDateString()) }}">
                    @if ($pe->has('birthday'))<p class="f-err">{{ $pe->first('birthday') }}</p>@endif
                    @if ($user->birthday)<p class="hint">Age: {{ $user->ageYears() }}</p>@endif
                </div>
                <div class="field">
                    <label for="contact_number">Contact number</label>
                    <input type="tel" id="contact_number" name="contact_number" required placeholder="09171234567" value="{{ old('contact_number', $user->contact_number) }}">
                    @if ($pe->has('contact_number'))<p class="f-err">{{ $pe->first('contact_number') }}</p>@endif
                </div>
            </div>

            @if ($isTenant)
            <div class="sub-head">Emergency contact <span class="opt">(parent or guardian)</span></div>
            <div class="row2">
                <div class="field">
                    <label for="emergency_contact_name">Name</label>
                    <input type="text" id="emergency_contact_name" name="emergency_contact_name" minlength="2" maxlength="100" required value="{{ old('emergency_contact_name', $user->emergency_contact_name) }}">
                    @if ($pe->has('emergency_contact_name'))<p class="f-err">{{ $pe->first('emergency_contact_name') }}</p>@endif
                </div>
                <div class="field">
                    <label for="emergency_contact_relationship">Relationship</label>
                    @php $rel = old('emergency_contact_relationship', $user->emergency_contact_relationship); @endphp
                    <select id="emergency_contact_relationship" name="emergency_contact_relationship" required>
                        <option value="" disabled {{ $rel ? '' : 'selected' }}>Choose…</option>
                        @foreach (\App\Models\User::RELATIONSHIPS as $r)
                            <option value="{{ $r }}" {{ $rel === $r ? 'selected' : '' }}>{{ $r }}</option>
                        @endforeach
                    </select>
                    @if ($pe->has('emergency_contact_relationship'))<p class="f-err">{{ $pe->first('emergency_contact_relationship') }}</p>@endif
                </div>
            </div>
            <div class="field">
                <label for="emergency_contact_number">Emergency contact number</label>
                <input type="tel" id="emergency_contact_number" name="emergency_contact_number" required placeholder="09181234567" value="{{ old('emergency_contact_number', $user->emergency_contact_number) }}">
                @if ($pe->has('emergency_contact_number'))<p class="f-err">{{ $pe->first('emergency_contact_number') }}</p>@endif
            </div>

            <div class="field">
                <label for="social_media_url">Social media link <span class="opt">(optional, e.g. your Facebook profile)</span></label>
                <input type="url" id="social_media_url" name="social_media_url" maxlength="255" placeholder="https://facebook.com/your.name" value="{{ old('social_media_url', $user->social_media_url) }}">
                @if ($pe->has('social_media_url'))<p class="f-err">{{ $pe->first('social_media_url') }}</p>@endif
            </div>
            @endif

            <div class="card-foot">
                @if ($user->created_at)<span class="since">Member since {{ $user->created_at->format('M j, Y') }}</span>@endif
                <button type="submit" class="btn btn-dark">Save details</button>
            </div>
        </form>
    </section>

    @if ($isTenant)
    {{-- ── Valid ID (tenants) ── --}}
    <section class="card">
        <div class="card-head">
            <h2><i class="bx bx-id-card"></i> Valid ID</h2>
            @if ($user->valid_id)
                <span class="pill ok"><i class="bx bx-check-circle"></i> Uploaded</span>
            @else
                <span class="pill warn"><i class="bx bx-error-circle"></i> Not uploaded</span>
            @endif
        </div>
        <p class="text">Upload a clear photo or PDF of a government or school ID. Your landlord uses it to confirm who you are.</p>
        @if ($user->valid_id)
            <p class="text"><a href="{{ route('account.valid-id.show', $user->user_id) }}" target="_blank" rel="noopener" class="file-link"><i class="bx bx-show"></i> View uploaded ID</a>
                <span class="muted-inline">· uploaded {{ $user->valid_id_uploaded_at?->format('M j, Y') }}</span></p>
        @endif
        <form method="POST" action="{{ route('account.valid-id') }}" enctype="multipart/form-data" class="upload-row">
            @csrf
            <input type="file" name="valid_id" accept="image/jpeg,image/png,image/webp,application/pdf" required>
            <button type="submit" class="btn btn-dark">{{ $user->valid_id ? 'Upload new copy' : 'Upload ID' }}</button>
        </form>
        @if ($vi->has('valid_id'))<p class="f-err">{{ $vi->first('valid_id') }}</p>@endif
        <p class="hint">JPG, PNG, WEBP or PDF, up to 5 MB. Only you, your landlord and the RSYNC admins can see this file.</p>
    </section>
    @endif

    @if ($user->isOwner())
    {{-- ── Business permit (landlords and sublessors) ── --}}
    @php $permit = $user->permitStatus(); @endphp
    <section class="card">
        <div class="card-head">
            <h2><i class="bx bx-file"></i> Business permit</h2>
            @switch ($permit)
                @case('approved') <span class="pill ok"><i class="bx bx-check-circle"></i> Approved</span> @break
                @case('pending')  <span class="pill warn"><i class="bx bx-time-five"></i> Waiting for review</span> @break
                @case('rejected') <span class="pill bad"><i class="bx bx-x-circle"></i> Not accepted</span> @break
                @default          <span class="pill warn"><i class="bx bx-error-circle"></i> Not uploaded</span>
            @endswitch
        </div>
        @if ($permit === 'none')
            <p class="text">Upload a clear photo or PDF of your business permit. The RSYNC team will check it to confirm that you can rent out rooms.</p>
        @elseif ($permit === 'pending')
            <p class="text">Uploaded on {{ $user->business_permit_uploaded_at?->format('M j, Y') }}. The RSYNC team will review it soon.</p>
        @elseif ($permit === 'approved')
            <p class="text">Your business permit was checked and approved.</p>
        @else
            <p class="text">Your permit could not be accepted. Please upload a clearer or updated copy.</p>
        @endif
        @if ($user->business_permit)
            <p class="text"><a href="{{ route('account.permit.show', $user->user_id) }}" target="_blank" rel="noopener" class="file-link"><i class="bx bx-show"></i> View uploaded permit</a></p>
        @endif
        <form method="POST" action="{{ route('account.permit') }}" enctype="multipart/form-data" class="upload-row">
            @csrf
            <input type="file" name="permit" accept="image/jpeg,image/png,image/webp,application/pdf" required>
            <button type="submit" class="btn btn-dark">{{ $user->business_permit ? 'Upload new copy' : 'Upload permit' }}</button>
        </form>
        @if ($pm->has('permit'))<p class="f-err">{{ $pm->first('permit') }}</p>@endif
        <p class="hint">JPG, PNG, WEBP or PDF, up to 5 MB. Only you and the RSYNC admins can see this file.@if ($user->business_permit) Uploading a new copy sends it for review again.@endif</p>
    </section>
    @endif
    @else
    <section class="card locked">
        <i class="bx bx-lock"></i>
        <p>Verify your email to fill in your name, birthday{{ $isTenant ? ', contact details and valid ID' : ', contact number and business permit' }}.</p>
    </section>
    @endif

    {{-- ── Password ── --}}
    <section class="card">
        <div class="card-head">
            <h2><i class="bx bx-lock-alt"></i> Change password</h2>
        </div>
        <form method="POST" action="{{ route('account.password') }}">
            @csrf
            <div class="field">
                <label for="current_password">Current password</label>
                <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
                @if ($pw->has('current_password'))<p class="f-err">{{ $pw->first('current_password') }}</p>@endif
            </div>
            <div class="row2">
                <div class="field">
                    <label for="password">New password</label>
                    <input type="password" id="password" name="password" minlength="8" required autocomplete="new-password">
                    @if ($pw->has('password'))<p class="f-err">{{ $pw->first('password') }}</p>@endif
                </div>
                <div class="field">
                    <label for="password_confirmation">Confirm new password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" minlength="8" required autocomplete="new-password">
                </div>
            </div>
            <p class="hint">{{ \App\Support\PasswordRules::HINT }} Forgot your current password? Log out and use “Forgot password” on the login page.</p>
            <div class="card-foot end">
                <button type="submit" class="btn btn-dark">Change password</button>
            </div>
        </form>
    </section>
</div>
@endsection

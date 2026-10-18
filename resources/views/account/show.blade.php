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

    {{-- ── Profile ── --}}
    <section class="card">
        <div class="card-head">
            <h2><i class="bx bx-user"></i> Profile</h2>
            <span class="role">{{ ucfirst($user->role) }}</span>
        </div>
        <form method="POST" action="{{ route('account.profile') }}">
            @csrf
            <div class="row2">
                <div class="field">
                    <label for="name">Full name</label>
                    <input type="text" id="name" name="name" maxlength="100" required value="{{ old('name', $user->name) }}">
                    @if ($pe->has('name'))<p class="f-err">{{ $pe->first('name') }}</p>@endif
                </div>
                <div class="field">
                    <label for="age">Age</label>
                    <input type="number" id="age" name="age" min="1" max="120" required value="{{ old('age', $user->age) }}">
                    @if ($pe->has('age'))<p class="f-err">{{ $pe->first('age') }}</p>@endif
                </div>
            </div>
            <div class="field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" maxlength="100" required value="{{ old('email', $user->email) }}">
                @if ($pe->has('email'))<p class="f-err">{{ $pe->first('email') }}</p>@endif
                <p class="hint">If you change your email, you will need to verify the new one.</p>
            </div>
            <div class="field">
                <label for="profile_current_password">Current password <span class="opt">(only needed when changing your email)</span></label>
                <input type="password" id="profile_current_password" name="current_password" autocomplete="current-password">
                @if ($pe->has('current_password'))<p class="f-err">{{ $pe->first('current_password') }}</p>@endif
            </div>
            <div class="card-foot">
                @if ($user->created_at)<span class="since">Member since {{ $user->created_at->format('M j, Y') }}</span>@endif
                <button type="submit" class="btn btn-dark">Save changes</button>
            </div>
        </form>
    </section>

    {{-- ── Email verification ── --}}
    <section class="card">
        <div class="card-head">
            <h2><i class="bx bx-envelope"></i> Email verification</h2>
            @if ($user->hasVerifiedEmail())
                <span class="pill ok"><i class="bx bx-check-circle"></i> Verified</span>
            @else
                <span class="pill warn"><i class="bx bx-error-circle"></i> Not verified</span>
            @endif
        </div>
        @if ($user->hasVerifiedEmail())
            <p class="text"><strong>{{ $user->email }}</strong> was verified on {{ $user->email_verified_at->format('M j, Y') }}. You can use all features.</p>
        @else
            <p class="text">We sent a link to <strong>{{ $user->email }}</strong>. Open it to verify your email. Until then you can look around, but you can't apply for rooms or submit requests.</p>
            <p class="text muted">No email? Check your Spam folder, or send a new link. Wrong email? Fix it in Profile above.</p>
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button type="submit" class="btn btn-amber"><i class="bx bx-send"></i> Resend verification link</button>
            </form>
        @endif
    </section>

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
            <p class="hint">At least 8 characters. Forgot your current password? Log out and use “Forgot password” on the login page.</p>
            <div class="card-foot end">
                <button type="submit" class="btn btn-dark">Change password</button>
            </div>
        </form>
    </section>
</div>
@endsection
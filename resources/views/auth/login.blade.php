@extends('layouts.auth')

@section('title', 'Login')
@section('page', 'login')

@if ($loginFailed)
    {{-- Same as legacy oopPHP::login(): the alert is printed before the page. --}}
    @section('before_html')
<script>alert('Invalid login credentials.');</script>
    @endsection
@endif

{{-- Left side: just a picture. To change it, put your own image in public/brand/
     named login.jpg (or .jpeg / .png / .webp). Until then the old photo is used. --}}
@php
    $loginPhoto = null;
    foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
        if (is_file(public_path("brand/login.$ext"))) {
            $loginPhoto = asset("brand/login.$ext") . '?v=' . filemtime(public_path("brand/login.$ext"));
            break;
        }
    }
@endphp
@if ($loginPhoto)
    @section('photo', $loginPhoto)
@endif
@section('photo_only', '1')

{{-- On the picture: a big headline and three live numbers (same rules as the homepage). --}}
@section('photo_extra')
    <div class="photo-poster">
        <h2 class="poster-title">Back up the hill, back <span>home.</span></h2>
        @if (($stats['rooms'] ?? 0) > 0)
        <dl class="photo-stats" aria-label="Rooms on RSYNC right now">
            <div><dt>{{ $stats['rooms'] }}</dt><dd>{{ $stats['rooms'] === 1 ? 'room' : 'rooms' }} with a free slot right now</dd></div>
            <div><dt>₱{{ number_format($stats['lowest'], 0) }}</dt><dd>lowest rent per person a month</dd></div>
            @if ($stats['areas'] > 0)
            <div><dt>{{ $stats['areas'] }}</dt><dd>{{ $stats['areas'] === 1 ? 'barangay' : 'barangays' }} around Baguio</dd></div>
            @endif
        </dl>
        @endif
    </div>
@endsection

@section('content')
<form method="POST" action="{{ route('login.attempt') }}" class="box">
    @csrf
    <a href="{{ route('home') }}" class="box-logo" aria-label="RSYNC home">RS<span>Y</span>NC</a>
    <h2>Sign in</h2>
    <p class="sub">Welcome back. Your room, rent and messages are waiting.</p>

    @if ($resetMsg)<div class="ok"><i class="bx bx-check-circle"></i>{{ $resetMsg }}</div>@endif

    <div class="field">
        <label for="email">Email</label>
        <div class="inp-wrap">
            <input type="email" id="email" name="email" autocomplete="email" placeholder="you@example.com" required>
            <i class="bx bx-envelope"></i>
        </div>
    </div>

    <div class="field">
        <label for="pw">Password</label>
        <div class="inp-wrap">
            <input type="password" id="pw" name="password" placeholder="Your password" autocomplete="current-password" required>
            <i class="bx bx-show" onclick="togglePw()"></i>
        </div>
    </div>

    <div class="forgot"><a href="{{ route('password.request') }}">Forgot password?</a></div>

    <button class="btn-main" name="login">Login</button>
    <a href="{{ route('home') }}" class="btn-back">← Back to Home</a>

    <div class="foot">Don't have an account? <a href="{{ route('register') }}">Create one</a></div>
</form>
@endsection
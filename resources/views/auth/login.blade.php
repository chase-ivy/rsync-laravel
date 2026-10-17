@extends('layouts.auth')

@section('title', 'Login')
@section('page', 'login')

@if ($loginFailed)
    {{-- Same as legacy oopPHP::login(): the alert is printed before the page. --}}
    @section('before_html')
<script>alert('Invalid login credentials.');</script>
    @endsection
@endif

@section('hero')
    <h2>Welcome back</h2>
    <p>Access your dashboard, manage listings, and track your tenants in one place.</p>
@endsection

@section('content')
<form method="POST" action="{{ route('login.attempt') }}" class="box">
    @csrf
    <div class="box-logo">RS<span>Y</span>NC</div>
    <h2>Sign in</h2>
    <p class="sub">Enter your credentials to continue</p>

    @if ($resetMsg)<div class="ok"><i class="bx bx-check-circle"></i>{{ $resetMsg }}</div>@endif

    <div class="field">
        <label>Email</label>
        <div class="inp-wrap">
            <input type="email" name="email" placeholder="you@example.com" required>
            <i class="bx bx-envelope"></i>
        </div>
    </div>

    <div class="field">
        <label>Password</label>
        <div class="inp-wrap">
            <input type="password" id="pw" name="password" placeholder="••••••••" required>
            <i class="bx bx-show" onclick="togglePw()"></i>
        </div>
    </div>

    <div class="forgot"><a href="{{ route('password.request') }}">Forgot password?</a></div>

    <button class="btn-main" name="login">Login</button>
    <a href="{{ route('home') }}" class="btn-back">← Back to Home</a>

    <div class="foot">Don't have an account? <a href="{{ route('register') }}">Create one</a></div>
</form>
@endsection
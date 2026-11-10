@extends('layouts.auth')

@section('title', 'Forgot Password')
@section('page', 'forgot')

@section('content')
<div class="box">
    <div class="box-logo">RS<span>Y</span>NC</div>
    <div class="icon-wrap"><i class="bx bx-lock-open-alt"></i></div>
    <h2>Forgot your password?</h2>
    <p class="sub">Enter your account email and we'll send you a 6-digit OTP to reset your password.</p>

    @if ($error)<div class="err">{{ $error }}</div>@endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="field">
            <label>Email Address</label>
            <div class="inp-wrap">
                <input type="email" name="email" placeholder="you@example.com" required autofocus value="{{ request('email', '') }}">
                <i class="bx bx-envelope"></i>
            </div>
        </div>
        <button class="btn-main" name="send_otp">Send OTP</button>
        <a href="{{ route('login') }}" class="btn-back">← Back to Login</a>
    </form>
</div>
@endsection
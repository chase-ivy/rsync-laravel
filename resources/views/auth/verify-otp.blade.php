@extends('layouts.auth')

@section('title', 'Enter OTP')
@section('page', 'forgot')

@section('content')
<div class="box">
    <div class="box-logo">RS<span>Y</span>NC</div>
    <div class="icon-wrap"><i class="bx bx-envelope"></i></div>
    <h2>Enter your OTP</h2>
    <p class="sub">We sent a 6-digit code to <strong>{{ $email }}</strong>. It expires in 10 minutes.</p>

    @if ($devOtp)
        {{-- Only on your own PC while emails go to the log file (the old app's "DEV FALLBACK"). --}}
        <div class="ok"><i class="bx bx-code-alt"></i>DEV: your code is <strong>{{ $devOtp }}</strong></div>
    @endif
    @if ($error)<div class="err">{{ $error }}</div>@endif

    <form method="POST" action="{{ route('password.otp.check') }}">
        @csrf
        <div class="field">
            <label>6-digit code</label>
            <div class="inp-wrap">
                <input type="text" name="otp" class="otp-input" inputmode="numeric" pattern="\d{6}" maxlength="6" placeholder="••••••" autocomplete="one-time-code" required autofocus>
            </div>
        </div>
        <button class="btn-main">Verify</button>
        <a href="{{ route('password.request') }}" class="btn-back">Send a new code</a>
    </form>
</div>
@endsection
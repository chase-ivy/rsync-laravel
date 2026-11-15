@extends('layouts.auth')

@section('title', 'New Password')
@section('page', 'forgot')

@section('content')
<div class="box">
    <div class="box-logo">RS<span>Y</span>NC</div>
    <div class="icon-wrap"><i class="bx bx-key"></i></div>
    <h2>Choose a new password</h2>
    <p class="sub">Your code was correct. Enter your new password below.</p>

    @if ($error)<div class="err">{{ $error }}</div>@endif

    <form method="POST" action="{{ route('password.update') }}" data-validate novalidate>
        @csrf
        <div class="field">
            <label for="pw">New Password</label>
            <div class="inp-wrap">
                <input type="password" id="pw" name="password" placeholder="••••••••" minlength="8" required autofocus autocomplete="new-password">
                <i class="bx bx-show" onclick="togglePw()" style="cursor:pointer"></i>
            </div>
            <ul class="pw-rules" data-pw-rules aria-live="polite">
                <li data-rule="length"><i class="bx bx-circle"></i> At least 8 characters</li>
                <li data-rule="upper"><i class="bx bx-circle"></i> An uppercase letter (A–Z)</li>
                <li data-rule="lower"><i class="bx bx-circle"></i> A lowercase letter (a–z)</li>
                <li data-rule="number"><i class="bx bx-circle"></i> A number (0–9)</li>
            </ul>
            <p class="f-msg" data-msg-for="password"></p>
        </div>
        <div class="field">
            <label for="password_confirmation">Confirm New Password</label>
            <div class="inp-wrap">
                <input type="password" id="password_confirmation" name="password_confirmation" placeholder="••••••••" required autocomplete="new-password">
                <i class="bx bx-lock-alt"></i>
            </div>
            <p class="f-msg" data-msg-for="password_confirmation"></p>
        </div>
        <button class="btn-main">Save new password</button>
        <a href="{{ route('login') }}" class="btn-back">← Back to Login</a>
    </form>
</div>
@endsection

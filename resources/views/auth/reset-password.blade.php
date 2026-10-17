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

    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <div class="field">
            <label>New Password</label>
            <div class="inp-wrap">
                <input type="password" id="pw" name="password" placeholder="••••••••" required autofocus>
                <i class="bx bx-show" onclick="togglePw()" style="cursor:pointer"></i>
            </div>
        </div>
        <div class="field">
            <label>Confirm New Password</label>
            <div class="inp-wrap">
                <input type="password" name="password_confirmation" placeholder="••••••••" required>
                <i class="bx bx-lock-alt"></i>
            </div>
        </div>
        <button class="btn-main">Save new password</button>
        <a href="{{ route('login') }}" class="btn-back">← Back to Login</a>
    </form>
</div>
@endsection
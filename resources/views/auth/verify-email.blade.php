@extends('layouts.auth')

@section('title', 'Verify your email')
@section('page', 'forgot')

@section('content')
<div class="box">
    <div class="box-logo">RS<span>Y</span>NC</div>
    <div class="icon-wrap"><i class="bx bx-envelope"></i></div>
    <h2>Verify your email</h2>
    <p class="sub">
        Before you can do this, please confirm your email address.
        We sent a link to <strong>{{ $email }}</strong>. Click it to finish.
    </p>

    @if (session('status'))
        <div class="ok"><i class="bx bx-check-circle"></i>{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button class="btn-main">Resend verification link</button>
    </form>
    <a href="{{ route(auth()->user()->homeRoute()) }}" class="btn-back">← Back</a>
</div>
@endsection
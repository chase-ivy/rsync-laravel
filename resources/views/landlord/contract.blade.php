@extends('layouts.public', ['css' => 'resources/css/landlord-dashboard.css'])

@section('title', 'Contract format · RSYNC')

@section('fonts')
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
@endsection

@section('content')
<nav>
    <span>RS<em>Y</em>NC</span>
    <div class="nav-actions">
        <a href="{{ route('landlord.dashboard') }}">Dashboard</a>
        <a href="{{ route('account') }}">Account</a>
        <a href="{{ route('landlord.tenants') }}">Tenants</a>
        <a href="{{ route('landlord.maintenance') }}">Maintenance</a>
        <a href="{{ route('logout') }}">Logout</a>
    </div>
</nav>
@include('partials.verify-banner')

<div class="wrap">
    <div class="top-bar">
        <h1>My Contract Format</h1>
        <p class="c-intro">Write your own terms and house rules. Every new tenant you assign gets a contract with these terms. Contracts you already made keep their terms; change one with "Edit contract" on the Tenants page.</p>
    </div>

    @if ($msg)<div class="toast {{ $isErr ? 'err' : '' }}">{{ $msg }}</div>@endif
    @if ($errors->any())<div class="toast err">Please check the fields marked below.</div>@endif
    @if ($standard)
        <div class="toast">The boxes now show the standard format. Click "Save contract format" to keep it.</div>
    @elseif (! $hasTemplate)
        <div class="toast">You are using the standard format. Change anything you like and save it as your own.</div>
    @endif

    <form method="POST" action="{{ route('landlord.contract.save') }}" class="c-card">
        @csrf
        @include('partials.contract-fields', ['terms' => $terms])

        <div class="c-actions">
            <button type="submit" class="btn btn-dark">Save contract format</button>
            <a href="{{ route('landlord.contract', ['standard' => 1]) }}" class="btn btn-ghost" onclick="return confirm('Replace the boxes with the standard format? Nothing is saved until you click Save.')">Reset to the standard format</a>
            <a href="{{ route('landlord.contract.preview') }}" class="btn btn-amber" target="_blank" rel="noopener">Preview contract</a>
            @if ($savedAt)<span class="t-sub">Last saved {{ $savedAt->format('M j, Y g:i A') }}</span>@endif
        </div>
    </form>
</div>
@endsection

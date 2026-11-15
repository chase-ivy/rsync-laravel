@extends('layouts.public', ['css' => 'resources/css/landlord-dashboard.css'])

@section('title', 'Edit contract ' . $code . ' · RSYNC')

@section('fonts')
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
@endsection

@section('content')
@php
    $rentValue = rtrim(rtrim(number_format((float) $terms['monthly_rent'], 2, '.', ''), '0'), '.');
@endphp
<nav>
    <span>RS<em>Y</em>NC</span>
    <div class="nav-actions">
        <a href="{{ route('landlord.dashboard') }}">Dashboard</a>
        <a href="{{ route('account') }}">Account</a>
        <a href="{{ route('landlord.tenants') }}">Tenants</a>
        <a href="{{ route('landlord.contract') }}">Contract</a>
        <a href="{{ route('landlord.maintenance') }}">Maintenance</a>
        <a href="{{ route('logout') }}">Logout</a>
    </div>
</nav>
@include('partials.verify-banner')

<div class="wrap">
    <div class="top-bar">
        <h1>Edit Contract {{ $code }}</h1>
        <p class="c-intro">Changes apply to this contract only. The tenant sees the new terms and the date of the change on their contract.</p>
    </div>

    @if ($errors->any())<div class="toast err">Please check the fields marked below.</div>@endif
    @if ($standard)<div class="toast">The terms now show the standard format. Click "Save changes" to keep it.</div>@endif

    <form method="POST" action="{{ route('landlord.contracts.update', $contract->tenant_id) }}" class="c-card">
        @csrf
        <div class="c-summary">
            <span>Tenant: <strong>{{ $contract->user?->name ?? 'Unknown' }}</strong></span>
            <span>Room: <strong>{{ $contract->room?->room_name ?? '—' }}</strong></span>
            <span>Start date: <strong>{{ $contract->start_date?->format('M j, Y') }}</strong></span>
            @if ($contract->terms_updated_at)<span>Last updated on {{ $contract->terms_updated_at->format('M j, Y') }}</span>@endif
        </div>

        <h2>Rent and end date</h2>
        <div class="row3">
            <div class="field">
                <label for="monthly_rent">Monthly rent (₱)</label>
                <input id="monthly_rent" type="number" name="monthly_rent" min="0" step="0.01" required value="{{ old('monthly_rent', $rentValue) }}" class="{{ $errors->has('monthly_rent') ? 'is-bad' : '' }}">
                @error('monthly_rent')<p class="f-err">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="end_date">End date</label>
                <input id="end_date" type="date" name="end_date" required min="{{ $contract->start_date?->copy()->addDay()->format('Y-m-d') }}" value="{{ old('end_date', $contract->end_date?->format('Y-m-d')) }}" class="{{ $errors->has('end_date') ? 'is-bad' : '' }}">
                @error('end_date')<p class="f-err">{{ $message }}</p>@enderror
            </div>
        </div>

        @include('partials.contract-fields', ['terms' => $terms])

        <label class="c-check"><input type="checkbox" name="save_as_default" value="1" @checked(old('save_as_default'))> Also save these terms as my default contract format</label>

        <div class="c-actions">
            <button type="submit" class="btn btn-dark">Save changes</button>
            <a href="{{ route('landlord.contracts.edit', ['tenancy' => $contract->tenant_id, 'standard' => 1]) }}" class="btn btn-ghost" onclick="return confirm('Replace the terms with the standard format? Nothing is saved until you click Save.')">Reset to the standard format</a>
            <a href="{{ route('contracts.show', $contract->tenant_id) }}" class="btn btn-amber" target="_blank" rel="noopener">View contract</a>
            <a href="{{ route('landlord.tenants') }}" class="btn btn-ghost">Cancel</a>
        </div>
    </form>
</div>
@endsection

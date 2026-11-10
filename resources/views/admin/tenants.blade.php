@extends('layouts.public', ['css' => 'resources/css/admin.css', 'js' => 'resources/js/admin-tenants.js'])

@section('title', 'Tenants & Contracts · RSYNC')

@section('fonts')
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<link href="https://unpkg.com/boxicons@latest/css/boxicons.min.css" rel="stylesheet">
@endsection

@section('content')
@include('partials.admin-nav')

<div class="wrap">
    <div class="top-bar">
        <h1>Tenants &amp; Contracts</h1>
        <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
            <a href="{{ route('logout') }}" class="btn btn-dark">Sign out</a>
        </div>
    </div>

    @if ($msg)<div class="toast {{ $isErr ? 'err' : '' }}">{{ $msg }}</div>@endif

    <!-- A. TENANT DETAILS -->
    <div class="card" id="tenants">
        <div class="card-header">
            <h2>Tenant Details</h2>
            <span class="card-sub">{{ $tenants->count() }} tenant accounts</span>
        </div>
        <div class="tbl-wrap">
            <table>
                <thead><tr><th>ID</th><th>Full Name</th><th>Email</th><th>Assigned Room</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($tenants as $t)
                    @php $renting = $t->tenancies->isNotEmpty(); @endphp
                    <tr>
                        <td>{{ $t->user_id }}</td>
                        <td>{{ $t->name }}</td>
                        <td>{{ $t->email }}</td>
                        <td>{{ $renting ? $t->tenancies->map(fn ($c) => $c->room?->room_name)->filter()->join(', ') : '—' }}</td>
                        <td><span class="badge {{ $renting ? 'badge-active' : 'badge-inactive' }}">{{ $renting ? 'Active' : 'Inactive' }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No tenant accounts yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- B. CONTRACT DETAILS -->
    <div class="card" id="contracts">
        <div class="card-header">
            <h2>Contract Details</h2>
        </div>
        <div class="tbl-wrap">
            <table>
                <thead><tr><th>Contract ID</th><th>Tenant Name</th><th>Room</th><th>Start Date</th><th>End Date</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($contracts as $c)
                    @php
                        // Active contracts whose end date has passed show as "Expired".
                        $label = $c->status === 'Left' ? 'Left' : ($c->end_date->toDateString() < $today ? 'Expired' : 'Active');
                    @endphp
                    <tr>
                        <td>{{ \App\Http\Controllers\Admin\AdminTenantController::code($c->tenant_id) }}</td>
                        <td>{{ $c->user?->name ?? '—' }}</td>
                        <td>{{ $c->room?->room_name ?? '—' }}</td>
                        <td>{{ $c->start_date->format('Y-m-d') }}</td>
                        <td>{{ $c->end_date->format('Y-m-d') }}</td>
                        <td><span class="badge badge-{{ strtolower($label) }}">{{ $label }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">No contracts yet. Landlords create them with "Assign".</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- C. UPDATE CONTRACT DETAILS -->
    <div class="card" id="update-contract">
        <div class="card-header">
            <h2>Update Contract Details</h2>
            <span class="card-sub">Running contracts only. To move a tenant to another room, the landlord uses Remove + Assign.</span>
        </div>
        @if ($active->isEmpty())
            <p class="muted" style="padding:24px;">No running contracts to update.</p>
        @else
            <form method="POST" action="{{ route('admin.contracts.update') }}" class="form-grid">
                @csrf
                <div class="field">
                    <label for="c-contract">Contract</label>
                    <select id="c-contract" name="tenant_id" required>
                        <option value="">-- Select Contract --</option>
                        @foreach ($active as $c)
                            <option value="{{ $c->tenant_id }}" data-start="{{ $c->start_date->format('Y-m-d') }}" data-end="{{ $c->end_date->format('Y-m-d') }}" @selected(old('tenant_id') == $c->tenant_id)>
                                {{ \App\Http\Controllers\Admin\AdminTenantController::code($c->tenant_id) }} · {{ $c->user?->name }} — {{ $c->room?->room_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="c-start">Start Date</label>
                    <input id="c-start" type="date" name="start_date" required value="{{ old('start_date') }}">
                </div>
                <div class="field">
                    <label for="c-end">End Date</label>
                    <input id="c-end" type="date" name="end_date" required value="{{ old('end_date') }}">
                </div>
                <button type="submit" class="btn btn-dark">Update</button>
            </form>
        @endif
    </div>

    <!-- D. UPDATE TENANT STATUS -->
    <div class="card" id="tenant-status">
        <div class="card-header">
            <h2>Update Tenant Status</h2>
        </div>
        <div class="tbl-wrap">
            <table>
                <thead><tr><th>Contract ID</th><th>Tenant Name</th><th>Room</th><th>Status</th><th>Change To</th></tr></thead>
                <tbody>
                @forelse ($active as $c)
                    <tr>
                        <td>{{ \App\Http\Controllers\Admin\AdminTenantController::code($c->tenant_id) }}</td>
                        <td>{{ $c->user?->name ?? '—' }}</td>
                        <td>{{ $c->room?->room_name ?? '—' }}</td>
                        <td><span class="badge badge-{{ strtolower($c->payment_status) }}">{{ $c->payment_status }}</span></td>
                        <td>
                            <form method="POST" action="{{ route('admin.contracts.status') }}" class="inline-form">
                                @csrf
                                <input type="hidden" name="tenant_id" value="{{ $c->tenant_id }}">
                                <select name="payment_status" aria-label="New status for {{ $c->user?->name }}">
                                    @foreach ($statuses as $s)
                                        <option @selected($s === $c->payment_status)>{{ $s }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn approve">Update</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No running contracts.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
@extends('layouts.public', ['css' => 'resources/css/landlord-dashboard.css'])

@section('title', 'My Tenants · RSYNC')

@section('fonts')
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<link href="https://unpkg.com/boxicons@latest/css/boxicons.min.css" rel="stylesheet">
@endsection

@section('content')
<nav>
    <span>RS<em>Y</em>NC</span>
    <div class="nav-actions">
        <a href="{{ route('landlord.dashboard') }}">Dashboard</a>
        <a href="{{ route('account') }}">Account</a>
        <a href="{{ route('landlord.maintenance') }}">Maintenance</a>
        <a href="{{ route('logout') }}">Logout</a>
    </div>
</nav>
@include('partials.verify-banner')

<div class="wrap">
    <div class="top-bar">
        <h1>My Tenants</h1>
        <span class="t-count">{{ $tenancies->count() }} current tenant{{ $tenancies->count() === 1 ? '' : 's' }}</span>
    </div>

    <div class="tbl-wrap">
    <table>
        <thead><tr><th>Tenant</th><th>Room</th><th>Contract</th><th>Status</th><th>Payment</th><th>Contact</th><th>Valid ID</th></tr></thead>
        <tbody>
        @forelse ($tenancies as $t)
            @php
                $end = $t->end_date;
                $daysLeft = $end ? (int) $today->diffInDays($end, false) : null;
            @endphp
            <tr>
                <td>
                    <div class="t-person">
                        @include('partials.avatar', ['u' => $t->user, 'size' => 40])
                        <div>
                            <strong>{{ $t->user?->name ?? 'Unknown' }}</strong>
                            <div class="t-sub">{{ $t->user?->email }}</div>
                        </div>
                    </div>
                </td>
                <td>{{ $t->room?->room_name }}</td>
                <td>
                    {{ $t->start_date?->format('M j, Y') ?? '—' }} – {{ $end?->format('M j, Y') ?? '—' }}
                </td>
                <td>
                    @if ($daysLeft === null)
                        <span class="badge badge-av">Active</span>
                    @elseif ($daysLeft < 0)
                        <span class="badge badge-oc">Contract ended {{ abs($daysLeft) }} day{{ abs($daysLeft) === 1 ? '' : 's' }} ago</span>
                    @elseif ($daysLeft <= 30)
                        <span class="badge badge-warn">Active · ends in {{ $daysLeft }} day{{ $daysLeft === 1 ? '' : 's' }}</span>
                    @else
                        <span class="badge badge-av">Active</span>
                    @endif
                </td>
                <td>{{ $t->payment_status ?? 'Registered' }}</td>
                <td class="t-contact">
                    @if ($t->user?->contact_number)<div><i class="bx bx-phone"></i> {{ $t->user->contact_number }}</div>@endif
                    @if ($t->user?->emergency_contact_number)
                        <div class="t-sub">Emergency: {{ $t->user->emergency_contact_name }} ({{ $t->user->emergency_contact_relationship }}) · {{ $t->user->emergency_contact_number }}</div>
                    @endif
                    @if ($t->user?->social_media_url)<div class="t-sub"><a href="{{ $t->user->social_media_url }}" target="_blank" rel="noopener nofollow">Social media</a></div>@endif
                    @if (! $t->user?->contact_number && ! $t->user?->emergency_contact_number)<span class="t-sub">Not filled in yet</span>@endif
                </td>
                <td>
                    @if ($t->user?->valid_id)
                        <a href="{{ route('account.valid-id.show', $t->user->user_id) }}" target="_blank" rel="noopener">View ID</a>
                    @else
                        <span class="t-sub">Not uploaded</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="t-empty">No current tenants yet. Assign tenants to your rooms from the Dashboard.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
</div>
@endsection

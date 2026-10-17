@extends('layouts.public', ['css' => 'resources/css/tenant-dashboard.css'])

@section('title', 'Tenant Dashboard · RSYNC')

@section('fonts')
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<link href="https://unpkg.com/boxicons@latest/css/boxicons.min.css" rel="stylesheet">
@endsection

@section('content')
<nav>
    <span>RS<em>Y</em>NC</span>
    <div class="nav-actions">
        <a href="{{ route('rooms.index') }}">Browse Rooms</a>
        <a href="{{ route('logout') }}">Logout</a>
    </div>
</nav>
@include('partials.verify-banner')

<div class="wrap">
    <div class="top-bar">
        <h1>Your Dashboard</h1>
    </div>

    @if ($msg)<div class="toast {{ $isErr ? 'err' : '' }}">{{ $msg }}</div>@endif

    <!-- ACTIVE BOOKINGS SECTION -->
    <div class="section-title">📍 Your Applied Rooms</div>
    @if ($bookings->isEmpty())
        <div class="empty-state">
            <i class="bx bx-home"></i>
            <p><a href="{{ route('rooms.index') }}" style="color:var(--amber)">Browse rooms</a> to find your next home.</p>
        </div>
    @else
        <div class="tbl-wrap">
        <table>
            <thead><tr><th>Room</th><th>Price</th><th>Duration</th><th>Occupancy</th><th>Status</th></tr></thead>
            <tbody>
            @foreach ($bookings as $b)
                @php
                    $start = $b->start_date;
                    $end = $b->end_date;
                    $days = round(($end->getTimestamp() - $start->getTimestamp()) / 86400);
                @endphp
            <tr>
                <td><strong>{{ $b->room->room_name }}</strong></td>
                <td>₱{{ number_format($b->room->price) }}</td>
                <td>{{ $start->format('M d, Y') }} - {{ $end->format('M d, Y') }}<br><span style="font-size:11px;color:var(--ink3);">{{ $days }} days</span></td>
                <td>{{ (int) $b->room->current_occupants }}/{{ (int) $b->room->max_occupants }} occupants</td>
                <td><span class="badge badge-av">Active</span></td>
            </tr>
            @endforeach
            </tbody>
        </table>
        </div>
    @endif

    <!-- APPLICATIONS SECTION -->
    <div class="section-title">📝 Your Applications</div>
    @if ($applications->isEmpty())
        <div class="empty-state">
            <i class="bx bx-file"></i>
            <p>No applications yet. <a href="{{ route('rooms.index') }}" style="color:var(--amber)">Apply for a room</a> to get started.</p>
        </div>
    @else
        @foreach ($applications as $app)
        <div class="room-card">
            @if ($app->cover_image)
                <img src="{{ asset('images/' . $app->cover_image) }}" alt="{{ $app->room_name }}" class="room-card-img" onerror="this.style.display='none'">
            @endif
            <div class="room-card-body">
                <div class="room-card-head">
                    <div>
                        <div class="room-card-name">{{ $app->room_name }}</div>
                        <div class="room-card-status {{ strtolower((string) $app->status) }}">{{ $app->status }}</div>
                    </div>
                    <div class="room-card-price">₱{{ number_format($app->price) }}<span>/month</span></div>
                </div>
                <div class="room-card-info">
                    {{ $app->description }}<br>
                    <strong>Applied:</strong> {{ $app->applied_at?->format('M d, Y \a\t h:i A') ?? '—' }}
                </div>
                @if ($app->status === 'Pending')
                    <div style="font-size:11px;color:var(--ink3);">⏳ Waiting for landlord approval</div>
                @elseif ($app->status === 'Verified')
                    <div style="font-size:11px;color:var(--green);">✓ Your application was verified! Proceed to booking.</div>
                @elseif ($app->status === 'Rejected')
                    <div style="font-size:11px;color:var(--red);">✕ Your application was rejected. Try another room.</div>
                @endif
            </div>
        </div>
        @endforeach
    @endif
</div>

<footer>
    <div class="foot-logo">RS<em>Y</em>NC</div>
    <p>© 2026 RSYNC. All rights reserved.</p>
</footer>
@endsection
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
                <img src="{{ \App\Support\RoomPhotos::url($app->cover_image) }}" alt="{{ $app->room_name }}" class="room-card-img" onerror="this.style.display='none'">
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
    
    <!-- NEW: MAINTENANCE REQUESTS -->
    <div class="section-title">🔧 Maintenance Requests</div>
    @if ($bookings->isEmpty())
        <div class="empty-state">
            <i class="bx bx-wrench"></i>
            <p>You can request maintenance once you are renting a room.</p>
        </div>
    @else
        <form class="form-card" method="POST" action="{{ route('tenant.maintenance.store') }}">
            @csrf
            <div class="form-row">
                <div class="field">
                    <label for="m-room">Room</label>
                    <select id="m-room" name="room_id" required>
                        @foreach ($bookings as $b)
                            <option value="{{ $b->room_id }}" @selected(old('room_id') == $b->room_id)>{{ $b->room->room_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field grow">
                    <label for="m-desc">What needs fixing?</label>
                    <textarea id="m-desc" name="description" rows="2" maxlength="1000" required placeholder="e.g. The bathroom faucet is leaking">{{ old('description') }}</textarea>
                </div>
            </div>
            <button type="submit" class="btn btn-dark">Send request</button>
        </form>
    @endif
    @if ($maintenance->isNotEmpty())
        <div class="tbl-wrap">
        <table>
            <thead><tr><th>Room</th><th>Problem</th><th>Submitted</th><th>Status</th></tr></thead>
            <tbody>
            @foreach ($maintenance as $m)
            <tr>
                <td><strong>{{ $m->room?->room_name ?? '—' }}</strong></td>
                <td>{{ $m->description }}</td>
                <td>{{ $m->submitted_at?->format('M d, Y') ?? '—' }}</td>
                <td><span class="badge {{ ['Pending' => 'badge-pend', 'In Progress' => 'badge-prog', 'Completed' => 'badge-av'][$m->status] ?? '' }}">{{ $m->status }}</span></td>
            </tr>
            @endforeach
            </tbody>
        </table>
        </div>
    @endif

    <!-- NEW: PAYMENTS -->
    <div class="section-title">💳 Payments</div>
    @if ($bookings->isEmpty())
        <div class="empty-state">
            <i class="bx bx-wallet"></i>
            <p>You can submit payments once you are renting a room.</p>
        </div>
    @else
        <form class="form-card" method="POST" action="{{ route('tenant.payments.store') }}">
            @csrf
            <div class="form-row">
                <div class="field">
                    <label for="p-amount">Amount (₱)</label>
                    <input id="p-amount" type="number" name="amount" min="1" step="0.01" required value="{{ old('amount') }}">
                </div>
                <div class="field">
                    <label for="p-date">Date paid</label>
                    <input id="p-date" type="date" name="date" required max="{{ now()->toDateString() }}" value="{{ old('date', now()->toDateString()) }}">
                </div>
            </div>
            <button type="submit" class="btn btn-dark">Submit payment</button>
        </form>
    @endif
    @if ($payments->isNotEmpty())
        <div class="tbl-wrap">
        <table>
            <thead><tr><th>Date</th><th>Amount</th><th>Status</th></tr></thead>
            <tbody>
            @foreach ($payments as $p)
            <tr>
                <td>{{ $p->date?->format('M d, Y') }}</td>
                <td>₱{{ number_format((float) $p->amount, 2) }}</td>
                <td><span class="badge {{ $p->status === 'Verified' ? 'badge-av' : 'badge-pend' }}">{{ $p->status }}</span></td>
            </tr>
            @endforeach
            </tbody>
        </table>
        </div>
    @endif
</div>

<footer>
    <div class="foot-logo">RS<em>Y</em>NC</div>
    <p>© 2026 RSYNC. All rights reserved.</p>
</footer>
@endsection
@extends('layouts.public', ['css' => 'resources/css/tenant-dashboard.css', 'js' => 'resources/js/tenant-dashboard.js'])

@section('title', 'My room · RSYNC')

@section('fonts')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,100..900&family=Syne:wght@800&display=swap" rel="stylesheet">
<link href="https://unpkg.com/boxicons@latest/css/boxicons.min.css" rel="stylesheet">
@endsection

@push('head')
{{-- Hide the other sections right away (before the page script loads) so the page does not jump. --}}
<script>document.documentElement.classList.add('js');try{if(localStorage.getItem('rsync-side-collapsed')==='1')document.documentElement.classList.add('side-collapsed');}catch(e){}</script>
@endpush

@section('content')
@php
    $me = auth()->user();
    $now = now('Asia/Manila');
    $hour = (int) $now->format('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
    $firstName = \Illuminate\Support\Str::of($me->name)->explode(' ')->first();
    $openRequests = $maintenance->whereIn('status', ['Pending', 'In Progress'])->count();
    $latestRequest = $maintenance->firstWhere('status', 'In Progress') ?? $maintenance->firstWhere('status', 'Pending') ?? $maintenance->first();
    $lastPayment = $payments->first();
    $latestApp = $applications->first();
    $pendingApps = $applications->where('status', 'Pending')->count();
    $badge = ['Pending' => 'badge-pend', 'In Progress' => 'badge-prog', 'Completed' => 'badge-av', 'Verified' => 'badge-av', 'Rejected' => 'badge-bad'];
    $peso = fn ($v) => '₱' . number_format((float) $v, 2);
    $firstBooking = $bookings->first();
@endphp

{{-- "Updates" card: under the room card, or in the side column when there is no room to show. --}}
@php $glanceInSide = $bookings->isEmpty(); @endphp
@section('glance')
                <div class="card glance">
                    <h3 class="card-title">Updates</h3>
                    <div class="glance-rows">
                    <a class="glance-row" href="#maintenance" data-panel-link="maintenance">
                        <i class="bx bx-wrench" aria-hidden="true"></i>
                        <span class="glance-text">
                            <strong>Repairs</strong>
                            <span>{{ $latestRequest ? \Illuminate\Support\Str::limit($latestRequest->description, 42) : 'No requests sent' }}</span>
                        </span>
                        @if ($latestRequest)<span class="badge {{ $badge[$latestRequest->status] ?? '' }}">{{ $latestRequest->status }}</span>@endif
                    </a>
                    <a class="glance-row" href="#payments" data-panel-link="payments">
                        <i class="bx bx-wallet" aria-hidden="true"></i>
                        <span class="glance-text">
                            <strong>Payments</strong>
                            <span>{{ $lastPayment ? 'Last: ' . $peso($lastPayment->amount) . ' on ' . $lastPayment->date?->format('M j') : 'No payments recorded' }}</span>
                        </span>
                        @if ($lastPayment)<span class="badge {{ $badge[$lastPayment->status] ?? '' }}">{{ $lastPayment->status }}</span>@endif
                    </a>
                    <a class="glance-row" href="#applications" data-panel-link="applications">
                        <i class="bx bx-file" aria-hidden="true"></i>
                        <span class="glance-text">
                            <strong>Applications</strong>
                            <span>{{ $latestApp ? $latestApp->room_name : 'None sent' }}</span>
                        </span>
                        @if ($latestApp)<span class="badge {{ $badge[$latestApp->status] ?? '' }}">{{ $latestApp->status }}</span>@endif
                    </a>
                    </div>
                </div>
@endsection

<a class="skip" href="#main">Skip to content</a>

{{-- Phones and tablets: a slim top bar. The Menu button slides the menu in from the left. --}}
<header class="topbar">
    <a class="wordmark" href="#overview" data-panel-link="overview" aria-label="RSYNC, my room">RS<em>Y</em>NC</a>
    <button type="button" class="menu-btn" data-menu-open aria-controls="td-menu" aria-expanded="false">
        <i class="bx bx-menu" aria-hidden="true"></i><span>Menu</span>
        @if ($openRequests + $pendingApps)<b class="count" aria-label="{{ $openRequests + $pendingApps }} to check">{{ $openRequests + $pendingApps }}</b>@endif
    </button>
</header>

<div class="scrim" data-menu-close></div>

{{-- The menu: fixed down the left side on laptops, a slide-in panel on phones and tablets. --}}
<aside class="side" id="td-menu" aria-label="Menu">
    <div class="side-top">
        <a class="wordmark" href="#overview" data-panel-link="overview" aria-label="RSYNC, my room">RS<em>Y</em>NC</a>
        {{-- Laptops: folds the menu to a slim strip of icons. Phones and tablets: slides the menu away. --}}
        <button type="button" class="side-toggle" data-side-toggle aria-controls="td-menu" aria-expanded="true" aria-label="Collapse menu" title="Collapse menu"><i class="bx bx-chevrons-left" aria-hidden="true"></i></button>
    </div>

    <nav class="tabs" aria-label="Dashboard sections">
        <a href="#overview" data-panel-link="overview" data-tip="My room"><i class="bx bx-door-open" aria-hidden="true"></i><span>My room</span></a>
        <a href="#payments" data-panel-link="payments" data-tip="Payments"><i class="bx bx-wallet" aria-hidden="true"></i><span>Payments</span></a>
        <a href="#maintenance" data-panel-link="maintenance" data-tip="Repairs"><i class="bx bx-wrench" aria-hidden="true"></i><span>Repairs</span>@if ($openRequests)<b class="count" aria-label="{{ $openRequests }} open">{{ $openRequests }}</b>@endif</a>
        <a href="#applications" data-panel-link="applications" data-tip="Applications"><i class="bx bx-file" aria-hidden="true"></i><span>Applications</span>@if ($pendingApps)<b class="count" aria-label="{{ $pendingApps }} waiting">{{ $pendingApps }}</b>@endif</a>
    </nav>

    <div class="acct" id="td-account">
        <div class="acct-me">
            @include('partials.avatar', ['u' => $me, 'size' => 36])
            <div><strong>{{ $me->name }}</strong><span>{{ $me->email }}</span></div>
        </div>
        <a href="{{ route('account') }}" data-tip="Account"><i class="bx bx-user" aria-hidden="true"></i><span>Account</span></a>
        <a href="{{ route('rooms.index') }}" data-tip="Browse Rooms"><i class="bx bx-search" aria-hidden="true"></i><span>Browse Rooms</span></a>
        <a href="{{ route('logout') }}" data-tip="Logout"><i class="bx bx-log-out" aria-hidden="true"></i><span>Logout</span></a>
    </div>
</aside>

@include('partials.verify-banner')

<main id="main" class="wrap">
    <div class="hello">
        <h1><span class="sr-only">Your Dashboard: </span>{{ $greeting }}, {{ $firstName }}</h1>
        <p>{{ $now->format('l, F j') }} in Baguio</p>
    </div>

    @if ($msg)<div class="toast {{ $isErr ? 'err' : '' }}">{{ $msg }}</div>@endif

    {{-- ───────── My room ───────── --}}
    <section class="panel-sec" id="overview" data-panel="overview" aria-labelledby="h-overview">
        <h2 id="h-overview" class="sr-only">My room</h2>

        <div class="ov">
            <div class="ov-main">
                @forelse ($bookings as $b)
                    @php
                        $start = $b->start_date;
                        $end = $b->end_date;
                        $days = (int) round(($end->getTimestamp() - $start->getTimestamp()) / 86400);
                        $left = max(0, (int) now()->startOfDay()->diffInDays($end, false));
                    @endphp
                    <article class="room-sum">
                        <div class="rs-head">
                            <div>
                                <h3>{{ $b->room->room_name }}</h3>
                                @if ($b->room->address)<p class="rs-addr"><i class="bx bx-map" aria-hidden="true"></i>{{ $b->room->address }}</p>@endif
                            </div>
                            <span class="rs-live">Renting now</span>
                        </div>

                        <dl class="rs-grid">
                            <div><dt>Rent</dt><dd>{{ $peso($b->monthly_rent ?? $b->room->price) }} <small>a month</small></dd></div>
                            <div class="rs-wide"><dt>Stay</dt><dd>{{ $start->format('M d, Y') }} to {{ $end->format('M d, Y') }} <small>{{ $left }} {{ $left === 1 ? 'day' : 'days' }} left of {{ $days }}</small></dd></div>
                            <div><dt>Sharing</dt><dd>{{ (int) $b->room->current_occupants }} of {{ (int) $b->room->max_occupants }} <small>occupants</small></dd></div>
                        </dl>

                        <div class="rs-foot">
                            @if ($b->isAccepted())
                                <span class="rs-ok"><i class="bx bx-check-circle" aria-hidden="true"></i>Contract accepted</span>
                                <a href="{{ route('contracts.show', $b->tenant_id) }}" class="btn btn-line btn-sm" target="_blank" rel="noopener"><i class="bx bx-file" aria-hidden="true"></i>View contract</a>
                            @else
                                <a href="{{ route('contracts.show', $b->tenant_id) }}" class="btn btn-sun" target="_blank" rel="noopener">Review and accept your contract</a>
                            @endif
                        </div>
                    </article>
                @empty
                    <article class="room-sum">
                        <h3>No room yet</h3>
                        <p class="rs-lead">You are not renting a room yet. Browse rooms to find your next home.</p>
                        <div class="rs-foot">
                            <a href="{{ route('rooms.index') }}" class="btn btn-ink"><i class="bx bx-search" aria-hidden="true"></i>Find a room</a>
                        </div>
                    </article>
                @endforelse

                @unless ($glanceInSide) @yield('glance') @endunless
            </div>

            <div class="ov-side">
                @if ($glanceInSide) @yield('glance') @endif
                @if ($notifications->isNotEmpty())
                <div class="card notices">
                    <div class="card-head">
                        <h3 class="card-title"><i class="bx bx-bell" aria-hidden="true"></i>Notifications <span class="count">{{ $notifications->count() }}</span></h3>
                        <form method="POST" action="{{ route('tenant.notifications.read') }}">
                            @csrf
                            <button type="submit" class="btn btn-line btn-sm">Mark all as read</button>
                        </form>
                    </div>
                    <ul class="notice-list">
                        @foreach ($notifications as $n)
                            <li>
                                <a href="{{ route('contracts.show', (int) ($n->data['tenancy_id'] ?? 0)) }}">{{ $n->data['title'] ?? 'Contract notice' }}</a>
                                <p>{{ $n->data['message'] ?? '' }}</p>
                                <time datetime="{{ $n->created_at?->toIso8601String() }}">{{ $n->created_at?->format('M d, Y \a\t h:i A') }}</time>
                            </li>
                        @endforeach
                    </ul>
                </div>
                @endif
                @if ($firstBooking)
                <div class="card rent">
                    <h3 class="card-title">Next rent</h3>
                    @if ($nextDue)
                        <p class="rent-line"><strong>{{ $peso($firstBooking->monthly_rent ?? $firstBooking->room->price) }}</strong> due {{ $nextDue->format('D, M j') }}</p>
                        @php $inDays = (int) $now->copy()->startOfDay()->diffInDays($nextDue, false); @endphp
                        <p class="rent-when">{{ $inDays <= 0 ? 'Due today' : ($inDays === 1 ? 'Tomorrow' : 'In ' . $inDays . ' days') }}</p>
                    @else
                        <p class="rent-line rent-none">No more rent is due under this contract.</p>
                    @endif
                    <div class="rent-foot">
                        <p>Paid and confirmed: <strong>{{ $peso($paidTotal) }}</strong>@if ($waitingTotal > 0)<br>Waiting for confirmation: <strong>{{ $peso($waitingTotal) }}</strong>@endif</p>
                        <a href="#payments" data-panel-link="payments" class="btn btn-line">Record a payment</a>
                    </div>
                </div>
                @endif
            </div>
        </div>

    </section>

    {{-- ───────── Payments ───────── --}}
    <section class="panel-sec" id="payments" data-panel="payments" aria-labelledby="h-payments">
        <div class="sec-head">
            <h2 id="h-payments">Payments</h2>
            <p>Record rent you have paid. Your landlord or the admin confirms it.</p>
        </div>
        <div class="split">
            @if ($bookings->isEmpty())
                <div class="card empty">
                    <i class="bx bx-wallet" aria-hidden="true"></i>
                    <p>You can submit payments once you are renting a room.</p>
                </div>
            @else
                <form class="card form" method="POST" action="{{ route('tenant.payments.store') }}">
                    @csrf
                    <h3 class="card-title">Record a payment</h3>
                    <div class="field">
                        <label for="p-amount">Amount (₱)</label>
                        <input id="p-amount" type="number" name="amount" min="1" step="0.01" required inputmode="decimal" value="{{ old('amount', $firstBooking->monthly_rent ?: '') }}">
                    </div>
                    <div class="field">
                        <label for="p-date">Date paid</label>
                        <input id="p-date" type="date" name="date" required max="{{ now()->toDateString() }}" value="{{ old('date', now()->toDateString()) }}">
                    </div>
                    <button type="submit" class="btn btn-ink btn-block">Submit payment</button>
                </form>
            @endif

            <div class="card history">
                <h3 class="card-title">Payment history</h3>
                @if ($payments->isNotEmpty())
                    <table class="pay-table">
                        <thead><tr><th scope="col">Date paid</th><th scope="col">Amount</th><th scope="col">Status</th></tr></thead>
                        <tbody>
                        @foreach ($payments as $p)
                            <tr>
                                <td>{{ $p->date?->format('M d, Y') }}</td>
                                <td class="num">{{ $peso($p->amount) }}</td>
                                <td><span class="badge {{ $p->status === 'Verified' ? 'badge-av' : 'badge-pend' }}">{{ $p->status }}</span></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="muted">Nothing recorded yet. Payments you submit show up here.</p>
                @endif
            </div>
        </div>
    </section>

    {{-- ───────── Repairs (maintenance) ───────── --}}
    <section class="panel-sec" id="maintenance" data-panel="maintenance" aria-labelledby="h-maintenance">
        <div class="sec-head">
            <h2 id="h-maintenance">Repairs</h2>
            <p>Tell your landlord what needs fixing and follow their reply here.</p>
        </div>
        <div class="split">
            @if ($bookings->isEmpty())
                <div class="card empty">
                    <i class="bx bx-wrench" aria-hidden="true"></i>
                    <p>You can request maintenance once you are renting a room.</p>
                </div>
            @else
                <form class="card form" method="POST" action="{{ route('tenant.maintenance.store') }}">
                    @csrf
                    <h3 class="card-title">Report a problem</h3>
                    <div class="field">
                        <label for="m-room">Room</label>
                        <select id="m-room" name="room_id" required>
                            @foreach ($bookings as $b)
                                <option value="{{ $b->room_id }}" @selected(old('room_id') == $b->room_id)>{{ $b->room->room_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="m-desc">What needs fixing?</label>
                        <textarea id="m-desc" name="description" rows="4" maxlength="1000" required placeholder="e.g. The bathroom faucet is leaking">{{ old('description') }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-ink btn-block">Send request</button>
                </form>
            @endif

            <div class="card requests">
                <h3 class="card-title">Your requests</h3>
                @if ($maintenance->isNotEmpty())
                    <ul class="req-list">
                        @foreach ($maintenance as $m)
                            <li class="req">
                                <div class="req-top">
                                    <strong>{{ $m->room?->room_name ?? '—' }}</strong>
                                    <span class="badge {{ $badge[$m->status] ?? '' }}">{{ $m->status }}</span>
                                </div>
                                <p class="req-text">{{ $m->description }}</p>
                                @if ($m->landlord_note)
                                    <div class="reply"><strong>Landlord's reply:</strong> {{ $m->landlord_note }}</div>
                                @endif
                                <time class="req-date">Sent {{ $m->submitted_at?->format('M d, Y') ?? '—' }}</time>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="muted">No requests yet. Anything broken or leaking? Report it on the left.</p>
                @endif
            </div>
        </div>
    </section>

    {{-- ───────── Applications ───────── --}}
    <section class="panel-sec" id="applications" data-panel="applications" aria-labelledby="h-applications">
        <div class="sec-head">
            <h2 id="h-applications">Applications</h2>
            <p>Rooms you have applied for, newest first.</p>
        </div>
        @if ($applications->isEmpty())
            <div class="card empty">
                <i class="bx bx-file" aria-hidden="true"></i>
                <p>No applications yet. <a href="{{ route('rooms.index') }}">Apply for a room</a> to get started.</p>
            </div>
        @else
            <div class="app-grid">
            @foreach ($applications as $app)
                <article class="card room-card">
                    <div class="room-card-media">
                        @if ($app->cover_image)
                            <img src="{{ \App\Support\RoomPhotos::url($app->cover_image) }}" alt="" class="room-card-img" loading="lazy" onerror="this.remove()">
                        @endif
                        <i class="bx bx-image" aria-hidden="true"></i>
                    </div>
                    <div class="room-card-body">
                        <div class="room-card-head">
                            <h3 class="room-card-name">{{ $app->room_name }}</h3>
                            <div class="room-card-status {{ strtolower((string) $app->status) }}">{{ $app->status }}</div>
                        </div>
                        <div class="room-card-price">₱{{ number_format($app->price) }}<span>/month</span></div>
                        <div class="room-card-info">
                            <p class="room-card-desc">{{ $app->description }}</p>
                            <strong>Applied:</strong> {{ $app->applied_at?->format('M d, Y \a\t h:i A') ?? '—' }}
                        </div>
                        @if ($app->status === 'Pending')
                            <div class="room-card-note">⏳ Waiting for landlord approval</div>
                        @elseif ($app->status === 'Verified')
                            <div class="room-card-note ok">✓ Your application was verified! Proceed to booking.</div>
                        @elseif ($app->status === 'Rejected')
                            <div class="room-card-note bad">✕ Your application was rejected. Try another room.</div>
                        @endif
                    </div>
                </article>
            @endforeach
            </div>
        @endif
    </section>

    <footer class="foot">RS<em>Y</em>NC, boarding houses in Baguio City. © 2026</footer>
</main>
@endsection

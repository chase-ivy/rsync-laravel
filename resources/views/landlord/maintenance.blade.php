@extends('layouts.public', ['css' => 'resources/css/maintenance.css'])

@section('title', 'Maintenance · RSYNC')

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
        <a href="{{ route('landlord.tenants') }}">Tenants</a>
        <a href="{{ route('logout') }}">Logout</a>
    </div>
</nav>
@include('partials.verify-banner')
<div class="wrap">
    <div class="top-bar">
        <h1>Maintenance Requests</h1>
        <p style="font-size:14px;color:var(--ink3);max-width:560px;">Requests from tenants in your rooms. Move each one along (Pending → In Progress → Completed) and leave a short reply the tenant sees on their dashboard.</p>
    </div>

    @if ($msg)<div class="toast {{ $isErr ? 'err' : '' }}">{{ $msg }}</div>@endif

    <div class="card">
        <div class="card-header">
            <h2>Tenant Maintenance Requests</h2>
            {{-- Filter by status (?status=...). "All" shows the 8 newest. --}}
            <div class="filters">
                <a href="{{ route('landlord.maintenance') }}" class="{{ $filter ? '' : 'on' }}">All</a>
                @foreach (\App\Http\Controllers\MaintenanceController::FILTERS as $f)
                    <a href="{{ route('landlord.maintenance', ['status' => $f]) }}" class="{{ $filter === $f ? 'on' : '' }}">{{ $f }}</a>
                @endforeach
            </div>
        </div>
        <div class="tbl-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Request ID</th>
                        <th>Room / Tenant</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Update &amp; reply</th>
                    </tr>
                </thead>
                <tbody>
                    @if ($maintenanceRequests->isEmpty())
                    <tr><td colspan="6" style="text-align:center;color:var(--ink3);padding:24px;">No maintenance requests found.</td></tr>
                    @else
                        @foreach ($maintenanceRequests as $req)
                        @php $done = $req->status === 'Completed'; @endphp
                        <tr>
                            <td>{{ $req->request_id ?? 'N/A' }}</td>
                            <td><strong>{{ $req->room?->room_name ?? '—' }}</strong><div class="sub">{{ $req->tenant?->name ?? '—' }}</div></td>
                            <td>{{ $req->description ?? 'No description' }}</td>
                            <td>
                                <span class="badge {{ strtolower(str_replace(' ', '', $req->status ?? 'pending')) }}">{{ $req->status ?? 'Pending' }}</span>
                                @if ($done && $req->resolved_at)<div class="sub">Done {{ $req->resolved_at->format('Y-m-d') }}</div>@endif
                            </td>
                            <td>{{ $req->submitted_at?->format('Y-m-d H:i:s') ?? 'Unknown' }}</td>
                            <td>
                                <form method="POST" action="{{ route('landlord.maintenance.status') }}" class="review-form">
                                    @csrf
                                    <input type="hidden" name="request_id" value="{{ $req->request_id }}">
                                    @if ($filter)<input type="hidden" name="filter" value="{{ $filter }}">@endif
                                    @if ($done)
                                        <input type="hidden" name="status" value="Completed">
                                    @else
                                        <select name="status" aria-label="New status">
                                            <option value="In Progress" @selected($req->status !== 'In Progress')>In Progress</option>
                                            <option value="Completed" @selected($req->status === 'In Progress')>Completed</option>
                                        </select>
                                    @endif
                                    <textarea name="landlord_note" rows="2" maxlength="500" placeholder="Reply to the tenant (optional)">{{ $req->landlord_note }}</textarea>
                                    <button type="submit" class="btn-save">{{ $done ? 'Save reply' : 'Save' }}</button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>Maintenance Status Report</h2>
        </div>
        <div class="status-grid">
            @if ($maintenanceStatus->isEmpty())
                <div class="status-card"><p class="count">0</p><p class="label">No status data available</p></div>
            @else
                @foreach ($maintenanceStatus as $status)
                <div class="status-card">
                    <p class="count">{{ (int) ($status->count ?? 0) }}</p>
                    <p class="label">{{ $status->status ?? 'Unknown' }}</p>
                </div>
                @endforeach
            @endif
        </div>
    </div>
</div>
<footer>
    <div class="foot-logo">RS<em>Y</em>NC</div>
    <p>© 2026 RSYNC. All rights reserved.</p>
</footer>
@endsection

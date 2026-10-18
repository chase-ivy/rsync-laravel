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
        <a href="{{ route('logout') }}">Logout</a>
    </div>
</nav>
@include('partials.verify-banner')
<div class="wrap">
    <div class="top-bar">
        <h1>Maintenance Requests</h1>
        <p style="font-size:14px;color:var(--ink3);max-width:560px;">Click on the Maintenance link from the dashboard to see tenant-submitted requests and the overall status report below.</p>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>Tenant Maintenance Requests</h2>
        </div>
        <div class="tbl-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Request ID</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    @if ($maintenanceRequests->isEmpty())
                    <tr><td colspan="4" style="text-align:center;color:var(--ink3);padding:24px;">No maintenance requests found.</td></tr>
                    @else
                        @foreach ($maintenanceRequests as $req)
                        <tr>
                            <td>{{ $req->request_id ?? 'N/A' }}</td>
                            <td>{{ $req->description ?? 'No description' }}</td>
                            <td><span class="badge {{ strtolower(str_replace(' ', '', $req->status ?? 'pending')) }}">{{ $req->status ?? 'Pending' }}</span></td>
                            <td>{{ $req->submitted_at?->format('Y-m-d H:i:s') ?? 'Unknown' }}</td>
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
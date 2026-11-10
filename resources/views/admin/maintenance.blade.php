@extends('layouts.public', ['css' => 'resources/css/admin.css'])

@section('title', 'Maintenance · RSYNC')

@section('fonts')
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<link href="https://unpkg.com/boxicons@latest/css/boxicons.min.css" rel="stylesheet">
@endsection

@php($code = fn ($id) => \App\Http\Controllers\Admin\AdminMaintenanceController::code($id))

@section('content')
@include('partials.admin-nav')

<div class="wrap">
    <div class="top-bar">
        <h1>Maintenance</h1>
        <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
            <a href="{{ route('logout') }}" class="btn btn-dark">Sign out</a>
        </div>
    </div>

    @if ($msg)<div class="toast {{ $isErr ? 'err' : '' }}">{{ $msg }}</div>@endif

    <!-- A. MAINTENANCE REQUESTS -->
    <div class="card" id="requests">
        <div class="card-header">
            <h2>Maintenance Requests</h2>
            <span class="card-sub">All rooms · tenants send these from their dashboard</span>
        </div>
        <div class="tbl-wrap">
            <table>
                <thead><tr><th>Request ID</th><th>Tenant Name</th><th>Room</th><th>Description</th><th>Request Date</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                @forelse ($requests as $req)
                    <tr>
                        <td>{{ $code($req->request_id) }}</td>
                        <td>{{ $req->tenant?->name ?? '—' }}</td>
                        <td>{{ $req->room?->room_name ?? '—' }}</td>
                        <td>{{ $req->description }}</td>
                        <td>{{ $req->submitted_at?->format('Y-m-d') ?? '—' }}</td>
                        <td><span class="badge badge-{{ \Illuminate\Support\Str::slug($req->status) }}">{{ $req->status }}</span></td>
                        <td>
                            @if ($req->status === 'Completed')
                                <span class="text-success">Completed</span>
                            @else
                                <div class="action-buttons">
                                    @if ($req->status === 'Pending')
                                        <form method="POST" action="{{ route('admin.maintenance.status') }}">
                                            @csrf
                                            <input type="hidden" name="request_id" value="{{ $req->request_id }}">
                                            <button type="submit" name="status" value="In Progress" class="btn btn-ghost">Start</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.maintenance.status') }}">
                                        @csrf
                                        <input type="hidden" name="request_id" value="{{ $req->request_id }}">
                                        <button type="submit" name="status" value="Completed" class="btn approve">Mark as Completed</button>
                                    </form>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">No maintenance requests yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- B. MAINTENANCE STATUS REPORT -->
    <div class="card" id="report">
        <div class="card-header">
            <h2>Maintenance Status Report</h2>
        </div>
        <div class="report-totals">
            <strong>Total Requests:</strong> {{ $total }} |
            <strong>Completed:</strong> {{ $completed }} |
            <strong>In Progress:</strong> {{ $inProgress }} |
            <strong>Pending:</strong> {{ $pending }}
        </div>
        <div class="tbl-wrap">
            <table>
                <thead><tr><th>Report ID</th><th>Tenant Name</th><th>Room</th><th>Issue</th><th>Date Resolved</th><th>Status Summary</th></tr></thead>
                <tbody>
                @forelse ($requests as $req)
                    <tr>
                        <td>MSR-{{ substr($code($req->request_id), 3) }}</td>
                        <td>{{ $req->tenant?->name ?? '—' }}</td>
                        <td>{{ $req->room?->room_name ?? '—' }}</td>
                        <td>{{ $req->description }}</td>
                        <td>{{ $req->resolved_at?->format('Y-m-d') ?? 'N/A' }}</td>
                        <td><span class="badge badge-{{ \Illuminate\Support\Str::slug($req->status) }}">{{ $req->status }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">Nothing to report yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
@extends('layouts.public', ['css' => 'resources/css/admin.css', 'js' => 'resources/js/admin-dashboard.js'])

@section('title', 'Admin Dashboard · RSYNC')

@section('fonts')
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<link href="https://unpkg.com/boxicons@latest/css/boxicons.min.css" rel="stylesheet">
@endsection

@section('content')
@include('partials.admin-nav')

<div class="wrap">
    <div class="top-bar">
        <h1>Admin Dashboard</h1>
        <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
            <a href="{{ route('logout') }}" class="btn btn-dark">Sign out</a>
        </div>
    </div>

    @if ($msg)<div class="toast {{ $isErr ? 'err' : '' }}">{{ $msg }}</div>@endif

    <!-- FINANCIAL SUMMARY -->
    <div class="card">
        <div class="card-header">
            <h2>Financial Summary</h2>
        </div>
        <div class="financial-grid">
            <div class="total-card">
                <h3>Total Payments</h3>
                <p>₱{{ number_format($totalPayments) }}</p>
            </div>
            <div class="total-card">
                <h3>Monthly Earnings</h3>
                <p class="green">₱{{ number_format($monthlyEarnings) }}</p>
            </div>
        </div>
        <div class="chart-box">
            <h3>Monthly Earnings Breakdown</h3>
            @if ($monthly->isEmpty())
                <p class="muted">No verified payments this year yet.</p>
            @else
                <div class="chart-holder">
                    <canvas id="monthlyEarningsChart" width="80" height="40" data-chart='@json($monthly)'></canvas>
                </div>
            @endif
        </div>
    </div>

    <!-- VERIFY USERS -->
    <div class="card" id="users">
        <div class="card-header">
            <h2>Verify Users</h2>
        </div>
        <div class="tbl-wrap">
            <table>
                <thead>
                    <tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Action</th></tr>
                </thead>
                <tbody>
                @forelse ($pendingUsers as $u)
                    <tr>
                        <td>{{ $u->name }}</td>
                        <td>{{ $u->email }}</td>
                        <td>{{ ucfirst($u->role) }}</td>
                        <td><span class="badge badge-pending">Pending</span></td>
                        <td>
                            <div class="action-buttons">
                                <form method="POST" action="{{ route('admin.users.approve', $u->user_id) }}">
                                    @csrf
                                    <button type="submit" class="btn approve">Approve</button>
                                </form>
                                <form method="POST" action="{{ route('admin.users.reject', $u->user_id) }}">
                                    @csrf
                                    <button type="submit" class="btn delete">Reject</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No users waiting for verification.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- VERIFY PAYMENTS -->
    <div class="card" id="payments">
        <div class="card-header">
            <h2>Verify Payments</h2>
        </div>
        <div class="tbl-wrap">
            <table>
                <thead>
                    <tr><th>Tenant</th><th>Date</th><th>Amount</th><th>Status</th><th>Action</th></tr>
                </thead>
                <tbody>
                @forelse ($payments as $p)
                    <tr>
                        <td>{{ $p->user?->name ?? 'Deleted user' }}</td>
                        <td>{{ $p->date?->format('M d, Y') }}</td>
                        <td>₱{{ number_format((float) $p->amount, 2) }}</td>
                        <td><span class="badge {{ $p->status === 'Verified' ? 'badge-verified' : 'badge-pending' }}">{{ $p->status }}</span></td>
                        <td>
                            @if ($p->status === 'Pending')
                                <form method="POST" action="{{ route('admin.payments.verify', $p->payment_id) }}">
                                    @csrf
                                    <button type="submit" class="btn approve">Verify</button>
                                </form>
                            @else
                                <span>Completed</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No payments yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- TRANSACTIONS -->
    <div class="card" id="transactions">
        <div class="card-header">
            <h2>Transaction Monitoring</h2>
        </div>
        <div class="tbl-wrap">
            <table>
                <thead>
                    <tr><th>ID</th><th>Type</th><th>Date</th><th>Amount</th><th>Status</th></tr>
                </thead>
                <tbody>
                @forelse ($transactions as $t)
                    <tr>
                        <td>{{ $t->transaction_id }}</td>
                        <td>{{ $t->type }}</td>
                        <td>{{ $t->date?->format('Y-m-d') }}</td>
                        <td>₱{{ number_format((float) $t->amount) }}</td>
                        <td><span class="badge {{ strtolower($t->status) === 'completed' ? 'badge-verified' : 'badge-pending' }}">{{ $t->status }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No transactions yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
@extends('layouts.public', ['css' => 'resources/css/admin.css', 'js' => 'resources/js/admin-dashboard.js'])

@section('title', 'Finance · RSYNC')

@section('fonts')
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<link href="https://unpkg.com/boxicons@latest/css/boxicons.min.css" rel="stylesheet">
@endsection

@section('content')
@include('partials.admin-nav')

<div class="wrap">
    <div class="top-bar">
        <h1>Finance</h1>
        <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
            <a href="{{ route('logout') }}" class="btn btn-dark">Sign out</a>
        </div>
    </div>

    @if ($msg)<div class="toast {{ $isErr ? 'err' : '' }}">{{ $msg }}</div>@endif

    <!-- A. TENANT TOTAL AMOUNT -->
    <div class="card" id="overview">
        <div class="card-header">
            <h2>Tenant Total Amount</h2>
            <span class="card-sub">Running contracts · payments counted once the admin verifies them</span>
        </div>
        <div class="tbl-wrap">
            <table>
                <thead><tr><th>Tenant Name</th><th>Room</th><th>Monthly Rent</th><th>Paid (Verified)</th><th>Waiting for Verification</th></tr></thead>
                <tbody>
                @forelse ($rows as $r)
                    <tr>
                        <td>{{ $r['tenant'] }}</td>
                        <td>{{ $r['room'] }}</td>
                        <td>₱{{ number_format($r['rent']) }}</td>
                        <td>₱{{ number_format($r['paid'], 2) }}</td>
                        <td>₱{{ number_format($r['waiting'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No running contracts.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="chart-box">
            <h3>Monthly Income Summary</h3>
            @if ($monthly->isEmpty())
                <p class="muted">No verified payments this year yet.</p>
            @else
                <div class="summary-split">
                    <table class="mini-table">
                        <thead><tr><th>Month</th><th>Total Income</th></tr></thead>
                        <tbody>
                        @foreach ($monthly as $m)
                            <tr><td>{{ $m['month'] }}</td><td>₱{{ number_format($m['amount']) }}</td></tr>
                        @endforeach
                            <tr class="total-row"><td>Total</td><td>₱{{ number_format($monthly->sum('amount')) }}</td></tr>
                        </tbody>
                    </table>
                    <div class="chart-holder">
                        <canvas id="monthlyEarningsChart" width="80" height="40" data-chart='@json($monthly)'></canvas>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- B. RECORD TRANSACTION -->
    <div class="card" id="record">
        <div class="card-header">
            <h2>Record Transaction</h2>
            <span class="card-sub">The ID is created automatically, e.g. {{ now()->format('Ym') }}-RM01</span>
        </div>
        <form method="POST" action="{{ route('admin.transactions.store') }}" class="form-grid form-grid-5">
            @csrf
            <div class="field">
                <label for="t-user">Tenant</label>
                <select id="t-user" name="user_id">
                    <option value="">— No tenant —</option>
                    @foreach ($tenants as $t)
                        <option value="{{ $t->user_id }}" @selected(old('user_id') == $t->user_id)>{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="t-type">Payment Type</label>
                <select id="t-type" name="type" required>
                    @foreach ($types as $type)
                        <option @selected(old('type') === $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="t-amount">Amount (₱)</label>
                <input id="t-amount" type="number" name="amount" min="1" step="0.01" required value="{{ old('amount') }}">
            </div>
            <div class="field">
                <label for="t-date">Date Paid</label>
                <input id="t-date" type="date" name="date" required max="{{ now()->toDateString() }}" value="{{ old('date', now()->toDateString()) }}">
            </div>
            <div class="field">
                <label for="t-status">Status</label>
                <select id="t-status" name="status">
                    <option>Pending</option>
                    <option @selected(old('status') === 'Completed')>Completed</option>
                </select>
            </div>
            <button type="submit" class="btn btn-dark">Record</button>
        </form>
    </div>

    <!-- C. TENANT TRANSACTIONS -->
    <div class="card" id="transactions">
        <div class="card-header">
            <h2>Tenant Transactions</h2>
        </div>
        <div class="tbl-wrap">
            <table>
                <thead><tr><th>Transaction ID</th><th>Tenant Name</th><th>Payment Type</th><th>Amount Paid</th><th>Date Paid</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                @forelse ($transactions as $txn)
                    <tr>
                        <td>{{ $txn->transaction_id }}</td>
                        <td>{{ $txn->user?->name ?? '—' }}</td>
                        <td>{{ $txn->type }}</td>
                        <td>₱{{ number_format((float) $txn->amount) }}</td>
                        <td>{{ $txn->date?->format('Y-m-d') }}</td>
                        <td><span class="badge {{ $txn->status === 'Completed' ? 'badge-verified' : 'badge-pending' }}">{{ $txn->status }}</span></td>
                        <td>
                            @if ($txn->status === 'Pending')
                                <form method="POST" action="{{ route('admin.transactions.paid') }}">
                                    @csrf
                                    <input type="hidden" name="transaction_id" value="{{ $txn->transaction_id }}">
                                    <button type="submit" class="btn approve">Mark as Paid</button>
                                </form>
                            @else
                                <span class="text-success">Completed</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">No transactions yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
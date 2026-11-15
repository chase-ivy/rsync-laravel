@extends('layouts.public', ['css' => 'resources/css/contract.css'])

@section('title', 'Contract ' . $code . ' · RSYNC')

@section('fonts')
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
@endsection

@section('content')
@php
    $rent = (float) $terms['monthly_rent'];   // the rent saved on the contract (see Tenancy::contractTerms())
    $dash = '________________';
@endphp

{{-- The bar and buttons are hidden when printing (see contract.css). --}}
<nav class="no-print">
    <span>RS<em>Y</em>NC</span>
    <div class="nav-actions">
        <a href="{{ route($backRoute) }}">{{ $backLabel ?? 'Back to Dashboard' }}</a>
        <button type="button" class="print-btn" onclick="window.print()">Print / Save as PDF</button>
    </div>
</nav>

@if (! empty($preview))
    <p class="preview-note no-print">Sample preview of your contract format. Real contracts show the tenant's details and the dates you enter when assigning.</p>
@endif

<main class="paper">
    <header class="doc-head">
        <div class="brand">RS<em>Y</em>NC · Baguio City</div>
        <h1>Boarding House Rental Agreement</h1>
        <div class="doc-meta">
            <span>Contract No. <strong>{{ $code }}</strong></span>
            <span>Status: <strong>{{ $contract->status }}</strong></span>
            <span>Printed: {{ now()->format('F j, Y') }}</span>
        </div>
        <div class="doc-meta set-by">
            <span>Terms set by the landlord</span>
            @if ($contract->terms_updated_at)
                <span>Last updated on <strong>{{ $contract->terms_updated_at->format('F j, Y') }}</strong></span>
            @endif
        </div>
    </header>

    <p>This agreement is made between the <strong>Landlord</strong> and the <strong>Tenant</strong> named below for the rental of a room in a boarding house in Baguio City, Philippines.</p>

    <section class="parties">
        <div class="party">
            <h2>Landlord</h2>
            <dl>
                <dt>Name</dt><dd>{{ $landlord?->name ?? $dash }}</dd>
                <dt>Contact no.</dt><dd>{{ $landlord?->contact_number ?: $dash }}</dd>
                <dt>Email</dt><dd>{{ $landlord?->email ?? $dash }}</dd>
            </dl>
        </div>
        <div class="party">
            <h2>Tenant</h2>
            <dl>
                <dt>Name</dt><dd>{{ $tenant?->name ?? $dash }}</dd>
                <dt>Contact no.</dt><dd>{{ $tenant?->contact_number ?: $dash }}</dd>
                <dt>Email</dt><dd>{{ $tenant?->email ?? $dash }}</dd>
                <dt>Emergency contact</dt>
                <dd>
                    @if ($tenant?->emergency_contact_name)
                        {{ $tenant->emergency_contact_name }}@if ($tenant->emergency_contact_relationship) ({{ $tenant->emergency_contact_relationship }})@endif
                        @if ($tenant->emergency_contact_number) · {{ $tenant->emergency_contact_number }}@endif
                    @else
                        {{ $dash }}
                    @endif
                </dd>
            </dl>
        </div>
    </section>

    <section>
        <h2>1. The room</h2>
        <table class="terms">
            <tr><th>Room</th><td>{{ $room?->room_name ?? $dash }}</td></tr>
            <tr><th>Address</th><td>{{ $room?->address ?: $dash }}, Baguio City</td></tr>
            <tr><th>Maximum occupants</th><td>{{ (int) ($room?->max_occupants ?? 1) }} person{{ (int) ($room?->max_occupants ?? 1) === 1 ? '' : 's' }}</td></tr>
            @if ($room && $room->amenityList())
            <tr><th>Included amenities</th><td>{{ implode(', ', array_column($room->amenityList(), 0)) }}</td></tr>
            @endif
        </table>
    </section>

    <section>
        <h2>2. Term and rent</h2>
        <table class="terms">
            <tr><th>Start date</th><td>{{ $contract->start_date?->format('F j, Y') ?? $dash }}</td></tr>
            <tr><th>End date</th><td>{{ $contract->end_date?->format('F j, Y') ?? $dash }}@if ($months) <span class="muted">(about {{ $months }} month{{ $months === 1 ? '' : 's' }})</span>@endif</td></tr>
            <tr><th>Monthly rent</th><td><strong>₱{{ number_format($rent, 2) }}</strong> per month</td></tr>
            @if ($terms['deposit'] !== null)
            <tr><th>Security deposit</th><td>₱{{ number_format((float) $terms['deposit'], 2) }}</td></tr>
            @endif
            @if ($terms['advance_months'] !== null)
            <tr><th>Advance payment</th><td>{{ $terms['advance_months'] }} month{{ (int) $terms['advance_months'] === 1 ? '' : 's' }} of rent in advance</td></tr>
            @endif
            @if (filled($terms['utilities_note']))
            <tr><th>Utilities</th><td>{{ $terms['utilities_note'] }}</td></tr>
            @endif
        </table>
    </section>

    <section>
        <h2>3. Repairs and damages</h2>
        <p class="repairs">{{ \App\Support\ContractDefaults::repairsSentence($terms['repairs_policy']) }}</p>
    </section>

    {{-- Terms and house rules are written by the landlord (Contract page), one item per line. --}}
    <section>
        <h2>4. Terms and conditions</h2>
        @if ($terms['terms'])
            <ol class="rules">
                @foreach ($terms['terms'] as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ol>
        @else
            <p class="muted">No additional terms.</p>
        @endif
    </section>

    <section>
        <h2>5. House rules</h2>
        @if ($terms['house_rules'])
            <ol class="rules">
                @foreach ($terms['house_rules'] as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ol>
        @else
            <p class="muted">No house rules listed.</p>
        @endif
    </section>

    <p class="agree">By signing below, both parties confirm that they have read, understood and agreed to this agreement.</p>

    <section class="signatures">
        <div class="sign">
            <div class="line"></div>
            <div class="who">{{ $landlord?->name ?? 'Landlord' }}</div>
            <div class="role">Landlord · Signature over printed name</div>
            <div class="date">Date: ____________________</div>
        </div>
        <div class="sign">
            <div class="line"></div>
            <div class="who">{{ $tenant?->name ?? 'Tenant' }}</div>
            <div class="role">Tenant · Signature over printed name</div>
            <div class="date">Date: ____________________</div>
        </div>
    </section>

    <footer class="doc-foot">Generated by RSYNC from contract {{ $code }}. The terms and house rules were set by the landlord; read them carefully and check them against local rules before signing.</footer>
</main>
@endsection

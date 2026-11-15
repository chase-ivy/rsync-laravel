@extends('layouts.public', ['css' => 'resources/css/contract.css'])

@section('title', 'Contract ' . $code . ' · RSYNC')

@section('fonts')
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
@endsection

@section('content')
@php
    $rent = (int) ($room?->price ?? 0);
    $dash = '________________';
@endphp

{{-- The bar and buttons are hidden when printing (see contract.css). --}}
<nav class="no-print">
    <span>RS<em>Y</em>NC</span>
    <div class="nav-actions">
        <a href="{{ route($backRoute) }}">Back to Dashboard</a>
        <button type="button" class="print-btn" onclick="window.print()">Print / Save as PDF</button>
    </div>
</nav>

<main class="paper">
    <header class="doc-head">
        <div class="brand">RS<em>Y</em>NC · Baguio City</div>
        <h1>Boarding House Rental Agreement</h1>
        <div class="doc-meta">
            <span>Contract No. <strong>{{ $code }}</strong></span>
            <span>Status: <strong>{{ $contract->status }}</strong></span>
            <span>Printed: {{ now()->format('F j, Y') }}</span>
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
        </table>
    </section>

    <section>
        <h2>3. Terms and conditions</h2>
        {{-- Generic terms; the landlord and tenant may agree on changes in writing. --}}
        <ol class="rules">
            <li>Rent is paid in advance on or before the same day of each month as the start date. Payments are recorded in RSYNC and confirmed by the landlord or admin.</li>
            <li>The room may only be used as a residence by the tenant. The number of occupants must not go over the maximum stated above.</li>
            <li>The tenant keeps the room clean and in good condition and pays for damage caused by the tenant or their guests, except normal wear and tear.</li>
            <li>Repairs are requested through the Maintenance section of RSYNC. The landlord attends to them within a reasonable time.</li>
            <li>The tenant may not sublet the room or transfer this agreement without the landlord's written consent.</li>
            <li>Either party who wants to end the agreement before the end date gives at least 30 days' written notice.</li>
            <li>On leaving, the tenant returns all keys and leaves the room in the condition it was received.</li>
        </ol>
    </section>

    <section>
        <h2>4. House rules</h2>
        <ol class="rules">
            <li>Quiet hours are from 10:00 PM to 6:00 AM. Be considerate of other boarders.</li>
            <li>Visitors are received only in common areas and must leave by 9:00 PM unless the landlord agrees otherwise.</li>
            <li>Smoking, illegal drugs, gambling and weapons are not allowed on the premises.</li>
            <li>Cooking is allowed only in designated areas. Unplug appliances when not in use and follow fire safety rules.</li>
            <li>Save water and electricity. Report leaks and electrical problems right away.</li>
            <li>Throw away garbage properly and follow the barangay's waste segregation schedule.</li>
        </ol>
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

    <footer class="doc-foot">Generated by RSYNC from contract {{ $code }}. This is a general template; check it against your own agreement and local rules before signing.</footer>
</main>
@endsection

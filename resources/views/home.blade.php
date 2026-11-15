@extends('layouts.public', ['css' => 'resources/css/home.css', 'js' => 'resources/js/home.js'])

@section('title', 'RSYNC — Find Your Room')

@if ($hasPins)
@push('head')
@include('partials.leaflet')
@endpush
@endif

@section('fonts')
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
<link href="https://unpkg.com/boxicons@latest/css/boxicons.min.css" rel="stylesheet">
@endsection

{{-- Values the page script needs. The room list keeps its own values on the list itself (partials/room-browse). --}}
@section('body_attrs')
data-register-url="{{ route('register') }}"
@endsection

@section('content')
@include('partials.public-nav')

{{-- The map is the hero: every matching room is a price pin, with the search card floating on its left (stacked under a shorter map on phones). --}}
<div class="home-top {{ $hasPins ? 'home-top--map' : '' }}">
    <div class="search-card" data-map-cover>
        <h1>Find a room that feels like <em>home</em></h1>
        <p class="search-sub">Boarding houses and bed spaces in Baguio City.</p>
        @include('partials.room-filters', ['action' => route('home'), 'stacked' => true])
        @if ($filters->isActive())
            <p class="search-result">
                <a href="#rooms"><strong>{{ $total }} {{ $total === 1 ? 'room' : 'rooms' }}</strong> {{ $total === 1 ? 'matches' : 'match' }} <i class="bx bx-down-arrow-alt" aria-hidden="true"></i></a>
                <a href="{{ route('home') }}" class="search-clear">Clear filters</a>
            </p>
        @endif
    </div>

    @include('partials.room-browse', [
        'heading' => 'Rooms in Baguio City',
        'cards' => $cards->take(8),   // two full rows of 4 on a laptop
        'baseUrl' => route('home'),
        'moreUrl' => route('rooms.index', $filters->query()),
    ])
</div>

<!-- ═══ SUBSCRIPTION PLANS SECTION ═══ -->
<section class="section plans" id="plans">
    <div class="plan-hero">
        <div>
            <h2 class="sec-title">Landlords: simple, transparent pricing</h2>
            <p class="plan-note">List your rooms, manage tenants, and grow your boarding house business.</p>
        </div>
        <div class="billing-toggle" role="tablist" aria-label="Billing period">
            <button type="button" class="tgl active" id="btn-m" onclick="setBilling('monthly')">Monthly</button>
            <button type="button" class="tgl" id="btn-a" onclick="setBilling('annual')">Annual <span class="save-tag">Save 20%</span></button>
        </div>
        <p class="plan-trial">All plans include a 14-day free trial. No credit card required.</p>
    </div>

    <div class="plans-grid">
        <div class="plan-card plan-starter">
            <div class="plan-card-top">
               
                <div>
                    <h3 class="plan-name">Starter</h3>
                    <p class="plan-desc">A lean option for first-time landlords.</p>
                </div>
            </div>
            <div class="plan-price-row">
                <span class="plan-curr">₱</span>
                <span class="plan-amt" id="p-s">0</span>
                <span class="plan-per">/mo</span>
            </div>
            <div class="plan-orig" id="o-s">&nbsp;</div>
            <hr class="plan-divider">
            <ul class="plan-feats">
                <li class="pf"><i class='bx bx-check'></i><span><strong>1 room listing</strong></span></li>
                <li class="pf"><i class='bx bx-check'></i><span>Basic tenant management</span></li>
                <li class="pf off"><i class='bx bx-minus'></i><span>Payment tracking</span></li>
                <li class="pf off"><i class='bx bx-minus'></i><span>Email support</span></li>
                <li class="pf off"><i class='bx bx-minus'></i><span>Featured listings</span></li>
            </ul>
            <button class="plan-btn" onclick="selectPlan('Starter')">Choose Starter</button>
        </div>

        <div class="plan-card featured-plan">
            <div class="plan-card-top">
            
                <div>
                    <h3 class="plan-name">Professional</h3>
                    <p class="plan-desc">For growing portfolios and daily operations.</p>
                </div>
            </div>
            <div class="plan-price-row">
                <span class="plan-curr">₱</span>
                <span class="plan-amt" id="p-p">399</span>
                <span class="plan-per">/mo</span>
            </div>
            <div class="plan-orig" id="o-p">&nbsp;</div>
            <hr class="plan-divider">
            <ul class="plan-feats">
                <li class="pf"><i class='bx bx-check'></i><span>Up to <strong>10 room listings</strong></span></li>
                <li class="pf"><i class='bx bx-check'></i><span>Advanced tenant management</span></li>
                <li class="pf"><i class='bx bx-check'></i><span>Transaction records & reports</span></li>
                <li class="pf"><i class='bx bx-check'></i><span>Priority email & chat support</span></li>
                <li class="pf featured-feat"><i class='bx bx-star'></i><span><strong>3 featured listings</strong>/month <span class="feat-tag hot">Hot</span></span></li>
            </ul>
            <button class="plan-btn amber-btn" onclick="selectPlan('Professional')">Choose Professional</button>
        </div>

        <div class="plan-card">
            <div class="plan-card-top">
                <div>
                    <h3 class="plan-name">Enterprise</h3>
                    <p class="plan-desc">For premium landlords with large portfolios.</p>
                </div>
            </div>
            <div class="plan-price-row">
                <span class="plan-curr">₱</span>
                <span class="plan-amt" id="p-e">699</span>
                <span class="plan-per">/mo</span>
            </div>
            <div class="plan-orig" id="o-e">&nbsp;</div>
            <hr class="plan-divider">
            <ul class="plan-feats">
                <li class="pf"><i class='bx bx-check'></i><span><strong>Unlimited</strong> room listings</span></li>
                <li class="pf"><i class='bx bx-check'></i><span>Full tenant management suite</span></li>
                <li class="pf"><i class='bx bx-check'></i><span>Advanced analytics & reports</span></li>
                <li class="pf"><i class='bx bx-check'></i><span>24/7 phone & priority support</span></li>
                <li class="pf featured-feat"><i class='bx bx-star'></i><span><strong>All listings always featured</strong></span></li>
                <li class="pf"><i class='bx bx-check'></i><span>Custom branding on listings</span></li>
            </ul>
            <button class="plan-btn" onclick="selectPlan('Enterprise')">Choose Enterprise</button>
        </div>
    </div>
</section>

<div class="why" aria-label="Why RSYNC">
    <div class="why-inner">
        <div class="why-grid">
            <div class="why-card"><div class="why-icon"><i class="bx bx-check-shield"></i></div><h3>Clean Booking System</h3><p>Simple room browsing, no confusion.</p></div>
            <div class="why-card"><div class="why-icon"><i class="bx bx-badge-check"></i></div><h3>Verified Listings</h3><p>All rooms reviewed before going live.</p></div>
            <div class="why-card"><div class="why-icon"><i class="bx bx-home-smile"></i></div><h3>Student Friendly</h3><p>Built for student lifestyle and budget.</p></div>
            <div class="why-card"><div class="why-icon"><i class="bx bx-bolt-circle"></i></div><h3>Real-time Availability</h3><p>Status updates instantly on changes.</p></div>
        </div>
    </div>
</div>

@include('partials.public-footer')
@endsection
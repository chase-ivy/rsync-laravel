@extends('layouts.public', ['css' => 'resources/css/home.css', 'js' => 'resources/js/home.js'])

@section('title', 'RSYNC — Find Your Room')

@section('fonts')
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<link href="https://unpkg.com/boxicons@latest/css/boxicons.min.css" rel="stylesheet">
@endsection

{{-- Values the page script needs (index.php printed them into its <script> with PHP). --}}
@section('body_attrs')
data-logged-in="{{ auth()->check() ? '1' : '0' }}"
data-tenant="{{ auth()->user()?->role === 'tenant' ? '1' : '0' }}"
data-search-url="{{ route('home') }}"
data-apply-url="{{ route('rooms.apply') }}"
data-room-url="{{ url('rooms') }}"
data-images-url="{{ \App\Support\RoomPhotos::baseUrl() }}"
data-register-url="{{ route('register') }}"
data-login-url="{{ route('login') }}"
data-dashboard-url="{{ route('tenant.dashboard') }}"
@endsection

@section('content')
@include('partials.public-nav')

<div class="hero">
    <div class="hero-grid"></div>
    <h1>Find a room that feels<br>like <em>home</em></h1>
    <p class="hero-sub">Simple, safe, and student-focused boarding house system in Baguio City.</p>

    <div class="search-wrap">
        <i class="bx bx-search s-icon"></i>
        <input type="text" id="si" placeholder="Search by room name, price, description..." autocomplete="off">
        <button class="s-clear" id="sc"><i class="bx bx-x"></i></button>
        <div id="sr"></div>
    </div>

    <div class="hero-stats">
        <div class="stat"><div class="stat-num">{{ $total }}</div><div class="stat-label">Total Rooms</div></div>
        <div class="stat-divider"></div>
        <div class="stat"><div class="stat-num">{{ $available }}</div><div class="stat-label">Available</div></div>
        <div class="stat-divider"></div>
        <div class="stat"><div class="stat-num">{{ $occupied }}</div><div class="stat-label">Occupied</div></div>
    </div>
</div>

<div class="section">
    <div class="sec-head">
        <div>
            <div class="sec-label">Browse</div>
            <div class="sec-title">Featured Rooms</div>
        </div>
        <a href="{{ route('rooms.index') }}" class="sec-link">View all →</a>
    </div>
    <div class="grid">
    @forelse ($featured as $r)
        @include('partials.room-card', ['r' => $r, 'withApply' => ! (auth()->user()?->role === 'tenant' && auth()->user()->activeTenancy())])
    @empty
        <p style="color:var(--ink3);grid-column:1/-1;padding:40px 0">No rooms listed yet.</p>
    @endforelse
</div>
<!-- ═══ SUBSCRIPTION PLANS SECTION ═══ -->
<div class="section" id="plans">
    <div class="plan-hero">
        <div>
            <div class="sec-label">For Landlords</div>
            <div class="sec-title" style="margin-bottom:6px;">Simple, transparent pricing</div>
            <p class="plan-note">List your rooms, manage tenants, and grow your boarding house business.</p>
        </div>
        <div class="billing-toggle" role="tablist" aria-label="Billing period">
            <button type="button" class="tgl active" id="btn-m" onclick="setBilling('monthly')">Monthly</button>
            <button type="button" class="tgl" id="btn-a" onclick="setBilling('annual')">Annual <span class="save-tag">Save 20%</span></button>
        </div>
    </div>

    <div class="plans-grid">
        <div class="plan-card plan-starter">
            <div class="plan-card-top">
                <div class="plan-icon-wrap starter"><i class='bx bx-leaf'></i></div>
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
                <div class="plan-icon-wrap pro"><i class='bx bx-store'></i></div>
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
                <div class="plan-icon-wrap ent"><i class='bx bxs-crown'></i></div>
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
    <p style="text-align:center;font-size:12px;color:var(--ink3);margin-top:20px;">All plans include a 14-day free trial. No credit card required.</p>
</div>

<div class="why">
    <div class="why-inner">
        <h2>Built for easier and convenient way to find your next boarding house!</h2>
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
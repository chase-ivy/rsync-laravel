@extends('layouts.public', ['css' => 'resources/css/room-detail.css', 'js' => 'resources/js/room-detail.js'])

@section('title'){{ $room->room_name }} · RSYNC @endsection

@section('fonts')
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
@endsection

{{-- Values the page script needs (room_detail.php printed them into its <script> with PHP). --}}
@section('body_attrs')
data-images='@json($images->map(fn ($img) => asset("images/" . $img->filename))->values())'
data-apply-url="{{ route('rooms.apply') }}"
data-dashboard-url="{{ route('tenant.dashboard') }}"
@endsection

@section('content')
@php $cover = $images->isNotEmpty() ? asset('images/' . $images->first()->filename) : null; @endphp

<nav>
    <div class="logo">RSYNC</div>
    <div class="nav-links">
        <a href="{{ route('home') }}">Home</a>
        <a href="{{ route('rooms.index') }}">Rooms</a>
        <a href="{{ route('login') }}">Login</a>
    </div>
</nav>

<div class="wrap">
    <a href="{{ route('rooms.index') }}" class="back">← Back to Rooms</a>

    <div class="layout">

        <!-- LEFT: GALLERY -->
        <div>
            <div class="gallery-main" id="mainImg">
                @if ($cover)
                    <img src="{{ $cover }}" id="mainImgEl" alt="{{ $room->room_name }}" onclick="openLightbox(0)" onerror="this.parentElement.innerHTML='<div class=\'no-img\'>No image available</div>'">
                @else
                    <div class="no-img">No images uploaded</div>
                @endif
            </div>

            @if ($images->count() > 1)
            <div class="gallery-thumbs" id="thumbs">
                @foreach ($images as $i => $img)
                    <img src="{{ asset('images/' . $img->filename) }}"
                         class="{{ $i === 0 ? 'active' : '' }}"
                         onclick="switchImg(this, this.src, {{ $i }})"
                         onerror="this.style.display='none'">
                @endforeach
            </div>
            @endif
        </div>

        <!-- RIGHT: INFO -->
        <div>
            <div class="info-card">
                <div class="room-title">{{ $room->room_name }}</div>
                <div class="room-price">₱{{ number_format($room->price) }}<span> / month</span></div>

                <div class="divider"></div>

                <div class="info-row">
                    <span class="label">Status</span>
                    <span class="badge {{ $room->status === 'Available' ? 'badge-av' : 'badge-oc' }}">{{ $room->status }}</span>
                </div>

                <div class="slots-block">
                    <div class="slots-label">Occupants: <strong>{{ $current }} / {{ $max }}</strong></div>
                    <div class="slots-bar">
                        <div class="slots-fill {{ $full ? 'full' : '' }}" style="width:{{ $pct }}%"></div>
                    </div>
                </div>

                <div class="info-row">
                    <span class="label">Max occupants</span>
                    <span class="val">{{ $max }} person{{ $max > 1 ? 's' : '' }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Slots available</span>
                    <span class="val" style="color:{{ $full ? 'var(--red)' : 'var(--green)' }}">{{ max(0, $max - $current) }} open</span>
                </div>

                @if (!empty($room->description))
                <div class="desc-block">
                    <h4>About this room</h4>
                    <p>{!! nl2br(e($room->description)) !!}</p>
                </div>
                @endif

                @if (auth()->user()?->role === 'tenant')
                    @if (!$full)
                        <button class="cta-btn" onclick="applyForRoom({{ $room->room_id }}, this.dataset.name)" data-name="{{ $room->room_name }}">Apply Now</button>
                    @else
                        <button class="cta-btn" style="background:#9ca3af;cursor:not-allowed" disabled>Room Full</button>
                    @endif
                @else
                    <a href="{{ route('login') }}" class="cta-btn">Login to Apply</a>
                @endif
            </div>
        </div>

    </div>
</div>

<!-- LIGHTBOX -->
<div class="lightbox" id="lightbox">
    <button class="lightbox-close" onclick="closeLightbox()">✕</button>
    <button class="lightbox-prev"  onclick="lbNav(-1)">&#8249;</button>
    <img id="lbImg" src="" alt="">
    <button class="lightbox-next"  onclick="lbNav(1)">&#8250;</button>
</div>

<footer>© 2026 RSYNC · Baguio City</footer>
@endsection
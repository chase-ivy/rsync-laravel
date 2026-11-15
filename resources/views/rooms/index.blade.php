@extends('layouts.public', ['css' => 'resources/css/rooms.css', 'js' => 'resources/js/rooms.js'])

@section('title', 'Rooms · RSYNC')

@if ($hasPins)
@push('head')
@include('partials.leaflet')
@endpush
@endif

@section('fonts')
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
<link href="https://unpkg.com/boxicons@latest/css/boxicons.min.css" rel="stylesheet">
@endsection

@section('content')
@include('partials.public-nav')

<header class="page-hero">
    <div class="page-hero-inner">
        <div class="page-hero-text">
            <h1>All Rooms</h1>
            <p>Boarding houses and bed spaces in Baguio City</p>
        </div>
        @include('partials.room-filters', ['action' => route('rooms.index')])
    </div>
</header>

@include('partials.room-browse', [
    'cards' => $rooms,
    'baseUrl' => route('rooms.index'),
])

@include('partials.public-footer')
@endsection

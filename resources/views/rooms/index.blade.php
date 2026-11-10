@extends('layouts.public', ['css' => 'resources/css/rooms.css', 'js' => 'resources/js/rooms.js'])

@section('title', 'Rooms · RSYNC')

@section('fonts')
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<link href="https://unpkg.com/boxicons@latest/css/boxicons.min.css" rel="stylesheet">
@endsection

@section('content')
@include('partials.public-nav')

<div class="page-hero">
    <h1>All Rooms</h1>
    <p>Boarding houses available in Baguio City</p>
    <div class="search-bar">
        <i class="bx bx-search"></i>
        <input type="text" id="q" placeholder="Search rooms…" value="{{ $search }}">
    </div>
</div>

<main>
<div class="grid">
@forelse ($rooms as $r)
    @include('partials.room-card', ['r' => $r, 'withApply' => false])
@empty
    <div class="empty">No rooms found.</div>
@endforelse
</div>
</main>

@include('partials.public-footer')
@endsection
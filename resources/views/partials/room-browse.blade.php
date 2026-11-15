{{--
    Room results: cards on the left, a map that stays in view on the right (home page and /rooms).
    On phones and tablets a List / Map switch shows one or the other.

    $cards    = the rooms to show as cards        $total   = how many rooms match the filters
    $filters  = App\Support\RoomFilters           $mapRooms = pins (RoomFilters::mapPins)
    $hasPins  = any listed room has a pin (no pins at all = no map)
    $baseUrl  = this page without filters ("Clear filters")
    $heading  = optional title;  $moreUrl = optional "See all" link when there are more rooms than cards
--}}
@php
    $user = auth()->user();
    // A tenant who already rents a room can't apply for another; landlords and admins don't apply at all.
    $canApply = ! $user || ($user->role === 'tenant' && ! $user->activeTenancy());
    $chips = $filters->chips();
    $noun = fn (int $n) => $n === 1 ? 'room' : 'rooms';
@endphp
<section class="browse {{ $hasPins ? 'has-map' : 'no-map' }}" id="rooms" data-browse data-view="list"
    data-logged-in="{{ $user ? '1' : '0' }}"
    data-tenant="{{ $user?->role === 'tenant' ? '1' : '0' }}"
    data-can-apply="{{ $canApply ? '1' : '0' }}"
    data-apply-url="{{ route('rooms.apply') }}"
    data-login-url="{{ route('login') }}"
    data-dashboard-url="{{ route('tenant.dashboard') }}"
    data-room-url="{{ url('rooms') }}">

    <div class="browse-head">
        <div class="browse-sum">
            @isset($heading)<h2 class="browse-title">{{ $heading }}</h2>@endisset
            <p class="browse-count">
                <strong>{{ $total }} {{ $noun($total) }}</strong> {{ $filters->isActive() ? 'match your search' : 'with a free slot' }}@if ($cards->count() < $total), showing {{ $cards->count() }}@endif
            </p>
        </div>

        @if ($chips)
            <ul class="chips" aria-label="Active filters">
                @foreach ($chips as [$label, $without])
                    <li><a class="chip-x" href="{{ $baseUrl . ($without ? '?' . http_build_query($without) : '') }}" aria-label="Remove filter {{ $label }}">{{ $label }} <i class="bx bx-x" aria-hidden="true"></i></a></li>
                @endforeach
                <li><a class="chips-clear" href="{{ $baseUrl }}">Clear filters</a></li>
            </ul>
        @endif

        @if ($hasPins)
            <div class="view-toggle" role="group" aria-label="Show rooms as">
                <button type="button" data-view-btn="list" aria-pressed="true"><i class="bx bx-list-ul" aria-hidden="true"></i> List</button>
                <button type="button" data-view-btn="map" aria-pressed="false"><i class="bx bx-map-alt" aria-hidden="true"></i> Map</button>
            </div>
        @endif
    </div>

    <div class="browse-body">
        <div class="browse-list">
            @if ($cards->isEmpty())
                <div class="empty">
                    @if ($filters->isActive())
                        <h3>No rooms found.</h3>
                        <p>Nothing matches all of your filters. Try a higher budget, “Any location” or “Any room type”.</p>
                        <a class="empty-btn" href="{{ $baseUrl }}">Clear filters and see all rooms</a>
                    @else
                        <h3>No rooms listed yet.</h3>
                        <p>Please check again soon. New rooms appear here as soon as landlords list them.</p>
                    @endif
                </div>
            @else
                <div class="grid">
                    @foreach ($cards as $r)
                        @include('partials.room-card', ['r' => $r, 'withApply' => $canApply])
                    @endforeach
                </div>
                @if (isset($moreUrl) && $cards->count() < $total)
                    <a class="more-link" href="{{ $moreUrl }}">See all {{ $total }} {{ $noun($total) }} <i class="bx bx-right-arrow-alt" aria-hidden="true"></i></a>
                @endif
            @endif
        </div>

        @if ($hasPins)
            <div class="browse-map" id="map">
                <div id="rooms-map" data-rooms='@json($mapRooms)' role="region" aria-label="Map of the rooms"></div>
                @if ($mapRooms->isEmpty() && $total > 0)
                    <p class="map-note">None of these rooms has a map pin yet.</p>
                @endif
            </div>
        @endif
    </div>
</section>

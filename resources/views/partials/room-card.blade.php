{{--
    One room card (home page and rooms page, see partials/room-browse).
    $r = a Room loaded with withListingInfo(); $withApply = show the Apply button.
    The number of occupants is not shown on purpose: renters pick a room type with the filters instead.
--}}
@php
    $cover = $r->cover_image ? \App\Support\RoomPhotos::url($r->cover_image) : null;
    $url = route('rooms.show', $r->room_id);
@endphp
<article class="card" data-room-id="{{ $r->room_id }}">
    <a class="c-img" href="{{ $url }}" tabindex="-1" aria-hidden="true">
        @if ($cover)
            <img src="{{ $cover }}" alt="" loading="lazy" onerror="this.outerHTML='<span class=\'no-img\'>No photo yet</span>'">
        @else<span class="no-img">No photo yet</span>@endif
        <span class="chip {{ strtolower((string) $r->status) }}">{{ $r->status }}</span>
    </a>
    <div class="c-body">
        <h3 class="c-name"><a href="{{ $url }}">{{ $r->room_name }}</a></h3>
        @if ($r->address)<div class="c-addr"><i class="bx bx-map" aria-hidden="true"></i> {{ $r->address }}</div>@endif
        <div class="c-meta">
            <span class="c-type">{{ \App\Support\RoomFilters::typeWord($r) }}</span>
            @if ($r->hasLocation())
                <a class="c-maplink" href="#map" data-show-on-map="{{ $r->room_id }}"><i class="bx bx-map-pin" aria-hidden="true"></i> Show on map</a>
            @endif
        </div>
        <p class="c-desc">{{ $r->description ?? '' }}</p>
        <div class="c-foot">
            <div class="c-price">₱{{ number_format($r->price) }}<span> / {{ $r->isWholeRoomPrice() ? 'room' : 'person' }}</span>
                @if ($r->isWholeRoomPrice())<div class="c-split">{{ $r->splitNote() }}</div>@endif
            </div>
            <div class="c-actions">
                <a href="{{ $url }}" class="view-btn">View</a>
                @if ($withApply)
                    <button type="button" class="view-btn apply-btn" data-apply="{{ $r->room_id }}" data-name="{{ $r->room_name }}">Apply</button>
                @endif
            </div>
        </div>
    </div>
</article>

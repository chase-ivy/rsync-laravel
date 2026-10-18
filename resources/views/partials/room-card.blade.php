{{--
    One room card (home page and rooms page).
    $r = a Room loaded with withListingInfo(); $withApply = show the Apply button (home page only).
--}}
@php
    $max = (int) ($r->max_occupants ?? 1);
    $cur = (int) ($r->current_occupants ?? 0);
    $pct = $max > 0 ? round($cur / $max * 100) : 0;
    $cover = $r->cover_image ? \App\Support\RoomPhotos::url($r->cover_image) : null;
@endphp
<div class="card" data-n="{{ mb_strtolower($r->room_name) }}" data-s="{{ strtolower((string) $r->status) }}" data-p="{{ $r->price }}">
    <div class="c-img">
        @if ($cover)
            <img src="{{ $cover }}" alt="{{ $r->room_name }}" onerror="this.parentElement.innerHTML='<div class=\'no-img\'>No image</div>'">
        @else<div class="no-img">No image</div>@endif
        <span class="chip {{ strtolower((string) $r->status) }}">{{ $r->status }}</span>
    </div>
    <div class="c-body">
        <div class="c-name">{{ $r->room_name }}</div>
        <div class="occ">Occupants: <strong>{{ $cur }}/{{ $max }}</strong>
            <div class="occ-bar"><div class="occ-fill {{ $cur >= $max ? 'full' : '' }}" style="width:{{ $pct }}%"></div></div>
        </div>
        <p class="c-desc">{{ $r->description ?? '' }}</p>
        <div class="c-foot">
            <div class="c-price">₱{{ number_format($r->price) }}<span>/mo</span></div>
            @if ($withApply)
                <div style="display:flex;gap:6px;">
                    <a href="{{ route('rooms.show', $r->room_id) }}" class="view-btn">View <i class="bx bx-right-arrow-alt"></i></a>
                    <button onclick="applyForRoom({{ $r->room_id }}, this.dataset.name)" data-name="{{ $r->room_name }}" class="view-btn" style="background:var(--amber);border:none;cursor:pointer;">Apply</button>
                </div>
            @else
                <a href="{{ route('rooms.show', $r->room_id) }}" class="view-btn">View <i class="bx bx-right-arrow-alt"></i></a>
            @endif
        </div>
    </div>
</div>
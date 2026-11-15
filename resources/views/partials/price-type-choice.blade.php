{{--
    "Price is: per person / for the whole room" for the Add / Edit Room windows.
    $prefix = "add" or "edit" (keeps the ids unique). Add Room starts on "per person"; Edit Room is set by the page script.
--}}
<div class="field">
    <label>Price is</label>
    <div class="price-type-grid">
        @foreach (\App\Models\Room::PRICE_TYPES as $key => $label)
            <label class="amenity-check" for="{{ $prefix }}_pt_{{ $key }}">
                <input type="radio" name="price_type" value="{{ $key }}" id="{{ $prefix }}_pt_{{ $key }}" required @checked($prefix === 'add' && $key === \App\Models\Room::DEFAULT_PRICE_TYPE)>
                {{ $label }}
            </label>
        @endforeach
    </div>
    <p class="t-sub">"Whole room" prices are split by the number of max occupants on each tenant's contract.</p>
</div>

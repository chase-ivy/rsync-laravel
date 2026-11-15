{{--
    Amenities checklist for the Add / Edit Room windows.
    $prefix = "add" or "edit" (keeps the checkbox ids unique); $checked = keys to tick (Add Room: none).
--}}
<div class="field">
    <label>Amenities <span style="text-transform:none;font-weight:400">(tick all that apply)</span></label>
    <div class="amenity-grid" data-amenities-for="{{ $prefix }}">
        @foreach (\App\Models\Room::AMENITIES as $key => [$label, $icon])
            <label class="amenity-check" for="{{ $prefix }}_am_{{ $key }}">
                <input type="checkbox" name="amenities[]" value="{{ $key }}" id="{{ $prefix }}_am_{{ $key }}" @checked(in_array($key, $checked ?? [], true))>
                <i class="bx {{ $icon }}"></i> {{ $label }}
            </label>
        @endforeach
    </div>
</div>

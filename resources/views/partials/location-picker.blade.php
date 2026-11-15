{{-- Pin picker used in the Add Room and Edit Room windows. resources/js/maps.js (createPicker) fills it in. --}}
<div class="field loc-picker" data-picker>
    <label>Location on map — click to drop a pin <span style="text-transform:none;font-weight:400">(optional)</span></label>
    <div class="pick-search">
        <input type="text" class="pick-q" placeholder="Search a place, e.g. Session Road, Baguio" autocomplete="off">
        <button type="button" class="btn btn-ghost btn-sm pick-go">Search</button>
    </div>
    <div class="pick-map"></div>
    <input type="hidden" name="latitude" class="pick-lat">
    <input type="hidden" name="longitude" class="pick-lng">
    <div class="pick-foot">
        <span class="pick-hint">No pin set — this room won't appear on the map.</span>
        <span class="pick-actions">
            <button type="button" class="btn btn-ghost btn-sm pick-locate">Use my location</button>
            <button type="button" class="btn btn-ghost btn-sm pick-clear">Remove pin</button>
        </span>
    </div>
</div>

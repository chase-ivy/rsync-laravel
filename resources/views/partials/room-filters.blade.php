{{--
    Room filters (home page and /rooms). A plain GET form: works without JavaScript and the result is a link that can be shared.
    $action = where the form goes; $filters = App\Support\RoomFilters; $areas = [area => room count].
    Optional: $stacked = narrow version for the home page search card.
--}}
<form class="filters{{ ($stacked ?? false) ? ' filters--stacked' : '' }}" method="get" action="{{ $action }}" role="search" aria-label="Find a room" data-filters>
    <label class="f-field f-key">
        <span class="f-label">Keyword</span>
        <span class="f-box"><i class="bx bx-search" aria-hidden="true"></i><input type="search" name="name" value="{{ $filters->name }}" placeholder="Room name or street" maxlength="100"></span>
    </label>

    <label class="f-field">
        <span class="f-label">Location</span>
        <span class="f-box"><i class="bx bx-map" aria-hidden="true"></i><select name="area" data-autosubmit>
            <option value="">Any location</option>
            @foreach ($areas as $area => $count)
                <option value="{{ $area }}" @selected($filters->area === $area)>{{ $area }} ({{ $count }})</option>
            @endforeach
        </select></span>
    </label>

    <div class="f-field f-budget" role="group" aria-labelledby="f-budget-label">
        <span class="f-label" id="f-budget-label">Monthly budget per person</span>
        <span class="f-range">
            <span class="f-box"><span class="f-peso" aria-hidden="true">₱</span><input type="number" name="min" value="{{ $filters->min }}" placeholder="Min" min="0" step="100" inputmode="numeric" aria-label="Lowest price in pesos"></span>
            <span class="f-dash" aria-hidden="true">–</span>
            <span class="f-box"><span class="f-peso" aria-hidden="true">₱</span><input type="number" name="max" value="{{ $filters->max }}" placeholder="Max" min="0" step="100" inputmode="numeric" aria-label="Highest price in pesos"></span>
        </span>
    </div>

    <label class="f-field">
        <span class="f-label">Room type</span>
        <span class="f-box"><i class="bx bx-group" aria-hidden="true"></i><select name="type" data-autosubmit>
            <option value="">Any room type</option>
            @foreach (\App\Support\RoomFilters::TYPES as $key => [$label])
                <option value="{{ $key }}" @selected($filters->type === $key)>{{ $label }}</option>
            @endforeach
        </select></span>
    </label>

    <button type="submit" class="f-go"><i class="bx bx-search" aria-hidden="true"></i> Search</button>
</form>

{{--
    Contract terms fields, shared by the landlord's Contract page and "Edit contract".
    Needs $terms (see ContractDefaults::terms()). After a failed save, old() keeps what was typed.
--}}
@use('App\Support\ContractDefaults')
@php
    $depositValue = $terms['deposit'] !== null ? (string) $terms['deposit'] : '';
    $advanceValue = $terms['advance_months'] !== null ? (string) $terms['advance_months'] : '';
@endphp

<h2>Repairs and damages</h2>
<div class="field">
    <label for="repairs_policy">Who pays for repairs and damages?</label>
    <select id="repairs_policy" name="repairs_policy" required class="{{ $errors->has('repairs_policy') ? 'is-bad' : '' }}">
        @foreach (ContractDefaults::REPAIRS as $key => $label)
            <option value="{{ $key }}" @selected(old('repairs_policy', $terms['repairs_policy']) === $key)>{{ $label }}</option>
        @endforeach
    </select>
    @error('repairs_policy')<p class="f-err">{{ $message }}</p>@enderror
</div>

<h2>Deposit, advance and utilities</h2>
<div class="row3">
    <div class="field">
        <label for="deposit">Security deposit (₱, optional)</label>
        <input id="deposit" type="number" name="deposit" min="0" step="0.01" value="{{ old('deposit', $depositValue) }}" placeholder="e.g. 3500" class="{{ $errors->has('deposit') ? 'is-bad' : '' }}">
        @error('deposit')<p class="f-err">{{ $message }}</p>@enderror
    </div>
    <div class="field">
        <label for="advance_months">Months paid in advance (optional)</label>
        <input id="advance_months" type="number" name="advance_months" min="0" max="12" step="1" value="{{ old('advance_months', $advanceValue) }}" placeholder="e.g. 1" class="{{ $errors->has('advance_months') ? 'is-bad' : '' }}">
        @error('advance_months')<p class="f-err">{{ $message }}</p>@enderror
    </div>
</div>
<div class="field">
    <label for="utilities_note">Utilities note (optional)</label>
    <input id="utilities_note" type="text" name="utilities_note" maxlength="255" value="{{ old('utilities_note', $terms['utilities_note']) }}" placeholder="e.g. Electricity is sub-metered and paid monthly." class="{{ $errors->has('utilities_note') ? 'is-bad' : '' }}">
    @error('utilities_note')<p class="f-err">{{ $message }}</p>@enderror
</div>

<h2>Terms and conditions</h2>
<div class="field">
    <label for="terms">One item per line</label>
    <textarea id="terms" name="terms" maxlength="{{ ContractDefaults::MAX_CHARS }}" class="{{ $errors->has('terms') ? 'is-bad' : '' }}">{{ old('terms', ContractDefaults::toText($terms['terms'])) }}</textarea>
    <p class="c-hint">Up to {{ ContractDefaults::MAX_LINES }} lines. Blank lines are skipped. Each line becomes a numbered item on the contract.</p>
    @error('terms')<p class="f-err">{{ $message }}</p>@enderror
</div>

<h2>House rules</h2>
<div class="field">
    <label for="house_rules">One rule per line</label>
    <textarea id="house_rules" name="house_rules" maxlength="{{ ContractDefaults::MAX_CHARS }}" class="{{ $errors->has('house_rules') ? 'is-bad' : '' }}">{{ old('house_rules', ContractDefaults::toText($terms['house_rules'])) }}</textarea>
    <p class="c-hint">Up to {{ ContractDefaults::MAX_LINES }} lines, for example quiet hours, visitors, curfew and cooking.</p>
    @error('house_rules')<p class="f-err">{{ $message }}</p>@enderror
</div>

@php
    /** @var \App\Models\ServiceFeeConfig|null $config */
    $pct = fn (?int $bps, float $fallback) => $bps === null ? $fallback : rtrim(rtrim(number_format($bps / 100, 2, '.', ''), '0'), '.');
@endphp

<div class="fee-grid c2">
    <div class="fee-field">
        <label for="name-{{ $config?->id ?? 'new' }}">Name</label>
        <input id="name-{{ $config?->id ?? 'new' }}" name="name" type="text"
               value="{{ old('name', $config?->name) }}" required
               placeholder="e.g. Platform default">
    </div>
    <div class="fee-field">
        <label for="structure-{{ $config?->id ?? 'new' }}">Default structure</label>
        <select id="structure-{{ $config?->id ?? 'new' }}" name="fee_structure" required>
            @foreach ($structures as $value => $label)
                <option value="{{ $value }}" @selected(old('fee_structure', $config?->fee_structure?->value) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</div>

<div class="fee-grid c2">
    <div class="fee-field">
        <label for="scope-{{ $config?->id ?? 'new' }}">Applies to</label>
        <select id="scope-{{ $config?->id ?? 'new' }}" name="scope_type" required>
            <option value="default" @selected(old('scope_type', $config?->scope_type) === 'default')>Platform default</option>
            <option value="listing_source" @selected(old('scope_type', $config?->scope_type) === 'listing_source')>Listing source</option>
            <option value="host" @selected(old('scope_type', $config?->scope_type) === 'host')>A specific host</option>
            <option value="property" @selected(old('scope_type', $config?->scope_type) === 'property')>A specific property</option>
        </select>
    </div>
    <div class="fee-field">
        <label for="scopeval-{{ $config?->id ?? 'new' }}">Scope value</label>
        <input id="scopeval-{{ $config?->id ?? 'new' }}" name="scope_value" type="text"
               value="{{ old('scope_value', $config?->scope_value) }}"
               placeholder="host or managed · user ID · property ID">
        <div class="fee-hint">Leave blank for the platform default.</div>
    </div>
</div>

<div class="fee-grid c3">
    <div class="fee-field">
        <label for="sh-{{ $config?->id ?? 'new' }}">Split-Fee · host %</label>
        <input id="sh-{{ $config?->id ?? 'new' }}" name="split_host_pct" type="number"
               step="0.1" min="0" max="50" required
               value="{{ old('split_host_pct', $pct($config?->split_host_bps, 3)) }}">
    </div>
    <div class="fee-field">
        <label for="sg-{{ $config?->id ?? 'new' }}">Split-Fee · guest %</label>
        <input id="sg-{{ $config?->id ?? 'new' }}" name="split_guest_pct" type="number"
               step="0.1" min="14.1" max="16.5" required
               value="{{ old('split_guest_pct', $pct($config?->split_guest_bps, 15)) }}">
        <div class="fee-hint">Must be 14.1%–16.5%.</div>
    </div>
    <div class="fee-field">
        <label for="oh-{{ $config?->id ?? 'new' }}">Single-Fee · host %</label>
        <input id="oh-{{ $config?->id ?? 'new' }}" name="single_host_pct" type="number"
               step="0.1" min="0" max="50" required
               value="{{ old('single_host_pct', $pct($config?->single_host_bps, 15.5)) }}">
        <div class="fee-hint">Guest pays 0% under Single-Fee.</div>
    </div>
</div>

<div class="fee-grid c3">
    <div class="fee-field">
        <label for="from-{{ $config?->id ?? 'new' }}">Effective from</label>
        <input id="from-{{ $config?->id ?? 'new' }}" name="effective_from" type="datetime-local"
               value="{{ old('effective_from', et($config?->effective_from, 'Y-m-d\TH:i')) }}">
    </div>
    <div class="fee-field">
        <label for="to-{{ $config?->id ?? 'new' }}">Effective to</label>
        <input id="to-{{ $config?->id ?? 'new' }}" name="effective_to" type="datetime-local"
               value="{{ old('effective_to', et($config?->effective_to, 'Y-m-d\TH:i')) }}">
        <div class="fee-hint">Blank = open-ended.</div>
    </div>
    <div class="fee-field">
        <label for="active-{{ $config?->id ?? 'new' }}">Status</label>
        <select id="active-{{ $config?->id ?? 'new' }}" name="active">
            <option value="1" @selected(old('active', $config?->active ?? true))>Active</option>
            <option value="0" @selected(! old('active', $config?->active ?? true))>Inactive</option>
        </select>
    </div>
</div>

<div class="fee-field">
    <label for="notes-{{ $config?->id ?? 'new' }}">Notes</label>
    <input id="notes-{{ $config?->id ?? 'new' }}" name="notes" type="text"
           value="{{ old('notes', $config?->notes) }}" placeholder="Why this configuration exists">
</div>

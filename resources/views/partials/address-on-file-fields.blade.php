{{-- Member address on file. Separate from any IP location, which is only ever
     compared against it. Expects $user (nullable). --}}
@php
    $lbl = 'display:block;font-size:12px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--muted);margin-bottom:6px;';
    $inp = 'width:100%;padding:11px 14px;border:1px solid var(--line);border-radius:10px;font-size:15px;background:#fff;outline:none;';
    $err = 'color:#b91c1c;font-size:12.5px;margin-top:4px;';
@endphp

<fieldset style="border:1px solid var(--line);border-radius:12px;padding:16px;margin:0;display:grid;gap:12px;">
    <legend style="padding:0 6px;font-weight:600;">Address on file</legend>

    <div>
        <label for="address_line1" style="{{ $lbl }}">Street address</label>
        <input id="address_line1" name="address_line1" type="text" maxlength="255" autocomplete="address-line1"
               value="{{ old('address_line1', $user?->address_line1) }}" style="{{ $inp }}">
        @error('address_line1') <div style="{{ $err }}">{{ $message }}</div> @enderror
    </div>

    <div>
        <label for="address_line2" style="{{ $lbl }}">Address line 2</label>
        <input id="address_line2" name="address_line2" type="text" maxlength="255" autocomplete="address-line2"
               value="{{ old('address_line2', $user?->address_line2) }}" style="{{ $inp }}">
        @error('address_line2') <div style="{{ $err }}">{{ $message }}</div> @enderror
    </div>

    <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));">
        <div>
            <label for="address_city" style="{{ $lbl }}">City</label>
            <input id="address_city" name="address_city" type="text" maxlength="128" autocomplete="address-level2"
                   value="{{ old('address_city', $user?->address_city) }}" style="{{ $inp }}">
            @error('address_city') <div style="{{ $err }}">{{ $message }}</div> @enderror
        </div>
        <div>
            <label for="address_state" style="{{ $lbl }}">State</label>
            <input id="address_state" name="address_state" type="text" maxlength="64" autocomplete="address-level1"
                   value="{{ old('address_state', $user?->address_state) }}" placeholder="FL" style="{{ $inp }}">
            @error('address_state') <div style="{{ $err }}">{{ $message }}</div> @enderror
        </div>
        <div>
            <label for="address_postal_code" style="{{ $lbl }}">ZIP code</label>
            <input id="address_postal_code" name="address_postal_code" type="text" maxlength="20" autocomplete="postal-code"
                   value="{{ old('address_postal_code', $user?->address_postal_code) }}" style="{{ $inp }}">
            @error('address_postal_code') <div style="{{ $err }}">{{ $message }}</div> @enderror
        </div>
        <div>
            <label for="address_country" style="{{ $lbl }}">Country</label>
            <input id="address_country" name="address_country" type="text" maxlength="2" autocomplete="country"
                   value="{{ old('address_country', $user?->address_country ?? 'US') }}" placeholder="US" style="{{ $inp }}">
            @error('address_country') <div style="{{ $err }}">{{ $message }}</div> @enderror
        </div>
    </div>
</fieldset>

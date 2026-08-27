@extends('dashboard.layout')

@section('eyebrow', 'Admin')
@section('title', 'Create a listing')

@section('content')
<div class="vyt-card-header" style="padding:0 0 18px;">
    <h3>Create a listing</h3>
    <span class="vyt-section-meta">On behalf of an owner</span>
</div>

<form method="POST" action="{{ route('admin.properties.store') }}">
    @csrf

    {{-- Whose listing ------------------------------------------------- --}}
    <div class="vyt-card" style="padding:22px;margin-bottom:18px;">
        <h4 style="margin:0 0 14px;font-size:16px;">Who owns it</h4>

        <div class="site-field">
            <label style="display:flex;align-items:center;gap:8px;text-transform:none;letter-spacing:0;font-size:14px;">
                <input type="radio" name="owner_mode" value="existing" id="mode-existing"
                       @checked(old('owner_mode', 'existing') === 'existing')>
                An existing account
            </label>
        </div>

        <div class="site-field" id="existing-fields">
            <label for="host_id">Account</label>
            <select id="host_id" name="host_id">
                <option value="">Choose an account…</option>
                @foreach ($owners as $owner)
                    <option value="{{ $owner->id }}" @selected(old('host_id') == $owner->id)>
                        {{ $owner->name }} — {{ $owner->email }}
                    </option>
                @endforeach
            </select>
            @error('host_id') <div class="err">{{ $message }}</div> @enderror
        </div>

        <div class="site-field" style="margin-top:18px;">
            <label style="display:flex;align-items:center;gap:8px;text-transform:none;letter-spacing:0;font-size:14px;">
                <input type="radio" name="owner_mode" value="new" id="mode-new"
                       @checked(old('owner_mode') === 'new')>
                Somebody with no account yet — create one
            </label>
        </div>

        <div id="new-fields" hidden>
            <div class="site-row-2">
                <div class="site-field">
                    <label for="owner_first_name">First name</label>
                    <input id="owner_first_name" name="owner_first_name" type="text" value="{{ old('owner_first_name') }}">
                    @error('owner_first_name') <div class="err">{{ $message }}</div> @enderror
                </div>
                <div class="site-field">
                    <label for="owner_last_name">Last name</label>
                    <input id="owner_last_name" name="owner_last_name" type="text" value="{{ old('owner_last_name') }}">
                    @error('owner_last_name') <div class="err">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="site-row-2">
                <div class="site-field">
                    <label for="owner_email">Email</label>
                    <input id="owner_email" name="owner_email" type="email" value="{{ old('owner_email') }}">
                    @error('owner_email') <div class="err">{{ $message }}</div> @enderror
                </div>
                <div class="site-field">
                    <label for="owner_phone">Phone</label>
                    <input id="owner_phone" name="owner_phone" type="tel" value="{{ old('owner_phone') }}">
                </div>
            </div>
            <p class="site-note" style="margin:0;">
                A one-time password is generated and emailed to them. They must set
                their own the first time they sign in, so it stops working then.
            </p>
        </div>
    </div>

    {{-- The listing ---------------------------------------------------- --}}
    <div class="vyt-card" style="padding:22px;margin-bottom:18px;">
        <h4 style="margin:0 0 14px;font-size:16px;">The property</h4>

        <div class="site-field">
            <label for="title">Listing title</label>
            <input id="title" name="title" type="text" value="{{ old('title') }}" required>
            @error('title') <div class="err">{{ $message }}</div> @enderror
        </div>

        <div class="site-field">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="4">{{ old('description') }}</textarea>
        </div>

        <div class="site-row-2">
            <div class="site-field">
                <label for="city">City</label>
                <input id="city" name="city" type="text" value="{{ old('city') }}">
            </div>
            <div class="site-field">
                <label for="region">State / region</label>
                <input id="region" name="region" type="text" value="{{ old('region') }}">
            </div>
        </div>
        <div class="site-row-2">
            <div class="site-field">
                <label for="country">Country code</label>
                <input id="country" name="country" type="text" maxlength="2" value="{{ old('country', 'US') }}">
                @error('country') <div class="err">{{ $message }}</div> @enderror
            </div>
            <div class="site-field">
                <label for="postal_code">Postal code</label>
                <input id="postal_code" name="postal_code" type="text" value="{{ old('postal_code') }}">
            </div>
        </div>

        <div class="site-row-2">
            <div class="site-field">
                <label for="capacity">Sleeps</label>
                <input id="capacity" name="capacity" type="number" min="1" max="99" value="{{ old('capacity', 2) }}" required>
            </div>
            <div class="site-field">
                <label for="bedrooms">Bedrooms</label>
                <input id="bedrooms" name="bedrooms" type="number" min="0" max="99" value="{{ old('bedrooms', 1) }}" required>
            </div>
        </div>
        <div class="site-row-2">
            <div class="site-field">
                <label for="beds">Beds</label>
                <input id="beds" name="beds" type="number" min="0" max="99" value="{{ old('beds', 1) }}" required>
            </div>
            <div class="site-field">
                <label for="bathrooms">Bathrooms</label>
                <input id="bathrooms" name="bathrooms" type="number" step="0.5" min="0" max="99" value="{{ old('bathrooms', 1) }}" required>
            </div>
        </div>

        <div class="site-row-2">
            <div class="site-field">
                <label for="price_dollars">Price (USD)</label>
                <input id="price_dollars" name="price_dollars" type="number" step="0.01" min="0"
                       value="{{ old('price_dollars') }}" required>
                {{-- Every member program stay is 7 days / 6 nights, so a rental
                     price is the price of that stay rather than a nightly rate
                     the visitor has to multiply out. --}}
                <small class="hint">For rent, the price of the 7 day / 6 night stay. For sale, the asking price.</small>
                @error('price_dollars') <div class="err">{{ $message }}</div> @enderror
            </div>
            <div class="site-field">
                <label for="minimum_nights">Minimum nights</label>
                <input id="minimum_nights" name="minimum_nights" type="number" min="1" max="365"
                       value="{{ old('minimum_nights', 1) }}">
            </div>
        </div>
    </div>

    {{-- Publication ---------------------------------------------------- --}}
    <div class="vyt-card" style="padding:22px;margin-bottom:18px;">
        <h4 style="margin:0 0 14px;font-size:16px;">Publication</h4>

        <div class="site-row-2">
            {{-- Listing type is what the advertisement offers; status is where
                 it sits in the workflow. A For Sale listing still has to be
                 drafted, reviewed and published, so the two stay separate. --}}
            <div class="site-field">
                <label>Listing type</label>
                <div style="display:flex;gap:18px;margin-top:6px;">
                    @foreach (\App\Enums\ListingType::cases() as $type)
                        <label style="display:inline-flex;align-items:center;gap:7px;font-weight:400;">
                            <input type="radio" name="listing_type" value="{{ $type->value }}"
                                   @checked(old('listing_type', 'rent') === $type->value)>
                            {{ $type->label() }}
                        </label>
                    @endforeach
                </div>
                @error('listing_type') <div class="err">{{ $message }}</div> @enderror
            </div>

            {{-- Active is not on this list on purpose.
                 Photos attach to a listing only after it exists, so anything
                 created live is live with no pictures. Publishing happens from
                 the listing page once there is something to show. --}}
            <div class="site-field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    @foreach (\App\Http\Requests\Admin\StoreAdminPropertyRequest::CREATABLE_STATUSES as $status)
                        <option value="{{ $status }}" @selected(old('status', 'draft') === $status)>
                            {{ ucfirst(str_replace('_', ' ', $status)) }}
                        </option>
                    @endforeach
                </select>
                <p class="site-hint" style="margin:6px 0 0;font-size:12.5px;color:var(--muted);">
                    Add photos and availability next, then activate it from the listing page.
                    A listing cannot go live without at least one photo.
                </p>
                @error('status') <div class="err">{{ $message }}</div> @enderror
            </div>
            <div class="site-field">
                <label for="listing_source">Listing source</label>
                <select id="listing_source" name="listing_source">
                    <option value="host" @selected(old('listing_source', 'host') === 'host')>Host — owner manages it</option>
                    <option value="managed" @selected(old('listing_source') === 'managed')>Managed — we manage it</option>
                </select>
            </div>
        </div>

        <div class="site-field">
            <label style="display:flex;align-items:center;gap:8px;text-transform:none;letter-spacing:0;font-size:14px;">
                <input type="hidden" name="notify_owner" value="0">
                <input type="checkbox" name="notify_owner" value="1" @checked(old('notify_owner', true))>
                Email the owner that this listing exists
            </label>
            <div style="font-size:12.5px;color:var(--muted);margin-top:4px;">
                Leave this on. A listing appearing under someone's name without telling
                them is how an owner first hears about it from a stranger's inquiry.
            </div>
        </div>
    </div>

    <button type="submit" class="site-cta">Create listing</button>
    <a href="{{ route('admin.properties.index') }}" style="margin-left:14px;font-size:14px;">Cancel</a>
</form>
@endsection

@push('scripts')
<script>
(function () {
    var existing = document.getElementById('mode-existing');
    var isNew    = document.getElementById('mode-new');
    var exFields = document.getElementById('existing-fields');
    var newFields= document.getElementById('new-fields');

    function sync() {
        exFields.hidden  = !existing.checked;
        newFields.hidden = !isNew.checked;
    }

    existing.addEventListener('change', sync);
    isNew.addEventListener('change', sync);
    sync();
})();
</script>
@endpush

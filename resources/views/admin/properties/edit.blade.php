@extends('dashboard.layout')

@section('eyebrow', 'Listing builder')
@section('title', $property->title)

@push('head')
    <style>
        .vyt-builder-head {
            background:#fff; border:1px solid var(--line); border-radius:14px;
            padding:18px 22px; margin-bottom:18px;
            display:flex; flex-wrap:wrap; gap:18px; align-items:center;
        }
        .vyt-builder-head .ref {
            font-family:ui-monospace,SFMono-Regular,Menlo,monospace;
            font-size:13px; color:var(--muted);
        }
        .vyt-builder-head h2 { margin:2px 0 0; font-size:19px; }
        .vyt-builder-head .spacer { flex:1; }
        .vyt-pill {
            display:inline-block; padding:3px 10px; border-radius:999px;
            font-size:12px; font-weight:600; background:#f3f4f6; color:#374151;
        }
        .vyt-pill.is-active { background:#ecfdf5; color:#047857; }
        .vyt-pill.is-draft  { background:#fef3c7; color:#92400e; }

        .vyt-section {
            background:#fff; border:1px solid var(--line); border-radius:14px;
            padding:22px; margin-bottom:18px;
        }
        .vyt-section > h3 {
            margin:0 0 4px; font-size:16px;
            font-family:'Fraunces',serif; font-weight:600;
        }
        .vyt-section > .hint { color:var(--muted); font-size:13px; margin:0 0 18px; }

        .vyt-grid { display:grid; gap:14px; grid-template-columns:1fr; }
        @media (min-width:760px) {
            .vyt-grid.cols-2 { grid-template-columns:1fr 1fr; }
            .vyt-grid.cols-3 { grid-template-columns:1fr 1fr 1fr; }
            .vyt-grid.cols-4 { grid-template-columns:1fr 1fr 1fr 1fr; }
        }
        .vyt-field label {
            display:block; font-size:12px; font-weight:600; letter-spacing:.04em;
            text-transform:uppercase; color:var(--muted); margin-bottom:5px;
        }
        .vyt-field input[type=text], .vyt-field input[type=number],
        .vyt-field select, .vyt-field textarea {
            width:100%; padding:9px 12px; border:1px solid var(--line);
            border-radius:8px; font-size:14px; background:var(--bg);
            outline:none; font-family:inherit;
        }
        .vyt-field textarea { min-height:96px; resize:vertical; }
        .vyt-field input:focus, .vyt-field select:focus, .vyt-field textarea:focus {
            border-color:var(--magenta); background:#fff;
        }
        .vyt-field .note { font-size:12px; color:var(--muted); margin-top:4px; }
        .vyt-field .err { font-size:12.5px; color:#b91c1c; margin-top:4px; }

        .vyt-amenity-group { margin-bottom:18px; }
        .vyt-amenity-group > h4 {
            margin:0 0 8px; font-size:12px; letter-spacing:.06em;
            text-transform:uppercase; color:var(--muted);
        }
        .vyt-amenities { display:grid; gap:6px 16px; grid-template-columns:1fr; }
        @media (min-width:640px) { .vyt-amenities { grid-template-columns:1fr 1fr; } }
        @media (min-width:1000px) { .vyt-amenities { grid-template-columns:1fr 1fr 1fr; } }
        .vyt-amenities label {
            display:flex; gap:8px; align-items:center; font-size:14px;
            text-transform:none; letter-spacing:0; color:var(--ink);
            font-weight:400; margin:0; cursor:pointer;
        }
        .vyt-switch {
            display:flex; gap:10px; align-items:flex-start; padding:10px 0;
            border-top:1px solid var(--line);
        }
        .vyt-switch:first-of-type { border-top:0; }
        .vyt-switch .copy { font-size:14px; }
        .vyt-switch .copy .sub { display:block; color:var(--muted); font-size:12.5px; }

        .vyt-dropzone { position:relative; border:2px dashed var(--line); border-radius:12px; background:var(--bg); }
        .vyt-dropzone.is-over { border-color:var(--magenta); background:#fff; }
        .vyt-dropzone input[type=file] { position:absolute; inset:0; opacity:0; width:100%; height:100%; cursor:pointer; }
        .vyt-dropzone label { display:block; padding:26px 18px; text-align:center; cursor:pointer; text-transform:none; letter-spacing:0; margin:0; }
        .vyt-dropzone label strong { display:block; font-size:15px; color:var(--ink); }
        .vyt-dropzone label span { display:block; font-size:12.5px; color:var(--muted); margin-top:4px; }

        .vyt-photo-grid { display:grid; gap:12px; grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); }
        .vyt-photo-card { border:1px solid var(--line); border-radius:10px; overflow:hidden; background:#fff; cursor:grab; }
        .vyt-photo-card.is-dragging { opacity:.4; }
        .vyt-photo-thumb { position:relative; aspect-ratio:4/3; background:#f3f4f6; }
        .vyt-photo-thumb img { width:100%; height:100%; object-fit:cover; display:block; }
        .vyt-photo-missing { display:flex; align-items:center; justify-content:center; height:100%; font-size:12px; color:#b91c1c; text-align:center; padding:8px; }
        .vyt-photo-badge { position:absolute; top:8px; left:8px; background:var(--gradient); color:#fff; font-size:11px; font-weight:600; padding:3px 9px; border-radius:999px; }
        .vyt-photo-meta { padding:8px 10px; display:grid; gap:2px; font-size:11.5px; color:var(--muted); }
        .vyt-actions {
            position:sticky; bottom:0; background:#fff;
            border:1px solid var(--line); border-radius:14px;
            padding:14px 22px; display:flex; gap:12px; align-items:center;
        }
        .vyt-actions .spacer { flex:1; }
        .vyt-save {
            background:var(--gradient); color:#fff; border:0; border-radius:9px;
            padding:11px 26px; font-size:14.5px; font-weight:600; cursor:pointer;
        }
    </style>
@endpush

@section('content')
    <div class="vyt-builder-head">
        <div>
            <div class="ref">{{ $property->reference }}</div>
            <h2>{{ $property->title }}</h2>
            <div class="vyt-faint" style="font-size:13px;margin-top:4px;">
                Member: {{ $property->host?->name ?? '—' }}
                @if ($position = $property->packagePosition())
                    · {{ $position }}
                @endif
            </div>
        </div>
        <div class="spacer"></div>
        <span class="vyt-pill {{ $property->status->value === 'active' ? 'is-active' : ($property->status->value === 'draft' ? 'is-draft' : '') }}">
            {{ strtoupper(str_replace('_', ' ', $property->status->value)) }}
        </span>
    </div>

    @if ($errors->any())
        <div class="site-alert" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;margin-bottom:18px;">
            <strong>Nothing was saved.</strong> {{ $errors->count() }} field(s) need attention — see below.
        </div>
    @endif

    <form method="POST" action="{{ route('admin.properties.update', $property) }}">
        @csrf
        @method('PATCH')

        {{-- ---------------------------------------------------------------- --}}
        <div class="vyt-section">
            <h3>Property basics</h3>
            <p class="hint">What this property is, who it belongs to, and whether it is advertised.</p>

            <div class="vyt-grid cols-2">
                <div class="vyt-field">
                    <label for="title">Property title</label>
                    <input id="title" name="title" type="text" value="{{ old('title', $property->title) }}" required>
                    @error('title') <div class="err">{{ $message }}</div> @enderror
                </div>
                <div class="vyt-field">
                    <label for="resort_name">Resort / property name</label>
                    <input id="resort_name" name="resort_name" type="text" value="{{ old('resort_name', $property->resort_name) }}">
                </div>
            </div>

            <div class="vyt-grid cols-3" style="margin-top:14px;">
                <div class="vyt-field">
                    <label for="property_kind">Property type</label>
                    <select id="property_kind" name="property_kind">
                        <option value="">Not set</option>
                        @foreach ($kinds as $value => $label)
                            <option value="{{ $value }}" @selected(old('property_kind', $property->property_kind) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="vyt-field">
                    <label>Status</label>
                    {{-- Read-only here on purpose. Status is changed by the
                         actions below, which check the listing is ready first;
                         a dropdown let anyone set Active on a listing with no
                         photos and no dates. --}}
                    <div style="padding:9px 0;">
                        <span class="vyt-pill">{{ strtoupper(str_replace('_', ' ', $property->status->value)) }}</span>
                    </div>
                </div>
                <div class="vyt-field">
                    <label for="position_in_package">Position in package</label>
                    <input id="position_in_package" name="position_in_package" type="number" min="1" max="20"
                           value="{{ old('position_in_package', $property->position_in_package) }}">
                    <div class="note">
                        @if ($property->memberServiceOrder)
                            {{ $property->memberServiceOrder->package->label() }} — allowance {{ $property->memberServiceOrder->package->propertyCount() }}
                        @else
                            Not attached to a paid order.
                        @endif
                    </div>
                </div>
            </div>

            <input type="hidden" name="host_id" value="{{ old('host_id', $property->host_id) }}">
        </div>

        {{-- ---------------------------------------------------------------- --}}
        <div class="vyt-section">
            <h3>Location</h3>
            <p class="hint">The full address is for staff. What the public sees is governed by the precision setting.</p>

            <div class="vyt-field">
                <label for="address_line">Full address <span style="text-transform:none;letter-spacing:0;">(internal)</span></label>
                <input id="address_line" name="address_line" type="text" value="{{ old('address_line', $property->address_line) }}">
            </div>

            <div class="vyt-grid cols-4" style="margin-top:14px;">
                <div class="vyt-field">
                    <label for="city">City</label>
                    <input id="city" name="city" type="text" value="{{ old('city', $property->city) }}">
                </div>
                <div class="vyt-field">
                    <label for="region">State / region</label>
                    <input id="region" name="region" type="text" value="{{ old('region', $property->region) }}">
                </div>
                <div class="vyt-field">
                    <label for="country">Country</label>
                    <input id="country" name="country" type="text" maxlength="2" placeholder="US"
                           value="{{ old('country', $property->country) }}">
                    @error('country') <div class="err">{{ $message }}</div> @enderror
                </div>
                <div class="vyt-field">
                    <label for="postal_code">Postal code</label>
                    <input id="postal_code" name="postal_code" type="text" value="{{ old('postal_code', $property->postal_code) }}">
                </div>
            </div>

            <div class="vyt-grid cols-3" style="margin-top:14px;">
                <div class="vyt-field">
                    <label for="latitude">Latitude</label>
                    <input id="latitude" name="latitude" type="text" value="{{ old('latitude', $property->latitude) }}">
                    @error('latitude') <div class="err">{{ $message }}</div> @enderror
                </div>
                <div class="vyt-field">
                    <label for="longitude">Longitude</label>
                    <input id="longitude" name="longitude" type="text" value="{{ old('longitude', $property->longitude) }}">
                    @error('longitude') <div class="err">{{ $message }}</div> @enderror
                </div>
                <div class="vyt-field">
                    <label for="location_precision">Public map display</label>
                    <select id="location_precision" name="location_precision" required>
                        @foreach ($precision as $value => $label)
                            <option value="{{ $value }}" @selected(old('location_precision', $property->location_precision) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="note">A member still living at the property has a real interest in the pin not being their front door.</div>
                </div>
            </div>
        </div>

        {{-- ---------------------------------------------------------------- --}}
        <div class="vyt-section">
            <h3>Property details</h3>
            <p class="hint">What a traveler checks before making an offer.</p>

            <div class="vyt-grid cols-4">
                <div class="vyt-field">
                    <label for="bedrooms">Bedrooms</label>
                    <input id="bedrooms" name="bedrooms" type="number" min="0" value="{{ old('bedrooms', $property->bedrooms) }}">
                </div>
                <div class="vyt-field">
                    <label for="bathrooms">Bathrooms</label>
                    <input id="bathrooms" name="bathrooms" type="text" value="{{ old('bathrooms', $property->bathrooms) }}">
                </div>
                <div class="vyt-field">
                    <label for="beds">Beds</label>
                    <input id="beds" name="beds" type="number" min="0" value="{{ old('beds', $property->beds) }}">
                </div>
                <div class="vyt-field">
                    <label for="capacity">Sleeps / max guests</label>
                    <input id="capacity" name="capacity" type="number" min="1" value="{{ old('capacity', $property->capacity) }}">
                </div>
            </div>

            <div class="vyt-field" style="margin-top:14px;">
                <label for="bed_configuration">Bed configuration</label>
                <input id="bed_configuration" name="bed_configuration" type="text"
                       placeholder="1 king, 2 queens, 1 sofa bed"
                       value="{{ old('bed_configuration', $property->bed_configuration) }}">
            </div>

            <div class="vyt-grid cols-4" style="margin-top:14px;">
                <div class="vyt-field">
                    <label for="square_feet">Square footage</label>
                    <input id="square_feet" name="square_feet" type="number" min="1" value="{{ old('square_feet', $property->square_feet) }}">
                </div>
                <div class="vyt-field">
                    <label for="floor_unit">Floor / unit</label>
                    <input id="floor_unit" name="floor_unit" type="text" value="{{ old('floor_unit', $property->floor_unit) }}">
                </div>
                <div class="vyt-field">
                    <label for="unit_size_type">Unit size / type</label>
                    <input id="unit_size_type" name="unit_size_type" type="text"
                           placeholder="2-bedroom lockoff" value="{{ old('unit_size_type', $property->unit_size_type) }}">
                </div>
                <div class="vyt-field">
                    <label for="view_type">View</label>
                    <input id="view_type" name="view_type" type="text" list="vyt-views"
                           value="{{ old('view_type', $property->view_type) }}">
                    <datalist id="vyt-views">
                        @foreach ($viewTypes as $view)
                            <option value="{{ $view }}"></option>
                        @endforeach
                    </datalist>
                </div>
            </div>

            <div class="vyt-grid cols-4" style="margin-top:14px;">
                <div class="vyt-field">
                    <label for="check_in_day">Check-in day</label>
                    <input id="check_in_day" name="check_in_day" type="text" placeholder="Saturday"
                           value="{{ old('check_in_day', $property->check_in_day) }}">
                </div>
                <div class="vyt-field">
                    <label for="check_in_time">Check-in time</label>
                    <input id="check_in_time" name="check_in_time" type="text" placeholder="16:00"
                           value="{{ old('check_in_time', $property->check_in_time) }}">
                </div>
                <div class="vyt-field">
                    <label for="check_out_time">Check-out time</label>
                    <input id="check_out_time" name="check_out_time" type="text" placeholder="10:00"
                           value="{{ old('check_out_time', $property->check_out_time) }}">
                </div>
                <div class="vyt-field">
                    <label for="minimum_nights">Minimum stay (nights)</label>
                    <input id="minimum_nights" name="minimum_nights" type="number" min="1"
                           value="{{ old('minimum_nights', $property->minimum_nights) }}">
                </div>
            </div>

            <div class="vyt-grid cols-3" style="margin-top:14px;">
                <div class="vyt-field">
                    <label for="pet_policy">Pet policy</label>
                    <input id="pet_policy" name="pet_policy" type="text" value="{{ old('pet_policy', $property->pet_policy) }}">
                </div>
                <div class="vyt-field">
                    <label for="smoking_policy">Smoking policy</label>
                    <input id="smoking_policy" name="smoking_policy" type="text" value="{{ old('smoking_policy', $property->smoking_policy) }}">
                </div>
                <div class="vyt-field">
                    <label for="parking_info">Parking</label>
                    <input id="parking_info" name="parking_info" type="text" value="{{ old('parking_info', $property->parking_info) }}">
                </div>
            </div>

            <div class="vyt-field" style="margin-top:14px;">
                <label for="accessibility_notes">Accessibility</label>
                <textarea id="accessibility_notes" name="accessibility_notes">{{ old('accessibility_notes', $property->accessibility_notes) }}</textarea>
            </div>
        </div>

        {{-- ---------------------------------------------------------------- --}}
        <div class="vyt-section">
            <h3>Description</h3>
            <p class="hint">Plain text. Anything pasted from a document is stored as written and escaped on the way out.</p>

            <div class="vyt-field">
                <label for="headline">Headline</label>
                <input id="headline" name="headline" type="text"
                       placeholder="Spacious Two-Bedroom Resort Stay Near Orlando Attractions"
                       value="{{ old('headline', $property->headline) }}">
            </div>

            <div class="vyt-field" style="margin-top:14px;">
                <label for="short_description">Short description</label>
                <textarea id="short_description" name="short_description" style="min-height:64px;"
                          placeholder="One or two sentences. Used on search cards.">{{ old('short_description', $property->short_description) }}</textarea>
                @error('short_description') <div class="err">{{ $message }}</div> @enderror
            </div>

            <div class="vyt-field" style="margin-top:14px;">
                <label for="description">Full description</label>
                <textarea id="description" name="description" style="min-height:180px;">{{ old('description', $property->description) }}</textarea>
            </div>

            <div style="margin-top:18px;">
                <label style="display:block;font-size:12px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;color:var(--muted);margin-bottom:5px;">
                    Highlights
                </label>
                <div class="vyt-grid cols-2">
                    @php($existing = old('highlights', $property->highlights ?? []))
                    @for ($i = 0; $i < 8; $i++)
                        <div class="vyt-field">
                            <input name="highlights[]" type="text"
                                   value="{{ $existing[$i] ?? '' }}"
                                   placeholder="{{ $i === 0 ? 'Sleeps up to 6' : ($i === 1 ? 'Resort pool' : '') }}">
                        </div>
                    @endfor
                </div>
                <div class="note" style="font-size:12px;color:var(--muted);margin-top:6px;">
                    Blank rows are discarded.
                </div>
            </div>
        </div>

        {{-- ---------------------------------------------------------------- --}}
        <div class="vyt-section">
            <h3>Amenities</h3>
            <p class="hint">{{ $property->amenities->count() }} currently selected.</p>

            @php($selected = collect(old('amenities', $property->amenities->pluck('id')->all()))->map(fn ($id) => (int) $id)->all())

            @forelse ($amenities as $category => $group)
                <div class="vyt-amenity-group">
                    <h4>{{ ucwords(str_replace(['_', '-'], ' ', $category ?: 'Other')) }}</h4>
                    <div class="vyt-amenities">
                        @foreach ($group as $amenity)
                            <label>
                                <input type="checkbox" name="amenities[]" value="{{ $amenity->id }}"
                                       @checked(in_array($amenity->id, $selected, true))>
                                <span>{{ $amenity->label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @empty
                <p class="vyt-faint">No amenities are configured yet. Add one below and it becomes available to every listing.</p>
            @endforelse

            <div class="vyt-field" style="max-width:420px;border-top:1px solid var(--line);padding-top:16px;">
                <label for="custom_amenity">Add a custom amenity</label>
                <input id="custom_amenity" name="custom_amenity" type="text" placeholder="e.g. Pickleball court">
                <div class="note">Added to the shared list, so the next listing can tick it rather than retype it.</div>
            </div>
        </div>

        {{-- ---------------------------------------------------------------- --}}
        <div class="vyt-section">
            <h3>Offer settings</h3>
            <p class="hint">What an interested traveler may do on this listing.</p>

            <label class="vyt-switch">
                <input type="checkbox" name="allow_offers" value="1" @checked(old('allow_offers', $property->allow_offers))>
                <span class="copy"><strong>Allow offers</strong>
                    <span class="sub">Show the Send an Offer button.</span></span>
            </label>
            <label class="vyt-switch">
                <input type="checkbox" name="allow_inquiries" value="1" @checked(old('allow_inquiries', $property->allow_inquiries))>
                <span class="copy"><strong>Allow inquiries</strong>
                    <span class="sub">Let someone ask a question without naming a price.</span></span>
            </label>
            <label class="vyt-switch">
                <input type="checkbox" name="display_suggested_amount" value="1" @checked(old('display_suggested_amount', $property->display_suggested_amount))>
                <span class="copy"><strong>Display a suggested amount</strong>
                    <span class="sub">Anchors the offer. Leave off if the member would rather not publish a number.</span></span>
            </label>
            <label class="vyt-switch">
                <input type="checkbox" name="require_guest_count" value="1" @checked(old('require_guest_count', $property->require_guest_count))>
                <span class="copy"><strong>Require guest count</strong></span>
            </label>
            <label class="vyt-switch">
                <input type="checkbox" name="require_message" value="1" @checked(old('require_message', $property->require_message))>
                <span class="copy"><strong>Require a message</strong>
                    <span class="sub">An offer with no context is one the member cannot judge.</span></span>
            </label>

            <div class="vyt-field" style="max-width:260px;margin-top:16px;">
                <label for="minimum_offer_dollars">Minimum offer (optional)</label>
                <input id="minimum_offer_dollars" name="minimum_offer_dollars" type="number" step="0.01" min="0"
                       value="{{ old('minimum_offer_dollars', $property->minimum_offer_cents ? number_format($property->minimum_offer_cents / 100, 2, '.', '') : '') }}">
                <div class="note">Offers below this are refused before the member sees them.</div>
            </div>
        </div>

        <div class="vyt-actions">
            <a href="{{ route('admin.properties.show', $property) }}" class="vyt-faint">← Property</a>
            <div class="spacer"></div>
            <button type="submit" class="vyt-save">Save changes</button>
        </div>
    </form>

    <div class="vyt-section" id="publishing">
        <h3>Publishing</h3>
        <p class="hint">
            Currently <strong>{{ strtoupper(str_replace('_', ' ', $property->status->value)) }}</strong>.
            Only an active listing is advertised.
        </p>

        @error('status')
            <div class="site-alert" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;">{{ $message }}</div>
        @enderror

        @if ($blockers)
            <div class="site-alert" style="background:#fffbeb;border-color:#fde68a;color:#92400e;">
                <strong>Not ready to go live.</strong>
                <ul style="margin:8px 0 0;padding-left:18px;">
                    @foreach ($blockers as $blocker)
                        <li>{{ $blocker }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px;">
            @foreach ([
                'draft'          => 'Save as draft',
                'pending_review' => 'Submit for review',
                'active'         => 'Activate listing',
                'paused'         => 'Pause listing',
            ] as $value => $label)
                @continue($property->status->value === $value)
                <form method="POST" action="{{ route('admin.properties.transition', $property) }}">
                    @csrf
                    <input type="hidden" name="to" value="{{ $value }}">
                    <button type="submit"
                            class="{{ $value === 'active' ? 'vyt-save' : '' }}"
                            style="{{ $value === 'active' ? 'padding:10px 22px;font-size:14px;' : 'padding:10px 18px;font-size:14px;border:1px solid var(--line);border-radius:9px;background:#fff;' }}"
                            @disabled($value === 'active' && $blockers)>
                        {{ $label }}
                    </button>
                </form>
            @endforeach
        </div>

        <p class="site-note" style="margin-top:12px;font-size:12.5px;color:var(--muted);">
            Activating starts what the member paid for, so it is refused until the
            listing has something to advertise.
        </p>
    </div>
    @include('admin.properties._availability')

    @include('admin.properties._photos')
@endsection

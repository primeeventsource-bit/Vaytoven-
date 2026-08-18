@extends('layouts.site')

@section('title', 'List your property or resort')
@section('meta_description', 'Tell Vaytoven about your vacation property or club resort and we will advertise it to travelers searching for stays like yours.')

@push('head')
    <style>
        /* Resort-only fields are hidden until the owner picks Resort, so the
           form stays short for the common case rather than asking a villa
           owner which club it belongs to. */
        .kind-resort-only { display: none; }
        body.is-resort .kind-resort-only { display: block; }
        body.is-resort .kind-property-only { display: none; }
        .kind-toggle { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; }
        .kind-toggle label {
            flex: 1; min-width: 190px; border: 1px solid var(--line); border-radius: 12px;
            padding: 14px 16px; cursor: pointer; background: #fff; display: block;
        }
        .kind-toggle input { margin-right: 8px; accent-color: #D63384; }
        .kind-toggle label:has(input:checked) {
            border-color: var(--magenta); box-shadow: 0 0 0 3px rgba(255, 61, 138, .14);
        }
        .kind-toggle strong { display: block; font-size: 15px; margin-bottom: 2px; }
        .kind-toggle span { font-size: 12.5px; color: var(--muted); line-height: 1.5; }
    </style>
@endpush

@section('content')
<div class="site-shell">
    <section class="site-hero">
        <p class="eyebrow">For hosts, owners and members</p>
        <h1>List your property or resort</h1>
        <p class="lede">
            Tell us what you have and our team will follow up to get it advertised. It takes a
            couple of minutes and asks for nothing sensitive.
        </p>
    </section>

    <section class="site-section">
        <div class="site-grid cols-2" style="align-items:start; gap:32px;">
            <div>
                @if (session('hosting_success'))
                    <div class="site-alert">
                        <strong>{{ session('hosting_success') }}</strong>
                        Your reference is <code>{{ session('hosting_reference') }}</code> — quote it if you follow up.
                    </div>
                @endif

                <form method="POST" action="{{ route('host.onboarding.store') }}">
                    @csrf

                    <h2 style="font-size:19px; margin:0 0 14px;">What are you listing?</h2>
                    <div class="kind-toggle">
                        <label>
                            <input type="radio" name="listing_kind" value="property"
                                   @checked(old('listing_kind', 'property') === 'property')>
                            <strong>A property</strong>
                            <span>A house, villa, condo or cabin you own or manage.</span>
                        </label>
                        <label>
                            <input type="radio" name="listing_kind" value="resort"
                                   @checked(old('listing_kind') === 'resort')>
                            <strong>A resort or club week</strong>
                            <span>Time in a vacation property you own or hold rights to.</span>
                        </label>
                    </div>

                    <h2 style="font-size:19px; margin:0 0 14px;">About you</h2>

                    <div class="site-row-2">
                        <div class="site-field">
                            <label for="first_name">First name</label>
                            <input id="first_name" name="first_name" type="text"
                                   value="{{ old('first_name', auth()->user()?->first_name) }}" required autocomplete="given-name">
                            @error('first_name') <div class="err">{{ $message }}</div> @enderror
                        </div>
                        <div class="site-field">
                            <label for="last_name">Last name</label>
                            <input id="last_name" name="last_name" type="text"
                                   value="{{ old('last_name', auth()->user()?->last_name) }}" required autocomplete="family-name">
                            @error('last_name') <div class="err">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="site-row-2">
                        <div class="site-field">
                            <label for="email">Email</label>
                            <input id="email" name="email" type="email"
                                   value="{{ old('email', auth()->user()?->email) }}" required autocomplete="email">
                            @error('email') <div class="err">{{ $message }}</div> @enderror
                        </div>
                        <div class="site-field">
                            <label for="phone">Phone <span style="text-transform:none; letter-spacing:0;">(optional)</span></label>
                            <input id="phone" name="phone" type="tel"
                                   value="{{ old('phone', auth()->user()?->phone) }}" autocomplete="tel" inputmode="tel">
                            @error('phone') <div class="err">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <h2 style="font-size:19px; margin:28px 0 6px;">About the listing</h2>
                    <p style="font-size:13px; color:var(--muted); margin:0 0 14px;">
                        Fill in what you know — we can work out the rest on the call.
                    </p>

                    <div class="kind-property-only">
                        <div class="site-row-2">
                            <div class="site-field">
                                <label for="property_name">Property name</label>
                                <input id="property_name" name="property_name" type="text"
                                       value="{{ old('property_name') }}" placeholder="e.g. Olive Grove Villa">
                                @error('property_name') <div class="err">{{ $message }}</div> @enderror
                            </div>
                            <div class="site-field">
                                <label for="property_type">Property type</label>
                                <select id="property_type" name="property_type">
                                    <option value="">Choose…</option>
                                    @foreach (['Villa or house', 'Condo or apartment', 'Cabin', 'Other'] as $type)
                                        <option value="{{ $type }}" @selected(old('property_type') === $type)>{{ $type }}</option>
                                    @endforeach
                                </select>
                                @error('property_type') <div class="err">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="kind-resort-only">
                        <div class="site-row-2">
                            <div class="site-field">
                                <label for="resort_name">Resort name</label>
                                <input id="resort_name" name="resort_name" type="text"
                                       value="{{ old('resort_name') }}" placeholder="The name your property goes by">
                                @error('resort_name') <div class="err">{{ $message }}</div> @enderror
                            </div>
                            <div class="site-field">
                                <label for="club_or_developer">Managing company <span style="text-transform:none;letter-spacing:0;">(optional)</span></label>
                                <input id="club_or_developer" name="club_or_developer" type="text"
                                       value="{{ old('club_or_developer') }}" placeholder="Who manages the property, if anyone">
                                @error('club_or_developer') <div class="err">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="site-field">
                            <label for="ownership_details">What do you hold?</label>
                            <input id="ownership_details" name="ownership_details" type="text"
                                   value="{{ old('ownership_details') }}"
                                   placeholder="e.g. Week 32 fixed, or 120,000 points a year">
                            @error('ownership_details') <div class="err">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="site-row-2">
                        <div class="site-field">
                            <label for="city">City or town</label>
                            <input id="city" name="city" type="text" value="{{ old('city') }}">
                            @error('city') <div class="err">{{ $message }}</div> @enderror
                        </div>
                        <div class="site-field">
                            <label for="region">State or region</label>
                            <input id="region" name="region" type="text" value="{{ old('region') }}">
                            @error('region') <div class="err">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="site-row-2">
                        <div class="site-field">
                            <label for="country">Country</label>
                            <input id="country" name="country" type="text" value="{{ old('country') }}">
                            @error('country') <div class="err">{{ $message }}</div> @enderror
                        </div>
                        <div class="site-field">
                            <label for="availability">Typical availability</label>
                            <input id="availability" name="availability" type="text"
                                   value="{{ old('availability') }}" placeholder="e.g. summer weeks, 12 weeks a year">
                            @error('availability') <div class="err">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="site-row-2">
                        <div class="site-field">
                            <label for="bedrooms">Bedrooms</label>
                            <input id="bedrooms" name="bedrooms" type="number" min="0" max="200" value="{{ old('bedrooms') }}">
                            @error('bedrooms') <div class="err">{{ $message }}</div> @enderror
                        </div>
                        <div class="site-field">
                            <label for="bathrooms">Bathrooms</label>
                            <input id="bathrooms" name="bathrooms" type="number" min="0" max="200" value="{{ old('bathrooms') }}">
                            @error('bathrooms') <div class="err">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="site-field">
                        <label for="indicative_nightly_dollars">Nightly rate you have in mind (USD, optional)</label>
                        <input id="indicative_nightly_dollars" name="indicative_nightly_dollars" type="number"
                               min="0" step="1" value="{{ old('indicative_nightly_dollars') }}">
                        <p style="font-size:12px; color:var(--muted); margin:6px 0 0;">
                            Indicative only. You set your own rates — Vaytoven does not price your property.
                        </p>
                        @error('indicative_nightly_dollars') <div class="err">{{ $message }}</div> @enderror
                    </div>

                    <div class="site-field">
                        <label for="message">Anything else we should know?</label>
                        <textarea id="message" name="message" style="min-height:110px;">{{ old('message') }}</textarea>
                        @error('message') <div class="err">{{ $message }}</div> @enderror
                    </div>

                    <button type="submit" class="site-cta"
                            data-track-audience="host" data-track-cta="list_property_submit">Submit details</button>
                </form>
            </div>

            <div>
                <div class="site-card">
                    <h3>What happens next</h3>
                    <p style="margin:0 0 10px;">
                        We read every submission. Someone from the team follows up to confirm the
                        details, talk through photos and copy, and agree the advertising package.
                    </p>
                    <p style="margin:0;">
                        Once your listing is live, travelers can send you offers and inquiries
                        directly, and you respond from your dashboard.
                    </p>
                </div>

                {{-- Stated plainly, because the previous version of this page
                     described a payout model Vaytoven does not operate. --}}
                <div class="site-card" style="margin-top:16px;">
                    <h3>How the money works</h3>
                    <p style="margin:0 0 10px;">
                        Vaytoven is an advertising and marketing platform. We charge for advertising,
                        listing and subscription services — that is the only thing our merchant
                        account processes.
                    </p>
                    <p style="margin:0 0 10px;">
                        We do not collect rental payments from guests, hold funds, or pay you out.
                        Any reservation, deposit, payment or refund is arranged
                        <strong>directly between you and the guest</strong>, on whatever terms you set.
                    </p>
                    <p style="margin:0;">
                        Payment is settled peer to peer, <strong>after</strong> you accept an offer
                        and the dates are confirmed as available — not before, and never through us.
                    </p>
                </div>

                <div class="site-card" style="margin-top:16px;">
                    <h3>We will never ask for</h3>
                    <p style="margin:0;">
                        Bank account or routing numbers, government ID, or tax forms. Advertising a
                        listing needs none of it. If anyone claiming to be from Vaytoven asks you for
                        those, <a href="{{ route('contact.show') }}">tell us</a>.
                    </p>
                </div>

                <div class="site-card" style="margin-top:16px;">
                    <h3>Questions first?</h3>
                    <p style="margin:0 0 10px;">
                        Read how listing works before you submit anything.
                    </p>
                    <a href="{{ route('hosts.show') }}">How hosting on Vaytoven works</a>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
(function () {
    // Toggle the resort-only fields. Progressive enhancement: with JavaScript
    // off both sets render, which is a longer form but never a broken one.
    var radios = document.querySelectorAll('input[name="listing_kind"]');

    function apply() {
        var resort = document.querySelector('input[name="listing_kind"]:checked');
        document.body.classList.toggle('is-resort', !!resort && resort.value === 'resort');
    }

    radios.forEach(function (r) { r.addEventListener('change', apply); });
    apply();
})();
</script>
@endpush

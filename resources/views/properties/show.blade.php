@extends('properties.layout')

@section('title', $property->title)

@section('content')

    <a href="{{ route('properties.index') }}" class="props-detail-back">← Back to all stays</a>

    <article class="props-detail">
        <h1>{{ $property->title }}</h1>
        <div class="props-detail-loc">
            {{ $property->city }}{{ $property->region ? ', '.$property->region : '' }}{{ $property->country ? ' · '.$property->country : '' }}
        </div>

        @if ($property->photos->isNotEmpty())
            @include('partials.photo-carousel', [
                'photos'  => $property->photos,
                'alt'     => $property->title,
                'hero'    => true,
                'loading' => 'eager',
            ])
        @endif

        <div class="props-detail-grid">
            <div>
                <div class="props-detail-stats">
                    <div class="props-detail-stat">
                        <strong>{{ $property->capacity }}</strong>
                        <span>guests</span>
                    </div>
                    <div class="props-detail-stat">
                        <strong>{{ $property->bedrooms }}</strong>
                        <span>{{ Str::plural('bedroom', $property->bedrooms) }}</span>
                    </div>
                    <div class="props-detail-stat">
                        <strong>{{ $property->beds }}</strong>
                        <span>{{ Str::plural('bed', $property->beds) }}</span>
                    </div>
                    <div class="props-detail-stat">
                        <strong>{{ rtrim(rtrim(number_format($property->bathrooms, 1), '0'), '.') }}</strong>
                        <span>{{ Str::plural('bath', $property->bathrooms) }}</span>
                    </div>
                    <div class="props-detail-stat">
                        <strong>{{ $property->minimum_nights }}</strong>
                        <span>min nights</span>
                    </div>
                </div>

                @if ($property->description)
                    <section class="props-detail-section">
                        <h2>About this stay</h2>
                        <p>{{ $property->description }}</p>
                    </section>
                @endif

                @if ($property->amenities->isNotEmpty())
                    <section class="props-detail-section">
                        <h2>What's included</h2>
                        <ul class="props-amenity-list">
                            @foreach ($property->amenities as $amenity)
                                <li>{{ $amenity->label }}</li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                <section class="props-detail-section">
                    <h2>Cancellation</h2>
                    <p>
                        @switch($property->cancellation_policy?->value)
                            @case('flexible')
                                <strong>Flexible.</strong> Full refund if you cancel at least 24 hours before check-in.
                                @break
                            @case('moderate')
                                <strong>Moderate.</strong> Full refund 5+ days before check-in. 50% between 5 days and 24 hours.
                                @break
                            @case('strict')
                                <strong>Strict.</strong> 50% refund if you cancel at least 7 days before check-in.
                                @break
                            @default
                                Cancellation policy details available on the booking page.
                        @endswitch
                        <a href="/help/cancellation-{{ $property->cancellation_policy?->value ?? 'moderate' }}">Read the policy →</a>
                    </p>
                </section>

                @if ($property->host?->name)
                    <section class="props-detail-section">
                        <h2>Hosted by</h2>
                        <p>{{ $property->host->name }}</p>
                    </section>
                @endif
            </div>

            <aside>
                <div class="props-book-card">
                    <div class="props-book-price">
                        ${{ number_format($property->base_nightly_cents / 100) }} <small>/ night</small>
                    </div>
                    @if ($property->cleaning_fee_cents > 0)
                        <div class="props-meta" style="margin-top:6px;">+ ${{ number_format($property->cleaning_fee_cents / 100) }} cleaning fee</div>
                    @endif

                    @if (session('booking_error'))
                        <div style="margin-top:14px; padding:12px 14px; background:#fef2f2; border:1px solid #fecaca; color:#b91c1c; border-radius:10px; font-size:13px;">
                            {{ session('booking_error') }}
                        </div>
                    @endif

                    <form method="GET" action="{{ route('bookings.review', $property) }}" style="margin-top:18px; display:grid; gap:10px;">
                        <div>
                            <label for="b-checkin" style="display:block; font-size:11px; letter-spacing:.08em; text-transform:uppercase; color:var(--muted); font-weight:600; margin-bottom:4px;">Check-in</label>
                            <input id="b-checkin" type="date" name="check_in" required min="{{ now()->toDateString() }}" style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:8px; font-size:14px; background:var(--bg); outline:none;">
                        </div>
                        <div>
                            <label for="b-checkout" style="display:block; font-size:11px; letter-spacing:.08em; text-transform:uppercase; color:var(--muted); font-weight:600; margin-bottom:4px;">Check-out</label>
                            <input id="b-checkout" type="date" name="check_out" required min="{{ now()->addDay()->toDateString() }}" style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:8px; font-size:14px; background:var(--bg); outline:none;">
                        </div>
                        <div>
                            <label for="b-guests" style="display:block; font-size:11px; letter-spacing:.08em; text-transform:uppercase; color:var(--muted); font-weight:600; margin-bottom:4px;">Guests</label>
                            <input id="b-guests" type="number" name="guests" min="1" max="{{ $property->capacity }}" value="2" required style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:8px; font-size:14px; background:var(--bg); outline:none;">
                        </div>
                        <button type="submit" class="props-book-cta" data-track-audience="traveler" data-track-cta="property_book_request" data-track-meta-id="{{ $property->id }}">
                            Continue to review
                        </button>
                    </form>

                    <p class="props-book-fineprint">
                        Min stay: {{ $property->minimum_nights }} {{ Str::plural('night', $property->minimum_nights) }} · Max guests: {{ $property->capacity }}
                    </p>
                </div>

                {{-- Make an offer / ask a question. Signed-in buyers only: every
                     submission is attributed to an account, and the owner
                     dashboard shows who submitted it. Owners don't see this on
                     their own listing. --}}
                @auth
                    @if ($property->host_id !== auth()->id())
                        <div class="props-card" style="margin-top:18px; padding:18px;">
                            <h3 style="margin:0 0 4px; font-size:16px;">Make an offer</h3>
                            <p style="margin:0 0 14px; font-size:12.5px; color:var(--muted); line-height:1.5;">
                                The owner has 24 hours to respond before your offer expires.
                            </p>

                            @if (session('success'))
                                <div style="margin-bottom:12px; padding:11px 13px; background:#ecfdf5; border:1px solid #a7f3d0; color:#047857; border-radius:9px; font-size:12.5px;">
                                    {{ session('success') }}
                                </div>
                            @endif
                            @if (session('error'))
                                <div style="margin-bottom:12px; padding:11px 13px; background:#fef2f2; border:1px solid #fecaca; color:#b91c1c; border-radius:9px; font-size:12.5px;">
                                    {{ session('error') }}
                                </div>
                            @endif

                            <form method="POST" action="{{ route('offers.store', $property) }}" style="display:grid; gap:10px;">
                                @csrf
                                <div>
                                    <label for="o-kind" style="display:block; font-size:11px; letter-spacing:.08em; text-transform:uppercase; color:var(--muted); font-weight:600; margin-bottom:4px;">Type</label>
                                    <select id="o-kind" name="kind" style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:8px; font-size:14px; background:var(--bg); outline:none;">
                                        <option value="offer">Offer with an amount</option>
                                        <option value="inquiry">Question, no amount</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="o-amount" style="display:block; font-size:11px; letter-spacing:.08em; text-transform:uppercase; color:var(--muted); font-weight:600; margin-bottom:4px;">Your offer (USD)</label>
                                    <input id="o-amount" type="number" name="amount_dollars" min="1" step="1"
                                           value="{{ old('amount_dollars') }}"
                                           style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:8px; font-size:14px; background:var(--bg); outline:none;">
                                    @error('amount_dollars')
                                        <span style="display:block; margin-top:4px; font-size:12px; color:#b91c1c;">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div>
                                    <label for="o-message" style="display:block; font-size:11px; letter-spacing:.08em; text-transform:uppercase; color:var(--muted); font-weight:600; margin-bottom:4px;">Message</label>
                                    <textarea id="o-message" name="message" rows="3" maxlength="2000"
                                              placeholder="Dates you have in mind, questions about the property…"
                                              style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:8px; font-size:14px; background:var(--bg); outline:none; font-family:inherit; resize:vertical;">{{ old('message') }}</textarea>
                                </div>
                                <button type="submit" class="props-book-cta"
                                        data-track-audience="traveler" data-track-cta="property_offer_submit"
                                        data-track-meta-id="{{ $property->id }}">
                                    Submit
                                </button>
                            </form>
                        </div>
                    @endif
                @endauth
            </aside>
        </div>
    </article>

    {{-- CTA tracking auto-bind for property events on this page --}}
    <script>
    (function () {
        document.querySelectorAll('[data-track-cta]').forEach(function (el) {
            el.addEventListener('click', function () {
                if (!window.Vaytoven || typeof window.Vaytoven.track !== 'function') return;
                var meta = { audience: el.dataset.trackAudience || 'traveler', cta: el.dataset.trackCta };
                for (var k in el.dataset) {
                    if (k.indexOf('trackMeta') === 0 && k.length > 'trackMeta'.length) {
                        var prop = k.slice('trackMeta'.length);
                        meta[prop.charAt(0).toLowerCase() + prop.slice(1)] = el.dataset[k];
                    }
                }
                try { window.Vaytoven.track('cta_click', meta); } catch (e) {}
            });
        });
    })();
    </script>

@endsection

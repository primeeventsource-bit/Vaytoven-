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
            <div class="props-detail-hero">
                @foreach ($property->photos->take(3) as $photo)
                    <img src="{{ $photo->url }}" alt="{{ $photo->caption ?? $property->title }}" loading="lazy">
                @endforeach
            </div>
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

                    <button type="button" class="props-book-cta" data-track-audience="traveler" data-track-cta="property_book_request" data-track-meta-id="{{ $property->id }}" disabled title="Booking flow lands in the next phase">
                        Request to book
                    </button>

                    <p class="props-book-fineprint">
                        Booking flow opens in the next release. For early access, <a href="/help">contact support</a>.
                    </p>
                </div>
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

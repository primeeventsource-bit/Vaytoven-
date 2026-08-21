@extends('properties.layout')

@section('title', $property->title)

@section('content')
    @if (request()->attributes->get('vyt_preview'))
        {{-- A preview that looks identical to the live page is how somebody
             concludes a draft is published. --}}
        <div style="background:#fffbeb;border-bottom:1px solid #fde68a;color:#92400e;padding:10px 16px;text-align:center;font-size:14px;">
            <strong>Preview.</strong> This listing is
            {{ strtoupper(str_replace('_', ' ', $property->status->value)) }} and is not visible to the public.
        </div>
    @endif

    <a href="{{ route('properties.index') }}" class="props-detail-back">← Back to all stays</a>

    <article class="props-detail">
        <div style="display:flex;gap:18px;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;">
            <div style="flex:1 1 auto;min-width:0;">
                <h1>{{ $property->title }}</h1>
                <div class="props-detail-loc">
                    {{ $property->city }}{{ $property->region ? ', '.$property->region : '' }}{{ $property->country ? ' · '.$property->country : '' }}
                </div>
            </div>

            {{-- Saving is a real form post, not a fetch, so it works with no
                 JavaScript and the saved/unsaved state comes back from the
                 server rather than being guessed at in the browser. --}}
            @if ($property->status === \App\Enums\PropertyStatus::Active)
                @auth
                    <form method="POST" action="{{ route('saved.toggle', $property) }}" style="flex:0 0 auto;">
                        @csrf
                        <button type="submit" class="props-save-btn @if($isSaved ?? false) is-saved @endif"
                                aria-pressed="{{ ($isSaved ?? false) ? 'true' : 'false' }}">
                            <span aria-hidden="true">{{ ($isSaved ?? false) ? '♥' : '♡' }}</span>
                            {{ ($isSaved ?? false) ? 'Saved' : 'Save' }}
                        </button>
                    </form>
                @else
                    {{-- Sent to sign in and returned here, rather than shown a
                         button that silently does nothing. --}}
                    <a href="{{ route('login', ['redirect' => request()->path()]) }}" class="props-save-btn" style="flex:0 0 auto;">
                        <span aria-hidden="true">♡</span> Save
                    </a>
                @endauth
            @endif
        </div>

        @if ($property->photos->isNotEmpty())
            <div data-vyt-event="gallery.opened" data-vyt-subject="{{ $property->reference }}">
            @include('partials.photo-carousel', [
                'photos'  => $property->photos,
                'alt'     => $property->title,
                'hero'    => true,
                'loading' => 'eager',
            ])
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
                    {{-- Declarative activity events: see the delegated listener
                         at the bottom of vyt-track.js. --}}
                    <section class="props-detail-section"
                             data-vyt-event="amenity.viewed" data-vyt-subject="{{ $property->reference }}">
                        <h2>What's included</h2>
                        <ul class="props-amenity-list">
                            @foreach ($property->amenities as $amenity)
                                <li>{{ $amenity->label }}</li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                {{-- No cancellation policy here, deliberately.

                     It promised things Vaytoven cannot do: "full refund",
                     "50% between 5 days and 24 hours", and a link to a booking
                     page. Vaytoven advertises listings — it takes no
                     reservation, holds no money and issues no refund, so it has
                     nothing to cancel and nothing to refund.

                     Whatever is agreed about cancelling is between the traveler
                     and the owner, and belongs in what they settle directly.
                     Printing a refund schedule under Vaytoven's name is how a
                     visitor comes to believe otherwise and disputes a charge
                     that was never taken. --}}

                @if ($property->host)
                    <section class="props-detail-section">
                        <h2>Advertised by</h2>
                        {{-- First name and last initial only. A listing already
                             carries a location and the dates the place is empty;
                             a full surname on top of that is what makes the set
                             identifying, and it tells the reader nothing useful. --}}
                        <p>{{ $property->host->publicDisplayName() }}</p>
                    </section>
                @endif
            </div>

            <aside>
                <div class="props-book-card">
                    <div class="props-book-price">
                        {{ $property->priceLabel() }} <small>{{ $property->priceCaption() }}</small>
                    </div>
                    @if ($property->cleaning_fee_cents > 0)
                        <div class="props-meta" style="margin-top:6px;">+ ${{ number_format($property->cleaning_fee_cents / 100) }} cleaning fee</div>
                    @endif

                    {{-- Vaytoven advertises the listing; it does not take
                         reservations, collect rental funds, or charge the
                         visitor for the stay. This form submits an OFFER to
                         the listing member, who responds directly. --}}
                    @if (session('success'))
                        <div style="margin-top:14px; padding:13px 15px; background:#ecfdf5; border:1px solid #a7f3d0; color:#047857; border-radius:10px; font-size:13px; line-height:1.55;">
                            {{ session('success') }}
                            @if (session('offer_expires_at'))
                                <div style="margin-top:6px; font-size:12px;">Expires {{ session('offer_expires_at') }}.</div>
                            @endif
                        </div>
                    @endif
                    @if (session('error'))
                        <div style="margin-top:14px; padding:12px 14px; background:#fef2f2; border:1px solid #fecaca; color:#b91c1c; border-radius:10px; font-size:13px;">
                            {{ session('error') }}
                        </div>
                    @endif

                    @auth
                        @if ($property->host_id === auth()->id())
                            <p class="props-book-fineprint" style="margin-top:18px;">This is your listing. Offers from visitors appear on
                                <a href="{{ route('offers.index') }}">your offers dashboard</a>.</p>
                        @else
                            <div data-vyt-event="offer.started" data-vyt-subject="{{ $property->reference }}">
                                @include('properties._offer-form', ['property' => $property])
                            </div>
                        @endif
                    @endauth

                    @guest
                        <p class="props-book-fineprint" style="margin-top:18px;">
                            <a href="{{ route('login') }}">Sign in</a> or
                            <a href="{{ route('register') }}">create an account</a> to submit an offer
                            on these dates.
                        </p>
                    @endguest

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

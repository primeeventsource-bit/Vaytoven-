{{--
  Submit Offer / Inquire About These Dates.

  This replaced the old "Continue to review" booking funnel. Vaytoven is a
  SaaS advertising and marketing platform: it introduces a visitor to a listing
  member and carries the message. It does not take the reservation, hold the
  dates, collect rental funds, or charge the visitor for the stay — all of that
  is arranged directly between the visitor and the listing member.

  The copy below says so explicitly, twice, because a form that looks like a
  checkout is exactly how a visitor comes to believe they have booked something.
--}}
@php $fieldStyle = 'width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:8px; font-size:14px; background:var(--bg); outline:none; font-family:inherit;'; @endphp

<form method="POST" action="{{ route('offers.store', $property) }}" style="margin-top:18px; display:grid; gap:10px;">
    @php($openWeeks = $property->availabilityWeeks->filter(fn ($w) => $w->status->acceptsOffers() && ! $w->hasPassed()))

    @if ($openWeeks->isNotEmpty())
        {{-- Ties the offer to the week actually being advertised, rather than to
             dates typed into a box. Without this the member has to match offers
             to weeks by eye, and an offer can arrive for dates never on sale. --}}
        <div>
            <label for="o-week" style="display:block; font-size:11px; letter-spacing:.08em; text-transform:uppercase; color:var(--muted); font-weight:600; margin-bottom:4px;">Which week</label>
            <select id="o-week" name="availability_week_id" required
                    style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:9px; font-size:14px;">
                @foreach ($openWeeks as $week)
                    <option value="{{ $week->id }}" @selected(old('availability_week_id') == $week->id)>
                        {{ $week->label() }} · {{ $week->nights() }} nights
                    </option>
                @endforeach
            </select>
            @error('availability_week_id')
                <div style="color:#b91c1c;font-size:12.5px;margin-top:4px;">{{ $message }}</div>
            @enderror
        </div>
    @endif
    @csrf

    <div>
        <label for="o-checkin" style="display:block; font-size:11px; letter-spacing:.08em; text-transform:uppercase; color:var(--muted); font-weight:600; margin-bottom:4px;">Desired check-in</label>
        <input id="o-checkin" type="date" name="check_in" value="{{ old('check_in') }}"
               min="{{ now()->toDateString() }}" style="{{ $fieldStyle }}">
        @error('check_in') <span style="font-size:12px; color:#b91c1c;">{{ $message }}</span> @enderror
    </div>

    <div>
        <label for="o-checkout" style="display:block; font-size:11px; letter-spacing:.08em; text-transform:uppercase; color:var(--muted); font-weight:600; margin-bottom:4px;">Desired check-out</label>
        <input id="o-checkout" type="date" name="check_out" value="{{ old('check_out') }}"
               min="{{ now()->addDay()->toDateString() }}" style="{{ $fieldStyle }}">
        @error('check_out') <span style="font-size:12px; color:#b91c1c;">{{ $message }}</span> @enderror
    </div>

    <div>
        <label for="o-guests" style="display:block; font-size:11px; letter-spacing:.08em; text-transform:uppercase; color:var(--muted); font-weight:600; margin-bottom:4px;">Guests</label>
        <input id="o-guests" type="number" name="guests" min="1" max="{{ $property->capacity }}"
               value="{{ old('guests', 2) }}" style="{{ $fieldStyle }}">
        @error('guests') <span style="font-size:12px; color:#b91c1c;">{{ $message }}</span> @enderror
    </div>

    <div>
        <label for="o-kind" style="display:block; font-size:11px; letter-spacing:.08em; text-transform:uppercase; color:var(--muted); font-weight:600; margin-bottom:4px;">Request type</label>
        <select id="o-kind" name="kind" style="{{ $fieldStyle }}">
            <option value="offer" @selected(old('kind', 'offer') === 'offer')>Submit an offer amount</option>
            <option value="inquiry" @selected(old('kind') === 'inquiry')>Ask about these dates</option>
        </select>
    </div>

    <div>
        <label for="o-amount" style="display:block; font-size:11px; letter-spacing:.08em; text-transform:uppercase; color:var(--muted); font-weight:600; margin-bottom:4px;">Your offer (USD)</label>
        <input id="o-amount" type="number" name="amount_dollars" min="1" step="1"
               value="{{ old('amount_dollars') }}"
               placeholder="{{ number_format($property->price_cents / 100) }} advertised"
               style="{{ $fieldStyle }}">
        @error('amount_dollars') <span style="font-size:12px; color:#b91c1c;">{{ $message }}</span> @enderror
    </div>

    <div>
        <label for="o-message" style="display:block; font-size:11px; letter-spacing:.08em; text-transform:uppercase; color:var(--muted); font-weight:600; margin-bottom:4px;">Message to the listing member</label>
        <textarea id="o-message" name="message" rows="3" maxlength="2000"
                  placeholder="Anything the member should know about your request…"
                  style="{{ $fieldStyle }} resize:vertical;">{{ old('message') }}</textarea>
        @error('message') <span style="font-size:12px; color:#b91c1c;">{{ $message }}</span> @enderror
    </div>

    <button type="submit" class="props-book-cta" style="letter-spacing:.06em;"
            data-track-audience="traveler" data-track-cta="property_submit_offer"
            data-track-meta-id="{{ $property->id }}">
        SUBMIT OFFER
    </button>
</form>

<p class="props-book-fineprint" style="margin-top:12px;">
    Submitting an offer or inquiry <strong>does not create a confirmed reservation and does not
    charge you for the stay</strong>. Vaytoven advertises this listing; any reservation, payment or
    cancellation is arranged directly with the listing member. Offers expire 24 hours after
    submission if the member does not respond.
</p>

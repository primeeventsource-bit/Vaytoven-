@extends('layouts.site')

@section('title', 'Trip support')
@section('meta_description', 'Get help with a booking, payment, cancellation, listing or offer. Raise a Vaytoven support request and track it by reference.')

@section('content')
<div class="site-shell">
    <section class="site-hero">
        <p class="eyebrow">Trip support</p>
        <h1>Something wrong with a trip?</h1>
        <p class="lede">
            Tell us what's happening and we'll pick it up. Requests about a booking, a payment or a
            cancellation are prioritised automatically — you don't need to mark them urgent.
        </p>
    </section>

    <section class="site-section">
        <div class="site-grid cols-2" style="align-items:start; gap:32px;">
            <div>
                @if (session('support_success'))
                    <div class="site-alert">
                        <strong>{{ session('support_success') }}</strong>
                        Your ticket reference is <code>{{ session('support_reference') }}</code>.
                    </div>
                @endif

                <form method="POST" action="{{ route('trip-support.store') }}">
                    @csrf

                    <div class="site-row-2">
                        <div class="site-field">
                            <label for="name">Your name</label>
                            <input id="name" name="name" type="text"
                                   value="{{ old('name', auth()->user()?->name) }}" required autocomplete="name">
                            @error('name') <div class="err">{{ $message }}</div> @enderror
                        </div>
                        <div class="site-field">
                            <label for="email">Email</label>
                            <input id="email" name="email" type="email"
                                   value="{{ old('email', auth()->user()?->email) }}" required autocomplete="email">
                            @error('email') <div class="err">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="site-row-2">
                        <div class="site-field">
                            <label for="phone">Phone <span style="text-transform:none; letter-spacing:0;">(optional)</span></label>
                            <input id="phone" name="phone" type="tel"
                                   value="{{ old('phone', auth()->user()?->phone) }}" autocomplete="tel" inputmode="tel">
                            @error('phone') <div class="err">{{ $message }}</div> @enderror
                        </div>
                        <div class="site-field">
                            <label for="category">What's it about?</label>
                            <select id="category" name="category" required>
                                <option value="">Choose one…</option>
                                @foreach ($categories as $value => $label)
                                    <option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('category') <div class="err">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="site-field">
                        <label for="property_reference">Booking code or listing <span style="text-transform:none; letter-spacing:0;">(optional)</span></label>
                        <input id="property_reference" name="property_reference" type="text"
                               value="{{ old('property_reference') }}"
                               placeholder="Confirmation code, listing name, or the page URL">
                        <p style="font-size:12px; color:var(--muted); margin:6px 0 0;">
                            Anything that helps us find it — whichever you have to hand.
                        </p>
                        @error('property_reference') <div class="err">{{ $message }}</div> @enderror
                    </div>

                    <div class="site-field">
                        <label for="subject">Subject</label>
                        <input id="subject" name="subject" type="text" value="{{ old('subject') }}" required>
                        @error('subject') <div class="err">{{ $message }}</div> @enderror
                    </div>

                    <div class="site-field">
                        <label for="message">What happened?</label>
                        <textarea id="message" name="message" required>{{ old('message') }}</textarea>
                        @error('message') <div class="err">{{ $message }}</div> @enderror
                    </div>

                    <button type="submit" class="site-cta"
                            data-track-audience="traveler" data-track-cta="trip_support_submit">Submit request</button>
                </form>
            </div>

            <div>
                <div class="site-card">
                    <h3>What happens next</h3>
                    <p style="margin:0 0 12px;">
                        Your request is logged with the exact date and time it arrived and given a
                        reference. A support agent picks it up from the same queue our help chat
                        writes to, so nothing falls between the two.
                    </p>
                    <p style="margin:0;">
                        We reply to the email address on the form — check spam if you don't see us.
                    </p>
                </div>

                <div class="site-card" style="margin-top:16px;">
                    <h3>Faster answers</h3>
                    <p style="margin:0 0 10px;">
                        How offers work, listing guidance, account security and platform fees are
                        all documented and searchable.
                    </p>
                    <a href="{{ route('help.index') }}">Search the Help Center</a>
                </div>

                <div class="site-card" style="margin-top:16px;">
                    <h3>Not about a trip?</h3>
                    <p style="margin:0 0 10px;">
                        Membership, billing, business or media questions are better handled by the
                        relevant team.
                    </p>
                    <a href="{{ route('contact.show') }}">Use the contact form</a>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

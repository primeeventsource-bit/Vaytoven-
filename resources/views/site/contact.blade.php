@extends('layouts.site')

@section('title', 'Contact us')
@section('meta_description', 'Get in touch with the Vaytoven team — support, membership, listings, billing, business and media enquiries.')

@section('content')
<div class="site-shell">
    <section class="site-hero">
        <p class="eyebrow">Contact</p>
        <h1>Talk to a human</h1>
        <p class="lede">
            Pick the team closest to your question and we'll route it there. We reply by email,
            usually within one business day.
        </p>
    </section>

    <section class="site-section">
        <div class="site-grid cols-2" style="align-items:start; gap:32px;">
            <div>
                @if (session('contact_success'))
                    <div class="site-alert">
                        <strong>{{ session('contact_success') }}</strong>
                        Your reference is <code>{{ session('contact_reference') }}</code> — quote it if you follow up.
                    </div>
                @endif

                <form method="POST" action="{{ route('contact.store') }}">
                    @csrf

                    <div class="site-row-2">
                        <div class="site-field">
                            <label for="first_name">First name</label>
                            <input id="first_name" name="first_name" type="text"
                                   value="{{ old('first_name') }}" required autocomplete="given-name">
                            @error('first_name') <div class="err">{{ $message }}</div> @enderror
                        </div>
                        <div class="site-field">
                            <label for="last_name">Last name</label>
                            <input id="last_name" name="last_name" type="text"
                                   value="{{ old('last_name') }}" required autocomplete="family-name">
                            @error('last_name') <div class="err">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="site-row-2">
                        <div class="site-field">
                            <label for="email">Email</label>
                            <input id="email" name="email" type="email"
                                   value="{{ old('email') }}" required autocomplete="email">
                            @error('email') <div class="err">{{ $message }}</div> @enderror
                        </div>
                        <div class="site-field">
                            <label for="phone">Phone <span style="text-transform:none; letter-spacing:0;">(optional)</span></label>
                            <input id="phone" name="phone" type="tel"
                                   value="{{ old('phone') }}" autocomplete="tel" inputmode="tel">
                            @error('phone') <div class="err">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="site-field">
                        <label for="department">Department</label>
                        <select id="department" name="department" required>
                            <option value="">Choose the closest match…</option>
                            @foreach ($departments as $value => $label)
                                <option value="{{ $value }}" @selected(old('department') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('department') <div class="err">{{ $message }}</div> @enderror
                    </div>

                    <div class="site-field">
                        <label for="subject">Subject</label>
                        <input id="subject" name="subject" type="text" value="{{ old('subject') }}" required>
                        @error('subject') <div class="err">{{ $message }}</div> @enderror
                    </div>

                    <div class="site-field">
                        <label for="message">Message</label>
                        <textarea id="message" name="message" required>{{ old('message') }}</textarea>
                        @error('message') <div class="err">{{ $message }}</div> @enderror
                    </div>

                    <button type="submit" class="site-cta"
                            data-track-audience="traveler" data-track-cta="contact_submit">Send message</button>
                </form>
            </div>

            <div>
                <div class="site-card">
                    <h3>Already travelling?</h3>
                    <p style="margin:0 0 14px;">
                        If something is wrong with a stay that is happening now, use Trip Support
                        instead — those requests are prioritised.
                    </p>
                    <a href="{{ route('trip-support.show') }}" class="site-cta"
                       style="font-size:14px; padding:10px 20px;">Go to Trip Support</a>
                </div>

                <div class="site-card" style="margin-top:16px;">
                    <h3>Looking for an answer now?</h3>
                    <p style="margin:0 0 10px;">
                        The Help Center covers bookings, listings, offers, memberships and account
                        security, and it's searchable.
                    </p>
                    <a href="{{ route('help.index') }}">Browse the Help Center</a>
                </div>

                <div class="site-card" style="margin-top:16px;">
                    <h3>Prefer email or phone?</h3>
                    <p style="margin:0 0 10px;">
                        The form routes to the right team and gives you a reference number, but you
                        can write to us directly:
                    </p>
                    <p style="margin:0;">
                        <a href="mailto:contact@vaytoven.com">contact@vaytoven.com</a><br>
                        <a href="tel:+18777829868">(877) 782-9868</a>
                    </p>
                </div>

                <div class="site-card" style="margin-top:16px;">
                    <h3>Registered office</h3>
                    <p style="margin:0;">
                        Vaytoven Technologies LLC<br>
                        500 S Australian Ave, Ste 600<br>
                        West Palm Beach, FL 33401
                    </p>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

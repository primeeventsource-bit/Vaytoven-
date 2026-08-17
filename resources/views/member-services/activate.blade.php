@extends('layouts.site')

{{-- Use the block form here, not the inline single-expression variant: the
     inline one compiled to an unterminated PHP open tag on this template and
     swallowed everything after it. Note also that a Blade comment is not a
     safe place to quote Blade or PHP syntax — the compiler still processes
     what is inside it. --}}
@php
    $navCurrent = 'pricing';
@endphp

@section('title', 'Member Services Activation · Vaytoven')
@section('meta_description', 'Activate Vaytoven Member Services. Choose Bronze, Silver or Gold, set your number of weeks, and pay securely online.')

@section('content')
<div class="site-shell">

    <section class="site-hero">
        <div class="eyebrow">Member Services</div>
        <h1>Activate your Member Services</h1>
        <p class="lede">
            Choose a package, set how many weeks you want advertised, and pay securely online.
            The total is calculated for you — one payment, not a recurring subscription.
        </p>
    </section>

    <section class="site-section">
        <form method="POST" action="{{ route('member-services.store') }}" id="ms-form">
            @csrf

            {{-- Packages. The whole card is the radio label, so the feature
                 list and the benefit are part of what you click. --}}
            <div class="pkg-grid" role="radiogroup" aria-label="Member Services package">
                @foreach (\App\Enums\MemberServicePackage::ordered() as $pkg)
                    @php
                        $weekly = $pkg->currentPricePerWeekCents();
                        $tag    = 'is-'.$pkg->value;
                    @endphp

                    <label class="pkg-card pkg-selectable {{ $tag }}{{ $pkg->badge() ? ' has-badge' : '' }}"
                           for="pkg-{{ $pkg->value }}" id="pkg-{{ $pkg->value }}-card">
                        <input type="radio" id="pkg-{{ $pkg->value }}" name="package"
                               value="{{ $pkg->value }}"
                               data-cents="{{ $weekly }}"
                               @checked(old('package') === $pkg->value)>

                        @if ($pkg->badge())
                            <span class="pkg-badge">{{ $pkg->badge() }}</span>
                        @endif

                        <div class="pkg-emoji" aria-hidden="true">{{ $pkg->emoji() }}</div>
                        <div class="pkg-name">{{ strtoupper($pkg->label()) }}</div>
                        <div class="pkg-headline">{{ $pkg->headline() }}</div>

                        <div class="pkg-price">${{ number_format($weekly / 100, 0) }}<span>/week</span></div>
                        <div class="pkg-allowance">{{ $pkg->propertyAllowance() }}</div>

                        <p class="pkg-tagline">{{ $pkg->tagline() }}</p>

                        <ul class="pkg-features">
                            @foreach ($pkg->features() as $feature)
                                <li class="{{ $feature['included'] ? '' : 'is-excluded' }}">
                                    <span class="pkg-tick" aria-hidden="true">{{ $feature['included'] ? '✓' : '—' }}</span>
                                    <span>
                                        {{ $feature['label'] }}
                                        @if ($feature['included'] && $feature['value'] !== '✓')
                                            <strong>{{ $feature['value'] }}</strong>
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>

                        <span class="pkg-cta pkg-choose">{{ $pkg->ctaLabel() }}</span>
                    </label>
                @endforeach
            </div>
            @error('package') <div class="site-field"><div class="err">{{ $message }}</div></div> @enderror

            {{-- Weeks + running total ----------------------------------- --}}
            <div class="ms-calc">
                <div class="ms-weeks">
                    <label for="weeks">Number of weeks</label>
                    <div class="ms-stepper">
                        <button type="button" id="ms-minus" aria-label="One week fewer">&minus;</button>
                        <input id="weeks" name="weeks" type="number" inputmode="numeric"
                               min="1" max="{{ $maxWeeks }}" value="{{ old('weeks', 4) }}" required>
                        <button type="button" id="ms-plus" aria-label="One week more">+</button>
                    </div>
                    @error('weeks') <div class="err">{{ $message }}</div> @enderror
                </div>

                {{-- Display only. The server recomputes this from the package
                     and the week count; no amount is submitted with the form,
                     so nothing here can change what gets charged. --}}
                <div class="ms-total">
                    <div class="ms-total-label">Total Member Services fee</div>
                    <div class="ms-total-num" id="ms-total" aria-live="polite">$0.00</div>
                    <div class="ms-total-basis" id="ms-basis">Choose a package to see your total.</div>
                </div>
            </div>

            {{-- Member details ------------------------------------------ --}}
            <h2 style="margin-top:44px;">Your details</h2>
            <div class="site-row-2">
                <div class="site-field">
                    <label for="first_name">First name</label>
                    <input id="first_name" name="first_name" type="text" value="{{ old('first_name') }}"
                           required autocomplete="given-name">
                    @error('first_name') <div class="err">{{ $message }}</div> @enderror
                </div>
                <div class="site-field">
                    <label for="last_name">Last name</label>
                    <input id="last_name" name="last_name" type="text" value="{{ old('last_name') }}"
                           required autocomplete="family-name">
                    @error('last_name') <div class="err">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="site-row-2">
                <div class="site-field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}"
                           required autocomplete="email">
                    @error('email') <div class="err">{{ $message }}</div> @enderror
                </div>
                <div class="site-field">
                    <label for="phone">Phone <span style="text-transform:none;letter-spacing:0;">(optional)</span></label>
                    <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel">
                    @error('phone') <div class="err">{{ $message }}</div> @enderror
                </div>
            </div>

            <button type="submit" class="site-cta" style="margin-top:8px;"
                    data-track-audience="member" data-track-cta="member_services_continue">
                Continue to secure payment
            </button>

            <p class="site-note" style="margin-top:22px;">
                Nothing is charged on this page. On the next step your card details are entered
                on a secure form hosted by our payment processor — Vaytoven never sees or stores
                your card number. This is a one-time activation fee for advertising and listing
                services, not a recurring subscription.
            </p>
        </form>
    </section>
</div>
@endsection

@push('head')
@include('partials.package-styles')
<style>
    /* Selectable variant of the shared card. */
    .pkg-selectable { cursor:pointer; }
    .pkg-selectable input { position:absolute; opacity:0; pointer-events:none; }
    .pkg-selectable:has(input:checked) {
        border-color:var(--magenta);
        box-shadow:0 14px 38px -16px rgba(214,51,132,.55);
    }
    .pkg-selectable:has(input:focus-visible) { outline:2px solid var(--purple); outline-offset:4px; }
    /* The CTA reads as the selected state rather than as a second button. */
    .pkg-selectable .pkg-choose { opacity:.5; transition:opacity .15s; }
    .pkg-selectable:has(input:checked) .pkg-choose { opacity:1; }
    .pkg-selectable:has(input:checked) .pkg-choose::after { content:'  ✓'; }
    .pkg-grid { margin-bottom:34px; }

    .ms-calc { display:grid; gap:20px; grid-template-columns:1fr; align-items:end; }
    @media (min-width:760px) { .ms-calc { grid-template-columns:1fr 1fr; } }
    .ms-weeks label {
        display:block; font-size:12px; font-weight:600; letter-spacing:.06em;
        text-transform:uppercase; color:var(--muted); margin-bottom:6px;
    }
    .ms-stepper { display:flex; align-items:stretch; gap:0; max-width:220px; }
    .ms-stepper button {
        width:48px; border:1px solid var(--line); background:#fff; font-size:22px;
        line-height:1; color:var(--ink); cursor:pointer;
    }
    .ms-stepper button:first-of-type { border-radius:9px 0 0 9px; }
    .ms-stepper button:last-of-type { border-radius:0 9px 9px 0; }
    .ms-stepper input {
        flex:1; min-width:0; text-align:center; border:1px solid var(--line);
        border-left:0; border-right:0; font:inherit; font-size:18px; padding:11px 0;
        background:var(--bg); -moz-appearance:textfield;
    }
    .ms-stepper input::-webkit-outer-spin-button,
    .ms-stepper input::-webkit-inner-spin-button { -webkit-appearance:none; margin:0; }

    .ms-total { background:var(--ink); color:#fff; border-radius:16px; padding:22px 26px; }
    .ms-total-label { font-size:12px; letter-spacing:.12em; text-transform:uppercase; color:rgba(255,255,255,.65); }
    .ms-total-num {
        font-family:'Fraunces',serif; font-size:clamp(30px,5vw,44px); font-weight:600;
        letter-spacing:-.02em; margin:6px 0 4px;
        background:var(--gradient); -webkit-background-clip:text; background-clip:text; color:transparent;
    }
    .ms-total-basis { font-size:13px; color:rgba(255,255,255,.6); }
</style>
@endpush

@push('scripts')
<script>
(function () {
    var form  = document.getElementById('ms-form');
    if (!form) return;

    var weeks = document.getElementById('weeks');
    var total = document.getElementById('ms-total');
    var basis = document.getElementById('ms-basis');
    var max   = parseInt(weeks.getAttribute('max'), 10) || 52;

    function selectedCents() {
        var picked = form.querySelector('input[name="package"]:checked');
        return picked ? parseInt(picked.dataset.cents, 10) : null;
    }

    function money(cents) {
        return '$' + (cents / 100).toLocaleString('en-US', {
            minimumFractionDigits: 2, maximumFractionDigits: 2
        });
    }

    // Mirrors MemberServiceOrderFactory exactly: rate x weeks, integer cents.
    // This is a preview — the server computes the figure it charges — but if
    // the two ever disagreed the member would be quoted one price and billed
    // another, so the arithmetic is kept identical on purpose.
    function render() {
        var cents = selectedCents();
        var n = parseInt(weeks.value, 10);

        if (!n || n < 1) { n = 1; }
        if (n > max) { n = max; weeks.value = max; }

        if (cents === null) {
            total.textContent = '$0.00';
            basis.textContent = 'Choose a package to see your total.';
            return;
        }

        total.textContent = money(cents * n);
        basis.textContent = n + ' ' + (n === 1 ? 'week' : 'weeks') + ' x ' + money(cents)
            + ' · charged once';
    }

    form.querySelectorAll('input[name="package"]').forEach(function (el) {
        el.addEventListener('change', render);
    });
    weeks.addEventListener('input', render);

    document.getElementById('ms-minus').addEventListener('click', function () {
        weeks.value = Math.max(1, (parseInt(weeks.value, 10) || 1) - 1);
        render();
    });
    document.getElementById('ms-plus').addEventListener('click', function () {
        weeks.value = Math.min(max, (parseInt(weeks.value, 10) || 0) + 1);
        render();
    });

    render();
})();
</script>
@endpush

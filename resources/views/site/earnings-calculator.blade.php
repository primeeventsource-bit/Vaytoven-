@extends('layouts.site')

@section('title', 'Earnings calculator')
@section('meta_description', 'Estimate what a vacation property could generate on Vaytoven. Estimates only — no guarantee of earnings, occupancy or results.')

@push('head')
    <style>
        /* Fraunces is a variable font with an optical-size axis. On auto the
           browser picks a more decorative cut as type gets larger — flared
           serifs and exaggerated curves — which is what reads as wavy on
           headings. Pinned here; the property inherits, so this one
           declaration reaches every heading below it. */
        html { font-optical-sizing: none; }

        .calc-wrap { display:grid; gap:28px; grid-template-columns:1fr; align-items:start; }
        @media (min-width:900px) { .calc-wrap { grid-template-columns:1.1fr .9fr; } }
        .calc-out {
            background:var(--gradient); color:#fff; border-radius:16px; padding:26px 28px;
        }
        .calc-out .label {
            font-size:11.5px; letter-spacing:.14em; text-transform:uppercase;
            opacity:.85; font-weight:700; margin-bottom:8px;
        }
        .calc-out .big {
            font-family:'Fraunces',serif; font-size:44px; line-height:1.05;
            font-variant-numeric:tabular-nums; margin-bottom:4px;
        }
        .calc-out .sub { font-size:13.5px; opacity:.9; }
        .calc-line {
            display:flex; justify-content:space-between; gap:16px;
            padding:11px 0; border-bottom:1px solid rgba(255,255,255,.22);
            font-size:14px; font-variant-numeric:tabular-nums;
        }
        .calc-line:last-child { border-bottom:0; }
        .calc-disclaimer {
            margin-top:22px; padding:16px 18px; background:#fffbeb;
            border:1px solid #fde68a; border-radius:11px;
            font-size:13px; color:#92400e; line-height:1.6;
        }
    </style>
@endpush

@section('content')
<div class="site-shell">
    <section class="site-hero">
        <p class="eyebrow">Earnings calculator</p>
        <h1>What could your property bring in?</h1>
        <p class="lede">
            A rough model based on your nightly rate, how many nights you'd make available, and how
            often you expect them to fill. It updates as you type.
        </p>
    </section>

    <section class="site-section">
        <div class="calc-wrap">
            <div>
                <div class="site-row-2">
                    <div class="site-field">
                        <label for="calc-location">Location</label>
                        <input id="calc-location" type="text" placeholder="e.g. Lake Tahoe, USA">
                    </div>
                    <div class="site-field">
                        <label for="calc-type">Property type</label>
                        <select id="calc-type">
                            <option value="1">Villa or house</option>
                            <option value="1">Condo or apartment</option>
                            <option value="1">Cabin</option>
                            <option value="1">Resort week / club inventory</option>
                        </select>
                    </div>
                </div>

                <div class="site-row-2">
                    <div class="site-field">
                        <label for="calc-bedrooms">Bedrooms</label>
                        <input id="calc-bedrooms" type="number" min="0" max="20" value="3">
                    </div>
                    <div class="site-field">
                        <label for="calc-bathrooms">Bathrooms</label>
                        <input id="calc-bathrooms" type="number" min="0" max="20" step="0.5" value="2">
                    </div>
                </div>

                <div class="site-row-2">
                    <div class="site-field">
                        <label for="calc-rate">Nightly rate (USD)</label>
                        <input id="calc-rate" type="number" min="0" step="5" value="280">
                    </div>
                    <div class="site-field">
                        <label for="calc-nights">Nights available per year</label>
                        <input id="calc-nights" type="number" min="0" max="365" value="120">
                    </div>
                </div>

                <div class="site-field">
                    <label for="calc-occupancy">Expected occupancy — <span id="calc-occupancy-out">65</span>%</label>
                    <input id="calc-occupancy" type="range" min="0" max="100" value="65"
                           style="padding:0; accent-color:#D63384;">
                </div>

                <button type="button" id="calc-run" class="site-cta"
                        data-track-audience="host" data-track-cta="earnings_calculate">Calculate estimate</button>
            </div>

            <div>
                <div class="calc-out">
                    <div class="label">Estimated annual gross to you</div>
                    <div class="big" id="calc-net">$0</div>
                    <div class="sub" id="calc-basis">Fill in the fields to see an estimate.</div>

                    <div style="margin-top:20px;">
                        <div class="calc-line"><span>Nights booked</span><span id="calc-booked">0</span></div>
                        <div class="calc-line"><span>Gross booking value</span><span id="calc-gross">$0</span></div>
                        <div class="calc-line"><span>Vaytoven's cut of it</span><span>$0</span></div>
                        <div class="calc-line"><strong>Estimated to you</strong><strong id="calc-net-line">$0</strong></div>
                    </div>
                </div>

                <div class="calc-disclaimer">
                    <strong>Estimates are provided for informational purposes only.</strong>
                    Vaytoven Technologies LLC does not guarantee earnings, occupancy, rental income,
                    property sales, or financial results. Actual outcomes depend on demand,
                    seasonality, pricing, your club or program rules, and factors outside the
                    platform's control. Vaytoven advertises listings and does not collect rental
                    payments or pay hosts — guests pay you directly.
                </div>

                {{-- Deliberately shows no deduction. Vaytoven charges the host an
                     advertising subscription, quoted up front; it takes no
                     percentage of what a guest pays, and that money never passes
                     through the platform, so there is nothing to subtract here. --}}
                <div class="calc-disclaimer" style="margin-top:14px;">
                    <strong>What Vaytoven costs is separate.</strong> You pay us an advertising
                    subscription to list, quoted in writing before you commit. We take no
                    commission on a stay, so nothing is deducted from the figure above.
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var el = function (id) { return document.getElementById(id); };
    var money = function (n) {
        return '$' + Math.round(n).toLocaleString('en-US');
    };

    function calculate() {
        var rate      = Math.max(0, parseFloat(el('calc-rate').value) || 0);
        var nights    = Math.max(0, parseFloat(el('calc-nights').value) || 0);
        var occupancy = Math.min(100, Math.max(0, parseFloat(el('calc-occupancy').value) || 0));

        var booked = Math.round(nights * (occupancy / 100));
        // No deduction: Vaytoven takes no percentage of a stay, so gross to the
        // host IS the estimate. Guests pay the host directly.
        var gross  = booked * rate;

        el('calc-booked').textContent   = booked.toLocaleString('en-US');
        el('calc-gross').textContent    = money(gross);
        el('calc-net-line').textContent = money(gross);
        el('calc-net').textContent      = money(gross);
        el('calc-basis').textContent    = booked > 0
            ? booked + ' nights at ' + money(rate) + ', paid to you by the guest.'
            : 'Increase nights or occupancy to see an estimate.';
    }

    el('calc-occupancy').addEventListener('input', function () {
        el('calc-occupancy-out').textContent = this.value;
        calculate();
    });

    ['calc-rate', 'calc-nights', 'calc-bedrooms', 'calc-bathrooms'].forEach(function (id) {
        el(id).addEventListener('input', calculate);
    });

    el('calc-run').addEventListener('click', calculate);

    calculate();
})();
</script>
@endpush

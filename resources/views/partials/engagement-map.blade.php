{{--
  "Where your ad is getting attention" — the member-facing map.

  Shows approximate cities and counts. It deliberately carries no IP address,
  device detail, visitor identity or exact coordinate: that information is
  evidence, it lives on the admin side, and a member has a legitimate interest
  in knowing their advertising reaches Orlando without knowing which household.

  Requires: $engagement (App\Services\Analytics\MemberEngagementMap::build)
            $listings   (Collection<Property>) for the property selector
--}}
@php
    $engagement = $engagement ?? null;
@endphp

@if ($engagement)
<section class="vyt-section">
    <div class="vyt-card">
        <div class="vyt-card-header">
            <h3>Where your ad is getting attention</h3>
            <span class="vyt-section-meta">approximate areas · {{ $engagement['windows'][$engagement['window']] ?? '' }}</span>
        </div>

        <div class="vyt-card-body">
            <div class="eng-totals">
                {{-- One figure. Ad views and clicks side by side asked the member
                     to work out the difference, and with views at zero next to a
                     large click count the pair read as broken. --}}
                <div><span class="eng-num">{{ number_format($engagement['totals']['ad_views']) }}</span><span class="eng-lbl">Ad Views</span></div>
                <div><span class="eng-num">{{ number_format($engagement['totals']['offers']) }}</span><span class="eng-lbl">Offers &amp; inquiries</span></div>
            </div>

            <form method="GET" class="eng-filters">
                <select name="engagement_days" onchange="this.form.submit()">
                    @foreach ($engagement['windows'] as $days => $label)
                        <option value="{{ $days }}" @selected($engagement['window'] === $days)>{{ $label }}</option>
                    @endforeach
                </select>

                @if (($listings ?? collect())->count() > 1)
                    <select name="engagement_property" onchange="this.form.submit()">
                        <option value="">All properties</option>
                        @foreach ($listings as $listing)
                            <option value="{{ $listing->id }}" @selected($engagement['property_id'] === $listing->id)>
                                {{ $listing->title }}
                            </option>
                        @endforeach
                    </select>
                @endif
            </form>

            @if (empty($engagement['pins']))
                <div class="vyt-card-empty" style="margin:0;">
                    No mapped activity yet for this period.
                    @if ($engagement['totals']['ad_views'] > 0)
                        {{-- Honest about why the totals and the map disagree. --}}
                        Areas appear once at least {{ $engagement['min_per_pin'] }} visits come from the
                        same place — below that, a marker would point at a person rather than an audience.
                    @endif
                </div>
            @else
                <div id="eng-map" class="eng-map"
                     data-pins="{{ json_encode($engagement['pins']) }}"
                     data-mapbox-token="{{ $mapboxToken ?? '' }}"
                     data-mapbox-style="{{ $mapboxStyle ?? '' }}"></div>

                <table class="vyt-table" style="margin-top:16px;">
                    <thead><tr><th>Area</th><th style="text-align:right;">Ad Views</th></tr></thead>
                    <tbody>
                        @foreach (array_slice($engagement['pins'], 0, 10) as $pin)
                            <tr>
                                <td>
                                    {{ $pin['city'] }}@if ($pin['region']), {{ $pin['region'] }}@endif
                                    <span class="vyt-faint">{{ $pin['country'] }}</span>
                                </td>
                                <td style="text-align:right;font-weight:600;">{{ number_format($pin['ad_views']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <p class="site-note" style="margin-top:14px;">
                Locations are approximate, worked out from the visitor's network rather than
                any precise position. We never show you who a visitor is, where exactly they
                are, or what device they used.
            </p>
        </div>
    </div>
</section>
@endif

@push('head')
<style>
        /* Source Serif 4 is a variable font with an optical-size axis.
           Pinned so the browser holds one cut at every size rather than
           selecting a more display-like one as type grows. The property
           inherits, so this single declaration reaches every heading below it.

           Fraunces was here until its letterforms — the curled g, the flared
           C and V — kept reading as wavy at heading sizes. No axis removed
           that: WONK and SOFT were already at 0 and rendering with them
           pinned was pixel-identical, so the face itself had to change. */
        html { font-optical-sizing: none; }

    .eng-totals { display:flex; gap:36px; flex-wrap:wrap; margin-bottom:18px; }
    .eng-num {
        display:block; font-family:'Source Serif 4', serif; font-size:30px; font-weight:600;
        background:var(--gradient); -webkit-background-clip:text; background-clip:text; color:transparent;
    }
    .eng-lbl { font-size:12px; letter-spacing:.08em; text-transform:uppercase; color:var(--muted); }
    .eng-filters { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px; }
    .eng-filters select {
        padding:8px 12px; border:1px solid var(--line); border-radius:9px; font:inherit; font-size:13.5px;
    }
    .eng-map { height:340px; border-radius:12px; overflow:hidden; border:1px solid var(--line); }
</style>
@endpush

@push('scripts')
@if (! empty($engagement['pins']))
<script>
(function () {
    var el = document.getElementById('eng-map');
    if (!el) return;

    var pins = JSON.parse(el.dataset.pins || '[]');
    if (!pins.length) return;

    // Leaflet is loaded by partials/listing-analytics, which may appear after
    // this block in the scripts stack. Bailing on a missing L would leave the
    // map silently blank depending on include order, so wait for it instead.
    var waited = 0;
    (function whenReady() {
        if (typeof L === 'undefined') {
            if ((waited += 100) > 8000) return;   // give up rather than spin
            return window.setTimeout(whenReady, 100);
        }
        render();
    })();

    function render() {

    var map = L.map(el, { scrollWheelZoom: false });

    // Mapbox tiles when a PUBLIC token is configured, OSM otherwise. The token
    // is filtered server-side — a secret sk.* key never reaches this script.
    var token = el.dataset.mapboxToken;
    var style = el.dataset.mapboxStyle || 'mapbox/light-v11';

    if (token) {
        L.tileLayer('https://api.mapbox.com/styles/v1/' + style + '/tiles/{z}/{x}/{y}?access_token=' + token, {
            tileSize: 512, zoomOffset: -1, attribution: '© Mapbox © OpenStreetMap'
        }).addTo(map);
    } else {
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap'
        }).addTo(map);
    }

    var bounds = [];

    pins.forEach(function (pin) {
        var total = pin.ad_views || 0;
        // Area scales with engagement, capped so one busy city does not cover
        // the map.
        var radius = Math.min(28, 8 + Math.sqrt(total) * 2.5);

        L.circleMarker([pin.lat, pin.lng], {
            radius: radius, color: '#D63384', fillColor: '#FF3D8A',
            fillOpacity: 0.45, weight: 2
        })
        .bindPopup(
            '<strong>' + (pin.city || 'Unknown') +
            (pin.region ? ', ' + pin.region : '') + '</strong><br>' +
            (pin.ad_views || 0) + ' ad views'
        )
        .addTo(map);

        bounds.push([pin.lat, pin.lng]);
    });

    map.fitBounds(bounds, { padding: [40, 40], maxZoom: 9 });

    }
})();
</script>
@endif
@endpush

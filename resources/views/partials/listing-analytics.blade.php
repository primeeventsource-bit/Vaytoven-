{{--
  Listing Views & Geo Map — shared analytics panel used by both the host
  dashboard (Maya's three listings) and the member dashboard (Margaret's
  managed listings tied to her enquiry).

  Required vars:
    $listings         : Collection<Property>
    $totalViews30d    : int
    $totalViews7d     : int
    $uniqueCountries  : int
    $perListingStats  : array<int, array{views_30d:int,views_7d:int,top_city:?string,top_country:?string}>
    $pinPoints        : array<int, array{lat:float,lng:float,city:?string,country:?string,count:int}>

  Empty-state safe: renders an explainer card when $listings is empty so the
  member surface doesn't look broken before any managed listings exist.
--}}

@push('head')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
@endpush

<section class="vyt-section">
    <div class="vyt-section-header">
        <h2>Listing performance</h2>
        <span class="vyt-section-meta">Last 30 days</span>
    </div>

    @if ($listings->isEmpty())
        <div class="vyt-card">
            <div class="vyt-card-empty">
                No listings yet. Once your Managed Listing Program submission is approved,
                your properties — and their visit analytics — will appear here.
            </div>
        </div>
    @else
        <div class="vyt-tiles" style="margin-bottom: 18px;">
            <div class="vyt-tile">
                <div class="vyt-tile-label">Views · last 30d</div>
                <span class="vyt-tile-value t-gradient">{{ number_format($totalViews30d) }}</span>
            </div>
            <div class="vyt-tile">
                <div class="vyt-tile-label">Views · last 7d</div>
                <span class="vyt-tile-value t-pink">{{ number_format($totalViews7d) }}</span>
            </div>
            <div class="vyt-tile">
                <div class="vyt-tile-label">Countries reached</div>
                <span class="vyt-tile-value t-purple">{{ number_format($uniqueCountries) }}</span>
            </div>
            <div class="vyt-tile">
                <div class="vyt-tile-label">Active listings</div>
                <span class="vyt-tile-value t-blue">{{ number_format($listings->count()) }}</span>
            </div>
        </div>

        <div class="vyt-card" style="margin-bottom: 18px;">
            <div class="vyt-card-header">
                <h3>Per-listing views</h3>
                <span class="vyt-section-meta">{{ $listings->count() }} {{ Str::plural('listing', $listings->count()) }}</span>
            </div>
            <table class="vyt-table">
                <thead>
                    <tr>
                        <th>Listing</th>
                        <th style="text-align:right;">Views · 7d</th>
                        <th style="text-align:right;">Views · 30d</th>
                        <th>Top origin</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($listings as $listing)
                        @php($s = $perListingStats[$listing->id] ?? null)
                        <tr>
                            <td>
                                <a href="{{ route('properties.show', $listing) }}">{{ $listing->title }}</a>
                                <div class="vyt-faint">{{ $listing->city }}@if($listing->country), {{ $listing->country }}@endif</div>
                            </td>
                            <td style="text-align:right;">{{ number_format($s['views_7d'] ?? 0) }}</td>
                            <td style="text-align:right;">{{ number_format($s['views_30d'] ?? 0) }}</td>
                            <td>
                                @if(($s['top_city'] ?? null))
                                    {{ $s['top_city'] }}@if($s['top_country']), {{ $s['top_country'] }}@endif
                                @else
                                    <span class="vyt-faint">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="vyt-card">
            <div class="vyt-card-header">
                <h3>Where your visitors are</h3>
                <span class="vyt-section-meta">{{ count($pinPoints) }} {{ Str::plural('city', count($pinPoints)) }}</span>
            </div>
            @if (count($pinPoints) === 0)
                <div class="vyt-card-empty">
                    No geo-resolved views yet. GeoIP data will appear here as real visits land
                    on your listings.
                </div>
            @else
                <div id="vyt-views-map"
                     style="height: 360px; width: 100%;"
                     data-pins="{{ json_encode($pinPoints) }}"
                     @if (! empty($mapboxToken))
                         data-mapbox-token="{{ $mapboxToken }}"
                         data-mapbox-style="{{ $mapboxStyle ?? 'mapbox/light-v11' }}"
                     @endif
                     aria-label="Map of visitor origins"></div>
            @endif
        </div>

        @push('scripts')
            <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
                    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
            <script>
                (function () {
                    var mapEl = document.getElementById('vyt-views-map');
                    if (!mapEl || typeof L === 'undefined') return;
                    var pins = [];
                    try { pins = JSON.parse(mapEl.dataset.pins || '[]'); } catch (e) { pins = []; }
                    if (!pins.length) return;

                    var map = L.map(mapEl, { scrollWheelZoom: false, zoomControl: true });

                    // Prefer Mapbox tiles when a token's wired (richer styling,
                    // hi-DPI). Fall back to raw OpenStreetMap so the map still
                    // renders if the token is missing or rate-limited.
                    var mbToken = mapEl.dataset.mapboxToken;
                    var mbStyle = mapEl.dataset.mapboxStyle || 'mapbox/light-v11';
                    if (mbToken) {
                        L.tileLayer(
                            'https://api.mapbox.com/styles/v1/' + mbStyle + '/tiles/512/{z}/{x}/{y}@2x?access_token=' + mbToken,
                            {
                                tileSize: 512,
                                zoomOffset: -1,
                                maxZoom: 19,
                                attribution: '&copy; <a href="https://www.mapbox.com/about/maps/">Mapbox</a> &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                            }
                        ).addTo(map);
                    } else {
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            maxZoom: 19,
                            attribution: '&copy; OpenStreetMap'
                        }).addTo(map);
                    }

                    var maxCount = Math.max.apply(null, pins.map(function (p) { return p.count; }));
                    var bounds = [];
                    pins.forEach(function (p) {
                        // Pin radius scales with count, capped so a popular city doesn't drown the map.
                        var r = 6 + Math.round(14 * (p.count / Math.max(maxCount, 1)));
                        var marker = L.circleMarker([p.lat, p.lng], {
                            radius: r,
                            color: '#7B2CBF',
                            fillColor: '#D63384',
                            fillOpacity: 0.55,
                            weight: 2
                        }).addTo(map);
                        var label = (p.city || '—') + (p.country ? ', ' + p.country : '') +
                                    ' · ' + p.count + ' view' + (p.count === 1 ? '' : 's');
                        marker.bindTooltip(label, { direction: 'top', opacity: 0.92 });
                        bounds.push([p.lat, p.lng]);
                    });

                    if (bounds.length === 1) {
                        map.setView(bounds[0], 5);
                    } else {
                        map.fitBounds(bounds, { padding: [30, 30], maxZoom: 7 });
                    }
                })();
            </script>
        @endpush
    @endif
</section>

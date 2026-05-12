{{--
  Listing Views & Geo Map — shared analytics panel used by both the host
  dashboard (Maya's host-listed properties) and the member dashboard
  (Margaret's managed listings tied to her enquiry).

  Required vars:
    $listings         : Collection<Property>
    $totalViews30d    : int
    $totalViews7d     : int
    $uniqueCountries  : int
    $perListingStats  : array<int, array{views_30d:int,views_7d:int,top_city:?string,top_country:?string}>
    $pinPoints        : array<int, array{lat:float,lng:float,city:?string,country:?string,count:int}>
    $pinsByListing    : array<string, array<int, array{lat,lng,city,country,count}>>  keyed by property_id
    $mapboxToken      : ?string  (only pk.* tokens reach here)
    $mapboxStyle      : ?string  (e.g. 'mapbox/light-v11')

  Interactivity:
    - Click a per-listing table row → map filters to that listing's
      pins + fitBounds to its visitor footprint, the row highlights, and
      a "Showing X · Show all" chip appears above the map.
    - Click "Show all" → restores the global pin set.
    - Click a pin → flyTo + popup with the city/country/count.
    - Keyboard: rows are focusable; Enter/Space activates.

  Empty-state safe: renders an explainer card when $listings is empty.
--}}

@push('head')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <style>
        /* Clickable listing rows in the per-listing table. */
        .vyt-table tr[data-listing-id] { cursor: pointer; }
        .vyt-table tr[data-listing-id]:focus { outline: 2px solid var(--purple); outline-offset: -2px; }
        .vyt-table tr[data-listing-id].is-active td {
            background: linear-gradient(90deg, #faf5ff 0%, #fef3f9 100%);
        }
        .vyt-table tr[data-listing-id].is-active td:first-child {
            box-shadow: inset 3px 0 0 var(--magenta);
        }

        /* "Showing X · Show all" filter chip above the map. */
        .vyt-map-filter-bar {
            display: none; align-items: center; gap: 10px;
            padding: 10px 22px; background: #faf5ff;
            border-bottom: 1px solid var(--line); font-size: 13px;
        }
        .vyt-map-filter-bar.is-on { display: flex; }
        .vyt-map-filter-bar .label { color: var(--muted); }
        .vyt-map-filter-bar .name { font-weight: 600; color: var(--ink); }
        .vyt-map-filter-bar button {
            margin-left: auto; background: var(--gradient); color:#fff;
            border: 0; padding: 5px 12px; border-radius: 999px;
            font-size: 12px; font-weight: 600; cursor: pointer;
        }
        .vyt-map-filter-bar button:hover { opacity: .92; }
    </style>
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
                <span class="vyt-section-meta">Click a row to zoom the map to that listing</span>
            </div>
            <table class="vyt-table" id="vyt-listings-table">
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
                        <tr data-listing-id="{{ $listing->id }}"
                            data-listing-title="{{ $listing->title }}"
                            tabindex="0" role="button"
                            aria-label="Zoom map to {{ $listing->title }}">
                            <td>
                                <a href="{{ route('properties.show', $listing) }}"
                                   onclick="event.stopPropagation();">{{ $listing->title }}</a>
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
                <span class="vyt-section-meta">{{ count($pinPoints) }} {{ Str::plural('city', count($pinPoints)) }} · click a pin to zoom</span>
            </div>

            <div id="vyt-map-filter-bar" class="vyt-map-filter-bar" role="status">
                <span class="label">Showing</span>
                <span class="name" id="vyt-map-filter-name">—</span>
                <button type="button" id="vyt-map-filter-clear">Show all listings</button>
            </div>

            @if (count($pinPoints) === 0)
                <div class="vyt-card-empty">
                    No geo-resolved views yet. GeoIP data will appear here as real visits land
                    on your listings.
                </div>
            @else
                <div id="vyt-views-map"
                     style="height: 420px; width: 100%;"
                     data-pins="{{ json_encode($pinPoints) }}"
                     data-pins-by-listing="{{ json_encode($pinsByListing) }}"
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

                    var allPins = [];
                    var byListing = {};
                    try { allPins = JSON.parse(mapEl.dataset.pins || '[]'); } catch (e) {}
                    try { byListing = JSON.parse(mapEl.dataset.pinsByListing || '{}'); } catch (e) {}
                    if (!allPins.length) return;

                    var map = L.map(mapEl, { scrollWheelZoom: false, zoomControl: true });

                    // Prefer Mapbox tiles when a public token is wired; fall back to OSM.
                    var mbToken = mapEl.dataset.mapboxToken;
                    var mbStyle = mapEl.dataset.mapboxStyle || 'mapbox/light-v11';
                    if (mbToken) {
                        L.tileLayer(
                            'https://api.mapbox.com/styles/v1/' + mbStyle + '/tiles/512/{z}/{x}/{y}@2x?access_token=' + mbToken,
                            {
                                tileSize: 512, zoomOffset: -1, maxZoom: 19,
                                attribution: '&copy; <a href="https://www.mapbox.com/about/maps/">Mapbox</a> &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                            }
                        ).addTo(map);
                    } else {
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            maxZoom: 19,
                            attribution: '&copy; OpenStreetMap'
                        }).addTo(map);
                    }

                    // Single layer group we clear + repopulate when the active
                    // filter changes. Avoids leaking markers between switches.
                    var markersLayer = L.layerGroup().addTo(map);

                    function pinRadius(count, maxCount) {
                        return 6 + Math.round(14 * (count / Math.max(maxCount, 1)));
                    }

                    function labelFor(p) {
                        return (p.city || p.country || '—') +
                               (p.city && p.country ? ', ' + p.country : '') +
                               ' · ' + p.count + ' view' + (p.count === 1 ? '' : 's');
                    }

                    // Renders a given pin set: clears prior markers, draws fresh,
                    // and fits the map's viewport to the new bounds.
                    function renderPins(pins, opts) {
                        opts = opts || {};
                        markersLayer.clearLayers();
                        if (!pins || !pins.length) return;

                        var maxCount = Math.max.apply(null, pins.map(function (p) { return p.count; }));
                        var bounds = [];

                        pins.forEach(function (p) {
                            var marker = L.circleMarker([p.lat, p.lng], {
                                radius: pinRadius(p.count, maxCount),
                                color: '#7B2CBF',
                                fillColor: '#D63384',
                                fillOpacity: 0.55,
                                weight: 2
                            }).addTo(markersLayer);
                            marker.bindTooltip(labelFor(p), { direction: 'top', opacity: 0.92 });
                            // Click a pin → zoom in to that city/country.
                            marker.on('click', function () {
                                map.flyTo([p.lat, p.lng], Math.max(map.getZoom(), 6), { duration: 0.7 });
                                marker.openTooltip();
                            });
                            bounds.push([p.lat, p.lng]);
                        });

                        // For a single pin, set a comfortable zoom; otherwise fit to bounds.
                        var animate = opts.animate !== false;
                        if (bounds.length === 1) {
                            map.flyTo(bounds[0], 5, { duration: animate ? 0.7 : 0 });
                        } else {
                            map.flyToBounds(bounds, { padding: [30, 30], maxZoom: 7, duration: animate ? 0.7 : 0 });
                        }
                    }

                    // Initial render: all listings.
                    renderPins(allPins, { animate: false });

                    // Per-listing row clicks filter the map. Clicking the active
                    // row again toggles back to the global view.
                    var table = document.getElementById('vyt-listings-table');
                    var filterBar = document.getElementById('vyt-map-filter-bar');
                    var filterName = document.getElementById('vyt-map-filter-name');
                    var clearBtn = document.getElementById('vyt-map-filter-clear');
                    var activeRow = null;

                    function setActive(row) {
                        if (activeRow) activeRow.classList.remove('is-active');
                        activeRow = row;
                        if (activeRow) {
                            activeRow.classList.add('is-active');
                            filterName.textContent = activeRow.dataset.listingTitle;
                            filterBar.classList.add('is-on');
                        } else {
                            filterBar.classList.remove('is-on');
                        }
                    }

                    function filterToListing(row) {
                        if (!row) return;
                        if (row === activeRow) {
                            // Toggle off — back to all.
                            setActive(null);
                            renderPins(allPins);
                            return;
                        }
                        var id = row.dataset.listingId;
                        var pins = byListing[id] || [];
                        setActive(row);
                        renderPins(pins);
                    }

                    if (table) {
                        table.addEventListener('click', function (e) {
                            // Don't hijack the listing-title <a> click — it stops
                            // propagation itself, but belt-and-braces here too.
                            if (e.target.closest('a')) return;
                            var row = e.target.closest('tr[data-listing-id]');
                            if (row) filterToListing(row);
                        });
                        table.addEventListener('keydown', function (e) {
                            if (e.key !== 'Enter' && e.key !== ' ') return;
                            var row = e.target.closest('tr[data-listing-id]');
                            if (row) { e.preventDefault(); filterToListing(row); }
                        });
                    }

                    if (clearBtn) {
                        clearBtn.addEventListener('click', function () {
                            setActive(null);
                            renderPins(allPins);
                        });
                    }
                })();
            </script>
        @endpush
    @endif
</section>

@extends('dashboard.layout')

@section('eyebrow', 'Admin')
@section('title', 'Activity map')

@push('head')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <style>
        .vyt-viewswitch { display:flex; gap:4px; margin-bottom:14px; }
        .vyt-viewswitch a {
            padding:8px 16px; font-size:13.5px; border:1px solid var(--line);
            border-radius:8px; color:var(--muted); background:#fff;
        }
        .vyt-viewswitch a.is-current { color:#fff; background:var(--gradient); border-color:transparent; font-weight:600; }

        .vyt-maplayers { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:14px; }
        .vyt-maplayers a {
            padding:6px 12px; font-size:12.5px; border:1px solid var(--line);
            border-radius:999px; color:var(--muted); background:#fff;
        }
        .vyt-maplayers a.is-current { color:var(--ink); border-color:var(--magenta); font-weight:600; }

        #vyt-activity-map { height:520px; border:1px solid var(--line); border-radius:14px; }
        .vyt-pin-popup h4 { margin:0 0 6px; font-size:14px; }
        .vyt-pin-popup ul { margin:0; padding-left:16px; font-size:12.5px; }
    </style>
@endpush

@section('content')
    <div class="vyt-viewswitch">
        <a href="{{ route('admin.activity.log') }}">List view</a>
        <a href="{{ route('admin.activity.map') }}" class="is-current" aria-current="page">Map view</a>
    </div>

    <div class="vyt-maplayers">
        <a href="{{ route('admin.activity.map', array_merge($filters, ['layer' => 'all'])) }}"
           class="{{ $layer === 'all' ? 'is-current' : '' }}">All activity</a>
        @foreach ($layers as $key => $definition)
            <a href="{{ route('admin.activity.map', array_merge($filters, ['layer' => $key])) }}"
               class="{{ $layer === $key ? 'is-current' : '' }}">{{ $definition['label'] }}</a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('admin.activity.map') }}"
          style="display:flex;gap:10px;align-items:flex-end;margin-bottom:14px;flex-wrap:wrap;">
        <input type="hidden" name="layer" value="{{ $layer }}">
        <div class="vyt-field">
            <label for="m-from" style="font-size:11px;text-transform:uppercase;color:var(--muted);">From</label>
            <input id="m-from" name="from" type="date" value="{{ $filters['from'] ?? '' }}"
                   style="padding:8px 10px;border:1px solid var(--line);border-radius:8px;">
        </div>
        <div class="vyt-field">
            <label for="m-to" style="font-size:11px;text-transform:uppercase;color:var(--muted);">To</label>
            <input id="m-to" name="to" type="date" value="{{ $filters['to'] ?? '' }}"
                   style="padding:8px 10px;border:1px solid var(--line);border-radius:8px;">
        </div>
        <button type="submit" class="vyt-save" style="padding:9px 18px;font-size:13.5px;">Apply</button>
    </form>

    @if ($pins->isEmpty())
        <div style="background:#fff;border:1px solid var(--line);border-radius:14px;padding:26px;text-align:center;color:var(--muted);">
            <p style="margin:0 0 6px;"><strong>No located activity for this filter.</strong></p>
            <p style="margin:0;font-size:13.5px;">
                Events are plotted from approximate GeoIP. Activity whose IP could not be
                resolved is recorded in the <a href="{{ route('admin.activity.log') }}">list view</a>
                but has no coordinates to plot.
            </p>
        </div>
    @else
        <div id="vyt-activity-map" data-pins="{{ json_encode($pins) }}"></div>

        <p class="vyt-faint" style="font-size:12.5px;margin-top:12px;">
            {{ $pins->count() }} location(s), {{ number_format($pins->sum('total')) }} event(s).
            Positions are <strong>approximate GeoIP</strong> derived from the IP address —
            a city-level estimate, never a physical address.
        </p>
    @endif
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
    var el = document.getElementById('vyt-activity-map');
    if (!el || !window.L) { return; }

    var pins = JSON.parse(el.dataset.pins || '[]');
    if (!pins.length) { return; }

    var map = L.map(el);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 18,
    }).addTo(map);

    var bounds = [];
    var largest = pins.reduce(function (max, p) { return Math.max(max, p.total); }, 1);

    pins.forEach(function (pin) {
        // Radius scales with the square root of the count: area then grows in
        // proportion to the number, which is how a reader judges a circle.
        // Scaling the radius directly makes a busy city look overwhelming.
        var radius = 8 + 22 * Math.sqrt(pin.total / largest);

        var rows = Object.keys(pin.breakdown).map(function (k) {
            return '<li>' + k + ': ' + pin.breakdown[k] + '</li>';
        }).join('');

        L.circleMarker([pin.lat, pin.lng], {
            radius: radius,
            color: '#D63384',
            fillColor: '#D63384',
            fillOpacity: 0.35,
            weight: 2,
        }).addTo(map).bindPopup(
            '<div class="vyt-pin-popup"><h4>' + pin.label + '</h4>'
            + '<ul>' + rows + '</ul>'
            + '<p style="margin:6px 0 0;font-size:11.5px;color:#6b7280;">Approximate GeoIP</p></div>'
        );

        bounds.push([pin.lat, pin.lng]);
    });

    map.fitBounds(bounds, { padding: [40, 40], maxZoom: 10 });
})();
</script>
@endpush

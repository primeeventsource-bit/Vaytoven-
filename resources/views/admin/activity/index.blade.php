@extends('dashboard.layout')

@section('eyebrow', 'Admin')
@section('title', 'Listing activity')

@section('content')

<div class="vyt-card-header" style="padding:0 0 18px;">
    <h3>Listing activity</h3>
    <span class="vyt-section-meta">{{ $listings->count() }} {{ Str::plural('listing', $listings->count()) }} · last 30 days</span>
</div>

<form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:22px;">
    <select name="owner" style="flex:1;min-width:240px;padding:9px 12px;border:1px solid var(--line);border-radius:9px;font:inherit;">
        <option value="">Every owner</option>
        @foreach ($owners as $owner)
            <option value="{{ $owner->id }}" @selected($ownerId === $owner->id)>
                {{ $owner->name }} — {{ $owner->email }}
            </option>
        @endforeach
    </select>
    <button type="submit" class="site-cta" style="padding:9px 20px;font-size:14px;">Filter</button>
    @if ($ownerId)
        <a href="{{ route('admin.activity.index') }}" style="align-self:center;font-size:14px;">Clear</a>
    @endif
</form>

{{-- Views, geography and the visitor map. The same panel hosts and members
     see for their own listings — here across every listing on the platform,
     rendered from the same ListingAnalytics service so the numbers agree. --}}
@include('partials.listing-analytics', [
    'listings'        => $listings,
    'totalViews30d'   => $totalViews30d,
    'totalViews7d'    => $totalViews7d,
    'uniqueCountries' => $uniqueCountries,
    'perListingStats' => $perListingStats,
    'pinPoints'       => $pinPoints,
    'pinsByListing'   => $pinsByListing,
    'mapboxToken'     => $mapboxToken,
    'mapboxStyle'     => $mapboxStyle,
])

{{-- Clicks ---------------------------------------------------------------- --}}
<section class="vyt-section" style="margin-top:32px;">
    <div class="vyt-card">
        <div class="vyt-card-header">
            <h3>Clicks</h3>
            <span class="vyt-section-meta">
                {{ number_format($clicks['total_30d']) }} in 30 days ·
                {{ number_format($clicks['total_7d']) }} in 7
            </span>
        </div>

        @if (empty($clicks['by_cta']))
            <div class="vyt-card-empty">
                No click events recorded in the last 30 days.
            </div>
        @else
            <div class="vyt-card-body">
                <h4 style="font-size:13px;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin:0 0 12px;">
                    By audience
                </h4>
                <ul class="vyt-kv" style="margin-bottom:24px;">
                    @foreach ($clicks['by_audience'] as $audience => $count)
                        <li>
                            <span class="k">{{ ucfirst($audience) }}</span>
                            <span class="v">{{ number_format($count) }}</span>
                        </li>
                    @endforeach
                </ul>

                <h4 style="font-size:13px;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin:0 0 12px;">
                    Top calls to action
                </h4>
                <table class="vyt-table">
                    <thead>
                        <tr><th>CTA</th><th style="text-align:right;">Clicks</th><th style="width:40%;"></th></tr>
                    </thead>
                    <tbody>
                        @php($max = max($clicks['by_cta'] ?: [1]))
                        @foreach ($clicks['by_cta'] as $cta => $count)
                            <tr>
                                <td class="vyt-mono" style="font-size:12.5px;">{{ $cta }}</td>
                                <td style="text-align:right;font-weight:600;">{{ number_format($count) }}</td>
                                <td>
                                    {{-- Proportional bar, so the shape of the
                                         funnel reads without arithmetic. --}}
                                    <div style="background:var(--line);border-radius:999px;height:8px;overflow:hidden;">
                                        <div style="background:var(--gradient);height:100%;width:{{ max(2, round(($count / $max) * 100)) }}%;"></div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</section>
@endsection

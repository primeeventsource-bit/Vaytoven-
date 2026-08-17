<div class="vyt-card">
    <div class="vyt-card-header">
        <h3>Properties</h3>
        <span class="vyt-section-meta">
            {{ $properties->count() }} of
            {{ $package?->package->propertyCount() ?? '—' }} allowed
        </span>
    </div>

    @if ($properties->isEmpty())
        <div class="vyt-card-empty">
            No properties yet.
            <a href="{{ route('admin.properties.create') }}">Create one for them</a>.
        </div>
    @else
        @if ($package && $properties->count() > $package->package->propertyCount())
            <div class="site-alert" style="margin:16px 22px;background:#fef2f2;border-color:#fecaca;color:#991b1b;">
                <strong>Over the package allowance.</strong>
                {{ $package->package->label() }} covers {{ $package->package->propertyCount() }},
                and this member has {{ $properties->count() }}. Nothing is enforced automatically —
                decide whether to upgrade them or pause a listing.
            </div>
        @endif

        <table class="vyt-table">
            <thead>
                <tr><th>Listing</th><th>Location</th><th style="text-align:right;">Nightly</th><th>Status</th><th style="text-align:right;">Views 30d</th></tr>
            </thead>
            <tbody>
                @foreach ($properties as $property)
                    <tr>
                        <td><a href="{{ route('properties.show', $property) }}">{{ $property->title }}</a></td>
                        <td class="vyt-faint">{{ $property->city ?: '—' }}@if ($property->country), {{ $property->country }}@endif</td>
                        <td style="text-align:right;">${{ number_format($property->base_nightly_cents / 100, 2) }}</td>
                        <td><span class="vyt-pill">{{ ucfirst(str_replace('_', ' ', $property->status->value ?? $property->status)) }}</span></td>
                        <td style="text-align:right;">{{ number_format($perListingStats[$property->id]['views_30d'] ?? 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

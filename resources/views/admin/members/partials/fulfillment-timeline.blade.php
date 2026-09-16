<div class="vyt-card" style="margin-bottom:18px;" id="fulfillment-timeline">
    <div class="vyt-card-header">
        <h3>Advertisement fulfillment timeline</h3>
        <span class="vyt-section-meta">Oldest first · each event from its own record</span>
    </div>

    @if ($fulfillmentTimeline->isEmpty())
        <div class="vyt-card-empty">Nothing recorded for this member yet.</div>
    @else
        <ol style="list-style:none;margin:0;padding:6px 22px 18px;">
            @foreach ($fulfillmentTimeline as $point)
                <li style="padding:14px 0;border-top:{{ $loop->first ? '0' : '1px solid var(--line)' }};">
                    <div style="display:flex;gap:10px;align-items:baseline;flex-wrap:wrap;">
                        <span class="vyt-mono" style="color:var(--purple);">{{ et($point->occurredAt, 'm/d/Y g:i:s A') }}</span>
                        <strong style="text-transform:uppercase;letter-spacing:.02em;">{{ $point->label }}</strong>
                        <span class="vyt-pill" style="{{ $point->isMemberEvent() ? 'background:#ecfdf5;color:#047857;' : '' }}">Performed by: {{ $point->performedBy }}</span>
                    </div>
                    <div style="font-size:13px;margin-top:4px;display:flex;gap:6px 16px;flex-wrap:wrap;">
                        @if ($point->actorName && $point->isMemberEvent())<span>Member: {{ $point->actorName }}</span>@endif
                        @if ($point->advertisementId)<span>Ad: <span class="vyt-mono">{{ $point->advertisementId }}</span></span>@endif
                        @if ($point->ipAddress)<span>IP: <span class="vyt-mono">{{ $point->ipAddress }}</span></span>@endif
                        @if ($point->approximateLocation())<span>Approx. location: {{ $point->approximateLocation() }}</span>@endif
                        @if ($point->comparison && $point->isMemberEvent())
                            <span>Address comparison: <strong>{{ $point->comparisonLabel() }}</strong>@if ($point->comparisonFromCurrentAddress) <span class="vyt-faint">(vs. current address)</span>@endif</span>
                        @endif
                        @if ($point->ipAddress)
                            <span>Device: {{ $point->deviceLabel() }}</span>
                            <span>Browser: {{ $point->browser ?? '—' }}</span>
                            <span>OS: {{ $point->platform ?? '—' }}</span>
                        @endif
                        @if ($point->sessionId)<span>Session: <a class="vyt-mono" href="{{ route('admin.activity.session', $point->sessionId) }}">{{ $point->sessionId }}</a></span>@endif
                    </div>
                    @if ($point->note)
                        <div class="vyt-faint" style="font-size:12px;margin-top:3px;">{{ $point->note }}</div>
                    @endif
                </li>
            @endforeach
        </ol>
        <div class="vyt-faint" style="font-size:11.5px;padding:0 22px 14px;">{{ \App\Support\Location\LocationComparison::DISCLOSURE }}</div>
    @endif
</div>

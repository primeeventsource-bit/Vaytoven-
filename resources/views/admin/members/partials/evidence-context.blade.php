{{-- One event's own network evidence. Expects $point (EvidencePoint). --}}
@php($cmp = $point->comparisonStatus())
@php($tone = match ($cmp) { 'consistent' => '#047857', 'nearby' => '#1d4ed8', 'different' => '#b45309', default => '#6b7280' })
<ul class="vyt-kv" style="font-size:13px;">
    <li><span class="k">IP</span><span class="v vyt-mono">{{ $point->ipAddress ?? '—' }}</span></li>
    <li><span class="k">Approximate IP-based location</span><span class="v">{{ $point->approximateLocation() ?? 'Unresolved' }}</span></li>
    <li>
        <span class="k">Address comparison</span>
        <span class="v">
            <span style="color:{{ $tone }};font-weight:700;">{{ $cmp === 'consistent' ? '✓ ' : '' }}{{ $point->comparisonLabel() }}</span>
            @if (! empty($point->comparison['reason']))
                <span class="vyt-faint" style="display:block;font-size:11.5px;">{{ $point->comparison['reason'] }}@if ($point->comparisonFromCurrentAddress) Compared with the current address on file.@endif</span>
            @endif
        </span>
    </li>
    <li><span class="k">Device</span><span class="v">{{ $point->deviceLabel() }}@if ($point->deviceHint()) — {{ $point->deviceHint() }}@endif</span></li>
    <li><span class="k">Browser</span><span class="v">{{ $point->browser ?? '—' }}</span></li>
    <li><span class="k">Operating system</span><span class="v">{{ $point->platform ?? '—' }}</span></li>
    <li>
        <span class="k">Session</span>
        <span class="v">
            @if ($point->sessionId)
                <a class="vyt-mono" href="{{ route('admin.activity.session', $point->sessionId) }}">{{ $point->sessionId }}</a>
            @else
                —
            @endif
        </span>
    </li>
    <li><span class="k">Server time</span><span class="v vyt-mono">{{ et($point->occurredAt, 'm/d/Y g:i:s A') }}</span></li>
    @if ($point->note)
        <li><span class="k">Note</span><span class="v">{{ $point->note }}</span></li>
    @endif
</ul>

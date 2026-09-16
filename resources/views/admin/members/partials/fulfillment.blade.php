@php($F = \App\Services\Fulfillment\AdvertisementFulfillment::class)

<div class="vyt-card" style="margin-bottom:18px;">
    <div class="vyt-card-header">
        <h3>Advertisement fulfillment / acceptance</h3>
        <span class="vyt-section-meta">Member-generated evidence only · staff activity is never counted</span>
    </div>

    @if ($fulfillment->isEmpty())
        <div class="vyt-card-empty">This member owns no active advertisement and has no fulfillment records.</div>
    @else
        <div style="overflow-x:auto;">
        <table class="vyt-table">
            <thead>
                <tr>
                    <th>Advertisement ID</th><th>Activated at</th><th>First member login</th>
                    <th>First ad access</th><th>Accepted</th><th>Accepted at</th>
                    <th>Member IP</th><th>Approx. IP-based location</th><th>Address comparison</th><th>Device / browser / OS</th><th>Session ID</th>
                    <th>Status</th><th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($fulfillment as $row)
                    @php($p = $row['property'])
                    @php($ctx = $row['accepted'] ?? $row['first_access'])
                    <tr>
                        <td>
                            <span class="vyt-mono">{{ $p->reference ?? '#'.$p->id }}</span>
                            <span class="vyt-faint" style="display:block;font-size:11.5px;">{{ Str::limit($p->title, 40) }}</span>
                        </td>
                        <td class="vyt-faint">{{ $row['activated_at'] ? et($row['activated_at'], 'm/d/Y g:i A') : 'Not recorded' }}</td>
                        <td class="vyt-faint">{{ $row['first_login'] ? et($row['first_login']->occurredAt, 'm/d/Y g:i A') : 'Not recorded' }}</td>
                        <td class="vyt-faint">{{ $row['first_access'] ? et($row['first_access']->occurred_at, 'm/d/Y g:i:s A') : '—' }}</td>
                        <td>{{ $row['accepted'] ? 'Yes' : 'No' }}</td>
                        <td class="vyt-faint">{{ $row['accepted'] ? et($row['accepted']->occurred_at, 'm/d/Y g:i:s A') : '—' }}</td>
                        <td class="vyt-mono">{{ $ctx?->ip_address ?? '—' }}</td>
                        <td class="vyt-faint">{{ $ctx?->location() ?? '—' }}</td>
                        <td>{{ $ctx ? \App\Support\Location\LocationComparison::labelFor($ctx->location_comparison) : '—' }}</td>
                        <td class="vyt-faint">{{ $ctx ? \App\Services\Tracking\ActivityRecorder::deviceLabel($ctx->device_type).' · '.$ctx->browser.' · '.$ctx->platform : '—' }}</td>
                        <td>
                            @if ($ctx?->session_id)
                                <a class="vyt-mono" href="{{ route('admin.activity.session', $ctx->session_id) }}">{{ $ctx->session_id }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @php($tone = match ($row['status']) { $F::STATUS_ACCEPTED => 'background:#ecfdf5;color:#047857;', $F::STATUS_ACCESSED => 'background:#eff6ff;color:#1d4ed8;', default => 'background:#fef3c7;color:#92400e;' })
                            <span class="vyt-pill" style="{{ $tone }}font-weight:700;">{{ $F::statusLabel($row['status']) }}</span>
                        </td>
                        <td>
                            <a href="{{ route('admin.members.fulfillment-certificate', [$member, $p]) }}" style="white-space:nowrap;font-size:13px;">Download Fulfillment Certificate</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    @endif
</div>

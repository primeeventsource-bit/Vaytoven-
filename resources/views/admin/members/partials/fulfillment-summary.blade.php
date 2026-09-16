{{-- FULFILLMENT & ACCEPTANCE — the chain at a glance:
     activation → client login → incentive → client access → client acceptance.
     Every line is read from its own stored record. --}}
@php
    $F   = \App\Services\Fulfillment\AdvertisementFulfillment::class;
    $I   = \App\Services\Fulfillment\MemberIncentive::class;
    $DR  = \App\Services\Fulfillment\DiningRewardsIncentive::class;
    $EP  = \App\Services\Fulfillment\EvidencePoint::class;
    $address = $member->addressOnFile();
    $tick = fn ($ok) => $ok ? '<span style="color:#047857;font-weight:700;">✓</span>' : '<span style="color:#9ca3af;">—</span>';
    $when = fn ($at) => $at ? et($at, 'm/d/Y g:i A') : 'Not recorded';
@endphp

<div class="vyt-card" style="margin-bottom:22px;border:2px solid #f5d0e6;">
    <div class="vyt-card-header">
        <h3>Fulfillment &amp; acceptance</h3>
        <span class="vyt-section-meta">Member-generated evidence only · staff activity is never counted</span>
    </div>
    <div class="vyt-card-body" style="display:grid;gap:18px;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));">

        <div>
            <div class="vyt-faint" style="font-size:11px;letter-spacing:.08em;text-transform:uppercase;margin-bottom:6px;">Member</div>
            <div style="font-weight:700;">{{ $member->name }} — <span class="vyt-mono">{{ $member->member_id ?: '#'.$member->id }}</span></div>
            <div class="vyt-faint" style="font-size:12.5px;">{{ $member->email }}</div>
            <div style="margin-top:8px;font-size:13px;">
                <div class="vyt-faint" style="font-size:11px;text-transform:uppercase;letter-spacing:.06em;">Address on file</div>
                @if ($address->oneLine())
                    @foreach ($address->lines() as $line)<div>{{ $line }}</div>@endforeach
                @else
                    <span style="color:#92400e;">No address on file</span> —
                    <a href="{{ route('admin.users.edit', $member) }}">add one</a> so logins can be compared.
                @endif
            </div>

            <div class="vyt-faint" style="font-size:11px;letter-spacing:.08em;text-transform:uppercase;margin:14px 0 6px;">Account</div>
            <div style="font-size:13px;">First login: {!! $tick($firstLogin) !!} {{ $firstLogin ? $when($firstLogin->occurredAt) : 'Never signed in' }}</div>
        </div>

        <div>
            <div class="vyt-faint" style="font-size:11px;letter-spacing:.08em;text-transform:uppercase;margin-bottom:6px;">Incentive</div>
            <div style="font-weight:700;">{{ $DR::NAME }}</div>
            <div class="vyt-faint" style="font-size:12.5px;">{{ $DR::PROVIDER }}</div>
            <div style="font-size:13px;margin-top:6px;display:grid;gap:3px;">
                <div>Presented: {!! $tick($incentive['presented']) !!} {{ $incentive['presented'] ? $when($incentive['presented']->occurred_at) : '' }}</div>
                <div>Acknowledged: {!! $tick($incentive['acknowledged']) !!} {{ $incentive['acknowledged'] ? $when($incentive['acknowledged']->occurred_at) : '' }}</div>
                <div>Delivered:
                    @if ($incentive['delivered'])
                        {!! $tick(true) !!} {{ $when($incentive['delivered']->occurred_at) }}
                        <span class="vyt-faint" style="display:block;font-size:11.5px;">{{ $incentive['delivered']->delivery_method }} · ref {{ $incentive['delivered']->delivery_reference }}</span>
                    @else
                        <span class="vyt-faint">No delivery evidence recorded</span>
                    @endif
                </div>
                <div style="margin-top:4px;"><span class="vyt-pill" style="font-weight:700;">{{ $I::statusLabel($incentive['status']) }}</span></div>
            </div>
            @if (! $incentive['eligible'])
                <div class="vyt-faint" style="font-size:12px;margin-top:6px;">Not a Managed Listing Program client account.</div>
            @endif
        </div>

        <div>
            <div class="vyt-faint" style="font-size:11px;letter-spacing:.08em;text-transform:uppercase;margin-bottom:6px;">Advertisement</div>
            @forelse ($fulfillment as $row)
                <div style="font-size:13px;display:grid;gap:3px;{{ ! $loop->first ? 'margin-top:12px;padding-top:12px;border-top:1px solid var(--line);' : '' }}">
                    <div>Advertisement ID: <span class="vyt-mono">{{ $row['property']->reference }}</span></div>
                    <div>Activated: {!! $tick($row['activated_at']) !!} {{ $when($row['activated_at']) }}</div>
                    <div>First access: {!! $tick($row['first_access']) !!} {{ $row['first_access'] ? $when($row['first_access']->occurred_at) : '' }}</div>
                    <div>Accepted: {!! $tick($row['accepted']) !!} {{ $row['accepted'] ? $when($row['accepted']->occurred_at) : '' }}</div>
                    <div style="margin-top:4px;">
                        @if ($row['status'] === $F::STATUS_ACCEPTED)
                            <span class="vyt-pill" style="background:#ecfdf5;color:#047857;font-weight:700;">✓ Advertisement accessed &amp; accepted</span>
                        @elseif ($row['status'] === $F::STATUS_ACCESSED)
                            <span class="vyt-pill" style="background:#eff6ff;color:#1d4ed8;font-weight:700;">Accessed — not yet accepted</span>
                        @else
                            <span class="vyt-pill" style="background:#fef3c7;color:#92400e;font-weight:700;">Not accessed</span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="vyt-faint" style="font-size:13px;">No active advertisement.</div>
            @endforelse
        </div>

        @php
            $primary = $fulfillment->first();
            $record  = $primary ? ($primary['accepted'] ?? $primary['first_access']) : null;
            $point   = $record ? $EP::fromRecord($record, $member) : $firstLogin;
        @endphp
        <div>
            <div class="vyt-faint" style="font-size:11px;letter-spacing:.08em;text-transform:uppercase;margin-bottom:6px;">
                Access evidence
                @if ($point) <span style="text-transform:none;letter-spacing:0;">· {{ $point->label }}</span> @endif
            </div>
            @if ($point)
                @include('admin.members.partials.evidence-context', ['point' => $point])
            @else
                <div class="vyt-faint" style="font-size:13px;">No member access recorded.</div>
            @endif
        </div>
    </div>

    <div style="padding:12px 22px;border-top:1px solid var(--line);display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
        <a href="{{ route('admin.members.show', ['user' => $member, 'tab' => 'advertising']) }}#fulfillment-timeline"
           style="border:1px solid var(--line);padding:8px 14px;border-radius:8px;font-weight:600;font-size:13px;">View full audit trail</a>
        @foreach ($fulfillment as $row)
            <a href="{{ route('admin.members.fulfillment-certificate', [$member, $row['property']]) }}"
               style="background:var(--gradient);color:#fff;padding:8px 14px;border-radius:8px;font-weight:600;font-size:13px;">
                Download fulfillment certificate{{ $fulfillment->count() > 1 ? ' · '.$row['property']->reference : '' }}
            </a>
        @endforeach
        <span class="vyt-faint" style="font-size:11.5px;flex-basis:100%;">{{ \App\Support\Location\LocationComparison::DISCLOSURE }}</span>
    </div>
</div>

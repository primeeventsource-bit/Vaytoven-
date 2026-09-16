<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Advertisement Service Fulfillment Record — {{ $certificateNumber }}</title>
    <style>
        @page { margin: 1.5cm 1.4cm; }
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 9.2pt; color: #1d1f21; line-height: 1.42; }
        .brand { color: #a21caf; font-weight: 700; letter-spacing: 0.06em; font-size: 11pt; }
        h1 { font-size: 15pt; margin: 2px 0 2px; }
        h2 { font-size: 10.5pt; margin: 14px 0 6px; padding-bottom: 3px; border-bottom: 1px solid #d1d5db; letter-spacing: .03em; }
        .meta { color: #6b7280; font-size: 8.3pt; }
        table { width: 100%; border-collapse: collapse; margin: 2px 0 8px; }
        th { text-align: left; width: 36%; padding: 4px 8px; color: #374151; font-weight: 600; background: #f9fafb; border-bottom: 1px solid #eef0f2; vertical-align: top; }
        td { padding: 4px 8px; border-bottom: 1px solid #eef0f2; vertical-align: top; }
        .grid th { width: auto; background: #f3f4f6; font-size: 8.3pt; }
        .grid td { font-size: 8.3pt; }
        .mono { font-family: DejaVu Sans Mono, monospace; font-size: 8pt; }
        .nr { color: #9ca3af; font-style: italic; }
        .status { display: inline-block; padding: 1px 8px; border-radius: 999px; font-weight: 700; font-size: 8.3pt; background: #f3f4f6; }
        .quote { border-left: 3px solid #a21caf; padding: 6px 10px; background: #faf5ff; margin: 4px 0; }
        .disclosure { border: 1px solid #fcd34d; background: #fffbeb; padding: 6px 10px; font-size: 8.3pt; margin: 6px 0; }
        .footer { margin-top: 16px; padding-top: 8px; border-top: 1px solid #d1d5db; font-size: 7.6pt; color: #6b7280; }
    </style>
</head>
<body>
@php($nr = \App\Services\Fulfillment\FulfillmentCertificate::NOT_RECORDED)
@php($v = fn ($value) => $value === $nr ? '<span class="nr">'.$nr.'</span>' : e($value))

<div class="brand">VAYTOVEN TECHNOLOGIES LLC</div>
<h1>ADVERTISEMENT SERVICE FULFILLMENT RECORD</h1>
<div class="meta">
    Certificate No. <span class="mono">{{ $certificateNumber }}</span> &middot;
    Generated {{ $generatedAt }} &middot;
    Acceptance status <span class="status">{{ $status }}</span>
</div>

<h2>MEMBER INFORMATION</h2>
<table>
    <tr><th>Member Name</th><td>{!! $v($memberName) !!}</td></tr>
    <tr><th>Member ID</th><td class="mono">{!! $v($memberId) !!}</td></tr>
    <tr><th>Email</th><td>{!! $v($memberEmail) !!}</td></tr>
    <tr>
        <th>Address on File</th>
        <td>
            @if ($addressLines)
                @foreach ($addressLines as $line){{ $line }}<br>@endforeach
                <span class="meta">({{ $addressBasis }})</span>
            @else
                <span class="nr">{{ $nr }}</span>
            @endif
        </td>
    </tr>
</table>

<h2>ADVERTISEMENT INFORMATION</h2>
<table>
    <tr><th>Advertisement ID</th><td class="mono">{{ $advertisementId }}</td></tr>
    <tr><th>Advertisement</th><td>{{ $advertisementTitle }}</td></tr>
    <tr><th>Advertisement URL</th><td class="mono">{{ $advertisementUrl }}</td></tr>
    <tr><th>Package / Program</th><td>{!! $v($package) !!}</td></tr>
    <tr><th>Activation Timestamp</th><td>{!! $v($activatedAt) !!}</td></tr>
</table>

<h2>MEMBER ACCESS INFORMATION</h2>
<table>
    <tr><th>First Member Login</th><td>{!! $v($firstLoginAt) !!}</td></tr>
    <tr><th>First Advertisement Access</th><td>{!! $v($firstAccessAt) !!}</td></tr>
    <tr><th>Acceptance Timestamp</th><td>{!! $v($acceptedAt) !!}</td></tr>
    <tr><th>IP Address @if($contextLabel)<br><span class="meta">({{ $contextLabel }})</span>@endif</th><td class="mono">{!! $v($ipAddress) !!}</td></tr>
    <tr><th>Approximate IP-Based Location</th><td>{!! $v($location) !!}</td></tr>
    <tr>
        <th>Address / GeoIP Comparison</th>
        <td>
            {!! $v($comparison) !!}
            @if ($comparisonSentence)<br><span class="meta">{{ $comparisonSentence }} {{ $comparisonReason }}</span>@endif
        </td>
    </tr>
    <tr><th>Device Category</th><td>{!! $v($device) !!}</td></tr>
    <tr><th>Browser</th><td>{!! $v($browser) !!}</td></tr>
    <tr><th>Operating System</th><td>{!! $v($operatingSystem) !!}</td></tr>
    <tr><th>Session ID</th><td class="mono">{!! $v($sessionId) !!}</td></tr>
</table>
<div class="disclosure">{{ $disclosure }}</div>

<h2>ACCEPTANCE STATEMENT</h2>
@if ($acknowledgementText)
    <div class="quote">{{ $acknowledgementText }}</div>
    <div class="meta">Version <span class="mono">{{ $acknowledgementVersion }}</span> &middot; SHA-256 <span class="mono">{{ $acknowledgementHash }}</span></div>
@else
    <p class="nr">{{ $nr }} — the member has not accepted this advertisement.</p>
@endif

@if ($events->isNotEmpty())
    <h2>EVENT RECORDS (each with its own context)</h2>
    <table class="grid">
        <thead>
            <tr><th>Event</th><th>Server time</th><th>IP / Approximate IP-Based Location</th><th>Address comparison</th><th>Device / Browser / OS</th><th>Session</th></tr>
        </thead>
        <tbody>
        @foreach ($events as $e)
            <tr>
                <td>{{ $e->label }}@if($e->note)<br><span class="meta">{{ $e->note }}</span>@endif</td>
                <td class="mono">{{ et($e->occurredAt, 'm/d/Y g:i:s A') }}</td>
                <td><span class="mono">{{ $e->ipAddress ?? '—' }}</span><br>{{ $e->approximateLocation() ?? 'Unresolved' }}</td>
                <td>{{ $e->comparisonLabel() }}@if($e->comparisonFromCurrentAddress)<br><span class="meta">vs. current address</span>@endif</td>
                <td>{{ $e->deviceLabel() }}<br>{{ $e->browser ?? '—' }} / {{ $e->platform ?? '—' }}</td>
                <td class="mono">{{ $e->sessionId ?? '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

@if ($records->isNotEmpty())
    <h2>SOURCE AUDIT RECORDS</h2>
    <table class="grid">
        <thead><tr><th>Record</th><th>Event</th><th>Source</th><th>SHA-256 (first 16)</th></tr></thead>
        <tbody>
        @foreach ($records as $r)
            <tr>
                <td class="mono">{{ $r->record_uuid }}</td>
                <td>{{ $r->event }}</td>
                <td>{{ $r->source }}</td>
                <td class="mono">{{ substr($r->record_hash, 0, 16) }}…</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

@if ($incentive && $incentive['eligible'])
    <h2>ENROLLMENT INCENTIVE</h2>
    <table>
        <tr><th>Incentive</th><td>{{ \App\Services\Fulfillment\DiningRewardsIncentive::NAME }} · {{ \App\Services\Fulfillment\DiningRewardsIncentive::PROVIDER }}</td></tr>
        <tr><th>Presented</th><td>{!! $incentive['presented'] ? e(et($incentive['presented']->occurred_at, 'm/d/Y g:i:s A')) : '<span class="nr">'.$nr.'</span>' !!}</td></tr>
        <tr><th>Acknowledged</th><td>{!! $incentive['acknowledged'] ? e(et($incentive['acknowledged']->occurred_at, 'm/d/Y g:i:s A')) : '<span class="nr">'.$nr.'</span>' !!}</td></tr>
        <tr><th>Delivered</th><td>{!! $incentive['delivered'] ? e(et($incentive['delivered']->occurred_at, 'm/d/Y g:i:s A').' · '.$incentive['delivered']->delivery_method.' · ref '.$incentive['delivered']->delivery_reference) : '<span class="nr">No delivery evidence recorded</span>' !!}</td></tr>
    </table>
    <div class="meta">Presentation and acknowledgement of the incentive are separate from, and are not, acceptance of the advertisement.</div>
@endif

@if ($corrections->isNotEmpty())
    <h2>CORRECTIONS ON FILE</h2>
    <table class="grid">
        <thead><tr><th>Recorded</th><th>Note</th><th>Record</th></tr></thead>
        <tbody>
        @foreach ($corrections as $c)
            <tr>
                <td class="mono">{{ et($c->occurred_at, 'm/d/Y g:i A') }}</td>
                <td>{{ $c->correction_note }}</td>
                <td class="mono">{{ $c->record_uuid }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

<div class="footer">
    Generated from Vaytoven's stored audit records. Fields marked "{{ $nr }}" have no stored record and have not been
    estimated. Timestamps are server-recorded. {{ $disclosure }} A consistent comparison means the approximate IP location
    is consistent with the member's address area; it does not establish that the member was at that address.
    Access and acceptance records are append-only; each carries a SHA-256 hash of its contents.
    Activity by Vaytoven staff is never recorded as member access or acceptance.
</div>
</body>
</html>

{{--
    The first sign-in record, as a filed document.

    Rendered by dompdf, which supports a conservative subset of CSS — no
    flexbox, no grid. Tables are what survive, so the layout uses them
    deliberately rather than out of habit.

    This exists to be kept. The email announces the event; this is the page
    somebody attaches to a dispute file months later, so it repeats the
    identifying details rather than assuming the covering email is still around.
--}}
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>First sign-in record — {{ $member->email }}</title>
    <style>
        @page { margin: 46px 44px 54px; }

        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2430; line-height: 1.55; }

        h1 { font-size: 22px; margin: 0 0 4px; color: #7b2cbf; }
        h2 {
            font-size: 13px; margin: 24px 0 8px; padding-bottom: 5px;
            border-bottom: 2px solid #d63384; color: #7b2cbf;
        }

        .lead { font-size: 11.5px; color: #444b5a; margin: 0; }
        .meta { font-size: 9px; color: #767e8f; margin-top: 6px; }
        .cover { border-bottom: 3px solid #d63384; padding-bottom: 14px; margin-bottom: 20px; }

        table { width: 100%; border-collapse: collapse; margin: 6px 0 12px; }
        th, td { text-align: left; vertical-align: top; padding: 7px 9px; border-bottom: 1px solid #e6e8ee; }
        th { width: 32%; background: #faf7ff; font-size: 9.5px; text-transform: uppercase; letter-spacing: .05em; color: #59617a; }
        td { font-size: 10.5px; }

        .k { font-family: DejaVu Sans Mono, monospace; font-size: 9.5px; }

        .note {
            background: #fdf2f8; border-left: 3px solid #d63384;
            padding: 9px 12px; margin: 12px 0; font-size: 9.5px; color: #59617a;
        }

        .footer { margin-top: 26px; padding-top: 10px; border-top: 1px solid #e6e8ee; font-size: 9px; color: #767e8f; }
    </style>
</head>
<body>

<div class="cover">
    <h1>First sign-in record</h1>
    <p class="lead">
        Fulfillment complete — the credentials reached the member and the account has been used.
    </p>
    <p class="meta">
        Record generated {{ et(now(), 'F j, Y \a\t g:ia') }} &middot; {{ config('app.legal_entity') }}
    </p>
</div>

<h2>Member</h2>
<table>
    <tr><th>Name</th><td>{{ $member->name }}</td></tr>
    <tr><th>Email</th><td class="k">{{ $member->email }}</td></tr>
    <tr><th>Account created</th><td>{{ et($member->created_at, 'F j, Y \a\t g:ia') }}</td></tr>
    <tr><th>First sign-in</th><td>{{ $context['signed_in_at'] ?? et(now(), 'F j, Y \a\t g:ia') }}</td></tr>
</table>

<h2>Where it was signed in from</h2>
<table>
    <tr><th>IP address</th><td class="k">{{ $context['ip_address'] ?? 'not recorded' }}</td></tr>
    <tr><th>Approximate area</th><td>{{ $context['location'] ?? 'could not be determined' }}</td></tr>
    @if (! empty($context['coordinates']))
        <tr><th>Approximate coordinates</th><td class="k">{{ $context['coordinates'] }}</td></tr>
    @endif
    @if (! empty($context['network']))
        <tr><th>Network</th><td>{{ $context['network'] }}</td></tr>
    @endif
</table>

@php
    $flags = [];
    if (! empty($context['is_vpn'])) { $flags[] = 'a VPN or anonymizing service'; }
    if (! empty($context['is_datacenter'])) { $flags[] = 'a data center rather than a home or mobile network'; }
@endphp

@if ($flags)
    <div class="note">
        <strong>Worth a look.</strong> This sign-in arrived through {{ implode(' and ', $flags) }}.
        Not unusual on its own, but the area above is where that service exits rather than where
        the member is.
    </div>
@endif

<h2>What they signed in on</h2>
<table>
    <tr><th>Device</th><td>{{ ucfirst($context['device_type'] ?? 'unknown') }}</td></tr>
    <tr><th>Platform</th><td>{{ $context['platform'] ?? 'unknown' }}</td></tr>
    <tr><th>Browser</th><td>{{ $context['browser'] ?? 'unknown' }}</td></tr>
    @if (! empty($context['user_agent']))
        <tr><th>Full device string</th><td class="k">{{ $context['user_agent'] }}</td></tr>
    @endif
</table>

<div class="footer">
    {{ config('app.legal_entity') }} &middot; {{ config('app.name') }}<br>
    Generated automatically on first account sign-in.
</div>

</body>
</html>

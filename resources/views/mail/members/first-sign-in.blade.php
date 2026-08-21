@component('mail::message')
# Member signed in for the first time

Fulfilment is complete for this account — the credentials reached the member and they have used them.

@component('mail::table')
| | |
|:---|:---|
| **Member** | {{ $member->name }} |
| **Email** | {{ $member->email }} |
| **Account created** | {{ et($member->created_at, 'F j, Y') }} |
| **First sign-in** | {{ $context['signed_in_at'] ?? et(now(), 'F j, Y \a\t g:ia') }} |
@endcomponent

## Where it was signed in from

@component('mail::table')
| | |
|:---|:---|
| **IP address** | {{ $context['ip_address'] ?? 'not recorded' }} |
| **Approximate area** | {{ $context['location'] ?? 'could not be determined' }} |
@if (!empty($context['coordinates']))
| **Approximate coordinates** | {{ $context['coordinates'] }} |
@endif
@if (!empty($context['network']))
| **Network** | {{ $context['network'] }} |
@endif
@endcomponent

@php
    // Built here rather than as inline @if chains inside the markdown: nested
    // conditionals between table rows are what turned this template into a
    // Blade parse error the first time.
    $flags = [];
    if (! empty($context['is_vpn'])) { $flags[] = 'a VPN or anonymizing service'; }
    if (! empty($context['is_datacenter'])) { $flags[] = 'a data center rather than a home or mobile network'; }
@endphp

@if ($flags)
> **Worth a look.** This sign-in came through {{ implode(' and ', $flags) }}.
> That is not unusual on its own — plenty of people use one — but it does mean the
> area above is where the service exits, not where the member is.
@endif

## What they signed in on

@component('mail::table')
| | |
|:---|:---|
| **Device** | {{ ucfirst($context['device_type'] ?? 'unknown') }} |
| **Platform** | {{ $context['platform'] ?? 'unknown' }} |
| **Browser** | {{ $context['browser'] ?? 'unknown' }} |
@endcomponent

@if (!empty($context['user_agent']))
Full device string, for the record:

@component('mail::panel')
{{ $context['user_agent'] }}
@endcomponent
@endif

@component('mail::button', ['url' => route('admin.users.show', $member)])
Open in admin
@endcomponent

Thanks,<br>
{{ config('app.name') }}

{{-- The registered company, not the consumer brand. It identifies the sender
     on an email, which the brand name alone does not. --}}
<small>{{ config('app.legal_entity') }}</small>
@endcomponent

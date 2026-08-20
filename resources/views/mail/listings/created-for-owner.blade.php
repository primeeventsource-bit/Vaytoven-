@component('mail::message')
# Your listing is on Vaytoven

Hi {{ $owner->first_name ?: $owner->name }},

Our team has set up a listing for you on Vaytoven.

@component('mail::panel')
**{{ $property->title }}**

@if ($property->city)MASK2@if ($property->country), {{ $property->country }}@endif<br>@endif
{{ $property->capacity }} {{ Str::plural('guest', $property->capacity) }} ·
{{ $property->bedrooms }} {{ Str::plural('bedroom', $property->bedrooms) }} ·
${{ number_format($property->base_nightly_cents / 100, 2) }} per night

Status: **{{ ucfirst(str_replace('_', ' ', $property->status->value ?? $property->status)) }}**
@endcomponent

@if ($temporaryPassword)
## Signing in for the first time

We created an account for you at **{{ $owner->email }}**.

@component('mail::panel')
Temporary password: **{{ $temporaryPassword }}**
@endcomponent

You'll be asked to choose your own password the first time you sign in — this
one stops working at that point, so nobody but you knows it afterwards.

@component('mail::button', ['url' => route('login')])
Sign in and set your password
@endcomponent
@else
@component('mail::button', ['url' => route('dashboard')])
View it on your dashboard
@endcomponent
@endif

## What happens next

You control this listing. Photos, description, nightly rate and availability
are all yours to change from your dashboard at any time.

Travelers submit **offers** on your dates — every offer expires after 24 hours.
You accept or decline, and if you accept, you arrange the stay and payment
with the guest directly. Vaytoven advertises your property; it does not take
reservations, collect rental payments, or take a percentage of what you earn.

**If you did not expect this email**, tell us and we will remove the listing —
reply here or call {{ setting('general.support_phone', '(877) 782-9868') }}.

Thanks,<br>
{{ setting('general.site_name', 'Vaytoven') }}
@endcomponent

@component('mail::message')
# Member signed in for the first time

Fulfilment is complete for this account — the credentials reached the member and they have used them.

**Member:** {{ $member->name }}
**Email:** {{ $member->email }}
**Account created:** {{ et($member->created_at, 'F j, Y') }}
**First sign-in:** {{ $signedInAt ?? et(now(), 'F j, Y \a\t g:ia') }}
@if ($ipAddress)
**From:** {{ $ipAddress }} (approximate — derived from the IP address, not a physical location)
@endif

@component('mail::button', ['url' => route('admin.users.show', $member)])
Open in admin
@endcomponent

This is an internal notification. The member was not sent a copy.

Thanks,<br>
{{ config('app.name') }}
@endcomponent

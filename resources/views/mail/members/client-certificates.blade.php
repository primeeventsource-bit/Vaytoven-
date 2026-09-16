@component('mail::message')
# Client certificates on file

For the office's records. This message was not sent to the client.

@component('mail::table')
| | |
|:---|:---|
| **Client** | {{ $client->name }} |
| **Email** | {{ $client->email }} |
| **Account ID** | {{ $client->member_id ?: '#'.$client->id }} |
| **Account created** | {{ et($client->created_at, 'F j, Y') }} |
@endcomponent

## Advertisements

@component('mail::table')
| Advertisement | Activated | First access | Accepted | Status | Certificate No. |
|:---|:---|:---|:---|:---|:---|
@foreach ($summaries as $s)
| {{ $s['advertisementId'] }} | {{ $s['activatedAt'] }} | {{ $s['firstAccessAt'] }} | {{ $s['acceptedAt'] }} | {{ $s['status'] }} | {{ $s['certificateNumber'] }} |
@endforeach
@endcomponent

Attached: the Service Usage Confirmation Certificate for the life of the account, and one Advertisement Service Fulfillment Record per advertisement.

Every value comes from stored audit records. "Not recorded" means no record exists — nothing has been estimated. Member access and acceptance tracking began in September 2026, so earlier advertisements show "Not recorded" for those lines until the client signs in and accepts.
@endcomponent

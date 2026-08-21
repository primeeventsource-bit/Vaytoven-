@extends('dashboard.layout')

@section('eyebrow', 'Admin · Offer')
@section('title', $offer->reference ?? ('Offer #'.$offer->id))

@section('content')

<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:20px;flex-wrap:wrap;padding-bottom:18px;">
    <div>
        <h2 style="font-family:'Fraunces',serif;font-size:24px;margin:0;">
            {{ $offer->reference ?? 'Offer #'.$offer->id }}
        </h2>
        <div class="vyt-faint" style="margin-top:4px;">
            {{ $offer->kind->value === 'offer' ? 'Offer' : 'Inquiry' }}
            on {{ $offer->property?->title ?? 'a listing' }}
            · submitted {{ et($offer->sent_at ?? $offer->created_at, 'M j, Y \a\t g:ia') }}
        </div>
    </div>
    <div>
        {{-- effectiveStatus(), so an offer past its 24 hours reads as expired
             even though nothing has swept it. --}}
        <span class="vyt-pill" style="font-size:14px;">{{ ucfirst($offer->effectiveStatus()->value) }}</span>
        <a href="{{ route('admin.offers.index') }}" style="margin-left:12px;font-size:14px;">← All offers</a>
    </div>
</div>

<div class="vyt-row-2">
    <div class="vyt-card">
        <div class="vyt-card-header"><h3>The request</h3></div>
        <div class="vyt-card-body">
            <ul class="vyt-kv">
                <li><span class="k">Listing</span><span class="v">{{ $offer->property?->title ?? '—' }}</span></li>
                <li>
                    <span class="k">Dates</span>
                    <span class="v">
                        {{ et($offer->proposed_check_in, 'M j, Y') ?? '—' }}
                        – {{ et($offer->proposed_check_out, 'M j, Y') ?? '—' }}
                        @if ($offer->nights())
                            <span class="vyt-faint">· {{ $offer->nights() }} {{ Str::plural('night', $offer->nights()) }}</span>
                        @endif
                    </span>
                </li>
                <li><span class="k">Guests</span><span class="v">{{ $offer->proposed_guests ?? '—' }}</span></li>
                <li>
                    <span class="k">Amount</span>
                    <span class="v">
                        @if ($offer->offer_amount_cents !== null)
                            <strong>${{ number_format($offer->offer_amount_cents / 100, 2) }}</strong>
                        @else
                            <span class="vyt-faint">Inquiry — no amount</span>
                        @endif
                    </span>
                </li>
                <li><span class="k">Expires</span><span class="v">{{ et($offer->expires_at, 'M j, Y g:ia') ?? '—' }}</span></li>
            </ul>

            @if ($offer->buyer_message)
                <div style="margin-top:16px;padding:14px 16px;background:#faf7ff;border-radius:10px;">
                    <div style="font-size:11.5px;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-bottom:6px;">
                        Message
                    </div>
                    {{ $offer->buyer_message }}
                </div>
            @endif
        </div>
    </div>

    <div class="vyt-card">
        <div class="vyt-card-header"><h3>People</h3></div>
        <div class="vyt-card-body">
            <div style="font-size:11.5px;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);">
                Submitted by
            </div>
            <ul class="vyt-kv" style="margin-bottom:18px;">
                <li><span class="k">Name</span><span class="v">{{ $offer->buyer?->name ?? '—' }}</span></li>
                <li><span class="k">Email</span><span class="v">{{ $offer->buyer?->email ?? '—' }}</span></li>
                <li><span class="k">Phone</span><span class="v">{{ $offer->buyer?->phone ?? '—' }}</span></li>
                <li><span class="k">IP</span><span class="v vyt-mono" style="font-size:12px;">{{ $offer->submitted_ip ?? '—' }}</span></li>
            </ul>

            <div style="font-size:11.5px;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);">
                Listing member
            </div>
            <ul class="vyt-kv">
                <li><span class="k">Name</span><span class="v">{{ $offer->member?->name ?? '—' }}</span></li>
                <li><span class="k">Email</span><span class="v">{{ $offer->member?->email ?? '—' }}</span></li>
                <li><span class="k">Phone</span><span class="v">{{ $offer->member?->phone ?? '—' }}</span></li>
                @if ($offer->member)
                    <li>
                        <span class="k">Profile</span>
                        <span class="v"><a href="{{ route('admin.members.show', $offer->member) }}">Open Member 360 →</a></span>
                    </li>
                @endif
            </ul>
        </div>
    </div>
</div>

{{-- Activity --------------------------------------------------------- --}}
<div class="vyt-card" style="margin-top:18px;">
    <div class="vyt-card-header"><h3>Activity</h3></div>
    <div class="vyt-card-body">
        @if (empty($timeline))
            <p class="vyt-faint" style="margin:0;">Nothing recorded.</p>
        @else
            <ul class="m360-timeline">
                @foreach ($timeline as $event)
                    <li>
                        <span class="at">{{ et($event['at'], 'M j, Y g:ia') }}</span>
                        <span class="dot"></span>
                        <span>
                            {{ $event['label'] }}
                            @if ($event['detail'])
                                <span class="detail">{{ $event['detail'] }}</span>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>

@if ($offer->effectiveStatus() === \App\Enums\MemberOfferStatus::Accepted)
    {{-- The distinction the whole platform turns on. An accepted offer is two
         people agreeing to talk terms; it is not a reservation Vaytoven holds,
         and staff must not tell a caller otherwise. --}}
    <div class="site-alert" style="margin-top:18px;background:#fffbeb;border-color:#fde68a;color:#92400e;">
        <strong>Accepted is not a reservation.</strong>
        The listing member agreed to proceed on these dates and this amount.
        Vaytoven holds no booking, took no payment and guarantees no stay — the
        two of them settle it directly. If a caller believes Vaytoven has
        reserved something, that is the misunderstanding to correct.
    </div>
@endif

@endsection

@push('head')
<style>
        /* Fraunces is a variable font with an optical-size axis. On auto the
           browser picks a more decorative cut as type gets larger — flared
           serifs and exaggerated curves — which is what reads as wavy on
           headings. Pinned here; the property inherits, so this one
           declaration reaches every heading below it. */
        html { font-optical-sizing: none; }

    .m360-timeline { list-style:none; margin:0; padding:0; }
    .m360-timeline li {
        display:grid; grid-template-columns:150px 14px 1fr; gap:14px;
        padding:12px 0; border-top:1px solid var(--line); align-items:start;
    }
    .m360-timeline li:first-child { border-top:0; }
    .m360-timeline .at { font-size:12.5px; color:var(--muted); }
    .m360-timeline .dot { width:10px; height:10px; border-radius:50%; margin-top:5px; background:var(--magenta); }
    .m360-timeline .detail { display:block; font-size:12.5px; color:var(--muted); margin-top:2px; }
</style>
@endpush

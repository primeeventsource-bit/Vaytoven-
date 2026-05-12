@extends('dashboard.layout')

@section('eyebrow', 'Member')
@section('title', 'Welcome back, ' . $me->name)

@section('content')

    @if ($myEnquiry)
        <section class="vyt-section">
            <div class="vyt-card">
                <div class="vyt-card-header">
                    <h3>Your Managed Listing Program enquiry</h3>
                    <span class="vyt-section-meta">Reference {{ $myEnquiry->reference ?? '—' }}</span>
                </div>
                <div class="vyt-card-body">
                    <ul class="vyt-kv">
                        <li><span class="k">Club</span><span class="v">{{ $myEnquiry->club }}</span></li>
                        <li><span class="k">Property</span><span class="v">{{ $myEnquiry->property }}</span></li>
                        <li><span class="k">Annual points</span><span class="v">{{ number_format((int) $myEnquiry->points) }}</span></li>
                        @php($det = $myEnquiry->exchange_detection)
                        @if ($det && ! empty($det['matches']))
                            @php($top = $det['matches'][0])
                            <li>
                                <span class="k">Banking / Exchange Group</span>
                                <span class="v">
                                    {{ $top['exchange_name'] }}
                                    <span class="vyt-faint" style="font-weight:400;font-size:12px;">·&nbsp;{{ $top['confidence'] }}% confidence</span>
                                </span>
                            </li>
                            @if ($det['status'] === 'needs_review')
                                <li>
                                    <span class="k"></span>
                                    <span class="v" style="font-size:13px;color:#92400e;background:#fffbeb;padding:6px 10px;border-radius:8px;font-weight:500;">
                                        Vaytoven found multiple possible exchange groups for this property. Please confirm with your member specialist before publishing.
                                    </span>
                                </li>
                            @endif
                        @endif
                        <li><span class="k">Status</span><span class="v"><span class="vyt-pill">{{ $myEnquiry->status }}</span></span></li>
                        <li><span class="k">Submitted</span><span class="v">{{ $myEnquiry->created_at?->diffForHumans() }}</span></li>
                    </ul>
                </div>
            </div>
        </section>
    @endif

    {{-- Offers Vaytoven has extended for the member's points inventory.
         Pending first (most actionable), then a short tail of responded/expired. --}}
    <section class="vyt-section">
        <div class="vyt-card">
            <div class="vyt-card-header">
                <h3>Offers
                    @if ($pendingOfferCount > 0)
                        <span class="vyt-pill" style="background:var(--gradient);color:#fff;margin-left:8px;">{{ $pendingOfferCount }} pending</span>
                    @endif
                </h3>
                <span class="vyt-section-meta">From Vaytoven · review and accept or decline</span>
            </div>
            @if ($offers->isEmpty())
                <div class="vyt-card-empty">
                    No offers yet. When Vaytoven proposes a booking against your points, it'll appear here.
                </div>
            @else
                @foreach ($offers as $offer)
                    @php($pending = $offer->status === \App\Enums\MemberOfferStatus::Pending)
                    <div style="padding:18px 22px;border-top:1px solid var(--line);">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;">
                            <div style="flex:1;min-width:280px;">
                                <div style="font-family:'Fraunces',serif;font-size:17px;font-weight:600;">
                                    {{ $offer->property?->title ?? 'Managed listing' }}
                                    @if ($offer->property?->city)
                                        <span class="vyt-faint" style="font-family:inherit;">·&nbsp;{{ $offer->property->city }}@if($offer->property->country), {{ $offer->property->country }}@endif</span>
                                    @endif
                                </div>
                                <ul class="vyt-kv" style="margin-top:10px;">
                                    <li>
                                        <span class="k">Dates</span>
                                        <span class="v">
                                            {{ $offer->proposed_check_in?->format('M j, Y') }} – {{ $offer->proposed_check_out?->format('M j, Y') }}
                                            <span class="vyt-faint" style="font-weight:400;">·&nbsp;{{ $offer->nights() }} {{ Str::plural('night', $offer->nights()) }}</span>
                                        </span>
                                    </li>
                                    <li><span class="k">Guests</span><span class="v">{{ $offer->proposed_guests }}</span></li>
                                    <li>
                                        <span class="k">Payout to you</span>
                                        <span class="v" style="color:var(--purple);">${{ number_format($offer->payout_to_member_cents / 100, 2) }}</span>
                                    </li>
                                    <li>
                                        <span class="k">Status</span>
                                        <span class="v">
                                            @if ($pending)
                                                <span class="vyt-pill" style="background:#fffbeb;color:#92400e;">Pending your response</span>
                                            @else
                                                <span class="vyt-pill">{{ ucfirst($offer->status->value) }}</span>
                                                @if ($offer->responded_at)
                                                    <span class="vyt-faint" style="font-weight:400;">· {{ $offer->responded_at->diffForHumans() }}</span>
                                                @endif
                                            @endif
                                        </span>
                                    </li>
                                    @if ($pending && $offer->expires_at)
                                        <li><span class="k">Expires</span><span class="v">{{ $offer->expires_at->diffForHumans() }}</span></li>
                                    @endif
                                </ul>

                                @if ($offer->instructions)
                                    <div style="margin-top:14px;padding:12px 14px;background:#faf5ff;border-radius:10px;font-size:13.5px;color:var(--ink);">
                                        <div style="font-weight:600;margin-bottom:4px;">Note from your specialist</div>
                                        {{ $offer->instructions }}
                                    </div>
                                @endif
                            </div>

                            @if ($pending)
                                <div style="display:flex;gap:8px;flex-shrink:0;">
                                    <form method="POST" action="{{ route('member.offers.accept', $offer) }}" style="margin:0;">
                                        @csrf
                                        <button type="submit"
                                                style="background:var(--gradient);color:#fff;border:0;padding:10px 18px;border-radius:999px;font-weight:600;font-size:14px;cursor:pointer;"
                                                onclick="return confirm('Accept this offer? You\'ll need to coordinate with your home program to reserve the dates and add the Vaytoven guests.');">
                                            Accept
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('member.offers.decline', $offer) }}" style="margin:0;">
                                        @csrf
                                        <button type="submit"
                                                style="background:#fff;color:var(--ink);border:1px solid var(--line);padding:10px 18px;border-radius:999px;font-weight:600;font-size:14px;cursor:pointer;"
                                                onclick="return confirm('Decline this offer? Your specialist will be in touch with revised options.');">
                                            Decline
                                        </button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </section>

    {{-- Help: what to do after accepting an offer. Replaces the eventual
         in-app chat widget for now — content the chat AI would surface
         once it's wired up. --}}
    <section class="vyt-section">
        <div class="vyt-card">
            <div class="vyt-card-header">
                <h3>After you accept: coordinating with your home program</h3>
                <span class="vyt-section-meta">Step-by-step</span>
            </div>
            <div class="vyt-card-body" style="font-size:14px;line-height:1.7;">
                <ol style="margin:0;padding-left:22px;">
                    <li>
                        <strong>Check availability for the dates above</strong> in your home program's member portal
                        @if ($myEnquiry?->club)
                            (your {{ $myEnquiry->club }} account).
                        @else
                            (Marriott, Hilton, Disney, RCI, Interval International, etc.).
                        @endif
                    </li>
                    <li><strong>Reserve the unit</strong> using your points / week-equivalents for the proposed dates.</li>
                    <li><strong>Reply to your member specialist</strong> with the resort's confirmation number once the booking is in.</li>
                    <li><strong>Add Vaytoven's guests</strong> to your guest pass list when prompted by your home program — your specialist will share the full guest names a few days before check-in.</li>
                </ol>
                <p style="margin:18px 0 0;color:var(--muted);font-size:13.5px;">
                    Questions about reservation windows, points conversion, or guest passes?
                    <a href="mailto:specialist@vaytoven.com?subject=Help with my offer">Contact your member specialist</a>
                    or check the <a href="{{ route('help.index') }}">help center</a>.
                </p>
            </div>
        </div>
    </section>

    @include('partials.listing-analytics', [
        'listings'        => $listings,
        'totalViews30d'   => $totalViews30d,
        'totalViews7d'    => $totalViews7d,
        'uniqueCountries' => $uniqueCountries,
        'perListingStats' => $perListingStats,
        'pinPoints'       => $pinPoints,
        'pinsByListing'   => $pinsByListing,
        'mapboxToken'     => $mapboxToken,
        'mapboxStyle'     => $mapboxStyle,
    ])

@endsection

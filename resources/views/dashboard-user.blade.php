@extends('dashboard.layout')

@section('eyebrow', 'Your account')
@section('title', 'Welcome back, ' . $me->name)

@section('content')

    {{-- Offers sent, not bookings taken. There are no stays to count and no
         charges to list: Vaytoven advertises listings and never collects money
         from a traveler, so the only thing this account holds is submissions
         waiting on a listing owner. --}}
    <section class="vyt-section">
        <div class="vyt-tiles">
            <div class="vyt-tile">
                <div class="vyt-tile-label">Awaiting a response</div>
                <span class="vyt-tile-value t-gradient">{{ $openOfferCount }}</span>
            </div>
            <div class="vyt-tile">
                <div class="vyt-tile-label">Offers submitted</div>
                <span class="vyt-tile-value t-pink">{{ $offers->count() }}</span>
            </div>
        </div>
    </section>

    <section class="vyt-section">
        <div class="vyt-card">
            <div class="vyt-card-header">
                <h3>My offers</h3>
                <span class="vyt-section-meta">{{ $offers->count() }} total · last 10</span>
            </div>
            @if ($offers->isEmpty())
                <div class="vyt-card-empty">
                    No offers yet — <a href="{{ route('properties.index') }}">browse listings</a> and submit one.
                </div>
            @else
                <table class="vyt-table">
                    <thead>
                        <tr>
                            <th>Listing</th>
                            <th>Dates</th>
                            <th>Your offer</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($offers as $offer)
                            <tr>
                                <td>
                                    {{ $offer->property?->title ?? '—' }}
                                    @if ($offer->property?->city)
                                        <span class="vyt-faint">· {{ $offer->property->city }}</span>
                                    @endif
                                </td>
                                <td class="vyt-faint">
                                    {{ $offer->proposed_check_in?->format('M j') }} – {{ $offer->proposed_check_out?->format('M j, Y') }}
                                </td>
                                {{-- Nullable: an inquiry carries dates and a
                                     message but no figure. --}}
                                <td>
                                    @if ($offer->offer_amount_cents !== null)
                                        ${{ number_format($offer->offer_amount_cents / 100, 2) }}
                                    @else
                                        <span class="vyt-faint">Inquiry</span>
                                    @endif
                                </td>
                                <td>
                                    {{-- effectiveStatus(), not status: an offer
                                         past its 24 hours reads as expired even
                                         if no sweep has run. --}}
                                    <span class="vyt-pill">{{ ucfirst($offer->effectiveStatus()->value) }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="vyt-faint" style="font-size:12px; margin:14px 22px 18px;">
                    Submitting an offer is not a reservation and does not charge you. If the
                    listing member accepts, you arrange the stay and payment with them directly.
                </p>
            @endif
        </div>
    </section>

    <section class="vyt-section">
        <div class="vyt-card">
            <div class="vyt-card-header"><h3>Quick links</h3></div>
            <div class="vyt-card-body">
                <div style="display:flex; gap:10px; flex-wrap: wrap;">
                    <a href="{{ route('properties.index') }}" style="padding:8px 16px; border-radius:999px; background:#f5f3ff; color:var(--purple); font-size:13px; font-weight:500;">Browse listings</a>
                    <a href="/help" style="padding:8px 16px; border-radius:999px; background:#fafaf9; color:var(--ink); font-size:13px; font-weight:500;">Help center</a>
                    <a href="{{ route('profile.edit') }}" style="padding:8px 16px; border-radius:999px; background:#fafaf9; color:var(--ink); font-size:13px; font-weight:500;">Profile</a>
                    <a href="{{ route('legal.tos') }}" style="padding:8px 16px; border-radius:999px; background:#fafaf9; color:var(--ink); font-size:13px; font-weight:500;">Terms</a>
                    <a href="{{ route('legal.privacy') }}" style="padding:8px 16px; border-radius:999px; background:#fafaf9; color:var(--ink); font-size:13px; font-weight:500;">Privacy</a>
                </div>
            </div>
        </div>
    </section>

@endsection

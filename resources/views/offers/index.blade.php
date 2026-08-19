@extends('dashboard.layout')

@section('eyebrow', 'My listings')
@section('title', 'Inquiries & Offers')

@push('head')
    @include('offers._table_styles')
@endpush

@section('content')

    <section class="vyt-section">
        <p style="margin:0 0 20px; color:var(--muted); font-size:14px; max-width:70ch;">
            Every inquiry and offer submitted against listings you own. Offers expire automatically
            24 hours after they were submitted; expired offers stay here as a permanent record.
            Accepting an offer records your decision and notifies the visitor — the reservation,
            payment and any deposit are arranged directly between you and them.
        </p>

        <div class="vyt-card" style="padding:0;">
            @if ($offers->isEmpty())
                <p class="vyt-empty">No inquiries or offers on your listings yet.</p>
            @else
                <div class="vyt-offers-wrap">
                    <table class="vyt-offers">
                        <thead>
                            <tr>
                                <th>Buyer</th>
                                <th>Listing</th>
                                <th>Requested dates</th>
                                <th>Guests</th>
                                <th>Offer Amount</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>IP Address</th>
                                <th>Status</th>
                                <th>Expires</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($offers as $offer)
                                @php $status = $offer->effectiveStatus(); @endphp
                                <tr>
                                    <td>
                                        {{ $offer->buyer?->name ?? 'Removed account' }}
                                        <span class="sub">{{ $offer->buyer?->email }}</span>
                                        @if ($offer->buyer_message)
                                            <div class="vyt-omsg">{{ $offer->buyer_message }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="listing">{{ $offer->property?->title ?? 'Listing removed' }}</span>
                                        <span class="vyt-okind">{{ $offer->kind->label() }}</span>
                                        @if ($offer->property?->city)
                                            <span class="sub">{{ $offer->property->city }}, {{ $offer->property->country }}</span>
                                        @endif
                                    </td>
                                    <td class="num">
                                        @if ($offer->proposed_check_in && $offer->proposed_check_out)
                                            {{ et($offer->proposed_check_in, 'M j') }} – {{ et($offer->proposed_check_out, 'M j, Y') }}
                                            <span class="sub">{{ $offer->nights() }} {{ Str::plural('night', $offer->nights()) }}</span>
                                        @else
                                            <span style="color:var(--muted);">—</span>
                                        @endif
                                    </td>
                                    <td class="num">{{ $offer->proposed_guests ?? '—' }}</td>
                                    <td class="num">
                                        @if ($offer->offer_amount_cents !== null)
                                            ${{ number_format($offer->offer_amount_cents / 100, 2) }}
                                        @else
                                            <span style="color:var(--muted);">—</span>
                                        @endif
                                    </td>
                                    <td class="num">{{ et($offer->sent_at, 'M j, Y') }}</td>
                                    <td class="num">{{ et($offer->sent_at, 'g:i A') }}</td>
                                    <td class="ip">{{ $offer->submitted_ip ?? '—' }}</td>
                                    <td>
                                        <span class="vyt-ostatus vyt-ostatus-{{ $status->value }}">{{ $status->label() }}</span>
                                    </td>
                                    <td class="num">
                                        {{ et($offer->expires_at, 'M j, Y g:i A') ?? '—' }}
                                    </td>
                                    <td>
                                        @if ($offer->isAwaitingOwner())
                                            <div class="vyt-oactions">
                                                <form method="POST" action="{{ route('offers.accept', $offer) }}">
                                                    @csrf
                                                    <button type="submit" class="accept">Accept</button>
                                                </form>
                                                <form method="POST" action="{{ route('offers.decline', $offer) }}">
                                                    @csrf
                                                    <button type="submit" class="decline">Decline</button>
                                                </form>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @if ($offers->hasPages())
            <div style="margin-top:20px;">{{ $offers->links() }}</div>
        @endif
    </section>

@endsection

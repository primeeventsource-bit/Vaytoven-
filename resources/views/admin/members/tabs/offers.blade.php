<div class="vyt-card">
    <div class="vyt-card-header">
        <h3>Offers &amp; inquiries</h3>
        <span class="vyt-section-meta">{{ $offers->count() }} total, both directions</span>
    </div>

    @if ($offers->isEmpty())
        <div class="vyt-card-empty">No offers involving this member.</div>
    @else
        <table class="vyt-table">
            <thead>
                <tr><th>Direction</th><th>Listing</th><th>Dates</th><th style="text-align:right;">Amount</th><th>Status</th><th>Received</th></tr>
            </thead>
            <tbody>
                @foreach ($offers as $offer)
                    <tr>
                        <td class="vyt-faint">
                            {{-- Which side of the offer this member is on. Showing
                                 only one side is how staff conclude a member has
                                 no activity when they have plenty. --}}
                            {{ $offer->member_user_id === $member->id ? 'Received' : 'Sent' }}
                        </td>
                        <td>{{ $offer->property?->title ?? '—' }}</td>
                        <td class="vyt-faint">
                            {{ et($offer->proposed_check_in, 'M j') }} – {{ et($offer->proposed_check_out, 'M j, Y') }}
                        </td>
                        <td style="text-align:right;">
                            @if ($offer->offer_amount_cents !== null)
                                ${{ number_format($offer->offer_amount_cents / 100, 2) }}
                            @else
                                <span class="vyt-faint">Inquiry</span>
                            @endif
                        </td>
                        <td><span class="vyt-pill">{{ ucfirst($offer->effectiveStatus()->value) }}</span></td>
                        <td class="vyt-faint">{{ $offer->created_at?->diffForHumans() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

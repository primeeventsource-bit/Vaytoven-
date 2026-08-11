@extends('dashboard.layout')

@section('eyebrow', 'Admin')
@section('title', 'Inquiries & Offers')

@push('head')
    @include('offers._table_styles')
    <style>
        .vyt-ofilters {
            background:#fff; border:1px solid var(--line); border-radius:14px;
            padding:14px 18px; margin-bottom:18px;
            display:grid; grid-template-columns:1fr; gap:10px;
        }
        @media (min-width:820px) { .vyt-ofilters { grid-template-columns:2fr 1fr 1fr auto; } }
        .vyt-ofilters input, .vyt-ofilters select {
            padding:9px 12px; border:1px solid var(--line); border-radius:8px;
            font-size:14px; background:var(--bg); outline:none;
        }
        .vyt-ofilters input:focus, .vyt-ofilters select:focus { border-color:var(--magenta); background:#fff; }
        .vyt-ofilters .submit {
            background:var(--gradient); color:#fff; border:0; padding:0 22px;
            border-radius:8px; font-weight:600; font-size:14px; cursor:pointer;
        }
    </style>
@endpush

@section('content')

    <section class="vyt-section">
        <div class="vyt-tiles">
            <div class="vyt-tile">
                <div class="vyt-tile-label">Total submissions</div>
                <span class="vyt-tile-value t-gradient">{{ number_format($counts['total']) }}</span>
            </div>
            <div class="vyt-tile">
                <div class="vyt-tile-label">Awaiting response</div>
                <span class="vyt-tile-value t-emerald">{{ number_format($counts['open']) }}</span>
            </div>
            <div class="vyt-tile">
                <div class="vyt-tile-label">Accepted</div>
                <span class="vyt-tile-value">{{ number_format($counts['accepted']) }}</span>
            </div>
            <div class="vyt-tile">
                <div class="vyt-tile-label">Expired</div>
                <span class="vyt-tile-value t-slate">{{ number_format($counts['expired']) }}</span>
            </div>
        </div>
    </section>

    <section class="vyt-section">
        <form method="GET" class="vyt-ofilters">
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search buyer name, email, or listing title">
            <select name="status">
                <option value="">All statuses</option>
                @foreach (\App\Enums\MemberOfferStatus::cases() as $case)
                    <option value="{{ $case->value }}" @selected($filters['status'] === $case->value)>{{ $case->label() }}</option>
                @endforeach
            </select>
            <select name="kind">
                <option value="">Offers &amp; inquiries</option>
                @foreach (\App\Enums\OfferKind::cases() as $case)
                    <option value="{{ $case->value }}" @selected($filters['kind'] === $case->value)>{{ $case->label() }}s only</option>
                @endforeach
            </select>
            <button type="submit" class="submit">Filter</button>
        </form>

        <div class="vyt-card" style="padding:0;">
            @if ($offers->isEmpty())
                <p class="vyt-empty">No submissions match these filters.</p>
            @else
                <div class="vyt-offers-wrap">
                    <table class="vyt-offers">
                        <thead>
                            <tr>
                                <th>Buyer</th>
                                <th>Listing owner</th>
                                <th>Property</th>
                                <th>Offer Amount</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>IP Address</th>
                                <th>Status</th>
                                <th>Expires</th>
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
                                        {{ $offer->member?->name ?? '—' }}
                                        <span class="sub">{{ $offer->member?->email }}</span>
                                    </td>
                                    <td>
                                        <span class="listing">{{ $offer->property?->title ?? 'Listing removed' }}</span>
                                        <span class="vyt-okind">{{ $offer->kind->label() }}</span>
                                        @if ($offer->property?->city)
                                            <span class="sub">{{ $offer->property->city }}, {{ $offer->property->country }}</span>
                                        @endif
                                    </td>
                                    <td class="num">
                                        @if ($offer->offer_amount_cents !== null)
                                            ${{ number_format($offer->offer_amount_cents / 100, 2) }}
                                        @else
                                            <span style="color:var(--muted);">—</span>
                                        @endif
                                    </td>
                                    <td class="num">{{ $offer->sent_at?->format('M j, Y') }}</td>
                                    <td class="num">{{ $offer->sent_at?->format('g:i A') }}</td>
                                    <td class="ip">{{ $offer->submitted_ip ?? '—' }}</td>
                                    <td><span class="vyt-ostatus vyt-ostatus-{{ $status->value }}">{{ $status->label() }}</span></td>
                                    <td class="num">{{ $offer->expires_at?->format('M j, Y g:i A') ?? '—' }}</td>
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

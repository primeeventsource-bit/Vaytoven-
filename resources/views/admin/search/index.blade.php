@extends('dashboard.layout')

@section('eyebrow', 'Admin')
@section('title', 'Search')

@section('content')

<form method="GET" action="{{ route('admin.search') }}" style="margin-bottom:26px;">
    <input type="search" name="q" value="{{ $term }}" autofocus
           placeholder="Name, email, phone, member ID, property, city, offer reference, order or NMI transaction"
           style="width:100%;padding:14px 18px;border:1px solid var(--line);border-radius:12px;font:inherit;font-size:16px;">
</form>

@if ($results === null)
    <div class="vyt-card-empty">
        Type at least two characters. A phone number works even if it was saved
        in a different format.
    </div>
@else
    @php($total = collect($results)->sum(fn ($c) => $c->count()))

    @if ($total === 0)
        <div class="vyt-card-empty">Nothing matched &ldquo;{{ $term }}&rdquo;.</div>
    @endif

    {{-- Members ------------------------------------------------------- --}}
    @if ($results['members']->isNotEmpty())
        <div class="vyt-card" style="margin-bottom:18px;">
            <div class="vyt-card-header">
                <h3>Members</h3>
                <span class="vyt-section-meta">{{ $results['members']->count() }}</span>
            </div>
            <table class="vyt-table">
                <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th></th></tr></thead>
                <tbody>
                    @foreach ($results['members'] as $user)
                        <tr>
                            <td>{{ $user->name }}</td>
                            <td class="vyt-faint">{{ $user->email }}</td>
                            <td class="vyt-faint">{{ $user->phone ?: '—' }}</td>
                            <td class="vyt-faint">{{ $user->role?->value ?? '—' }}</td>
                            <td><a href="{{ route('admin.members.show', $user) }}">Open 360 →</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Properties ---------------------------------------------------- --}}
    @if ($results['properties']->isNotEmpty())
        <div class="vyt-card" style="margin-bottom:18px;">
            <div class="vyt-card-header">
                <h3>Properties</h3>
                <span class="vyt-section-meta">{{ $results['properties']->count() }}</span>
            </div>
            <table class="vyt-table">
                <thead><tr><th>Listing</th><th>Location</th><th>Owner</th><th>Status</th></tr></thead>
                <tbody>
                    @foreach ($results['properties'] as $property)
                        <tr>
                            <td><a href="{{ route('properties.show', $property) }}">{{ $property->title }}</a></td>
                            <td class="vyt-faint">{{ $property->city ?: '—' }}</td>
                            <td class="vyt-faint">
                                @if ($property->host)
                                    <a href="{{ route('admin.members.show', $property->host) }}">{{ $property->host->name }}</a>
                                @else — @endif
                            </td>
                            <td><span class="vyt-pill">{{ ucfirst(str_replace('_', ' ', $property->status->value ?? $property->status)) }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Offers -------------------------------------------------------- --}}
    @if ($results['offers']->isNotEmpty())
        <div class="vyt-card" style="margin-bottom:18px;">
            <div class="vyt-card-header">
                <h3>Offers &amp; inquiries</h3>
                <span class="vyt-section-meta">{{ $results['offers']->count() }}</span>
            </div>
            <table class="vyt-table">
                <thead><tr><th>Reference</th><th>Listing</th><th>From</th><th>Status</th><th>Received</th></tr></thead>
                <tbody>
                    @foreach ($results['offers'] as $offer)
                        <tr>
                            <td><a href="{{ route('admin.offers.show', $offer) }}" class="vyt-mono">{{ $offer->reference ?? '#'.$offer->id }}</a></td>
                            <td>{{ $offer->property?->title ?? '—' }}</td>
                            <td class="vyt-faint">{{ $offer->buyer?->name ?? '—' }}</td>
                            <td><span class="vyt-pill">{{ ucfirst($offer->effectiveStatus()->value) }}</span></td>
                            <td class="vyt-faint">{{ $offer->created_at?->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Orders -------------------------------------------------------- --}}
    @if ($results['orders']->isNotEmpty())
        <div class="vyt-card">
            <div class="vyt-card-header">
                <h3>Member Services orders</h3>
                <span class="vyt-section-meta">{{ $results['orders']->count() }}</span>
            </div>
            <table class="vyt-table">
                <thead><tr><th>Reference</th><th>Member</th><th>Package</th><th style="text-align:right;">Total</th><th>Status</th><th>NMI transaction</th></tr></thead>
                <tbody>
                    @foreach ($results['orders'] as $order)
                        <tr>
                            <td class="vyt-mono">{{ $order->reference }}</td>
                            <td class="vyt-faint">{{ $order->fullName() }}<span style="display:block;font-size:12px;">{{ $order->email }}</span></td>
                            <td>{{ $order->package->label() }}</td>
                            <td style="text-align:right;">${{ $order->totalDollars() }}</td>
                            <td><span class="vyt-pill">{{ $order->effectiveStatus()->label() }}</span></td>
                            <td class="vyt-mono" style="font-size:12px;">{{ $order->nmi_transaction_id ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endif

@endsection

@extends('dashboard.layout')

@section('eyebrow', 'Admin')
@section('title', 'Member Services')

@section('content')
<div class="vyt-card-header" style="padding:0 0 18px;">
    <h3>Member Services activations</h3>
    <span class="vyt-section-meta">{{ $orders->total() }} total</span>
</div>

@if (session('status'))
    <div class="site-alert">{{ session('status') }}</div>
@endif
@error('order') <div class="site-alert" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;">{{ $message }}</div> @enderror

<div class="vyt-tiles" style="margin-bottom:22px;">
    <div class="vyt-tile">
        <div class="vyt-tile-label">Collected</div>
        {{-- Paid only. Pending orders are pipeline, not money. --}}
        <span class="vyt-tile-value t-emerald">${{ number_format($paidCents / 100, 2) }}</span>
    </div>
    <div class="vyt-tile">
        <div class="vyt-tile-label">Paid orders</div>
        <span class="vyt-tile-value t-gradient">{{ $paidCount }}</span>
    </div>
    <div class="vyt-tile">
        <div class="vyt-tile-label">Awaiting payment</div>
        <span class="vyt-tile-value t-pink">{{ $awaiting }}</span>
    </div>
</div>

<form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px;">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Reference, email or surname"
           style="flex:1;min-width:220px;padding:9px 12px;border:1px solid var(--line);border-radius:9px;font:inherit;">
    <select name="status" style="padding:9px 12px;border:1px solid var(--line);border-radius:9px;font:inherit;">
        <option value="">All statuses</option>
        @foreach ($statuses as $s)
            <option value="{{ $s->value }}" @selected($activeStatus === $s->value)>{{ $s->label() }}</option>
        @endforeach
    </select>
    <button type="submit" class="site-cta" style="padding:9px 20px;font-size:14px;">Filter</button>
</form>

@if ($orders->isEmpty())
    <div class="vyt-card-empty">No activations yet.</div>
@else
<div style="overflow-x:auto;">
<table class="vyt-table" style="min-width:920px;">
    <thead>
        <tr>
            <th>Reference</th>
            <th>Member</th>
            <th>Package</th>
            <th style="text-align:right;">Total</th>
            <th>Status</th>
            <th>Transaction</th>
            <th>Created</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @foreach ($orders as $order)
            <tr>
                <td class="vyt-mono">{{ $order->reference }}</td>
                <td>
                    {{ $order->fullName() }}
                    <span class="vyt-faint" style="display:block;font-size:12px;">{{ $order->email }}</span>
                </td>
                <td>
                    {{ $order->package->label() }}
                    <span class="vyt-faint" style="display:block;font-size:12px;">
                        {{ $order->weeks }} &times; ${{ $order->pricePerWeekDollars() }}
                    </span>
                </td>
                <td style="text-align:right;font-weight:600;">${{ $order->totalDollars() }}</td>
                <td>
                    {{-- effectiveStatus(), so a lapsed link reads as expired
                         even though nothing has swept it. --}}
                    <span class="vyt-pill">{{ $order->effectiveStatus()->label() }}</span>
                    @if ($order->payment_attempts > 1 && ! $order->paid_at)
                        <span class="vyt-faint" style="display:block;font-size:11.5px;">{{ $order->payment_attempts }} attempts</span>
                    @endif
                </td>
                <td class="vyt-mono" style="font-size:12px;">
                    {{ $order->nmi_transaction_id ?? '—' }}
                    @if (! $order->paid_at && $order->nmi_response_text)
                        <span class="vyt-faint" style="display:block;">{{ Str::limit($order->nmi_response_text, 28) }}</span>
                    @endif
                </td>
                <td class="vyt-faint">{{ $order->created_at?->diffForHumans() }}</td>
                <td>
                    @if ($order->status !== \App\Enums\MemberServiceOrderStatus::Paid
                         && $order->status !== \App\Enums\MemberServiceOrderStatus::Cancelled)
                        <form method="POST" action="{{ route('admin.member-services.cancel', $order) }}"
                              onsubmit="return confirm('Cancel {{ $order->reference }}? The payment link stops working.');">
                            @csrf
                            <button type="submit" style="font-size:12.5px;color:#b91c1c;">Cancel</button>
                        </form>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
</div>

<div style="margin-top:18px;">{{ $orders->links() }}</div>
@endif
@endsection

<div class="vyt-card">
    <div class="vyt-card-header">
        <h3>Member Services orders</h3>
        <span class="vyt-section-meta">matched by email</span>
    </div>

    @if ($orders->isEmpty())
        <div class="vyt-card-empty">No Member Services orders for {{ $member->email }}.</div>
    @else
        <table class="vyt-table">
            <thead>
                <tr><th>Reference</th><th>Package</th><th style="text-align:right;">Total</th><th>Status</th><th>NMI transaction</th><th>Date</th></tr>
            </thead>
            <tbody>
                @foreach ($orders as $order)
                    <tr>
                        <td class="vyt-mono">{{ $order->reference }}</td>
                        <td>
                            {{ $order->package->label() }}
                            <span class="vyt-faint" style="display:block;font-size:12px;">
                                {{ $order->weeks }} &times; ${{ $order->pricePerWeekDollars() }}
                            </span>
                        </td>
                        <td style="text-align:right;font-weight:600;">${{ $order->totalDollars() }}</td>
                        <td><span class="vyt-pill">{{ $order->effectiveStatus()->label() }}</span></td>
                        <td class="vyt-mono" style="font-size:12px;">{{ $order->nmi_transaction_id ?? '—' }}</td>
                        <td class="vyt-faint">{{ et($order->paid_at ?? $order->created_at, 'M j, Y') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<p class="site-note" style="margin-top:14px;">
    Orders are matched to this profile by email address, because activation does not
    require an account. An order placed under a different address will not appear here.
</p>

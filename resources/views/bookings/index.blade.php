@extends('properties.layout')

@section('title', 'My bookings')

@section('content')

    <div class="props-eyebrow">Account</div>
    <h1 class="props-title">My bookings</h1>
    <p class="props-meta">{{ $bookings->total() }} {{ Str::plural('booking', $bookings->total()) }} total</p>

    @if ($bookings->isEmpty())
        <div class="props-empty">
            <h3>No bookings yet</h3>
            <p>When you book a stay, it'll show up here. <a href="{{ route('properties.index') }}">Browse properties →</a></p>
        </div>
    @else
        <div class="vyt-card" style="background:#fff; border:1px solid var(--line); border-radius:14px; overflow:hidden;">
            <table class="vyt-table" style="width:100%; border-collapse:collapse; font-size:13.5px;">
                <thead style="background:#fafaf9;">
                    <tr>
                        <th style="text-align:left; padding:10px 22px; font-size:11px; text-transform:uppercase; letter-spacing:.08em; color:var(--muted); font-weight:600;">Code</th>
                        <th style="text-align:left; padding:10px 22px; font-size:11px; text-transform:uppercase; letter-spacing:.08em; color:var(--muted); font-weight:600;">Property</th>
                        <th style="text-align:left; padding:10px 22px; font-size:11px; text-transform:uppercase; letter-spacing:.08em; color:var(--muted); font-weight:600;">Dates</th>
                        <th style="text-align:left; padding:10px 22px; font-size:11px; text-transform:uppercase; letter-spacing:.08em; color:var(--muted); font-weight:600;">Status</th>
                        <th style="text-align:left; padding:10px 22px; font-size:11px; text-transform:uppercase; letter-spacing:.08em; color:var(--muted); font-weight:600;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($bookings as $booking)
                        <tr style="border-top:1px solid var(--line);">
                            <td style="padding:12px 22px;">
                                <a href="{{ route('bookings.show', $booking) }}" style="font-family:'SFMono-Regular',Consolas,monospace; font-size:12.5px; color:var(--purple); font-weight:600;">{{ $booking->confirmation_code }}</a>
                            </td>
                            <td style="padding:12px 22px;">
                                {{ $booking->property?->title ?? '—' }}
                                <div style="font-size:12px; color:var(--muted);">{{ $booking->property?->city }}{{ $booking->property?->country ? ', '.$booking->property->country : '' }}</div>
                            </td>
                            <td style="padding:12px 22px; color:var(--muted); font-size:12.5px;">
                                {{ $booking->check_in_date?->format('M j') }} – {{ $booking->check_out_date?->format('M j, Y') }}
                                <div style="font-size:11px;">{{ $booking->nights }} {{ Str::plural('night', $booking->nights) }} · {{ $booking->guests }} {{ Str::plural('guest', $booking->guests) }}</div>
                            </td>
                            <td style="padding:12px 22px;">
                                @php
                                    $statusTone = match ($booking->status->value) {
                                        'confirmed', 'in_progress', 'completed' => 'background:#ecfdf5; color:#047857;',
                                        'pending_payment'                      => 'background:#fffbeb; color:#92400e;',
                                        'cancelled'                            => 'background:#fef2f2; color:#b91c1c;',
                                        default                                => 'background:#f5f3ff; color:var(--purple);',
                                    };
                                @endphp
                                <span style="display:inline-block; padding:2px 10px; border-radius:999px; font-size:11px; font-weight:600; letter-spacing:.02em; text-transform:capitalize; {{ $statusTone }}">{{ str_replace('_', ' ', $booking->status->value) }}</span>
                            </td>
                            <td style="padding:12px 22px; font-weight:500;">${{ number_format($booking->total_cents / 100, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="props-pagination" style="margin-top:24px;">{{ $bookings->links() }}</div>
    @endif

@endsection

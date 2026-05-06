@extends('dashboard.layout')

@section('eyebrow', 'Your account')
@section('title', 'Welcome back, ' . $me->name)

@section('content')

    <section class="vyt-section">
        <div class="vyt-tiles">
            <div class="vyt-tile">
                <div class="vyt-tile-label">Upcoming stays</div>
                <span class="vyt-tile-value t-gradient">{{ $upcomingCount }}</span>
            </div>
            <div class="vyt-tile">
                <div class="vyt-tile-label">Total bookings</div>
                <span class="vyt-tile-value t-pink">{{ $bookings->count() }}</span>
            </div>
            <div class="vyt-tile">
                <div class="vyt-tile-label">Recent charges</div>
                <span class="vyt-tile-value t-emerald">{{ $charges->count() }}</span>
            </div>
        </div>
    </section>

    <section class="vyt-section">
        <div class="vyt-card">
            <div class="vyt-card-header">
                <h3>My bookings</h3>
                <span class="vyt-section-meta">{{ $bookings->count() }} total · last 10</span>
            </div>
            @if ($bookings->isEmpty())
                <div class="vyt-card-empty">
                    No bookings yet — <a href="/">browse properties</a> to plan a stay.
                </div>
            @else
                <table class="vyt-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Property</th>
                            <th>Dates</th>
                            <th>Status</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($bookings as $booking)
                            <tr>
                                <td><span class="vyt-mono">{{ $booking->confirmation_code }}</span></td>
                                <td>{{ $booking->property?->title ?? '—' }}</td>
                                <td class="vyt-faint">
                                    {{ $booking->check_in_date?->format('M j') }} – {{ $booking->check_out_date?->format('M j, Y') }}
                                </td>
                                <td><span class="vyt-pill">{{ $booking->status->value }}</span></td>
                                <td>${{ number_format($booking->total_cents / 100, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </section>

    <section class="vyt-section">
        <div class="vyt-card">
            <div class="vyt-card-header">
                <h3>Recent charges</h3>
                <span class="vyt-section-meta">Latest 5</span>
            </div>
            @if ($charges->isEmpty())
                <div class="vyt-card-empty">No charges on your account.</div>
            @else
                <ul class="vyt-kv" style="padding: 6px 22px 14px;">
                    @foreach ($charges as $charge)
                        <li>
                            <span class="k">{{ $charge->created_at?->format('M j, Y') }}</span>
                            <span class="vyt-mono">{{ strtoupper($charge->currency ?? 'USD') }} ${{ number_format($charge->amount_cents / 100, 2) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    <section class="vyt-section">
        <div class="vyt-card">
            <div class="vyt-card-header"><h3>Quick links</h3></div>
            <div class="vyt-card-body">
                <div style="display:flex; gap:10px; flex-wrap: wrap;">
                    <a href="/help" style="padding:8px 16px; border-radius:999px; background:#f5f3ff; color:var(--purple); font-size:13px; font-weight:500;">Help center</a>
                    <a href="{{ route('profile.edit') }}" style="padding:8px 16px; border-radius:999px; background:#fafaf9; color:var(--ink); font-size:13px; font-weight:500;">Profile</a>
                    <a href="{{ route('legal.tos') }}" style="padding:8px 16px; border-radius:999px; background:#fafaf9; color:var(--ink); font-size:13px; font-weight:500;">Terms</a>
                    <a href="{{ route('legal.privacy') }}" style="padding:8px 16px; border-radius:999px; background:#fafaf9; color:var(--ink); font-size:13px; font-weight:500;">Privacy</a>
                </div>
            </div>
        </div>
    </section>

@endsection

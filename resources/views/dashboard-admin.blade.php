@extends('dashboard.layout')

@section('eyebrow', 'Operations')
@section('title', 'Operations dashboard')
@section('header_meta', 'Live signals · refresh for the latest')

@section('content')

    {{-- Top tile row — funnel + risk at a glance ----------------------------- --}}
    <section class="vyt-section">
        <div class="vyt-tiles">
            <a class="vyt-tile" href="#enquiries">
                <div class="vyt-tile-label">New enquiries</div>
                <span class="vyt-tile-value t-pink">{{ $enquiriesNew }}</span>
            </a>
            <div class="vyt-tile">
                <div class="vyt-tile-label">Open tickets</div>
                <span class="vyt-tile-value t-amber">{{ $ticketsOpen }}</span>
            </div>
            <div class="vyt-tile">
                <div class="vyt-tile-label">Open disputes</div>
                <span class="vyt-tile-value t-red">{{ $disputesOpen }}</span>
            </div>
            <a class="vyt-tile" href="/help">
                <div class="vyt-tile-label">Help articles</div>
                <span class="vyt-tile-value t-purple">{{ $helpArticleCount }}</span>
            </a>
        </div>
    </section>

    {{-- Second row — money + activity -------------------------------------- --}}
    <section class="vyt-section">
        <div class="vyt-tiles">
            <div class="vyt-tile">
                <div class="vyt-tile-label">Charges · last 7d</div>
                <span class="vyt-tile-value t-emerald">${{ number_format($chargesLast7dCents/100) }}</span>
            </div>
            <div class="vyt-tile">
                <div class="vyt-tile-label">Refunds · last 7d</div>
                <span class="vyt-tile-value t-slate">${{ number_format($refundsLast7dCents/100) }}</span>
            </div>
            <div class="vyt-tile">
                <div class="vyt-tile-label">Tracking events · 24h</div>
                <span class="vyt-tile-value t-blue">{{ number_format($trackingEvents24h) }}</span>
            </div>
            <div class="vyt-tile">
                <div class="vyt-tile-label">Suspicious logins · 24h</div>
                <span class="vyt-tile-value {{ $suspiciousLogins24h > 0 ? 't-red' : 't-slate' }}">{{ $suspiciousLogins24h }}</span>
            </div>
        </div>
    </section>

    {{-- Bookings + Users breakdown ----------------------------------------- --}}
    <section class="vyt-section">
        <div class="vyt-row-2">
            <div class="vyt-card">
                <div class="vyt-card-header"><h3>Bookings by status</h3></div>
                <div class="vyt-card-body">
                    @if ($bookingsByStatus->isEmpty())
                        <p class="vyt-faint" style="margin:0;">No bookings yet.</p>
                    @else
                        <ul class="vyt-kv">
                            @foreach ($bookingsByStatus as $status => $count)
                                <li><span class="k">{{ str_replace('_', ' ', $status) }}</span><span class="v">{{ $count }}</span></li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            <div class="vyt-card">
                <div class="vyt-card-header"><h3>Users by role</h3></div>
                <div class="vyt-card-body">
                    @if ($usersByRole->isEmpty())
                        <p class="vyt-faint" style="margin:0;">No users yet.</p>
                    @else
                        <ul class="vyt-kv">
                            @foreach ($usersByRole as $role => $count)
                                <li><span class="k">{{ str_replace('_', ' ', $role) }}</span><span class="v">{{ $count }}</span></li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </section>

    {{-- Recent member enquiries ------------------------------------------ --}}
    <section class="vyt-section" id="enquiries">
        <div class="vyt-card">
            <div class="vyt-card-header">
                <h3>Recent member enquiries</h3>
                <span class="vyt-section-meta">Latest 5</span>
            </div>
            @if ($enquiriesRecent->isEmpty())
                <div class="vyt-card-empty">No enquiries yet — the form on the landing posts here.</div>
            @else
                <table class="vyt-table">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Name</th>
                            <th>Club</th>
                            <th>Status</th>
                            <th>Submitted</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($enquiriesRecent as $enquiry)
                            <tr>
                                <td><span class="vyt-mono">{{ $enquiry->reference }}</span></td>
                                <td>{{ $enquiry->fullName() }}</td>
                                <td class="vyt-faint">{{ $enquiry->club }}</td>
                                <td><span class="vyt-pill">{{ $enquiry->status->value }}</span></td>
                                <td class="vyt-faint">{{ $enquiry->created_at?->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </section>

    {{-- Recent bookings -------------------------------------------------- --}}
    <section class="vyt-section">
        <div class="vyt-card">
            <div class="vyt-card-header">
                <h3>Recent bookings</h3>
                <span class="vyt-section-meta">Latest 5</span>
            </div>
            @if ($bookingsRecent->isEmpty())
                <div class="vyt-card-empty">No bookings yet.</div>
            @else
                <table class="vyt-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Property</th>
                            <th>Status</th>
                            <th>Total</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($bookingsRecent as $booking)
                            <tr>
                                <td><span class="vyt-mono">{{ $booking->confirmation_code }}</span></td>
                                <td>{{ $booking->property?->title ?? '—' }}</td>
                                <td><span class="vyt-pill">{{ $booking->status->value }}</span></td>
                                <td>${{ number_format($booking->total_cents / 100, 2) }}</td>
                                <td class="vyt-faint">{{ $booking->created_at?->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </section>

    {{-- Legal versions + activity ---------------------------------------- --}}
    <section class="vyt-section">
        <div class="vyt-row-2">
            <div class="vyt-card">
                <div class="vyt-card-header">
                    <h3>Legal versions in force</h3>
                    <a href="/legal/versions" class="vyt-section-meta" style="text-transform: none; letter-spacing: 0;">JSON →</a>
                </div>
                <div class="vyt-card-body">
                    @if ($legalVersions->isEmpty())
                        <p class="vyt-faint" style="margin:0;">No legal versions seeded — run <code style="font-family:'SFMono-Regular',Consolas,monospace; font-size:12px; background:#f5f3ff; padding:2px 6px; border-radius:4px;">php artisan db:seed</code>.</p>
                    @else
                        <ul class="vyt-kv">
                            @foreach ($legalVersions as $version)
                                <li>
                                    <span class="k">{{ str_replace('_', ' ', $version->kind) }}</span>
                                    <span class="h">{{ $version->version_label }} · {{ substr($version->content_hash, 0, 8) }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <p class="vyt-faint" style="margin: 14px 0 0;">DRAFT until counsel review.</p>
                    @endif
                </div>
            </div>

            <div class="vyt-card">
                <div class="vyt-card-header"><h3>Activity (last 24h)</h3></div>
                <div class="vyt-card-body">
                    <ul class="vyt-kv">
                        <li><span class="k">Chat sessions started</span><span class="v">{{ $chatSessions24h }}</span></li>
                        <li><span class="k">Tracking events recorded</span><span class="v">{{ number_format($trackingEvents24h) }}</span></li>
                        <li><span class="k">Suspicious logins</span><span class="v {{ $suspiciousLogins24h > 0 ? 'danger' : '' }}">{{ $suspiciousLogins24h }}</span></li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

@endsection

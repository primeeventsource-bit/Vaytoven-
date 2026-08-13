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
            <a class="vyt-tile" href="{{ route('admin.users.index') }}">
                <div class="vyt-tile-label">Manage users</div>
                <span class="vyt-tile-value t-purple">{{ $usersByRole?->sum() ?? 0 }}</span>
            </a>
            <a class="vyt-tile" href="{{ route('admin.settings.index') }}">
                <div class="vyt-tile-label">Settings &amp; configuration</div>
                <span class="vyt-tile-value t-gradient">⚙</span>
            </a>
        </div>
    </section>

    {{-- Second row — money + activity -------------------------------------- --}}
    <section class="vyt-section">
        <div class="vyt-tiles">
            {{-- Charges and refunds tiles are gone with the booking product.
                 They summed money taken for stays, which Vaytoven does not
                 take; leaving them would have reported $0 forever and read
                 like a reporting bug rather than an absent product. --}}
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

    {{-- Offers + Users breakdown ------------------------------------------- --}}
    <section class="vyt-section">
        <div class="vyt-row-2">
            <div class="vyt-card">
                <div class="vyt-card-header"><h3>Offers by status</h3></div>
                <div class="vyt-card-body">
                    @if ($offersByStatus->isEmpty())
                        <p class="vyt-faint" style="margin:0;">No offers yet.</p>
                    @else
                        <ul class="vyt-kv">
                            @foreach ($offersByStatus as $status => $count)
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
                            <th>Banking / Exchange Group</th>
                            <th>Status</th>
                            <th>Submitted</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($enquiriesRecent as $enquiry)
                            @php($det = $enquiry->exchange_detection)
                            <tr>
                                <td><span class="vyt-mono">{{ $enquiry->reference }}</span></td>
                                <td>{{ $enquiry->fullName() }}</td>
                                <td class="vyt-faint">{{ $enquiry->club }}</td>
                                <td>
                                    @if ($det && ! empty($det['matches']))
                                        @php($top = $det['matches'][0])
                                        <span class="vyt-pill" title="{{ $top['confidence'] }}% confidence">{{ $top['exchange_name'] }}</span>
                                        @if ($det['status'] === 'needs_review')
                                            <span class="vyt-pill" style="background:#fffbeb;color:#92400e;margin-left:4px;" title="Multiple matches — needs human confirmation">needs review</span>
                                        @elseif ($det['status'] === 'confirmed')
                                            <span class="vyt-pill" style="background:#ecfdf5;color:#047857;margin-left:4px;">confirmed</span>
                                        @endif
                                    @elseif ($det && $det['status'] === 'no_match')
                                        <span class="vyt-faint">no match</span>
                                    @else
                                        <span class="vyt-faint">—</span>
                                    @endif
                                </td>
                                <td><span class="vyt-pill">{{ $enquiry->status->value }}</span></td>
                                <td class="vyt-faint">{{ $enquiry->created_at?->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </section>

    {{-- Recent offers ------------------------------------------------------ --}}
    <section class="vyt-section">
        <div class="vyt-card">
            <div class="vyt-card-header">
                <h3>Recent offers</h3>
                <span class="vyt-section-meta">Latest 5</span>
            </div>
            @if ($offersRecent->isEmpty())
                <div class="vyt-card-empty">No offers yet.</div>
            @else
                <table class="vyt-table">
                    <thead>
                        <tr>
                            <th>Listing</th>
                            <th>Dates</th>
                            <th>Status</th>
                            <th>Amount</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($offersRecent as $offer)
                            <tr>
                                <td>{{ $offer->property?->title ?? '—' }}</td>
                                <td class="vyt-faint">
                                    {{ $offer->proposed_check_in?->format('M j') }} – {{ $offer->proposed_check_out?->format('M j, Y') }}
                                </td>
                                <td><span class="vyt-pill">{{ $offer->effectiveStatus()->value }}</span></td>
                                <td>
                                    @if ($offer->offer_amount_cents !== null)
                                        ${{ number_format($offer->offer_amount_cents / 100, 2) }}
                                    @else
                                        <span class="vyt-faint">Inquiry</span>
                                    @endif
                                </td>
                                <td class="vyt-faint">{{ $offer->created_at?->diffForHumans() }}</td>
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

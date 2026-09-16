@extends('dashboard.layout')

@section('eyebrow', 'Admin · Members')
@section('title', $views[$view])

@push('head')
    <style>
        .fr-views { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:14px; }
        .fr-views a { padding:7px 12px; border:1px solid var(--line); border-radius:999px; font-size:13px; color:var(--muted); background:#fff; }
        .fr-views a.is-current { color:#fff; background:var(--gradient); border-color:transparent; font-weight:600; }
        .fr-list { display:grid; gap:10px; }
        .fr-row { background:#fff; border:1px solid var(--line); border-radius:12px; padding:14px 16px; display:grid; gap:10px; grid-template-columns:minmax(200px,1.2fr) repeat(auto-fit,minmax(170px,1fr)); }
        .fr-k { font-size:11px; text-transform:uppercase; letter-spacing:.06em; color:var(--muted); }
        .fr-v { font-size:13.5px; }
        .fr-ok { color:#047857; font-weight:700; }
        .fr-no { color:#9ca3af; }
        @media (max-width: 700px) { .fr-row { grid-template-columns:1fr; } }
    </style>
@endpush

@section('content')
    @php
        $F = \App\Services\Fulfillment\AdvertisementFulfillment::class;
        $I = \App\Services\Fulfillment\MemberIncentive::class;
        $when = fn ($at) => $at ? et($at, 'm/d/Y g:i A') : null;
    @endphp

    <nav class="fr-views" aria-label="Register views">
        @foreach ($views as $key => $label)
            <a href="{{ route('admin.fulfillment.index', array_filter(['view' => $key, 'q' => $q])) }}"
               class="{{ $view === $key ? 'is-current' : '' }}" @if ($view === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    <form method="GET" action="{{ route('admin.fulfillment.index') }}" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;">
        <input type="hidden" name="view" value="{{ $view }}">
        <input name="q" value="{{ $q }}" placeholder="Name, email or member ID"
               style="flex:1 1 220px;min-width:0;padding:9px 12px;border:1px solid var(--line);border-radius:8px;">
        <button type="submit" class="vyt-save" style="padding:9px 16px;">Search</button>
    </form>

    <p class="vyt-faint" style="font-size:13px;margin:0 0 10px;">
        {{ number_format($clients->total()) }} Managed Listing Program client(s). Member-generated evidence only.
        Locations are approximate IP-based locations.
    </p>

    <div class="fr-list">
        @forelse ($rows as $row)
            @php($m = $row['member'])
            <div class="fr-row">
                <div>
                    <a href="{{ route('admin.members.show', $m) }}" style="font-weight:700;">{{ $m->name }}</a>
                    <div class="vyt-faint" style="font-size:12.5px;">{{ $m->member_id ?: '#'.$m->id }} · {{ $m->email }}</div>
                    <div class="vyt-faint" style="font-size:12px;">{{ $m->addressOnFile()->area() ?? 'No address on file' }}</div>
                    @if ($view === 'certificates' && auth()->user()?->hasPermission('users.view'))
                        {{-- The same usage certificate the office email carried: the
                             whole life of the account, not the default 90 days. --}}
                        <div style="margin-top:6px;font-size:12.5px;">
                            <a href="{{ route('admin.users.certificate', ['user' => $m, 'from' => $m->created_at?->toDateString(), 'to' => now()->toDateString()]) }}">Download service usage certificate</a>
                        </div>
                    @endif
                </div>

                @if (in_array($view, ['fulfillment', 'first-login'], true))
                    <div>
                        <div class="fr-k">First login</div>
                        @if ($row['firstLogin'])
                            <div class="fr-v"><span class="fr-ok">✓</span> {{ $when($row['firstLogin']->occurredAt) }}</div>
                            <div class="vyt-faint" style="font-size:12px;">{{ $row['firstLogin']->ipAddress }} · {{ $row['firstLogin']->approximateLocation() ?? 'Unresolved' }}</div>
                            <div class="vyt-faint" style="font-size:12px;">{{ $row['firstLogin']->deviceLabel() }} · {{ $row['firstLogin']->browser }} · {{ $row['firstLogin']->platform }}</div>
                            <div style="font-size:12px;">{{ $row['firstLogin']->comparisonLabel() }}</div>
                        @else
                            <div class="fr-v fr-no">Never signed in</div>
                        @endif
                    </div>
                @endif

                @if (in_array($view, ['fulfillment', 'incentives'], true))
                    <div>
                        <div class="fr-k">Dining Rewards</div>
                        <div class="fr-v" style="font-weight:700;">{{ $I::statusLabel($row['incentive']['status']) }}</div>
                        @foreach (['presented' => 'Presented', 'acknowledged' => 'Acknowledged', 'delivered' => 'Delivered'] as $k => $label)
                            <div style="font-size:12px;">{{ $label }}:
                                @if ($row['incentive'][$k]) <span class="fr-ok">✓</span> {{ $when($row['incentive'][$k]->occurred_at) }}
                                @else <span class="fr-no">—</span> @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                @if (in_array($view, ['fulfillment', 'acceptance', 'certificates'], true))
                    <div>
                        <div class="fr-k">Advertisements</div>
                        @forelse ($row['ads'] as $ad)
                            <div style="font-size:12.5px;{{ ! $loop->first ? 'margin-top:6px;' : '' }}">
                                <span class="vyt-mono">{{ $ad['property']->reference }}</span>
                                <strong>{{ $F::statusLabel($ad['status']) }}</strong>
                                @if ($view !== 'certificates')
                                    <div class="vyt-faint">
                                        Accessed: {{ $ad['first_access'] ? $when($ad['first_access']->occurred_at) : '—' }}
                                        · Accepted: {{ $ad['accepted'] ? $when($ad['accepted']->occurred_at) : '—' }}
                                    </div>
                                @endif
                                @if ($view === 'certificates' || $ad['accepted'])
                                    <div><a href="{{ route('admin.members.fulfillment-certificate', [$m, $ad['property']]) }}">Download fulfillment certificate</a></div>
                                @endif
                            </div>
                        @empty
                            <div class="fr-v fr-no">No active advertisement</div>
                        @endforelse
                    </div>
                @endif
            </div>
        @empty
            <div class="vyt-card-empty" style="background:#fff;border:1px solid var(--line);border-radius:12px;padding:26px;text-align:center;">No clients match.</div>
        @endforelse
    </div>

    <div style="margin-top:16px;">{{ $clients->links() }}</div>
@endsection

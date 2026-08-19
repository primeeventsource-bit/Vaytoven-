@extends('dashboard.layout')

@section('eyebrow', 'Admin')
@section('title', 'Activity & IP logs')

@push('head')
    <style>
        .vyt-tabs { display:flex; gap:4px; overflow-x:auto; border-bottom:1px solid var(--line); margin-bottom:16px; }
        .vyt-tabs a { padding:10px 12px; font-size:13.5px; color:var(--muted); white-space:nowrap; border-bottom:2px solid transparent; }
        .vyt-tabs a.is-current { color:var(--ink); border-bottom-color:var(--magenta); font-weight:600; }
        .vyt-tabs .n { font-size:11px; color:var(--muted); margin-left:4px; }

        .vyt-filters { background:#fff; border:1px solid var(--line); border-radius:12px; padding:14px 16px; margin-bottom:16px; }
        .vyt-filters .row { display:grid; gap:10px; grid-template-columns:1fr; }
        @media (min-width:900px) { .vyt-filters .row { grid-template-columns:repeat(6,1fr); } }
        .vyt-filters input, .vyt-filters select {
            width:100%; padding:8px 10px; border:1px solid var(--line); border-radius:8px;
            font-size:13.5px; background:var(--bg); outline:none;
        }
        .vyt-filters label { display:block; font-size:11px; text-transform:uppercase; letter-spacing:.05em; color:var(--muted); margin-bottom:4px; }

        .vyt-log { width:100%; border-collapse:collapse; background:#fff; border:1px solid var(--line); border-radius:12px; overflow:hidden; }
        .vyt-log th { text-align:left; font-size:11px; text-transform:uppercase; letter-spacing:.05em; color:var(--muted); padding:10px 12px; border-bottom:1px solid var(--line); }
        .vyt-log td { padding:10px 12px; border-top:1px solid var(--line); font-size:13px; vertical-align:top; }
        .vyt-log tr:hover td { background:#fafafa; }
        .vyt-mono { font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:12px; }
        .vyt-chip { display:inline-block; padding:2px 8px; border-radius:999px; font-size:11px; font-weight:600; background:#f3f4f6; color:#374151; }
        .vyt-chip.ok { background:#ecfdf5; color:#047857; }
        .vyt-chip.bad { background:#fef2f2; color:#b91c1c; }
    </style>
@endpush

@section('content')
    <div style="display:flex;gap:4px;margin-bottom:14px;">
        <a href="{{ route('admin.activity.log') }}" style="padding:8px 16px;font-size:13.5px;border:1px solid transparent;border-radius:8px;color:#fff;background:var(--gradient);font-weight:600;" aria-current="page">List view</a>
        <a href="{{ route('admin.activity.map') }}" style="padding:8px 16px;font-size:13.5px;border:1px solid var(--line);border-radius:8px;color:var(--muted);background:#fff;">Map view</a>
    </div>

    <nav class="vyt-tabs" aria-label="Activity filters">
        @foreach ($groups as $key => $label)
            <a href="{{ route('admin.activity.log', array_merge($filters, ['group' => $key, 'page' => null])) }}"
               class="{{ $group === $key ? 'is-current' : '' }}">
                {{ $label }}<span class="n">{{ number_format($groupCounts[$key] ?? 0) }}</span>
            </a>
        @endforeach
    </nav>

    <form method="GET" action="{{ route('admin.activity.log') }}" class="vyt-filters">
        <input type="hidden" name="group" value="{{ $group }}">
        <div class="row">
            <div>
                <label for="f-user">Member / email</label>
                <input id="f-user" name="user" value="{{ $filters['user'] ?? '' }}" placeholder="email or id">
            </div>
            <div>
                <label for="f-ip">IP address</label>
                <input id="f-ip" name="ip" value="{{ $filters['ip'] ?? '' }}" placeholder="73.12.">
            </div>
            <div>
                <label for="f-session">Session</label>
                <input id="f-session" name="session" value="{{ $filters['session'] ?? '' }}" placeholder="SES-A71F29">
            </div>
            <div>
                <label for="f-subject">Property / reference</label>
                <input id="f-subject" name="subject" value="{{ $filters['subject'] ?? '' }}" placeholder="VAY-P-10582">
            </div>
            <div>
                <label for="f-from">From</label>
                <input id="f-from" name="from" type="date" value="{{ $filters['from'] ?? '' }}">
            </div>
            <div>
                <label for="f-to">To</label>
                <input id="f-to" name="to" type="date" value="{{ $filters['to'] ?? '' }}">
            </div>
        </div>

        <div class="row" style="margin-top:10px;">
            <div>
                <label for="f-type">Activity type</label>
                <select id="f-type" name="type">
                    <option value="">Any</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}" @selected(($filters['type'] ?? '') === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="f-country">Country</label>
                <input id="f-country" name="country" maxlength="2" value="{{ $filters['country'] ?? '' }}" placeholder="US">
            </div>
            <div>
                <label for="f-city">City</label>
                <input id="f-city" name="city" value="{{ $filters['city'] ?? '' }}">
            </div>
            <div>
                <label for="f-device">Device</label>
                <select id="f-device" name="device">
                    <option value="">Any</option>
                    @foreach (['desktop', 'mobile', 'tablet'] as $d)
                        <option value="{{ $d }}" @selected(($filters['device'] ?? '') === $d)>{{ ucfirst($d) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="f-result">Result</label>
                <select id="f-result" name="result">
                    <option value="">Any</option>
                    @foreach (['successful', 'failed', 'completed'] as $r)
                        <option value="{{ $r }}" @selected(($filters['result'] ?? '') === $r)>{{ ucfirst($r) }}</option>
                    @endforeach
                </select>
            </div>
            <div style="display:flex;align-items:flex-end;gap:8px;">
                <button type="submit" class="vyt-save" style="padding:9px 18px;font-size:13.5px;">Filter</button>
                <a href="{{ route('admin.activity.log') }}" class="vyt-faint" style="font-size:13px;">Clear</a>
            </div>
        </div>
    </form>

    <p class="vyt-faint" style="font-size:13px;margin:0 0 10px;">
        {{ number_format($events->total()) }} event(s).
        Locations are <strong>approximate GeoIP</strong>, not a physical address.
    </p>

    @if ($events->isEmpty())
        <div class="vyt-card-empty" style="background:#fff;border:1px solid var(--line);border-radius:12px;padding:26px;text-align:center;color:var(--muted);">
            No activity matches these filters.
        </div>
    @else
        <table class="vyt-log">
            <thead>
                <tr>
                    <th>When</th><th>Who</th><th>Activity</th><th>Where</th>
                    <th>IP / approx. location</th><th>Device</th><th>Referrer</th><th>Session</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($events as $event)
                    <tr>
                        <td class="vyt-mono">{{ et($event->occurred_at, 'm/d/Y g:i:s A') }}</td>
                        <td>
                            @if ($event->actor)
                                {{ $event->actor->name }}
                                <span class="vyt-faint" style="display:block;font-size:11.5px;">
                                    {{ ucfirst($event->actor->role?->value ?? 'user') }} · {{ $event->actor->email }}
                                </span>
                            @elseif ($event->actor_user_id)
                                {{-- The account is gone but the event is not: the
                                     foreign key was dropped so deletion cannot erase
                                     the trail. Saying "Guest" here would be false. --}}
                                <span class="vyt-faint">Deleted account #{{ $event->actor_user_id }}</span>
                            @else
                                <span class="vyt-faint">Guest</span>
                            @endif
                        </td>
                        <td>
                            {{ \App\Enums\ActivityType::tryFrom($event->event_type)?->label() ?? $event->event_type }}
                            @if ($event->result)
                                <span class="vyt-chip {{ $event->result === 'failed' ? 'bad' : 'ok' }}">{{ ucfirst($event->result) }}</span>
                            @endif
                        </td>
                        <td class="vyt-faint">
                            {{ $event->subject_reference ?? $event->path ?? '—' }}
                        </td>
                        <td>
                            <span class="vyt-mono">{{ $event->ip_address ?? '—' }}</span>
                            <span class="vyt-faint" style="display:block;font-size:11.5px;">
                                {{ trim(collect([$event->city, $event->region, $event->country])->filter()->implode(', ')) ?: 'Unresolved' }}
                            </span>
                        </td>
                        <td class="vyt-faint">
                            {{ ucfirst($event->device_type ?? '—') }}
                            <span style="display:block;font-size:11.5px;">{{ $event->browser }}</span>
                        </td>
                        <td class="vyt-faint">{{ $event->referrer_host ?? '—' }}</td>
                        <td>
                            @if ($event->session_id)
                                <a href="{{ route('admin.activity.session', $event->session_id) }}" class="vyt-mono">{{ $event->session_id }}</a>
                            @else
                                <span class="vyt-faint">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div style="margin-top:16px;">{{ $events->links() }}</div>
    @endif
@endsection

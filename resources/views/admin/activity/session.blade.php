@extends('dashboard.layout')

@section('eyebrow', 'Activity & IP logs')
@section('title', 'Visitor journey')

@push('head')
    <style>
        .vyt-journey { background:#fff; border:1px solid var(--line); border-radius:14px; padding:22px; }
        .vyt-journey-step { display:grid; grid-template-columns:120px 1fr; gap:18px; padding:14px 0; border-top:1px solid var(--line); }
        .vyt-journey-step:first-of-type { border-top:0; }
        .vyt-journey-step .t { font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:12.5px; color:var(--muted); }
        .vyt-journey-step h4 { margin:0 0 3px; font-size:14.5px; }
        .vyt-journey-step .d { font-size:12.5px; color:var(--muted); }
        .vyt-summary { background:#fff; border:1px solid var(--line); border-radius:14px; padding:18px 22px; margin-bottom:16px; display:grid; gap:6px; }
        .vyt-summary .k { font-size:11px; text-transform:uppercase; letter-spacing:.05em; color:var(--muted); }
        .vyt-mono { font-family:ui-monospace,SFMono-Regular,Menlo,monospace; }
    </style>
@endpush

@section('content')
    <p style="margin:0 0 14px;">
        <a href="{{ route('admin.activity.log') }}" class="vyt-faint">← All activity</a>
    </p>

    <div class="vyt-summary">
        <div>
            <span class="k">Session</span>
            <div class="vyt-mono" style="font-size:16px;">{{ $sessionId }}</div>
        </div>
        <div style="display:grid;gap:14px;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));margin-top:6px;">
            <div>
                <span class="k">Visitor</span>
                <div>{{ $first->actor?->name ?? 'Guest' }}</div>
                @if ($first->actor)
                    <div class="vyt-faint" style="font-size:12.5px;">{{ $first->actor->email }}</div>
                @endif
            </div>
            <div>
                <span class="k">IP</span>
                <div class="vyt-mono">{{ $first->ip_address ?? '—' }}</div>
            </div>
            <div>
                <span class="k">Approx. GeoIP</span>
                <div>{{ trim(collect([$first->city, $first->region, $first->country])->filter()->implode(', ')) ?: 'Unresolved' }}</div>
            </div>
            <div>
                <span class="k">Device</span>
                <div>{{ ucfirst($first->device_type ?? 'unknown') }} · {{ $first->browser ?? '—' }}</div>
            </div>
            <div>
                <span class="k">Referrer</span>
                <div>{{ $first->referrer_host ?? '—' }}</div>
            </div>
            <div>
                <span class="k">Events</span>
                <div>{{ $events->count() }}</div>
            </div>
        </div>

        <p class="vyt-faint" style="font-size:12.5px;margin:8px 0 0;">
            Location is <strong>approximate GeoIP</strong> derived from the IP address.
            It is not a physical address and should never be described as one.
        </p>
    </div>

    <div class="vyt-journey">
        @foreach ($events as $event)
            <div class="vyt-journey-step">
                <div class="t">{{ $event->occurred_at?->format('g:i:s A') }}</div>
                <div>
                    <h4>{{ \App\Enums\ActivityType::tryFrom($event->event_type)?->label() ?? $event->event_type }}</h4>
                    <div class="d">
                        {{ collect([
                            $event->subject_reference,
                            $event->path ? '/'.$event->path : null,
                            $event->result ? ucfirst($event->result) : null,
                        ])->filter()->implode(' · ') ?: '—' }}
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection

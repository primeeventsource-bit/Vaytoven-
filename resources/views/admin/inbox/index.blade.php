@extends('dashboard.layout')

@section('eyebrow', 'Admin')
@section('title', 'Contact & Support Requests')

@push('head')
    <style>
        .inbox-tabs { display:flex; gap:6px; margin-bottom:20px; flex-wrap:wrap; }
        .inbox-tabs a {
            padding:8px 16px; border-radius:999px; font-size:14px; font-weight:500;
            color:var(--ink); text-decoration:none; border:1px solid var(--line); background:#fff;
        }
        .inbox-tabs a.active { background:var(--gradient); color:#fff; border-color:transparent; }
        .inbox-tabs .badge {
            display:inline-block; margin-left:7px; padding:1px 7px; border-radius:999px;
            background:#fee2e2; color:#b91c1c; font-size:11px; font-weight:700;
        }
        .inbox-tabs a.active .badge { background:rgba(255,255,255,.25); color:#fff; }
        .inbox-table { width:100%; border-collapse:collapse; font-size:13.5px; min-width:820px; }
        .inbox-table th {
            text-align:left; font-size:11px; letter-spacing:.08em; text-transform:uppercase;
            color:var(--muted); font-weight:650; padding:0 14px 10px 0; border-bottom:1px solid var(--line);
        }
        .inbox-table td { padding:14px 14px 14px 0; border-bottom:1px solid var(--line); vertical-align:top; }
        .inbox-table .ref { font-family:'SFMono-Regular',Consolas,monospace; font-size:12px; white-space:nowrap; }
        .inbox-table .sub { display:block; font-size:12px; color:var(--muted); margin-top:2px; }
        .inbox-body { max-width:420px; color:var(--muted); font-size:12.5px; line-height:1.55; }
        .inbox-pill {
            display:inline-block; padding:2px 9px; border-radius:999px; font-size:10.5px;
            font-weight:700; letter-spacing:.05em; text-transform:uppercase; white-space:nowrap;
        }
        .inbox-new { background:#fffbeb; color:#92400e; }
        .inbox-done { background:#ecfdf5; color:#047857; }
        .inbox-high { background:#fee2e2; color:#b91c1c; }
    </style>
@endpush

@section('content')
<section class="vyt-section">
    <nav class="inbox-tabs">
        <a href="{{ route('admin.inbox.index', ['tab' => 'contact']) }}" @class(['active' => $tab === 'contact'])>
            Contact messages
            @if ($counts['contact']) <span class="badge">{{ $counts['contact'] }}</span> @endif
        </a>
        <a href="{{ route('admin.inbox.index', ['tab' => 'support']) }}" @class(['active' => $tab === 'support'])>
            Trip support
            @if ($counts['support']) <span class="badge">{{ $counts['support'] }}</span> @endif
        </a>
        <a href="{{ route('admin.inbox.index', ['tab' => 'hosting']) }}" @class(['active' => $tab === 'hosting'])>
            List-your-property
            @if ($counts['hosting']) <span class="badge">{{ $counts['hosting'] }}</span> @endif
        </a>
        <a href="{{ route('admin.inbox.index', ['tab' => 'applications']) }}" @class(['active' => $tab === 'applications'])>
            Job applications
            @if ($counts['applications']) <span class="badge">{{ $counts['applications'] }}</span> @endif
        </a>
    </nav>

    <div class="vyt-card" style="padding:0; overflow-x:auto;">
        @if ($tab === 'contact')
            @if ($contactMessages->isEmpty())
                <p style="text-align:center; padding:48px; color:var(--muted);">No contact messages yet.</p>
            @else
                <table class="inbox-table">
                    <thead><tr>
                        <th>Reference</th><th>From</th><th>Department</th><th>Subject &amp; message</th>
                        <th>Received</th><th>Status</th><th></th>
                    </tr></thead>
                    <tbody>
                        @foreach ($contactMessages as $m)
                            <tr>
                                <td class="ref">{{ $m->reference }}</td>
                                <td>{{ $m->name() }}<span class="sub">{{ $m->email }}</span>
                                    @if ($m->phone)<span class="sub">{{ $m->phone }}</span>@endif</td>
                                <td>{{ $m->department->label() }}</td>
                                <td><strong>{{ $m->subject }}</strong>
                                    <div class="inbox-body">{{ Str::limit($m->message, 220) }}</div></td>
                                <td style="white-space:nowrap;">{{ $m->created_at->format('M j, Y') }}
                                    <span class="sub">{{ $m->created_at->format('g:i A') }}</span></td>
                                <td>
                                    <span class="inbox-pill {{ $m->status === 'new' ? 'inbox-new' : 'inbox-done' }}">{{ $m->status }}</span>
                                </td>
                                <td>
                                    @can('inbox.manage')
                                        @if ($m->status === 'new')
                                            <form method="POST" action="{{ route('admin.inbox.handled', $m) }}">
                                                @csrf
                                                <button type="submit" style="border:0; background:none; padding:0; color:var(--magenta); font-weight:600; font-size:13px; cursor:pointer; font-family:inherit;">
                                                    Mark handled
                                                </button>
                                            </form>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

        @elseif ($tab === 'support')
            @if ($tickets->isEmpty())
                <p style="text-align:center; padding:48px; color:var(--muted);">No Trip Support requests yet.</p>
            @else
                <table class="inbox-table">
                    <thead><tr>
                        <th>Reference</th><th>From</th><th>Category</th><th>Subject &amp; detail</th>
                        <th>Listing ref</th><th>Received</th><th>Priority</th><th>Status</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($tickets as $t)
                            <tr>
                                <td class="ref">{{ $t->reference }}</td>
                                <td>{{ $t->contact_name ?? $t->openedBy?->name }}
                                    <span class="sub">{{ $t->replyToEmail() }}</span>
                                    @if ($t->contact_phone)<span class="sub">{{ $t->contact_phone }}</span>@endif</td>
                                <td>{{ $t->category?->label() ?? '—' }}</td>
                                <td><strong>{{ $t->subject }}</strong>
                                    <div class="inbox-body">{{ Str::limit($t->body, 220) }}</div></td>
                                <td class="inbox-body" style="max-width:180px;">{{ $t->property_reference ?: '—' }}</td>
                                <td style="white-space:nowrap;">{{ $t->created_at->format('M j, Y') }}
                                    <span class="sub">{{ $t->created_at->format('g:i A') }}</span></td>
                                <td><span class="inbox-pill {{ $t->priority === 'high' ? 'inbox-high' : 'inbox-new' }}">{{ $t->priority }}</span></td>
                                <td><span class="inbox-pill {{ $t->status === 'open' ? 'inbox-new' : 'inbox-done' }}">{{ $t->status }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

        @elseif ($tab === 'hosting')
            @if ($hostingEnquiries->isEmpty())
                <p style="text-align:center; padding:48px; color:var(--muted);">No property submissions yet.</p>
            @else
                <table class="inbox-table">
                    <thead><tr>
                        <th>Reference</th><th>Owner</th><th>Property</th><th>Location</th>
                        <th>Rooms</th><th>Indicative rate</th><th>Availability</th><th>Received</th><th>Status</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($hostingEnquiries as $h)
                            <tr>
                                <td class="ref">{{ $h->reference }}</td>
                                <td>{{ $h->name() }}<span class="sub">{{ $h->email }}</span>
                                    @if ($h->phone)<span class="sub">{{ $h->phone }}</span>@endif</td>
                                <td><strong>{{ $h->displayName() }}</strong>
                                    <span class="sub">{{ $h->isResort() ? trim(($h->club_or_developer ?: 'Resort').' · '.($h->ownership_details ?: '')) : ($h->property_type ?: '') }}</span>
                                    @if ($h->message)<div class="inbox-body">{{ Str::limit($h->message, 200) }}</div>@endif</td>
                                <td class="inbox-body" style="max-width:170px;">{{ $h->location() }}</td>
                                <td style="white-space:nowrap;">
                                    {{ $h->bedrooms !== null ? $h->bedrooms.' bd' : '—' }}
                                    {{ $h->bathrooms !== null ? '· '.$h->bathrooms.' ba' : '' }}
                                </td>
                                <td style="white-space:nowrap;">
                                    {{ $h->indicative_nightly_cents ? '$'.number_format($h->indicative_nightly_cents / 100) : '—' }}
                                </td>
                                <td class="inbox-body" style="max-width:150px;">{{ $h->availability ?: '—' }}</td>
                                <td style="white-space:nowrap;">{{ $h->created_at->format('M j, Y') }}
                                    <span class="sub">{{ $h->created_at->format('g:i A') }}</span></td>
                                <td><span class="inbox-pill {{ $h->status === 'new' ? 'inbox-new' : 'inbox-done' }}">{{ $h->status }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

        @else
            @if ($applications->isEmpty())
                <p style="text-align:center; padding:48px; color:var(--muted);">No applications yet.</p>
            @else
                <table class="inbox-table">
                    <thead><tr>
                        <th>Reference</th><th>Applicant</th><th>Role</th><th>Note</th><th>CV</th><th>Received</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($applications as $a)
                            <tr>
                                <td class="ref">{{ $a->reference }}</td>
                                <td>{{ $a->name() }}<span class="sub">{{ $a->email }}</span></td>
                                <td>{{ $a->opening?->title ?? '—' }}</td>
                                <td class="inbox-body">{{ Str::limit($a->cover_note, 200) ?: '—' }}</td>
                                <td>{{ $a->resume_path ? 'Attached' : '—' }}</td>
                                <td style="white-space:nowrap;">{{ $a->created_at->format('M j, Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        @endif
    </div>

    @php $paginator = $contactMessages ?? $tickets ?? $hostingEnquiries ?? $applications; @endphp
    @if ($paginator && $paginator->hasPages())
        <div style="margin-top:20px;">{{ $paginator->links() }}</div>
    @endif
</section>
@endsection

@extends('dashboard.layout')

@section('eyebrow', 'Admin')
@section('title', 'Users')

@push('head')
    <style>
        .vyt-userfilters {
            background:#fff; border:1px solid var(--line); border-radius:14px;
            padding:14px 18px; margin-bottom:18px;
            display:grid; grid-template-columns:1fr; gap:10px;
        }
        @media (min-width:760px) { .vyt-userfilters { grid-template-columns:2fr 1fr 1fr auto; } }
        .vyt-userfilters input, .vyt-userfilters select {
            padding:9px 12px; border:1px solid var(--line); border-radius:8px;
            font-size:14px; background:var(--bg); outline:none;
        }
        .vyt-userfilters input:focus, .vyt-userfilters select:focus { border-color:var(--magenta); background:#fff; }
        .vyt-userfilters .submit {
            background:var(--gradient); color:#fff; border:0; padding:0 22px;
            border-radius:8px; font-weight:600; font-size:14px; cursor:pointer;
        }
        .vyt-userfilters .submit:hover { transform:translateY(-1px); }

        .vyt-role-pill {
            display:inline-block; padding:2px 10px; border-radius:999px;
            font-size:11px; font-weight:600; letter-spacing:.04em;
            text-transform:uppercase;
        }
        .vyt-role-super_admin     { background:#fce7f3; color:#9d174d; }
        .vyt-role-admin           { background:#fdf2f8; color:#be185d; }
        .vyt-role-member_specialist { background:#f5f3ff; color:#5b21b6; }
        .vyt-role-host            { background:#fef3c7; color:#92400e; }
        .vyt-role-member          { background:#dbeafe; color:#1e40af; }
        .vyt-role-traveler        { background:#f1f5f9; color:#475569; }

        .vyt-status-active      { background:#ecfdf5; color:#047857; }
        .vyt-status-deactivated { background:#fee2e2; color:#b91c1c; }
    </style>
@endpush

@section('content')

    <section class="vyt-section">
        <div class="vyt-tiles">
            <div class="vyt-tile">
                <div class="vyt-tile-label">Total users</div>
                <span class="vyt-tile-value t-gradient">{{ number_format($counts['total']) }}</span>
            </div>
            <div class="vyt-tile">
                <div class="vyt-tile-label">Active</div>
                <span class="vyt-tile-value t-emerald">{{ number_format($counts['active']) }}</span>
            </div>
            <div class="vyt-tile">
                <div class="vyt-tile-label">Deactivated</div>
                <span class="vyt-tile-value {{ $counts['deactivated'] > 0 ? 't-red' : 't-slate' }}">{{ number_format($counts['deactivated']) }}</span>
            </div>
        </div>
    </section>

    <section class="vyt-section">
        <form method="GET" action="{{ route('admin.users.index') }}" class="vyt-userfilters">
            <input type="search" name="q" value="{{ $q }}" placeholder="Search by name or email">

            <select name="role">
                <option value="">All roles</option>
                @foreach ($roles as $r)
                    <option value="{{ $r->value }}" @selected($role === $r->value)>{{ $r->value }}</option>
                @endforeach
            </select>

            <select name="status">
                <option value="all"         @selected($status === 'all')>All</option>
                <option value="active"      @selected($status === 'active')>Active</option>
                <option value="deactivated" @selected($status === 'deactivated')>Deactivated</option>
            </select>

            <button type="submit" class="submit">Filter</button>
        </form>

        <div class="vyt-card">
            <div class="vyt-card-header">
                <h3>Users · {{ $users->total() }} {{ Str::plural('match', $users->total()) }}</h3>
                <a href="{{ route('admin.users.create') }}"
                   style="background:var(--gradient);color:#fff;text-decoration:none;padding:8px 18px;border-radius:999px;font-weight:600;font-size:13px;">
                    + New user
                </a>
            </div>
            @if ($users->isEmpty())
                <div class="vyt-card-empty">
                    No users match the current filters.
                </div>
            @else
                <table class="vyt-table">
                    <thead>
                        <tr>
                            <th>Email · Name</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last login</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $u)
                            <tr style="cursor:pointer;" onclick="window.location='{{ route('admin.users.show', $u) }}'">
                                <td>
                                    <a href="{{ route('admin.users.show', $u) }}" style="font-weight:600;">{{ $u->email }}</a>
                                    <div class="vyt-faint">{{ $u->name }}</div>
                                </td>
                                <td><span class="vyt-role-pill vyt-role-{{ $u->role?->value }}">{{ str_replace('_', ' ', $u->role?->value) }}</span></td>
                                <td>
                                    @if ($u->isActive())
                                        <span class="vyt-pill vyt-status-active">Active</span>
                                    @else
                                        <span class="vyt-pill vyt-status-deactivated">Deactivated</span>
                                    @endif
                                </td>
                                <td class="vyt-faint">{{ $u->last_login_at?->diffForHumans() ?? '—' }}</td>
                                <td class="vyt-faint">{{ $u->created_at?->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div style="margin-top:18px;">
            {{ $users->links() }}
        </div>
    </section>

@endsection

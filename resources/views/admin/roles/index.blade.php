@extends('dashboard.layout')

@section('eyebrow', 'Admin')
@section('title', 'Roles & Permissions')

@push('head')
    <style>
        .vyt-rolegrid { display:grid; gap:14px; grid-template-columns:1fr; }
        @media (min-width:900px) { .vyt-rolegrid { grid-template-columns:repeat(2,1fr); } }
        .vyt-rolecard {
            background:#fff; border:1px solid var(--line); border-radius:14px;
            padding:18px 20px; display:flex; flex-direction:column; gap:10px;
        }
        .vyt-rolecard h3 { margin:0; font-size:17px; display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
        .vyt-rolecard p { margin:0; color:var(--muted); font-size:13.5px; line-height:1.5; }
        .vyt-rolemeta { display:flex; gap:16px; font-size:12.5px; color:var(--muted); flex-wrap:wrap; }
        .vyt-rolemeta b { color:var(--ink); font-weight:600; }
        .vyt-badge {
            display:inline-block; padding:2px 9px; border-radius:999px;
            font-size:10.5px; font-weight:700; letter-spacing:.05em; text-transform:uppercase;
        }
        .vyt-badge-system { background:#f1f5f9; color:#475569; }
        .vyt-badge-super  { background:#fce7f3; color:#9d174d; }
        .vyt-rolecard-actions { display:flex; gap:10px; margin-top:auto; padding-top:6px; }
        .vyt-rolecard-actions a, .vyt-rolecard-actions button {
            font-size:13px; font-weight:600; text-decoration:none; border:0;
            background:none; padding:0; cursor:pointer; color:var(--magenta);
        }
        .vyt-rolecard-actions .danger { color:#b91c1c; }
        .vyt-rolecard.is-locked { opacity:.62; }
    </style>
@endpush

@section('content')

    <section class="vyt-section">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap; margin-bottom:16px;">
            <p style="margin:0; color:var(--muted); font-size:14px; max-width:62ch;">
                A role is a named set of permissions. Every user gets the permissions of their primary
                role, plus any extra roles assigned to them. Super Admins bypass all checks.
            </p>
            @can('roles.create')
                <a href="{{ route('admin.roles.create') }}" class="vyt-btn">New role</a>
            @endcan
        </div>

        <div class="vyt-rolegrid">
            @foreach ($roles as $role)
                @php $locked = ! auth()->user()->isSuperAdmin() && $role->level >= $actorLevel; @endphp
                <article @class(['vyt-rolecard', 'is-locked' => $locked])>
                    <h3>
                        {{ $role->name }}
                        @if ($role->is_super)
                            <span class="vyt-badge vyt-badge-super">Bypasses all checks</span>
                        @elseif ($role->is_system)
                            <span class="vyt-badge vyt-badge-system">System</span>
                        @endif
                    </h3>

                    <p>{{ $role->description }}</p>

                    <div class="vyt-rolemeta">
                        <span><b>{{ $role->is_super ? 'All' : $role->permissions_count }}</b> permissions</span>
                        <span><b>{{ $role->users_count }}</b> directly assigned</span>
                        <span>Level <b>{{ $role->level }}</b></span>
                        <span><code>{{ $role->key }}</code></span>
                    </div>

                    <div class="vyt-rolecard-actions">
                        @if ($locked)
                            <span style="color:var(--muted); font-size:12.5px;">At or above your privilege level</span>
                        @else
                            @can('roles.edit')
                                <a href="{{ route('admin.roles.edit', $role) }}">Edit permissions</a>
                            @endcan
                            @if (! $role->is_system)
                                @can('roles.delete')
                                    <form method="POST" action="{{ route('admin.roles.destroy', $role) }}"
                                          onsubmit="return confirm('Delete the “{{ $role->name }}” role? This cannot be undone.');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="danger">Delete</button>
                                    </form>
                                @endcan
                            @endif
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </section>

@endsection

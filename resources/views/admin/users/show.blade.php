@extends('dashboard.layout')

@section('eyebrow', 'Admin · Users')
@section('title', $user->name)

@section('content')

    <div style="max-width:760px;">
        <div class="vyt-card" style="margin-bottom:18px;">
            <div class="vyt-card-header">
                <h3>{{ $user->email }}</h3>
                <div style="display:flex;gap:8px;align-items:center;">
                    @if ($user->isActive())
                        <span class="vyt-pill" style="background:#ecfdf5;color:#047857;">Active</span>
                    @else
                        <span class="vyt-pill" style="background:#fee2e2;color:#b91c1c;">Deactivated</span>
                    @endif
                    <a href="{{ route('admin.users.edit', $user) }}"
                       style="background:var(--gradient);color:#fff;text-decoration:none;padding:8px 18px;border-radius:999px;font-weight:600;font-size:13px;">
                        Edit
                    </a>
                </div>
            </div>
            <div class="vyt-card-body">
                <ul class="vyt-kv">
                    <li><span class="k">Name</span><span class="v">{{ $user->name }}</span></li>
                    <li><span class="k">Role</span><span class="v">{{ str_replace('_', ' ', $user->role?->value) }}</span></li>
                    <li><span class="k">Email verified</span><span class="v">{{ $user->email_verified_at ? 'yes' : 'no' }}</span></li>
                    <li><span class="k">Last login</span><span class="v">{{ $user->last_login_at?->diffForHumans() ?? '—' }}</span></li>
                    <li><span class="k">Joined</span><span class="v">{{ $user->created_at?->format('M j, Y') }}</span></li>
                    @if ($user->created_by_user_id)
                        <li><span class="k">Created by admin</span><span class="v vyt-faint">ID {{ $user->created_by_user_id }}</span></li>
                    @endif
                    @if (! $user->isActive())
                        <li><span class="k">Deactivated</span><span class="v">{{ $user->deactivated_at?->diffForHumans() }}</span></li>
                        @if ($user->deactivated_by_user_id)
                            <li><span class="k">Deactivated by</span><span class="v vyt-faint">admin ID {{ $user->deactivated_by_user_id }}</span></li>
                        @endif
                    @endif
                </ul>
            </div>
        </div>

        @php($isSelf = auth()->id() === $user->id)
        @if (! $isSelf)
            <div class="vyt-card">
                <div class="vyt-card-header">
                    <h3>{{ $user->isActive() ? 'Deactivate this user' : 'Reactivate this user' }}</h3>
                </div>
                <div class="vyt-card-body">
                    @if ($user->isActive())
                        <p style="color:var(--muted);font-size:14px;margin:0 0 14px;">
                            Soft-deactivation. The user cannot log in, but their bookings, login history, and
                            chargeback evidence remain intact. You can reactivate them at any time.
                        </p>
                        <form method="POST" action="{{ route('admin.users.deactivate', $user) }}" style="margin:0;">
                            @csrf
                            <input type="text" name="reason" maxlength="200" placeholder="Reason (optional)"
                                   style="padding:10px 12px;border:1px solid var(--line);border-radius:8px;font-size:14px;width:60%;margin-right:10px;">
                            <button type="submit"
                                    style="background:#fee2e2;color:#b91c1c;border:1px solid #fca5a5;padding:10px 18px;border-radius:999px;font-weight:600;font-size:14px;cursor:pointer;"
                                    onclick="return confirm('Deactivate {{ $user->email }}? They will not be able to log in until reactivated.');">
                                Deactivate
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.users.reactivate', $user) }}" style="margin:0;">
                            @csrf
                            <button type="submit"
                                    style="background:var(--gradient);color:#fff;border:0;padding:10px 18px;border-radius:999px;font-weight:600;font-size:14px;cursor:pointer;">
                                Reactivate
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @endif
    </div>

@endsection

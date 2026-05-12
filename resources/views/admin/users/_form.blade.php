{{--
  Shared form fields for create + edit.
  Vars: $user (nullable for create), $roles, $canGrantAdmin, $isSelf (nullable).
--}}
@php
    $isCreate = ! isset($user) || ! $user->exists;
    $isSelf = $isSelf ?? false;
@endphp

@if ($errors->any())
    <div style="background:#fee2e2;color:#b91c1c;border:1px solid #fca5a5;padding:12px 16px;border-radius:10px;margin-bottom:18px;font-size:14px;">
        <strong>Please fix:</strong>
        <ul style="margin:6px 0 0;padding-left:20px;">
            @foreach ($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div style="display:grid;gap:14px;grid-template-columns:1fr;">
    <div>
        <label style="display:block;font-size:12px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--muted);margin-bottom:6px;">
            Email <span style="color:var(--magenta);">*</span>
        </label>
        <input type="email" name="email" required
               value="{{ old('email', $user?->email) }}"
               style="width:100%;padding:11px 14px;border:1px solid var(--line);border-radius:10px;font-size:15px;background:#fff;outline:none;"
               autocomplete="off">
    </div>

    <div>
        <label style="display:block;font-size:12px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--muted);margin-bottom:6px;">
            Full name <span style="color:var(--magenta);">*</span>
        </label>
        <input type="text" name="name" required maxlength="255"
               value="{{ old('name', $user?->name) }}"
               style="width:100%;padding:11px 14px;border:1px solid var(--line);border-radius:10px;font-size:15px;background:#fff;outline:none;">
    </div>

    <div>
        <label style="display:block;font-size:12px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--muted);margin-bottom:6px;">
            Role <span style="color:var(--magenta);">*</span>
        </label>
        <select name="role" required
                @if($isSelf) disabled @endif
                style="width:100%;padding:11px 14px;border:1px solid var(--line);border-radius:10px;font-size:15px;background:#fff;outline:none;">
            @foreach ($roles as $r)
                @php
                    $isPrivileged = in_array($r->value, ['admin', 'super_admin'], true);
                    $disabled = $isPrivileged && ! $canGrantAdmin;
                @endphp
                <option value="{{ $r->value }}"
                        @selected(old('role', $user?->role?->value) === $r->value)
                        @disabled($disabled)>
                    {{ str_replace('_', ' ', $r->value) }}
                    @if ($isPrivileged) (privileged) @endif
                    @if ($disabled) — super_admin only @endif
                </option>
            @endforeach
        </select>
        @if ($isSelf)
            <input type="hidden" name="role" value="{{ $user->role->value }}">
            <div class="vyt-faint" style="margin-top:6px;font-size:12px;">Locked: you cannot change your own role.</div>
        @endif
    </div>

    <div>
        <label style="display:block;font-size:12px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--muted);margin-bottom:6px;">
            {{ $isCreate ? 'Password' : 'New password (leave blank to keep)' }}
            @if ($isCreate) <span style="color:var(--magenta);">*</span> @endif
        </label>
        <input type="password" name="password" {{ $isCreate ? 'required' : '' }} minlength="10"
               autocomplete="new-password"
               style="width:100%;padding:11px 14px;border:1px solid var(--line);border-radius:10px;font-size:15px;background:#fff;outline:none;">
        <div class="vyt-faint" style="margin-top:6px;font-size:12px;">
            At least 10 characters · mixed case · a number · a symbol.
        </div>
    </div>

    <div>
        <label style="display:block;font-size:12px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--muted);margin-bottom:6px;">
            {{ $isCreate ? 'Confirm password' : 'Confirm new password' }}
        </label>
        <input type="password" name="password_confirmation" minlength="10" {{ $isCreate ? 'required' : '' }}
               style="width:100%;padding:11px 14px;border:1px solid var(--line);border-radius:10px;font-size:15px;background:#fff;outline:none;">
    </div>
</div>

<div style="margin-top:22px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
    <button type="submit"
            style="background:var(--gradient);color:#fff;border:0;padding:11px 24px;border-radius:999px;font-weight:600;font-size:14px;cursor:pointer;">
        {{ $isCreate ? 'Create user' : 'Save changes' }}
    </button>
    <a href="{{ $isCreate ? route('admin.users.index') : route('admin.users.show', $user) }}"
       style="color:var(--muted);text-decoration:none;font-size:14px;">Cancel</a>
</div>

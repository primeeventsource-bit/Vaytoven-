@php
    /** @var \App\Models\Role $role */
    $isEdit = $role->exists;
@endphp

@push('head')
    <style>
        .vyt-rolefield { margin-bottom:16px; }
        .vyt-rolefield label { display:block; font-size:13px; font-weight:600; margin-bottom:5px; }
        .vyt-rolefield input[type=text], .vyt-rolefield input[type=number], .vyt-rolefield textarea {
            width:100%; padding:9px 12px; border:1px solid var(--line); border-radius:8px;
            font-size:14px; background:var(--bg); outline:none; font-family:inherit;
        }
        .vyt-rolefield input:focus, .vyt-rolefield textarea:focus { border-color:var(--magenta); background:#fff; }
        .vyt-rolefield .hint { font-size:12px; color:var(--muted); margin-top:4px; }
        .vyt-rolefield .err { font-size:12.5px; color:#b91c1c; margin-top:4px; }

        .vyt-permmodule { border:1px solid var(--line); border-radius:12px; margin-bottom:12px; background:#fff; }
        .vyt-permmodule > header {
            display:flex; align-items:center; justify-content:space-between; gap:12px;
            padding:12px 16px; border-bottom:1px solid var(--line);
        }
        .vyt-permmodule > header h4 { margin:0; font-size:14.5px; }
        .vyt-permmodule > header button {
            background:none; border:0; color:var(--magenta); font-size:12.5px;
            font-weight:600; cursor:pointer; padding:0;
        }
        .vyt-permlist { padding:6px 16px 14px; display:grid; gap:2px; }
        .vyt-permrow { display:flex; gap:10px; align-items:flex-start; padding:7px 0; }
        .vyt-permrow input[type=checkbox] { margin-top:3px; width:15px; height:15px; accent-color:#be185d; }
        .vyt-permrow .txt strong { display:block; font-size:13.5px; font-weight:600; }
        .vyt-permrow .txt span { display:block; font-size:12px; color:var(--muted); line-height:1.45; }
        .vyt-permrow.is-disabled { opacity:.45; }
        .vyt-permrow code { font-size:11px; color:var(--muted); }
    </style>
@endpush

<form method="POST" action="{{ $isEdit ? route('admin.roles.update', $role) : route('admin.roles.store') }}">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div class="vyt-card" style="margin-bottom:20px;">
        <div class="vyt-rolefield">
            <label for="name">Role name</label>
            <input type="text" id="name" name="name" value="{{ old('name', $role->name) }}" required>
            @error('name') <div class="err">{{ $message }}</div> @enderror
        </div>

        <div class="vyt-rolefield">
            <label for="key">Key</label>
            @if ($isEdit)
                <input type="text" id="key" value="{{ $role->key }}" disabled>
                <div class="hint">The key is permanent — role assignments and the primary-role mapping depend on it.</div>
            @else
                <input type="text" id="key" name="key" value="{{ old('key') }}"
                       placeholder="e.g. regional_manager" required>
                <div class="hint">Lowercase letters, numbers, and underscores. Permanent once saved.</div>
            @endif
            @error('key') <div class="err">{{ $message }}</div> @enderror
        </div>

        <div class="vyt-rolefield">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="2">{{ old('description', $role->description) }}</textarea>
            @error('description') <div class="err">{{ $message }}</div> @enderror
        </div>

        <div class="vyt-rolefield">
            <label for="level">Privilege level</label>
            <input type="number" id="level" name="level" min="0" max="{{ $maxLevel }}"
                   value="{{ old('level', $role->level ?? 10) }}"
                   @disabled($role->is_system) required>
            <div class="hint">
                Higher is more privileged (0–{{ $maxLevel }}). Nobody can manage a role at or above their own
                level.{{ $role->is_system ? ' System roles have a fixed level.' : '' }}
            </div>
            @error('level') <div class="err">{{ $message }}</div> @enderror
        </div>
    </div>

    <h3 style="font-size:16px; margin:0 0 4px;">Permissions</h3>
    <p style="margin:0 0 14px; color:var(--muted); font-size:13.5px;">
        @if ($role->is_super)
            This role bypasses every permission check, so individual grants below have no effect.
        @else
            Tick each action this role may perform. Anything you cannot perform yourself is disabled.
        @endif
    </p>

    @foreach ($catalog as $moduleKey => $module)
        @continue(empty($module['permissions']))
        <section class="vyt-permmodule" data-module="{{ $moduleKey }}">
            <header>
                <h4>{{ $module['label'] }}</h4>
                <button type="button" onclick="vytToggleModule('{{ $moduleKey }}')">Select all / none</button>
            </header>
            <div class="vyt-permlist">
                @foreach ($module['permissions'] as $permKey => $perm)
                    @php
                        $grantable = in_array($permKey, $grantableKeys, true);
                        $checked = in_array($permKey, old('permissions', $granted), true);
                    @endphp
                    <label @class(['vyt-permrow', 'is-disabled' => ! $grantable])>
                        <input type="checkbox" name="permissions[]" value="{{ $permKey }}"
                               @checked($checked) @disabled(! $grantable)>
                        <span class="txt">
                            <strong>{{ $perm['label'] }} <code>{{ $permKey }}</code></strong>
                            <span>{{ $perm['description'] }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </section>
    @endforeach

    <div style="display:flex; gap:12px; align-items:center; margin-top:20px;">
        <button type="submit" class="vyt-btn">{{ $isEdit ? 'Save role' : 'Create role' }}</button>
        <a href="{{ route('admin.roles.index') }}" style="font-size:14px; color:var(--muted);">Cancel</a>
    </div>
</form>

@push('scripts')
    <script>
        function vytToggleModule(moduleKey) {
            const boxes = document.querySelectorAll(
                '[data-module="' + moduleKey + '"] input[type=checkbox]:not(:disabled)'
            );
            const turnOn = Array.from(boxes).some(b => !b.checked);
            boxes.forEach(b => { b.checked = turnOn; });
        }
    </script>
@endpush

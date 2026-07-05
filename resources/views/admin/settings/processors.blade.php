@extends('dashboard.layout')

@section('eyebrow', 'Admin · Settings')
@section('title', 'Payment Processors')

@push('head')
    @include('admin.settings._styles')
@endpush

@section('content')
    <section class="vyt-section">
        @include('admin.settings._nav', ['activeScreen' => 'processors'])

        <p class="vyt-faint" style="margin-bottom:16px;font-size:13px;">
            Routing tries enabled processors by priority (lower first). Credentials are encrypted at rest,
            never shown after saving, and an empty field keeps the current value. "Test connection" runs
            server-side and reports pass/fail only.
        </p>

        @foreach ($processors as $processor)
            <div class="vyt-card" style="margin-bottom:16px;">
                <div class="vyt-card-header">
                    <h3>
                        {{ $processor->display_name }}
                        <span class="vyt-pill {{ $processor->enabled ? 'vyt-flag-on' : 'vyt-flag-off' }}" style="margin-left:8px;">{{ $processor->enabled ? 'enabled' : 'disabled' }}</span>
                        @if ($processor->is_default)
                            <span class="vyt-pill" style="background:#fdf2f8;color:#be185d;margin-left:4px;">default</span>
                        @endif
                        <span class="vyt-pill" style="background:{{ $processor->mode === 'live' ? '#ecfdf5' : '#fef3c7' }};color:{{ $processor->mode === 'live' ? '#047857' : '#92400e' }};margin-left:4px;">{{ $processor->mode }}</span>
                    </h3>
                    <form method="POST" action="{{ route('admin.settings.processors.test', $processor->code) }}">
                        @csrf
                        <button type="submit"
                                style="background:#fff;border:1px solid var(--line);padding:7px 16px;border-radius:999px;font-weight:600;font-size:12px;cursor:pointer;">
                            Test connection
                        </button>
                    </form>
                </div>

                <form method="POST" action="{{ route('admin.settings.processors.update', $processor->code) }}">
                    @csrf
                    @method('PUT')

                    <div class="vyt-colform" style="border-top:0;">
                        <div>
                            <label>Enabled</label>
                            <input type="hidden" name="enabled" value="0">
                            <label class="vyt-switch">
                                <input type="checkbox" name="enabled" value="1" @checked($processor->enabled)>
                                <span class="vyt-slider"></span>
                            </label>
                        </div>
                        <div>
                            <label for="mode-{{ $processor->code }}">Mode</label>
                            <select id="mode-{{ $processor->code }}" name="mode">
                                <option value="test" @selected($processor->mode === 'test')>test</option>
                                <option value="live" @selected($processor->mode === 'live')>live</option>
                            </select>
                        </div>
                        <div>
                            <label for="priority-{{ $processor->code }}">Priority (lower = first)</label>
                            <input type="number" id="priority-{{ $processor->code }}" name="priority" value="{{ $processor->priority }}" min="1" max="999">
                        </div>
                        <div>
                            <label>Default processor</label>
                            <input type="hidden" name="is_default" value="0">
                            <label class="vyt-switch">
                                <input type="checkbox" name="is_default" value="1" @checked($processor->is_default)>
                                <span class="vyt-slider"></span>
                            </label>
                        </div>
                        <div>
                            <label for="min-{{ $processor->code }}">Min amount ($)</label>
                            <input type="number" step="0.01" min="0" id="min-{{ $processor->code }}" name="min_amount_dollars"
                                   value="{{ $processor->min_amount_cents !== null ? number_format($processor->min_amount_cents / 100, 2, '.', '') : '' }}">
                        </div>
                        <div>
                            <label for="max-{{ $processor->code }}">Max amount ($)</label>
                            <input type="number" step="0.01" min="0" id="max-{{ $processor->code }}" name="max_amount_dollars"
                                   value="{{ $processor->max_amount_cents !== null ? number_format($processor->max_amount_cents / 100, 2, '.', '') : '' }}">
                        </div>

                        @php
                            $fields = $credentialFields[$processor->code] ?? $credentialFields['default'];
                            $saved = $processor->credentials ?? [];
                        @endphp
                        @foreach ($fields as $field)
                            <div>
                                <label for="cred-{{ $processor->code }}-{{ $field }}">{{ str_replace('_', ' ', $field) }}</label>
                                <input type="password" autocomplete="new-password"
                                       id="cred-{{ $processor->code }}-{{ $field }}"
                                       name="credentials[{{ $field }}]" value=""
                                       placeholder="{{ array_key_exists($field, $saved) ? '•••••••• (set)' : 'not set' }}">
                            </div>
                        @endforeach

                        <div class="actions">
                            <button type="submit">Save {{ $processor->display_name }}</button>
                            @if ($processor->updatedBy)
                                <span class="vyt-faint" style="font-size:12px;align-self:center;">
                                    Last changed by {{ $processor->updatedBy->name }} · {{ $processor->updated_at->diffForHumans() }}
                                </span>
                            @endif
                        </div>
                    </div>
                </form>
            </div>
        @endforeach
    </section>
@endsection

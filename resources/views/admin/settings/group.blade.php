@extends('dashboard.layout')

@section('eyebrow', 'Admin · Settings')
@section('title', $groupLabel)

@push('head')
    @include('admin.settings._styles')
@endpush

@section('content')
    <section class="vyt-section">
        @include('admin.settings._nav', ['activeGroup' => $group])

        @php $old = old('settings', []); @endphp

        <form method="POST" action="{{ route('admin.settings.group.update', $group) }}"
              x-data="{ dirty: false }" @input="dirty = true" @change="dirty = true">
            @csrf
            @method('PUT')

            <div class="vyt-setform">
                @foreach ($fields as $key => $field)
                    <div class="vyt-setrow">
                        <label class="vyt-setlabel" for="set-{{ $key }}">
                            {{ $field['label'] }}
                            @if ($field['help'])
                                <span class="vyt-sethelp">{{ $field['help'] }}</span>
                            @endif
                            @if ($field['public'])
                                <span class="vyt-sethelp">Public — exposed to the frontend bootstrap.</span>
                            @endif
                        </label>

                        <div>
                            @php $value = $old[$key] ?? $field['value']; @endphp

                            @if ($field['sensitive'])
                                <input type="password" id="set-{{ $key }}" name="settings[{{ $key }}]" value=""
                                       autocomplete="new-password"
                                       placeholder="{{ $field['has_value'] ? '•••••••• (set)' : 'not set' }}">
                                <span class="vyt-sethelp">Leave blank to keep the current value.</span>
                            @elseif ($field['type'] === 'bool')
                                <input type="hidden" name="settings[{{ $key }}]" value="0">
                                <label class="vyt-switch">
                                    <input type="checkbox" id="set-{{ $key }}" name="settings[{{ $key }}]" value="1" @checked((bool) $value)>
                                    <span class="vyt-slider"></span>
                                </label>
                            @elseif ($field['type'] === 'enum')
                                <select id="set-{{ $key }}" name="settings[{{ $key }}]">
                                    @foreach ($field['options'] ?? [] as $option)
                                        <option value="{{ $option }}" @selected((string) $value === (string) $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                            @elseif ($field['type'] === 'cents')
                                <div class="vyt-setaffix">
                                    <span>$</span>
                                    <input type="number" step="0.01" min="0" id="set-{{ $key }}" name="settings[{{ $key }}]"
                                           value="{{ is_numeric($value) ? number_format(((int) $value) / 100, 2, '.', '') : $value }}">
                                </div>
                            @elseif ($field['type'] === 'percent')
                                <div class="vyt-setaffix">
                                    <input type="number" step="1" id="set-{{ $key }}" name="settings[{{ $key }}]" value="{{ $value }}">
                                    <span>%</span>
                                </div>
                            @elseif ($field['type'] === 'int')
                                <input type="number" step="1" id="set-{{ $key }}" name="settings[{{ $key }}]" value="{{ $value }}">
                            @elseif ($field['type'] === 'json')
                                <textarea id="set-{{ $key }}" name="settings[{{ $key }}]" spellcheck="false">{{ is_string($value) ? $value : json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</textarea>
                            @elseif ($field['type'] === 'text')
                                <textarea id="set-{{ $key }}" name="settings[{{ $key }}]">{{ $value }}</textarea>
                            @else
                                <input type="text" id="set-{{ $key }}" name="settings[{{ $key }}]" value="{{ $value }}">
                            @endif

                            @if ($errors->has($key))
                                <div class="vyt-seterror">{{ $errors->first($key) }}</div>
                            @endif
                        </div>
                    </div>
                @endforeach

                <div class="vyt-savebar">
                    @if ($lastChange?->updatedBy)
                        <span class="vyt-lastchange">
                            Last changed by {{ $lastChange->updatedBy->name }} · {{ $lastChange->updated_at->diffForHumans() }}
                        </span>
                    @endif
                    <button type="submit" :disabled="! dirty" disabled>Save changes</button>
                </div>
            </div>
        </form>
    </section>
@endsection

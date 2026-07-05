@extends('dashboard.layout')

@section('eyebrow', 'Admin · Settings')
@section('title', 'Feature Flags')

@push('head')
    @include('admin.settings._styles')
@endpush

@section('content')
    <section class="vyt-section">
        @include('admin.settings._nav', ['activeScreen' => 'flags'])

        {{-- One form per flag row, via the HTML form= attribute (a <form>
             can't legally wrap <td>s). --}}
        @foreach ($flags as $flag)
            <form id="flag-{{ $loop->index }}" method="POST" action="{{ route('admin.settings.flags.update', $flag->key) }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="enabled" value="0">
            </form>
        @endforeach

        <div class="vyt-card">
            <div class="vyt-card-header">
                <h3>Feature flags · {{ $flags->count() }}</h3>
            </div>
            <table class="vyt-table">
                <thead>
                    <tr>
                        <th>Flag</th>
                        <th>State</th>
                        <th>Scope</th>
                        <th>Rollout %</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($flags as $flag)
                        @php $formId = 'flag-'.$loop->index; @endphp
                        <tr>
                            <td>
                                <span style="font-weight:600;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:13px;">{{ $flag->key }}</span>
                                <span class="vyt-pill {{ $flag->enabled ? 'vyt-flag-on' : 'vyt-flag-off' }}" style="margin-left:8px;">{{ $flag->enabled ? 'on' : 'off' }}</span>
                                <div class="vyt-faint" style="max-width:420px;">{{ $flag->description }}</div>
                                @if ($flag->updatedBy)
                                    <div class="vyt-faint" style="font-size:11px;">Last changed by {{ $flag->updatedBy->name }} · {{ $flag->updated_at->diffForHumans() }}</div>
                                @endif
                            </td>
                            <td>
                                <label class="vyt-switch">
                                    <input type="checkbox" name="enabled" value="1" form="{{ $formId }}" @checked($flag->enabled)>
                                    <span class="vyt-slider"></span>
                                </label>
                            </td>
                            <td style="white-space:nowrap;">
                                <select name="scope" form="{{ $formId }}" style="padding:6px 8px;border:1px solid var(--line);border-radius:8px;font-size:13px;">
                                    @foreach (['global', 'role', 'audience', 'environment'] as $scope)
                                        <option value="{{ $scope }}" @selected($flag->scope === $scope)>{{ $scope }}</option>
                                    @endforeach
                                </select>
                                <input type="text" name="scope_value" value="{{ $flag->scope_value }}" placeholder="scope value" form="{{ $formId }}"
                                       style="width:110px;padding:6px 8px;border:1px solid var(--line);border-radius:8px;font-size:13px;">
                            </td>
                            <td>
                                <input type="number" name="rollout_pct" value="{{ $flag->rollout_pct }}" min="0" max="100" placeholder="100" form="{{ $formId }}"
                                       style="width:70px;padding:6px 8px;border:1px solid var(--line);border-radius:8px;font-size:13px;">
                            </td>
                            <td>
                                <button type="submit" form="{{ $formId }}"
                                        style="background:var(--gradient);color:#fff;border:0;padding:7px 16px;border-radius:999px;font-weight:600;font-size:12px;cursor:pointer;">
                                    Save
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection

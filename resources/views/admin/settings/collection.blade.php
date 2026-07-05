@extends('dashboard.layout')

@section('eyebrow', 'Admin · Settings')
@section('title', $config['title'])

@push('head')
    @include('admin.settings._styles')
@endpush

@section('content')
    <section class="vyt-section">
        @include('admin.settings._nav', ['activeScreen' => 'collection:'.$collection])

        <div class="vyt-card" style="margin-bottom:16px;">
            <div class="vyt-card-header">
                <h3>{{ $config['title'] }} · {{ $rows->count() }}</h3>
            </div>

            @if ($rows->isEmpty())
                <div class="vyt-card-empty">Nothing here yet — add the first entry below.</div>
            @else
                <table class="vyt-table">
                    <thead>
                        <tr>
                            @foreach ($config['columns'] as $column)
                                <th>{{ str_replace('_', ' ', $column) }}</th>
                            @endforeach
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                @foreach ($config['columns'] as $column)
                                    <td>
                                        @php $cell = $row->{$column}; @endphp
                                        @if (is_bool($cell))
                                            <span class="vyt-pill {{ $cell ? 'vyt-flag-on' : 'vyt-flag-off' }}">{{ $cell ? 'yes' : 'no' }}</span>
                                        @else
                                            {{ $cell }}
                                        @endif
                                    </td>
                                @endforeach
                                <td style="text-align:right;">
                                    <a href="#edit-{{ $collection }}-{{ $row->id }}"
                                       style="font-size:12px;font-weight:600;">Edit ↓</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                {{-- Edit forms (one per row, below the table to keep the DOM valid) --}}
                @foreach ($rows as $row)
                    <details id="edit-{{ $collection }}-{{ $row->id }}" style="border-top:1px solid var(--line);">
                        <summary style="padding:10px 20px;font-size:13px;font-weight:600;cursor:pointer;color:var(--muted);">
                            Edit: {{ $row->{$config['columns'][1] ?? $config['columns'][0]} ?? $row->{$config['columns'][0]} }}
                        </summary>
                        <form method="POST" action="{{ route('admin.settings.collections.update', [$collection, $row->id]) }}">
                            @csrf
                            @method('PUT')
                            <div class="vyt-colform">
                                @foreach ($config['fields'] as $field => $spec)
                                    @include('admin.settings._collection_field', ['field' => $field, 'spec' => $spec, 'value' => $row->{$field}, 'idPrefix' => 'edit-'.$row->id])
                                @endforeach
                                <div class="actions">
                                    <button type="submit">Save</button>
                                </div>
                            </div>
                        </form>
                    </details>
                @endforeach
            @endif
        </div>

        {{-- Create --}}
        <div class="vyt-card" x-data="{ open: false }">
            <div class="vyt-card-header" style="cursor:pointer;" @click="open = ! open">
                <h3>+ New entry</h3>
            </div>
            <div x-show="open" x-cloak>
                <form method="POST" action="{{ route('admin.settings.collections.store', $collection) }}">
                    @csrf
                    <div class="vyt-colform" style="border-top:0;">
                        @foreach ($config['fields'] as $field => $spec)
                            @include('admin.settings._collection_field', ['field' => $field, 'spec' => $spec, 'value' => old($field), 'idPrefix' => 'new'])
                        @endforeach
                        <div class="actions">
                            <button type="submit">Create</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </section>
@endsection

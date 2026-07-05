@extends('dashboard.layout')

@section('eyebrow', 'Admin · Settings')
@section('title', 'Email & SMS Templates')

@push('head')
    @include('admin.settings._styles')
@endpush

@section('content')
    <section class="vyt-section">
        @include('admin.settings._nav', ['activeScreen' => 'templates'])

        <div class="vyt-setnav" style="margin-bottom:14px;">
            <a href="{{ route('admin.settings.templates', ['channel' => 'email']) }}" @class(['active' => $channel === 'email'])>Email</a>
            <a href="{{ route('admin.settings.templates', ['channel' => 'sms']) }}" @class(['active' => $channel === 'sms'])>SMS</a>
        </div>

        <p class="vyt-faint" style="margin-bottom:16px;font-size:13px;">
            Versions are immutable — saving publishes a new version and retires the old one, so any message
            ever sent can be traced to the exact copy that was live. Merge tags use <code>@{{ double_braces }}</code>.
        </p>

        @foreach ($templates as $key => $versions)
            @php $live = $versions->firstWhere('active', true) ?? $versions->first(); @endphp
            <div class="vyt-card" style="margin-bottom:16px;" x-data="{ open: false }">
                <div class="vyt-card-header" style="cursor:pointer;" @click="open = ! open">
                    <h3>
                        {{ $live->name }}
                        <span class="vyt-faint" style="font-weight:400;font-size:12px;margin-left:8px;font-family:ui-monospace,Menlo,Consolas,monospace;">{{ $key }}</span>
                        <span class="vyt-pill vyt-flag-on" style="margin-left:8px;">v{{ $live->version }}</span>
                    </h3>
                    <span class="vyt-faint" style="font-size:12px;" x-text="open ? 'collapse' : 'edit'"></span>
                </div>

                <div x-show="open" x-cloak>
                    <form method="POST" action="{{ route('admin.settings.templates.store') }}">
                        @csrf
                        <input type="hidden" name="channel" value="{{ $channel }}">
                        <input type="hidden" name="key" value="{{ $key }}">

                        <div class="vyt-colform" style="border-top:0;">
                            <div class="full">
                                <label>Name</label>
                                <input type="text" name="name" value="{{ $live->name }}" required>
                            </div>
                            @if ($channel === 'email')
                                <div class="full">
                                    <label>Subject</label>
                                    <input type="text" name="subject" value="{{ $live->subject }}" required>
                                </div>
                                <div class="full">
                                    <label>Body (Markdown)</label>
                                    <textarea name="body_markdown" rows="10" required>{{ $live->body_markdown }}</textarea>
                                </div>
                            @else
                                <div class="full">
                                    <label>Body</label>
                                    <textarea name="body_text" rows="4" maxlength="1600" required>{{ $live->body_text }}</textarea>
                                </div>
                            @endif

                            @if ($live->variables)
                                <div class="full vyt-faint" style="font-size:12px;">
                                    Available merge tags:
                                    @foreach ($live->variables as $variable)
                                        <code>@{{ {{ $variable }} }}</code>@if (! $loop->last) · @endif
                                    @endforeach
                                </div>
                            @endif

                            <div class="actions">
                                <button type="submit">Publish as v{{ $live->version + 1 }}</button>
                                <span class="vyt-faint" style="font-size:12px;align-self:center;">
                                    {{ $versions->count() }} {{ Str::plural('version', $versions->count()) }} on record
                                </span>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach

        {{-- New template --}}
        <div class="vyt-card" x-data="{ open: false }">
            <div class="vyt-card-header" style="cursor:pointer;" @click="open = ! open">
                <h3>+ New {{ $channel }} template</h3>
            </div>
            <div x-show="open" x-cloak>
                <form method="POST" action="{{ route('admin.settings.templates.store') }}">
                    @csrf
                    <input type="hidden" name="channel" value="{{ $channel }}">
                    <div class="vyt-colform" style="border-top:0;">
                        <div>
                            <label>Key (a-z, 0-9, _)</label>
                            <input type="text" name="key" pattern="[a-z0-9_]+" required placeholder="payout_sent">
                        </div>
                        <div class="full">
                            <label>Name</label>
                            <input type="text" name="name" required>
                        </div>
                        @if ($channel === 'email')
                            <div class="full">
                                <label>Subject</label>
                                <input type="text" name="subject" required>
                            </div>
                            <div class="full">
                                <label>Body (Markdown)</label>
                                <textarea name="body_markdown" rows="8" required></textarea>
                            </div>
                        @else
                            <div class="full">
                                <label>Body</label>
                                <textarea name="body_text" rows="4" maxlength="1600" required></textarea>
                            </div>
                        @endif
                        <div class="actions">
                            <button type="submit">Create template</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </section>
@endsection

@extends('layouts.base')

@section('title', 'Demo data &middot; Vaytoven Admin')
@section('section', 'Admin')

@section('content')
    @include('partials.admin-nav')

    <h1>Demo data</h1>
    <p style="color:var(--muted);margin-top:-12px;max-width:74ch;">
        Two kinds of account here are not real people. They are removed separately,
        because they are needed for different lengths of time.
    </p>

    @if (session('success'))
        <div class="site-alert" style="background:#f0fdf4;border-color:#bbf7d0;color:#166534;">{{ session('success') }}</div>
    @endif
    @error('confirmation')
        <div class="site-alert" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;">{{ $message }}</div>
    @enderror

    {{-- How close the real listings are to carrying the site on their own.
         The demo listings exist to stop the storefront looking empty, so this
         number is the whole answer to "can they go yet". --}}
    @php
        $pct   = min(100, (int) round($progress['real'] / max(1, $progress['target']) * 100));
        $short = max(0, $progress['target'] - $progress['real']);
    @endphp
    <div class="vyt-card" style="margin-top:18px;">
        <div class="vyt-card-header">
            <h3>Real listings</h3>
            <span class="vyt-section-meta">{{ $progress['real'] }} of {{ $progress['target'] }} &middot; active only</span>
        </div>
        <div class="vyt-card-body">
            <div style="height:10px;background:#f1f5f9;border-radius:999px;overflow:hidden;">
                <div style="height:100%;width:{{ $pct }}%;background:var(--gradient);border-radius:999px;"></div>
            </div>
            <p style="margin:14px 0 0;color:var(--muted);font-size:13.5px;">
                @if ($progress['ready'])
                    You have enough real listings to carry the public site. The demo
                    listings can go whenever you want.
                @else
                    {{ $short }} more real {{ Str::plural('listing', $short) }} before the demo
                    listings stop doing any work. Until then they are what a visitor sees
                    on a page that would otherwise be nearly empty.
                @endif
            </p>
        </div>
    </div>

    @foreach ($groups as $group)
        @php
            $preview  = $group['preview'];
            $accounts = $preview['accounts'];
            $isDemo   = $group['key'] === 'demo';
            $held     = $isDemo && ! $progress['ready'];
            $edge     = $held ? 'var(--line)' : '#fecaca';
            $listings = $preview['counts']['Listings'] ?? 0;
            $short_   = 'the '.lcfirst($group['label']);
        @endphp

        <div class="vyt-card" style="margin-top:18px;border-color:{{ $edge }};">
            <div class="vyt-card-header">
                <h3>{{ $group['label'] }}</h3>
                <span class="vyt-section-meta"><code>{{ $group['suffix'] }}</code></span>
            </div>

            <div class="vyt-card-body">
                <p style="margin:0 0 16px;color:var(--muted);font-size:13.5px;max-width:74ch;">
                    {{ $group['blurb'] }}
                </p>

                @if (empty($accounts))
                    <div class="vyt-faint">None found. Nothing here to remove.</div>
                @else
                    <div class="vyt-tiles">
                        @foreach ($preview['counts'] as $label => $count)
                            @php($tone = $count > 0 ? 't-pink' : '')
                            <div class="vyt-tile">
                                <div class="vyt-tile-label">{{ $label }}</div>
                                <span class="vyt-tile-value {{ $tone }}">{{ number_format($count) }}</span>
                            </div>
                        @endforeach
                    </div>

                    {{-- The demo group is small enough to name every account. The
                         test group runs to the hundreds, so it gets a sample and a
                         count instead of an unreadable wall. --}}
                    @if (count($accounts) <= 40)
                        <table class="vyt-table" style="margin-top:18px;">
                            <thead><tr><th>Account</th><th>Role</th><th>Privilege</th></tr></thead>
                            <tbody>
                                @foreach ($accounts as $account)
                                    <tr>
                                        <td class="vyt-mono">{{ $account['email'] }}</td>
                                        <td>{{ $account['role'] }}</td>
                                        <td>
                                            @if ($account['staff'])
                                                <span class="vyt-pill" style="border-color:#fca5a5;color:#b91c1c;">staff</span>
                                            @else
                                                <span class="vyt-faint">&mdash;</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        @php($sample = array_slice($accounts, 0, 5))
                        <div style="margin-top:18px;font-size:13.5px;">
                            <div style="margin-bottom:8px;">
                                <strong>{{ number_format(count($accounts)) }} accounts</strong>, for example:
                            </div>
                            @foreach ($sample as $account)
                                <div class="vyt-mono vyt-faint">{{ $account['email'] }}</div>
                            @endforeach
                            <div class="vyt-faint" style="margin-top:8px;">
                                &hellip; and {{ number_format(count($accounts) - count($sample)) }} more, all on
                                <code>{{ $group['suffix'] }}</code>.
                            </div>
                        </div>
                    @endif

                    {{-- The action ------------------------------------------------ --}}
                    <div style="margin-top:22px;padding-top:20px;border-top:1px solid var(--line);">
                        @if ($held)
                            <p style="margin:0 0 14px;font-size:13.5px;">
                                <strong>Held.</strong> Removing these now would take
                                {{ $listings }} {{ Str::plural('listing', $listings) }}
                                off the public site with only {{ $progress['real'] }} real
                                {{ Str::plural('listing', $progress['real']) }} left to fill it.
                                The form below still works &mdash; but the site will look
                                emptier straight afterwards.
                            </p>
                        @endif

                        <p style="margin:0 0 14px;font-size:13.5px;">
                            Permanent, and there is no undo. Photos and documents are deleted
                            from storage along with the rows.
                        </p>

                        @php($areYouSure = 'Permanently remove '.$short_.'? This cannot be undone.')
                        <form method="POST" action="{{ route('admin.demo-data.destroy') }}"
                              onsubmit="return confirm(@js($areYouSure));">
                            @csrf @method('DELETE')
                            <input type="hidden" name="scope" value="{{ $group['key'] }}">

                            <div class="vyt-field" style="max-width:340px;">
                                <label for="confirm-{{ $group['key'] }}">
                                    Type <code>{{ $group['confirm'] }}</code> to confirm
                                </label>
                                <input id="confirm-{{ $group['key'] }}" name="confirmation" type="text"
                                       autocomplete="off" placeholder="{{ $group['confirm'] }}" required>
                            </div>

                            <button type="submit"
                                    style="margin-top:14px;background:#b91c1c;color:#fff;border:0;padding:11px 22px;border-radius:999px;font-weight:600;font-size:14px;cursor:pointer;">
                                Remove {{ lcfirst($group['label']) }}
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    @endforeach

    {{-- What stays, whichever button is pressed. --}}
    @php($retained = $groups['demo']['preview']['retained'] ?? [])
    @if (! empty($retained))
        <div class="vyt-card" style="margin-top:18px;">
            <div class="vyt-card-header"><h3>What stays either way</h3></div>
            <div class="vyt-card-body">
                <table class="vyt-table">
                    <tbody>
                        @foreach ($retained as $label => $why)
                            <tr>
                                <td style="width:26%;"><strong>{{ $label }}</strong></td>
                                <td class="vyt-faint">{{ $why }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection

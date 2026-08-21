@extends('layouts.base')

@section('title', 'Demo data &middot; Vaytoven Admin')
@section('section', 'Admin')

@section('content')
    @include('partials.admin-nav')

    <h1>Demo data</h1>
    <p style="color:var(--muted);margin-top:-12px;max-width:72ch;">
        The seeded accounts and listings that let the site show something before real
        members arrived. Once you have real clients they stop being a demonstration and
        start being fiction in the same tables as the real thing &mdash; publicly listed,
        counted in your totals, and hard to tell apart at a glance.
    </p>

    @if (session('success'))
        <div class="site-alert" style="background:#f0fdf4;border-color:#bbf7d0;color:#166534;">{{ session('success') }}</div>
    @endif
    @error('confirmation')
        <div class="site-alert" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;">{{ $message }}</div>
    @enderror

    @if (empty($preview['accounts']))
        <div class="vyt-card">
            <div class="vyt-card-empty">
                No demo accounts found. Nothing here to remove.
            </div>
        </div>
    @else
        {{-- What goes ---------------------------------------------------------- --}}
        <div class="vyt-card">
            <div class="vyt-card-header">
                <h3>What would be removed</h3>
                <span class="vyt-section-meta">accounts ending <code>{{ $preview['suffix'] }}</code></span>
            </div>

            <div class="vyt-card-body">
                <table class="vyt-table">
                    <thead><tr><th>Account</th><th>Role</th><th>Privilege</th></tr></thead>
                    <tbody>
                        @foreach ($preview['accounts'] as $account)
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

                <div class="vyt-tiles" style="margin-top:18px;">
                    @foreach ($preview['counts'] as $label => $count)
                        <div class="vyt-tile">
                            <div class="vyt-tile-label">{{ $label }}</div>
                            <span class="vyt-tile-value {{ $count > 0 ? 't-pink' : '' }}">{{ number_format($count) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- What stays --------------------------------------------------------- --}}
        <div class="vyt-card" style="margin-top:18px;">
            <div class="vyt-card-header"><h3>What stays</h3></div>
            <div class="vyt-card-body">
                <table class="vyt-table">
                    <tbody>
                        @foreach ($preview['retained'] as $label => $why)
                            <tr>
                                <td style="width:26%;"><strong>{{ $label }}</strong></td>
                                <td class="vyt-faint">{{ $why }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- The action ---------------------------------------------------------- --}}
        <div class="vyt-card" style="margin-top:18px;border-color:#fecaca;">
            <div class="vyt-card-header"><h3>Remove it</h3></div>
            <div class="vyt-card-body">
                <p style="margin:0 0 14px;">
                    Permanent, and there is no undo. Photos and documents are deleted from
                    storage along with the rows. Do this once you have real clients and no
                    longer need the site to look populated.
                </p>

                <form method="POST" action="{{ route('admin.demo-data.destroy') }}"
                      onsubmit="return confirm('Permanently remove all demo accounts and their data? This cannot be undone.');">
                    @csrf @method('DELETE')

                    <div class="vyt-field" style="max-width:320px;">
                        <label for="confirmation">
                            Type <code>{{ $confirmation }}</code> to confirm
                        </label>
                        <input id="confirmation" name="confirmation" type="text"
                               autocomplete="off" placeholder="{{ $confirmation }}" required>
                    </div>

                    <button type="submit"
                            style="margin-top:14px;background:#b91c1c;color:#fff;border:0;padding:11px 22px;border-radius:999px;font-weight:600;font-size:14px;cursor:pointer;">
                        Delete demo data permanently
                    </button>
                </form>
            </div>
        </div>
    @endif
@endsection

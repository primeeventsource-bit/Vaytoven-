<div class="vyt-card">
    <div class="vyt-card-header">
        <h3>Advertising periods</h3>
        <span class="vyt-section-meta">{{ $periods->count() }} on record</span>
    </div>

    @if ($periods->isEmpty())
        <div class="vyt-card-empty">
            Nothing activated yet.
            @if ($package)
                This member has a paid {{ $package->package->label() }} order
                ({{ $package->weeks }} {{ Str::plural('week', $package->weeks) }}) —
                activate it from
                <a href="{{ route('admin.member-services.index') }}">Member Services</a>.
            @else
                There is no paid order to activate.
            @endif
        </div>
    @else
        <table class="vyt-table">
            <thead>
                <tr>
                    <th>Property</th><th>From</th><th>To</th>
                    <th style="text-align:right;">Days left</th><th>Status</th><th>Activated by</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($periods as $period)
                    <tr>
                        <td>{{ $period->property?->title ?? '—' }}</td>
                        <td class="vyt-faint">{{ et($period->starts_at, 'M j, Y') }}</td>
                        <td class="vyt-faint">{{ et($period->ends_at, 'M j, Y') }}</td>
                        <td style="text-align:right;font-weight:600;">
                            {{-- daysRemaining() floors at zero: "-6 days left"
                                 is a number nobody can act on, and the expired
                                 status already carries that meaning. --}}
                            {{ $period->isLive() ? $period->daysRemaining() : '—' }}
                        </td>
                        <td><span class="vyt-pill">{{ $period->effectiveStatus()->label() }}</span></td>
                        <td class="vyt-faint">
                            {{ $period->activatedBy?->email ?? '—' }}
                            @if ($period->activated_at)
                                <span style="display:block;font-size:11.5px;">{{ et($period->activated_at, 'M j, g:ia') }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<p class="site-note" style="margin-top:14px;">
    Status is read through the clock, not the stored column — there is no scheduler
    on this environment, so a period that ran out reads as expired here whether or
    not anything has swept it.
</p>

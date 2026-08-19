<div class="vyt-card">
    <div class="vyt-card-header">
        <h3>Activity log</h3>
        <span class="vyt-section-meta">newest first · not editable</span>
    </div>

    @if (empty($timeline))
        <div class="vyt-card-empty">No recorded activity.</div>
    @else
        <div class="vyt-card-body">
            <ul class="m360-timeline">
                @foreach ($timeline as $event)
                    <li class="k-{{ $event['kind'] }}">
                        <span class="at">{{ et($event['at'], 'M j, Y g:ia') }}</span>
                        <span class="dot"></span>
                        <span>
                            {{ $event['label'] }}
                            @if ($event['detail'])
                                <span class="detail">{{ $event['detail'] }}</span>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>

<p class="site-note" style="margin-top:14px;">
    Assembled from the systems that already record events — the admin audit log,
    login sessions, terms acceptances, orders and listings. Every entry is a side
    effect of something that happened; nothing here can be written by hand, which
    is what makes it worth anything as evidence.
</p>

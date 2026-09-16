{{-- The member's advertisements and whether each has been reviewed and
     accepted. Expects $adStates from AdvertisementFulfillment::forMember(). --}}
@if (! empty($adStates) && $adStates->isNotEmpty())
    <section class="vyt-section">
        <div class="vyt-card">
            <div class="vyt-card-header">
                <h3>Your advertisements</h3>
                <span class="vyt-section-meta">Review and confirm each active advertisement</span>
            </div>
            @foreach ($adStates as $ad)
                @php($p = $ad['property'])
                <div style="padding:14px 22px;border-top:1px solid var(--line);display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                    <div>
                        <div style="font-weight:600;">{{ $p->title }}</div>
                        <div class="vyt-faint" style="font-size:12.5px;">{{ $p->reference }}</div>
                    </div>
                    @if ($ad['status'] === \App\Services\Fulfillment\AdvertisementFulfillment::STATUS_ACCEPTED)
                        <a href="{{ route('member.advertisements.show', $p) }}" class="vyt-pill" style="background:#ecfdf5;color:#047857;">Accepted · view</a>
                    @else
                        <a href="{{ route('member.advertisements.show', $p) }}"
                           style="background:var(--gradient);color:#fff;padding:9px 16px;border-radius:999px;font-weight:600;font-size:14px;">
                            Review &amp; accept
                        </a>
                    @endif
                </div>
            @endforeach
        </div>
    </section>
@endif

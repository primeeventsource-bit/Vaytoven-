{{--
  Photo carousel partial — wraps an Eloquent Collection of PropertyPhoto-like
  rows in a native scroll-snap carousel. Bound by /vyt-carousel.js.

  Required:
    $photos — Collection|array of objects/arrays with at least a 'url' key.
              Optional 'caption' key falls back to $alt or empty.
  Optional:
    $alt    — fallback alt text when no caption per photo (default: empty).
    $hero   — when true, applies .is-hero class (16:9 aspect, larger).
    $loading — img loading attribute, default 'lazy'. Pass 'eager' for above-the-fold.
--}}
@php
    $photos = is_iterable($photos ?? null) ? collect($photos) : collect();
    $alt = $alt ?? '';
    $hero = $hero ?? false;
    $loading = $loading ?? 'lazy';
@endphp

@if ($photos->isEmpty())
    {{-- Graceful empty: a flat tinted block, no carousel chrome. --}}
    <div class="vyt-carousel {{ $hero ? 'is-hero' : '' }}" aria-hidden="true"></div>
@else
    <div class="vyt-carousel {{ $hero ? 'is-hero' : '' }}" data-vyt-carousel>
        <div class="vyt-carousel-track">
            @foreach ($photos as $photo)
                @php
                    $url = is_array($photo) ? ($photo['url'] ?? null) : ($photo->url ?? null);
                    $caption = is_array($photo) ? ($photo['caption'] ?? null) : ($photo->caption ?? null);
                @endphp
                <div class="vyt-carousel-slide">
                    <img src="{{ $url }}" alt="{{ $caption ?: $alt }}" loading="{{ $loading }}" draggable="false">
                </div>
            @endforeach
        </div>
        @if ($photos->count() > 1)
            <button type="button" class="vyt-carousel-arrow is-prev" aria-label="Previous photo">‹</button>
            <button type="button" class="vyt-carousel-arrow is-next" aria-label="Next photo">›</button>
            <div class="vyt-carousel-dots" aria-hidden="true"></div>
        @endif
    </div>
@endif

{{-- Pagination that carries its own styles.

     Laravel's default view is written for Tailwind, which these pages do not
     load — its arrow icons then render at the full width of the page. This
     one depends on nothing but itself. --}}
@if ($paginator->hasPages())
    <nav class="vy-pager" role="navigation" aria-label="Pagination">
        <style>
            .vy-pager { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:10px; margin:8px 0; font-size:14px; }
            .vy-pager .vy-pager-info { color:#6b7280; font-size:13px; }
            .vy-pager ul { list-style:none; display:flex; flex-wrap:wrap; gap:6px; margin:0; padding:0; }
            .vy-pager a, .vy-pager span.vy-page { display:inline-flex; align-items:center; justify-content:center; min-width:36px; height:36px;
                padding:0 12px; border:1px solid #e7e5e4; border-radius:8px; background:#fff; color:#1d1f21; text-decoration:none; line-height:1; }
            .vy-pager a:hover { border-color:#D63384; color:#D63384; text-decoration:none; }
            .vy-pager .is-current span.vy-page { background:linear-gradient(135deg,#FF3D8A 0%,#D63384 50%,#7B2CBF 100%); color:#fff; border-color:transparent; font-weight:600; }
            .vy-pager .is-disabled span.vy-page { color:#b8b5b0; background:#fafaf9; }
        </style>

        <div class="vy-pager-info">
            @if ($paginator->firstItem())
                Showing {{ number_format($paginator->firstItem()) }}–{{ number_format($paginator->lastItem()) }} of {{ number_format($paginator->total()) }}
            @endif
        </div>

        <ul>
            @if ($paginator->onFirstPage())
                <li class="is-disabled" aria-disabled="true"><span class="vy-page">‹ Previous</span></li>
            @else
                <li><a href="{{ $paginator->previousPageUrl() }}" rel="prev">‹ Previous</a></li>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="is-disabled" aria-disabled="true"><span class="vy-page">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="is-current" aria-current="page"><span class="vy-page">{{ $page }}</span></li>
                        @else
                            <li><a href="{{ $url }}">{{ $page }}</a></li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <li><a href="{{ $paginator->nextPageUrl() }}" rel="next">Next ›</a></li>
            @else
                <li class="is-disabled" aria-disabled="true"><span class="vy-page">Next ›</span></li>
            @endif
        </ul>
    </nav>
@endif

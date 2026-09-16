{{-- Previous / Next only, self-styled. See vaytoven.blade.php. --}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination" style="display:flex;gap:8px;margin:8px 0;font-size:14px;">
        @php($btn = 'display:inline-flex;align-items:center;height:36px;padding:0 14px;border:1px solid #e7e5e4;border-radius:8px;background:#fff;text-decoration:none;line-height:1;')
        @if ($paginator->onFirstPage())
            <span style="{{ $btn }}color:#b8b5b0;background:#fafaf9;">‹ Previous</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" style="{{ $btn }}color:#1d1f21;">‹ Previous</a>
        @endif

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" style="{{ $btn }}color:#1d1f21;">Next ›</a>
        @else
            <span style="{{ $btn }}color:#b8b5b0;background:#fafaf9;">Next ›</span>
        @endif
    </nav>
@endif

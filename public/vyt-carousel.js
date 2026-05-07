/**
 * Property photo carousel — vanilla JS, native scroll-snap.
 *
 * Looks for [data-vyt-carousel] containers on the page. Each carousel has:
 *   .vyt-carousel-track  — horizontal flex; native scroll-snap-x
 *   .vyt-carousel-slide  — one per photo
 *   .vyt-carousel-dots   — dot indicators (filled in by JS)
 *   .vyt-carousel-arrow  — optional left/right buttons
 *
 * The native scroll handles swipe on touch devices. The JS just:
 *   - tracks the active slide via IntersectionObserver
 *   - updates the dot indicator state
 *   - wires arrow buttons to scrollBy ±slideWidth
 *   - kills the parent <a> click when the user is dragging the slide
 *     (prevents accidental navigation while flicking through photos)
 *
 * Defer-loaded; runs once on DOMContentLoaded then mounts new carousels
 * via mutation observer (so the index pagination + filter re-renders also
 * pick up new ones — no full page reload required).
 */
(function (window, document) {
    'use strict';

    function bind(carousel) {
        if (carousel.dataset.vytCarouselBound) return;
        carousel.dataset.vytCarouselBound = '1';

        var track = carousel.querySelector('.vyt-carousel-track');
        var slides = Array.from(carousel.querySelectorAll('.vyt-carousel-slide'));
        var dotsHost = carousel.querySelector('.vyt-carousel-dots');
        var prevBtn = carousel.querySelector('.vyt-carousel-arrow.is-prev');
        var nextBtn = carousel.querySelector('.vyt-carousel-arrow.is-next');
        if (!track || slides.length <= 1) {
            // Single photo — hide arrows + dots if present.
            if (prevBtn) prevBtn.style.display = 'none';
            if (nextBtn) nextBtn.style.display = 'none';
            if (dotsHost) dotsHost.style.display = 'none';
            return;
        }

        // Render dots.
        if (dotsHost) {
            dotsHost.innerHTML = slides.map(function (_, i) {
                return '<button type="button" class="vyt-carousel-dot' + (i === 0 ? ' is-active' : '') + '" data-vyt-go="' + i + '" aria-label="Show photo ' + (i + 1) + '"></button>';
            }).join('');

            dotsHost.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-vyt-go]');
                if (!btn) return;
                e.preventDefault();
                e.stopPropagation();
                slides[parseInt(btn.dataset.vytGo, 10)].scrollIntoView({ behavior: 'smooth', inline: 'start', block: 'nearest' });
            });
        }

        // Arrow buttons.
        function step(direction) {
            track.scrollBy({ left: direction * track.clientWidth, behavior: 'smooth' });
        }
        if (prevBtn) {
            prevBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                step(-1);
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                step(1);
            });
        }

        // Active slide tracking via IntersectionObserver — cheaper than
        // listening to scroll + measuring on every frame.
        var dots = dotsHost ? Array.from(dotsHost.querySelectorAll('.vyt-carousel-dot')) : [];
        if ('IntersectionObserver' in window) {
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting && entry.intersectionRatio > 0.55) {
                        var idx = slides.indexOf(entry.target);
                        if (idx >= 0) {
                            dots.forEach(function (d, i) { d.classList.toggle('is-active', i === idx); });
                        }
                    }
                });
            }, { root: track, threshold: [0.55, 0.9] });
            slides.forEach(function (s) { io.observe(s); });
        }

        // Suppress parent <a> navigation during a drag — without this, a
        // touch flick on the card photo accidentally opens the listing.
        var dragStartX = null;
        track.addEventListener('pointerdown', function (e) { dragStartX = e.clientX; });
        track.addEventListener('click', function (e) {
            if (dragStartX !== null && Math.abs(e.clientX - dragStartX) > 8) {
                e.preventDefault();
                e.stopPropagation();
            }
            dragStartX = null;
        }, true);
    }

    function init() {
        document.querySelectorAll('[data-vyt-carousel]').forEach(bind);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Pick up carousels added later (e.g., after a filter re-render via
    // soft-reload or future pjax).
    if ('MutationObserver' in window) {
        new MutationObserver(function () { init(); })
            .observe(document.body, { childList: true, subtree: true });
    }
})(window, document);

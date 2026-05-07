/**
 * Vrbo-style search bar — destination autocomplete, dual-month calendar
 * date range picker, and guests stepper. Vanilla JS, no frameworks.
 *
 * Activates on every <form data-vyt-search> on the page. Multiple bars
 * coexist (e.g., landing hero + sticky bar at top of /properties results).
 *
 * Submits to /properties via the form's action with q, check_in, check_out,
 * adults, children, infants query params.
 */
(function (window, document) {
    'use strict';

    var SUGGEST_ENDPOINT = '/api/v1/destinations/suggest';
    var SUGGEST_DEBOUNCE_MS = 180;

    function debounce(fn, ms) {
        var t;
        return function () {
            var ctx = this, args = arguments;
            clearTimeout(t);
            t = setTimeout(function () { fn.apply(ctx, args); }, ms);
        };
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function formatDate(d) {
        // YYYY-MM-DD in local time (avoid UTC drift across timezones).
        var y = d.getFullYear();
        var m = String(d.getMonth() + 1).padStart(2, '0');
        var day = String(d.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + day;
    }

    function shortDate(d) {
        return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
    }

    /* ── Autocomplete ───────────────────────────────────────────────────── */

    function bindAutocomplete(form) {
        var input = form.querySelector('[data-vyt-search-input]');
        var box = form.querySelector('[data-vyt-suggest]');
        if (!input || !box) return;

        var lastResults = [];
        var highlight = -1;

        function render(results) {
            lastResults = results;
            highlight = -1;
            if (!results.length) {
                box.innerHTML = '<div class="vyt-suggest-empty">No matches yet — try a city or country name.</div>';
                box.hidden = false;
                return;
            }

            var byType = { city: [], country: [], property: [] };
            results.forEach(function (r) { (byType[r.type] || []).push(r); });

            var html = '';
            var sectionTitles = { city: 'Cities', country: 'Regions', property: 'Properties' };
            var icons = { city: '📍', country: '🌍', property: '🏠' };

            ['city', 'country', 'property'].forEach(function (type) {
                if (!byType[type].length) return;
                html += '<div class="vyt-suggest-section">';
                html += '<div class="vyt-suggest-heading">' + sectionTitles[type] + '</div>';
                byType[type].forEach(function (r) {
                    var idx = results.indexOf(r);
                    html += '<div class="vyt-suggest-item" data-idx="' + idx + '">'
                        + '<div class="vyt-suggest-icon">' + icons[type] + '</div>'
                        + '<div class="vyt-suggest-text">'
                        + '<strong>' + escapeHtml(r.label) + '</strong>'
                        + '<span>' + escapeHtml(r.sublabel || '') + '</span>'
                        + '</div></div>';
                });
                html += '</div>';
            });

            box.innerHTML = html;
            box.hidden = false;

            box.querySelectorAll('.vyt-suggest-item').forEach(function (el) {
                el.addEventListener('mousedown', function (e) {
                    e.preventDefault(); // mousedown so blur doesn't fire first
                    pick(parseInt(el.dataset.idx, 10));
                });
            });
        }

        function pick(idx) {
            var r = lastResults[idx];
            if (!r) return;
            input.value = r.label;
            box.hidden = true;
            // Property suggestions take you straight to the listing.
            if (r.type === 'property' && r.property_id) {
                window.location.assign('/properties/' + r.property_id);
                return;
            }
            // Otherwise let the user keep building the query — focus shifts
            // to dates so they keep moving.
            var datesTrigger = form.querySelector('[data-vyt-dates-trigger]');
            if (datesTrigger) datesTrigger.focus();
            // Persist the destination on the dataset for the form submit.
            form.dataset.vytSelectedLat = r.lat;
            form.dataset.vytSelectedLng = r.lng;
            form.dataset.vytSelectedZoom = r.zoom;
            // Notify any map module subscribed to selections.
            window.dispatchEvent(new CustomEvent('vyt:destination-selected', { detail: r }));
        }

        function fetchSuggestions(q) {
            fetch(SUGGEST_ENDPOINT + '?q=' + encodeURIComponent(q), {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (input.value.trim() === q) render(data.suggestions || []);
                })
                .catch(function () { box.hidden = true; });
        }

        var debouncedFetch = debounce(fetchSuggestions, SUGGEST_DEBOUNCE_MS);

        input.addEventListener('input', function () {
            var q = input.value.trim();
            if (q.length < 2) { box.hidden = true; return; }
            debouncedFetch(q);
        });

        input.addEventListener('focus', function () {
            if (input.value.trim().length >= 2) {
                debouncedFetch(input.value.trim());
            }
        });

        input.addEventListener('blur', function () {
            // Hide after a tick so click on suggestion still fires.
            setTimeout(function () { box.hidden = true; }, 120);
        });

        input.addEventListener('keydown', function (e) {
            if (box.hidden) return;
            var items = box.querySelectorAll('.vyt-suggest-item');
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                highlight = Math.min(highlight + 1, items.length - 1);
                updateHighlight(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                highlight = Math.max(highlight - 1, 0);
                updateHighlight(items);
            } else if (e.key === 'Enter' && highlight >= 0) {
                e.preventDefault();
                pick(parseInt(items[highlight].dataset.idx, 10));
            } else if (e.key === 'Escape') {
                box.hidden = true;
            }
        });

        function updateHighlight(items) {
            items.forEach(function (el, i) {
                el.classList.toggle('is-highlighted', i === highlight);
            });
        }
    }

    /* ── Popovers (open/close coordination) ─────────────────────────────── */

    function bindPopovers(form) {
        var datesTrigger = form.querySelector('[data-vyt-dates-trigger]');
        var guestsTrigger = form.querySelector('[data-vyt-guests-trigger]');
        var datesPop = form.querySelector('[data-vyt-popover="dates"]');
        var guestsPop = form.querySelector('[data-vyt-popover="guests"]');

        function openPopover(pop) {
            // Close any other open popover first.
            form.querySelectorAll('[data-vyt-popover]').forEach(function (p) {
                if (p !== pop) p.hidden = true;
            });
            pop.hidden = false;
        }
        function closeAll() {
            form.querySelectorAll('[data-vyt-popover]').forEach(function (p) { p.hidden = true; });
        }

        if (datesTrigger) datesTrigger.addEventListener('click', function () {
            datesPop.hidden ? openPopover(datesPop) : closeAll();
        });
        if (guestsTrigger) guestsTrigger.addEventListener('click', function () {
            guestsPop.hidden ? openPopover(guestsPop) : closeAll();
        });

        form.querySelectorAll('[data-vyt-popover-close]').forEach(function (btn) {
            btn.addEventListener('click', closeAll);
        });

        // Click-outside to close.
        document.addEventListener('click', function (e) {
            if (!form.contains(e.target)) closeAll();
        });
    }

    /* ── Dual-month calendar ────────────────────────────────────────────── */

    function bindCalendar(form) {
        var mount = form.querySelector('[data-vyt-cal]');
        var fromInput = form.querySelector('[data-vyt-dates-from]');
        var toInput = form.querySelector('[data-vyt-dates-to]');
        var display = form.querySelector('[data-vyt-dates-display]');
        var clearBtn = form.querySelector('[data-vyt-dates-clear]');
        if (!mount) return;

        var now = new Date();
        var anchor = new Date(now.getFullYear(), now.getMonth(), 1);
        var fromDate = fromInput.value ? new Date(fromInput.value + 'T00:00:00') : null;
        var toDate = toInput.value ? new Date(toInput.value + 'T00:00:00') : null;

        function render() {
            var months = [
                renderMonth(new Date(anchor.getFullYear(), anchor.getMonth(), 1)),
                renderMonth(new Date(anchor.getFullYear(), anchor.getMonth() + 1, 1)),
            ];
            mount.innerHTML = ''
                + '<div class="vyt-cal-controls">'
                + '<button type="button" class="vyt-cal-nav" data-vyt-cal-prev aria-label="Previous month">‹</button>'
                + '<span style="font-size:13px; color: var(--muted);">Pick check-in &amp; check-out</span>'
                + '<button type="button" class="vyt-cal-nav" data-vyt-cal-next aria-label="Next month">›</button>'
                + '</div>'
                + months.join('');

            mount.querySelector('[data-vyt-cal-prev]').addEventListener('click', function () {
                anchor = new Date(anchor.getFullYear(), anchor.getMonth() - 1, 1);
                render();
            });
            mount.querySelector('[data-vyt-cal-next]').addEventListener('click', function () {
                anchor = new Date(anchor.getFullYear(), anchor.getMonth() + 1, 1);
                render();
            });

            mount.querySelectorAll('.vyt-cal-day:not(.is-empty):not(.is-past)').forEach(function (el) {
                el.addEventListener('click', function () {
                    var d = new Date(el.dataset.date + 'T00:00:00');
                    if (!fromDate || (fromDate && toDate)) {
                        fromDate = d; toDate = null;
                    } else if (d <= fromDate) {
                        fromDate = d; toDate = null;
                    } else {
                        toDate = d;
                    }
                    syncFields();
                    render();
                });
            });
        }

        function renderMonth(monthStart) {
            var year = monthStart.getFullYear();
            var month = monthStart.getMonth();
            var firstDay = monthStart.getDay();
            var daysInMonth = new Date(year, month + 1, 0).getDate();
            var monthLabel = monthStart.toLocaleString(undefined, { month: 'long', year: 'numeric' });

            var html = '<div class="vyt-cal-month">'
                + '<h4>' + monthLabel + '</h4>'
                + '<div class="vyt-cal-grid">';

            ['Su','Mo','Tu','We','Th','Fr','Sa'].forEach(function (d) {
                html += '<div class="dow">' + d + '</div>';
            });
            // leading empties
            for (var i = 0; i < firstDay; i++) {
                html += '<div class="vyt-cal-day is-empty"></div>';
            }
            for (var d = 1; d <= daysInMonth; d++) {
                var date = new Date(year, month, d);
                var iso = formatDate(date);
                var classes = ['vyt-cal-day'];
                var startToday = new Date(now.getFullYear(), now.getMonth(), now.getDate());
                if (date < startToday) classes.push('is-past');
                if (fromDate && date.getTime() === fromDate.getTime()) classes.push('is-from');
                if (toDate && date.getTime() === toDate.getTime()) classes.push('is-to');
                if (fromDate && toDate && date > fromDate && date < toDate) classes.push('is-between');
                html += '<div class="' + classes.join(' ') + '" data-date="' + iso + '">' + d + '</div>';
            }
            html += '</div></div>';
            return html;
        }

        function syncFields() {
            fromInput.value = fromDate ? formatDate(fromDate) : '';
            toInput.value = toDate ? formatDate(toDate) : '';
            if (fromDate && toDate) {
                display.textContent = shortDate(fromDate) + ' — ' + shortDate(toDate);
            } else if (fromDate) {
                display.textContent = shortDate(fromDate) + ' — Check-out';
            } else {
                display.textContent = 'Check-in — Check-out';
            }
        }

        if (clearBtn) clearBtn.addEventListener('click', function () {
            fromDate = null; toDate = null;
            syncFields();
            render();
        });

        syncFields();
        render();
    }

    /* ── Guests stepper ──────────────────────────────────────────────────── */

    function bindGuests(form) {
        var display = form.querySelector('[data-vyt-guests-display]');
        if (!display) return;

        function getCount(key) {
            var input = form.querySelector('[data-vyt-guests-' + key + ']');
            return input ? parseInt(input.value, 10) || 0 : 0;
        }
        function setCount(key, value, min, max) {
            value = Math.max(min, Math.min(max, value));
            var input = form.querySelector('[data-vyt-guests-' + key + ']');
            var counter = form.querySelector('[data-vyt-guests-count="' + key + '"]');
            if (input) input.value = value;
            if (counter) counter.textContent = value;
            updateDisplay();
            updateButtonStates();
        }
        function updateDisplay() {
            var total = getCount('adults') + getCount('children');
            var infants = getCount('infants');
            var label = total + ' ' + (total === 1 ? 'guest' : 'guests');
            if (infants) label += ', ' + infants + ' ' + (infants === 1 ? 'infant' : 'infants');
            display.textContent = label;
        }
        function updateButtonStates() {
            ['adults', 'children', 'infants'].forEach(function (key) {
                var inc = form.querySelector('[data-vyt-guests-increment="' + key + '"]');
                var dec = form.querySelector('[data-vyt-guests-decrement="' + key + '"]');
                if (!inc || !dec) return;
                var min = parseInt(inc.dataset.min, 10);
                var max = parseInt(inc.dataset.max, 10);
                var curr = getCount(key);
                dec.disabled = curr <= min;
                inc.disabled = curr >= max;
            });
        }

        form.querySelectorAll('[data-vyt-guests-increment]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var key = btn.dataset.vytGuestsIncrement;
                var max = parseInt(btn.dataset.max, 10);
                var min = parseInt(form.querySelector('[data-vyt-guests-increment="' + key + '"]').dataset.min, 10);
                setCount(key, getCount(key) + 1, min, max);
            });
        });
        form.querySelectorAll('[data-vyt-guests-decrement]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var key = btn.dataset.vytGuestsDecrement;
                var inc = form.querySelector('[data-vyt-guests-increment="' + key + '"]');
                var min = parseInt(inc.dataset.min, 10);
                var max = parseInt(inc.dataset.max, 10);
                setCount(key, getCount(key) - 1, min, max);
            });
        });

        updateDisplay();
        updateButtonStates();
    }

    /* ── Boot ────────────────────────────────────────────────────────────── */

    function init() {
        document.querySelectorAll('form[data-vyt-search]').forEach(function (form) {
            bindAutocomplete(form);
            bindPopovers(form);
            bindCalendar(form);
            bindGuests(form);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})(window, document);

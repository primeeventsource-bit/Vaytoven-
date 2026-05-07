/**
 * Properties results map — Leaflet + OpenStreetMap.
 *
 * Reads the JSON data island the index view ships (#vyt-map-data),
 * drops a price pin per property at its lat/lng, fits the map to the
 * bounding box of all visible pins, and wires bidirectional hover
 * highlighting between cards in the results list and pins on the map.
 *
 * Mobile (<1080px): the map column is hidden by default. The fixed
 * 'Show map' button at the bottom toggles it as a modal overlay.
 *
 * Subscribes to the 'vyt:destination-selected' CustomEvent the search
 * bar dispatches when a user picks a city/country/property suggestion,
 * and recentres the map without reloading the page.
 *
 * No bundler. No build step. Vanilla JS that runs after Leaflet's IIFE
 * has registered window.L.
 */
(function (window, document) {
    'use strict';

    function init() {
        var mapEl = document.getElementById('vyt-properties-map');
        var dataEl = document.getElementById('vyt-map-data');
        if (!mapEl || !dataEl || !window.L) return;

        var data;
        try { data = JSON.parse(dataEl.textContent); }
        catch (e) { return; }
        if (!Array.isArray(data) || data.length === 0) return;

        // OpenStreetMap tile layer — free, no token required.
        var map = L.map(mapEl, {
            scrollWheelZoom: true,
            attributionControl: true,
            zoomControl: true,
        });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        }).addTo(map);

        var pinsByPropertyId = {};
        var bounds = [];

        function priceLabel(cents) {
            return '$' + Math.round(cents / 100);
        }

        function popupHtml(p) {
            var img = p.photo
                ? '<img class="vyt-popup-img" src="' + p.photo + '" alt="' + escapeHtml(p.title) + '" loading="lazy">'
                : '<div class="vyt-popup-img" aria-hidden="true"></div>';
            return img
                + '<div class="vyt-popup-body">'
                + '<h4 class="vyt-popup-title">' + escapeHtml(p.title) + '</h4>'
                + '<div class="vyt-popup-loc">' + escapeHtml(p.city || '') + (p.country ? ', ' + escapeHtml(p.country) : '') + '</div>'
                + '<div class="vyt-popup-price">' + priceLabel(p.price) + ' <small>/ night</small></div>'
                + '</div>';
        }

        function escapeHtml(s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }

        data.forEach(function (p) {
            if (typeof p.lat !== 'number' || typeof p.lng !== 'number') return;
            if (p.lat === 0 && p.lng === 0) return; // skip un-geocoded rows

            var icon = L.divIcon({
                className: '',
                html: '<div class="vyt-price-pin" data-property-id="' + p.id + '">' + priceLabel(p.price) + '</div>',
                iconSize: [60, 28],
                iconAnchor: [30, 14],
            });
            var marker = L.marker([p.lat, p.lng], { icon: icon }).addTo(map);

            marker.bindPopup(popupHtml(p), { maxWidth: 240, autoPan: true });
            marker.on('popupopen', function () {
                // Click-through from popup body to listing.
                var popup = marker.getPopup();
                var node = popup.getElement();
                if (node) {
                    node.querySelector('.vyt-popup-body')?.addEventListener('click', function () {
                        window.location.assign(p.url);
                    });
                }
            });

            pinsByPropertyId[p.id] = marker;
            bounds.push([p.lat, p.lng]);
        });

        if (bounds.length) {
            map.fitBounds(bounds, { padding: [40, 40], maxZoom: 13 });
        } else {
            map.setView([20, 0], 2);
        }

        /* ── Bidirectional hover highlight ──────────────────────────── */

        function highlightProperty(id, on) {
            var marker = pinsByPropertyId[id];
            if (marker) {
                var el = marker.getElement();
                if (el) {
                    var pin = el.querySelector('.vyt-price-pin');
                    if (pin) pin.classList.toggle('is-active', on);
                }
            }
            var card = document.querySelector('.props-card[data-property-id="' + id + '"]');
            if (card) card.classList.toggle('is-map-hover', on);
        }

        document.querySelectorAll('.props-card[data-property-id]').forEach(function (card) {
            var id = parseInt(card.dataset.propertyId, 10);
            card.addEventListener('mouseenter', function () { highlightProperty(id, true); });
            card.addEventListener('mouseleave', function () { highlightProperty(id, false); });
        });

        Object.keys(pinsByPropertyId).forEach(function (id) {
            var marker = pinsByPropertyId[id];
            marker.on('mouseover', function () { highlightProperty(parseInt(id, 10), true); });
            marker.on('mouseout',  function () { highlightProperty(parseInt(id, 10), false); });
        });

        /* ── Mobile toggle ──────────────────────────────────────────── */

        var toggle = document.querySelector('[data-vyt-map-toggle]');
        var mapCol = document.querySelector('[data-vyt-map-col]');
        if (toggle && mapCol) {
            toggle.addEventListener('click', function () {
                var isOpen = mapCol.classList.toggle('is-mobile-open');
                toggle.querySelector('span').textContent = isOpen ? 'Show list' : 'Show map';
                toggle.setAttribute('aria-pressed', isOpen ? 'true' : 'false');
                if (isOpen) {
                    // Leaflet needs a re-layout after becoming visible.
                    setTimeout(function () { map.invalidateSize(); }, 50);
                }
            });
        }

        /* ── Search-bar selection re-centres the map ────────────────── */

        window.addEventListener('vyt:destination-selected', function (e) {
            var d = e.detail;
            if (!d || typeof d.lat !== 'number' || typeof d.lng !== 'number') return;
            map.flyTo([d.lat, d.lng], d.zoom || 11, { duration: 0.6 });
        });
    }

    // Leaflet ships via <script defer> on the same page; DOMContentLoaded
    // fires AFTER both scripts are parsed. Run init then.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})(window, document);

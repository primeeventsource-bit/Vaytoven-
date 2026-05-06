/**
 * Vaytoven tracking SDK (FR-10.3).
 *
 * Drop into any page with:
 *   <script src="/vyt-track.js" defer></script>
 *
 * Behaviour:
 *   - Sets a vyt_vid cookie (UUIDv4) on first visit, 1-year TTL.
 *   - Captures URL params (utm_source, utm_medium, utm_campaign, utm_term,
 *     utm_content, gclid, fbclid) into a vyt_utm cookie, 30-day TTL.
 *     Server-side first-touch attribution captures from this on first event;
 *     subsequent events do not overwrite.
 *   - Posts a 'page_view' event to /api/v1/tracking/events on script load.
 *   - Exposes window.Vaytoven.track(eventType, metadata) for app code to
 *     emit custom events (booking_started, modal_opened, etc).
 *
 * Hard rules respected:
 *   - No card data, no PII in metadata. The server's PII filter is a backstop.
 *   - Failures are silent (no exception bubbling) — tracking is non-critical.
 */
(function (window, document) {
    'use strict';

    var ENDPOINT = '/api/v1/tracking/events';
    var VID_COOKIE = 'vyt_vid';
    var UTM_COOKIE = 'vyt_utm';
    var VID_TTL_DAYS = 365;
    var UTM_TTL_DAYS = 30;

    function uuidv4() {
        // RFC 4122 v4. crypto.randomUUID() is the modern path; fallback below.
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }
        return ([1e7] + -1e3 + -4e3 + -8e3 + -1e11).replace(/[018]/g, function (c) {
            return (c ^ window.crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16);
        });
    }

    function setCookie(name, value, days) {
        var expires = new Date(Date.now() + days * 864e5).toUTCString();
        var sameSite = 'Lax';
        var secure = window.location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = name + '=' + encodeURIComponent(value)
            + '; expires=' + expires
            + '; path=/'
            + '; SameSite=' + sameSite + secure;
    }

    function getCookie(name) {
        var pairs = document.cookie ? document.cookie.split('; ') : [];
        for (var i = 0; i < pairs.length; i++) {
            var idx = pairs[i].indexOf('=');
            if (idx > 0 && pairs[i].slice(0, idx) === name) {
                try { return decodeURIComponent(pairs[i].slice(idx + 1)); }
                catch (e) { return null; }
            }
        }
        return null;
    }

    function getOrSetVisitorId() {
        var vid = getCookie(VID_COOKIE);
        if (!vid) {
            vid = uuidv4();
            setCookie(VID_COOKIE, vid, VID_TTL_DAYS);
        }
        return vid;
    }

    function captureUtmIfPresent() {
        var search = window.location.search;
        if (!search) return null;

        var params = new URLSearchParams(search);
        var keys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid'];
        var captured = {};
        var any = false;

        for (var i = 0; i < keys.length; i++) {
            var v = params.get(keys[i]);
            if (v) { captured[keys[i]] = v; any = true; }
        }

        if (!any) return null;

        // Persist for 30 days so a delayed conversion still attributes correctly.
        setCookie(UTM_COOKIE, JSON.stringify(captured), UTM_TTL_DAYS);
        return captured;
    }

    function getStoredUtm() {
        var raw = getCookie(UTM_COOKIE);
        if (!raw) return null;
        try { return JSON.parse(raw); } catch (e) { return null; }
    }

    function getCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : null;
    }

    function send(eventType, metadata) {
        var visitorId = getOrSetVisitorId();
        var utm = captureUtmIfPresent() || getStoredUtm() || {};

        var body = Object.assign({
            event_type: eventType,
            visitor_id: visitorId,
            metadata: metadata || {},
        }, utm);

        var payload = JSON.stringify(body);
        var headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Vaytoven-Surface': 'web',
        };
        var csrf = getCsrfToken();
        if (csrf) headers['X-CSRF-TOKEN'] = csrf;

        // Prefer sendBeacon when navigating away (it survives unload); fall back
        // to fetch for in-page events.
        if (navigator.sendBeacon && typeof Blob !== 'undefined') {
            try {
                var blob = new Blob([payload], { type: 'application/json' });
                if (navigator.sendBeacon(ENDPOINT, blob)) return;
            } catch (e) { /* fall through to fetch */ }
        }

        // Fire-and-forget. Failures swallowed by .catch — tracking is non-critical.
        if (typeof fetch === 'function') {
            fetch(ENDPOINT, {
                method: 'POST',
                headers: headers,
                body: payload,
                credentials: 'same-origin',
                keepalive: true,
            }).catch(function () {});
        }
    }

    // Public API.
    window.Vaytoven = window.Vaytoven || {};
    window.Vaytoven.track = function (eventType, metadata) {
        if (typeof eventType !== 'string' || !eventType) return;
        send(eventType, metadata);
    };
    window.Vaytoven.visitorId = function () { return getOrSetVisitorId(); };

    // Auto-fire a page_view on load.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            send('page_view', { path: window.location.pathname });
        });
    } else {
        send('page_view', { path: window.location.pathname });
    }
})(window, document);

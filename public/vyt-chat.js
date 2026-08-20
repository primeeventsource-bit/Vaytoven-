/**
 * Vaytoven Support Chat widget (FR-11.1).
 *
 * Drop into any page with:
 *   <script src="/vyt-chat.js" defer></script>
 *
 * Renders a floating launcher in the bottom-right corner. Click opens a
 * panel with a conversation + message input. Sessions persist via localStorage
 * so refreshes keep context. POSTs to /api/v1/support/chat (rate-limited
 * 30/IP/min). Failures surface a generic "temporarily unavailable" message.
 *
 * Hard rules respected:
 *   - No card data, no PII collected client-side. The server PII filter is a backstop.
 *   - The brand mark is the V-pin. Pink-to-purple gradient on the launcher.
 *   - Never use the T-word. (Widget never types it; messages come from server.)
 */
(function (window, document) {
    'use strict';

    var ENDPOINT = '/api/v1/support/chat';
    var SESSION_KEY = 'vyt_chat_session';
    var STATE_KEY = 'vyt_chat_open';

    function el(tag, attrs, children) {
        var node = document.createElement(tag);
        if (attrs) {
            for (var k in attrs) {
                if (k === 'style') {
                    Object.assign(node.style, attrs[k]);
                } else if (k.startsWith('on') && typeof attrs[k] === 'function') {
                    node.addEventListener(k.slice(2), attrs[k]);
                } else if (k === 'text') {
                    node.textContent = attrs[k];
                } else {
                    node.setAttribute(k, attrs[k]);
                }
            }
        }
        if (children) {
            children.forEach(function (c) { node.appendChild(c); });
        }
        return node;
    }

    function getSessionId() {
        try { return parseInt(localStorage.getItem(SESSION_KEY) || '', 10) || null; }
        catch (e) { return null; }
    }

    function setSessionId(id) {
        try { localStorage.setItem(SESSION_KEY, String(id)); } catch (e) { /* swallow */ }
    }

    function getCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : null;
    }

    function send(message, onReply, onError) {
        var headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Vaytoven-Surface': 'web',
        };
        var csrf = getCsrfToken();
        if (csrf) headers['X-CSRF-TOKEN'] = csrf;

        var body = { message: message };
        var sid = getSessionId();
        if (sid) body.session_id = sid;

        fetch(ENDPOINT, {
            method: 'POST',
            headers: headers,
            credentials: 'same-origin',
            body: JSON.stringify(body),
        }).then(function (r) {
            return r.json().then(function (data) { return { ok: r.ok, status: r.status, data: data }; });
        }).then(function (out) {
            if (out.data && out.data.session_id) setSessionId(out.data.session_id);
            if (out.ok && out.data && out.data.reply) {
                onReply(out.data.reply);
            } else if (out.data && out.data.reply) {
                // 503 graceful fallback still has a `reply` we can show.
                onReply(out.data.reply);
            } else {
                onError();
            }
        }).catch(onError);
    }

    var styles = ''
        + '.vyt-launch{position:fixed;right:20px;bottom:20px;width:56px;height:56px;border-radius:50%;'
        + 'background:linear-gradient(135deg,#FF3D8A 0%,#7B2CBF 100%);box-shadow:0 6px 24px rgba(123,44,191,.32);'
        + 'cursor:pointer;border:none;z-index:99998;display:flex;align-items:center;justify-content:center;'
        + 'color:white;font-family:system-ui,-apple-system,sans-serif;transition:transform .15s ease}'
        + '.vyt-launch:hover{transform:translateY(-2px)}'
        + '.vyt-launch svg{width:24px;height:24px}'
        + '.vyt-panel{position:fixed;right:20px;bottom:90px;width:360px;max-height:560px;background:#fff;'
        + 'border-radius:14px;box-shadow:0 12px 48px rgba(0,0,0,.18),0 2px 8px rgba(0,0,0,.06);'
        + 'display:none;flex-direction:column;overflow:hidden;z-index:99999;'
        + 'font-family:system-ui,-apple-system,sans-serif;color:#1d1f21}'
        + '.vyt-panel.open{display:flex}'
        + '.vyt-header{padding:14px 16px;background:linear-gradient(135deg,#FF3D8A 0%,#7B2CBF 100%);color:white;'
        + 'display:flex;justify-content:space-between;align-items:center}'
        + '.vyt-header h3{margin:0;font-size:14px;font-weight:600;letter-spacing:-.005em}'
        + '.vyt-header button{background:transparent;border:0;color:white;cursor:pointer;font-size:18px;padding:0 4px}'
        + '.vyt-msgs{flex:1;overflow-y:auto;padding:12px 14px;background:#fafaf9;font-size:13.5px;line-height:1.45}'
        + '.vyt-msg{margin:6px 0;padding:8px 12px;border-radius:10px;max-width:80%;word-wrap:break-word}'
        + '.vyt-msg.u{background:#1d1f21;color:white;margin-left:auto;border-bottom-right-radius:4px}'
        + '.vyt-msg.a{background:#fff;border:1px solid #e7e5e4;color:#1d1f21;border-bottom-left-radius:4px}'
        + '.vyt-msg.sys{background:#fef3c7;color:#92400e;font-size:12px;text-align:center;max-width:100%;border-radius:6px}'
        + '.vyt-input{display:flex;border-top:1px solid #e7e5e4;background:white}'
        + '.vyt-input input{flex:1;padding:12px 14px;border:0;font-size:13.5px;outline:none;background:transparent;color:#1d1f21}'
        + '.vyt-input button{padding:0 16px;background:linear-gradient(135deg,#FF3D8A 0%,#7B2CBF 100%);color:white;'
        + 'border:0;font-size:13px;font-weight:600;cursor:pointer}'
        + '.vyt-input button:disabled{opacity:.5;cursor:wait}';

    function injectStyles() {
        var s = document.createElement('style');
        s.textContent = styles;
        document.head.appendChild(s);
    }

    function build() {
        var msgs = el('div', { class: 'vyt-msgs' });
        // No booking language here. Vaytoven advertises listings and does not
        // take reservations, so inviting the question sets up an expectation
        // the assistant then has to walk back — and the visitor reads that as
        // the product being broken rather than as it never having existed.
        // The system prompt has said so since the booking product was removed;
        // this placeholder and the greeting below were the last two places
        // still offering it.
        var input = el('input', { type: 'text', placeholder: 'Ask about advertising, offers, or your account…', maxlength: 4000 });
        var sendBtn = el('button', { text: 'Send', type: 'button' });
        var inputRow = el('div', { class: 'vyt-input' }, [input, sendBtn]);

        function appendMsg(role, text) {
            var m = el('div', { class: 'vyt-msg ' + (role === 'user' ? 'u' : (role === 'sys' ? 'sys' : 'a')), text: text });
            msgs.appendChild(m);
            msgs.scrollTop = msgs.scrollHeight;
        }

        function submit() {
            var text = input.value.trim();
            if (!text) return;
            appendMsg('user', text);
            input.value = '';
            sendBtn.disabled = true;

            send(text, function (reply) {
                appendMsg('assistant', reply);
                sendBtn.disabled = false;
                input.focus();
            }, function () {
                appendMsg('sys', 'Connection trouble — please try again.');
                sendBtn.disabled = false;
            });
        }

        sendBtn.addEventListener('click', submit);
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); submit(); }
        });

        var closeBtn = el('button', { text: '×', type: 'button', 'aria-label': 'Close' });
        var header = el('div', { class: 'vyt-header' }, [
            el('h3', { text: 'Vaytoven Support' }),
            closeBtn,
        ]);
        var panel = el('div', { class: 'vyt-panel', role: 'dialog', 'aria-label': 'Vaytoven support chat' }, [
            header, msgs, inputRow,
        ]);

        var launcher = el('button', { class: 'vyt-launch', type: 'button', 'aria-label': 'Open support chat' });
        launcher.innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.05 2 11c0 2.69 1.34 5.1 3.45 6.71l-.96 3.04 3.32-1.84C9.04 19.61 10.49 20 12 20c5.52 0 10-4.05 10-9s-4.48-9-10-9z"/></svg>';

        function open() {
            panel.classList.add('open');
            try { localStorage.setItem(STATE_KEY, '1'); } catch (e) {}
            setTimeout(function () { input.focus(); }, 50);
            if (msgs.children.length === 0) {
                appendMsg('assistant', "Hi — I can help with property advertising, offers, and account questions. What's on your mind?");
            }
        }
        function close() {
            panel.classList.remove('open');
            try { localStorage.removeItem(STATE_KEY); } catch (e) {}
        }

        launcher.addEventListener('click', function () {
            panel.classList.contains('open') ? close() : open();
        });
        closeBtn.addEventListener('click', close);

        document.body.appendChild(panel);
        document.body.appendChild(launcher);

        // Restore open state on reload.
        try { if (localStorage.getItem(STATE_KEY) === '1') open(); } catch (e) {}
    }

    function boot() {
        injectStyles();
        build();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})(window, document);

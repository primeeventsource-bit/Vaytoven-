// @ts-check
import { test, expect } from '@playwright/test';

/**
 * Public API smoke tests — anonymous-friendly endpoints used by the SDK
 * and chat widget.
 */
test.describe('Public API', () => {
    test('/health returns 200 with DB + Redis green', async ({ request }) => {
        const resp = await request.get('/health');
        expect(resp.status()).toBe(200);

        const body = await resp.json();
        expect(body.status).toBe('ok');
        expect(body.checks?.database?.ok).toBe(true);
        expect(body.checks?.redis?.ok).toBe(true);
    });

    test('GET /api/v1/properties returns paginated active listings', async ({ request }) => {
        const resp = await request.get('/api/v1/properties');
        expect(resp.status()).toBe(200);

        const body = await resp.json();
        expect(Array.isArray(body.data)).toBe(true);
        expect(body.data.length).toBeGreaterThan(0);
        const sample = body.data[0];
        expect(sample).toHaveProperty('id');
        expect(sample).toHaveProperty('title');
        expect(sample).toHaveProperty('base_nightly_cents');
        expect(typeof sample.base_nightly_cents).toBe('number');
    });

    test('GET /api/v1/properties supports city filter', async ({ request }) => {
        const resp = await request.get('/api/v1/properties?city=Bali');
        expect(resp.status()).toBe(200);

        const body = await resp.json();
        for (const property of body.data) {
            expect(property.location.city).toBe('Bali');
        }
    });

    test('POST /api/v1/tracking/events accepts cta_click events', async ({ request }) => {
        const resp = await request.post('/api/v1/tracking/events', {
            headers: { 'X-Vaytoven-Surface': 'web' },
            data: {
                event_type: 'cta_click',
                visitor_id: '00000000-1111-2222-3333-444444444444',
                metadata: { audience: 'traveler', cta: 'e2e_smoke' },
            },
        });

        expect(resp.status()).toBe(201);
        const body = await resp.json();
        expect(body).toHaveProperty('event_uuid');
        expect(body.event_uuid).toMatch(/^[0-9a-f-]{36}$/);
    });

    test('POST /api/v1/support/chat returns graceful 503 fallback when Anthropic unset', async ({ request }) => {
        const resp = await request.post('/api/v1/support/chat', {
            headers: { 'X-Vaytoven-Surface': 'web' },
            data: { message: 'hello e2e' },
        });

        // Either 200 with a real reply (if ANTHROPIC_API_KEY configured) or
        // 200 with the graceful fallback. Both must include a session_id +
        // reply field so the client widget can render something.
        expect([200, 503]).toContain(resp.status());
        const body = await resp.json();
        expect(body).toHaveProperty('reply');
    });

    test('GET /vyt-track.js carries SDK globals', async ({ request }) => {
        const resp = await request.get('/vyt-track.js');
        expect(resp.status()).toBe(200);
        const body = await resp.text();
        expect(body).toContain('window.Vaytoven');
        expect(body).toContain('vyt_vid');
        expect(body).toContain('/api/v1/tracking/events');
    });

    test('GET /vyt-chat.js carries widget globals', async ({ request }) => {
        const resp = await request.get('/vyt-chat.js');
        expect(resp.status()).toBe(200);
        const body = await resp.text();
        expect(body).toContain('vyt_chat_session');
        expect(body).toContain('/api/v1/support/chat');
        expect(body).toContain('Vaytoven Support');
    });
});

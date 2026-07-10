// @ts-check
import { test, expect } from '@playwright/test';

/**
 * Legal docs + /legal/versions JSON endpoint.
 */
test.describe('Legal docs', () => {
    const docs = [
        { path: '/legal/tos',              titleRe: /Terms of Service/i },
        { path: '/legal/privacy',          titleRe: /Privacy Policy/i },
        { path: '/legal/member-agreement', titleRe: /Member Agreement/i },
    ];

    for (const doc of docs) {
        test(`${doc.path} renders with DRAFT banner`, async ({ page }) => {
            await page.goto(doc.path);
            await expect(page.getByRole('heading', { name: doc.titleRe })).toBeVisible();
            // Until counsel review the DRAFT banner must remain.
            await expect(page.getByText(/DRAFT/, { exact: false }).first()).toBeVisible();
        });
    }

    test('GET /legal/versions returns 3 entries with SHA-256 hashes', async ({ request }) => {
        const resp = await request.get('/legal/versions');
        expect(resp.status()).toBe(200);

        const body = await resp.json();
        expect(Array.isArray(body.versions)).toBe(true);
        expect(body.versions.length).toBe(3);

        const kinds = body.versions.map((v) => v.kind).sort();
        expect(kinds).toEqual(['member_agreement', 'privacy', 'tos']);

        for (const v of body.versions) {
            expect(v.content_hash).toMatch(/^[0-9a-f]{64}$/);
            expect(v.content_url).toContain(v.kind === 'member_agreement' ? '/legal/member-agreement' : `/legal/${v.kind}`);
        }
    });
});

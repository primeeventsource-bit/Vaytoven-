// @ts-check
import { test, expect } from '@playwright/test';

/**
 * Help center (/help).
 */
test.describe('Help center', () => {
    test('index renders categories with seeded articles', async ({ page }) => {
        await page.goto('/help');

        await expect(page.getByRole('heading', { name: /How can we help/i })).toBeVisible();

        // Audience filter chips — scope to the .help-audiences container so
        // we don't collide with the top-nav links of the same names.
        for (const label of ['All', 'Travelers', 'Hosts', 'Members']) {
            await expect(
                page.locator('.help-audiences').getByRole('link', { name: new RegExp(`^${label}$`, 'i') })
            ).toBeVisible();
        }

        // At least one curated article from each audience scope.
        await expect(page.getByText('Flexible cancellation policy')).toBeVisible();
        await expect(page.getByText('Becoming a Vaytoven host')).toBeVisible();
        await expect(page.getByText('How the managed program works')).toBeVisible();
    });

    test('audience filter narrows to that persona + the all bucket', async ({ page }) => {
        await page.goto('/help?audience=member');

        await expect(page.getByText('How the managed program works')).toBeVisible();
        // Host-only article must not leak into a member view.
        await expect(page.getByText('Becoming a Vaytoven host')).toHaveCount(0);
    });

    test('article show page renders body + back link', async ({ page }) => {
        await page.goto('/help/cancellation-flexible');

        await expect(page.getByRole('heading', { name: /Flexible cancellation policy/i })).toBeVisible();
        // The phrase "24 hours" appears multiple times in body — first() it.
        await expect(page.getByText(/24 hours/i).first()).toBeVisible();
        // Back link starts with an arrow glyph that confuses accessible-name
        // matching; address by its CSS class.
        await expect(page.locator('a.help-article-back')).toBeVisible();
    });

    test('JSON search endpoint returns ranked results', async ({ request }) => {
        const resp = await request.get('/help/search?q=cancel');
        expect(resp.status()).toBe(200);

        const body = await resp.json();
        expect(body.query).toBe('cancel');
        expect(Array.isArray(body.results)).toBe(true);
        expect(body.results.length).toBeGreaterThan(0);

        const slugs = body.results.map((r) => r.slug);
        expect(slugs).toContain('cancellation-flexible');
    });

    test('live search input on index renders matching results', async ({ page }) => {
        await page.goto('/help');

        const input = page.locator('#help-search-input');
        await input.fill('refund');

        // Debounced; wait for the dropdown.
        await expect(page.locator('#help-search-results .help-result').first()).toBeVisible({ timeout: 4000 });
    });

    test('unknown article slug returns 404', async ({ page }) => {
        const resp = await page.goto('/help/this-article-does-not-exist');
        expect(resp?.status()).toBe(404);
    });
});

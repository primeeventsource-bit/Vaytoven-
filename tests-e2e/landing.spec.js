// @ts-check
import { test, expect } from '@playwright/test';

/**
 * Landing page (/) — three-audience marketing surface.
 */
test.describe('Landing page', () => {
    test('renders hero, search, all three audience sections', async ({ page }) => {
        await page.goto('/');
        await expect(page).toHaveTitle(/Vaytoven/i);

        // Hero
        await expect(page.getByRole('heading', { name: /Find your place/i })).toBeVisible();

        // Vrbo-style search bar posts to /properties.
        await expect(page.locator('form.vyt-search')).toHaveAttribute(
            'action',
            /\/properties$/,
        );

        // Three audience sections by id (the section anchors). #host and
        // #members each appear once on the page even though the new top
        // nav also links there — the section ids only attach to the actual
        // <section>s, the nav uses route URLs.
        await expect(page.locator('#destinations')).toBeVisible();
        await expect(page.locator('#host')).toBeVisible();
        await expect(page.locator('#members')).toBeVisible();

        // Branded gradient = pink → purple
        const heroStyle = await page.evaluate(() => {
            const root = getComputedStyle(document.documentElement);
            return root.getPropertyValue('--gradient').trim();
        });
        expect(heroStyle).toContain('#FF3D8A');
        expect(heroStyle).toContain('#7B2CBF');
    });

    test('every audience CTA carries data-track attributes', async ({ page }) => {
        await page.goto('/');

        // Each audience must have at least one tagged element.
        for (const audience of ['traveler', 'host', 'member']) {
            const count = await page.locator(`[data-track-audience="${audience}"]`).count();
            expect(count, `audience=${audience} should have ≥1 CTA`).toBeGreaterThan(0);
        }

        // Primary CTA per audience. Search button is now part of the
        // Vrbo-style bar (.vyt-search-submit); host + members CTAs unchanged.
        await expect(page.locator('button.vyt-search-submit[data-track-cta="search_submit"]')).toBeVisible();
        await expect(page.locator('a.host-primary-cta[data-track-cta="host_onboarding_open"]')).toBeVisible();
        await expect(page.locator('button.members-cta[data-track-cta="enquiry_open"]')).toBeVisible();
    });

    test('destination cards link to filtered /properties', async ({ page }) => {
        await page.goto('/');

        const baliCard = page.locator('a.dest-card[data-track-meta-destination="bali"]');
        await expect(baliCard).toHaveAttribute('href', /\/properties\?destination=bali/);
    });

    test('member enquiry modal opens and shows form fields', async ({ page }) => {
        await page.goto('/');
        await page.locator('#members').scrollIntoViewIfNeeded();
        await page.locator('button.members-cta').click();

        const modal = page.locator('#members-modal');
        await expect(modal).toHaveClass(/is-open/);
        await expect(page.locator('#m-first')).toBeVisible();
        await expect(page.locator('#m-last')).toBeVisible();
        await expect(page.locator('#members-form input[name="email"]')).toBeVisible();
        // Club is a <select>, not <input>.
        await expect(page.locator('#members-form select[name="club"]')).toBeVisible();
    });

    test('chat widget script and tracking SDK both load', async ({ page }) => {
        const scriptSources = [];
        page.on('response', (resp) => {
            const url = resp.url();
            if (url.endsWith('/vyt-track.js') || url.endsWith('/vyt-chat.js')) {
                scriptSources.push({ url, status: resp.status() });
            }
        });

        await page.goto('/');
        await page.waitForTimeout(800); // allow defer'd scripts to fetch

        const trackHit = scriptSources.find((s) => s.url.endsWith('/vyt-track.js'));
        const chatHit = scriptSources.find((s) => s.url.endsWith('/vyt-chat.js'));
        expect(trackHit?.status).toBe(200);
        expect(chatHit?.status).toBe(200);
    });

    test('vyt_vid cookie set after page load', async ({ page, context }) => {
        await page.goto('/');
        // SDK runs on DOMContentLoaded; give it a moment.
        await page.waitForTimeout(1000);

        const cookies = await context.cookies();
        const vid = cookies.find((c) => c.name === 'vyt_vid');
        expect(vid?.value).toMatch(/^[0-9a-f-]{36}$/);
    });

    test('footer legal links resolve to /legal/* pages', async ({ page }) => {
        await page.goto('/');
        // route() helper renders absolute URLs; match by suffix instead of exact.
        const privacyLink = page.locator('a[href$="/legal/privacy"]').first();
        await privacyLink.scrollIntoViewIfNeeded();
        await privacyLink.click();

        await expect(page).toHaveURL(/\/legal\/privacy$/);
        await expect(page.getByRole('heading', { name: /Privacy Policy/i })).toBeVisible();
    });
});

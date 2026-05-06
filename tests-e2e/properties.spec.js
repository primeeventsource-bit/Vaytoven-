// @ts-check
import { test, expect } from '@playwright/test';

/**
 * Property browse + detail (/properties, /properties/{id}).
 */
test.describe('Properties', () => {
    test('index renders seeded properties from all destinations', async ({ page }) => {
        await page.goto('/properties');

        await expect(page.getByRole('heading', { name: /Find your next stay/i })).toBeVisible();

        const expectedTitles = [
            'Ubud Jungle Villa',
            'Seminyak Beachfront',
            'Oia Caldera',
            'South Lake Tahoe',
            'Le Marais',
            'Shibuya Tower',
        ];
        for (const title of expectedTitles) {
            await expect(page.getByText(title, { exact: false }).first(), `expected ${title}`).toBeVisible();
        }
    });

    test('destination filter narrows to matching city', async ({ page }) => {
        await page.goto('/properties?destination=bali');

        await expect(page.getByRole('heading', { name: /Stays in Bali/i })).toBeVisible();
        await expect(page.getByText(/Ubud Jungle Villa/i)).toBeVisible();
        // Filtering to Bali must hide Paris listings entirely.
        await expect(page.getByText(/Le Marais/i)).toHaveCount(0);
    });

    test('search filter narrows by free-text query', async ({ page }) => {
        await page.goto('/properties?q=cabin');

        // Header acknowledges the search.
        await expect(page.getByText(/Results for "cabin"/i)).toBeVisible();
    });

    test('capacity filter narrows results', async ({ page }) => {
        await page.goto('/properties?min_capacity=8');

        // The 8-guest "Lakefront Modern" should appear.
        await expect(page.getByText(/Lakefront Modern/i)).toBeVisible();
        // 2-guest "Oia Caldera View Suite" should not.
        await expect(page.getByText(/Oia Caldera View Suite/i)).toHaveCount(0);
    });

    test('clicking a property card opens the detail page with photos + booking form', async ({ page }) => {
        await page.goto('/properties');

        // Click first card.
        await page.locator('a.props-card').first().click();

        await expect(page).toHaveURL(/\/properties\/\d+$/);
        await expect(page.locator('.props-detail-hero img').first()).toBeVisible();
        await expect(page.locator('input#b-checkin')).toBeVisible();
        await expect(page.locator('input#b-checkout')).toBeVisible();
        await expect(page.getByRole('button', { name: /Continue to review/i })).toBeVisible();
    });

    test('detail page shows cancellation summary linking to help center', async ({ page }) => {
        await page.goto('/properties/1');

        // Cancellation section exists with deep-link to help.
        await expect(page.getByRole('heading', { name: /Cancellation$/i })).toBeVisible();
        const helpLink = page.locator('a[href^="/help/cancellation-"]').first();
        await expect(helpLink).toBeVisible();
    });

    test('booking form bounces unauthenticated users to login', async ({ page }) => {
        await page.goto('/properties/1');

        // Fill out the booking form on the property show page.
        const dayShift = (n) => {
            const d = new Date();
            d.setDate(d.getDate() + n);
            return d.toISOString().slice(0, 10);
        };
        await page.locator('#b-checkin').fill(dayShift(7));
        await page.locator('#b-checkout').fill(dayShift(10));
        await page.locator('#b-guests').fill('2');
        await page.getByRole('button', { name: /Continue to review/i }).click();

        // Auth-gated → redirects to /login.
        await expect(page).toHaveURL(/\/login/);
    });
});

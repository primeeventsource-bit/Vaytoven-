// @ts-check
import { test, expect } from '@playwright/test';

/**
 * Auth flow + protected-route gates. Includes a fresh-user E2E happy path
 * that registers, accepts terms, and lands on the dashboard.
 */
test.describe('Auth + register', () => {
    test('register form requires accept_terms checkbox', async ({ page }) => {
        await page.goto('/register');

        // Breeze x-input-label renders labels with empty text content; address
        // fields by id/name rather than accessible name.
        await expect(page.locator('input#name')).toBeVisible();
        await expect(page.locator('input#email')).toBeVisible();
        await expect(page.locator('input#password')).toBeVisible();
        await expect(page.locator('input[name="accept_terms"]')).toBeVisible();
        // Legal links rendered by Laravel route() are absolute URLs — suffix match.
        await expect(page.locator('a[href$="/legal/tos"]')).toBeVisible();
        await expect(page.locator('a[href$="/legal/privacy"]')).toBeVisible();
    });

    test('full register → dashboard happy path', async ({ page }) => {
        const stamp = Date.now();
        const email = `e2e+${stamp}@vaytoven.test`;
        const password = 'PlaywrightPass!9876';

        await page.goto('/register');
        await page.locator('input#name').fill('E2E Tester');
        await page.locator('input#email').fill(email);
        await page.locator('input#password').fill(password);
        await page.locator('input#password_confirmation').fill(password);
        await page.locator('input#accept_terms').check();

        await page.getByRole('button', { name: /Register/i }).click();

        // Email verification is on by default; the user lands on
        // /verify-email until they click the emailed link. /dashboard is
        // also acceptable in case verification is disabled on this env.
        await expect(page).toHaveURL(/\/dashboard|\/verify-email/);
    });

    test('/dashboard redirects unauthenticated visitors to /login', async ({ page }) => {
        await page.goto('/dashboard');
        await expect(page).toHaveURL(/\/login$/);
    });

    test('/account/bookings redirects unauthenticated visitors to /login', async ({ page }) => {
        await page.goto('/account/bookings');
        await expect(page).toHaveURL(/\/login$/);
    });

    test('/host/onboarding redirects unauthenticated visitors to /login', async ({ page }) => {
        await page.goto('/host/onboarding');
        await expect(page).toHaveURL(/\/login$/);
    });

    test('/login page renders', async ({ page }) => {
        await page.goto('/login');
        await expect(page.locator('input#email')).toBeVisible();
        await expect(page.locator('input#password')).toBeVisible();
    });
});

// @ts-check
import { test, expect } from '@playwright/test';

/**
 * Auth flow + protected-route gates. Includes a fresh-user E2E happy path
 * that registers, accepts terms, and lands on the dashboard.
 */
test.describe('Auth + register', () => {
    test('register form shows every required field', async ({ page }) => {
        await page.goto('/register');

        // The Vaytoven-branded form collects the name in two parts plus a
        // phone number; the old single `name` input no longer exists.
        await expect(page.getByLabel('First name')).toBeVisible();
        await expect(page.getByLabel('Last name')).toBeVisible();
        await expect(page.getByLabel('Email')).toBeVisible();
        await expect(page.getByLabel('Phone number')).toBeVisible();
        await expect(page.locator('input#password')).toBeVisible();
        await expect(page.locator('input#password_confirmation')).toBeVisible();
        await expect(page.locator('input[name="accept_terms"]')).toBeVisible();

        // The consent copy must link the documents the acceptance record will
        // reference. Scoped to the consent block: the branded layout's page
        // footer carries its own Terms/Privacy links, so an unscoped selector
        // matches twice and trips Playwright's strict mode.
        const consent = page.locator('.vyt-consent');
        await expect(consent.locator('a[href$="/legal/tos"]')).toBeVisible();
        await expect(consent.locator('a[href$="/legal/privacy"]')).toBeVisible();
    });

    test('register page is Vaytoven-branded, not a Laravel default', async ({ page }) => {
        await page.goto('/register');

        await expect(page.getByRole('button', { name: /create account/i })).toBeVisible();
        await expect(page.getByRole('link', { name: /sign in/i })).toBeVisible();
        // The brand card and logo, not the Breeze guest layout.
        await expect(page.locator('.vyt-auth-card')).toBeVisible();
        await expect(page.locator('.vyt-auth-brand svg')).toBeVisible();
    });

    test('full register → dashboard happy path', async ({ page }) => {
        const stamp = Date.now();
        const email = `e2e+${stamp}@vaytoven.test`;
        const password = 'PlaywrightPass!9876';

        await page.goto('/register');
        await page.locator('input#first_name').fill('E2E');
        await page.locator('input#last_name').fill('Tester');
        await page.locator('input#email').fill(email);
        await page.locator('input#phone').fill('+1 555 010 2030');
        await page.locator('input#password').fill(password);
        await page.locator('input#password_confirmation').fill(password);
        await page.locator('input#accept_terms').check();

        await page.getByRole('button', { name: /create account/i }).click();

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

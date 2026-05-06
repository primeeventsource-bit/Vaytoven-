// @ts-check
import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright config for Vaytoven E2E.
 *
 * Targets the deployed v-app-dev environment by default. Override with
 *   E2E_BASE_URL=https://my-other-host npx playwright test
 * to run against a different deployment.
 *
 * Single chromium project keeps install size + run time small. Add firefox
 * or webkit projects when cross-browser bugs surface.
 */
export default defineConfig({
    testDir: './tests-e2e',
    timeout: 30_000,
    expect: { timeout: 5_000 },
    fullyParallel: false, // some tests register users; sequential keeps DB state predictable
    retries: 1,
    workers: 1,
    reporter: [['list']],

    use: {
        baseURL: process.env.E2E_BASE_URL ?? 'https://v-app-dev-main-oyo1n9.laravel.cloud',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        ignoreHTTPSErrors: false,
        actionTimeout: 10_000,
        navigationTimeout: 15_000,
    },

    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});

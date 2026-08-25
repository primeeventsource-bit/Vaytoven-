// @ts-check
import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright config for Vaytoven E2E.
 *
 * These tests REGISTER REAL USERS. Whatever host they point at ends up with
 * an account per run, permanently, in that host's database.
 *
 * So the default target is local. It used to be the deployed environment,
 * which meant `npx playwright test` with no arguments wrote users straight
 * into production — and a copy of this repo kept doing it to Vaytoven long
 * after it had become a different company's codebase. Pointing somewhere
 * else is now something you have to say out loud:
 *
 *   E2E_BASE_URL=https://staging.example.com E2E_ALLOW_REMOTE=1 npx playwright test
 *
 * Both variables are required together. E2E_BASE_URL alone fails with an
 * explanation rather than quietly filling somebody's user table.
 *
 * Single chromium project keeps install size + run time small. Add firefox
 * or webkit projects when cross-browser bugs surface.
 */

const DEFAULT_BASE_URL = 'http://127.0.0.1:8000';

const baseURL = process.env.E2E_BASE_URL ?? DEFAULT_BASE_URL;

/** Local by any of the names it goes by, including a bare port on the loopback. */
const isLocal = /^https?:\/\/(localhost|127\.0\.0\.1|0\.0\.0\.0|\[::1\])(:\d+)?(\/|$)/i.test(baseURL);

if (!isLocal && process.env.E2E_ALLOW_REMOTE !== '1') {
    throw new Error(
        `Refusing to run E2E against ${baseURL}.\n\n` +
        'These tests register real accounts, and the host you named is not local. ' +
        'If that is genuinely what you want, say so explicitly:\n\n' +
        `  E2E_BASE_URL=${baseURL} E2E_ALLOW_REMOTE=1 npx playwright test\n\n` +
        'Never point this at a production site. The accounts it creates cannot be ' +
        'undone from here, and every one of them triggers the mail a real signup would.'
    );
}

export default defineConfig({
    testDir: './tests-e2e',
    timeout: 30_000,
    expect: { timeout: 5_000 },
    fullyParallel: false, // some tests register users; sequential keeps DB state predictable
    retries: 1,
    workers: 1,
    reporter: [['list']],

    use: {
        baseURL,
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

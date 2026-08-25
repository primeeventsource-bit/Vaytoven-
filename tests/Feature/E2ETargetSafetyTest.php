<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The end-to-end suite must not be aimed at a live site by default.
 *
 * Those tests register real accounts. Whatever host they point at ends up with
 * one per run, permanently, and each signup fires the mail a real signup would.
 *
 * This is not hypothetical. The config used to default to the deployed
 * environment, so `npx playwright test` with no arguments wrote users straight
 * into production — and when the codebase was copied to start another site, the
 * copy kept the inherited base URL and went on registering its own test
 * accounts here for weeks. The first anybody noticed was the office inbox
 * filling with first-sign-in notices for a domain nobody recognised.
 *
 * A JS config file is not something the PHP suite would normally read. It is
 * read here because the failure it guards against is silent, expensive, and
 * lands in production rather than in a test report.
 */
class E2ETargetSafetyTest extends TestCase
{
    private function config(): string
    {
        $path = base_path('playwright.config.js');

        $this->assertFileExists($path, 'the E2E config should exist');

        return (string) file_get_contents($path);
    }

    /** The fallback target — what runs when nobody sets E2E_BASE_URL — must be local. */
    public function test_the_default_target_is_local(): void
    {
        $config = $this->config();

        $this->assertMatchesRegularExpression(
            '/DEFAULT_BASE_URL\s*=\s*[\'"]https?:\/\/(localhost|127\.0\.0\.1)/',
            $config,
            'the default E2E target must be a local host',
        );
    }

    /**
     * The specific address this has already gone wrong with, plus the live
     * domain. Neither may appear as a default anywhere in the file.
     */
    public function test_no_deployed_host_is_baked_in_as_a_default(): void
    {
        $config = $this->config();

        foreach (['v-app-dev-main-oyo1n9.laravel.cloud', 'vaytoven.com'] as $host) {
            $this->assertStringNotContainsString(
                '?? \''.'https://'.$host,
                $config,
                $host.' must not be the fallback E2E target',
            );
        }
    }

    /** Pointing somewhere remote has to be said out loud, not just implied. */
    public function test_a_remote_target_requires_an_explicit_opt_in(): void
    {
        $config = $this->config();

        $this->assertStringContainsString('E2E_ALLOW_REMOTE', $config);
        $this->assertStringContainsString('throw new Error', $config);
    }
}

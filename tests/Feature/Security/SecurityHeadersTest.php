<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_headers_are_present_on_a_public_page(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('X-Permitted-Cross-Domain-Policies', 'none');
        $this->assertStringContainsString('camera=()', $response->headers->get('Permissions-Policy'));
    }

    /**
     * A 302 to the login screen is a response the browser acts on, so it needs
     * the headers too. Appending the middleware instead of prepending would
     * miss every short-circuited response.
     */
    public function test_the_headers_survive_a_redirect_from_an_earlier_middleware(): void
    {
        $this->get(route('profile.edit'))
            ->assertRedirect(route('login'))
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    // --- HSTS ----------------------------------------------------------------

    public function test_hsts_is_sent_over_https(): void
    {
        $header = $this->get('https://vaytoven.com/')->headers->get('Strict-Transport-Security');

        $this->assertStringContainsString('max-age=15768000', $header);
        $this->assertStringContainsString('includeSubDomains', $header);
    }

    /** Preload is effectively irreversible, so it is deliberately absent. */
    public function test_hsts_does_not_claim_preload(): void
    {
        $this->assertStringNotContainsString(
            'preload',
            (string) $this->get('https://vaytoven.com/')->headers->get('Strict-Transport-Security')
        );
    }

    /** Sending HSTS over plain HTTP would poison local development. */
    public function test_hsts_is_not_sent_over_plain_http(): void
    {
        $this->get('http://localhost/')->assertHeaderMissing('Strict-Transport-Security');
    }

    // --- CSP -----------------------------------------------------------------

    /**
     * Report-only, deliberately. An enforced policy assembled from a grep
     * would eventually break the payment page, which is the one page where
     * breakage costs money directly.
     */
    /**
     * Enforcing, not report-only.
     *
     * It ran report-only while the policy was being shaped, which is the right
     * order — a policy that blocks something the site needs takes the site
     * down. Every public page was then loaded and checked for
     * securitypolicyviolation events and none reported one, so the policy
     * already describes what the site loads. Report-only would document an
     * intention and stop nothing: an injected script gets reported and still
     * runs.
     */
    public function test_the_policy_is_enforced(): void
    {
        $response = $this->get('/');

        $this->assertNotNull(
            $response->headers->get('Content-Security-Policy'),
            'the policy must be enforced, not merely reported',
        );
        $response->assertHeaderMissing('Content-Security-Policy-Report-Only');
    }

    /** Violations still reach us once enforcing, so breakage is visible. */
    public function test_the_policy_still_reports_violations(): void
    {
        $policy = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('report-uri', $policy);
    }

    public function test_the_policy_allows_the_hosts_the_site_actually_uses(): void
    {
        $policy = $this->get('/')->headers->get('Content-Security-Policy');

        // Collect.js and the iframes it injects for the card fields. Without
        // these the payment page cannot take a card at all.
        $this->assertStringContainsString('https://secure.nmi.com', $policy);
        $this->assertStringContainsString('frame-src https://secure.nmi.com', $policy);

        $this->assertStringContainsString('https://unpkg.com', $policy);      // Leaflet
        $this->assertStringContainsString('https://api.mapbox.com', $policy); // tiles
        $this->assertStringContainsString('https://fonts.bunny.net', $policy);
    }

    public function test_the_policy_locks_down_framing_and_form_targets(): void
    {
        $policy = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("frame-ancestors 'none'", $policy);
        $this->assertStringContainsString("form-action 'self'", $policy);
        $this->assertStringContainsString("base-uri 'self'", $policy);
        $this->assertStringContainsString("object-src 'none'", $policy);
    }

    /**
     * The edge already sends these. Sending them twice produces duplicate
     * headers and hides which one is in effect.
     */
    public function test_it_does_not_duplicate_headers_the_edge_already_sets(): void
    {
        $response = $this->get('/');

        $response->assertHeaderMissing('X-Frame-Options');
        $response->assertHeaderMissing('X-Content-Type-Options');
    }

    // --- the report endpoint --------------------------------------------------

    public function test_a_violation_report_is_logged(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn ($message, $context) => $message === 'csp: policy violation reported.'
                && $context['blocked-uri'] === 'https://evil.example/x.js');

        $this->postJson(route('security.csp-report'), [
            'csp-report' => [
                'document-uri'       => 'https://vaytoven.com/pricing',
                'violated-directive' => 'script-src',
                'blocked-uri'        => 'https://evil.example/x.js',
            ],
        ])->assertNoContent();
    }

    /** The browser posts this, so the body is hostile input. */
    public function test_an_unrecognised_body_is_ignored_rather_than_logged(): void
    {
        Log::shouldReceive('warning')->never();

        $this->postJson(route('security.csp-report'), ['something' => 'else'])
            ->assertNoContent();
    }

    /** An open endpoint that logs unbounded attacker text is a flooding tool. */
    public function test_a_long_field_is_truncated(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn ($message, $context) => strlen($context['blocked-uri']) === 500);

        $this->postJson(route('security.csp-report'), [
            'csp-report' => ['blocked-uri' => str_repeat('a', 5000)],
        ])->assertNoContent();
    }

    public function test_a_nested_value_is_not_logged_as_a_structure(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn ($message, $context) => $context['blocked-uri'] === null);

        $this->postJson(route('security.csp-report'), [
            'csp-report' => ['blocked-uri' => ['nested' => 'payload']],
        ])->assertNoContent();
    }
}

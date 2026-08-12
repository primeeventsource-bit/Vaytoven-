<?php

namespace Tests\Feature\Legal;

use App\Services\Legal\LegalDocumentRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TermsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_terms_tab_appears_in_the_primary_nav(): void
    {
        $html = $this->get('/properties')->assertOk()->getContent();

        $this->assertStringContainsString('topnav_terms', $html);
        $this->assertStringContainsString('/terms', $html);
    }

    /**
     * /terms is an alias, not a second page. Keeping one canonical URL matters
     * because it is the address recorded on every terms_acceptance row.
     */
    public function test_the_friendly_aliases_redirect_to_the_canonical_document(): void
    {
        $this->get('/terms')->assertRedirect('/legal/tos');
        $this->get('/terms-and-conditions')->assertRedirect('/legal/tos');
        $this->get('/privacy')->assertRedirect('/legal/privacy');
    }

    public function test_the_terms_page_renders_the_full_agreement(): void
    {
        $html = $this->get('/legal/tos')->assertOk()->getContent();

        // Spot-check across the document rather than only the top, so a
        // truncated render fails.
        foreach ([
            'Terms and Conditions',
            'Important relationship disclosures',
            'Overview of services',
            'Limitation of liability',
            'Governing law and dispute resolution',
            'Advertiser Disclosure Statement',
            '(877) 782-9868',
        ] as $needle) {
            $this->assertStringContainsString($needle, $html, "Terms page is missing: {$needle}");
        }
    }

    /**
     * The company describes itself as a platform, not a broker or agency, and
     * the terms must keep saying so — this is a compliance statement, not copy.
     */
    public function test_the_terms_state_what_vaytoven_is_not(): void
    {
        $text = preg_replace('/\s+/', ' ', strip_tags($this->get('/legal/tos')->getContent()));

        $this->assertStringContainsString(
            'not acting as a real estate broker, travel agency, escrow provider, or property management company',
            $text,
        );
    }

    /**
     * The label is asserted against the registry rather than a literal.
     *
     * A hardcoded 'v2' here only ever fails when someone legitimately revises a
     * document, which trains you to bump the literal and move on. What actually
     * needs guarding is that the registry, the row it materialises, and the
     * label rendered on the page all agree — a mismatch there means users see a
     * version string that is not the one their acceptance is recorded against.
     */
    public function test_the_terms_are_registered_as_the_current_version(): void
    {
        $registry = app(LegalDocumentRegistry::class);
        $registry->materialiseAll();

        $declared = collect($registry->documents())
            ->firstWhere('kind', LegalDocumentRegistry::KIND_TOS)['version_label'];

        $current = $registry->currentVersions();

        $this->assertArrayHasKey(LegalDocumentRegistry::KIND_TOS, $current);
        $this->assertSame($declared, $current[LegalDocumentRegistry::KIND_TOS]->version_label);

        $this->get('/legal/tos')->assertOk()->assertSee($declared);
    }

    /**
     * Adding the nav tab changed page chrome, not the agreement. The hash must
     * be unmoved by that — the whole reason canonicalText() extracts only the
     * document region.
     */
    public function test_nav_chrome_is_excluded_from_the_terms_hash(): void
    {
        $registry = app(LegalDocumentRegistry::class);
        $method = new \ReflectionMethod($registry, 'canonicalText');
        $canonical = $method->invoke($registry, view('legal.tos')->render());

        $this->assertStringNotContainsString('topnav_terms', $canonical);
        $this->assertStringNotContainsString('vyt-topnav', $canonical);
        $this->assertStringContainsString('Advertiser Disclosure Statement', $canonical);
    }
}

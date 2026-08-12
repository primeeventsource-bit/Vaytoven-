<?php

namespace Tests\Feature\Legal;

use App\Models\TermsVersion;
use App\Services\Legal\LegalDocumentRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * A terms version hash must identify the AGREEMENT, nothing else.
 *
 * Before these tests the registry hashed the entire rendered page, which
 * carries a per-session CSRF token and absolute route() URLs built from
 * APP_URL. The practical consequence: the same unchanged document hashed
 * differently on every environment, and pointing the app at a real domain
 * would have forced every existing user to re-accept terms whose text had not
 * changed by a single word.
 */
class LegalHashStabilityTest extends TestCase
{
    use RefreshDatabase;

    private function tosHash(): string
    {
        app(LegalDocumentRegistry::class)->materialiseAll();

        return TermsVersion::query()
            ->where('kind', LegalDocumentRegistry::KIND_TOS)
            ->orderByDesc('id')
            ->value('content_hash');
    }

    public function test_the_hash_does_not_change_when_the_app_url_changes(): void
    {
        $before = $this->tosHash();

        // Simulates attaching a real domain.
        config(['app.url' => 'https://www.vaytoven.example']);
        URL::forceRootUrl('https://www.vaytoven.example');

        $this->assertSame($before, $this->tosHash());
    }

    /**
     * EVERY legal document, not just the ToS.
     *
     * The Member Agreement regressed this exact way: section 4.3 linked the
     * Terms with route(), whose absolute URL is built from APP_URL, so the
     * agreement hashed differently on each environment and a domain change
     * would have forced every member to re-accept an unchanged contract.
     */
    public function test_no_legal_document_hash_depends_on_the_app_url(): void
    {
        $registry = app(LegalDocumentRegistry::class);
        $method = new \ReflectionMethod($registry, 'canonicalText');

        $before = [];
        foreach ($registry->documents() as $doc) {
            $before[$doc['kind']] = hash('sha256', $method->invoke($registry, view($doc['view'])->render()));
        }

        config(['app.url' => 'https://www.vaytoven.example']);
        URL::forceRootUrl('https://www.vaytoven.example');

        foreach ($registry->documents() as $doc) {
            $after = hash('sha256', $method->invoke($registry, view($doc['view'])->render()));

            $this->assertSame($before[$doc['kind']], $after,
                "The {$doc['kind']} document's hash moved when APP_URL changed — it contains an absolute URL.");
        }
    }

    /** Belt and braces: no absolute host inside any hashed document region. */
    public function test_no_legal_document_embeds_an_absolute_application_url(): void
    {
        $registry = app(LegalDocumentRegistry::class);
        $method = new \ReflectionMethod($registry, 'canonicalText');

        foreach ($registry->documents() as $doc) {
            $canonical = $method->invoke($registry, view($doc['view'])->render());

            $this->assertDoesNotMatchRegularExpression(
                '#(href|src)="https?://(localhost|127\.0\.0\.1|[^"]*laravel\.cloud)#i',
                $canonical,
                "The {$doc['kind']} document embeds an environment-specific URL.",
            );
        }
    }

    public function test_re_materialising_is_idempotent(): void
    {
        $this->tosHash();
        $countAfterFirst = TermsVersion::query()->count();

        app(LegalDocumentRegistry::class)->materialiseAll();

        $this->assertSame($countAfterFirst, TermsVersion::query()->count());
    }

    public function test_the_hashed_text_excludes_page_chrome(): void
    {
        $registry = app(LegalDocumentRegistry::class);

        $method = new \ReflectionMethod($registry, 'canonicalText');
        $canonical = $method->invoke($registry, view('legal.tos')->render());

        $this->assertStringNotContainsString('csrf-token', $canonical);
        $this->assertStringNotContainsString('<nav', $canonical);
        $this->assertStringNotContainsString('<!doctype', strtolower($canonical));

        // ...but still contains the agreement itself.
        $this->assertStringContainsString('Terms and Conditions', $canonical);
        $this->assertStringContainsString('Limitation of liability', $canonical);
    }

    public function test_changing_the_document_text_does_change_the_hash(): void
    {
        $before = $this->tosHash();

        // A real edit to the agreement must still mint a new version.
        \Illuminate\Support\Facades\View::composer('legal.tos', function ($view) {
            $view->getFactory()->startSection('content', '<p>Materially different terms.</p>');
        });

        $this->assertNotSame($before, $this->tosHash());
    }
}

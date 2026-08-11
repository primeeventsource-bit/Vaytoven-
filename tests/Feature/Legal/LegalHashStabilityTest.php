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
        $this->assertStringContainsString('Terms of Service', $canonical);
        $this->assertStringContainsString('Vaytoven Technologies LLC', $canonical);
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

<?php

namespace Tests\Feature\Legal;

use App\Services\Legal\LegalDocumentRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_legal_page_renders(): void
    {
        foreach (['/legal/tos', '/legal/privacy', '/legal/member-agreement'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_versions_endpoint_lists_all_three_kinds(): void
    {
        app(LegalDocumentRegistry::class)->materialiseAll();

        $resp = $this->getJson('/legal/versions');
        $resp->assertOk();

        $kinds = collect($resp->json('versions'))->pluck('kind')->all();

        $this->assertEqualsCanonicalizing(
            ['tos', 'privacy', 'member_agreement'],
            $kinds,
        );

        // Each entry must carry the SHA-256 hash and the canonical URL —
        // both used by audit tooling to verify what's published matches DB.
        foreach ($resp->json('versions') as $v) {
            $this->assertSame(64, strlen($v['content_hash']));
            $this->assertNotEmpty($v['content_url']);
        }
    }

    public function test_versions_endpoint_is_empty_when_nothing_materialised(): void
    {
        $this->getJson('/legal/versions')
            ->assertOk()
            ->assertExactJson(['versions' => []]);
    }

    public function test_legal_pages_link_from_landing_footer(): void
    {
        app(LegalDocumentRegistry::class)->materialiseAll();

        $body = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('/legal/privacy', $body);
        $this->assertStringContainsString('/legal/tos', $body);
        $this->assertStringContainsString('/legal/member-agreement', $body);
    }
}

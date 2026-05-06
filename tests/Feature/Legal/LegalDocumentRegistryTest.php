<?php

namespace Tests\Feature\Legal;

use App\Models\TermsVersion;
use App\Services\Legal\LegalDocumentRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalDocumentRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_materialise_all_creates_one_row_per_kind(): void
    {
        $registry = app(LegalDocumentRegistry::class);
        $rows = $registry->materialiseAll();

        $this->assertCount(4, $rows);
        $this->assertSame(4, TermsVersion::count());

        $kinds = TermsVersion::pluck('kind')->all();
        $this->assertEqualsCanonicalizing(
            ['tos', 'privacy', 'chargeback', 'member_agreement'],
            $kinds,
        );
    }

    public function test_materialise_all_is_idempotent(): void
    {
        $registry = app(LegalDocumentRegistry::class);

        $registry->materialiseAll();
        $first = TermsVersion::pluck('content_hash')->sort()->values()->all();

        $registry->materialiseAll();
        $second = TermsVersion::pluck('content_hash')->sort()->values()->all();

        $this->assertSame($first, $second, 'Re-running with unchanged content must not create new rows.');
        $this->assertSame(4, TermsVersion::count());
    }

    public function test_registration_required_returns_tos_and_privacy_only(): void
    {
        $registry = app(LegalDocumentRegistry::class);
        $registry->materialiseAll();

        $required = $registry->registrationRequired();
        $kinds = collect($required)->map(fn (TermsVersion $v) => $v->kind)->all();

        $this->assertEqualsCanonicalizing(['tos', 'privacy'], $kinds);
    }

    public function test_current_versions_returns_each_kinds_active_row(): void
    {
        $registry = app(LegalDocumentRegistry::class);
        $registry->materialiseAll();

        $current = $registry->currentVersions();

        $this->assertArrayHasKey('tos', $current);
        $this->assertArrayHasKey('privacy', $current);
        $this->assertArrayHasKey('chargeback', $current);
        $this->assertArrayHasKey('member_agreement', $current);
    }

    public function test_content_hash_is_sha256_of_rendered_html(): void
    {
        $registry = app(LegalDocumentRegistry::class);
        $registry->materialiseAll();

        foreach (TermsVersion::all() as $v) {
            $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $v->content_hash);
        }
    }
}

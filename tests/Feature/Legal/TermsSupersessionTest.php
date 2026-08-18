<?php

namespace Tests\Feature\Legal;

use App\Models\TermsAcceptance;
use App\Models\TermsVersion;
use App\Models\User;
use App\Services\Legal\LegalDocumentRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Exactly one version of each legal document is in force at a time.
 *
 * superseded_at existed from the start and was never written to, so every row
 * claimed to be current and currentFor() was really answering "latest
 * effective_at". That is the wrong answer as soon as a document is reverted.
 */
class TermsSupersessionTest extends TestCase
{
    use RefreshDatabase;

    private function version(string $kind, string $label, string $content, string $at): TermsVersion
    {
        Carbon::setTestNow($at);

        $version = TermsVersion::forContent(
            kind: $kind,
            content: $content,
            url: 'https://vaytoven.com/legal/tos',
            versionLabel: $label,
        );

        $version->markAsTheCurrentVersion();
        Carbon::setTestNow();

        return $version->refresh();
    }

    public function test_publishing_a_new_version_retires_the_previous_one(): void
    {
        $first  = $this->version('tos', 'v1', 'the first text', '2026-01-01 10:00:00');
        $second = $this->version('tos', 'v2', 'the second text', '2026-02-01 10:00:00');

        $this->assertNotNull($first->refresh()->superseded_at);
        $this->assertNull($second->refresh()->superseded_at);
        $this->assertTrue(TermsVersion::currentFor('tos')->is($second));
    }

    public function test_only_one_version_of_a_kind_is_ever_in_force(): void
    {
        foreach (['a', 'b', 'c', 'd'] as $i => $text) {
            $this->version('tos', 'v'.($i + 1), $text, '2026-0'.($i + 1).'-01 10:00:00');
        }

        $this->assertSame(1, TermsVersion::where('kind', 'tos')->whereNull('superseded_at')->count());
        $this->assertSame(4, TermsVersion::where('kind', 'tos')->count());
    }

    public function test_retiring_one_kind_leaves_the_others_alone(): void
    {
        $privacy = $this->version('privacy', 'v1', 'privacy text', '2026-01-01 10:00:00');

        $this->version('tos', 'v1', 'tos one', '2026-01-02 10:00:00');
        $this->version('tos', 'v2', 'tos two', '2026-01-03 10:00:00');

        $this->assertNull($privacy->refresh()->superseded_at);
    }

    /**
     * The case that ordering alone gets wrong.
     *
     * Content-addressing returns the ORIGINAL row when text is reverted, and
     * its effective_at is older than the text it replaced — so "latest
     * effective" names the version the site stopped serving, and the
     * re-acceptance middleware asks people to accept a document that is not
     * the one on screen.
     */
    public function test_reverting_to_earlier_text_makes_that_version_current_again(): void
    {
        $original = $this->version('tos', 'v1', 'the original text', '2026-01-01 10:00:00');
        $revised  = $this->version('tos', 'v2', 'the revised text', '2026-02-01 10:00:00');

        // Counsel reverts. Same text, so content-addressing returns row one.
        $reverted = $this->version('tos', 'v3', 'the original text', '2026-03-01 10:00:00');

        $this->assertTrue($reverted->is($original), 'identical text should reuse the existing row');
        $this->assertTrue(TermsVersion::currentFor('tos')->is($original));
        $this->assertNotNull($revised->refresh()->superseded_at);
    }

    /** effective_at means "when this text first took effect" and stays put. */
    public function test_a_revert_does_not_rewrite_when_the_text_first_took_effect(): void
    {
        $original = $this->version('tos', 'v1', 'the original text', '2026-01-01 10:00:00');
        $this->version('tos', 'v2', 'the revised text', '2026-02-01 10:00:00');
        $this->version('tos', 'v3', 'the original text', '2026-03-01 10:00:00');

        $this->assertSame('2026-01-01', $original->refresh()->effective_at->toDateString());
    }

    // --- what must not change -------------------------------------------------

    /**
     * "Never overwrite an accepted contract version." Retiring a version is
     * bookkeeping about whether it is still in force; the terms somebody
     * accepted, and the record of that acceptance, are untouchable.
     */
    public function test_retiring_a_version_alters_nothing_about_the_document_or_its_acceptances(): void
    {
        $first = $this->version('tos', 'v1', 'the first text', '2026-01-01 10:00:00');

        $user = User::factory()->create();
        $acceptance = TermsAcceptance::create([
            'user_id'          => $user->id,
            'terms_version_id' => $first->id,
            'accepted_at'      => now(),
            'ip_address'       => '203.0.113.10',
        ]);

        $columns = ['kind', 'version_label', 'content_hash', 'content_url', 'effective_at'];
        $snapshot = fn (TermsVersion $v) => array_map(
            fn ($value) => $value instanceof Carbon ? $value->toIso8601String() : $value,
            $v->only($columns)
        );

        $before = $snapshot($first);

        $this->version('tos', 'v2', 'the second text', '2026-02-01 10:00:00');

        $this->assertSame($before, $snapshot($first->refresh()));

        $acceptance->refresh();
        $this->assertSame($first->id, $acceptance->terms_version_id);
        $this->assertSame('203.0.113.10', $acceptance->ip_address);
    }

    /** Stamped with when it stopped being in force, not when the code ran. */
    public function test_a_version_is_stamped_at_the_moment_it_was_replaced(): void
    {
        $first = $this->version('tos', 'v1', 'the first text', '2026-01-01 10:00:00');
        $this->version('tos', 'v2', 'the second text', '2026-02-01 10:00:00');

        $this->assertSame('2026-02-01', $first->refresh()->superseded_at->toDateString());
    }

    // --- the registry ---------------------------------------------------------

    public function test_materialising_the_real_documents_leaves_one_current_version_per_kind(): void
    {
        $registry = app(LegalDocumentRegistry::class);

        $registry->materialiseAll();
        $registry->materialiseAll();   // idempotent: a re-run must not churn

        foreach (TermsVersion::distinct()->pluck('kind') as $kind) {
            $this->assertSame(
                1,
                TermsVersion::where('kind', $kind)->whereNull('superseded_at')->count(),
                "{$kind} has more than one version in force"
            );
        }
    }

    public function test_the_versions_endpoint_reports_the_version_in_force(): void
    {
        app(LegalDocumentRegistry::class)->materialiseAll();

        $response = $this->getJson('/legal/versions')->assertOk();

        foreach ($response->json('versions') as $entry) {
            $current = TermsVersion::currentFor($entry['kind']);

            $this->assertSame($current->content_hash, $entry['content_hash']);
            $this->assertNull($current->superseded_at);
        }
    }
}

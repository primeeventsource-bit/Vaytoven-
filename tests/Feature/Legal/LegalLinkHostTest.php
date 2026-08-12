<?php

namespace Tests\Feature\Legal;

use App\Models\TermsVersion;
use App\Models\User;
use App\Services\Legal\LegalDocumentRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Legal links must point at the host the user is actually on.
 *
 * terms_versions.content_url records the absolute URL the text was published at
 * when the version was materialised. The seeder runs on Laravel Cloud, so on
 * the live site every row froze the vanity hostname
 * `https://v-app-dev-main-oyo1n9.laravel.cloud/legal/tos`. The register form
 * and the re-acceptance page rendered that column verbatim, so a user on
 * vaytoven.com clicking "Terms" left for an internal hostname.
 *
 * That is infrastructure disclosure, and it reads like a phishing redirect at
 * the exact moment you are asking someone to agree to a contract. Found by
 * crawling the deployed site, not by reading the code — the templates look
 * perfectly reasonable.
 */
class LegalLinkHostTest extends TestCase
{
    use RefreshDatabase;

    /** Materialise the documents as if the seeder had run on another host. */
    private function seedWithForeignUrls(): void
    {
        app(LegalDocumentRegistry::class)->materialiseAll();

        TermsVersion::query()->get()->each(function (TermsVersion $v) {
            $v->forceFill([
                'content_url' => 'https://v-app-dev-main-oyo1n9.laravel.cloud/legal/'.$v->kind,
            ])->save();
        });
    }

    public function test_public_url_ignores_the_host_frozen_into_the_row(): void
    {
        $this->seedWithForeignUrls();

        foreach (TermsVersion::query()->get() as $version) {
            $this->assertStringNotContainsString('laravel.cloud', $version->publicUrl(),
                "The {$version->kind} link still points at the seeding environment.");
            $this->assertStringContainsString(config('app.url'), $version->publicUrl());
        }
    }

    public function test_the_register_form_does_not_link_off_to_another_host(): void
    {
        $this->seedWithForeignUrls();

        $this->get('/register')->assertOk()->assertDontSee('laravel.cloud');
    }

    /**
     * The re-acceptance page matters most: a terms revision sends every
     * existing user here, so a bad link there reaches the whole user base at
     * once rather than only new signups.
     */
    public function test_the_re_acceptance_page_does_not_link_off_to_another_host(): void
    {
        $this->seedWithForeignUrls();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('legal.review-and-accept'))
            ->assertOk()
            ->assertDontSee('laravel.cloud');
    }

    /** Auditors follow the JSON; it must not send them somewhere internal. */
    public function test_the_versions_endpoint_publishes_reachable_urls(): void
    {
        $this->seedWithForeignUrls();

        $versions = $this->getJson('/legal/versions')->assertOk()->json('versions');

        $this->assertNotEmpty($versions);
        foreach ($versions as $v) {
            $this->assertStringNotContainsString('laravel.cloud', $v['content_url']);
            $this->assertStringContainsString('/legal/', $v['content_url']);
        }
    }

    /**
     * The recorded URL itself is left alone — it is an audit fact about where
     * the text was published, and rewriting history to make a link pretty
     * would be the wrong fix.
     */
    public function test_the_recorded_publication_url_is_preserved(): void
    {
        $this->seedWithForeignUrls();

        $this->assertStringContainsString(
            'laravel.cloud',
            TermsVersion::query()->first()->content_url,
        );
    }
}

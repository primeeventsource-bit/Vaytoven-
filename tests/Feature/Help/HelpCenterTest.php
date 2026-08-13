<?php

namespace Tests\Feature\Help;

use App\Enums\HelpAudience;
use App\Models\HelpArticle;
use Database\Seeders\HelpArticleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_renders_with_published_articles_grouped_by_category(): void
    {
        $this->seed(HelpArticleSeeder::class);

        $this->get('/help')
            ->assertOk()
            ->assertSee('How can we help?')
            ->assertSee('How to submit an offer')
            ->assertSee('Becoming a Vaytoven host')
            ->assertSee('How the managed program works')
            // The cancellation-policy articles described refund tiers on stays
            // Vaytoven never took payment for.
            ->assertDontSee('cancellation policy');
    }

    public function test_index_filters_by_audience_query_string(): void
    {
        $this->seed(HelpArticleSeeder::class);

        $resp = $this->get('/help?audience=member');
        $resp->assertOk();
        $resp->assertSee('How the managed program works');

        // Host-only articles must not leak into a member view (the 'all'
        // bucket still shows because it's relevant to every audience).
        $resp->assertDontSee('Becoming a Vaytoven host');
    }

    public function test_show_renders_article_body(): void
    {
        $this->seed(HelpArticleSeeder::class);

        $this->get('/help/how-offers-work')
            ->assertOk()
            ->assertSee('How to submit an offer')
            ->assertSee('is not a reservation', false);
    }

    public function test_show_404_for_missing_slug(): void
    {
        $this->get('/help/nonexistent-article')->assertNotFound();
    }

    public function test_show_404_for_unpublished_article(): void
    {
        HelpArticle::create([
            'slug' => 'draft-only',
            'audience' => HelpAudience::Traveler,
            'category' => 'cancellation',
            'title' => 'Draft article',
            'summary' => 'Not yet live.',
            'body' => 'This should never be visible publicly.',
            'is_published' => false,
        ]);

        $this->get('/help/draft-only')->assertNotFound();
    }

    public function test_search_endpoint_returns_json_results(): void
    {
        $this->seed(HelpArticleSeeder::class);

        $resp = $this->getJson('/help/search?q=cancel');
        $resp->assertOk()
            ->assertJsonStructure([
                'query',
                'audience',
                'results' => [['slug', 'title', 'summary', 'audience', 'category', 'url']],
            ]);

        // "cancel" still has to return something useful — it is a question
        // people genuinely arrive with. It now resolves to the article that
        // explains cancellation is between them and the listing member,
        // rather than to a Vaytoven refund tier that never existed.
        $slugs = collect($resp->json('results'))->pluck('slug')->all();
        $this->assertContains('who-handles-refunds', $slugs);
    }

    public function test_search_endpoint_scopes_by_audience(): void
    {
        $this->seed(HelpArticleSeeder::class);

        $resp = $this->getJson('/help/search?q=payout&audience=member');
        $resp->assertOk();

        // Member-scoped search should bias toward member articles + 'all',
        // and not pull host-only articles.
        $audiences = collect($resp->json('results'))->pluck('audience')->unique()->all();
        foreach ($audiences as $a) {
            $this->assertContains($a, ['member', 'all']);
        }
    }

    public function test_search_returns_empty_results_for_short_query(): void
    {
        $this->seed(HelpArticleSeeder::class);

        $this->getJson('/help/search?q=a')
            ->assertOk()
            ->assertExactJson(['query' => 'a', 'audience' => null, 'results' => []]);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(HelpArticleSeeder::class);
        $first = HelpArticle::count();

        // Re-running the seeder must not duplicate rows — slug-keyed upsert.
        $this->seed(HelpArticleSeeder::class);
        $this->assertSame($first, HelpArticle::count());
    }

    public function test_seeder_creates_articles_for_every_audience(): void
    {
        $this->seed(HelpArticleSeeder::class);

        $audiences = HelpArticle::pluck('audience')->map(fn ($a) => $a->value)->unique()->sort()->values()->all();

        $this->assertContains('traveler', $audiences);
        $this->assertContains('host', $audiences);
        $this->assertContains('member', $audiences);
    }
}

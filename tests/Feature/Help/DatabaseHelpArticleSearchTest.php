<?php

namespace Tests\Feature\Help;

use App\Enums\HelpAudience;
use App\Models\HelpArticle;
use App\Services\Help\DatabaseHelpArticleSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseHelpArticleSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_returns_empty_for_blank_query(): void
    {
        $search = new DatabaseHelpArticleSearch();
        $this->assertCount(0, $search->search(''));
        $this->assertCount(0, $search->search('   '));
    }

    public function test_search_returns_empty_for_short_tokens(): void
    {
        HelpArticle::create($this->articleAttrs(['slug' => 'a', 'title' => 'A']));

        $search = new DatabaseHelpArticleSearch();

        // 'a' is below the 2-char threshold — guards against LIKE '%a%' matching everything.
        $this->assertCount(0, $search->search('a'));
    }

    public function test_search_ranks_title_hits_above_body_hits(): void
    {
        // Both articles match "refund" but only the first has it in the title.
        HelpArticle::create($this->articleAttrs([
            'slug' => 'refund-policy', 'title' => 'Refund policy', 'summary' => 's', 'body' => 'b',
        ]));
        HelpArticle::create($this->articleAttrs([
            'slug' => 'cancel-tips', 'title' => 'Cancel tips', 'summary' => 's',
            'body' => 'You may receive a refund according to policy.',
        ]));

        $search = new DatabaseHelpArticleSearch();
        $results = $search->search('refund');

        $this->assertSame('refund-policy', $results->first()->slug);
    }

    public function test_search_filters_by_audience_and_includes_all_bucket(): void
    {
        HelpArticle::create($this->articleAttrs([
            'slug' => 'host-only', 'title' => 'Host-only refund', 'audience' => HelpAudience::Host,
        ]));
        HelpArticle::create($this->articleAttrs([
            'slug' => 'member-only', 'title' => 'Member-only refund', 'audience' => HelpAudience::Member,
        ]));
        HelpArticle::create($this->articleAttrs([
            'slug' => 'shared', 'title' => 'Universal refund rule', 'audience' => HelpAudience::All,
        ]));

        $search = new DatabaseHelpArticleSearch();

        $member = $search->search('refund', HelpAudience::Member)->pluck('slug')->all();

        $this->assertContains('member-only', $member);
        $this->assertContains('shared', $member);
        $this->assertNotContains('host-only', $member);
    }

    public function test_search_skips_unpublished_articles(): void
    {
        HelpArticle::create($this->articleAttrs([
            'slug' => 'draft-refund', 'title' => 'Refund draft', 'is_published' => false,
        ]));
        HelpArticle::create($this->articleAttrs([
            'slug' => 'live-refund', 'title' => 'Refund live',
        ]));

        $search = new DatabaseHelpArticleSearch();
        $slugs = $search->search('refund')->pluck('slug')->all();

        $this->assertContains('live-refund', $slugs);
        $this->assertNotContains('draft-refund', $slugs);
    }

    public function test_search_keywords_field_is_searchable(): void
    {
        HelpArticle::create($this->articleAttrs([
            'slug' => 'kyc',
            'title' => 'Identity verification',
            'summary' => 'Required before payouts.',
            'body' => 'You must verify your identity with Stripe.',
            'search_keywords' => 'kyc, know your customer',
        ]));

        $search = new DatabaseHelpArticleSearch();
        $slugs = $search->search('kyc')->pluck('slug')->all();

        $this->assertContains('kyc', $slugs);
    }

    private function articleAttrs(array $overrides = []): array
    {
        return array_merge([
            'slug' => 'sample-'.uniqid(),
            'audience' => HelpAudience::All,
            'category' => 'general',
            'title' => 'Sample',
            'summary' => 'Sample summary',
            'body' => 'Sample body content',
            'is_published' => true,
        ], $overrides);
    }
}

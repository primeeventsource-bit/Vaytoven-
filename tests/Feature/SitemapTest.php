<?php

namespace Tests\Feature;

use App\Enums\PropertyStatus;
use App\Models\HelpArticle;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_serves_xml(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<urlset', false)
            ->assertSee(url('/'), false);
    }

    public function test_it_lists_the_public_pages(): void
    {
        $response = $this->get('/sitemap.xml');

        foreach (['properties.index', 'hosts.show', 'members.show', 'member-services.show',
                  'legal.tos', 'legal.privacy', 'help.index'] as $name) {
            $response->assertSee(route($name), false);
        }
    }

    /**
     * The list is an allow-list for a reason: a sitemap harvested from the
     * router is how /admin and one-time payment links get advertised to
     * search engines.
     */
    public function test_it_never_lists_private_areas(): void
    {
        $body = $this->get('/sitemap.xml')->getContent();

        foreach (['/admin', '/account', '/dashboard', '/member-payment', '/webhooks', '/login'] as $path) {
            $this->assertStringNotContainsString('<loc>'.url($path), $body);
        }
    }

    // --- dynamic entries ------------------------------------------------------

    public function test_an_active_property_is_listed(): void
    {
        $host = User::factory()->create();
        $property = Property::factory()->create([
            'host_id' => $host->id,
            'status'  => PropertyStatus::Active->value,
        ]);

        $this->get('/sitemap.xml')->assertSee(route('properties.show', $property), false);
    }

    /** A paused listing 404s, and a sitemap full of 404s is worse than a short one. */
    public function test_a_non_active_property_is_not_listed(): void
    {
        $host = User::factory()->create();

        foreach ([PropertyStatus::Draft, PropertyStatus::Paused, PropertyStatus::Archived] as $status) {
            $property = Property::factory()->create([
                'host_id' => $host->id,
                'status'  => $status->value,
            ]);

            $this->get('/sitemap.xml')
                ->assertDontSee(route('properties.show', $property), false);
        }
    }

    public function test_published_help_articles_are_listed(): void
    {
        $article = HelpArticle::factory()->create();

        $this->get('/sitemap.xml')->assertSee(route('help.show', $article->slug), false);
    }

    /** An unpublished article 404s, so listing it advertises a dead URL. */
    public function test_an_unpublished_help_article_is_not_listed(): void
    {
        $article = HelpArticle::factory()->unpublished()->create();

        $this->get('/sitemap.xml')->assertDontSee(route('help.show', $article->slug), false);
    }

    // --- robots ---------------------------------------------------------------

    public function test_robots_points_at_the_sitemap_and_blocks_private_areas(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));

        $this->assertStringContainsString('Sitemap: https://vaytoven.com/sitemap.xml', $robots);

        foreach (['/admin', '/account', '/member-payment', '/api/'] as $path) {
            $this->assertStringContainsString('Disallow: '.$path, $robots);
        }
    }
}

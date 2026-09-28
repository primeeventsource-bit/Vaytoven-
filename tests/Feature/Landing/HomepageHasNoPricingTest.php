<?php

namespace Tests\Feature\Landing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public homepage carries no prices.
 *
 * Package pricing belongs to the enrollment flow, and a nightly figure on a
 * marketing card is a number Vaytoven neither sets nor collects. A visitor
 * browsing stays is not shopping for an advertising package, and the package
 * price means nothing until somebody has chosen how many weeks they want
 * advertised.
 */
class HomepageHasNoPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_homepage_shows_no_currency_amounts(): void
    {
        $body = $this->get('/')->assertOk()->getContent();

        // Any "$12", "$1,200", "$84/week" — in copy, in a card, anywhere.
        $this->assertDoesNotMatchRegularExpression(
            '/\$\s?[0-9]/',
            $body,
            'A currency amount is back on the public homepage.',
        );

        foreach (['from $', '/week', 'Estimated annual earnings', 'Sample member earnings', 'Compare all features'] as $gone) {
            $this->assertStringNotContainsString($gone, $body, "\"{$gone}\" is back on the homepage.");
        }
    }

    /** The page still sells the thing — it just does not quote it. */
    public function test_the_homepage_still_leads_members_into_enrollment(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Member Services')
            ->assertSee('Start Member Services')
            ->assertSee(route('member-services.show'), false)
            ->assertSee('Managed Listing Program')
            // Listings, enquiries and offers still front the page.
            ->assertSee('Featured stays')
            ->assertSee(route('properties.index'), false);
    }

    /** Pricing lives in the enrollment flow, which is reached from the nav. */
    public function test_package_pricing_is_still_available_once_enrollment_starts(): void
    {
        $body = $this->get(route('member-services.show'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/\$\s?[0-9]/', $body, 'Enrollment lost its package pricing.');
    }
}

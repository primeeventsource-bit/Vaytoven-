<?php

namespace Tests\Feature\Landing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public homepage quotes no package pricing.
 *
 * What Vaytoven charges to advertise belongs to the enrollment flow: a
 * visitor browsing stays is not shopping for an advertising package, and the
 * figure means nothing before somebody has chosen how many weeks they want
 * advertised.
 *
 * Earnings projections are a different thing and stay: they are the owner's
 * own rate and weeks, money Vaytoven never collects.
 */
class HomepageHasNoPricingTest extends TestCase
{
    use RefreshDatabase;

    private function homepage(): string
    {
        return preg_replace('/\s+/', ' ', strip_tags($this->get('/')->assertOk()->getContent()));
    }

    public function test_the_homepage_quotes_no_package_price(): void
    {
        $text = $this->homepage();

        foreach (['$249', '$349', '$449'] as $price) {
            $this->assertStringNotContainsString($price, $text, "The homepage quotes the package price {$price}.");
        }

        foreach (['/week', 'per week', 'from $', 'Compare all features', 'Pick a package'] as $gone) {
            $this->assertStringNotContainsStringIgnoringCase($gone, $text, "\"{$gone}\" is back on the homepage.");
        }
    }

    /**
     * No percentage fee, anywhere.
     *
     * Vaytoven is paid to advertise and takes no share of what a guest pays.
     * A "3%" beside an earnings figure describes a commission this company
     * does not charge.
     */
    public function test_the_homepage_never_states_a_percentage_fee(): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/\b\d+(\.\d+)?%\s*(fee|commission|cut)/i',
            $this->homepage(),
            'The homepage states a percentage fee.',
        );
    }

    /** The projections a property owner comes for are still here. */
    public function test_the_earnings_projections_are_still_shown(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Estimated annual earnings')
            ->assertSee('Before our fee')
            ->assertSee('Sample member earnings · illustrative')
            ->assertSee('Illustrative only')
            ->assertSee('Average nightly rate');
    }

    /** The page still leads a member into enrollment, where prices live. */
    public function test_the_homepage_still_leads_members_into_enrollment(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Member Services')
            ->assertSee('Start Member Services')
            ->assertSee(route('member-services.show'), false)
            ->assertSee('Featured stays')
            ->assertSee(route('properties.index'), false);
    }

    public function test_package_pricing_is_available_once_enrollment_starts(): void
    {
        $enrollment = preg_replace('/\s+/', ' ', strip_tags(
            $this->get(route('member-services.show'))->assertOk()->getContent(),
        ));

        foreach (['$249', '$349', '$449'] as $price) {
            $this->assertStringContainsString($price, $enrollment, "Enrollment does not show {$price}.");
        }
    }
}

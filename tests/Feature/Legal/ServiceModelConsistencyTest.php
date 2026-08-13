<?php

namespace Tests\Feature\Legal;

use Database\Seeders\HelpArticleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Terms must describe the business the company actually runs.
 *
 * Vaytoven sells exactly two things:
 *
 *   Host   — a recurring MONTHLY SaaS subscription. The host builds and
 *            manages their own listing through the dashboard.
 *   Member — a ONE-TIME fee for a 180-day managed listing term. Vaytoven
 *            assists with the listing and markets it. Explicitly NOT a
 *            recurring subscription.
 *
 * Getting this wrong in the Terms is not a copy problem — it is a contract
 * describing a billing relationship the customer does not have. The pricing
 * copy on the marketing pages drifted from it once already: the member program
 * was advertised as "$200–$800 per week plus a subscription fee", which is a
 * recurring weekly charge, in a document that now says one payment for 180
 * days.
 */
class ServiceModelConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private function tosText(): string
    {
        return preg_replace('/\s+/', ' ',
            strip_tags($this->get('/legal/tos')->assertOk()->getContent()));
    }

    public function test_the_terms_describe_the_host_monthly_subscription(): void
    {
        $text = $this->tosText();

        $this->assertStringContainsString('Monthly SaaS Subscription', $text);
        $this->assertStringContainsString('monthly subscription fee', $text);
        $this->assertStringContainsString(
            'responsible for creating, maintaining, and managing their own property listing',
            $text,
        );
    }

    public function test_the_terms_describe_the_180_day_member_program(): void
    {
        $text = $this->tosText();

        $this->assertStringContainsString('180-Day Managed Listing Program', $text);
        $this->assertStringContainsString('one-time fee', $text);

        // The negative is the part that protects the customer: they must not
        // be able to read this as something that bills again.
        $this->assertStringContainsString(
            'is not a monthly or annual recurring subscription',
            $text,
        );
    }

    public function test_the_terms_state_that_travelers_are_never_charged(): void
    {
        $text = $this->tosText();

        $this->assertStringContainsString('does not charge travelers', $text);
        $this->assertStringContainsString('never charges anyone for a stay', $text);
    }

    /**
     * The Terms must not offer a stay cancellation policy. Vaytoven takes no
     * payment for a stay, so it has nothing to cancel and nothing to refund.
     */
    public function test_the_terms_disclaim_any_stay_cancellation_policy(): void
    {
        $text = $this->tosText();

        $this->assertStringContainsString('Vaytoven has no stay cancellation policy', $text);
        $this->assertStringContainsString('displays no cancellation policy for a stay', $text);
    }

    public function test_the_terms_name_the_operating_entity(): void
    {
        $this->assertStringContainsString('Vaytoven Technologies LLC', $this->tosText());
    }

    /**
     * The marketing pages must not contradict the contract.
     *
     * A per-week charge is a recurring charge. Advertising one while the Terms
     * promise a single payment for 180 days is the kind of mismatch a customer
     * discovers on their second invoice.
     */
    public function test_no_public_page_advertises_a_recurring_member_charge(): void
    {
        $pages = ['/', '/members', '/become-a-host'];

        foreach ($pages as $url) {
            $text = preg_replace('/\s+/', ' ',
                strip_tags($this->get($url)->assertOk()->getContent()));

            foreach (['per week', 'upfront weekly', 'weekly program cost'] as $phrase) {
                $this->assertStringNotContainsStringIgnoringCase($phrase, $text,
                    "{$url} advertises \"{$phrase}\", contradicting the one-time 180-day member fee.");
            }
        }
    }

    /** The help center has to answer "which one am I on?" correctly. */
    public function test_the_help_center_explains_both_options(): void
    {
        $this->seed(HelpArticleSeeder::class);

        $text = preg_replace('/\s+/', ' ', strip_tags(
            $this->get('/help/host-subscription-or-member-program')->assertOk()->getContent(),
        ));

        $this->assertStringContainsString('monthly subscription fee', $text);
        $this->assertStringContainsString('one-time fee for a 180-day', $text);
        $this->assertStringContainsString('NOT a monthly or annual recurring subscription', $text);
    }
}

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
 *   180-Day Member Managed Listing Program — a ONE-TIME fee for managed
 *            listing and advertising services across the 180-day program.
 *            Explicitly NOT a recurring subscription.
 *   Host 30-Day Subscription — a RECURRING 30-day subscription for access to
 *            the SaaS platform and dashboard. The host creates and manages
 *            their own listings.
 *
 * "30-day" is not shorthand for "monthly": the cycle runs 30 days from the
 * start date, so the renewal drifts against the calendar. Describing it as
 * monthly in a contract sets a billing expectation the system will not meet.
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

    public function test_the_terms_describe_the_host_30_day_subscription(): void
    {
        $text = $this->tosText();

        $this->assertStringContainsString('Host 30-Day Subscription', $text);
        $this->assertStringContainsString('recurring 30-day subscription', $text);
        $this->assertStringContainsString(
            'responsible for creating and managing their own listings',
            $text,
        );
    }

    /**
     * The Terms must not call the host subscription "monthly".
     *
     * A 30-day cycle and a calendar month are different products from a
     * billing standpoint — 30 days drifts, a month does not — and a customer
     * who reads "monthly" will expect the same date each month.
     */
    public function test_the_terms_do_not_describe_the_host_plan_as_monthly(): void
    {
        $this->assertStringNotContainsStringIgnoringCase('monthly subscription', $this->tosText());
    }

    public function test_the_terms_describe_the_180_day_member_program(): void
    {
        $text = $this->tosText();

        $this->assertStringContainsString('180-Day Member Managed Listing Program', $text);
        $this->assertStringContainsString('one-time fee', $text);

        // The negative is the part that protects the customer: they must not
        // be able to read this as something that bills again.
        $this->assertStringContainsString('is not a recurring subscription', $text);
        $this->assertStringContainsString('does not bill again', $text);
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
     * The distinction is between a fee CALCULATED per week and a fee CHARGED
     * every week. The member packages are priced per week ($249/$349/$449) and
     * charged once — "$449/week × 6 weeks, paid today" is accurate and is what
     * the pricing section says.
     *
     * What must never appear is the framing that says the member keeps paying:
     * "upfront weekly cost", "weekly program cost", "plus a subscription fee".
     * That is what the page used to claim, and it is the mismatch a customer
     * discovers on their second invoice.
     */
    public function test_no_public_page_advertises_a_recurring_member_charge(): void
    {
        $pages = ['/', '/members', '/become-a-host'];

        $recurringFraming = [
            'upfront weekly',
            'weekly program cost',
            'weekly cost',
            'per week plus a subscription',
            'weekly subscription',
        ];

        foreach ($pages as $url) {
            $text = preg_replace('/\s+/', ' ',
                strip_tags($this->get($url)->assertOk()->getContent()));

            foreach ($recurringFraming as $phrase) {
                $this->assertStringNotContainsStringIgnoringCase($phrase, $text,
                    "{$url} advertises \"{$phrase}\", which reads as a recurring charge.");
            }
        }
    }

    /**
     * Where a weekly rate IS shown, it must say the charge happens once.
     *
     * "$449/week" on its own invites the reading that it bills weekly. The
     * pricing section has to carry the qualifier next to the numbers.
     */
    public function test_the_pricing_section_says_the_fee_is_charged_once(): void
    {
        $text = preg_replace('/\s+/', ' ', strip_tags($this->get('/')->assertOk()->getContent()));

        $this->assertStringContainsString('charged once', $text);
        $this->assertStringContainsString('not a recurring subscription', $text);
    }

    /** The help center has to answer "which one am I on?" correctly. */
    public function test_the_help_center_explains_both_options(): void
    {
        $this->seed(HelpArticleSeeder::class);

        $text = preg_replace('/\s+/', ' ', strip_tags(
            $this->get('/help/host-subscription-or-member-program')->assertOk()->getContent(),
        ));

        $this->assertStringContainsString('recurring 30-day subscription', $text);
        $this->assertStringContainsString('one-time fee', $text);
        $this->assertStringContainsString('NOT a recurring subscription', $text);
        $this->assertStringContainsString('creating and managing your own listings', $text);
    }
}

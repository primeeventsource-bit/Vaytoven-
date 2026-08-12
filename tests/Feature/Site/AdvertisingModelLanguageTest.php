<?php

namespace Tests\Feature\Site;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vaytoven is a SaaS advertising platform, not a booking platform.
 *
 * The host pays Vaytoven to advertise. What a guest pays for a stay is settled
 * peer to peer, directly between guest and host, after an offer is accepted and
 * the dates are agreed. Vaytoven never collects it, never holds it in escrow,
 * and never remits it — so it also never takes a percentage of it.
 *
 * These pages previously described the opposite: a "3% host fee", a payout
 * schedule, KYC enrolment before going live, and an earnings calculator that
 * deducted a commission. Each of those statements was not merely off-brand, it
 * described a regulated money-transmission relationship the company does not
 * have. This test is the guard that stops that language reappearing.
 */
class AdvertisingModelLanguageTest extends TestCase
{
    use RefreshDatabase;

    /** Public surfaces a host or guest reads before deciding to sign up. */
    private const HOST_FACING = [
        '/',
        '/become-a-host',
        '/members',
        '/host/onboarding',
        '/list-your-property',
        '/earnings-calculator',
    ];

    /**
     * Phrases that assert Vaytoven moves rental money or takes a cut of it.
     *
     * Matched against text with tags stripped, so a comment in the template
     * explaining WHY the claim is absent does not trip the assertion.
     */
    private const FORBIDDEN = [
        'kyc',
        'enroll for payouts',
        'enrol for payouts',
        'payout schedule',
        'host fee',
        'host commission',
        'commission on each booking',
        'we pay you',
        'we pay out',
        'we hold your funds',
        'held in escrow',
        'payout method',
    ];

    public function test_no_host_facing_page_claims_vaytoven_pays_the_host(): void
    {
        foreach (self::HOST_FACING as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            // Strip tags first: the templates carry HTML comments explaining
            // why these claims are gone, and those must not count as hits.
            $text = strtolower(preg_replace('/\s+/', ' ', strip_tags($html)));

            foreach (self::FORBIDDEN as $phrase) {
                $this->assertStringNotContainsString(
                    $phrase,
                    $text,
                    "{$url} contains \"{$phrase}\" — Vaytoven advertises listings and does not "
                    ."collect, hold, or remit rental money, nor take a percentage of it.",
                );
            }
        }
    }

    /**
     * The negation has to be stated, not merely implied by absence.
     *
     * A host reading these pages must be told plainly who pays them, because
     * the alternative reading — that the platform pays, like every competitor
     * — is what they arrive expecting.
     */
    public function test_the_host_pages_say_plainly_that_the_guest_pays_directly(): void
    {
        foreach (['/become-a-host', '/host/onboarding', '/members'] as $url) {
            $text = strtolower(preg_replace('/\s+/', ' ',
                strip_tags($this->get($url)->assertOk()->getContent())));

            $this->assertTrue(
                str_contains($text, 'directly') && str_contains($text, 'advertis'),
                "{$url} never states that payment is settled directly and that Vaytoven's role "
                .'is advertising.',
            );
        }
    }

    /**
     * The calculator must not subtract anything.
     *
     * It previously rendered "Host commission (3%)" and deducted it from the
     * estimate, which told a prospective host that Vaytoven takes a cut of a
     * stay. Gross to the host IS the estimate.
     */
    public function test_the_earnings_calculator_deducts_nothing(): void
    {
        $html = $this->get('/earnings-calculator')->assertOk()->getContent();

        $this->assertStringNotContainsString('calc-fee', $html,
            'The calculator still renders a fee deduction line.');
        $this->assertStringNotContainsString('FEE_PCT', $html,
            'The calculator still applies a percentage to the estimate.');

        $text = strtolower(preg_replace('/\s+/', ' ', strip_tags($html)));
        $this->assertStringContainsString('we take no commission on a stay', $text);
    }

    /** The legal pages carry the same position, in the binding wording. */
    public function test_the_legal_documents_disclaim_escrow_and_intermediation(): void
    {
        foreach (['/legal/tos', '/legal/member-agreement'] as $url) {
            $text = preg_replace('/\s+/', ' ',
                strip_tags($this->get($url)->assertOk()->getContent()));

            $this->assertMatchesRegularExpression(
                '/not (a )?(real estate broker|party)|does not hold|solely responsible/i',
                $text,
                "{$url} does not disclaim acting as an intermediary for the transaction.",
            );
        }
    }
}

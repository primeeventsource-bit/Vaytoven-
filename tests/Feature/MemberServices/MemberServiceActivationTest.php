<?php

namespace Tests\Feature\MemberServices;

use App\Enums\MemberServiceOrderStatus;
use App\Enums\MemberServicePackage;
use App\Mail\MemberServicePaymentLink;
use App\Models\MemberServiceOrder;
use App\Models\Setting;
use App\Services\MemberServices\MemberServiceOrderFactory;
use App\Services\Settings\SettingsRepository;
use App\Services\Settings\SettingsSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Member Services activation: choosing a package and locking in a price.
 *
 * The load-bearing property is that the AMOUNT IS SERVER-COMPUTED. The browser
 * shows a running total so the member knows what they are agreeing to, but
 * that figure is decoration — nothing about the money comes from the request.
 * If it did, a member could accept a $2,694 quote and post $2.94, and the
 * gateway would take it, because by then it is just a number the application
 * asked for.
 */
class MemberServiceActivationTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'package'    => 'gold',
            'weeks'      => 6,
            'first_name' => 'Dana',
            'last_name'  => 'Whitfield',
            'email'      => 'dana@example.com',
            'phone'      => '+1 555 555 0100',
        ], $overrides);
    }

    private function setPrice(MemberServicePackage $package, int $cents): void
    {
        $spec = SettingsSchema::spec($package->settingKey());

        Setting::query()->updateOrCreate(
            ['key' => $package->settingKey()],
            [
                'group' => $spec['group'], 'type' => $spec['type'], 'label' => $spec['label'],
                'value' => (string) $cents, 'default_value' => (string) $spec['default'],
                'is_public' => false, 'is_sensitive' => false,
            ],
        );

        app(SettingsRepository::class)->forget($package->settingKey());
    }

    // --- pricing ----------------------------------------------------------

    public function test_the_three_packages_price_at_249_349_and_449_per_week(): void
    {
        $this->assertSame(24900, MemberServicePackage::Bronze->currentPricePerWeekCents());
        $this->assertSame(34900, MemberServicePackage::Silver->currentPricePerWeekCents());
        $this->assertSame(44900, MemberServicePackage::Gold->currentPricePerWeekCents());
    }

    /** The worked example from the spec: Gold x 6 weeks = $2,694.00. */
    public function test_gold_times_six_weeks_totals_2694_dollars(): void
    {
        Mail::fake();

        $this->post('/member-services', $this->validPayload(['package' => 'gold', 'weeks' => 6]))
            ->assertRedirect();

        $order = MemberServiceOrder::sole();

        $this->assertSame(44900, $order->price_per_week_cents);
        $this->assertSame(269400, $order->total_cents);
        $this->assertSame('2,694.00', $order->totalDollars());
    }

    public function test_bronze_times_four_weeks_totals_996_dollars(): void
    {
        Mail::fake();

        $this->post('/member-services', $this->validPayload(['package' => 'bronze', 'weeks' => 4]));

        $this->assertSame(99600, MemberServiceOrder::sole()->total_cents);
    }

    public function test_silver_times_four_weeks_totals_1396_dollars(): void
    {
        Mail::fake();

        $this->post('/member-services', $this->validPayload(['package' => 'silver', 'weeks' => 4]));

        $this->assertSame(139600, MemberServiceOrder::sole()->total_cents);
    }

    // --- the amount cannot come from the request --------------------------

    /**
     * The whole point. A submitted amount must be ignored, not merely
     * validated against — there is no amount field at all.
     */
    public function test_an_amount_submitted_with_the_form_is_ignored(): void
    {
        Mail::fake();

        $this->post('/member-services', $this->validPayload([
            'package'      => 'gold',
            'weeks'        => 6,
            // Everything an attacker might try.
            'amount'       => '2.94',
            'total'        => 294,
            'total_cents'  => 294,
            'price_per_week_cents' => 1,
        ]))->assertRedirect();

        $order = MemberServiceOrder::sole();

        $this->assertSame(269400, $order->total_cents, 'A request field changed the price.');
        $this->assertSame(44900, $order->price_per_week_cents);
    }

    // --- the snapshot -----------------------------------------------------

    /**
     * Repricing Gold must not reprice a link a member is already holding.
     */
    public function test_a_later_price_change_does_not_reprice_an_existing_order(): void
    {
        Mail::fake();

        $this->post('/member-services', $this->validPayload(['package' => 'gold', 'weeks' => 6]));
        $order = MemberServiceOrder::sole();

        $this->setPrice(MemberServicePackage::Gold, 49900);   // $449 -> $499

        $order->refresh();

        $this->assertSame(44900, $order->price_per_week_cents, 'The snapshot moved with the setting.');
        $this->assertSame(269400, $order->total_cents);

        // ...but a NEW order picks up the new rate.
        $this->post('/member-services', $this->validPayload([
            'package' => 'gold', 'weeks' => 1, 'email' => 'second@example.com',
        ]));

        $newest = MemberServiceOrder::orderByDesc('id')->first();
        $this->assertSame(49900, $newest->price_per_week_cents);
    }

    public function test_an_admin_price_change_applies_to_the_next_order(): void
    {
        Mail::fake();
        $this->setPrice(MemberServicePackage::Bronze, 19900);

        $this->post('/member-services', $this->validPayload(['package' => 'bronze', 'weeks' => 3]));

        $this->assertSame(59700, MemberServiceOrder::sole()->total_cents);
    }

    // --- validation -------------------------------------------------------

    public function test_weeks_must_be_at_least_one(): void
    {
        $this->post('/member-services', $this->validPayload(['weeks' => 0]))
            ->assertSessionHasErrors('weeks');

        $this->assertSame(0, MemberServiceOrder::count());
    }

    public function test_weeks_are_capped_so_a_typo_cannot_create_a_huge_order(): void
    {
        // 440 instead of 44 — the realistic mistake.
        $this->post('/member-services', $this->validPayload(['weeks' => 440]))
            ->assertSessionHasErrors('weeks');

        $this->assertSame(0, MemberServiceOrder::count());
    }

    public function test_an_unknown_package_is_rejected(): void
    {
        $this->post('/member-services', $this->validPayload(['package' => 'platinum']))
            ->assertSessionHasErrors('package');
    }

    public function test_contact_details_are_required(): void
    {
        $this->post('/member-services', ['package' => 'gold', 'weeks' => 2])
            ->assertSessionHasErrors(['first_name', 'last_name', 'email']);
    }

    // --- the order --------------------------------------------------------

    public function test_it_issues_a_reference_and_sends_the_member_to_the_payment_page(): void
    {
        Mail::fake();

        $response = $this->post('/member-services', $this->validPayload());
        $order = MemberServiceOrder::sole();

        $this->assertMatchesRegularExpression('/^VTN-[A-Z2-9]{8}$/', $order->reference);
        $response->assertRedirect(route('member-payment.show', $order->reference));

        $this->assertSame(MemberServiceOrderStatus::AwaitingPayment, $order->status);
        $this->assertNotNull($order->link_expires_at);
    }

    /**
     * References must not be guessable. A sequential id in a URL lets anyone
     * walk the range and read other members' names, phones and amounts.
     */
    public function test_references_are_not_sequential(): void
    {
        Mail::fake();

        $refs = [];
        foreach (['a@example.com', 'b@example.com', 'c@example.com'] as $email) {
            $this->post('/member-services', $this->validPayload(['email' => $email]));
            $refs[] = MemberServiceOrder::orderByDesc('id')->first()->reference;
        }

        $this->assertCount(3, array_unique($refs), 'References collided.');

        // Sequential means "the next one is predictable from the last", not
        // "it ends in a digit" — the alphabet contains 2-9, so a random
        // reference legitimately ends in a number about a third of the time.
        // What must not happen is the ids being one apart.
        $bodies = array_map(fn ($r) => substr($r, 4), $refs);

        $this->assertNotSame($bodies, array_map('strval', range(
            (int) $bodies[0], (int) $bodies[0] + 2,
        )));

        // And no two share a prefix, which a counter-based scheme would.
        $this->assertCount(3, array_unique(array_map(fn ($b) => substr($b, 0, 5), $bodies)),
            'References share a prefix — they look counter-derived.');

        // Each is drawn from the intended alphabet at the intended length.
        foreach ($refs as $ref) {
            $this->assertMatchesRegularExpression('/^VTN-[A-Z2-9]{8}$/', $ref);
        }
    }

    public function test_it_emails_the_payment_link(): void
    {
        Mail::fake();
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.example.com',
            'mail.mailers.smtp.username' => 'u',
            'mail.mailers.smtp.password' => 'p',
        ]);

        $this->post('/member-services', $this->validPayload());

        Mail::assertSent(MemberServicePaymentLink::class, fn ($mail) => $mail->hasTo('dana@example.com'));
    }

    /**
     * A mail outage must not lose the order.
     *
     * Mail is currently undeliverable in production, so this is the live path:
     * the order is still created and the member still reaches the payment page.
     */
    public function test_the_order_survives_a_mail_outage(): void
    {
        config(['mail.default' => 'log']);
        app()->detectEnvironment(fn () => 'production');
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $this->post('/member-services', $this->validPayload())->assertRedirect();

        $this->assertSame(1, MemberServiceOrder::count());
        $this->assertSame(269400, MemberServiceOrder::sole()->total_cents);
    }

    // --- the activation page ---------------------------------------------

    public function test_the_page_shows_all_three_packages_with_weekly_rates(): void
    {
        $this->get('/member-services')
            ->assertOk()
            ->assertSee('BRONZE')->assertSee('$249')
            ->assertSee('SILVER')->assertSee('$349')
            ->assertSee('GOLD')->assertSee('$449');
    }

    /** No amount field may exist on the form, hidden or otherwise. */
    public function test_the_form_submits_no_money_field(): void
    {
        $html = $this->get('/member-services')->assertOk()->getContent();

        foreach (['name="amount"', 'name="total"', 'name="total_cents"', 'name="price'] as $field) {
            $this->assertStringNotContainsString($field, $html,
                "The activation form submits {$field} — the amount must be server-computed.");
        }
    }

    // --- it has to be findable -------------------------------------------

    /**
     * The activation flow shipped reachable only from one line at the foot of
     * /members. It was live and correct and nobody could find it, which for a
     * payment page is the same as not shipping it. These assert the routes a
     * real visitor would actually take.
     */
    public function test_the_homepage_links_to_the_pricing_page(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString(route('member-services.show'), $html,
            'The homepage does not link to Member Services activation.');
    }

    public function test_the_homepage_shows_all_three_package_prices(): void
    {
        $text = preg_replace('/\s+/', ' ', strip_tags($this->get('/')->assertOk()->getContent()));

        foreach (['$249', '$349', '$449'] as $price) {
            $this->assertStringContainsString($price, $text, "The homepage does not show {$price}.");
        }
    }

    public function test_the_top_nav_carries_a_pricing_tab_on_every_public_page(): void
    {
        foreach (['/', '/members', '/become-a-host', '/properties', '/member-services'] as $url) {
            $this->assertStringContainsString(
                'data-track-cta="topnav_pricing"',
                $this->get($url)->assertOk()->getContent(),
                "{$url} has no Pricing tab in the top nav.",
            );
        }
    }

    public function test_the_footer_links_to_pricing(): void
    {
        $this->get('/')->assertOk()->assertSee('data-track-cta="footer_pricing"', false);
    }

    /**
     * The packages advertise no travel incentive.
     *
     * Complimentary restaurant, airfare and cruise offers were removed. A
     * "free cruise with purchase" is a regulated promotion in several states
     * and the FTC expects material redemption conditions disclosed alongside
     * the offer — advertising one with no published terms is a complaint the
     * platform cannot answer. What the packages sell is advertising.
     */
    public function test_no_package_surface_advertises_a_travel_incentive(): void
    {
        $forbidden = [
            'incentive', 'complimentary', 'cruise', 'airfare',
            'restaurant discount', 'bonus member benefit',
        ];

        foreach (['/', '/member-services'] as $url) {
            $text = strtolower(preg_replace('/\s+/', ' ',
                strip_tags($this->get($url)->assertOk()->getContent())));

            foreach ($forbidden as $phrase) {
                $this->assertStringNotContainsString($phrase, $text,
                    "{$url} advertises \"{$phrase}\" — the travel incentives are withdrawn.");
            }
        }
    }

    /**
     * All three tiers cover exactly one property.
     *
     * They differ by visibility — placement, presentation, featured status,
     * promotional exposure and support — not by listing count. The cards
     * previously said 1 / 2 / 3 properties, so this guards against the copy
     * drifting back to a multi-property promise the packages do not include.
     */
    public function test_every_package_covers_one_property(): void
    {
        foreach (MemberServicePackage::ordered() as $pkg) {
            $this->assertSame(1, $pkg->propertyCount(), "{$pkg->value} is not one property.");
            $this->assertSame('1 PROPERTY', $pkg->propertyAllowance());
        }

        foreach (['/', '/member-services'] as $url) {
            $text = strtolower(preg_replace('/\s+/', ' ',
                strip_tags($this->get($url)->assertOk()->getContent())));

            foreach (['up to 2 propert', 'up to 3 propert', 'multi-property'] as $phrase) {
                $this->assertStringNotContainsString($phrase, $text,
                    "{$url} still promises more than one property.");
            }
        }
    }

    public function test_the_quote_helper_matches_what_gets_charged(): void
    {
        $quote = app(MemberServiceOrderFactory::class)->quote(MemberServicePackage::Silver, 5);

        $this->assertSame(174500, $quote['total_cents']);
        $this->assertSame(34900, $quote['price_per_week_cents']);
    }
}

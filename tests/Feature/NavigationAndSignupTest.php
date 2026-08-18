<?php

namespace Tests\Feature;

use App\Models\Subscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for the unified top nav restructure: 5 fixed tabs (Stay → Become a
 * Host → Members → Signup → Login) wired across every public-facing branded
 * surface, plus the newsletter signup endpoint at /signup.
 */
class NavigationAndSignupTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_carries_unified_top_nav_with_all_five_tabs(): void
    {
        $body = $this->get('/')->assertOk()->getContent();

        // Brand link.
        $this->assertMatchesRegularExpression('/href="\/"\s+class="brand"/', $body);

        // Five required tabs (stay, become a host, members, signup, login).
        $this->assertStringContainsString('Stay</a>', $body);
        $this->assertStringContainsString('Become a Host', $body);
        $this->assertStringContainsString('Members', $body);
        $this->assertStringContainsString('Signup', $body);
        $this->assertStringContainsString('Login', $body);

        // Tracking on each so the funnel knows.
        $this->assertStringContainsString('data-track-cta="topnav_stay"', $body);
        $this->assertStringContainsString('data-track-cta="topnav_become_host"', $body);
        $this->assertStringContainsString('data-track-cta="topnav_members"', $body);
        $this->assertStringContainsString('data-track-cta="topnav_signup"', $body);
        $this->assertStringContainsString('data-track-cta="topnav_login"', $body);
    }

    public function test_become_a_host_page_renders_with_application_cta(): void
    {
        $resp = $this->get('/become-a-host');

        $resp->assertOk();
        $resp->assertSee('Your second home');
        $resp->assertSee('Start your application');

        // The stat block used to advertise a "3% host fee" and "$250K damage
        // cover". Both described a booking platform that takes a cut of a stay
        // and stands behind it. Vaytoven charges the host to advertise and
        // takes nothing from what the guest pays, so the headline number is 0%.
        $resp->assertSee('0%');
        $resp->assertSee('COMMISSION ON WHAT YOU EARN');
        $resp->assertDontSee('3%');
        $resp->assertDontSee('$250K');

        $resp->assertSee(route('host.onboarding.index'));
    }

    public function test_become_a_host_page_lists_onboarding_steps(): void
    {
        $body = $this->get('/become-a-host')->assertOk()->getContent();

        // Step headings. "Verify identity (payout enrollment)" and "first
        // booking" are gone: Vaytoven advertises listings, so there is no
        // payout to enrol for and travelers send offers rather than bookings.
        //
        // "Concierge buildout" is gone too. Hosts are on the 30-day
        // subscription and create their own listings; the managed buildout is
        // the separate 180-day member program.
        $this->assertStringContainsString('Apply</h3>', $body);
        $this->assertStringContainsString('Start your 30-day subscription', $body);
        $this->assertStringContainsString('Build your listing', $body);
        $this->assertStringContainsString('Go live + first offers', $body);

        $this->assertStringNotContainsString('payout enrollment', $body);
        $this->assertStringNotContainsString('Concierge buildout', $body);
    }

    public function test_members_page_renders_with_program_details_and_faqs(): void
    {
        $resp = $this->get('/members');

        $resp->assertOk();
        $resp->assertSee('Managed Listing Program');
        // "Turn unused points into real income" read like cashing in a loyalty
        // balance; "weeks" replaced it, and "time" replaced that. The point of
        // the current wording is that nothing on the page frames this as a
        // points programme or a rental.
        $resp->assertSee("Turn the time you don't use into", false);
        $resp->assertSee('Member program FAQs');

        // The member program is a ONE-TIME fee for a 180-day term. It was
        // previously advertised here as "$200–$800 per week + a subscription
        // fee", which is a recurring charge and contradicts the agreement.
        $resp->assertSee('180-day');
        $resp->assertSee('one-time fee', false);
        $resp->assertDontSee('upfront weekly');
        $resp->assertDontSee('per week');
    }

    /**
     * The inverse of what this test used to assert.
     *
     * It required the page to name Marriott, Hilton, Disney, Wyndham, RCI and
     * Interval. Naming other companies' programmes made the page read as a
     * points-club rental service, which is not what Vaytoven sells and is not
     * a positioning it wants. Vaytoven advertises vacation properties.
     *
     * Kept as a test rather than deleted, because a brand name is exactly the
     * kind of thing that creeps back in one bullet at a time.
     */
    public function test_the_members_page_names_no_club_brands_and_no_points(): void
    {
        $body = $this->get('/members')->assertOk()->getContent();

        foreach (['Marriott', 'Hilton', 'Disney', 'Wyndham', 'RCI', 'Interval',
                  'Worldmark', 'Bluegreen', 'Westgate', 'Hyatt', 'Diamond Resorts'] as $brand) {
            $this->assertStringNotContainsString($brand, $body, "Brand name on the page: {$brand}");
        }

        foreach (['points', 'Points', 'vacation club', 'Vacation Club'] as $phrase) {
            $this->assertStringNotContainsString($phrase, $body, "Club/points wording on the page: {$phrase}");
        }
    }

    public function test_signup_page_renders_newsletter_form(): void
    {
        $resp = $this->get('/signup');

        $resp->assertOk();
        $resp->assertSee('Get early access');
        $resp->assertSee('full_name');
        $resp->assertSee('Subscribe');
    }

    public function test_signup_persists_subscriber_with_idempotency(): void
    {
        $payload = [
            'full_name' => 'E2E Subscriber',
            'email'     => 'subscriber@example.test',
            'phone'     => '+1 555 555 0123',
        ];

        $this->withHeaders(['Referer' => 'https://www.vaytoven.com/'])
            ->post('/signup', $payload)
            ->assertRedirect(route('signup.show'))
            ->assertSessionHas('subscriber_success');

        $row = Subscriber::sole();
        $this->assertSame('subscriber@example.test', $row->email);
        $this->assertSame('+1 555 555 0123', $row->phone);
        $this->assertSame(Subscriber::STATUS_ACTIVE, $row->status);
        $this->assertSame('https://www.vaytoven.com/', $row->source_url);

        // Re-submitting the same email must not create a duplicate row.
        $this->post('/signup', array_merge($payload, ['phone' => '+1 555 555 9999']))->assertRedirect();
        $this->assertSame(1, Subscriber::count());
        $this->assertSame('+1 555 555 9999', Subscriber::sole()->phone);
    }

    public function test_signup_normalises_email_case_for_dedup(): void
    {
        $this->post('/signup', [
            'full_name' => 'Mixed Case',
            'email'     => 'Mixed@Case.Test',
        ])->assertRedirect();
        $this->post('/signup', [
            'full_name' => 'Mixed Case Again',
            'email'     => 'mixed@case.test',
        ])->assertRedirect();

        $this->assertSame(1, Subscriber::count());
        $this->assertSame('mixed@case.test', Subscriber::sole()->email);
    }

    public function test_signup_rejects_missing_required_fields(): void
    {
        $this->post('/signup', [])->assertSessionHasErrors(['full_name', 'email']);
        $this->assertSame(0, Subscriber::count());
    }

    public function test_signup_rejects_invalid_email(): void
    {
        $this->post('/signup', [
            'full_name' => 'Bad Email',
            'email'     => 'not-an-email',
        ])->assertSessionHasErrors('email');
        $this->assertSame(0, Subscriber::count());
    }

    public function test_property_index_uses_unified_top_nav(): void
    {
        $body = $this->get('/properties')->assertOk()->getContent();

        $this->assertStringContainsString('vyt-topnav', $body);
        $this->assertStringContainsString('Become a Host', $body);
        $this->assertStringContainsString('Signup', $body);
    }

    public function test_help_index_uses_unified_top_nav(): void
    {
        $body = $this->get('/help')->assertOk()->getContent();

        $this->assertStringContainsString('vyt-topnav', $body);
        $this->assertStringContainsString('Become a Host', $body);
        $this->assertStringContainsString('Signup', $body);
    }

    public function test_legal_pages_use_unified_top_nav(): void
    {
        $body = $this->get('/legal/tos')->assertOk()->getContent();

        $this->assertStringContainsString('vyt-topnav', $body);
        $this->assertStringContainsString('Become a Host', $body);
    }
}

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
        // "Concierge buildout" is gone too. Hosts are on the monthly
        // subscription and build their own listing; the managed buildout is
        // the separate 180-day member program.
        $this->assertStringContainsString('Apply</h3>', $body);
        $this->assertStringContainsString('Start your monthly subscription', $body);
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
        // balance. The inventory is weeks the member already owns.
        $resp->assertSee("Turn the weeks you don't use into", false);
        $resp->assertSee('Member program FAQs');

        // The member program is a ONE-TIME fee for a 180-day term. It was
        // previously advertised here as "$200–$800 per week + a subscription
        // fee", which is a recurring charge and contradicts the agreement.
        $resp->assertSee('180-day');
        $resp->assertSee('one-time fee', false);
        $resp->assertDontSee('upfront weekly');
        $resp->assertDontSee('per week');
    }

    public function test_members_page_lists_eligible_clubs(): void
    {
        $body = $this->get('/members')->assertOk()->getContent();

        // The clubs grid covers the major points-based programs.
        foreach (['Marriott', 'Hilton', 'Disney', 'Wyndham', 'RCI', 'Interval'] as $club) {
            $this->assertStringContainsString($club, $body, "Expected club: {$club}");
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

<?php

namespace Tests\Feature\Site;

use App\Enums\PropertyStatus;
use App\Enums\UserRole;
use App\Models\ContactMessage;
use App\Models\JobApplication;
use App\Models\JobOpening;
use App\Models\PressRelease;
use App\Models\Property;
use App\Models\SupportTicket;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The pages that replaced the footer's `href="#"` links.
 *
 * The requirement was explicitly not "give each dead link a URL" — so these
 * tests check the destination has the right CONTENT and that the functionality
 * behind it actually works, not merely that the route returns 200.
 */
class SitePagesTest extends TestCase
{
    use RefreshDatabase;

    /** Every new page must render. */
    public function test_all_replacement_pages_render(): void
    {
        foreach ([
            'destinations.index', 'mobile-app', 'about', 'host-resources.index',
            'earnings-calculator', 'contact.show', 'trip-support.show',
            'careers.index', 'press.index',
        ] as $route) {
            $this->get(route($route))->assertOk();
        }
    }

    // --- No placeholder links anywhere -----------------------------------

    public function test_no_public_page_contains_a_placeholder_link(): void
    {
        $offenders = [];

        foreach ([
            '/', '/properties', '/help', '/destinations', '/mobile-app', '/about',
            '/host-resources', '/earnings-calculator', '/contact', '/trip-support',
            '/careers', '/press', '/become-a-host', '/members', '/login', '/register',
            '/legal/tos', '/legal/privacy',
        ] as $path) {
            $html = $this->get($path)->getContent();

            if (preg_match('/href\s*=\s*"#"/', $html) || preg_match('/href\s*=\s*""/', $html)) {
                $offenders[] = $path;
            }
        }

        $this->assertSame([], $offenders,
            'These pages still contain href="#" or an empty href.');
    }

    public function test_the_footer_links_resolve_to_real_routes(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        foreach ([
            '/destinations', '/properties', '/mobile-app', '/help', '/trip-support',
            '/become-a-host', '/members', '/host-resources', '/earnings-calculator',
            '/about', '/careers', '/press', '/contact',
        ] as $path) {
            $this->assertStringContainsString($path, $html, "Footer is missing a link to {$path}");
            $this->get($path)->assertOk();
        }
    }

    // --- Destinations are derived from real listings ----------------------

    public function test_destinations_are_built_from_published_listings_only(): void
    {
        Property::factory()->create(['city' => 'Zermatt', 'country' => 'Switzerland', 'status' => PropertyStatus::Active->value]);
        Property::factory()->create(['city' => 'Hidden Bay', 'country' => 'Nowhere', 'status' => PropertyStatus::Draft->value]);

        $this->get('/destinations')
            ->assertOk()
            ->assertSee('Zermatt')
            // A destination with no live listing must never be advertised.
            ->assertDontSee('Hidden Bay');
    }

    public function test_destination_search_filters(): void
    {
        Property::factory()->create(['city' => 'Zermatt', 'status' => PropertyStatus::Active->value]);
        Property::factory()->create(['city' => 'Lisbon', 'status' => PropertyStatus::Active->value]);

        $this->get('/destinations?q=Zerm')->assertOk()->assertSee('Zermatt')->assertDontSee('Lisbon');
    }

    // --- Contact: a form that actually persists ---------------------------

    public function test_contact_form_persists_and_returns_a_reference(): void
    {
        $response = $this->post('/contact', [
            'first_name' => 'Dana', 'last_name' => 'Reed',
            'email' => 'dana@example.com', 'phone' => '+1 555 010 2030',
            'department' => 'billing', 'subject' => 'Invoice question',
            'message' => 'I was charged twice for the same booking last week.',
        ]);

        $message = ContactMessage::query()->sole();

        $response->assertRedirect(route('contact.show'))
            ->assertSessionHas('contact_reference', $message->reference);

        $this->assertSame('billing', $message->department->value);
        $this->assertNotNull($message->ip);
        $this->assertStringStartsWith('VYT-C-', $message->reference);
        $this->assertSame(ContactMessage::STATUS_NEW, $message->status);
    }

    public function test_contact_form_rejects_incomplete_submissions(): void
    {
        $this->post('/contact', ['first_name' => 'Dana'])
            ->assertSessionHasErrors(['last_name', 'email', 'department', 'subject', 'message']);

        $this->assertSame(0, ContactMessage::query()->count());
    }

    // --- Trip Support: a real ticket in the real queue --------------------

    public function test_trip_support_raises_a_ticket_with_a_reference(): void
    {
        $response = $this->post('/trip-support', [
            'name' => 'Sam Ito', 'email' => 'sam@example.com',
            'category' => 'booking', 'property_reference' => 'VYT-BK-9911',
            'subject' => 'Cannot check in', 'message' => 'The door code from the host is not working.',
        ]);

        $ticket = SupportTicket::query()->sole();

        $response->assertRedirect(route('trip-support.show'))
            ->assertSessionHas('support_reference', $ticket->reference);

        $this->assertSame(SupportTicket::SOURCE_TRIP_SUPPORT, $ticket->source);
        $this->assertSame('open', $ticket->status);
        // Booking problems are someone mid-trip — they must not queue normally.
        $this->assertSame('high', $ticket->priority);
        $this->assertStringStartsWith('VYT-S-', $ticket->reference);
    }

    public function test_trip_support_prefills_from_the_signed_in_account(): void
    {
        $user = User::factory()->create(['name' => 'Ada Reed', 'email' => 'ada@example.com']);

        $this->actingAs($user)->post('/trip-support', [
            'category' => 'account', 'subject' => 'Locked out',
            'message' => 'Two-factor codes are not arriving on my phone.',
        ])->assertRedirect();

        $ticket = SupportTicket::query()->sole();

        $this->assertSame('ada@example.com', $ticket->contact_email);
        $this->assertSame($user->id, $ticket->opened_by_user_id);
    }

    // --- Careers: honest empty state, real applications -------------------

    public function test_careers_shows_the_honest_empty_state_when_no_roles_are_published(): void
    {
        $this->get('/careers')
            ->assertOk()
            ->assertSee('There are currently no open positions. Please check back for future opportunities.');
    }

    public function test_careers_lists_published_roles_and_hides_drafts(): void
    {
        JobOpening::create([
            'title' => 'Senior Laravel Engineer', 'department' => 'Engineering',
            'location' => 'Remote (US)', 'employment_type' => 'full_time',
            'summary' => 'Own the listings platform end to end.',
            'description' => 'You will work across the listing and offers stack.',
            'is_published' => true,
        ]);
        JobOpening::create([
            'title' => 'Secret Draft Role', 'department' => 'Ops',
            'location' => 'Remote', 'employment_type' => 'contract',
            'summary' => 'Not ready yet.', 'description' => 'Draft.',
            'is_published' => false,
        ]);

        $this->get('/careers')->assertOk()
            ->assertSee('Senior Laravel Engineer')
            ->assertDontSee('Secret Draft Role');
    }

    public function test_a_closed_role_is_not_reachable_by_url(): void
    {
        $job = JobOpening::create([
            'title' => 'Closed Role', 'department' => 'Ops', 'location' => 'Remote',
            'employment_type' => 'full_time', 'summary' => 's', 'description' => 'd',
            'is_published' => true, 'closed_at' => now()->subDay(),
        ]);

        $this->get(route('careers.show', $job))->assertNotFound();
    }

    public function test_a_job_application_is_recorded(): void
    {
        $job = JobOpening::create([
            'title' => 'Support Specialist', 'department' => 'Support', 'location' => 'Remote',
            'employment_type' => 'full_time', 'summary' => 's', 'description' => 'd',
            'is_published' => true,
        ]);

        $this->post(route('careers.apply', $job), [
            'first_name' => 'Lee', 'last_name' => 'Ng', 'email' => 'lee@example.com',
            'cover_note' => 'I have run support desks for five years.',
        ])->assertRedirect(route('careers.show', $job));

        $application = JobApplication::query()->sole();

        $this->assertSame($job->id, $application->job_opening_id);
        $this->assertStringStartsWith('VYT-J-', $application->reference);
    }

    // --- Press: no invented coverage --------------------------------------

    public function test_press_is_empty_until_something_is_published(): void
    {
        $this->get('/press')->assertOk()->assertSee('No announcements have been published yet.');
    }

    public function test_press_shows_live_releases_and_hides_future_dated_ones(): void
    {
        PressRelease::create([
            'title' => 'Vaytoven launches offer tracking', 'excerpt' => 'Offers now expire in 24 hours.',
            'body' => 'Full text.', 'is_published' => true, 'published_at' => now()->subDay(),
        ]);
        PressRelease::create([
            'title' => 'Embargoed announcement', 'excerpt' => 'Not yet.',
            'body' => 'Full text.', 'is_published' => true, 'published_at' => now()->addWeek(),
        ]);

        $this->get('/press')->assertOk()
            ->assertSee('Vaytoven launches offer tracking')
            ->assertDontSee('Embargoed announcement');
    }

    // --- Calculator carries the required disclaimer -----------------------

    public function test_earnings_calculator_shows_the_no_guarantee_disclaimer(): void
    {
        $html = $this->get('/earnings-calculator')->assertOk()->getContent();

        // Collapse whitespace first: the disclaimer wraps across several lines
        // in the template, so a literal assertSee would be checking the
        // indentation rather than the wording.
        $text = preg_replace('/\s+/', ' ', strip_tags($html));

        $this->assertStringContainsString(
            'Estimates are provided for informational purposes only.',
            $text,
        );
        $this->assertStringContainsString(
            'Vaytoven Technologies LLC does not guarantee earnings, occupancy, rental income, property sales, or financial results.',
            $text,
        );
    }

    // --- Admin can actually see what the forms produce --------------------

    public function test_submissions_are_visible_in_the_admin_inbox(): void
    {
        $this->seed(RbacSeeder::class);

        $this->post('/contact', [
            'first_name' => 'Dana', 'last_name' => 'Reed', 'email' => 'dana@example.com',
            'department' => 'general', 'subject' => 'Hello there',
            'message' => 'Just checking the contact form works end to end.',
        ]);

        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get('/admin/inbox?tab=contact')
            ->assertOk()
            ->assertSee('Hello there')
            ->assertSee('dana@example.com');
    }

    public function test_a_user_without_inbox_permission_cannot_read_submissions(): void
    {
        $this->seed(RbacSeeder::class);
        $traveler = User::factory()->create(['role' => UserRole::Traveler]);

        $this->actingAs($traveler)->get('/admin/inbox')->assertForbidden();
    }
}

<?php

namespace Tests\Feature\Bookings;

use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Vaytoven does not take bookings, and there must be no way back in.
 *
 * The checkout used to live behind a `stay.checkout` feature flag that
 * defaulted to off. That was never enough: a flag is a door, an operator can
 * open it from the admin console, and everything behind it — reservation
 * records, guest charges, refund policies, payout figures — described a
 * regulated relationship the company does not have. The whole product is gone
 * rather than disabled.
 *
 * These tests fail if any of it comes back, including by someone re-adding a
 * setting and flipping it on.
 */
class NoBookingProductTest extends TestCase
{
    use RefreshDatabase;

    /** Every URL the booking product ever answered on. */
    private const RETIRED_WEB_PATHS = [
        '/account/bookings',
        '/bookings/1',
        '/bookings/1/cancel',
        '/bookings/1/pay',
        '/properties/1/book',
    ];

    private const RETIRED_API_PATHS = [
        '/api/v1/bookings',
        '/api/v1/bookings/1',
    ];

    public function test_no_booking_route_is_registered(): void
    {
        foreach (Route::getRoutes() as $route) {
            $this->assertStringNotContainsString('booking', strtolower($route->uri()),
                "A booking route is registered: {$route->uri()}");

            $this->assertStringNotContainsString('/book', strtolower($route->uri()),
                "A booking route is registered: {$route->uri()}");
        }
    }

    public function test_the_retired_web_paths_are_gone_for_a_signed_in_user(): void
    {
        $user = User::factory()->create();

        foreach (self::RETIRED_WEB_PATHS as $path) {
            $this->actingAs($user)->get($path)->assertNotFound();
        }
    }

    /**
     * Signed out too, and specifically NOT a redirect to /login.
     *
     * A 302 to the login page would mean the route still exists and is merely
     * gated, which is the state this change exists to end.
     */
    public function test_the_retired_web_paths_are_gone_for_a_visitor(): void
    {
        foreach (self::RETIRED_WEB_PATHS as $path) {
            $this->get($path)->assertNotFound();
        }
    }

    /**
     * The API mattered as much as the web flow: POST /api/v1/bookings created
     * a reservation with any authenticated user token.
     */
    public function test_the_booking_api_is_gone_even_with_a_valid_token(): void
    {
        Sanctum::actingAs(User::factory()->create());

        foreach (self::RETIRED_API_PATHS as $path) {
            $this->getJson($path)->assertNotFound();
        }

        $this->postJson('/api/v1/bookings', [
            'property_id' => 1,
            'check_in_date' => now()->addDays(5)->toDateString(),
            'check_out_date' => now()->addDays(7)->toDateString(),
        ])->assertNotFound();
    }

    /** The property page offers a submission, never a reservation. */
    public function test_a_listing_page_offers_no_way_to_book(): void
    {
        $property = Property::factory()->create();
        $traveler = User::factory()->create(['role' => \App\Enums\UserRole::Traveler]);

        // Both paths: the form itself only renders for a signed-in non-owner,
        // so checking only the guest view would miss the CTA entirely.
        $guestHtml = $this->get(route('properties.show', $property))->assertOk()->getContent();
        $authedHtml = $this->actingAs($traveler)
            ->get(route('properties.show', $property))->assertOk()->getContent();

        foreach (['guest' => $guestHtml, 'signed in' => $authedHtml] as $who => $html) {
            $this->assertStringNotContainsString('/book', $html, "The {$who} view links to a booking URL.");
            $this->assertStringNotContainsStringIgnoringCase('book now', $html, "The {$who} view says Book Now.");
        }

        // A visitor is invited to sign in and offer; a signed-in traveler gets
        // the form. Neither is offered a reservation.
        $this->assertStringContainsStringIgnoringCase('submit an offer', $guestHtml);
        $this->assertStringContainsStringIgnoringCase('offer', $authedHtml);
        $this->assertStringContainsString(route('offers.store', $property), $authedHtml);
    }

    /**
     * No setting may resurrect it. `booking.stay_checkout_enabled` is gone
     * from the catalog, so an operator cannot turn checkout back on from the
     * admin console.
     */
    public function test_no_stay_checkout_setting_remains_in_the_catalog(): void
    {
        $keys = array_keys(\App\Services\Settings\SettingsSchema::catalog());

        foreach ($keys as $key) {
            $this->assertStringNotContainsString('stay_checkout', $key,
                "The stay checkout setting is still in the catalog: {$key}");
        }
    }

    /**
     * The assistant must not offer to look up a booking. Given a
     * get_booking_status tool it will happily invent the product in the most
     * convincing possible voice.
     */
    public function test_the_support_assistant_has_no_booking_tools(): void
    {
        $registry = new \App\Services\SupportChat\Tools\ToolRegistry(null);

        foreach ($registry->definitions() as $tool) {
            $this->assertStringNotContainsString('booking', $tool['name']);
            $this->assertStringNotContainsString('charge', $tool['name']);
        }
    }
}

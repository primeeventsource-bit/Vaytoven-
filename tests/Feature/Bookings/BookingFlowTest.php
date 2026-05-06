<?php

namespace Tests\Feature\Bookings;

use App\Enums\BookingStatus;
use App\Enums\PropertyStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Property;
use App\Models\TermsAcceptance;
use App\Models\User;
use App\Services\Legal\LegalDocumentRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Materialise legal versions and pre-accept on test users so the
        // terms.current middleware doesn't intercept booking flow tests.
        app(LegalDocumentRegistry::class)->materialiseAll();
    }

    public function test_unauthenticated_review_request_redirects_to_login(): void
    {
        $property = Property::factory()->create(['status' => PropertyStatus::Active->value]);

        $this->get(route('bookings.review', $property))->assertRedirect(route('login'));
    }

    public function test_review_redirects_back_when_dates_missing(): void
    {
        $traveler = $this->makeTraveler();
        $property = Property::factory()->create(['status' => PropertyStatus::Active->value]);

        $this->actingAs($traveler)
            ->get(route('bookings.review', $property))
            ->assertRedirect(route('properties.show', $property));
    }

    public function test_review_redirects_back_when_under_minimum_nights(): void
    {
        $traveler = $this->makeTraveler();
        $property = Property::factory()->create([
            'status' => PropertyStatus::Active->value,
            'minimum_nights' => 5,
        ]);

        // 1-night stay against a 5-night minimum
        $this->actingAs($traveler)
            ->get(route('bookings.review', $property).'?check_in='.now()->addDay()->toDateString().'&check_out='.now()->addDays(2)->toDateString().'&guests=2')
            ->assertRedirect(route('properties.show', $property))
            ->assertSessionHas('booking_error');
    }

    public function test_review_renders_with_price_breakdown_when_dates_valid(): void
    {
        $traveler = $this->makeTraveler();
        $property = Property::factory()->create([
            'status'             => PropertyStatus::Active->value,
            'base_nightly_cents' => 20000, // $200/night
            'cleaning_fee_cents' => 5000,
            'minimum_nights'     => 1,
            'capacity'           => 4,
        ]);

        $checkIn  = now()->addDays(7)->toDateString();
        $checkOut = now()->addDays(10)->toDateString();

        $resp = $this->actingAs($traveler)
            ->get(route('bookings.review', $property)."?check_in={$checkIn}&check_out={$checkOut}&guests=2");

        $resp->assertOk();
        $resp->assertSee('Confirm your stay');
        $resp->assertSee('$200', false);              // nightly rate
        $resp->assertSee('Confirm booking');
    }

    public function test_store_persists_booking_and_redirects_to_show(): void
    {
        $traveler = $this->makeTraveler();
        $property = Property::factory()->create([
            'status'             => PropertyStatus::Active->value,
            'base_nightly_cents' => 15000,
            'cleaning_fee_cents' => 0,
            'minimum_nights'     => 1,
            'capacity'           => 6,
        ]);

        $checkIn  = now()->addDays(7)->toDateString();
        $checkOut = now()->addDays(9)->toDateString();

        $this->actingAs($traveler)
            ->post(route('bookings.store', $property), [
                'check_in'  => $checkIn,
                'check_out' => $checkOut,
                'guests'    => 2,
            ])
            ->assertRedirect();

        $booking = Booking::sole();
        $this->assertSame($traveler->id, $booking->traveler_id);
        $this->assertSame($property->id, $booking->property_id);
        $this->assertSame(BookingStatus::PendingPayment, $booking->status);
        $this->assertSame(2, $booking->nights);
    }

    public function test_store_returns_to_property_when_dates_overlap(): void
    {
        $traveler = $this->makeTraveler();
        $property = Property::factory()->create([
            'status'             => PropertyStatus::Active->value,
            'base_nightly_cents' => 10000,
            'cleaning_fee_cents' => 0,
            'minimum_nights'     => 1,
        ]);

        $checkIn  = now()->addDays(7)->toDateString();
        $checkOut = now()->addDays(10)->toDateString();

        // Pre-existing booking covers the window.
        Booking::factory()->create([
            'property_id'    => $property->id,
            'check_in_date'  => $checkIn,
            'check_out_date' => $checkOut,
            'status'         => BookingStatus::Confirmed->value,
        ]);

        $this->actingAs($traveler)
            ->post(route('bookings.store', $property), [
                'check_in'  => $checkIn,
                'check_out' => $checkOut,
                'guests'    => 2,
            ])
            ->assertRedirect(route('properties.show', $property))
            ->assertSessionHas('booking_error');

        // No new booking row — the existing factory-created one stays at 1.
        $this->assertSame(1, Booking::count());
    }

    public function test_show_404s_for_other_users_booking(): void
    {
        $owner = $this->makeTraveler();
        $other = $this->makeTraveler();

        $booking = Booking::factory()->create([
            'traveler_id' => $owner->id,
        ]);

        $this->actingAs($other)
            ->get(route('bookings.show', $booking))
            ->assertNotFound();
    }

    public function test_show_renders_for_owner_and_displays_demo_mode_banner(): void
    {
        $traveler = $this->makeTraveler();
        $booking = Booking::factory()->create([
            'traveler_id' => $traveler->id,
        ]);

        $resp = $this->actingAs($traveler)
            ->get(route('bookings.show', $booking));

        $resp->assertOk();
        $resp->assertSee($booking->confirmation_code);
        $resp->assertSee('Demo mode');
    }

    public function test_property_show_form_posts_to_booking_review_route(): void
    {
        $property = Property::factory()->create(['status' => PropertyStatus::Active->value]);

        $body = $this->get(route('properties.show', $property))->assertOk()->getContent();

        $this->assertStringContainsString('action="'.route('bookings.review', $property), $body);
        $this->assertStringContainsString('Continue to review', $body);
    }

    private function makeTraveler(): User
    {
        $user = User::factory()->create([
            'role' => UserRole::Traveler,
            'email_verified_at' => now(),
        ]);
        foreach (app(LegalDocumentRegistry::class)->registrationRequired() as $version) {
            TermsAcceptance::create([
                'user_id' => $user->id,
                'terms_version_id' => $version->id,
                'accepted_at' => now(),
            ]);
        }
        return $user;
    }
}

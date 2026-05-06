<?php

namespace Tests\Feature\Api;

use App\Enums\BookingStatus;
use App\Enums\PropertyStatus;
use App\Models\Booking;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingApiTest extends TestCase
{
    use RefreshDatabase;

    private function actAsTraveler(): User
    {
        $user = User::factory()->create();
        $token = $user->createToken('phpunit')->plainTextToken;
        $this->withHeader('Authorization', "Bearer {$token}");

        return $user;
    }

    public function test_store_creates_booking_with_snapshotted_pricing(): void
    {
        $this->actAsTraveler();
        $property = Property::factory()->create([
            'status' => PropertyStatus::Active->value,
            'base_nightly_cents' => 10000,    // $100/night
            'cleaning_fee_cents' => 5000,      // $50
        ]);

        $resp = $this->postJson('/api/v1/bookings', [
            'property_id' => $property->id,
            'check_in_date' => now()->addWeek()->toDateString(),
            'check_out_date' => now()->addWeek()->addDays(3)->toDateString(),
            'guests' => 2,
        ]);

        $resp->assertCreated()
            ->assertJsonPath('data.property_id', $property->id)
            ->assertJsonPath('data.nights', 3)
            ->assertJsonPath('data.nightly_rate_cents', 10000)
            ->assertJsonPath('data.subtotal_cents', 30000)
            ->assertJsonPath('data.cleaning_fee_cents', 5000)
            ->assertJsonPath('data.status', BookingStatus::PendingPayment->value);

        $this->assertMatchesRegularExpression(
            '/^VYT-[A-Z0-9]{6}$/',
            $resp->json('data.confirmation_code')
        );
    }

    public function test_store_rejects_overlapping_booking_with_409(): void
    {
        $this->actAsTraveler();
        $property = Property::factory()->create(['status' => PropertyStatus::Active->value]);

        // First booking blocks 2026-12-01..12-08
        Booking::factory()->create([
            'property_id' => $property->id,
            'check_in_date' => '2026-12-01',
            'check_out_date' => '2026-12-08',
            'status' => BookingStatus::Confirmed->value,
        ]);

        // Second tries to book 2026-12-05..12-10 — overlaps middle 3 days.
        $resp = $this->postJson('/api/v1/bookings', [
            'property_id' => $property->id,
            'check_in_date' => '2026-12-05',
            'check_out_date' => '2026-12-10',
            'guests' => 2,
        ]);

        $resp->assertStatus(409)
            ->assertJsonPath('error', 'booking_conflict');
    }

    public function test_store_allows_booking_after_existing_checkout(): void
    {
        $this->actAsTraveler();
        $property = Property::factory()->create(['status' => PropertyStatus::Active->value]);

        Booking::factory()->create([
            'property_id' => $property->id,
            'check_in_date' => '2026-12-01',
            'check_out_date' => '2026-12-08',
            'status' => BookingStatus::Confirmed->value,
        ]);

        // Back-to-back: starts on the day the prior booking ends. NOT an overlap.
        $resp = $this->postJson('/api/v1/bookings', [
            'property_id' => $property->id,
            'check_in_date' => '2026-12-08',
            'check_out_date' => '2026-12-12',
            'guests' => 2,
        ]);

        $resp->assertCreated();
    }

    public function test_store_ignores_cancelled_bookings_when_checking_overlap(): void
    {
        $this->actAsTraveler();
        $property = Property::factory()->create(['status' => PropertyStatus::Active->value]);

        Booking::factory()->cancelled()->create([
            'property_id' => $property->id,
            'check_in_date' => '2026-12-01',
            'check_out_date' => '2026-12-08',
        ]);

        // Cancelled bookings shouldn't block new ones on the same dates.
        $resp = $this->postJson('/api/v1/bookings', [
            'property_id' => $property->id,
            'check_in_date' => '2026-12-03',
            'check_out_date' => '2026-12-06',
            'guests' => 2,
        ]);

        $resp->assertCreated();
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actAsTraveler();

        $this->postJson('/api/v1/bookings', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['property_id', 'check_in_date', 'check_out_date', 'guests']);
    }

    public function test_store_requires_authentication(): void
    {
        $this->postJson('/api/v1/bookings', [
            'property_id' => 1,
            'check_in_date' => now()->addWeek()->toDateString(),
            'check_out_date' => now()->addWeek()->addDays(2)->toDateString(),
            'guests' => 2,
        ])->assertStatus(401);
    }

    public function test_index_only_returns_authenticated_users_bookings(): void
    {
        $traveler = $this->actAsTraveler();
        $other = User::factory()->create();

        Booking::factory()->count(2)->create(['traveler_id' => $traveler->id]);
        Booking::factory()->count(3)->create(['traveler_id' => $other->id]);

        $resp = $this->getJson('/api/v1/bookings');

        $resp->assertOk();
        $this->assertCount(2, $resp->json('data'));
    }

    public function test_show_404s_when_accessing_another_users_booking(): void
    {
        $this->actAsTraveler();
        $other = User::factory()->create();
        $booking = Booking::factory()->create(['traveler_id' => $other->id]);

        $this->getJson("/api/v1/bookings/{$booking->id}")->assertNotFound();
    }

    public function test_cancel_transitions_status_and_records_audit_row(): void
    {
        $traveler = $this->actAsTraveler();
        $booking = Booking::factory()->confirmed()->create(['traveler_id' => $traveler->id]);

        $resp = $this->postJson("/api/v1/bookings/{$booking->id}/cancel", [
            'reason' => 'plans_changed',
        ]);

        $resp->assertOk()->assertJsonPath('data.status', BookingStatus::Cancelled->value);

        $booking->refresh();
        $this->assertSame(BookingStatus::Cancelled, $booking->status);
        $this->assertSame('plans_changed', $booking->cancelled_reason);
        // Initial creation row + the cancel transition = 2.
        $this->assertCount(2, $booking->stateTransitions);
    }

    public function test_cancel_rejects_when_already_cancelled(): void
    {
        $traveler = $this->actAsTraveler();
        $booking = Booking::factory()->cancelled()->create(['traveler_id' => $traveler->id]);

        $this->postJson("/api/v1/bookings/{$booking->id}/cancel")->assertStatus(422);
    }
}

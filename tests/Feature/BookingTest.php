<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_belongs_to_property_and_traveler(): void
    {
        $property = Property::factory()->create();
        $traveler = User::factory()->create();

        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'traveler_id' => $traveler->id,
        ]);

        $this->assertTrue($booking->property->is($property));
        $this->assertTrue($booking->traveler->is($traveler));
    }

    public function test_confirmation_code_format(): void
    {
        $booking = Booking::factory()->create();

        $this->assertMatchesRegularExpression('/^VYT-[A-Z0-9]{6}$/', $booking->confirmation_code);
    }

    public function test_confirmation_code_is_unique(): void
    {
        $bookings = Booking::factory()->count(50)->create();

        $codes = $bookings->pluck('confirmation_code')->unique();
        $this->assertCount(50, $codes);
    }

    public function test_money_fields_are_integer_cents(): void
    {
        $booking = Booking::factory()->create([
            'nightly_rate_cents' => 12500,
            'nights' => 4,
            'subtotal_cents' => 50000,
            'cleaning_fee_cents' => 7500,
            'service_fee_cents' => 6000,
            'tax_cents' => 5080,
            'total_cents' => 68580,
        ]);

        $this->assertSame(12500, $booking->nightly_rate_cents);
        $this->assertSame(68580, $booking->total_cents);
        $this->assertIsInt($booking->total_cents);
    }

    public function test_status_casts_to_enum(): void
    {
        $booking = Booking::factory()->create(['status' => BookingStatus::Confirmed->value]);

        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertTrue($booking->status->isActive());
    }

    public function test_initial_state_transition_is_recorded_on_create(): void
    {
        $booking = Booking::factory()->create();

        $this->assertCount(1, $booking->stateTransitions);
        $first = $booking->stateTransitions->first();
        $this->assertNull($first->from_state);
        $this->assertSame(BookingStatus::PendingPayment->value, $first->to_state);
    }

    public function test_transition_to_records_audit_row_and_updates_status(): void
    {
        $booking = Booking::factory()->create();
        $admin = User::factory()->create();

        $booking->transitionTo(BookingStatus::Confirmed, $admin->id);
        $booking->refresh();

        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertCount(2, $booking->fresh()->stateTransitions);

        $latest = $booking->stateTransitions->last();
        $this->assertSame(BookingStatus::PendingPayment->value, $latest->from_state);
        $this->assertSame(BookingStatus::Confirmed->value, $latest->to_state);
        $this->assertSame($admin->id, $latest->actor_user_id);
    }

    public function test_cancelling_sets_cancelled_at_and_reason(): void
    {
        $booking = Booking::factory()->create();

        $booking->transitionTo(BookingStatus::Cancelled, null, 'host_cancelled_force_majeure');
        $booking->refresh();

        $this->assertSame(BookingStatus::Cancelled, $booking->status);
        $this->assertNotNull($booking->cancelled_at);
        $this->assertSame('host_cancelled_force_majeure', $booking->cancelled_reason);
    }

    public function test_unique_constraint_blocks_identical_date_overlap(): void
    {
        $property = Property::factory()->create();

        Booking::factory()->create([
            'property_id' => $property->id,
            'check_in_date' => '2026-12-01',
            'check_out_date' => '2026-12-08',
        ]);

        $this->expectException(QueryException::class);

        Booking::factory()->create([
            'property_id' => $property->id,
            'check_in_date' => '2026-12-01',
            'check_out_date' => '2026-12-08',
        ]);
    }
}

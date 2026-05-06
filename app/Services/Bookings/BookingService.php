<?php

namespace App\Services\Bookings;

use App\Enums\BookingStatus;
use App\Exceptions\BookingConflictException;
use App\Models\Booking;
use App\Models\Property;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for creating bookings (FR-3.1).
 *
 * Overlap prevention strategy:
 *   1. Wrap creation in a DB transaction.
 *   2. lockForUpdate() the property row (acquires an InnoDB row-level lock on MySQL,
 *      a no-op on SQLite — tests still serialise via PHPUnit's single-threaded run).
 *   3. Inside the transaction, query for any existing booking on the property whose
 *      date range overlaps the requested range — using the standard interval-overlap
 *      predicate: a.check_in < b.check_out AND a.check_out > b.check_in.
 *   4. If any match, throw BookingConflictException (HTTP 409).
 *   5. Otherwise, insert the booking. The unique key on
 *      (property_id, check_in_date, check_out_date) provides defence-in-depth
 *      for the trivial case of identical date pairs.
 *
 * The cancellation_policy + nightly_rate_cents are SNAPSHOTTED from the property
 * at booking time so a host changing their pricing later doesn't retroactively
 * change confirmed bookings (FR-3.5 / NFR-1).
 */
class BookingService
{
    /**
     * @throws BookingConflictException
     */
    public function create(
        User $traveler,
        Property $property,
        string|CarbonImmutable $checkIn,
        string|CarbonImmutable $checkOut,
        int $guests,
    ): Booking {
        $checkInDate = CarbonImmutable::parse($checkIn)->startOfDay();
        $checkOutDate = CarbonImmutable::parse($checkOut)->startOfDay();

        if ($checkOutDate->lessThanOrEqualTo($checkInDate)) {
            throw new \InvalidArgumentException('check_out_date must be after check_in_date');
        }

        return DB::transaction(function () use ($traveler, $property, $checkInDate, $checkOutDate, $guests) {
            // Re-fetch the property with a row lock so two concurrent requests
            // serialise here. SQLite no-ops the lock; tests still pass because
            // PHPUnit runs sequentially.
            $lockedProperty = Property::query()
                ->whereKey($property->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Standard interval overlap check (FR-3.1):
            //   existing.check_in < requested.check_out
            //   AND existing.check_out > requested.check_in
            //
            // whereDate() extracts the DATE portion before comparing — required
            // because Laravel's default $dateFormat 'Y-m-d H:i:s' means a 'date'-cast
            // column actually stores '2026-12-08 00:00:00'. A naive `where('>')`
            // comparison '2026-12-08 00:00:00' > '2026-12-08' is lexicographically
            // TRUE, which would falsely reject legitimate back-to-back bookings.
            $overlapping = Booking::query()
                ->where('property_id', $lockedProperty->id)
                ->whereNotIn('status', [BookingStatus::Cancelled->value])
                ->whereDate('check_in_date', '<', $checkOutDate->toDateString())
                ->whereDate('check_out_date', '>', $checkInDate->toDateString())
                ->exists();

            if ($overlapping) {
                throw new BookingConflictException();
            }

            $nights = $checkInDate->diffInDays($checkOutDate);
            $rate = $lockedProperty->base_nightly_cents;
            $cleaning = $lockedProperty->cleaning_fee_cents;
            $subtotal = $rate * $nights;
            $serviceFee = (int) round($subtotal * 0.12);
            $tax = (int) round(($subtotal + $cleaning + $serviceFee) * 0.08);
            $total = $subtotal + $cleaning + $serviceFee + $tax;

            return Booking::create([
                'property_id' => $lockedProperty->id,
                'traveler_id' => $traveler->id,
                'check_in_date' => $checkInDate->toDateString(),
                'check_out_date' => $checkOutDate->toDateString(),
                'guests' => $guests,
                'nightly_rate_cents' => $rate,
                'nights' => $nights,
                'subtotal_cents' => $subtotal,
                'cleaning_fee_cents' => $cleaning,
                'service_fee_cents' => $serviceFee,
                'tax_cents' => $tax,
                'total_cents' => $total,
                'cancellation_policy' => $lockedProperty->cancellation_policy?->value ?? 'moderate',
                'status' => BookingStatus::PendingPayment->value,
            ]);
        });
    }
}

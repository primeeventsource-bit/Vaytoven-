<?php

namespace App\Services;

use App\Events\BookingConfirmed;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\Property;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingService
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly StripeService $stripe,
    ) {
    }

    /**
     * Create a booking. Wraps everything in a DB transaction with row locking
     * so concurrent requests for the same dates can't both succeed.
     */
    public function create(
        User $guest,
        Property $property,
        CarbonImmutable $checkIn,
        CarbonImmutable $checkOut,
        int $guests,
        ?string $message = null,
    ): Booking {
        if ($property->host_id === $guest->id) {
            throw new BookingException('You cannot book your own listing.');
        }

        if (! $property->isPublished()) {
            throw new BookingException('This listing is not currently available.');
        }

        return DB::transaction(function () use ($guest, $property, $checkIn, $checkOut, $guests, $message) {
            // Lock the property row for the duration to serialize collision checks
            $property = Property::where('id', $property->id)->lockForUpdate()->firstOrFail();

            $this->assertNoCollision($property, $checkIn, $checkOut);

            $quote = $this->pricing->quote($property, $checkIn, $checkOut, $guests);

            $booking = Booking::create([
                'uuid'                     => (string) Str::uuid(),
                'confirmation_code'        => Booking::generateConfirmationCode(),
                'property_id'              => $property->id,
                'guest_id'                 => $guest->id,
                'host_id'                  => $property->host_id,
                'check_in'                 => $checkIn,
                'check_out'                => $checkOut,
                'nights'                   => $quote['nights'],
                'guests'                   => $guests,
                'adults'                   => $guests,
                'base_price_cents'         => $quote['base_price_cents'],
                'cleaning_fee_cents'       => $quote['cleaning_fee_cents'],
                'extra_guest_fee_cents'    => $quote['extra_guest_fee_cents'],
                'subtotal_cents'           => $quote['subtotal_cents'],
                'guest_service_fee_cents'  => $quote['guest_service_fee_cents'],
                'host_service_fee_cents'   => $quote['host_service_fee_cents'],
                'tax_cents'                => $quote['tax_cents'],
                'total_cents'              => $quote['total_cents'],
                'host_payout_cents'        => $quote['host_payout_cents'],
                'currency'                 => $quote['currency'],
                'cancellation_policy'      => $property->cancellation_policy,
                'guest_message'            => $message,
                'status'                   => $property->instant_book ? 'confirmed' : 'pending',
                'payment_status'           => 'unpaid',
                'confirmed_at'             => $property->instant_book ? now() : null,
            ]);

            return $booking;
        });
    }

    /**
     * Authorize payment via Stripe. Called after the user submits their card.
     * On success, sets payment_status='authorized' and (if not already)
     * confirms the booking.
     */
    public function authorizePayment(Booking $booking, string $paymentMethodId): Booking
    {
        if ($booking->payment_status !== 'unpaid') {
            throw new BookingException('This booking is already paid or processing.');
        }

        $intent = $this->stripe->createPaymentIntent(
            amountCents: $booking->total_cents,
            currency: strtolower($booking->currency),
            paymentMethodId: $paymentMethodId,
            metadata: [
                'booking_id'        => (string) $booking->id,
                'confirmation_code' => $booking->confirmation_code,
                'guest_id'          => (string) $booking->guest_id,
            ],
        );

        $booking->update([
            'stripe_payment_intent_id' => $intent->id,
            'payment_status'           => $intent->status === 'succeeded' ? 'paid' : 'authorized',
        ]);

        if ($booking->status === 'pending' && $booking->property->instant_book) {
            $booking->update([
                'status'       => 'confirmed',
                'confirmed_at' => now(),
            ]);
        }

        if ($booking->status === 'confirmed') {
            event(new BookingConfirmed($booking));
        }

        return $booking->fresh();
    }

    /**
     * Cancel a booking. Calculates refund based on policy.
     */
    public function cancel(Booking $booking, User $cancelledBy, ?string $reason = null): Booking
    {
        if (! $booking->isCancellable()) {
            throw new BookingException('This booking can no longer be cancelled.');
        }

        return DB::transaction(function () use ($booking, $cancelledBy, $reason) {
            $refundCents = $this->pricing->cancellationRefund(
                totalCents: $booking->total_cents,
                policy:     $booking->cancellation_policy,
                now:        CarbonImmutable::now(),
                checkIn:    CarbonImmutable::parse($booking->check_in),
            );

            if ($refundCents > 0 && $booking->stripe_payment_intent_id) {
                $this->stripe->refund($booking->stripe_payment_intent_id, $refundCents);
            }

            $booking->update([
                'status'              => 'cancelled',
                'cancelled_by'        => $cancelledBy->id,
                'cancelled_at'        => now(),
                'cancellation_reason' => $reason,
                'payment_status'      => match (true) {
                    $refundCents === $booking->total_cents => 'refunded',
                    $refundCents > 0                       => 'partially_refunded',
                    default                                => $booking->payment_status,
                },
            ]);

            return $booking->fresh();
        });
    }

    /**
     * Throw if the proposed dates collide with an existing active booking
     * on the same property. Belt-and-suspenders against the DB exclusion
     * constraint, so we can return a friendly error before the constraint fires.
     */
    private function assertNoCollision(Property $property, CarbonImmutable $checkIn, CarbonImmutable $checkOut): void
    {
        $collision = Booking::query()
            ->where('property_id', $property->id)
            ->whereIn('status', ['pending', 'confirmed', 'checked_in'])
            ->where('check_in', '<', $checkOut)
            ->where('check_out', '>', $checkIn)
            ->exists();

        if ($collision) {
            throw new BookingException('Those dates are no longer available.');
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\PropertyStatus;
use App\Exceptions\BookingConflictException;
use App\Models\Booking;
use App\Models\Property;
use App\Services\Bookings\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Web-side booking flow (FR-3.x). Distinct from Api\BookingController which
 * serves the SDK + future mobile clients.
 *
 * Flow:
 *   GET  /properties/{property}/book   →  Review page with price breakdown
 *   POST /properties/{property}/book   →  BookingService.create + redirect
 *   GET  /bookings/{booking}            →  Booking detail (own only)
 *
 * Pricing math is computed twice on purpose — once for the review page
 * preview and once authoritatively inside BookingService.create. The model
 * snapshot is what gets persisted; the preview is just UI.
 */
class BookingFlowController extends Controller
{
    public function __construct(private readonly BookingService $bookings)
    {
    }

    /**
     * GET /properties/{property}/book — review page.
     */
    public function review(Request $request, Property $property): View|RedirectResponse
    {
        if ($property->status !== PropertyStatus::Active) {
            abort(404);
        }

        $validator = Validator::make($request->query(), [
            'check_in'  => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'guests'    => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        // If dates are missing or invalid, send the user back to the property
        // page so they can pick valid dates rather than rendering an empty form.
        if ($validator->fails()) {
            return redirect()
                ->route('properties.show', $property)
                ->withErrors($validator)
                ->with('booking_error', 'Pick a check-in and check-out date to continue.');
        }

        $checkIn = CarbonImmutable::parse((string) $request->query('check_in'))->startOfDay();
        $checkOut = CarbonImmutable::parse((string) $request->query('check_out'))->startOfDay();
        $guests = max(1, (int) $request->integer('guests', 1));

        $nights = $checkIn->diffInDays($checkOut);

        if ($nights < $property->minimum_nights) {
            return redirect()
                ->route('properties.show', $property)
                ->with('booking_error', "This property requires a minimum stay of {$property->minimum_nights} night(s).");
        }

        $breakdown = $this->priceBreakdown($property, $nights);

        return view('bookings.review', [
            'property'  => $property->load('photos'),
            'checkIn'   => $checkIn,
            'checkOut'  => $checkOut,
            'guests'    => $guests,
            'nights'    => $nights,
            'breakdown' => $breakdown,
        ]);
    }

    /**
     * POST /properties/{property}/book — create the booking.
     */
    public function store(Request $request, Property $property): RedirectResponse
    {
        if ($property->status !== PropertyStatus::Active) {
            abort(404);
        }

        $request->validate([
            'check_in'  => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'guests'    => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        try {
            $booking = $this->bookings->create(
                traveler:  $request->user(),
                property:  $property,
                checkIn:   (string) $request->string('check_in'),
                checkOut:  (string) $request->string('check_out'),
                guests:    (int) $request->integer('guests'),
            );
        } catch (BookingConflictException) {
            return redirect()
                ->route('properties.show', $property)
                ->with('booking_error', "Those dates were just booked by someone else. Please pick different dates.");
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('properties.show', $property)
                ->with('booking_error', $e->getMessage());
        }

        return redirect()
            ->route('bookings.show', $booking)
            ->with('booking_success', 'Booking created. Payment is the next step.');
    }

    /**
     * GET /bookings/{booking} — own bookings only.
     */
    public function show(Request $request, Booking $booking): View
    {
        if ($booking->traveler_id !== $request->user()->id) {
            abort(404);
        }

        return view('bookings.show', [
            'booking'  => $booking->load('property.photos'),
            // Stripe is "live" only when a real (non-dummy) secret is configured.
            'stripeLive' => $this->stripeConfigured(),
        ]);
    }

    /**
     * Mirror BookingService's pricing math for the review page preview.
     * Kept private + duplicated on purpose — service is authoritative.
     */
    private function priceBreakdown(Property $property, int $nights): array
    {
        $rate = $property->base_nightly_cents;
        $cleaning = $property->cleaning_fee_cents;
        $subtotal = $rate * $nights;
        $serviceFee = (int) round($subtotal * 0.12);
        $tax = (int) round(($subtotal + $cleaning + $serviceFee) * 0.08);

        return [
            'rate_cents'        => $rate,
            'subtotal_cents'    => $subtotal,
            'cleaning_cents'    => $cleaning,
            'service_fee_cents' => $serviceFee,
            'tax_cents'         => $tax,
            'total_cents'       => $subtotal + $cleaning + $serviceFee + $tax,
        ];
    }

    private function stripeConfigured(): bool
    {
        $secret = (string) (config('services.stripe.secret') ?? '');
        return $secret !== '' && ! str_starts_with($secret, 'sk_test_dummy');
    }
}

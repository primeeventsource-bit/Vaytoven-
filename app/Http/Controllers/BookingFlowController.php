<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\PropertyStatus;
use App\Exceptions\BookingConflictException;
use App\Models\Booking;
use App\Models\PaymentIntent;
use App\Models\Property;
use App\Services\Bookings\BookingService;
use App\Services\Payments\Stripe\StripeService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Throwable;

/**
 * Web-side booking flow (FR-3.x). Distinct from Api\BookingController which
 * serves the SDK + future mobile clients.
 *
 * Flow:
 *   GET  /properties/{property}/book   →  Review page with price breakdown
 *   POST /properties/{property}/book   →  BookingService.create + (live) Stripe PI + redirect
 *   GET  /bookings/{booking}            →  Booking detail (own only); reads Stripe redirect_status
 *   GET  /bookings/{booking}/pay        →  Stripe Elements page (live mode only)
 *
 * Stripe is auto-detected: if STRIPE_SECRET is unset or the dummy fallback,
 * we don't even try to create a PaymentIntent. The booking sits at
 * pending_payment with a 'demo mode' banner on the show page. The moment a
 * real key is configured, new bookings get a PaymentIntent + the show page
 * renders a Pay button that links to /bookings/{booking}/pay.
 */
class BookingFlowController extends Controller
{
    public function __construct(
        private readonly BookingService $bookings,
        private readonly StripeService $stripe,
    ) {
    }

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

        // Live mode: kick off a Stripe PaymentIntent right away so the show
        // page can immediately offer a Pay button. Demo mode skips this and
        // the booking sits at pending_payment with the demo banner.
        if ($this->stripeConfigured()) {
            try {
                $intent = $this->stripe->createPaymentIntent($booking);
                // PaymentIntent association on the Booking lets the pay page
                // resolve the right intent without an extra query.
                $booking->update(['payment_intent_id' => $intent->id]);

                return redirect()->route('bookings.pay', $booking);
            } catch (Throwable $e) {
                // Stripe API failure — log and fall through to the demo show
                // page so the booking row isn't lost.
                Log::error('booking flow: stripe payment intent creation failed', [
                    'booking_id' => $booking->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return redirect()
            ->route('bookings.show', $booking)
            ->with('booking_success', 'Booking created. Payment is the next step.');
    }

    public function show(Request $request, Booking $booking): View
    {
        if ($booking->traveler_id !== $request->user()->id) {
            abort(404);
        }

        $booking->load('property.photos');

        // Stripe redirects back here with `redirect_status` after the user
        // confirms payment client-side. The webhook is the authoritative
        // state-update path; this is just for friendly UX immediately after
        // redirect, before the webhook arrives (typically <500ms).
        $redirectStatus = $request->query('redirect_status');
        $paymentNotice = $this->paymentNoticeFor($redirectStatus);

        return view('bookings.show', [
            'booking'       => $booking,
            'stripeLive'    => $this->stripeConfigured(),
            'paymentNotice' => $paymentNotice,
        ]);
    }

    /**
     * Stripe Elements payment page. Live mode only — demo bookings are
     * redirected back to /bookings/{booking} where the demo banner explains
     * what's missing.
     */
    public function pay(Request $request, Booking $booking): View|RedirectResponse
    {
        if ($booking->traveler_id !== $request->user()->id) {
            abort(404);
        }

        if (! $this->stripeConfigured()) {
            return redirect()->route('bookings.show', $booking);
        }

        // Already paid — short-circuit back to the confirmation page.
        if ($booking->status !== BookingStatus::PendingPayment) {
            return redirect()->route('bookings.show', $booking);
        }

        $intent = $booking->payment_intent_id
            ? PaymentIntent::find($booking->payment_intent_id)
            : null;

        // No intent yet (e.g., the create-time call failed and we fell back).
        // Try once more here so the user can still pay.
        if (! $intent) {
            $intent = $this->stripe->createPaymentIntent($booking);
            $booking->update(['payment_intent_id' => $intent->id]);
        }

        return view('bookings.pay', [
            'booking'         => $booking->load('property:id,title,city,country'),
            'clientSecret'    => $intent->client_secret,
            'publishableKey'  => (string) (config('services.stripe.key') ?? ''),
            'returnUrl'       => route('bookings.show', $booking),
        ]);
    }

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
        $key    = (string) (config('services.stripe.key') ?? '');
        // Both halves required: secret for server-side intent creation,
        // publishable key for client-side Stripe.js.
        return $secret !== '' && $key !== '' && ! str_starts_with($secret, 'sk_test_dummy');
    }

    /**
     * Map Stripe's redirect_status query param to a friendly notice.
     */
    private function paymentNoticeFor(?string $redirectStatus): ?array
    {
        return match ($redirectStatus) {
            'succeeded'      => ['tone' => 'success', 'message' => 'Payment received — your booking is confirmed.'],
            'processing'     => ['tone' => 'info',    'message' => 'Payment is processing. We\'ll email you when it clears.'],
            'requires_payment_method' => ['tone' => 'error', 'message' => 'That payment method was declined. Try again with a different card.'],
            default          => null,
        };
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\PropertyStatus;
use App\Exceptions\BookingConflictException;
use App\Models\Booking;
use App\Models\Charge;
use App\Models\PaymentIntent;
use App\Models\PaymentProcessorConfig;
use App\Models\Property;
use App\Services\Bookings\BookingService;
use App\Services\Bookings\QuoteCalculator;
use App\Services\Payments\Nmi\NmiPaymentDeclinedException;
use App\Services\Payments\Nmi\NmiService;
use App\Services\Payments\RefundCalculator;
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
 *   POST /properties/{property}/book   →  BookingService.create + local intent + redirect
 *   GET  /bookings/{booking}            →  Booking detail (own only); reads ?payment= status
 *   GET  /bookings/{booking}/pay        →  NMI Collect.js card form (live mode only)
 *   POST /bookings/{booking}/pay        →  charge the Collect.js payment_token (type=sale)
 *
 * Unlike the old Stripe Elements flow, NMI settles synchronously: Collect.js
 * tokenizes the card in the browser, POSTs the payment_token here, and the
 * sale verdict comes back in the same request — no webhook round-trip needed
 * to confirm the booking.
 *
 * NMI is auto-detected: if NMI_SECURITY_KEY / NMI_TOKENIZATION_KEY are unset,
 * the booking sits at pending_payment with a 'demo mode' banner on the show
 * page. The moment real keys are configured, new bookings get an intent + the
 * show page renders a Pay button that links to /bookings/{booking}/pay.
 */
class BookingFlowController extends Controller
{
    public function __construct(
        private readonly BookingService $bookings,
        private readonly NmiService $payments,
    ) {}

    /**
     * GET /account/bookings — paginated list of the signed-in user's bookings,
     * newest first by check-in date.
     */
    public function index(Request $request): View
    {
        $bookings = Booking::query()
            ->where('traveler_id', $request->user()->id)
            ->with('property:id,title,city,country')
            ->orderByDesc('check_in_date')
            ->paginate(15);

        return view('bookings.index', [
            'bookings' => $bookings,
        ]);
    }

    public function review(Request $request, Property $property): View|RedirectResponse
    {
        if ($property->status !== PropertyStatus::Active) {
            abort(404);
        }

        $validator = Validator::make($request->query(), [
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'guests' => ['nullable', 'integer', 'min:1', 'max:50'],
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

        // Effective minimum = the stricter of the property's own rule and
        // the site-wide floor from the Settings console.
        $minNights = max((int) $property->minimum_nights, (int) setting('booking.min_nights', 1));
        if ($nights < $minNights) {
            return redirect()
                ->route('properties.show', $property)
                ->with('booking_error', "This property requires a minimum stay of {$minNights} night(s).");
        }

        $maxNights = (int) setting('booking.max_nights', 30);
        if ($nights > $maxNights) {
            return redirect()
                ->route('properties.show', $property)
                ->with('booking_error', "Stays are limited to {$maxNights} nights.");
        }

        $breakdown = $this->priceBreakdown($property, $nights);

        return view('bookings.review', [
            'property' => $property->load('photos'),
            'checkIn' => $checkIn,
            'checkOut' => $checkOut,
            'guests' => $guests,
            'nights' => $nights,
            'breakdown' => $breakdown,
        ]);
    }

    public function store(Request $request, Property $property): RedirectResponse
    {
        if ($property->status !== PropertyStatus::Active) {
            abort(404);
        }

        $request->validate([
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'guests' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        try {
            $booking = $this->bookings->create(
                traveler: $request->user(),
                property: $property,
                checkIn: (string) $request->string('check_in'),
                checkOut: (string) $request->string('check_out'),
                guests: (int) $request->integer('guests'),
            );
        } catch (BookingConflictException) {
            return redirect()
                ->route('properties.show', $property)
                ->with('booking_error', 'Those dates were just booked by someone else. Please pick different dates.');
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('properties.show', $property)
                ->with('booking_error', $e->getMessage());
        }

        // Live mode: create the local payment-intent row right away so the
        // show page can immediately offer a Pay button. This is DB-only (NMI
        // isn't contacted until the traveler submits a card), so it can only
        // fail on a genuine DB error. Demo mode skips this and the booking
        // sits at pending_payment with the demo banner.
        if ($this->paymentsConfigured()) {
            try {
                $intent = $this->payments->createPaymentIntent($booking);
                // PaymentIntent association on the Booking lets the pay page
                // resolve the right intent without an extra query.
                $booking->update(['payment_intent_id' => $intent->id]);

                return redirect()->route('bookings.pay', $booking);
            } catch (Throwable $e) {
                Log::error('booking flow: payment intent creation failed', [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage(),
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

        // processPayment() redirects back here with ?payment= after the
        // synchronous NMI sale attempt. Unlike the old Stripe redirect_status,
        // this reflects the settled outcome — no webhook race to worry about.
        $paymentNotice = $this->paymentNoticeFor($request->query('payment'));

        return view('bookings.show', [
            'booking' => $booking,
            'paymentsLive' => $this->paymentsConfigured(),
            'paymentNotice' => $paymentNotice,
        ]);
    }

    /**
     * NMI Collect.js payment page. Live mode only — demo bookings are
     * redirected back to /bookings/{booking} where the demo banner explains
     * what's missing.
     */
    public function pay(Request $request, Booking $booking): View|RedirectResponse
    {
        if ($booking->traveler_id !== $request->user()->id) {
            abort(404);
        }

        if (! $this->paymentsConfigured()) {
            return redirect()->route('bookings.show', $booking);
        }

        // Already paid — short-circuit back to the confirmation page.
        if ($booking->status !== BookingStatus::PendingPayment) {
            return redirect()->route('bookings.show', $booking);
        }

        // Ensure the local intent row exists (e.g., created pre-NMI or the
        // create-time call failed and we fell back).
        $intent = $booking->payment_intent_id
            ? PaymentIntent::find($booking->payment_intent_id)
            : null;

        if (! $intent) {
            $intent = $this->payments->createPaymentIntent($booking);
            $booking->update(['payment_intent_id' => $intent->id]);
        }

        return view('bookings.pay', [
            'booking' => $booking->load('property:id,title,city,country'),
            'tokenizationKey' => (string) (config('services.nmi.tokenization_key') ?? ''),
            'collectJsUrl' => (string) (config('services.nmi.collect_js_url') ?? 'https://secure.nmi.com/token/Collect.js'),
            'processUrl' => route('bookings.pay.process', $booking),
        ]);
    }

    /**
     * POST /bookings/{booking}/pay — charge the Collect.js payment_token.
     *
     * Synchronous: NMI's sale verdict comes back in this request. Approved →
     * booking confirmed before the redirect; declined → back to the pay page
     * with a friendly error and the intent reset for retry.
     */
    public function processPayment(Request $request, Booking $booking): RedirectResponse
    {
        if ($booking->traveler_id !== $request->user()->id) {
            abort(404);
        }

        if (! $this->paymentsConfigured()) {
            return redirect()->route('bookings.show', $booking);
        }

        if ($booking->status !== BookingStatus::PendingPayment) {
            return redirect()->route('bookings.show', $booking);
        }

        $request->validate([
            'payment_token' => ['required', 'string', 'max:512'],
        ]);

        $intent = $booking->payment_intent_id
            ? PaymentIntent::find($booking->payment_intent_id)
            : null;

        if (! $intent) {
            $intent = $this->payments->createPaymentIntent($booking);
            $booking->update(['payment_intent_id' => $intent->id]);
        }

        try {
            $this->payments->chargeIntent($intent, (string) $request->string('payment_token'));
        } catch (NmiPaymentDeclinedException $e) {
            Log::info('booking flow: nmi sale declined', [
                'booking_id' => $booking->id,
                'responsetext' => $e->getMessage(),
            ]);

            return redirect()
                ->route('bookings.pay', $booking)
                ->with('payment_error', $e->friendlyMessage());
        } catch (Throwable $e) {
            Log::error('booking flow: nmi sale failed', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('bookings.pay', $booking)
                ->with('payment_error', 'We could not reach the payment gateway. Nothing was charged — please try again.');
        }

        return redirect()->route('bookings.show', ['booking' => $booking, 'payment' => 'succeeded']);
    }

    /**
     * GET /bookings/{booking}/cancel — show refund preview before confirming.
     */
    public function cancelForm(Request $request, Booking $booking): View|RedirectResponse
    {
        if ($booking->traveler_id !== $request->user()->id) {
            abort(404);
        }

        if ($booking->status->isTerminal()) {
            return redirect()
                ->route('bookings.show', $booking)
                ->with('booking_error', 'This booking is already in a terminal state.');
        }

        $breakdown = RefundCalculator::compute($booking);

        return view('bookings.cancel', [
            'booking' => $booking->load('property:id,title,city,country'),
            'breakdown' => $breakdown,
        ]);
    }

    /**
     * POST /bookings/{booking}/cancel — transition to cancelled, optionally
     * issue an NMI refund when live + a successful charge exists.
     */
    public function cancel(Request $request, Booking $booking): RedirectResponse
    {
        if ($booking->traveler_id !== $request->user()->id) {
            abort(404);
        }

        if ($booking->status->isTerminal()) {
            return redirect()
                ->route('bookings.show', $booking)
                ->with('booking_error', 'This booking is already in a terminal state.');
        }

        $reason = (string) $request->string('reason')->limit(255) ?: 'traveler_cancelled';

        $breakdown = RefundCalculator::compute($booking);

        $booking->transitionTo(
            BookingStatus::Cancelled,
            actorUserId: $request->user()->id,
            reason: $reason,
        );

        $refundIssued = false;
        if ($this->paymentsConfigured() && $breakdown->total_cents > 0) {
            // Find the most recent successful charge against this booking and
            // issue an NMI refund. NmiService dedupes on (charge, amount) so
            // an accidental double-submit doesn't refund twice.
            $charge = Charge::query()
                ->where('booking_id', $booking->id)
                ->whereNotNull('external_charge_id')
                ->latest('created_at')
                ->first();

            if ($charge) {
                try {
                    $this->payments->refundCharge($charge, $breakdown, actorUserId: $request->user()->id, reason: $reason);
                    $refundIssued = true;
                } catch (Throwable $e) {
                    Log::error('booking flow: nmi refund failed', [
                        'booking_id' => $booking->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $message = $breakdown->total_cents > 0
            ? sprintf(
                'Booking cancelled. %s%s',
                $refundIssued ? 'Refund of $'.number_format($breakdown->total_cents / 100, 2).' is on its way.' : "You're owed a $".number_format($breakdown->total_cents / 100, 2).' refund per the cancellation policy.',
                $refundIssued ? '' : ' We will process it within 1 business day.',
            )
            : 'Booking cancelled. Per the cancellation policy, no refund is due.';

        return redirect()
            ->route('bookings.show', $booking)
            ->with('booking_success', $message);
    }

    private function priceBreakdown(Property $property, int $nights): array
    {
        $rate = $property->base_nightly_cents;
        $cleaning = $property->cleaning_fee_cents;
        // Admin-tunable fee % and tax rate — same math as BookingService,
        // so the review page always matches the persisted booking.
        $quote = QuoteCalculator::breakdown($rate, $nights, $cleaning);

        return [
            'rate_cents' => $rate,
            'subtotal_cents' => $quote['subtotal_cents'],
            'cleaning_cents' => $cleaning,
            'service_fee_cents' => $quote['service_fee_cents'],
            'tax_cents' => $quote['tax_cents'],
            'total_cents' => $quote['total_cents'],
        ];
    }

    private function paymentsConfigured(): bool
    {
        // Ops can pull NMI out of rotation from the Settings console (row
        // toggle or processor.nmi feature flag); the flow then falls back to
        // demo mode rather than charging through a disabled processor.
        if (! PaymentProcessorConfig::isEnabled('nmi') || ! feature('processor.nmi')) {
            return false;
        }

        $securityKey = (string) (config('services.nmi.security_key') ?? '');
        $tokenizationKey = (string) (config('services.nmi.tokenization_key') ?? '');

        // Both halves required: security_key for server-side Payment API
        // calls, tokenization_key for client-side Collect.js.
        return $securityKey !== '' && $tokenizationKey !== '' && ! str_starts_with($securityKey, 'demo_dummy');
    }

    /**
     * Map the ?payment= query param (set by processPayment) to a notice.
     */
    private function paymentNoticeFor(?string $status): ?array
    {
        return match ($status) {
            'succeeded' => ['tone' => 'success', 'message' => 'Payment received — your booking is confirmed.'],
            default => null,
        };
    }
}

<?php

namespace App\Services\Chargeback;

use App\Models\Booking;
use App\Models\ChargebackDispute;
use App\Models\Contract;
use App\Models\LoginSession;
use App\Models\TermsAcceptance;
use App\Models\TrackingEvent;
use Carbon\CarbonImmutable;

/**
 * Builds the chargeback evidence bundle for a booking or user (FR-10.6, FR-10.13).
 *
 * Pulls from every source of evidence the company has:
 *   - login_sessions: proves the cardholder physically accessed the account
 *   - tracking_events: proves browse-to-book engagement, time spent, etc.
 *   - charges + refunds: pricing reality
 *   - terms_acceptances: proves consent to ToS / Chargeback Policy
 *   - contracts: signed agreements (DocuSign)
 *
 * The bundle is returned as a structured DTO. Per-processor adapters
 * (StripeDisputeAdapter, AuthorizeNetDisputeAdapter, etc.) format it for
 * each processor's submission portal/API.
 */
class EvidenceBundleService
{
    /**
     * Event types classified as "consumption" (active engagement —
     * stronger evidence the cardholder used the service) per FR-10.6.
     * Everything else is treated as "passive" (page views, ad clicks).
     */
    private const CONSUMPTION_TYPES = [
        'login_succeeded',
        'login_attempted',
        'logout',
        'search_performed',
        'property_viewed',
        'booking_started',
        'booking_created',
        'booking_confirmed',
        'message_sent',
        'message_received',
        'payment_succeeded',
        'payment_method_attached',
        'terms_accepted',
        'profile_updated',
        'review_submitted',
        'contract_signed',
        'check_in_completed',
        'check_out_completed',
    ];

    public function generateForBooking(Booking $booking, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): EvidenceBundle
    {
        $userId = $booking->traveler_id;
        $from ??= CarbonImmutable::parse($booking->created_at)->subDays(30);
        $to ??= CarbonImmutable::now();

        $dispute = ChargebackDispute::where('booking_id', $booking->id)->first();

        return $this->buildBundle(
            booking: $booking,
            userId: $userId,
            disputeId: $dispute?->id,
            from: $from,
            to: $to,
        );
    }

    public function generateForUser(int $userId, CarbonImmutable $from, CarbonImmutable $to): EvidenceBundle
    {
        return $this->buildBundle(
            booking: null,
            userId: $userId,
            disputeId: null,
            from: $from,
            to: $to,
        );
    }

    private function buildBundle(?Booking $booking, ?int $userId, ?int $disputeId, CarbonImmutable $from, CarbonImmutable $to): EvidenceBundle
    {
        // Login records (FR-10.7) — strong primary evidence of cardholder access.
        $logins = LoginSession::query()
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->whereBetween('occurred_at', [$from, $to])
            ->orderBy('occurred_at')
            ->get()
            ->map(fn ($r) => $r->only([
                'auth_event', 'surface', 'ip_address', 'country', 'region', 'city',
                'is_vpn', 'is_tor', 'is_datacenter', 'device_type', 'os', 'browser',
                'is_suspicious', 'occurred_at',
            ]))
            ->all();

        // Charges (booking-scoped only). Booking model doesn't define a payments
        // relation yet; query Charge directly.
        $charges = $booking
            ? \App\Models\Charge::where('booking_id', $booking->id)
                ->orderBy('created_at')
                ->get()
                ->map(fn ($c) => $c->only(['external_charge_id', 'amount_cents', 'currency', 'captured', 'created_at']))
                ->all()
            : [];

        $refunds = $booking
            ? \App\Models\Refund::where('booking_id', $booking->id)
                ->orderBy('created_at')
                ->get()
                ->map(fn ($r) => $r->only(['external_refund_id', 'amount_cents', 'reason', 'created_at']))
                ->all()
            : [];

        // Terms acceptances — proves the user agreed to ToS / Chargeback Policy.
        $terms = $userId
            ? TermsAcceptance::with('termsVersion:id,kind,version_label,content_hash,effective_at')
                ->where('user_id', $userId)
                ->orderBy('accepted_at')
                ->get()
                ->map(fn ($a) => [
                    'kind' => $a->termsVersion?->kind,
                    'version_label' => $a->termsVersion?->version_label,
                    'content_hash' => $a->termsVersion?->content_hash,
                    'accepted_at' => $a->accepted_at?->toIso8601String(),
                    'ip_address' => $a->ip_address,
                ])
                ->all()
            : [];

        // All tracking events for the user in the window, classified.
        $events = TrackingEvent::query()
            ->when($userId, fn ($q) => $q->where('actor_user_id', $userId))
            ->whereBetween('occurred_at', [$from, $to])
            ->orderBy('occurred_at')
            ->get();

        $consumption = [];
        $passive = [];
        foreach ($events as $e) {
            $row = [
                'event_uuid' => $e->event_uuid,
                'event_type' => $e->event_type,
                'surface' => $e->surface?->value,
                'occurred_at' => $e->occurred_at->toIso8601String(),
                'metadata' => $e->metadata,
            ];

            if (in_array($e->event_type, self::CONSUMPTION_TYPES, true)) {
                $consumption[] = $row;
            } else {
                $passive[] = $row;
            }
        }

        // Signed contracts — proves the booking was a real agreement.
        $contracts = $userId
            ? Contract::where('user_id', $userId)
                ->whereIn('status', ['signed', 'completed'])
                ->orderBy('completed_at')
                ->get()
                ->map(fn ($c) => $c->only(['envelope_id', 'subject', 'status', 'completed_at']))
                ->all()
            : [];

        return new EvidenceBundle(
            booking_id: $booking?->id,
            user_id: $userId,
            dispute_id: $disputeId,
            confirmation_code: $booking?->confirmation_code ?? '',
            logins: $logins,
            charges: $charges,
            refunds: $refunds,
            terms_acceptances: $terms,
            consumption_events: $consumption,
            passive_events: $passive,
            contracts: $contracts,
            generated_at: CarbonImmutable::now()->toIso8601String(),
        );
    }
}

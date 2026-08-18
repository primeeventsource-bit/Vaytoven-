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
 * (NmiDisputeAdapter, AuthorizeNetDisputeAdapter, etc.) format it for
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

        // Vaytoven's own billing. Matched by the user's email, because
        // activation is a public flow that does not require an account — the
        // order and the user are linked by the address the member typed.
        $email = $userId ? \App\Models\User::find($userId)?->email : null;

        $orders = $email
            ? \App\Models\MemberServiceOrder::where('email', $email)
                ->orderBy('created_at')
                ->get()
                ->map(fn ($o) => [
                    'reference'            => $o->reference,
                    'package'              => $o->package->label(),
                    'weeks'                => $o->weeks,
                    'price_per_week_cents' => $o->price_per_week_cents,
                    'total_cents'          => $o->total_cents,
                    'currency'             => $o->currency,
                    'status'               => $o->status->value,
                    'paid_at'              => $o->paid_at?->toIso8601String(),
                    // The processor's own reference is the single most useful
                    // field in this entire bundle when a dispute is filed.
                    'nmi_transaction_id'   => $o->nmi_transaction_id,
                    'nmi_authcode'         => $o->nmi_authcode,
                    'submitted_ip'         => $o->submitted_ip,
                    'created_at'           => $o->created_at?->toIso8601String(),
                ])
                ->all()
            : [];

        // Fulfilment: which property was advertised, for how long, from when.
        // A receipt proves a charge; this proves the service was delivered.
        $periods = $email
            ? \App\Models\AdvertisingPeriod::query()
                ->whereIn('member_service_order_id',
                    \App\Models\MemberServiceOrder::where('email', $email)->select('id'))
                ->with('property:id,title,city,country')
                ->orderBy('starts_at')
                ->get()
                ->map(fn ($p) => [
                    'property_id'    => $p->property_id,
                    'property_title' => $p->property?->title,
                    'property_city'  => $p->property?->city,
                    'starts_at'      => $p->starts_at?->toIso8601String(),
                    'ends_at'        => $p->ends_at?->toIso8601String(),
                    'activated_at'   => $p->activated_at?->toIso8601String(),
                    'status'         => $p->effectiveStatus()->value,
                ])
                ->all()
            : [];

        // The advertisements as published, for the properties this member had
        // running. Each carries the hash recorded at capture, so the copy in
        // the pack can be shown not to have been edited since.
        $snapshots = $email
            ? \App\Models\PropertySnapshot::query()
                ->whereIn('property_id', \App\Models\AdvertisingPeriod::query()
                    ->whereIn('member_service_order_id',
                        \App\Models\MemberServiceOrder::where('email', $email)->select('id'))
                    ->select('property_id'))
                ->orderBy('captured_at')
                ->get()
                ->map(fn ($s) => [
                    'property_id'  => $s->property_id,
                    'reason'       => $s->reason,
                    'captured_at'  => $s->captured_at?->toIso8601String(),
                    'content_hash' => $s->content_hash,
                    'intact'       => $s->isIntact(),
                    'content'      => $s->content,
                ])
                ->all()
            : [];

        // The service trail: the evidence-grade sequence, in order, with the
        // audit context each step carried. Queried separately from the
        // browse events above rather than filtered out of them, so adding a
        // new consumption type later cannot quietly widen what goes to an
        // issuer.
        $serviceTrail = TrackingEvent::query()
            ->when($userId, fn ($q) => $q->where('actor_user_id', $userId))
            ->whereIn('event_type', \App\Enums\ActivityType::evidenceTrail())
            ->whereBetween('occurred_at', [$from, $to])
            ->orderBy('occurred_at')
            ->get()
            ->map(fn ($e) => [
                'event_uuid'   => $e->event_uuid,
                'activity'     => \App\Enums\ActivityType::tryFrom($e->event_type)?->label() ?? $e->event_type,
                'occurred_at'  => $e->occurred_at?->toIso8601String(),
                'result'       => $e->result,
                'subject'      => $e->subject_reference,
                'ip_address'   => $e->ip_address,
                // Described as approximate everywhere it is shown, because
                // that is what a GeoIP lookup is.
                'approx_location' => trim(collect([$e->city, $e->region, $e->country])->filter()->implode(', ')) ?: null,
                'device'       => $e->device_type,
                'browser'      => $e->browser,
                'session_id'   => $e->session_id,
            ])
            ->all();

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
            member_service_orders: $orders,
            advertising_periods: $periods,
            ad_snapshots: $snapshots,
            service_trail: $serviceTrail,
        );
    }
}

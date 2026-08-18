<?php

namespace App\Services\Chargeback;

/**
 * Immutable value object holding the structured chargeback evidence for one
 * booking + user + dispute. Per-processor DisputeAdapter classes consume this
 * to produce processor-specific submission formats.
 *
 * Ordering rule (FR-10.6): consumption_events come BEFORE passive_events in
 * the events list. Consumption = login, search, booking_created, message_sent,
 * payment_succeeded, terms_accepted. Passive = page_view, ad_click, scroll.
 */
final readonly class EvidenceBundle
{
    public function __construct(
        public ?int $booking_id,
        public ?int $user_id,
        public ?int $dispute_id,
        public string $confirmation_code,
        public array $logins,                    // login_sessions rows as arrays
        public array $charges,                   // charges + linked payment_intent
        public array $refunds,                   // refunds for the booking
        public array $terms_acceptances,         // terms_acceptances + version snippets
        public array $consumption_events,        // first by FR-10.6
        public array $passive_events,            // last by FR-10.6
        public array $contracts,                 // DocuSign contracts (signed agreements)
        public string $generated_at,             // ISO-8601
        // Vaytoven's own billing — what the member actually paid for, and the
        // advertising that was delivered against it. The bundle previously
        // carried only booking charges, so a certificate for a member who paid
        // for Member Services showed their logins and contracts but not the
        // transaction, which is the one thing a processor asks for.
        public array $member_service_orders = [],
        public array $advertising_periods = [],
        // What the advertisement actually said while it ran. The current
        // listing may share nothing with the version the member paid for.
        public array $ad_snapshots = [],

        /**
         * The narrow sequence that answers "did this person agree to this
         * and pay for it": account created, terms accepted, contract signed,
         * payment approved, advertising activated, later logins.
         *
         * Kept separate from consumption/passive events on purpose.
         * Browsing history pads a dispute file with unrelated visitor
         * records and proves nothing about consent; a card issuer reading
         * forty page views to find one payment is a worse outcome than
         * handing them the six rows that matter.
         */
        public array $service_trail = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'booking_id' => $this->booking_id,
            'user_id' => $this->user_id,
            'dispute_id' => $this->dispute_id,
            'confirmation_code' => $this->confirmation_code,
            'logins' => $this->logins,
            'charges' => $this->charges,
            'refunds' => $this->refunds,
            'terms_acceptances' => $this->terms_acceptances,
            // Per FR-10.6: consumption events first, passive last.
            'events' => array_merge($this->consumption_events, $this->passive_events),
            'consumption_events_count' => count($this->consumption_events),
            'passive_events_count' => count($this->passive_events),
            'contracts' => $this->contracts,
            'member_service_orders' => $this->member_service_orders,
            'advertising_periods' => $this->advertising_periods,
            'ad_snapshots' => $this->ad_snapshots,
            // Listed before the browse events in the exported file: it is
            // what an issuer reads first, and burying it under page views
            // is how a good bundle gets skimmed past.
            'service_trail' => $this->service_trail,
            'generated_at' => $this->generated_at,
        ];
    }
}

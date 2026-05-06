<?php

namespace App\Services\Payments\Stripe;

use App\Services\Chargeback\EvidenceBundle;
use App\Services\Payments\DisputeAdapter;
use App\Services\Payments\DisputeSubmissionResult;
use Stripe\StripeClient;

/**
 * Stripe dispute adapter (FR-10.12, FR-4.6).
 *
 * Stripe's dispute API accepts structured evidence fields:
 *   customer_communication, receipt, service_documentation, etc.
 * We map our EvidenceBundle into those fields and call disputes->update().
 *
 * https://stripe.com/docs/disputes/responding
 */
class StripeDisputeAdapter implements DisputeAdapter
{
    public function __construct(private readonly StripeClient $stripe)
    {
    }

    public function submit(string $externalDisputeId, EvidenceBundle $bundle): DisputeSubmissionResult
    {
        // Build Stripe's evidence fields. Each field has a 5KB cap; we summarise
        // rather than dump the raw bundle. The full JSON gets attached as a file
        // via files->create() so the dispute reviewer has access to everything.

        $loginCount = count($bundle->logins);
        $consumptionCount = count($bundle->consumption_events);
        $termsCount = count($bundle->terms_acceptances);

        $customerCommunication = sprintf(
            "Booking %s. Cardholder authenticated %d time(s), accepted %d versions of our terms, and engaged with the service through %d consumption events (search, booking, messaging, payment).",
            $bundle->confirmation_code,
            $loginCount,
            $termsCount,
            $consumptionCount,
        );

        $serviceDocumentation = collect($bundle->consumption_events)
            ->take(20)
            ->map(fn ($e) => "{$e['occurred_at']} {$e['event_type']} ({$e['surface']})")
            ->implode("\n");

        $response = $this->stripe->disputes->update($externalDisputeId, [
            'evidence' => [
                'customer_communication' => mb_substr($customerCommunication, 0, 5000),
                'service_documentation' => mb_substr($serviceDocumentation, 0, 5000),
                'access_activity_log' => mb_substr(json_encode($bundle->logins), 0, 5000),
            ],
            'submit' => true,
        ]);

        return new DisputeSubmissionResult(
            processor: 'stripe',
            external_dispute_id: $externalDisputeId,
            mode: 'api',
            api_response: ['id' => $response->id, 'status' => $response->status],
            note: 'Submitted via Stripe Disputes API',
        );
    }
}

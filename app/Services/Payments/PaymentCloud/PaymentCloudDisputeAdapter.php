<?php

namespace App\Services\Payments\PaymentCloud;

use App\Services\Payments\PortalPdfDisputeAdapter;

/**
 * Payment Cloud is a high-risk reseller — disputes route to the underlying
 * acquirer but Payment Cloud's portal is the operator's surface for filing
 * rebuttals. No first-party API.
 */
class PaymentCloudDisputeAdapter extends PortalPdfDisputeAdapter
{
    public function processorKey(): string
    {
        return 'paymentcloud';
    }

    public function portalUploadUrl(): string
    {
        return (string) (config('services.chargeback.paymentcloud.portal_url')
            ?? 'https://portal.paymentcloudinc.com/');
    }

    public function submissionInstructions(): string
    {
        return 'Merchant Portal → Chargebacks → flag case as Disputed → upload this PDF and email risk@paymentcloudinc.com with the case id.';
    }
}

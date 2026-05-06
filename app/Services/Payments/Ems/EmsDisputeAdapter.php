<?php

namespace App\Services\Payments\Ems;

use App\Services\Payments\PortalPdfDisputeAdapter;

/**
 * Electronic Merchant Systems (EMS) routes disputes through the eMerchant View
 * portal. Operator opens the chargeback case and uploads the rebuttal PDF.
 */
class EmsDisputeAdapter extends PortalPdfDisputeAdapter
{
    public function processorKey(): string
    {
        return 'ems';
    }

    public function portalUploadUrl(): string
    {
        return (string) (config('services.chargeback.ems.portal_url')
            ?? 'https://emerchantview.com/');
    }

    public function submissionInstructions(): string
    {
        return 'eMerchantView → Chargebacks → select case → Respond → upload this PDF as documentation.';
    }
}

<?php

namespace App\Services\Payments\Netevia;

use App\Services\Payments\PortalPdfDisputeAdapter;

/**
 * Netevia disputes are managed through the Netevia Merchant Portal. No public
 * dispute API; rebuttals are uploaded as PDF documents per case.
 */
class NeteviaDisputeAdapter extends PortalPdfDisputeAdapter
{
    public function processorKey(): string
    {
        return 'netevia';
    }

    public function portalUploadUrl(): string
    {
        return (string) (config('services.chargeback.netevia.portal_url')
            ?? 'https://merchant.netevia.com/');
    }

    public function submissionInstructions(): string
    {
        return 'Merchant Portal → Reports → Chargebacks → select case → Submit Documents → upload this PDF.';
    }
}

<?php

namespace App\Services\Payments\Nuvei;

use App\Services\Payments\PortalPdfDisputeAdapter;

/**
 * Nuvei (Safecharge) disputes are managed in the Merchant Control Panel
 * under the Risk > Chargebacks section. Their REST API supports submission
 * but is gated behind a separate enablement; default path is portal upload.
 */
class NuveiDisputeAdapter extends PortalPdfDisputeAdapter
{
    public function processorKey(): string
    {
        return 'nuvei';
    }

    public function portalUploadUrl(): string
    {
        return (string) (config('services.chargeback.nuvei.portal_url')
            ?? 'https://controlpanel.nuvei.com/');
    }

    public function submissionInstructions(): string
    {
        return 'Risk → Chargebacks → select case → Reply tab → upload PDF and add a one-line rebuttal summary.';
    }
}

<?php

namespace App\Services\Payments\Mes;

use App\Services\Payments\PortalPdfDisputeAdapter;

/**
 * Merchant e-Solutions handles disputes through MeS Customer Service Portal.
 * No public API; rebuttal documents are emailed to chargebacks@merchante-solutions.com
 * or uploaded through the portal's case file upload.
 */
class MesDisputeAdapter extends PortalPdfDisputeAdapter
{
    public function processorKey(): string
    {
        return 'mes';
    }

    public function portalUploadUrl(): string
    {
        return (string) (config('services.chargeback.mes.portal_url')
            ?? 'https://portal.merchante-solutions.com/');
    }

    public function submissionInstructions(): string
    {
        return 'Customer Service Portal → Chargebacks → open case → Upload Documents → attach this PDF; copy chargebacks@merchante-solutions.com.';
    }
}

<?php

namespace App\Services\Payments\Kurv;

use App\Services\Payments\PortalPdfDisputeAdapter;

/**
 * Kurv (formerly Curve Payments) handles chargebacks through its merchant
 * dashboard. No public submission API at this time.
 */
class KurvDisputeAdapter extends PortalPdfDisputeAdapter
{
    public function processorKey(): string
    {
        return 'kurv';
    }

    public function portalUploadUrl(): string
    {
        return (string) (config('services.chargeback.kurv.portal_url')
            ?? 'https://merchant.kurv.com/');
    }

    public function submissionInstructions(): string
    {
        return 'Dashboard → Disputes → open case → Reply with Evidence → upload this PDF.';
    }
}

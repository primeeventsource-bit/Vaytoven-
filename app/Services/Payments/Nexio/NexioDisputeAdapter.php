<?php

namespace App\Services\Payments\Nexio;

use App\Services\Payments\PortalPdfDisputeAdapter;

/**
 * Nexio publishes a dispute API but enrolment is per-merchant; we keep the
 * portal-PDF path as the default. Operator can switch to API submission once
 * Nexio enables the dispute endpoints on the merchant account.
 */
class NexioDisputeAdapter extends PortalPdfDisputeAdapter
{
    public function processorKey(): string
    {
        return 'nexio';
    }

    public function portalUploadUrl(): string
    {
        return (string) (config('services.chargeback.nexio.portal_url')
            ?? 'https://dashboard.nexiopay.com/');
    }

    public function submissionInstructions(): string
    {
        return 'Dashboard → Disputes → open case → Add Evidence → upload this PDF.';
    }
}

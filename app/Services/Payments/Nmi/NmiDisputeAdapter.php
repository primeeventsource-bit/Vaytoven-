<?php

namespace App\Services\Payments\Nmi;

use App\Services\Payments\PortalPdfDisputeAdapter;

/**
 * NMI's chargeback workflow lives under the Control Panel > Disputes tab.
 * No public submission API; merchants reply with a PDF attachment.
 */
class NmiDisputeAdapter extends PortalPdfDisputeAdapter
{
    public function processorKey(): string
    {
        return 'nmi';
    }

    public function portalUploadUrl(): string
    {
        return (string) (config('services.chargeback.nmi.portal_url')
            ?? 'https://secure.nmi.com/merchants/');
    }

    public function submissionInstructions(): string
    {
        return 'Control Panel → Disputes → open the case → Add Response → upload this PDF.';
    }
}

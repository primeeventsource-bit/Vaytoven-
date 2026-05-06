<?php

namespace App\Services\Payments\AuthorizeNet;

use App\Services\Payments\PortalPdfDisputeAdapter;

/**
 * Authorize.Net rebuttals are filed through the Merchant Interface > Reports
 * > Chargeback Statistics page — operator uploads the cert PDF as supporting
 * documentation and a one-paragraph rebuttal.
 */
class AuthorizeNetDisputeAdapter extends PortalPdfDisputeAdapter
{
    public function processorKey(): string
    {
        return 'authorizenet';
    }

    public function portalUploadUrl(): string
    {
        return (string) (config('services.chargeback.authorizenet.portal_url')
            ?? 'https://account.authorize.net/');
    }

    public function submissionInstructions(): string
    {
        return 'Reports → Chargeback Statistics → select dispute → Reply with this PDF as the supporting document.';
    }
}

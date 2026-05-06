<?php

namespace App\Services\Payments\AuthorizeNet;

use App\Exceptions\NotImplementedException;
use App\Services\Chargeback\EvidenceBundle;
use App\Services\Payments\DisputeAdapter;
use App\Services\Payments\DisputeSubmissionResult;

/**
 * Authorize.Net dispute adapter (Phase 12 deliverable).
 *
 * Authorize.Net has a chargeback rebuttal API; this stub exists so calling
 * code can resolve the right adapter today. Implementation pending counsel
 * sign-off on the rebuttal letter template.
 */
class AuthorizeNetDisputeAdapter implements DisputeAdapter
{
    public function submit(string $externalDisputeId, EvidenceBundle $bundle): DisputeSubmissionResult
    {
        throw NotImplementedException::for(self::class, 'Authorize.Net adapter pending Phase 12');
    }
}

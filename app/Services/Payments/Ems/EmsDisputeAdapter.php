<?php

namespace App\Services\Payments\Ems;

use App\Exceptions\NotImplementedException;
use App\Services\Chargeback\EvidenceBundle;
use App\Services\Payments\DisputeAdapter;
use App\Services\Payments\DisputeSubmissionResult;

/**
 * EMS dispute adapter (Phase 12 deliverable).
 *
 * Stub exists so DisputeAdapterRegistry can resolve the right adapter today.
 * Implementation pending Phase 12 ? for processors without a structured API,
 * this will produce a printable PDF for portal upload (mode='manual_pdf').
 */
class EmsDisputeAdapter implements DisputeAdapter
{
    public function submit(string $externalDisputeId, EvidenceBundle $bundle): DisputeSubmissionResult
    {
        throw NotImplementedException::for(self::class, 'EMS adapter pending Phase 12');
    }
}

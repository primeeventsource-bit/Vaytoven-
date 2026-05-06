<?php

namespace App\Services\Payments;

use App\Services\Chargeback\EvidenceBundle;

interface DisputeAdapter
{
    /**
     * Format an EvidenceBundle into the processor's expected submission shape
     * and submit (or return a payload for portal upload, depending on processor).
     *
     * Returns a transport-agnostic result describing what was sent + the
     * processor's response (when it has an API).
     *
     * @param string $externalDisputeId The processor's dispute reference
     */
    public function submit(string $externalDisputeId, EvidenceBundle $bundle): DisputeSubmissionResult;
}

<?php

namespace App\Services\Payments;

use App\Services\Chargeback\ChargebackCertificateService;
use App\Services\Chargeback\EvidenceBundle;
use Illuminate\Support\Facades\Storage;

/**
 * Base adapter for processors without a structured submission API.
 *
 * The reality: none of the acquirers Vaytoven supports exposes a clean
 * disputes API — they all accept rebuttals through their merchant portal
 * as an uploaded PDF (and sometimes a transcribed cover note).
 *
 * What this base does for those processors:
 *   1. Renders the Service Usage Confirmation Certificate as a PDF using
 *      the same template ops attaches to manual rebuttals today.
 *   2. Persists the PDF to a per-processor storage path so ops can find it
 *      without spelunking through artifact ids.
 *   3. Returns a DisputeSubmissionResult with mode='manual_pdf' carrying
 *      the artifact path and the processor's portal URL in the note —
 *      everything the operator needs to upload, copy-pasted from the row.
 *
 * Concrete subclasses provide:
 *   - processorKey():           kebab-case identifier matching DisputeAdapterRegistry
 *   - portalUploadUrl():        where the operator uploads the PDF
 *   - submissionInstructions(): short note guiding the operator
 *
 * This is deliberately not an "API call" mock. Wiring real REST submissions
 * for each processor requires sandbox credentials per acquirer; the interface
 * stays open for those upgrades but ships value today by giving every
 * processor a working, reproducible artifact pipeline.
 */
abstract class PortalPdfDisputeAdapter implements DisputeAdapter
{
    public function __construct(protected readonly ChargebackCertificateService $certificates)
    {
    }

    public function submit(string $externalDisputeId, EvidenceBundle $bundle): DisputeSubmissionResult
    {
        $pdf = $this->certificates->forBundle($bundle);
        $path = $this->artifactPath($externalDisputeId);

        Storage::put($path, $pdf);

        return new DisputeSubmissionResult(
            processor:           $this->processorKey(),
            external_dispute_id: $externalDisputeId,
            mode:                'manual_pdf',
            artifact_path:       $path,
            api_response:        null,
            note:                sprintf(
                'Upload %s to %s — %s',
                basename($path),
                $this->portalUploadUrl(),
                $this->submissionInstructions(),
            ),
        );
    }

    abstract public function processorKey(): string;

    abstract public function portalUploadUrl(): string;

    abstract public function submissionInstructions(): string;

    /**
     * Storage-relative path used for the rendered PDF. Includes the dispute
     * id so multiple disputes per processor don't collide.
     */
    protected function artifactPath(string $externalDisputeId): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_\-]/', '_', $externalDisputeId) ?: 'dispute';
        return sprintf('disputes/%s/%s.pdf', $this->processorKey(), $safe);
    }
}

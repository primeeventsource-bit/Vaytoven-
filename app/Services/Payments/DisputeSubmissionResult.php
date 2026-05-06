<?php

namespace App\Services\Payments;

final readonly class DisputeSubmissionResult
{
    public function __construct(
        public string $processor,            // 'stripe', 'authorizenet', etc.
        public string $external_dispute_id,
        public string $mode,                 // 'api' (auto-submitted) | 'manual_pdf' (operator uploads)
        public ?string $artifact_path = null, // s3:// or local path for the PDF / JSON dump
        public ?array $api_response = null,   // when mode=api, the processor's response payload
        public ?string $note = null,
    ) {
    }
}

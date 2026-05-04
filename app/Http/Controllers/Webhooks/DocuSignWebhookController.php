<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ContractEvent;
use App\Services\DocuSign\EnvelopeService;
use App\Services\DocuSign\WebhookVerifier;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Receives DocuSign Connect webhook events and updates the Contract row +
 * appends to contract_events.
 *
 * Configure in DocuSign Admin -> Connect:
 *   - URL: https://{vaytoven-host}/webhooks/docusign
 *   - Format: JSON
 *   - Events: envelope-sent, envelope-delivered, recipient-completed,
 *             envelope-completed, envelope-declined, envelope-voided,
 *             recipient-authenticationfailed, recipient-resent,
 *             recipient-reassign
 *   - Include HMAC Signature: enabled, key copied to DOCUSIGN_HMAC_KEYS
 *
 * This route is intentionally `web` (not `api`) because DocuSign's payload
 * doesn't carry a CSRF token; we exclude it from VerifyCsrfToken in
 * bootstrap/app.php (see docs/docusign-setup.md).
 */
class DocuSignWebhookController extends Controller
{
    public function __construct(
        private readonly WebhookVerifier $verifier,
        private readonly EnvelopeService $envelopes,
    ) {}

    public function __invoke(Request $request): Response
    {
        $raw = $request->getContent();
        if (! $this->verifier->verify($raw, $request->headers->all())) {
            Log::channel('errorlog')->warning('DocuSign webhook rejected: signature mismatch.', [
                'ip' => $request->ip(),
            ]);
            return response('forbidden', 403);
        }

        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            return response('bad payload', 400);
        }

        // DocuSign Connect's modern JSON format wraps everything in `data`.
        // Older SOAP-style or completed-envelope formats vary slightly; we
        // try the most common shapes and fall back to the root.
        $envelope = $payload['data']['envelopeSummary'] ?? $payload['envelope'] ?? $payload['data'] ?? $payload;
        $envelopeId = $payload['data']['envelopeId']
            ?? $envelope['envelopeId']
            ?? $envelope['envelope_id']
            ?? null;

        if (! $envelopeId) {
            return response('missing envelope id', 400);
        }

        $contract = Contract::where('envelope_id', $envelopeId)->first();
        if (! $contract) {
            // Likely a webhook for an envelope sent outside this system or
            // before our DB row was committed; ack so DocuSign stops retrying.
            Log::channel('errorlog')->info('DocuSign webhook for unknown envelope.', [
                'envelope_id' => $envelopeId,
            ]);
            return response('ok', 200);
        }

        $eventType = $this->mapEvent(
            $payload['event'] ?? $payload['data']['event'] ?? $envelope['status'] ?? null
        );

        $occurredAt = $this->parseTimestamp(
            $payload['generatedDateTime']
            ?? $envelope['statusChangedDateTime']
            ?? $payload['data']['generatedDateTime']
            ?? null
        );

        $signer = $this->primarySigner($envelope);

        ContractEvent::create([
            'contract_id'     => $contract->id,
            'event_type'      => $eventType,
            'occurred_at'     => $occurredAt,
            'recipient_id'    => $signer['recipientId'] ?? null,
            'recipient_email' => $signer['email'] ?? null,
            'ip_address'      => $signer['ipAddress'] ?? $request->ip(),
            'user_agent'      => $signer['userAgent'] ?? null,
            'raw_payload'     => $payload,
        ]);

        $this->applyToContract($contract, $eventType, $occurredAt, $signer);

        if ($eventType === ContractEvent::EVENT_COMPLETED) {
            try {
                $this->envelopes->pullCompletedDocuments($contract);
            } catch (\Throwable $e) {
                Log::channel('errorlog')->error('Failed to pull completed DocuSign documents.', [
                    'contract_id' => $contract->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        return response('ok', 200);
    }

    private function applyToContract(Contract $contract, string $eventType, Carbon $occurredAt, array $signer): void
    {
        $update = [];

        match ($eventType) {
            ContractEvent::EVENT_SENT => $update = [
                'status' => Contract::STATUS_SENT, 'sent_at' => $occurredAt,
            ],
            ContractEvent::EVENT_DELIVERED => $update = [
                'status' => Contract::STATUS_DELIVERED,
            ],
            ContractEvent::EVENT_VIEWED => $update = [
                'status' => Contract::STATUS_VIEWED, 'viewed_at' => $occurredAt,
            ],
            ContractEvent::EVENT_SIGNED => $update = [
                'status' => Contract::STATUS_SIGNED, 'signed_at' => $occurredAt,
            ],
            ContractEvent::EVENT_COMPLETED => $update = [
                'status' => Contract::STATUS_COMPLETED, 'completed_at' => $occurredAt,
            ],
            ContractEvent::EVENT_DECLINED => $update = [
                'status' => Contract::STATUS_DECLINED, 'declined_at' => $occurredAt,
            ],
            ContractEvent::EVENT_VOIDED => $update = [
                'status' => Contract::STATUS_VOIDED, 'voided_at' => $occurredAt,
            ],
            default => null,
        };

        if ($ip = $signer['ipAddress'] ?? null) {
            $update['last_signer_ip'] = $ip;
        }
        if ($ua = $signer['userAgent'] ?? null) {
            $update['last_signer_user_agent'] = $ua;
        }

        if (! empty($update)) {
            $contract->forceFill($update)->save();
        }
    }

    private function mapEvent(?string $raw): string
    {
        $r = strtolower(str_replace('-', '_', (string) $raw));
        return match (true) {
            str_contains($r, 'recipient_authenticationfailed') => ContractEvent::EVENT_AUTH_FAILED,
            str_contains($r, 'envelope_completed'),
            str_contains($r, 'completed') => ContractEvent::EVENT_COMPLETED,
            str_contains($r, 'recipient_completed'),
            str_contains($r, 'signed')    => ContractEvent::EVENT_SIGNED,
            str_contains($r, 'recipient_viewed'),
            str_contains($r, 'viewed')    => ContractEvent::EVENT_VIEWED,
            str_contains($r, 'recipient_delivered'),
            str_contains($r, 'delivered') => ContractEvent::EVENT_DELIVERED,
            str_contains($r, 'declined')  => ContractEvent::EVENT_DECLINED,
            str_contains($r, 'voided')    => ContractEvent::EVENT_VOIDED,
            str_contains($r, 'reassign')  => ContractEvent::EVENT_REASSIGNED,
            str_contains($r, 'resent')    => ContractEvent::EVENT_RESENT,
            str_contains($r, 'sent')      => ContractEvent::EVENT_SENT,
            default                       => ContractEvent::EVENT_DELIVERED,
        };
    }

    private function parseTimestamp(?string $iso): Carbon
    {
        try {
            return $iso ? Carbon::parse($iso) : now();
        } catch (\Throwable) {
            return now();
        }
    }

    /**
     * Pull the most relevant signer from the payload (first one in the
     * "recipients.signers" array, which is the primary recipient).
     */
    private function primarySigner(array $envelope): array
    {
        return $envelope['recipients']['signers'][0] ?? [];
    }
}

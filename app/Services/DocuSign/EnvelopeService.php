<?php

namespace App\Services\DocuSign;

use App\Models\Contract;
use DocuSign\eSign\Api\EnvelopesApi;
use DocuSign\eSign\Model\Document;
use DocuSign\eSign\Model\EnvelopeDefinition;
use DocuSign\eSign\Model\Recipients;
use DocuSign\eSign\Model\Signer;
use DocuSign\eSign\Model\SignHere;
use DocuSign\eSign\Model\Tabs;
use DocuSign\eSign\Model\TemplateRole;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Wraps DocuSign envelope operations:
 *   - Send a contract from a template OR an uploaded PDF
 *   - Generate the recipient signing-view URL (embedded signing) so the
 *     client signs without leaving the Vaytoven UI
 *   - Pull the signed PDF and the Certificate of Completion after
 *     "completed" webhook fires, persist to local storage, and write
 *     the paths back onto the Contract row
 */
class EnvelopeService
{
    public function __construct(
        private readonly DocuSignClient $client,
    ) {}

    /**
     * Create and send a DocuSign envelope for the given Contract row.
     * Returns the envelope_id on success and writes it onto the Contract.
     */
    public function send(Contract $contract, ?string $pdfPath = null): string
    {
        $api = new EnvelopesApi($this->client->api());

        $definition = new EnvelopeDefinition([
            'email_subject' => "Please sign: {$contract->title}",
            'status'        => 'sent',
        ]);

        if ($contract->template_id) {
            $definition->setTemplateId($contract->template_id);
            $definition->setTemplateRoles([
                new TemplateRole([
                    'email'     => $contract->client_email,
                    'name'      => $contract->client_name,
                    'role_name' => 'signer',
                    'client_user_id' => $this->clientUserId($contract), // marks as embedded
                ]),
            ]);
        } else {
            if (! $pdfPath || ! is_readable($pdfPath)) {
                throw new RuntimeException('A readable PDF path is required when no template_id is set on the contract.');
            }

            $definition->setDocuments([
                new Document([
                    'document_base64' => base64_encode((string) file_get_contents($pdfPath)),
                    'name'            => basename($pdfPath),
                    'file_extension'  => 'pdf',
                    'document_id'     => '1',
                ]),
            ]);

            $definition->setRecipients(new Recipients([
                'signers' => [
                    new Signer([
                        'email'         => $contract->client_email,
                        'name'          => $contract->client_name,
                        'recipient_id'  => '1',
                        'routing_order' => '1',
                        'client_user_id' => $this->clientUserId($contract),
                        'tabs'          => new Tabs([
                            'sign_here_tabs' => [
                                new SignHere([
                                    'document_id' => '1',
                                    'page_number' => '1',
                                    'x_position'  => '100',
                                    'y_position'  => '700',
                                ]),
                            ],
                        ]),
                    ]),
                ],
            ]));
        }

        try {
            $result = $api->createEnvelope($this->client->accountId(), $definition);
        } catch (Throwable $e) {
            throw new RuntimeException('DocuSign envelope creation failed: ' . $e->getMessage(), 0, $e);
        }

        $envelopeId = $result->getEnvelopeId();

        $contract->forceFill([
            'envelope_id' => $envelopeId,
            'status'      => Contract::STATUS_SENT,
            'sent_at'     => now(),
        ])->save();

        return $envelopeId;
    }

    /**
     * Build a one-time embedded signing URL the client can be redirected to.
     * URLs expire in 5 minutes by DocuSign default — generate fresh per click.
     */
    public function recipientViewUrl(Contract $contract, string $returnUrl): string
    {
        if (! $contract->envelope_id) {
            throw new RuntimeException('Contract has no envelope_id; send it first.');
        }

        $api = new EnvelopesApi($this->client->api());

        $request = new \DocuSign\eSign\Model\RecipientViewRequest([
            'authentication_method' => 'none',
            'client_user_id'        => $this->clientUserId($contract),
            'recipient_id'          => '1',
            'return_url'            => $returnUrl,
            'user_name'             => $contract->client_name,
            'email'                 => $contract->client_email,
        ]);

        $view = $api->createRecipientView(
            $this->client->accountId(),
            $contract->envelope_id,
            $request
        );

        return $view->getUrl();
    }

    /**
     * Download the signed combined PDF and the Certificate of Completion,
     * persist them to the configured filesystem disk, and write the paths
     * back onto the Contract.
     */
    public function pullCompletedDocuments(Contract $contract, string $disk = 'local'): void
    {
        if (! $contract->envelope_id) {
            return;
        }

        $api = new EnvelopesApi($this->client->api());
        $accountId = $this->client->accountId();

        $combinedPath = $api->getDocument($accountId, 'combined', $contract->envelope_id);
        $certPath     = $api->getDocument($accountId, 'certificate', $contract->envelope_id);

        $signedKey = "contracts/{$contract->id}/signed.pdf";
        $certKey   = "contracts/{$contract->id}/certificate.pdf";

        Storage::disk($disk)->put($signedKey, file_get_contents($combinedPath));
        Storage::disk($disk)->put($certKey,   file_get_contents($certPath));

        $contract->forceFill([
            'signed_pdf_path'      => $signedKey,
            'certificate_pdf_path' => $certKey,
        ])->save();
    }

    public function void(Contract $contract, string $reason = 'Voided by sender.'): void
    {
        if (! $contract->envelope_id) {
            return;
        }

        $api = new EnvelopesApi($this->client->api());

        $update = new \DocuSign\eSign\Model\Envelope([
            'status'        => 'voided',
            'voided_reason' => $reason,
        ]);

        $api->update($this->client->accountId(), $contract->envelope_id, $update);

        $contract->forceFill([
            'status'    => Contract::STATUS_VOIDED,
            'voided_at' => now(),
        ])->save();
    }

    /**
     * client_user_id is what makes a recipient "embedded" (signs in our UI)
     * vs "remote" (gets a DocuSign-hosted signing email). Stable per contract
     * is fine since each contract is one-shot.
     */
    private function clientUserId(Contract $contract): string
    {
        return 'vaytoven-contract-'.$contract->id;
    }
}

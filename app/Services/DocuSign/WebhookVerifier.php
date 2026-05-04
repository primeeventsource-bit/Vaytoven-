<?php

namespace App\Services\DocuSign;

/**
 * Verifies the HMAC signature DocuSign Connect attaches to webhook payloads.
 *
 * Configure in DocuSign Admin -> Connect -> Edit Configuration -> "Include
 * HMAC Signature". Up to 10 keys may be active; we accept any that matches.
 *
 * The signature is the raw POST body, HMAC-SHA256, base64-encoded, sent as
 * the X-DocuSign-Signature-1 (...-2, ...-3) header.
 */
class WebhookVerifier
{
    /**
     * @param array<int,string> $keys Configured HMAC keys (any may match).
     */
    public function __construct(private readonly array $keys) {}

    /**
     * @param array<string,string|array<string>> $headers
     */
    public function verify(string $rawBody, array $headers): bool
    {
        if (empty($this->keys)) {
            // No keys configured — fail closed; we never want to accept
            // unverified webhooks in any real environment.
            return false;
        }

        $providedSignatures = $this->collectSignatureHeaders($headers);
        if (empty($providedSignatures)) {
            return false;
        }

        foreach ($this->keys as $key) {
            $expected = base64_encode(hash_hmac('sha256', $rawBody, $key, true));

            foreach ($providedSignatures as $provided) {
                if (hash_equals($expected, $provided)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string,string|array<string>> $headers
     * @return array<int,string>
     */
    private function collectSignatureHeaders(array $headers): array
    {
        $sigs = [];
        foreach ($headers as $name => $value) {
            $lower = strtolower((string) $name);
            if (str_starts_with($lower, 'x-docusign-signature-')) {
                $sigs[] = is_array($value) ? ($value[0] ?? '') : $value;
            }
        }
        return array_filter($sigs);
    }
}

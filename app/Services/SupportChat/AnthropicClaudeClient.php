<?php

namespace App\Services\SupportChat;

use GuzzleHttp\Client as Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Production Claude client. Hits api.anthropic.com/v1/messages directly via
 * Guzzle so we don't depend on the SDK's release cadence.
 *
 * If ANTHROPIC_API_KEY is missing or invalid, throws SupportChatUnavailable
 * — the controller layer turns that into a graceful "support is temporarily
 * unavailable" response (FR-11.10).
 */
class AnthropicClaudeClient implements ClaudeClient
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';

    public function __construct(
        private readonly string $apiKey,
        private readonly Http $http = new Http(),
        private readonly int $timeout = 30,
    ) {
    }

    public function send(array $messages, array $tools, string $systemPrompt, string $model): array
    {
        if (empty($this->apiKey)) {
            throw new SupportChatUnavailable('ANTHROPIC_API_KEY is not configured');
        }

        try {
            $response = $this->http->post(self::API_URL, [
                'headers' => [
                    'x-api-key' => $this->apiKey,
                    'anthropic-version' => self::API_VERSION,
                    'content-type' => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'max_tokens' => 1024,
                    'system' => $systemPrompt,
                    'messages' => $messages,
                    'tools' => $tools,
                ],
                'timeout' => $this->timeout,
            ]);

            return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            Log::error('Claude API call failed: '.$e->getMessage(), ['exception' => $e]);
            throw new SupportChatUnavailable('Claude API call failed', previous: $e);
        }
    }
}

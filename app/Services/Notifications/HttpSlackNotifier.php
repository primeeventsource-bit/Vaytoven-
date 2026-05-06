<?php

namespace App\Services\Notifications;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Log;
use Throwable;

class HttpSlackNotifier implements SlackNotifier
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly string $webhookUrl,
    ) {
    }

    public function send(array $payload): void
    {
        try {
            $response = $this->http
                ->timeout(5)
                ->acceptJson()
                ->asJson()
                ->post($this->webhookUrl, $payload);

            if (! $response->successful()) {
                Log::warning('slack notify: non-2xx from webhook', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
            }
        } catch (Throwable $e) {
            // Swallow — operator triages from logs. Slack is a soft-fail signal.
            Log::warning('slack notify: transport error: '.$e->getMessage());
        }
    }
}

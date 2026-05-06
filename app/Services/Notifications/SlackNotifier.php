<?php

namespace App\Services\Notifications;

/**
 * Posts a message to a Slack channel via incoming webhook.
 *
 * Implementations:
 *   - HttpSlackNotifier: posts JSON to the configured webhook URL.
 *   - NoOpSlackNotifier: silently drops the message (used when no webhook
 *     is configured, in tests by default, and as a graceful no-op so jobs
 *     don't fail loudly when ops hasn't wired Slack yet).
 *
 * Implementations MUST NOT throw on transport errors — the dispatching job
 * catches and logs, but a thrown exception in production would queue retries
 * for what is fundamentally a fire-and-forget signal.
 */
interface SlackNotifier
{
    /**
     * @param array<string, mixed> $payload Slack message payload (text, blocks, etc.)
     */
    public function send(array $payload): void;
}

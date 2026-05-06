<?php

namespace App\Services\Notifications;

/**
 * Drops messages on the floor. Bound when SLACK_OPS_WEBHOOK_URL is unset
 * so local dev and CI environments don't need a real webhook.
 */
class NoOpSlackNotifier implements SlackNotifier
{
    public function send(array $payload): void
    {
        // intentionally empty
    }
}

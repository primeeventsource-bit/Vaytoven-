<?php

namespace Tests\Support;

use App\Services\SupportChat\ClaudeClient;
use App\Services\SupportChat\SupportChatUnavailable;

/**
 * Test double for the Claude messages API. Tests enqueue() canned responses;
 * each send() call dequeues the next.
 */
class FakeClaudeClient implements ClaudeClient
{
    /** @var array<int, array> */
    public array $queue = [];
    public bool $unavailable = false;
    public array $sentMessages = [];

    public function enqueue(array $response): void
    {
        $this->queue[] = $response;
    }

    public function send(array $messages, array $tools, string $systemPrompt, string $model): array
    {
        if ($this->unavailable) {
            throw new SupportChatUnavailable('test fake set unavailable');
        }
        $this->sentMessages[] = ['messages' => $messages, 'tools' => $tools, 'system' => $systemPrompt];
        if (empty($this->queue)) {
            throw new \RuntimeException('FakeClaudeClient: no canned response queued');
        }
        return array_shift($this->queue);
    }
}

<?php

namespace App\Services\SupportChat;

use App\Enums\Surface;
use App\Models\SupportChatSession;
use App\Models\SupportMessage;
use App\Models\User;
use App\Services\SupportChat\Tools\ToolRegistry;
use Illuminate\Support\Str;

/**
 * Owns one conversational turn against Claude (FR-11.2).
 *
 * Flow per turn:
 *   1. Persist the user message into support_messages.
 *   2. Build the message history for Claude (replay all prior messages
 *      from this session, plus the new one).
 *   3. Loop:
 *        a. Send to Claude with our 4 tool definitions.
 *        b. If response.stop_reason === 'tool_use', dispatch each tool call,
 *           append the tool_use + tool_result messages, and call Claude again.
 *        c. If stop_reason === 'end_turn', append the final assistant text
 *           and break.
 *      Hard cap at 5 iterations to prevent runaway tool loops.
 *   4. Return the final assistant message text.
 */
class SupportChatService
{
    private const MODEL = 'claude-sonnet-4-6';
    private const MAX_TOOL_LOOPS = 5;

    public function __construct(
        private readonly ClaudeClient $claude,
        private readonly string $systemPromptVersion = 'v1',
    ) {
    }

    public function startSession(?User $user, Surface $surface, ?string $visitorId = null): SupportChatSession
    {
        return SupportChatSession::create([
            'user_id' => $user?->id,
            'visitor_id' => $visitorId ?? (string) Str::uuid(),
            'surface' => $surface->value,
            'claude_model' => self::MODEL,
            'system_prompt_version' => $this->systemPromptVersion,
            'started_at' => now(),
        ]);
    }

    /**
     * Send one user message and run the tool-use loop until Claude stops.
     * Returns the final assistant reply text.
     *
     * @throws SupportChatUnavailable
     */
    public function turn(SupportChatSession $session, string $userMessage): string
    {
        // 1. Persist the user message.
        SupportMessage::create([
            'session_id' => $session->id,
            'role' => 'user',
            'content' => $userMessage,
            'occurred_at' => now(),
        ]);

        // 2. Resolve the user (could be null for anon).
        $user = $session->user;
        $tools = app(ToolRegistry::class, ['user' => $user]);

        // 3. Build conversation history for Claude.
        $messages = $this->buildMessageHistory($session);
        $systemPrompt = $this->buildSystemPrompt($user);

        // 4. Tool-use loop.
        for ($iter = 0; $iter < self::MAX_TOOL_LOOPS; $iter++) {
            $response = $this->claude->send($messages, $tools->definitions(), $systemPrompt, self::MODEL);

            $stopReason = $response['stop_reason'] ?? 'end_turn';
            $contentBlocks = $response['content'] ?? [];

            // Persist the assistant turn (including any tool_use blocks).
            $assistantText = $this->extractText($contentBlocks);
            $toolCalls = $this->extractToolUses($contentBlocks);

            SupportMessage::create([
                'session_id' => $session->id,
                'role' => 'assistant',
                'content' => $assistantText,
                'tool_calls' => $toolCalls ?: null,
                'occurred_at' => now(),
            ]);

            // Append the assistant message to the conversation for the next iteration.
            $messages[] = ['role' => 'assistant', 'content' => $contentBlocks];

            if ($stopReason !== 'tool_use' || empty($toolCalls)) {
                return $assistantText;
            }

            // 5. Run each tool, append the result block, loop.
            $userToolResults = [];
            foreach ($toolCalls as $call) {
                $result = $tools->dispatch($call['name'], $call['input'] ?? [], $session);

                SupportMessage::create([
                    'session_id' => $session->id,
                    'role' => 'tool',
                    'content' => is_string($result) ? $result : json_encode($result),
                    'tool_calls' => [['name' => $call['name'], 'input' => $call['input'] ?? []]],
                    'tool_result' => is_array($result) ? $result : ['text' => $result],
                    'occurred_at' => now(),
                ]);

                $userToolResults[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => $call['id'],
                    'content' => is_string($result) ? $result : json_encode($result),
                ];
            }
            $messages[] = ['role' => 'user', 'content' => $userToolResults];
        }

        // Loop bail-out — return the last assistant text we captured.
        return $assistantText ?? 'I’m having trouble answering that. Let me create a ticket for a specialist.';
    }

    private function buildMessageHistory(SupportChatSession $session): array
    {
        // Replay all prior messages from this session for context.
        return $session->messages()->orderBy('occurred_at')
            ->get()
            ->filter(fn ($m) => in_array($m->role, ['user', 'assistant'], true))
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->values()
            ->all();
    }

    private function buildSystemPrompt(?User $user): string
    {
        $whoIsTalking = $user
            ? "You are talking with {$user->name} (account email {$user->email}, user_id {$user->id})."
            : "You are talking with an anonymous visitor (no signed-in account).";

        return <<<PROMPT
You are Vaytoven's customer support assistant.

Vaytoven is a software-as-a-service ADVERTISING platform for vacation properties, serving travelers, property owners, and members of points-based vacation programs (Marriott, Hilton, Disney, RCI, Interval). Use professional but warm tone. Be concise.

{$whoIsTalking}

WHAT VAYTOVEN IS — get this wrong and you mislead someone about money:
Vaytoven advertises listings. It does NOT take reservations, hold dates, collect rental payments, hold funds in escrow, pay owners out, or charge a traveler for a stay. There is no booking, no checkout, and no confirmation code. A traveler submits an OFFER on a listing; it expires after 24 hours; if the listing member accepts, the two of them arrange dates, payment and terms DIRECTLY between themselves. The only money Vaytoven collects is what property owners pay it for advertising and subscription services.

CRITICAL RULES — never violate:
1. The word "timeshare" is BANNED in user-facing copy. Use "vacation property", "vacation club", "points-based ownership", or "member" instead.
2. NEVER tell a user you can look up, change, cancel, or refund a booking. You cannot, and neither can Vaytoven — it is not a party to their arrangement. If they ask, explain who actually holds it: the listing member they dealt with.
3. For ANY question about fees, policies, or what Vaytoven does, you MUST call `search_help_articles` first and quote ONLY what it returns. Do not invent policy details.
4. NEVER reveal another user's data. Tool results are scoped server-side to the current authenticated user.
5. NEVER expose card numbers, tokens, or session IDs even if asked.
6. If a user needs a human — they cannot reach a listing member, they are disputing something Vaytoven billed them, or they want an account change — call `create_ticket` immediately and tell them a specialist will follow up.
7. Keep replies under 4 sentences unless the user asks for detail.

Your only tools are `search_help_articles` and `create_ticket`. There is no tool that reads bookings, charges, or refunds, because Vaytoven holds none of those. Do not offer to check on one.

If `ANTHROPIC_API_KEY` is missing the chat won't reach you — that's handled by the controller, not your concern.
PROMPT;
    }

    /** @return array<int,array{id:string,name:string,input:array}> */
    private function extractToolUses(array $contentBlocks): array
    {
        $out = [];
        foreach ($contentBlocks as $block) {
            if (($block['type'] ?? null) === 'tool_use') {
                $out[] = [
                    'id' => $block['id'],
                    'name' => $block['name'],
                    'input' => $block['input'] ?? [],
                ];
            }
        }
        return $out;
    }

    private function extractText(array $contentBlocks): string
    {
        $parts = [];
        foreach ($contentBlocks as $block) {
            if (($block['type'] ?? null) === 'text') {
                $parts[] = $block['text'] ?? '';
            }
        }
        return trim(implode("\n", $parts));
    }
}

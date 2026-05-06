<?php

namespace App\Services\SupportChat;

interface ClaudeClient
{
    /**
     * Send a single turn to Claude's Messages API and return the parsed response.
     *
     * @param  array  $messages   Conversation history in Anthropic message format
     *                            [['role'=>'user'|'assistant', 'content'=>...], ...]
     * @param  array  $tools      Tool definitions (Anthropic schema)
     * @param  string $systemPrompt
     * @param  string $model      e.g. 'claude-sonnet-4-6'
     *
     * @return array Anthropic response — keys: id, role, content[] (each item:
     *               type=text|tool_use, plus type-specific fields), stop_reason,
     *               usage{input_tokens, output_tokens}
     */
    public function send(array $messages, array $tools, string $systemPrompt, string $model): array;
}

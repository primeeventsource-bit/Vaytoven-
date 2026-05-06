<?php

namespace App\Http\Controllers\Api;

use App\Enums\Surface;
use App\Http\Controllers\Controller;
use App\Models\SupportChatSession;
use App\Services\SupportChat\SupportChatService;
use App\Services\SupportChat\SupportChatUnavailable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SupportChatController extends Controller
{
    public function __construct(private readonly SupportChatService $chat)
    {
    }

    /**
     * POST /api/v1/support/chat
     * Body: { session_id?: int, message: string, visitor_id?: string }
     *
     * If no session_id is given, opens a new session for this surface.
     * Returns: { session_id, reply }
     *
     * On Claude unavailability (FR-11.10), returns 503 with a generic
     * graceful-fallback message — no internal error leaks.
     */
    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => ['nullable', 'integer', 'exists:support_chat_sessions,id'],
            'message' => ['required', 'string', 'max:4000'],
            'visitor_id' => ['nullable', 'string', 'max:36'],
        ]);

        $surface = $request->attributes->get('vaytoven_surface');
        if (! $surface instanceof Surface) {
            $surface = Surface::tryFrom((string) $surface) ?? Surface::Web;
        }

        $session = $request->integer('session_id')
            ? SupportChatSession::findOrFail($request->integer('session_id'))
            : $this->chat->startSession($request->user(), $surface, $request->input('visitor_id'));

        try {
            $reply = $this->chat->turn($session, $request->string('message')->toString());
        } catch (SupportChatUnavailable $e) {
            Log::warning('Support chat unavailable: '.$e->getMessage());
            return response()->json([
                'session_id' => $session->id,
                'reply' => "I'm temporarily unable to respond. Please try again in a few minutes, or email support@vaytoven.com for urgent issues.",
                'error' => 'support_chat_unavailable',
            ], 503);
        }

        return response()->json([
            'session_id' => $session->id,
            'reply' => $reply,
        ]);
    }
}

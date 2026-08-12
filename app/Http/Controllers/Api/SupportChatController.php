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
    public function __construct(private readonly SupportChatService $chat) {}

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
        // Admin kill switch (Settings console): both the ai_chat.enabled
        // setting and the ai_chat feature flag must be on. Server-side
        // rejection — hiding the widget alone is not a gate.
        if (! setting('ai_chat.enabled', true) || ! feature('ai_chat')) {
            return response()->json([
                // Read from settings rather than a literal: the fallback message
                // is the ONLY thing a visitor gets when chat is down, so a stale
                // hardcoded address here is a dead end at exactly the wrong moment.
                'reply' => 'Live chat is currently unavailable. Please email '
                    .setting('general.support_email', 'contact@vaytoven.com')
                    .' and we will get back to you.',
                'error' => 'support_chat_disabled',
            ], 503);
        }

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
            ? $this->resumeSession($request)
            : $this->chat->startSession($request->user(), $surface, $request->input('visitor_id'));

        try {
            $reply = $this->chat->turn($session, $request->string('message')->toString());
        } catch (SupportChatUnavailable $e) {
            Log::warning('Support chat unavailable: '.$e->getMessage());

            return response()->json([
                'session_id' => $session->id,
                'reply' => "I'm temporarily unable to respond. Please try again in a few minutes, or email "
                    .setting('general.support_email', 'contact@vaytoven.com').' for urgent issues.',
                'error' => 'support_chat_unavailable',
            ], 503);
        }

        return response()->json([
            'session_id' => $session->id,
            'reply' => $reply,
        ]);
    }

    /**
     * Resume an existing chat session — but only the caller's own.
     *
     * This endpoint is public, and `session_id` is a sequential integer, so
     * without an ownership check anyone could enumerate ids and resume a
     * stranger's conversation. That is not merely a privacy leak of the
     * transcript: SupportChatService rebuilds the tool registry as the SESSION
     * OWNER, so the caller would inherit that user's identity and be able to
     * ask the assistant for their bookings and recent charges. The registry's
     * "every tool scopes to the session user" invariant only protects against
     * prompt injection — it assumes the session belongs to the caller.
     *
     * Ownership is established two ways, matching how sessions are created:
     *   - signed in  -> the session's user_id must be the caller
     *   - anonymous  -> the session's visitor_id must match the caller's
     *
     * A mismatch is a 404, not a 403: confirming that an id exists but belongs
     * to someone else is itself an enumeration oracle.
     */
    private function resumeSession(Request $request): SupportChatSession
    {
        $session = SupportChatSession::findOrFail($request->integer('session_id'));
        $user = $request->user();

        if ($session->user_id !== null) {
            abort_unless($user && $session->user_id === $user->id, 404);

            return $session;
        }

        // Anonymous session: the visitor id is the only thing tying it to a
        // browser, so it must be supplied and must match exactly.
        $visitorId = (string) $request->input('visitor_id');

        abort_if(
            $visitorId === '' || $session->visitor_id === null || ! hash_equals((string) $session->visitor_id, $visitorId),
            404,
        );

        return $session;
    }
}

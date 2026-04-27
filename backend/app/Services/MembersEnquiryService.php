<?php

namespace App\Services;

use App\Mail\MembersEnquiryReceived;
use App\Models\MembersEnquiry;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class MembersEnquiryService
{
    /**
     * Ingest a validated submission from the public form.
     * Persists the row, computes a spam score, and fans out notifications.
     *
     * @param  array<string,mixed>  $data    Validated form fields.
     * @param  Request              $request HTTP request (for IP, UA, source inference).
     */
    public function ingest(array $data, Request $request): MembersEnquiry
    {
        return DB::transaction(function () use ($data, $request) {
            $ip = $request->ip();
            $ua = $request->userAgent();

            // Compute spam score before insert so flagged rows are excluded from default queue
            $score = $this->computeSpamScore($data, $request);

            $enquiry = MembersEnquiry::create([
                'first_name'        => trim($data['first_name']),
                'last_name'         => trim($data['last_name']),
                'email'             => strtolower(trim($data['email'])),
                'phone'             => preg_replace('/\s+/', '', $data['phone']),
                'program'           => $data['program'],
                'property'          => trim($data['property']),
                'annual_points'     => $data['annual_points']     ?? null,
                'best_time_to_call' => $data['best_time_to_call'] ?? null,
                'notes'             => $data['notes']             ?? null,
                'consent_given'     => true,
                'consent_at'        => now(),
                'source'            => $data['source']      ?? 'website',
                'referrer_url'      => $data['referrer_url'] ?? $request->headers->get('referer'),
                'user_agent'        => $ua,
                'ip_address'        => $ip,
                'status'            => 'new',
                'spam_score'        => $score,
                'flagged'           => $score >= 50,
            ]);

            // Fire-and-forget notification fan-out (FR-9.4).
            // In production, dispatch to a queue so the HTTP response is not delayed.
            $this->notifySpecialists($enquiry);
            $this->sendConfirmationEmail($enquiry);

            return $enquiry;
        });
    }

    /**
     * Heuristic spam score (0–100). 50+ flags the enquiry off the default queue (FR-9.9).
     * This is intentionally simple; replace with a real classifier (e.g. reCAPTCHA / Akismet) later.
     */
    public function computeSpamScore(array $data, Request $request): int
    {
        $score = 0;

        // Same IP submitting multiple in last 15 min
        $ip = $request->ip();
        if ($ip) {
            $recent = MembersEnquiry::where('ip_address', $ip)
                ->where('created_at', '>=', now()->subMinutes(15))
                ->count();
            if ($recent >= 1) $score += 25;
            if ($recent >= 3) $score += 25; // total 50, auto-flag
        }

        // Disposable / role-based local-parts often signal spam
        $email = strtolower($data['email'] ?? '');
        if (preg_match('/^(admin|info|test|noreply|no-reply|root)@/', $email)) {
            $score += 30;
        }
        if (preg_match('/(mailinator|tempmail|10minutemail|guerrillamail|yopmail|trashmail)/', $email)) {
            $score += 50;
        }

        // Excess URL count in notes (typical link-spam pattern)
        $notes = $data['notes'] ?? '';
        $urls = preg_match_all('/https?:\/\//i', $notes);
        if ($urls >= 2) $score += 20;
        if ($urls >= 4) $score += 30;

        // Empty user agent (likely automated)
        if (empty($request->userAgent())) {
            $score += 20;
        }

        // Property field too short or matches name verbatim (low effort)
        $property = trim($data['property'] ?? '');
        $fullName = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
        if (mb_strlen($property) < 8) $score += 15;
        if ($fullName && mb_strtolower($property) === mb_strtolower($fullName)) $score += 25;

        return min(100, $score);
    }

    /**
     * Notify the on-call member specialist channel (FR-9.4).
     * Slack webhook in production; logged locally in dev.
     */
    public function notifySpecialists(MembersEnquiry $enquiry): void
    {
        $payload = [
            'text'   => "📩 New Members enquiry from {$enquiry->fullName()}",
            'blocks' => [
                [
                    'type' => 'header',
                    'text' => ['type' => 'plain_text', 'text' => 'New Managed Listing Program enquiry'],
                ],
                [
                    'type'   => 'section',
                    'fields' => [
                        ['type' => 'mrkdwn', 'text' => "*Name:*\n{$enquiry->fullName()}"],
                        ['type' => 'mrkdwn', 'text' => "*Email:*\n{$enquiry->email}"],
                        ['type' => 'mrkdwn', 'text' => "*Phone:*\n{$enquiry->phone}"],
                        ['type' => 'mrkdwn', 'text' => "*Program:*\n{$enquiry->program}"],
                        ['type' => 'mrkdwn', 'text' => "*Property:*\n{$enquiry->property}"],
                        ['type' => 'mrkdwn', 'text' => "*Best time:*\n" . ($enquiry->best_time_to_call ?? '—')],
                    ],
                ],
                [
                    'type' => 'context',
                    'elements' => [
                        ['type' => 'mrkdwn', 'text' => "Source: `{$enquiry->source}`  •  Spam score: `{$enquiry->spam_score}`"
                            . ($enquiry->flagged ? '  •  ⚠️ FLAGGED' : '')],
                    ],
                ],
                [
                    'type' => 'actions',
                    'elements' => [[
                        'type' => 'button',
                        'text' => ['type' => 'plain_text', 'text' => 'Open in admin'],
                        'url'  => config('app.url') . '/admin/members-enquiries/' . $enquiry->id,
                        'style' => 'primary',
                    ]],
                ],
            ],
        ];

        $webhook = config('services.slack.members_webhook');
        if (! $webhook) {
            Log::info('members_enquiry.slack_skipped', ['id' => $enquiry->id, 'reason' => 'webhook_unconfigured']);
            return;
        }

        try {
            \Illuminate\Support\Facades\Http::timeout(5)->post($webhook, $payload);
        } catch (\Throwable $e) {
            Log::warning('members_enquiry.slack_failed', [
                'id'    => $enquiry->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send a confirmation email to the enquirer.
     * Queued by default since the Mailable implements ShouldQueue, so the public
     * POST endpoint doesn't block on SMTP latency.
     */
    public function sendConfirmationEmail(MembersEnquiry $enquiry): void
    {
        try {
            Mail::to($enquiry->email)->send(new MembersEnquiryReceived($enquiry));
        } catch (\Throwable $e) {
            Log::warning('members_enquiry.email_failed', [
                'id'    => $enquiry->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Assign an enquiry to a specialist. Idempotent.
     */
    public function assign(MembersEnquiry $enquiry, User $specialist): MembersEnquiry
    {
        $enquiry->update(['assigned_to' => $specialist->id]);
        return $enquiry->fresh();
    }
}

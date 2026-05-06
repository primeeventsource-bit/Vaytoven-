<?php

namespace App\Jobs;

use App\Models\MemberEnquiry;
use App\Services\Notifications\SlackNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Fires a Slack message to the ops channel when a member enquiry lands.
 *
 * Why a job rather than a synchronous notify in the controller:
 *   - the form post returns instantly even if Slack is slow
 *   - retry semantics if the webhook is briefly down
 *
 * The notifier itself swallows transport errors, so this job will not retry
 * on a network blip — that's a deliberate trade-off because Slack notifications
 * are a soft signal, not load-bearing for the conversion flow.
 */
class NotifyOpsOfMemberEnquiry implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public MemberEnquiry $enquiry)
    {
    }

    public function handle(SlackNotifier $slack): void
    {
        $e = $this->enquiry;

        // Plain-text fallback so even legacy clients render something useful.
        $text = sprintf(
            ":wave: New member enquiry — %s\n*%s* · %s · %s\nClub: %s · Property: %s · Points: %s\n%s",
            $e->reference,
            $e->fullName(),
            $e->email,
            $e->phone,
            $e->club,
            $e->property,
            $e->points,
            $e->contact_window ? 'Best contact window: '.$e->contact_window : '',
        );

        $slack->send([
            'text'   => $text,
            'blocks' => [
                [
                    'type' => 'header',
                    'text' => ['type' => 'plain_text', 'text' => "New member enquiry — {$e->reference}"],
                ],
                [
                    'type' => 'section',
                    'fields' => [
                        ['type' => 'mrkdwn', 'text' => "*Name*\n{$e->fullName()}"],
                        ['type' => 'mrkdwn', 'text' => "*Email*\n{$e->email}"],
                        ['type' => 'mrkdwn', 'text' => "*Phone*\n{$e->phone}"],
                        ['type' => 'mrkdwn', 'text' => "*Club*\n{$e->club}"],
                        ['type' => 'mrkdwn', 'text' => "*Property*\n{$e->property}"],
                        ['type' => 'mrkdwn', 'text' => "*Points*\n{$e->points}"],
                    ],
                ],
                ...($e->contact_window ? [[
                    'type' => 'context',
                    'elements' => [
                        ['type' => 'mrkdwn', 'text' => "Best contact window: {$e->contact_window}"],
                    ],
                ]] : []),
            ],
        ]);
    }
}

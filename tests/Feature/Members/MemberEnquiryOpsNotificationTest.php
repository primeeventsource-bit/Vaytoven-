<?php

namespace Tests\Feature\Members;

use App\Jobs\NotifyOpsOfMemberEnquiry;
use App\Models\MemberEnquiry;
use App\Services\Notifications\HttpSlackNotifier;
use App\Services\Notifications\NoOpSlackNotifier;
use App\Services\Notifications\SlackNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Finish step: an enquiry must surface to ops via Slack so a human picks it up
 * promptly. We test:
 *   - the controller dispatches the queued job
 *   - the job actually hits the configured webhook
 *   - the no-webhook configuration falls back to NoOp so jobs run cleanly
 */
class MemberEnquiryOpsNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_controller_dispatches_ops_notification_job(): void
    {
        Mail::fake();
        Bus::fake();

        $this->withHeaders(['Referer' => 'https://www.vaytoven.com/'])
            ->post('/members/enquiry', [
                'first_name' => 'Ada', 'last_name' => 'Lovelace',
                'email' => 'ada@example.com', 'phone' => '+1 555 555 0100',
                'club' => 'Marriott', 'property' => 'Ko Olina',
                'points' => '4500', 'consent' => 'on',
            ])->assertOk();

        Bus::assertDispatched(NotifyOpsOfMemberEnquiry::class, function ($job) {
            return $job->enquiry->email === 'ada@example.com';
        });
    }

    public function test_job_posts_to_configured_slack_webhook(): void
    {
        Http::fake([
            'hooks.slack.com/*' => Http::response('ok', 200),
        ]);

        config(['services.slack.ops_webhook_url' => 'https://hooks.slack.com/services/T/B/X']);
        $this->app->forgetInstance(SlackNotifier::class);
        $this->app->bind(SlackNotifier::class, fn ($app) => new HttpSlackNotifier(
            $app->make(HttpFactory::class),
            'https://hooks.slack.com/services/T/B/X',
        ));

        $enquiry = MemberEnquiry::factory()->create([
            'first_name' => 'Ada',
            'last_name'  => 'Lovelace',
            'reference'  => 'VYT-TESTREF1',
        ]);

        (new NotifyOpsOfMemberEnquiry($enquiry))->handle($this->app->make(SlackNotifier::class));

        Http::assertSent(function ($request) {
            $body = $request->data();
            return str_contains($request->url(), 'hooks.slack.com')
                && str_contains((string) ($body['text'] ?? ''), 'VYT-TESTREF1')
                && str_contains((string) ($body['text'] ?? ''), 'Ada Lovelace');
        });
    }

    public function test_job_is_no_op_when_webhook_unset(): void
    {
        Http::fake();

        config(['services.slack.ops_webhook_url' => null]);
        $this->app->forgetInstance(SlackNotifier::class);

        $notifier = $this->app->make(SlackNotifier::class);
        $this->assertInstanceOf(NoOpSlackNotifier::class, $notifier);

        $enquiry = MemberEnquiry::factory()->create();
        (new NotifyOpsOfMemberEnquiry($enquiry))->handle($notifier);

        Http::assertNothingSent();
    }

    public function test_http_notifier_swallows_transport_failure(): void
    {
        Http::fake([
            'hooks.slack.com/*' => Http::response('boom', 500),
        ]);

        $notifier = new HttpSlackNotifier(
            $this->app->make(HttpFactory::class),
            'https://hooks.slack.com/services/T/B/X',
        );

        // Must not throw — Slack is a soft signal; failures only log.
        $notifier->send(['text' => 'hello']);

        Http::assertSentCount(1);
    }
}

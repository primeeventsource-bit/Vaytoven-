<?php

namespace Tests\Feature\Mail;

use App\Listeners\RecordMailWasDelivered;
use App\Support\Mail\MailDeliverability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Configured is not delivering.
 *
 * /health reported mail as "ok" on the live site while every send was refused
 * with SMTP 535: host, username and password were all present and the password
 * was simply wrong. The configuration check could not see that, and a false
 * green is worse than a known-bad state because nobody investigates green.
 */
class MailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function configuredSmtp(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.example.com',
            'mail.mailers.smtp.username' => 'contact@vaytoven.com',
            'mail.mailers.smtp.password' => 'a-password',
        ]);
        app()->detectEnvironment(fn () => 'production');
        Cache::flush();
    }

    public function test_a_configured_but_unproven_mailer_is_not_reported_healthy(): void
    {
        $this->configuredSmtp();

        $this->assertTrue(MailDeliverability::isDeliverable(), 'it is configured');
        $this->assertFalse(MailDeliverability::isVerified(), 'nothing has been sent');

        $this->getJson('/health')
            ->assertJsonPath('checks.mail.ok', false)
            ->assertJsonPath('checks.mail.verified', false);
    }

    public function test_the_unverified_reason_names_the_command_that_would_prove_it(): void
    {
        $this->configuredSmtp();

        $reason = MailDeliverability::unverifiedReason();

        $this->assertStringContainsString('mail:test', $reason);
        $this->assertStringContainsString('535', $reason);
    }

    public function test_a_successful_send_marks_it_verified(): void
    {
        $this->configuredSmtp();

        MailDeliverability::recordSuccessfulSend();

        $this->assertTrue(MailDeliverability::isVerified());
        $this->assertNotNull(MailDeliverability::lastSuccessfulSendAt());

        $this->getJson('/health')
            ->assertJsonPath('checks.mail.ok', true)
            ->assertJsonPath('checks.mail.verified', true);
    }

    /** A log transport "succeeding" proves nothing, so it must not count. */
    public function test_a_sink_transport_cannot_mark_itself_verified(): void
    {
        config(['mail.default' => 'log']);
        app()->detectEnvironment(fn () => 'production');
        Cache::flush();

        MailDeliverability::recordSuccessfulSend();

        $this->assertNull(MailDeliverability::lastSuccessfulSendAt());
        $this->assertFalse(MailDeliverability::isVerified());
    }

    /** Nothing to prove on a dev box or in the suite. */
    public function test_local_and_testing_need_no_proof(): void
    {
        config(['mail.default' => 'log']);
        app()->detectEnvironment(fn () => 'local');

        $this->assertTrue(MailDeliverability::isVerified());
        $this->assertNull(MailDeliverability::unverifiedReason());
    }

    /**
     * The listener is wired to Laravel's MessageSent event rather than to call
     * sites, so any real send counts and no future one has to opt in.
     *
     * Asserted through the dispatcher rather than by sending: a real send would
     * need a reachable SMTP server, and a fake transport is a sink that must
     * not certify itself — which is the very next test.
     */
    public function test_the_listener_is_registered_for_the_message_sent_event(): void
    {
        $this->assertTrue(Event::hasListeners(MessageSent::class));

        $registered = array_map(
            fn ($listener) => is_string($listener) ? $listener : get_class($listener),
            Event::getRawListeners()[MessageSent::class] ?? []
        );

        $this->assertContains(
            RecordMailWasDelivered::class,
            $registered,
            'MessageSent must reach RecordMailWasDelivered, or delivery is never recorded'
        );
    }

    /** A sink "succeeding" proves nothing, even through the real event path. */
    public function test_a_send_over_a_sink_transport_does_not_certify_delivery(): void
    {
        config(['mail.default' => 'array']);
        app()->detectEnvironment(fn () => 'production');
        Cache::flush();

        Mail::raw('probe', fn ($m) => $m->to('someone@example.com')->subject('probe'));

        $this->assertNull(
            MailDeliverability::lastSuccessfulSendAt(),
            'the array transport is a sink and must not certify itself'
        );
    }
    public function test_an_unconfigured_mailer_still_reports_its_original_reason(): void
    {
        config(['mail.default' => 'log']);
        app()->detectEnvironment(fn () => 'production');

        $this->getJson('/health')
            ->assertJsonPath('checks.mail.ok', false);

        $this->assertStringContainsString('discards mail', MailDeliverability::unverifiedReason());
    }
}

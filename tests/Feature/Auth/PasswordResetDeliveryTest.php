<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\Mail\MailDeliverability;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Password reset must either work or say it does not.
 *
 * The site ran for months with MAIL_MAILER unset, which falls through to the
 * `log` transport. Every layer reported success: Mail::send() returned,
 * Password::sendResetLink() returned RESET_LINK_SENT, and the page told the
 * user their link was on its way. Nothing was ever sent, and nothing anywhere
 * said so. These tests exist so that failure mode cannot come back quietly.
 */
class PasswordResetDeliveryTest extends TestCase
{
    use RefreshDatabase;

    /** Point the app at a transport that genuinely delivers. */
    private function withWorkingMail(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.example-provider.com',
            'mail.mailers.smtp.username' => 'apikey',
            'mail.mailers.smtp.password' => 'secret',
        ]);
    }

    /**
     * The exact production misconfiguration: a sink transport, not local.
     *
     * Flipping the environment is what makes a `log` mailer count as an
     * outage, but it also switches CSRF validation on — Laravel skips that
     * middleware only while the environment is literally "testing". The
     * exemption keeps these tests about mail rather than about tokens.
     */
    private function withSilentlyBrokenMail(): void
    {
        config(['mail.default' => 'log']);
        app()->detectEnvironment(fn () => 'production');

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    // --- deliverability detection ----------------------------------------

    public function test_a_log_transport_outside_local_is_not_deliverable(): void
    {
        $this->withSilentlyBrokenMail();

        $this->assertFalse(MailDeliverability::isDeliverable());
        $this->assertStringContainsString('discards mail', MailDeliverability::reason());
    }

    public function test_a_log_transport_in_local_development_is_fine(): void
    {
        config(['mail.default' => 'log']);
        app()->detectEnvironment(fn () => 'local');

        $this->assertTrue(MailDeliverability::isDeliverable());
    }

    /**
     * MAIL_MAILER=smtp with nothing behind it is the other silent failure:
     * it points at 127.0.0.1 by config default and throws at send time.
     */
    public function test_smtp_pointed_at_localhost_is_not_deliverable(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1']);
        app()->detectEnvironment(fn () => 'production');

        $this->assertFalse(MailDeliverability::isDeliverable());
        $this->assertStringContainsString('no host or credentials', MailDeliverability::reason());
    }

    public function test_smtp_with_a_host_but_no_credentials_is_not_deliverable(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.example-provider.com',
            'mail.mailers.smtp.username' => null,
        ]);
        app()->detectEnvironment(fn () => 'production');

        $this->assertFalse(MailDeliverability::isDeliverable());
    }

    // --- the form's behaviour --------------------------------------------

    public function test_it_refuses_and_says_so_when_mail_cannot_be_sent(): void
    {
        $this->withSilentlyBrokenMail();
        Notification::fake();

        $user = User::factory()->create(['email' => 'someone@example.com']);

        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect('/forgot-password')
            ->assertSessionHasErrors('email');

        // No token minted: it would be a valid credential nobody can receive,
        // and it would invalidate any earlier link that still worked.
        $this->assertDatabaseCount('password_reset_tokens', 0);
        Notification::assertNothingSent();
    }

    public function test_the_form_warns_up_front_during_a_mail_outage(): void
    {
        $this->withSilentlyBrokenMail();

        $this->get('/forgot-password')
            ->assertOk()
            ->assertSee('Password reset emails are unavailable', false)
            ->assertSee('contact@vaytoven.com');
    }

    public function test_the_form_does_not_cry_wolf_when_mail_works(): void
    {
        $this->withWorkingMail();

        $this->get('/forgot-password')
            ->assertOk()
            ->assertDontSee('Password reset emails are unavailable', false);
    }

    public function test_it_sends_the_reset_link_when_mail_works(): void
    {
        $this->withWorkingMail();
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    /**
     * The form must not reveal which addresses have accounts. Stock Breeze
     * answers "We can't find a user with that email address" for an unknown
     * one, which turns the page into an enumeration oracle.
     */
    public function test_an_unknown_address_is_indistinguishable_from_a_known_one(): void
    {
        $this->withWorkingMail();
        Notification::fake();

        $user = User::factory()->create(['email' => 'real@example.com']);

        $known = $this->post('/forgot-password', ['email' => $user->email]);
        $unknown = $this->post('/forgot-password', ['email' => 'nobody-here@example.com']);

        $this->assertSame($known->getStatusCode(), $unknown->getStatusCode());
        $unknown->assertSessionHasNoErrors()->assertSessionHas('status');

        $this->assertSame(
            session()->get('status'),
            $known->getSession()->get('status'),
        );

        Notification::assertSentTo($user, ResetPassword::class);
    }

    // --- end to end -------------------------------------------------------

    /**
     * The link in the email must actually let someone back in. Covers the
     * whole path rather than only the send, because a reset that emails a
     * token nobody can redeem is the same outage wearing a different hat.
     */
    public function test_a_user_can_complete_a_reset_from_the_emailed_link(): void
    {
        $this->withWorkingMail();
        Notification::fake();

        $user = User::factory()->create(['email' => 'reset-me@example.com']);

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $this->get('/reset-password/'.$notification->token)->assertOk();

            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'a-brand-new-password',
                'password_confirmation' => 'a-brand-new-password',
            ])->assertSessionHasNoErrors();

            return true;
        });

        $this->assertTrue(
            auth()->attempt(['email' => $user->email, 'password' => 'a-brand-new-password']),
            'The new password does not work, so the reset did not complete.',
        );
    }

    // --- the outage is visible to operators -------------------------------

    /**
     * The invariant, asserted rather than a literal status code.
     *
     * The test environment has no Redis, so /health answers 503 here whatever
     * mail does — pinning 200 would only be testing the local Redis situation.
     * What must hold in every environment is that toggling mail moves the
     * reported check but never the status code, because a status code change
     * is what pulls the container out of rotation.
     */
    public function test_a_mail_outage_does_not_change_the_health_status_code(): void
    {
        $this->withWorkingMail();
        $healthy = $this->getJson('/health');
        $healthy->assertJsonPath('checks.mail.ok', true);

        $this->withSilentlyBrokenMail();
        $degraded = $this->getJson('/health');
        $degraded->assertJsonPath('checks.mail.ok', false);

        $this->assertSame(
            $healthy->getStatusCode(),
            $degraded->getStatusCode(),
            'A mail outage changed the health status code, which would take the site out of rotation.',
        );
    }

    /**
     * The check has to name the transport, or an operator reading /health
     * learns only that "mail" is unhappy and still has to go digging.
     *
     * Deliberately does not assert the top-level status: this environment has
     * no Redis, so the critical failure reports "unhealthy" and correctly
     * outranks the advisory "degraded". Asserting the mail fields is the part
     * that belongs to this test.
     */
    public function test_health_names_the_mail_outage_so_an_operator_can_act(): void
    {
        $this->withSilentlyBrokenMail();

        $this->getJson('/health')
            ->assertJsonPath('checks.mail.ok', false)
            ->assertJsonPath('checks.mail.transport', 'log')
            ->assertJsonPath('checks.mail.error', fn ($e) => str_contains((string) $e, 'discards mail'));
    }

    /** The health payload must not echo credentials. */
    public function test_health_does_not_leak_mail_credentials(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.example-provider.com',
            'mail.mailers.smtp.username' => 'apikey',
            'mail.mailers.smtp.password' => 'super-secret-value',
        ]);

        $body = $this->getJson('/health')->getContent();

        $this->assertStringNotContainsString('super-secret-value', $body);
        $this->assertStringNotContainsString('apikey', $body);
    }
}

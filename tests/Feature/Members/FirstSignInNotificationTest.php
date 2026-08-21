<?php

namespace Tests\Feature\Members;

use App\Mail\MemberFirstSignIn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Telling the office a member has used their account for the first time.
 *
 * That sign-in is the moment fulfilment completes: the member paid, an account
 * was issued, and it has now demonstrably reached them. Until it happens nobody
 * knows whether the credentials arrived at all, and that gap is where a member
 * goes quiet and later disputes the charge.
 *
 * The rule that matters most here is who does NOT receive it. The member is
 * never a recipient — not to, not cc, not bcc. An email about an action they
 * just performed tells them nothing and reads as a security alert about
 * themselves.
 */
class FirstSignInNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function member(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'email'                => 'new.member@example.com',
            'must_change_password' => false,
            'last_login_at'        => null,
        ], $attributes));
    }

    private function signIn(User $user): void
    {
        // The real event, not a manual dispatch: the notification hangs off
        // Laravel's Login event via the TrackAuthEvents subscriber, and firing
        // it by hand would pass even if that wiring were broken.
        // A real iPhone user-agent, so the device/platform/browser
        // assertions below are reading parsed values rather than nulls.
        $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1')
            ->post(route('login'), [
                'email'    => $user->email,
                'password' => 'password',
            ]);
    }

    public function test_the_office_is_told_on_a_members_first_sign_in(): void
    {
        Mail::fake();

        $this->signIn($this->member());

        Mail::assertSent(MemberFirstSignIn::class);
    }

    /**
     * The whole point of the request. If this ever fails, a member is being
     * emailed about their own sign-in.
     */
    public function test_the_member_never_receives_it(): void
    {
        Mail::fake();

        $member = $this->member();

        $this->signIn($member);

        Mail::assertSent(MemberFirstSignIn::class, function (MemberFirstSignIn $mail) use ($member) {
            $built = $mail->envelope();

            $addresses = collect(array_merge($built->to, $built->cc, $built->bcc))
                ->map(fn ($a) => is_string($a) ? $a : $a->address)
                ->all();

            $this->assertNotContains($member->email, $addresses, 'the member must never be a recipient');

            return true;
        });
    }

    public function test_it_goes_to_the_office_address(): void
    {
        Mail::fake();
        config()->set('mail.office_address', 'contact@vaytoven.com');

        $this->signIn($this->member());

        Mail::assertSent(MemberFirstSignIn::class, function (MemberFirstSignIn $mail) {
            $to = collect($mail->envelope()->to)
                ->map(fn ($a) => is_string($a) ? $a : $a->address)
                ->all();

            return in_array('contact@vaytoven.com', $to, true);
        });
    }

    /** Only the FIRST sign-in. A member logging in daily must not mail the office daily. */
    public function test_a_returning_member_does_not_trigger_it(): void
    {
        Mail::fake();

        $this->signIn($this->member(['last_login_at' => now()->subDays(3)]));

        Mail::assertNotSent(MemberFirstSignIn::class);
    }

    /** And signing in twice in a row sends exactly one. */
    public function test_signing_in_again_does_not_send_a_second(): void
    {
        Mail::fake();

        $member = $this->member();

        $this->signIn($member);
        $this->post(route('logout'));
        $this->signIn($member->refresh());

        Mail::assertSentCount(1);
    }

    /** A failed attempt is not a sign-in. */
    public function test_a_failed_sign_in_sends_nothing(): void
    {
        Mail::fake();

        $member = $this->member();

        $this->post(route('login'), [
            'email'    => $member->email,
            'password' => 'not-the-password',
        ]);

        Mail::assertNotSent(MemberFirstSignIn::class);
    }

    /** The subject has to identify who, or the office cannot act on it. */
    public function test_the_subject_names_the_member(): void
    {
        Mail::fake();

        $member = $this->member();

        $this->signIn($member);

        Mail::assertSent(MemberFirstSignIn::class, function (MemberFirstSignIn $mail) use ($member) {
            return str_contains($mail->envelope()->subject, $member->email);
        });
    }

    /**
     * The office needs to know where and on what, not just that it
     * happened. A sign-in from an unexpected country or a data centre is
     * the one worth a second look.
     */
    public function test_it_carries_the_ip_device_and_location(): void
    {
        Mail::fake();

        $this->signIn($this->member());

        Mail::assertSent(MemberFirstSignIn::class, function (MemberFirstSignIn $mail) {
            $c = $mail->context;

            $this->assertArrayHasKey('ip_address', $c);
            $this->assertArrayHasKey('location', $c);
            $this->assertSame('mobile', $c['device_type'], 'an iPhone is a mobile');
            $this->assertSame('iOS', $c['platform']);
            $this->assertSame('Safari', $c['browser']);
            $this->assertNotEmpty($c['user_agent']);

            return true;
        });
    }

    /**
     * The explanatory paragraph about GeoIP was removed by request. The row
     * label still says "Approximate area", which is what stops the figure being
     * read as a precise position, so that is what is pinned here.
     */
    public function test_the_location_row_is_labelled_approximate(): void
    {
        Mail::fake();

        $member = $this->member();
        $this->signIn($member);

        Mail::assertSent(MemberFirstSignIn::class, function (MemberFirstSignIn $mail) {
            $body = $mail->render();

            $this->assertStringContainsStringIgnoringCase('Approximate area', $body);

            return true;
        });
    }

    /** The registered company identifies the sender; the brand name alone does not. */
    public function test_the_email_carries_the_legal_entity(): void
    {
        Mail::fake();

        $this->signIn($this->member());

        Mail::assertSent(MemberFirstSignIn::class, function (MemberFirstSignIn $mail) {
            return str_contains($mail->render(), config('app.legal_entity'));
        });
    }

    // --- the attached record ---------------------------------------------------------

    /**
     * The email announces the event; the PDF is what gets filed. In a dispute
     * months later, "when did this member first use the account and from
     * where" wants a document somebody can hand over.
     */
    public function test_a_pdf_record_is_attached(): void
    {
        Mail::fake();

        $this->signIn($this->member());

        Mail::assertSent(MemberFirstSignIn::class, function (MemberFirstSignIn $mail) {
            $attachments = $mail->attachments();

            $this->assertCount(1, $attachments, 'exactly one record should be attached');

            return true;
        });
    }

    /** Present is not the same as readable — assert it is genuinely a PDF. */
    public function test_the_attachment_is_a_real_pdf(): void
    {
        // Built directly, so a failure points at the renderer rather than at
        // Laravel's attachment plumbing.
        $mail = new MemberFirstSignIn($this->member(), ['device_type' => 'mobile']);
        $pdf  = \Barryvdh\DomPDF\Facade\Pdf::loadView('docs.first-sign-in-record', [
            'member'  => $mail->member,
            'context' => $mail->context,
        ])->output();

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(1000, strlen($pdf), 'a few hundred bytes is not a rendered page');
    }

    /** Who and when, so a folder of these is navigable. */
    public function test_the_attachment_is_named_for_the_member_and_the_date(): void
    {
        $mail = new MemberFirstSignIn($this->member(), []);

        $this->assertMatchesRegularExpression(
            '/^first-sign-in-newmember-\d{4}-\d{2}-\d{2}\.pdf$/',
            $mail->filename(),
        );
    }

    /** The record must carry the same detail the email does. */
    public function test_the_pdf_contains_the_member_and_the_sign_in_detail(): void
    {
        $member = $this->member();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('docs.first-sign-in-record', [
            'member'  => $member,
            'context' => [
                'ip_address'   => '203.0.113.9',
                'location'     => 'Orlando, Florida, US',
                'device_type'  => 'mobile',
                'platform'     => 'iOS',
                'browser'      => 'Safari',
                'signed_in_at' => 'August 21, 2026 at 9:14am EDT',
            ],
        ])->output();

        // dompdf compresses its streams, so assert on the view instead of the
        // bytes — the same data, without decoding a PDF to prove a string.
        $html = view('docs.first-sign-in-record', [
            'member'  => $member,
            'context' => ['ip_address' => '203.0.113.9', 'location' => 'Orlando, Florida, US', 'device_type' => 'mobile'],
        ])->render();

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString($member->email, $html);
        $this->assertStringContainsString('203.0.113.9', $html);
        $this->assertStringContainsString('Orlando, Florida, US', $html);
        $this->assertStringContainsString(config('app.legal_entity'), $html);
    }

    /** A mail outage must never stop somebody signing in. */
    public function test_a_mail_failure_does_not_block_the_sign_in(): void
    {
        Mail::shouldReceive('send')->andThrow(new \RuntimeException('smtp down'));

        $member = $this->member();

        $this->signIn($member);

        $this->assertAuthenticatedAs($member->fresh());
    }
}

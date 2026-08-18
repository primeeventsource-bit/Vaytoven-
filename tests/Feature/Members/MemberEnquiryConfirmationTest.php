<?php

namespace Tests\Feature\Members;

use App\Mail\MemberEnquiryReceived;
use App\Models\MemberEnquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Verify step: when an enquiry is submitted, the prospect should receive a
 * confirmation mail (queued) carrying their reference. Without this, the form
 * looks like a black hole and trust drops — which kills the conversion path.
 */
class MemberEnquiryConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmation_mail_is_queued_to_prospect(): void
    {
        Mail::fake();
        Bus::fake();

        $this->postValidEnquiry();

        $enquiry = MemberEnquiry::sole();

        Mail::assertQueued(MemberEnquiryReceived::class, function (MemberEnquiryReceived $mail) use ($enquiry) {
            return $mail->hasTo($enquiry->email)
                && $mail->enquiry->is($enquiry);
        });
    }

    public function test_confirmation_mail_subject_includes_reference(): void
    {
        $enquiry = MemberEnquiry::factory()->create();

        $mail = new MemberEnquiryReceived($enquiry);
        $envelope = $mail->envelope();

        $this->assertStringContainsString($enquiry->reference, $envelope->subject);
        $this->assertStringContainsString('We got your request', $envelope->subject);
    }

    public function test_confirmation_mail_renders_with_enquiry_details(): void
    {
        $enquiry = MemberEnquiry::factory()->create([
            'first_name' => 'Ada',
            'property'   => 'Ko Olina',
        ]);

        $rendered = (new MemberEnquiryReceived($enquiry))->render();

        $this->assertStringContainsString('Ada', $rendered);
        $this->assertStringContainsString($enquiry->reference, $rendered);
        $this->assertStringContainsString('Ko Olina', $rendered);
    }

    /**
     * The confirmation email used to echo a club name and a points balance
     * back at the sender. Neither is asked for any more: Vaytoven advertises
     * vacation properties, and quoting a club programme back to someone framed
     * it as a points-club rental service.
     */
    public function test_the_confirmation_mail_does_not_mention_clubs_or_points(): void
    {
        $enquiry = MemberEnquiry::factory()->create([
            'club'   => 'Some Club',      // a legacy row can still hold these
            'points' => '4500',
        ]);

        $rendered = (new MemberEnquiryReceived($enquiry))->render();

        $this->assertStringNotContainsString('Some Club', $rendered);
        $this->assertStringNotContainsString('4500', $rendered);
        $this->assertStringNotContainsString('Points', $rendered);
    }

    public function test_each_enquiry_gets_unique_reference(): void
    {
        $a = MemberEnquiry::factory()->create();
        $b = MemberEnquiry::factory()->create();

        $this->assertNotSame($a->reference, $b->reference);
        $this->assertMatchesRegularExpression('/^VYT-[A-Z0-9]{8}$/', $a->reference);
    }

    private function postValidEnquiry(): void
    {
        $this->withHeaders(['Referer' => 'https://www.vaytoven.com/'])
            ->post('/members/enquiry', [
                'first_name'     => 'Ada',
                'last_name'      => 'Lovelace',
                'email'          => 'ada@example.com',
                'phone'          => '+1 555 555 0100',
                'club'           => 'Marriott',
                'property'       => 'Ko Olina, Hawaii',
                'points'         => '4500',
                'contact_window' => 'Weekday afternoons PT',
                'consent'        => 'on',
            ])->assertOk();
    }
}

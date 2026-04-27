<?php

namespace Tests\Feature;

use App\Mail\MembersEnquiryReceived;
use App\Models\MembersEnquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MembersEnquiryMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_mailable_is_queued_on_submit(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/members-enquiries', [
            'first_name' => 'Christian',
            'last_name'  => 'Test',
            'email'      => 'christian@example.com',
            'phone'      => '5550123',
            'program'    => 'Marriott Vacation Club',
            'property'   => 'Marriott Maui Ocean Club, Lahaina HI',
            'consent'    => true,
        ])->assertCreated();

        Mail::assertQueued(MembersEnquiryReceived::class, function ($mail) {
            return $mail->hasTo('christian@example.com')
                && $mail->envelope()->subject === 'We got your enquiry — Vaytoven Members Program';
        });
    }

    public function test_mailable_renders_with_required_strings(): void
    {
        $enquiry = MembersEnquiry::factory()->create([
            'first_name'        => 'Christian',
            'program'           => 'Marriott Vacation Club',
            'property'          => 'Marriott Maui Ocean Club, Lahaina HI',
            'best_time_to_call' => 'Morning (8am – 12pm)',
        ]);

        $rendered = (new MembersEnquiryReceived($enquiry))->render();

        $this->assertStringContainsString('Thanks, Christian — we got it.', $rendered);
        $this->assertStringContainsString('Marriott Vacation Club', $rendered);
        $this->assertStringContainsString('Marriott Maui Ocean Club, Lahaina HI', $rendered);
        $this->assertStringContainsString('Morning (8am – 12pm)', $rendered);
        $this->assertStringContainsString('Managed Listing Program', $rendered);

        // Make sure the T-word is never produced (FR-9.8).
        $this->assertStringNotContainsStringIgnoringCase('timeshare', $rendered);
    }

    public function test_text_fallback_renders(): void
    {
        $enquiry = MembersEnquiry::factory()->create();

        $mailable = new MembersEnquiryReceived($enquiry);
        $textBody = (string) view('emails.members.enquiry-received-text', $mailable->content()->with);

        $this->assertStringContainsString('Thanks,', $textBody);
        $this->assertStringContainsString($enquiry->program, $textBody);
        $this->assertStringContainsString('What happens next', $textBody);
        $this->assertStringNotContainsStringIgnoringCase('timeshare', $textBody);
    }
}

<?php

namespace Tests\Feature;

use App\Models\MemberEnquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberEnquiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_enquiry_persists_to_database(): void
    {
        $payload = [
            'first_name'     => 'Ada',
            'last_name'      => 'Lovelace',
            'email'          => 'ada@example.com',
            'phone'          => '+1 555 555 0100',
            'club'           => 'Marriott',
            'property'       => 'Ko Olina, Hawaii',
            'points'         => '4500',
            'contact_window' => 'Weekday afternoons PT',
            'consent'        => 'on',
        ];

        $response = $this->withHeaders(['Referer' => 'https://www.vaytoven.com/'])
            ->post('/members/enquiry', $payload);

        $response->assertOk();
        $response->assertJson(['ok' => true]);

        $this->assertDatabaseCount('members_enquiries', 1);

        $enquiry = MemberEnquiry::sole();
        $this->assertSame('ada@example.com', $enquiry->email);
        $this->assertSame('Marriott', $enquiry->club);
        $this->assertSame('https://www.vaytoven.com/', $enquiry->source_url);
        $this->assertNotNull($enquiry->consented_at);
    }

    public function test_missing_required_field_is_rejected(): void
    {
        $response = $this->postJson('/members/enquiry', [
            'first_name' => 'Ada',
            // everything else missing
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('members_enquiries', 0);
    }

    public function test_consent_must_be_accepted(): void
    {
        $response = $this->postJson('/members/enquiry', [
            'first_name' => 'Ada',
            'last_name'  => 'Lovelace',
            'email'      => 'ada@example.com',
            'phone'      => '+1 555 555 0100',
            'club'       => 'Marriott',
            'property'   => 'Ko Olina, Hawaii',
            'points'     => '4500',
            // 'consent' omitted
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('members_enquiries', 0);
    }
}

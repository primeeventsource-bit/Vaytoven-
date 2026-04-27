<?php

namespace Tests\Feature;

use App\Models\MembersEnquiry;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MembersEnquiryTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Christian',
            'last_name'  => 'Test',
            'email'      => 'christian@example.com',
            'phone'      => '555-0123',
            'program'    => 'Marriott Vacation Club',
            'property'   => 'Marriott Maui Ocean Club, Lahaina HI',
            'consent'    => true,
            'source'     => 'website',
        ], $overrides);
    }

    private function makeAdmin(): User
    {
        $role = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin']
        );
        $user = User::factory()->create();
        $user->roles()->attach($role->id);
        return $user->fresh();
    }

    // ─── Public submit ────────────────────────────────────────────

    public function test_public_can_submit_a_valid_enquiry(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/members-enquiries', $this->validPayload());

        $response->assertCreated()
            ->assertJsonStructure(['message', 'id'])
            ->assertJsonFragment(['message' => 'Thanks — we got it. A member specialist will reach out within one business day.']);

        $this->assertDatabaseHas('members_enquiries', [
            'email'   => 'christian@example.com',
            'program' => 'Marriott Vacation Club',
            'status'  => 'new',
            'consent_given' => true,
        ]);
    }

    public function test_submit_fails_without_consent(): void
    {
        $response = $this->postJson('/api/v1/members-enquiries', $this->validPayload(['consent' => false]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['consent']);
    }

    public function test_submit_fails_with_unknown_program(): void
    {
        $response = $this->postJson('/api/v1/members-enquiries', $this->validPayload(['program' => 'Bogus Resort']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['program']);
    }

    public function test_submit_fails_without_property_text(): void
    {
        $response = $this->postJson('/api/v1/members-enquiries', $this->validPayload(['property' => 'a']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['property']);
    }

    public function test_disposable_email_increases_spam_score(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/members-enquiries', $this->validPayload([
            'email' => 'temp@mailinator.com',
        ]))->assertCreated();

        $row = MembersEnquiry::firstOrFail();
        $this->assertGreaterThanOrEqual(50, $row->spam_score);
        $this->assertTrue($row->flagged);
    }

    public function test_public_endpoint_does_not_echo_back_pii(): void
    {
        Mail::fake();
        $response = $this->postJson('/api/v1/members-enquiries', $this->validPayload());
        $response->assertCreated();
        $body = $response->json();
        $this->assertArrayNotHasKey('email', $body);
        $this->assertArrayNotHasKey('phone', $body);
        $this->assertArrayHasKey('id', $body);
    }

    // ─── Admin queue ──────────────────────────────────────────────

    public function test_admin_can_list_enquiries_excluding_flagged_by_default(): void
    {
        Mail::fake();
        // 3 normal, 1 flagged
        MembersEnquiry::factory()->count(3)->create();
        MembersEnquiry::factory()->create(['flagged' => true, 'spam_score' => 80]);

        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/members-enquiries');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_admin_can_include_flagged(): void
    {
        Mail::fake();
        MembersEnquiry::factory()->count(2)->create();
        MembersEnquiry::factory()->create(['flagged' => true, 'spam_score' => 80]);

        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/members-enquiries?include_flagged=1');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_non_admin_is_forbidden(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/members-enquiries')
            ->assertForbidden();
    }

    public function test_admin_can_assign_to_self(): void
    {
        $admin   = $this->makeAdmin();
        $enquiry = MembersEnquiry::factory()->create(['assigned_to' => null]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/members-enquiries/{$enquiry->id}/assign")
            ->assertOk()
            ->assertJsonPath('data.assigned_to.id', $admin->uuid);
    }

    public function test_admin_can_transition_status(): void
    {
        $admin   = $this->makeAdmin();
        $enquiry = MembersEnquiry::factory()->create(['status' => 'new']);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/admin/members-enquiries/{$enquiry->id}", [
                'status' => 'qualified',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'qualified');

        $this->assertNotNull($enquiry->fresh()->qualified_at);
    }

    public function test_rejecting_requires_reason(): void
    {
        $admin   = $this->makeAdmin();
        $enquiry = MembersEnquiry::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/admin/members-enquiries/{$enquiry->id}", [
                'status' => 'rejected',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['rejection_reason']);
    }

    public function test_rate_limit_enforced_on_public_submit(): void
    {
        Mail::fake();

        // 10 successful, 11th throttled (FR-9.9)
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/members-enquiries', $this->validPayload([
                'email' => "christian{$i}@example.com",
            ]))->assertCreated();
        }

        $this->postJson('/api/v1/members-enquiries', $this->validPayload([
            'email' => 'overflow@example.com',
        ]))->assertStatus(429);
    }
}

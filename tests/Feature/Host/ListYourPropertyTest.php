<?php

namespace Tests\Feature\Host;

use App\Enums\UserRole;
use App\Models\HostingEnquiry;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Host payout enrollment described a model Vaytoven does not operate: guests
 * paying Vaytoven, funds held, ACH payouts, and a request for bank details,
 * government ID and tax forms. It is replaced by a property submission form.
 */
class ListYourPropertyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_is_public_on_both_urls(): void
    {
        // An owner should not need an account to ask about being advertised,
        // and /host/onboarding is already published so it must keep working.
        foreach (['/host/onboarding', '/list-your-property'] as $path) {
            $this->get($path)->assertOk()->assertSee('List your property or resort');
        }
    }

    /**
     * The old page asked owners to submit bank details, photo ID and tax forms.
     * Checked against the form's INPUTS rather than the page text, because the
     * page deliberately still says the words — in a card promising we will
     * never ask for them.
     */
    public function test_the_form_has_no_field_for_banking_or_identity_documents(): void
    {
        $html = $this->get('/host/onboarding')->assertOk()->getContent();

        foreach (['routing', 'bank_account', 'ssn', 'tax_id', 'w9', 'date_of_birth'] as $banned) {
            $this->assertStringNotContainsStringIgnoringCase('name="'.$banned, $html,
                "The form still collects “{$banned}”.");
        }

        $this->assertStringNotContainsString('Enroll for payouts', $html);
    }

    public function test_a_resort_submission_captures_club_and_ownership(): void
    {
        $this->post('/host/onboarding', [
            'listing_kind' => 'resort',
            'first_name' => 'Jo', 'last_name' => 'Park', 'email' => 'jo@example.com',
            'resort_name' => 'Marbella Beach Club',
            'club_or_developer' => 'Marriott Vacation Club',
            'ownership_details' => 'Week 32, fixed',
        ])->assertRedirect();

        $enquiry = HostingEnquiry::query()->sole();

        $this->assertTrue($enquiry->isResort());
        $this->assertSame('Marbella Beach Club', $enquiry->displayName());
        $this->assertSame('Marriott Vacation Club', $enquiry->club_or_developer);
        $this->assertSame('Week 32, fixed', $enquiry->ownership_details);
    }

    public function test_the_page_states_that_vaytoven_does_not_handle_rental_money(): void
    {
        $text = preg_replace('/\s+/', ' ', strip_tags($this->get('/host/onboarding')->getContent()));

        $this->assertStringContainsString(
            'We do not collect rental payments from guests, hold funds, or pay you out.',
            $text,
        );
    }

    public function test_a_submission_is_recorded_with_the_property_details(): void
    {
        $response = $this->post('/host/onboarding', [
            'first_name' => 'Marta', 'last_name' => 'Reyes',
            'email' => 'marta@example.com', 'phone' => '+1 555 010 2030',
            'property_name' => 'Olive Grove Villa', 'property_type' => 'Villa or house',
            'city' => 'Ostuni', 'region' => 'Puglia', 'country' => 'Italy',
            'bedrooms' => 4, 'bathrooms' => 3,
            'indicative_nightly_dollars' => '450',
            'availability' => 'May to September',
            'message' => 'Pool, sea view, sleeps eight.',
        ]);

        $enquiry = HostingEnquiry::query()->sole();

        // back() — so the alias and the canonical path both return where the
        // visitor actually was.
        $response->assertRedirect()->assertSessionHas('hosting_reference', $enquiry->reference);

        $this->assertSame('Olive Grove Villa', $enquiry->property_name);
        $this->assertSame('Ostuni, Puglia, Italy', $enquiry->location());
        $this->assertSame(4, $enquiry->bedrooms);
        // Money is integer cents, never a float.
        $this->assertSame(45000, $enquiry->indicative_nightly_cents);
        $this->assertStringStartsWith('VYT-H-', $enquiry->reference);
        $this->assertNotNull($enquiry->ip);
    }

    public function test_only_contact_details_are_required(): void
    {
        // An owner who only knows "I have a place somewhere" should still be
        // able to start the conversation.
        $this->post('/host/onboarding', [
            'first_name' => 'Sam', 'last_name' => 'Doe', 'email' => 'sam@example.com',
        ])->assertRedirect();

        $this->assertSame(1, HostingEnquiry::query()->count());
    }

    public function test_a_signed_in_member_has_their_details_prefilled(): void
    {
        $member = User::factory()->create([
            'first_name' => 'Ada', 'last_name' => 'Reed',
            'email' => 'ada@example.com', 'role' => UserRole::Member,
        ]);

        $this->actingAs($member)->post('/host/onboarding', [
            'property_name' => 'Lakeside Cabin',
        ])->assertRedirect();

        $enquiry = HostingEnquiry::query()->sole();

        $this->assertSame('ada@example.com', $enquiry->email);
        $this->assertSame($member->id, $enquiry->user_id);
    }

    public function test_submissions_are_visible_in_the_admin_inbox(): void
    {
        $this->seed(RbacSeeder::class);

        $this->post('/host/onboarding', [
            'first_name' => 'Marta', 'last_name' => 'Reyes', 'email' => 'marta@example.com',
            'property_name' => 'Olive Grove Villa', 'city' => 'Ostuni',
        ]);

        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get('/admin/inbox?tab=hosting')->assertOk()
            ->assertSee('Olive Grove Villa')
            ->assertSee('marta@example.com');
    }
}

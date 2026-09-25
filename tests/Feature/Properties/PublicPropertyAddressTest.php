<?php

namespace Tests\Feature\Properties;

use App\Enums\PropertyStatus;
use App\Enums\UserRole;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Property ID printed on a listing has to BE that listing's address.
 *
 * A listing with no public_ref fell back to printing its owner's member
 * number: the same value on every listing that member owns, and a URL that
 * 404s.
 */
class PublicPropertyAddressTest extends TestCase
{
    use RefreshDatabase;

    private function member(?string $memberId): User
    {
        return User::factory()->create(['role' => UserRole::Member, 'member_id' => $memberId]);
    }

    public function test_a_new_listing_is_addressed_even_when_the_builder_did_not_set_it(): void
    {
        $member = $this->member('202609240300');

        $first  = Property::factory()->create(['host_id' => $member->id, 'status' => PropertyStatus::Active->value]);
        $second = Property::factory()->create(['host_id' => $member->id, 'status' => PropertyStatus::Active->value]);

        $this->assertSame('202609240300', $first->refresh()->public_ref);
        $this->assertSame('202609240300-2', $second->refresh()->public_ref, 'Two listings shared one address.');

        $this->get('/properties/202609240300')->assertOk()->assertSee('Property ID #202609240300');
        $this->get('/properties/202609240300-2')->assertOk();
    }

    public function test_giving_a_member_their_number_addresses_the_listings_they_already_had(): void
    {
        $member = $this->member(null);
        $listing = Property::factory()->create(['host_id' => $member->id, 'status' => PropertyStatus::Active->value]);

        $this->assertNull($listing->refresh()->public_ref);

        $member->update(['member_id' => '555777']);

        $this->assertSame('555777', $listing->refresh()->public_ref);
        $this->get('/properties/555777')->assertOk();

        // A later save that does not touch the number leaves the address alone.
        $member->update(['phone' => '+1 555 555 0111']);
        $this->assertSame('555777', $listing->refresh()->public_ref);
    }

    /** The id URL keeps working and points at the published address. */
    public function test_the_numeric_url_redirects_to_the_published_address(): void
    {
        $member  = $this->member('909090');
        $listing = Property::factory()->create(['host_id' => $member->id, 'status' => PropertyStatus::Active->value]);

        $this->get('/properties/'.$listing->id)->assertRedirect('/properties/909090');
    }

    /** A member with no number still gets a listing page, on its row id. */
    public function test_a_listing_for_a_member_without_a_number_is_still_reachable(): void
    {
        $member  = $this->member(null);
        $listing = Property::factory()->create(['host_id' => $member->id, 'status' => PropertyStatus::Active->value]);

        $this->get('/properties/'.$listing->id)->assertOk();
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Enums\PropertyStatus;
use App\Enums\UserRole;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Services\Listings\PublicPropertyRef;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Member numbers, and the listing addresses derived from them.
 *
 * A listing used to be published at its database row id — a number that means
 * nothing to anybody and quietly reveals how many listings exist. It is now
 * published under the owner's member number, which staff already use when they
 * talk to that member.
 *
 * The rule that matters most is that no address ever stops working. A link sent
 * to a client last month has to resolve after a member number is added, or the
 * feature costs more than it gives.
 */
class MemberIdListingUrlTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create([
            'role'                 => UserRole::SuperAdmin,
            'must_change_password' => false,
        ]);

        $user->roles()->sync([Role::where('key', 'super_admin')->firstOrFail()->id]);

        return $user;
    }

    private function member(?string $memberId = null): User
    {
        return User::factory()->create([
            'role'                 => UserRole::Member,
            'member_id'            => $memberId,
            'must_change_password' => false,
        ]);
    }

    private function listing(User $owner, array $attributes = []): Property
    {
        return Property::factory()->create(array_merge([
            'host_id' => $owner->id,
            'status'  => PropertyStatus::Active->value,
        ], $attributes));
    }

    // --- deriving the address --------------------------------------------------------

    public function test_the_first_listing_takes_the_member_number_bare(): void
    {
        $member  = $this->member('20482');
        $listing = $this->listing($member);

        app(PublicPropertyRef::class)->assignFor($member);

        $this->assertSame('20482', $listing->refresh()->public_ref);
    }

    /** Later listings are suffixed, so one member's three are all distinct. */
    public function test_later_listings_are_suffixed(): void
    {
        $member = $this->member('20482');
        $first  = $this->listing($member);
        $second = $this->listing($member);
        $third  = $this->listing($member);

        app(PublicPropertyRef::class)->assignFor($member);

        $this->assertSame('20482',   $first->refresh()->public_ref);
        $this->assertSame('20482-2', $second->refresh()->public_ref);
        $this->assertSame('20482-3', $third->refresh()->public_ref);
    }

    /**
     * Re-running must not renumber anything. A ref that moved would break a URL
     * somebody has already been given.
     */
    public function test_assigning_again_leaves_existing_addresses_alone(): void
    {
        $member  = $this->member('20482');
        $listing = $this->listing($member);

        $service = app(PublicPropertyRef::class);
        $service->assignFor($member);
        $service->assignFor($member);

        $this->assertSame('20482', $listing->refresh()->public_ref);
    }

    /** Two members must never be handed the same address. */
    public function test_a_colliding_number_walks_forward_instead_of_clashing(): void
    {
        $first = $this->member('700');
        $this->listing($first);
        $this->listing($first);
        app(PublicPropertyRef::class)->assignFor($first);   // 700, 700-2

        // A second member is given a number that would produce "700-2".
        $second = $this->member('700-2');
        $this->listing($second);
        app(PublicPropertyRef::class)->assignFor($second);

        $refs = Property::whereNotNull('public_ref')->pluck('public_ref');

        $this->assertSame($refs->unique()->count(), $refs->count(), 'addresses must be unique');
    }

    public function test_a_member_with_no_number_gets_no_address(): void
    {
        $member  = $this->member();
        $listing = $this->listing($member);

        $this->assertSame(0, app(PublicPropertyRef::class)->assignFor($member));
        $this->assertNull($listing->refresh()->public_ref);
    }

    // --- the URLs --------------------------------------------------------------------

    public function test_the_listing_is_published_at_the_member_number(): void
    {
        $member  = $this->member('20482');
        $listing = $this->listing($member);
        app(PublicPropertyRef::class)->assignFor($member);

        $this->assertStringEndsWith('/properties/20482', route('properties.show', $listing->refresh()));

        $this->get('/properties/20482')->assertOk()->assertSee($listing->title);
    }

    /** The whole point of keeping the old form working. */
    public function test_the_old_numeric_url_still_resolves(): void
    {
        $member  = $this->member('20482');
        $listing = $this->listing($member);
        app(PublicPropertyRef::class)->assignFor($member);

        $this->get('/properties/'.$listing->id)
            ->assertRedirect('/properties/20482');
    }

    /** Permanent, so search engines move rather than keeping both. */
    public function test_the_old_url_redirects_permanently(): void
    {
        $member  = $this->member('20482');
        $listing = $this->listing($member);
        app(PublicPropertyRef::class)->assignFor($member);

        $this->get('/properties/'.$listing->id)->assertStatus(301);
    }

    /** A listing with no member number keeps working on its id. */
    public function test_a_listing_without_an_address_still_serves_on_its_id(): void
    {
        $listing = $this->listing($this->member());

        $this->get('/properties/'.$listing->id)->assertOk()->assertSee($listing->title);
    }

    public function test_an_unknown_address_is_not_found(): void
    {
        $this->get('/properties/no-such-listing')->assertNotFound();
    }

    // --- on the listing page ---------------------------------------------------------

    /** Shown above the owner's name, so both sides have one thing to quote. */
    public function test_the_listing_shows_the_member_id_above_the_name(): void
    {
        $member  = $this->member('20482');
        $listing = $this->listing($member);
        app(PublicPropertyRef::class)->assignFor($member);

        $body = $this->get('/properties/20482')->assertOk()->getContent();

        $this->assertStringContainsString('Member ID 20482', $body);

        $this->assertLessThan(
            strpos($body, $member->publicDisplayName()),
            strpos($body, 'Member ID 20482'),
            'the number should come above the name',
        );
    }

    /** A listing whose owner has no number simply omits the line. */
    public function test_a_listing_without_a_member_id_shows_no_id_line(): void
    {
        $listing = $this->listing($this->member());

        $this->get('/properties/'.$listing->id)
            ->assertOk()
            ->assertDontSee('Member ID');
    }

    // --- the admin screens -----------------------------------------------------------

    public function test_the_create_form_offers_a_member_id_field(): void
    {
        $this->actingAs($this->staff())
            ->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee('Member ID number')
            ->assertSee('name="member_id"', false);
    }

    public function test_creating_a_user_stores_the_member_id(): void
    {
        $this->actingAs($this->staff())->post(route('admin.users.store'), [
            'first_name' => 'Dana',
            'last_name'  => 'Whitfield',
            'email'      => 'dana.member@example.com',
            'password'   => 'Str0ng-Passw0rd!23',
            'password_confirmation' => 'Str0ng-Passw0rd!23',
            'role'       => UserRole::Member->value,
            'member_id'  => '30991',
        ]);

        $this->assertSame('30991', User::where('email', 'dana.member@example.com')->sole()->member_id);
    }

    /** The request in the user's words: edit an old user, add a number, get addresses. */
    public function test_adding_a_member_id_to_an_existing_user_addresses_their_listings(): void
    {
        $member = $this->member();
        $a = $this->listing($member);
        $b = $this->listing($member);

        $this->assertNull($a->refresh()->public_ref);

        $this->actingAs($this->staff())->patch(route('admin.users.update', $member), [
            'first_name' => $member->first_name ?: 'Dana',
            'last_name'  => $member->last_name ?: 'Whitfield',
            'email'      => $member->email,
            'role'       => $member->role->value,
            'member_id'  => '44120',
        ]);

        $this->assertSame('44120',   $a->refresh()->public_ref);
        $this->assertSame('44120-2', $b->refresh()->public_ref);
    }

    /** Two members sharing a number would mean two listings at one address. */
    public function test_a_duplicate_member_id_is_refused(): void
    {
        $this->member('55500');

        $this->actingAs($this->staff())->post(route('admin.users.store'), [
            'first_name' => 'Second',
            'last_name'  => 'Person',
            'email'      => 'second@example.com',
            'password'   => 'Str0ng-Passw0rd!23',
            'password_confirmation' => 'Str0ng-Passw0rd!23',
            'role'       => UserRole::Member->value,
            'member_id'  => '55500',
        ])->assertSessionHasErrors('member_id');
    }

    /** A slash or space would break the URL it goes into. */
    public function test_an_unsafe_member_id_is_refused(): void
    {
        $this->actingAs($this->staff())->post(route('admin.users.store'), [
            'first_name' => 'Bad',
            'last_name'  => 'Input',
            'email'      => 'bad@example.com',
            'password'   => 'Str0ng-Passw0rd!23',
            'password_confirmation' => 'Str0ng-Passw0rd!23',
            'role'       => UserRole::Member->value,
            'member_id'  => 'no/slashes here',
        ])->assertSessionHasErrors('member_id');
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Enums\PropertyStatus;
use App\Enums\UserRole;
use App\Models\AdminAuditLog;
use App\Models\Property;
use App\Models\PropertyPhoto;
use App\Models\Role;
use App\Models\User;
use App\Services\Admin\DemoDataPurge;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Removing the seeded demo accounts and their data.
 *
 * The whole risk here is scope. A purge that reaches one row further than
 * intended deletes a paying member, and there is no undo. So most of what
 * follows is about what the tool must NOT touch: real accounts, the acting
 * admin, the shared photo library, and the append-only activity log.
 */
class DemoDataPurgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config()->set('filesystems.default', 'local');
    }

    private function superAdmin(): User
    {
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create([
            'email'                => 'boss@vaytoven.com',
            'role'                 => UserRole::SuperAdmin,
            'must_change_password' => false,
        ]);

        $user->roles()->sync([Role::where('key', 'super_admin')->firstOrFail()->id]);

        return $user;
    }

    private function demoUser(string $local = 'demo.one'): User
    {
        return User::factory()->create([
            'email'                => $local.DemoDataPurge::DEFAULT_SUFFIX,
            'must_change_password' => false,
        ]);
    }

    private function realUser(string $email = 'paying.member@example.com'): User
    {
        return User::factory()->create(['email' => $email, 'must_change_password' => false]);
    }

    // --- the preview ----------------------------------------------------------------

    public function test_the_preview_lists_the_demo_accounts_and_counts(): void
    {
        $this->demoUser('a');
        $this->demoUser('b');
        $this->realUser();

        $preview = (new DemoDataPurge())->preview();

        $this->assertSame(2, $preview['counts']['Accounts']);
        $this->assertCount(2, $preview['accounts']);
    }

    /** Counting is not deleting. */
    public function test_previewing_removes_nothing(): void
    {
        $this->demoUser();

        (new DemoDataPurge())->preview();

        $this->assertSame(1, User::where('email', 'like', '%'.DemoDataPurge::DEFAULT_SUFFIX)->count());
    }

    public function test_the_screen_renders_for_a_super_admin(): void
    {
        $this->demoUser();

        $this->actingAs($this->superAdmin())
            ->get(route('admin.demo-data.index'))
            ->assertOk()
            ->assertSee('What would be removed');
    }

    // --- scope: what must never be touched -------------------------------------------

    /** The one that matters. A real member must survive intact. */
    public function test_a_real_account_is_never_removed(): void
    {
        $demo = $this->demoUser();
        $real = $this->realUser();

        (new DemoDataPurge())->purge();

        $this->assertSame(0, User::where('id', $demo->id)->count());
        $this->assertSame(1, User::where('id', $real->id)->count(), 'a real account must survive');
    }

    /** An address that merely contains the suffix mid-string is not a demo account. */
    public function test_a_lookalike_address_is_not_matched(): void
    {
        $lookalike = $this->realUser('someone'.DemoDataPurge::DEFAULT_SUFFIX.'.co.uk');

        (new DemoDataPurge())->purge();

        $this->assertSame(1, User::where('id', $lookalike->id)->count());
    }

    /**
     * Deleting the account performing the purge would end the request halfway
     * through and leave the rest of the data behind.
     */
    public function test_the_acting_admin_is_never_removed(): void
    {
        $actor = $this->superAdmin();
        $actor->forceFill(['email' => 'admin'.DemoDataPurge::DEFAULT_SUFFIX])->save();

        (new DemoDataPurge())->purge($actor);

        $this->assertSame(1, User::where('id', $actor->id)->count());
    }

    /** An empty or malformed suffix must match nothing, not everything. */
    public function test_an_empty_suffix_matches_nothing(): void
    {
        $this->realUser();
        $this->demoUser();

        $this->assertCount(0, (new DemoDataPurge(''))->accounts());
        $this->assertCount(0, (new DemoDataPurge('nonsense'))->accounts());

        (new DemoDataPurge(''))->purge();

        $this->assertSame(2, User::count(), 'an empty suffix must delete nothing at all');
    }

    // --- what it does remove ---------------------------------------------------------

    public function test_it_removes_the_demo_listings_and_their_photos_from_storage(): void
    {
        $demo = $this->demoUser();

        $property = Property::factory()->create([
            'host_id' => $demo->id,
            'status'  => PropertyStatus::Active->value,
        ]);

        Storage::disk('local')->put('demo/pic.webp', 'bytes');

        PropertyPhoto::create([
            'property_id' => $property->id,
            'disk'        => 'local',
            'path'        => 'demo/pic.webp',
            'category'    => 'other',
            'mime_type'   => 'image/webp',
        ]);

        (new DemoDataPurge())->purge();

        $this->assertSame(0, Property::where('id', $property->id)->count());
        $this->assertSame(0, PropertyPhoto::count());
        Storage::disk('local')->assertMissing('demo/pic.webp');
    }

    /** A real member's listing is not collateral. */
    public function test_a_real_members_listing_survives(): void
    {
        $this->demoUser();
        $real = $this->realUser();

        $theirs = Property::factory()->create(['host_id' => $real->id]);

        (new DemoDataPurge())->purge();

        $this->assertSame(1, Property::where('id', $theirs->id)->count());
    }

    // --- the screen -------------------------------------------------------------------

    /** Typing the wrong thing must not destroy anything. */
    public function test_a_wrong_confirmation_removes_nothing(): void
    {
        $this->demoUser();

        $this->actingAs($this->superAdmin())
            ->delete(route('admin.demo-data.destroy'), ['confirmation' => 'yes please'])
            ->assertSessionHasErrors('confirmation');

        $this->assertSame(1, User::where('email', 'like', '%'.DemoDataPurge::DEFAULT_SUFFIX)->count());
    }

    public function test_the_exact_confirmation_removes_the_demo_accounts(): void
    {
        $this->demoUser();

        $this->actingAs($this->superAdmin())
            ->delete(route('admin.demo-data.destroy'), ['confirmation' => 'DELETE DEMO DATA'])
            ->assertRedirect(route('admin.demo-data.index'));

        $this->assertSame(0, User::where('email', 'like', '%'.DemoDataPurge::DEFAULT_SUFFIX)->count());
    }

    /** "18 accounts" is not an answer to "which ones". */
    public function test_the_purge_is_audited_with_the_account_list(): void
    {
        $this->demoUser('audited');

        $this->actingAs($this->superAdmin())
            ->delete(route('admin.demo-data.destroy'), ['confirmation' => 'DELETE DEMO DATA']);

        $log = AdminAuditLog::where('action', 'demo_data.purged')->sole();

        $this->assertContains('audited'.DemoDataPurge::DEFAULT_SUFFIX, $log->payload['accounts']);
    }

    // --- access -----------------------------------------------------------------------

    public function test_an_admin_who_is_not_a_super_admin_cannot_open_it(): void
    {
        $this->seed(RbacSeeder::class);

        $admin = User::factory()->create(['role' => UserRole::Admin, 'must_change_password' => false]);
        $admin->roles()->sync([Role::where('key', 'admin')->firstOrFail()->id]);

        $this->actingAs($admin)->get(route('admin.demo-data.index'))->assertForbidden();
    }

    public function test_an_admin_who_is_not_a_super_admin_cannot_run_it(): void
    {
        $this->seed(RbacSeeder::class);
        $this->demoUser();

        $admin = User::factory()->create(['role' => UserRole::Admin, 'must_change_password' => false]);
        $admin->roles()->sync([Role::where('key', 'admin')->firstOrFail()->id]);

        $this->actingAs($admin)
            ->delete(route('admin.demo-data.destroy'), ['confirmation' => 'DELETE DEMO DATA'])
            ->assertForbidden();

        $this->assertSame(1, User::where('email', 'like', '%'.DemoDataPurge::DEFAULT_SUFFIX)->count());
    }

    public function test_a_member_cannot_reach_it(): void
    {
        $this->actingAs($this->realUser())
            ->get(route('admin.demo-data.index'))
            ->assertForbidden();
    }
}

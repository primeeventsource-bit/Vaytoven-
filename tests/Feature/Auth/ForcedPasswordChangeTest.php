<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A staff-issued password is a shared secret until the owner replaces it.
 *
 * Any account created by someone else starts with a password that someone else
 * knows. Until it is changed, "this account did X" is not a statement anyone
 * can stand behind — at least two people could have done it. So the account is
 * held on the change-password screen and can do nothing else.
 */
class ForcedPasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    private function pendingUser(): User
    {
        return User::factory()->create([
            'password'             => 'IssuedByStaff123',
            'must_change_password' => true,
        ]);
    }

    public function test_a_pending_user_is_redirected_off_every_page(): void
    {
        $user = $this->pendingUser();

        foreach (['/dashboard', '/profile', '/member-services'] as $url) {
            $this->actingAs($user)->get($url)
                ->assertRedirect(route('password.first-change'));
        }
    }

    public function test_the_change_screen_itself_stays_reachable(): void
    {
        $this->actingAs($this->pendingUser())
            ->get(route('password.first-change'))
            ->assertOk()
            ->assertSee('Set your own password');
    }

    /** They must be able to leave without setting one. */
    public function test_a_pending_user_can_still_sign_out(): void
    {
        $this->actingAs($this->pendingUser())
            ->post('/logout')
            ->assertRedirect();

        $this->assertGuest();
    }

    public function test_an_api_client_gets_a_machine_readable_refusal(): void
    {
        $this->actingAs($this->pendingUser())
            ->getJson('/api/v1/auth/me')
            ->assertStatus(423)
            ->assertJsonPath('error', 'password_change_required');
    }

    public function test_setting_a_password_clears_the_flag_and_releases_the_user(): void
    {
        $user = $this->pendingUser();

        $this->actingAs($user)->post(route('password.first-change.store'), [
            'password'              => 'my-own-password-99',
            'password_confirmation' => 'my-own-password-99',
        ])->assertRedirect(route('dashboard'));

        $user->refresh();

        $this->assertFalse($user->must_change_password);
        $this->assertNotNull($user->password_changed_at);
        $this->assertTrue(Hash::check('my-own-password-99', $user->password));

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    /**
     * Reusing the issued password would leave the account shared, which is the
     * entire thing this flow exists to end.
     */
    public function test_the_issued_password_cannot_be_reused(): void
    {
        $user = $this->pendingUser();

        $this->actingAs($user)->post(route('password.first-change.store'), [
            'password'              => 'IssuedByStaff123',
            'password_confirmation' => 'IssuedByStaff123',
        ])->assertSessionHasErrors('password');

        $this->assertTrue($user->refresh()->must_change_password);
    }

    public function test_a_weak_password_is_rejected(): void
    {
        $this->actingAs($this->pendingUser())->post(route('password.first-change.store'), [
            'password' => 'short1', 'password_confirmation' => 'short1',
        ])->assertSessionHasErrors('password');
    }

    public function test_mismatched_confirmation_is_rejected(): void
    {
        $this->actingAs($this->pendingUser())->post(route('password.first-change.store'), [
            'password' => 'a-good-password-1', 'password_confirmation' => 'a-different-one-2',
        ])->assertSessionHasErrors('password');
    }

    public function test_a_normal_user_is_never_held(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);

        $this->actingAs($user)->get('/dashboard')->assertOk();
        $this->actingAs($user)->get(route('password.first-change'))
            ->assertRedirect(route('dashboard'));
    }

    // --- admin-issued reset ------------------------------------------------

    /**
     * Staff can REPLACE a password, never read one.
     *
     * This is the supported answer to "a member is locked out". Keeping real
     * passwords readable so staff could look one up would mean storing them
     * reversibly — one breach would hand over every member's actual password,
     * and with password reuse, their email and bank logins too.
     */
    public function test_an_admin_can_issue_a_temporary_password(): void
    {
        $this->seed(RbacSeeder::class);

        $admin = User::factory()->create();
        $admin->roles()->sync([Role::where('key', 'super_admin')->first()->id]);

        $member = User::factory()->create([
            'password'             => 'the-members-own-password',
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.users.reset-password', $member));

        $response->assertSessionHas('temporary_password');
        $issued = session('temporary_password')['password'];

        $member->refresh();

        $this->assertTrue($member->must_change_password, 'The temporary password is not single-use.');
        $this->assertTrue(Hash::check($issued, $member->password));
        $this->assertFalse(Hash::check('the-members-own-password', $member->password));
    }

    /** The password must never be written to the audit trail. */
    public function test_the_temporary_password_is_not_recorded_in_the_audit_log(): void
    {
        $this->seed(RbacSeeder::class);

        $admin = User::factory()->create();
        $admin->roles()->sync([Role::where('key', 'super_admin')->first()->id]);
        $member = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.users.reset-password', $member));

        $issued = session('temporary_password')['password'];

        $logged = \App\Models\AdminAuditLog::where('action', 'user.reset_password')->first();

        $this->assertNotNull($logged);
        $this->assertStringNotContainsString($issued, json_encode($logged->payload));
    }

    /** No route, anywhere, hands back an existing password. */
    public function test_no_admin_surface_exposes_a_stored_password(): void
    {
        $this->seed(RbacSeeder::class);

        $admin = User::factory()->create();
        $admin->roles()->sync([Role::where('key', 'super_admin')->first()->id]);

        $member = User::factory()->create(['password' => 'a-secret-only-they-know']);

        foreach ([
            route('admin.users.index'),
            route('admin.users.show', $member),
            route('admin.users.edit', $member),
        ] as $url) {
            $html = $this->actingAs($admin)->get($url)->getContent();

            $this->assertStringNotContainsString('a-secret-only-they-know', $html);
            // Nor the hash, which is offline-crackable once it leaves the DB.
            $this->assertStringNotContainsString($member->password, $html);
        }
    }
}

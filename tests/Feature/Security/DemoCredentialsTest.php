<?php

namespace Tests\Feature\Security;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The demo accounts must not share a password that lives in the repository.
 *
 * DemoUsersSeeder carried one as a class constant. A seeder is source code and
 * source code gets pushed, so that password was published — and the accounts
 * it created were still live months later, including a super_admin, on the
 * environment holding real users and the payment keys.
 */
class DemoCredentialsTest extends TestCase
{
    use RefreshDatabase;

    private function demo(string $local, UserRole $role = UserRole::Traveler): User
    {
        return User::factory()->create([
            'email' => $local.'@demo.vaytoven.local',
            'role'  => $role,
            'password' => 'a-known-password',
            'must_change_password' => false,
        ]);
    }

    // --- the root cause -------------------------------------------------------

    /** The seeder must not contain a literal password to publish. */
    public function test_the_seeder_holds_no_hardcoded_password(): void
    {
        $source = file_get_contents(database_path('seeders/DemoUsersSeeder.php'));

        $this->assertStringNotContainsString('private const PASSWORD', $source);
        $this->assertDoesNotMatchRegularExpression(
            "/const\s+PASSWORD\s*=\s*'/",
            $source,
            'a password constant is a published credential'
        );
        $this->assertStringContainsString('DEMO_PASSWORD', $source);
    }

    // --- the rotation ---------------------------------------------------------

    public function test_it_rotates_every_demo_account(): void
    {
        $this->demo('member');
        $this->demo('host');

        $this->artisan('vaytoven:rotate-demo-credentials')->assertSuccessful();

        foreach (User::where('email', 'like', '%@demo.vaytoven.local')->get() as $account) {
            $this->assertFalse(Hash::check('a-known-password', $account->password));
            $this->assertTrue($account->must_change_password);
        }
    }

    /**
     * An account nobody can log into is the right state for one whose password
     * was published. The replacement must not be echoed into a shell history
     * or a CI log on the way there.
     */
    public function test_the_new_password_is_never_printed(): void
    {
        $account = $this->demo('member');

        // Artisan::call, not $this->artisan(): the PendingCommand helper
        // buffers its own output and leaves Artisan::output() empty.
        $this->assertSame(0, Artisan::call('vaytoven:rotate-demo-credentials'));

        $output = Artisan::output();

        $this->assertNotSame('', trim($output), 'the command should report what it did');
        $this->assertStringNotContainsString('New password', $output);

        // The strongest form of the assertion: whatever hash the account now
        // carries, nothing in the output verifies against it.
        foreach (preg_split('/\s+/', $output) as $token) {
            if (strlen($token) >= 8) {
                $this->assertFalse(
                    Hash::check($token, $account->refresh()->password),
                    "the command printed the new password: {$token}"
                );
            }
        }
    }

    public function test_a_dry_run_changes_nothing(): void
    {
        $account = $this->demo('member');

        $this->artisan('vaytoven:rotate-demo-credentials --dry-run')->assertSuccessful();

        $this->assertTrue(Hash::check('a-known-password', $account->refresh()->password));
    }

    public function test_staff_only_leaves_ordinary_demo_accounts_alone(): void
    {
        $ordinary = $this->demo('member', UserRole::Member);
        $admin    = $this->demo('admin', UserRole::SuperAdmin);

        $this->artisan('vaytoven:rotate-demo-credentials --staff-only')->assertSuccessful();

        $this->assertTrue(Hash::check('a-known-password', $ordinary->refresh()->password));
        $this->assertFalse(Hash::check('a-known-password', $admin->refresh()->password));
    }

    /**
     * The legacy role column and the RBAC pivot are two separate paths into
     * the admin area. Revoking one and leaving the other still leaves a
     * privileged account.
     */
    public function test_revoking_staff_closes_both_privilege_paths(): void
    {
        $admin = $this->demo('admin', UserRole::SuperAdmin);
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $admin->roles()->sync([\App\Models\Role::where('key', 'super_admin')->firstOrFail()->id]);

        $this->artisan('vaytoven:rotate-demo-credentials --revoke-staff')->assertSuccessful();

        $admin->refresh();
        $this->assertFalse($admin->isStaff());
        $this->assertFalse($admin->isSuperAdmin());
        $this->assertSame(0, $admin->roles()->count());
    }

    public function test_it_never_touches_a_real_account(): void
    {
        $real = User::factory()->create([
            'email' => 'someone@example.com',
            'password' => 'a-known-password',
        ]);

        $this->artisan('vaytoven:rotate-demo-credentials')->assertSuccessful();

        $this->assertTrue(Hash::check('a-known-password', $real->refresh()->password));
    }

    public function test_it_reports_cleanly_when_there_is_nothing_to_do(): void
    {
        $this->artisan('vaytoven:rotate-demo-credentials')
            ->expectsOutputToContain('Nothing to do')
            ->assertSuccessful();
    }
}

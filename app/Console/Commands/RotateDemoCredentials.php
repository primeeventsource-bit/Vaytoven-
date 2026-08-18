<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Retire the demo accounts' shared password, and optionally their privilege.
 *
 * DemoUsersSeeder used to carry a fixed password as a class constant. A seeder
 * is source code and source code gets pushed, so that password was published —
 * and the accounts it created are still live, including a super_admin, on the
 * environment holding real users and the payment keys. The seeder no longer
 * works that way, but fixing the seeder does nothing for accounts that already
 * exist.
 *
 * The new password is random and is NOT printed. That is the point: these are
 * demo accounts, and an account nobody can log into is the correct state for a
 * published credential. If a demo login is needed again, re-seed with
 * DEMO_PASSWORD set and the accounts are usable once more.
 */
class RotateDemoCredentials extends Command
{
    protected $signature = 'vaytoven:rotate-demo-credentials
                            {--domain=@demo.vaytoven.local : Email suffix identifying demo accounts}
                            {--staff-only : Only touch accounts with staff privilege}
                            {--revoke-staff : Also downgrade staff demo accounts to traveler}
                            {--dry-run : Report what would change and change nothing}';

    protected $description = 'Rotate the password on demo accounts so the published one stops working';

    public function handle(): int
    {
        $domain = (string) $this->option('domain');

        $accounts = User::query()
            ->where('email', 'like', '%'.$domain)
            ->orderBy('email')
            ->get();

        if ($accounts->isEmpty()) {
            $this->info("No accounts matching *{$domain}. Nothing to do.");

            return self::SUCCESS;
        }

        if ($this->option('staff-only')) {
            $accounts = $accounts->filter(fn (User $u) => $u->isStaff())->values();
        }

        $dryRun = (bool) $this->option('dry-run');
        $revoke = (bool) $this->option('revoke-staff');
        $rows   = [];

        foreach ($accounts as $account) {
            $wasStaff = $account->isStaff();
            $role     = $account->role?->value ?? 'none';
            $actions  = ['password rotated'];

            if ($revoke && $wasStaff) {
                $actions[] = 'downgraded to traveler';
            }

            $rows[] = [$account->email, $role, $wasStaff ? 'staff' : '-', implode(', ', $actions)];

            if ($dryRun) {
                continue;
            }

            $account->forceFill([
                // Never surfaced. An unusable demo account is the right state
                // for a credential that has been published.
                'password'             => Str::password(32),
                'must_change_password' => true,
                'remember_token'       => Str::random(60),
            ]);

            if ($revoke && $wasStaff) {
                $account->role = UserRole::Traveler;
            }

            $account->save();

            // Detaching pivot roles too: the RBAC gate and the legacy role
            // column are separate paths to the admin area and leaving one
            // attached would leave the account privileged.
            if ($revoke && $wasStaff) {
                $account->roles()->detach();
            }

            // The application log, not admin_audit_logs. That table requires a
            // non-null actor_user_id by design — the rule is that every
            // privileged write records WHO did it — and a command run from a
            // shell has no acting user. Inventing one would put a false name
            // on an evidence record, which is worse than logging elsewhere.
            Log::warning('demo account credentials rotated.', [
                'email'         => $account->email,
                'previous_role' => $role,
                'staff_revoked' => $revoke && $wasStaff,
            ]);
        }

        $this->table(['Email', 'Role', 'Privilege', 'Action'], $rows);

        if ($dryRun) {
            $this->warn('Dry run — nothing was changed.');

            return self::SUCCESS;
        }

        $this->info(count($rows).' demo account(s) rotated.');
        $this->line('The new passwords are random and were not recorded. To make these accounts');
        $this->line('usable again, re-run DemoUsersSeeder with DEMO_PASSWORD set.');

        return self::SUCCESS;
    }
}

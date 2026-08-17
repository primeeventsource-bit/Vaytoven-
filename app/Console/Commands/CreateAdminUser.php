<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Create or promote a backend account.
 *
 *   php artisan vaytoven:create-admin contact@vaytoven.com --role=super_admin
 *
 * The password is NOT a hardcoded constant and is not committed anywhere. Pass
 * it with --password, or omit it and the command generates a strong one and
 * prints it once.
 *
 * This exists because the alternative — a seeder with the credential written
 * into it — is how `admin@demo.vaytoven.local` ended up with a working
 * super-admin password published in a public GitHub repository. A seeder is
 * source code; source code gets pushed.
 *
 * Whatever the password is, the account is created with must_change_password
 * set, so it stops working as a shared secret the moment the owner signs in.
 */
class CreateAdminUser extends Command
{
    protected $signature = 'vaytoven:create-admin
                            {email : Email address for the account}
                            {--role=super_admin : Role slug (super_admin, admin, member_specialist, ...)}
                            {--name= : Display name}
                            {--password= : Password to set. Omit to generate one}
                            {--no-force-change : Do NOT require a password change at first sign-in}';

    protected $description = 'Create or promote a backend user, forcing a password change at first sign-in';

    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));
        $slug  = (string) $this->option('role');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("Not a valid email address: {$email}");

            return self::FAILURE;
        }

        $role = Role::query()->where('key', $slug)->first();

        if (! $role) {
            $this->error("No role with slug '{$slug}'. Seed RBAC first (db:seed --class=RbacSeeder).");
            $this->line('  Available: '.Role::query()->pluck('key')->implode(', '));

            return self::FAILURE;
        }

        $password  = (string) ($this->option('password') ?: Str::password(16, symbols: false));
        $generated = ! $this->option('password');

        $user = User::query()->where('email', $email)->first();
        $existed = (bool) $user;

        if (! $user) {
            $user = new User(['email' => $email]);
        }

        $user->forceFill([
            'name'                 => $this->option('name') ?: ($user->name ?: Str::before($email, '@')),
            'password'             => $password,          // hashed by cast
            'must_change_password' => ! $this->option('no-force-change'),
            'password_changed_at'  => null,
            'email_verified_at'    => $user->email_verified_at ?? now(),
            'deactivated_at'       => null,
        ]);

        // Keep the legacy enum column in step with the RBAC role where one
        // matches, so the fail-open admin checks agree with the role table.
        if ($enum = UserRole::tryFrom($slug)) {
            $user->role = $enum;
        }

        $user->save();

        // sync(), not attach(): re-running must not leave the account holding
        // a role somebody removed on purpose.
        $user->roles()->sync([$role->id]);

        $this->newLine();
        $this->info(($existed ? 'Updated' : 'Created').": {$email}");
        $this->line('  Role ............. '.$role->name.' ('.$role->key.')');
        $this->line('  Must change pw ... '.($user->must_change_password ? 'yes, at first sign-in' : 'NO'));

        if ($generated) {
            $this->newLine();
            $this->warn('  Generated password (shown once, not stored anywhere else):');
            $this->line('    '.$password);
        }

        $this->newLine();
        $this->line('  Do not commit this password. It is a one-time credential —');
        $this->line('  the account holder replaces it the first time they sign in.');

        return self::SUCCESS;
    }
}

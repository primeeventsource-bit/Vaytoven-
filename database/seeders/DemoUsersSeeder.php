<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds six demo accounts — one per role — for local development and
 * stakeholder demos. Refuses to run in production. Re-runnable: each block
 * keys on a stable identifier and skips if the record already exists.
 *
 * Usage:  php artisan db:seed --class=DemoUsersSeeder
 *
 * Universal password: Vaytoven$2026  (User::password has the `hashed` cast,
 * so we pass plaintext and Laravel bcrypts on save.)
 *
 * Demo emails all use the fake `.local` TLD so they can never receive mail
 * and can never be confused with real customers. To wipe before launch:
 *   DELETE FROM users WHERE email LIKE '%@demo.vaytoven.local';
 */
class DemoUsersSeeder extends Seeder
{
    private const PASSWORD = 'Vaytoven$2026';
    private const DOMAIN   = '@demo.vaytoven.local';

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->warn('DemoUsersSeeder refused: APP_ENV=production.');
            return;
        }

        $users = $this->seedUsers();
        $this->seedHostListings($users['host']);
        $this->seedMemberEnquiry($users['member']);
        $this->seedReturningGuestLoginHistory($users['guest']);

        $this->command->info('DemoUsersSeeder complete. Password for all six accounts: ' . self::PASSWORD);
    }

    /**
     * @return array<string,User> keyed by local-part of the email
     */
    private function seedUsers(): array
    {
        $accounts = [
            'admin'      => ['Alex Vaytoven',     UserRole::SuperAdmin],
            'specialist' => ['Jordan Reyes',      UserRole::MemberSpecialist],
            'host'       => ['Maya Bennett',      UserRole::Host],
            'member'     => ['Margaret Mitchell', UserRole::Member],
            'newclient'  => ['Sarah Anderson',    UserRole::Traveler],
            'guest'      => ['Daniel Brown',      UserRole::Traveler],
        ];

        $users = [];
        foreach ($accounts as $local => [$name, $role]) {
            $email = $local . self::DOMAIN;
            $users[$local] = User::updateOrCreate(
                ['email' => $email],
                [
                    'name'              => $name,
                    'password'          => self::PASSWORD, // hashed by cast
                    'role'              => $role,
                    'email_verified_at' => now(),
                ]
            );
            $this->command->info("  ✓ user  {$email}  ({$role->value})");
        }

        return $users;
    }

    /**
     * Three sample listings owned by Maya (host@demo). Keyed on (host_id,title)
     * so a re-run is a no-op. Status = 'active' so they show in search.
     */
    private function seedHostListings(User $host): void
    {
        $listings = [
            [
                'title'              => 'Cliffside Villa Uluwatu',
                'description'        => 'Glass-walled three-bedroom perched above Padang Padang. Infinity pool, outdoor shower, full kitchen, daily housekeeping.',
                'latitude'           => -8.8290,
                'longitude'          => 115.0844,
                'city'               => 'Uluwatu',
                'region'             => 'Bali',
                'country'            => 'ID',
                'capacity'           => 6,
                'bedrooms'           => 3,
                'beds'               => 4,
                'bathrooms'          => 3.0,
                'base_nightly_cents' => 32500,
                'cleaning_fee_cents' => 7500,
            ],
            [
                'title'              => 'Modern Cabin Lake Tahoe',
                'description'        => 'Heated four-bedroom A-frame two minutes from Northstar. Hot tub, board storage, fully stocked kitchen, fireplace.',
                'latitude'           => 39.0968,
                'longitude'          => -120.0324,
                'city'               => 'Truckee',
                'region'             => 'California',
                'country'            => 'US',
                'capacity'           => 8,
                'bedrooms'           => 4,
                'beds'               => 5,
                'bathrooms'          => 3.0,
                'base_nightly_cents' => 28000,
                'cleaning_fee_cents' => 9500,
            ],
            [
                'title'              => 'Historic Pied-à-Terre Paris',
                'description'        => 'One-bedroom Haussmann apartment in the 6th, walk to the Luxembourg gardens. Original parquet, espresso bar, lift.',
                'latitude'           => 48.8504,
                'longitude'          => 2.3324,
                'city'               => 'Paris',
                'region'             => 'Île-de-France',
                'country'            => 'FR',
                'capacity'           => 2,
                'bedrooms'           => 1,
                'beds'               => 1,
                'bathrooms'          => 1.0,
                'base_nightly_cents' => 41000,
                'cleaning_fee_cents' => 6500,
            ],
        ];

        foreach ($listings as $row) {
            DB::table('properties')->updateOrInsert(
                ['host_id' => $host->id, 'title' => $row['title']],
                $row + [
                    'listing_source'      => 'host',
                    'cancellation_policy' => 'moderate',
                    'minimum_nights'      => 2,
                    'status'              => 'active',
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]
            );
            $this->command->info("  ✓ listing  {$row['title']}");
        }
    }

    /**
     * Margaret's Managed Listing Program enquiry, in `new` status so it lands
     * in the specialist's queue. Keyed on email so re-running is safe.
     */
    private function seedMemberEnquiry(User $member): void
    {
        $exists = DB::table('members_enquiries')->where('email', $member->email)->exists();
        if ($exists) {
            $this->command->info('  · enquiry  already present for member@');
            return;
        }

        DB::table('members_enquiries')->insert([
            'reference'      => 'VYT-' . strtoupper(Str::random(8)),
            'first_name'     => 'Margaret',
            'last_name'      => 'Mitchell',
            'email'          => $member->email,
            'phone'          => '+1-555-010-0004',
            'club'           => 'Marriott Vacation Club',
            'property'       => 'Marriott Grande Vista (Orlando, FL)',
            'points'         => '250000',
            'contact_window' => 'Afternoons (1–5pm ET)',
            'status'         => 'new',
            'consented_at'   => now()->subDays(2),
            'source_url'     => 'https://app.vaytoven.com/members',
            'ip'             => '192.0.2.41', // RFC 5737 documentation range — never a real address
            'user_agent'     => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15',
            'created_at'     => now()->subDays(2),
            'updated_at'     => now()->subDays(2),
        ]);

        $this->command->info('  ✓ enquiry  Margaret Mitchell → specialist queue');
    }

    /**
     * 90 days of login_sessions for Daniel (the returning guest), to give the
     * Activity tab realistic data: ~weekly logins from Trenton, a short
     * Orlando trip mid-window, and one San Juan login flagged new_country.
     *
     * Idempotent: bails if Daniel already has any login_sessions rows.
     */
    private function seedReturningGuestLoginHistory(User $guest): void
    {
        $hasHistory = DB::table('login_sessions')->where('user_id', $guest->id)->exists();
        if ($hasHistory) {
            $this->command->info('  · login history  already present for guest@');
            return;
        }

        $rows = [];

        // ~Weekly logins from Trenton, NJ over the 90-day window.
        // RFC 5737 documentation IPs only — never real addresses.
        for ($daysAgo = 90; $daysAgo >= 1; $daysAgo -= 7) {
            $rows[] = $this->loginRow($guest->id, $daysAgo, [
                'auth_event'  => 'login',
                'ip_address'  => '198.51.100.' . random_int(2, 250),
                'country'     => 'US',
                'region'      => 'NJ',
                'city'        => 'Trenton',
                'latitude'    => 40.2206,
                'longitude'   => -74.7597,
                'device_type' => 'desktop',
                'os'          => 'macOS',
                'browser'     => 'Safari',
            ]);
        }

        // Short Orlando, FL trip ~day 45 (two logins on consecutive days, mobile).
        $rows[] = $this->loginRow($guest->id, 45, [
            'auth_event'  => 'login',
            'ip_address'  => '203.0.113.18',
            'country'     => 'US',
            'region'      => 'FL',
            'city'        => 'Orlando',
            'latitude'    => 28.5383,
            'longitude'   => -81.3792,
            'device_type' => 'mobile',
            'os'          => 'iOS 17',
            'browser'     => 'Mobile Safari',
        ]);
        $rows[] = $this->loginRow($guest->id, 44, [
            'auth_event'  => 'login',
            'ip_address'  => '203.0.113.18',
            'country'     => 'US',
            'region'      => 'FL',
            'city'        => 'Orlando',
            'latitude'    => 28.5383,
            'longitude'   => -81.3792,
            'device_type' => 'mobile',
            'os'          => 'iOS 17',
            'browser'     => 'Mobile Safari',
        ]);

        // The flagged one: San Juan PR, ~day 30. Suspicious = new_country.
        $rows[] = $this->loginRow($guest->id, 30, [
            'auth_event'         => 'login',
            'ip_address'         => '203.0.113.77',
            'country'            => 'PR',
            'region'             => 'San Juan',
            'city'               => 'San Juan',
            'latitude'           => 18.4655,
            'longitude'          => -66.1057,
            'device_type'        => 'mobile',
            'os'                 => 'iOS 17',
            'browser'             => 'Mobile Safari',
            'is_suspicious'      => true,
            'suspicious_reasons' => json_encode(['new_country']),
        ]);

        DB::table('login_sessions')->insert($rows);

        $this->command->info('  ✓ login history  ' . count($rows) . ' rows for guest@ (incl. 1 new_country flag)');
    }

    /**
     * Builds a login_sessions row with sensible defaults; caller overrides
     * any field via $overrides.
     *
     * @param array<string,mixed> $overrides
     * @return array<string,mixed>
     */
    private function loginRow(int $userId, int $daysAgo, array $overrides): array
    {
        $occurredAt = Carbon::now()->subDays($daysAgo)->setTime(random_int(8, 22), random_int(0, 59));

        return array_merge([
            'user_id'            => $userId,
            'auth_event'         => 'login',
            'surface'            => 'web',
            'session_id'         => Str::random(40),
            'ip_address'         => null,
            'country'            => null,
            'region'             => null,
            'city'               => null,
            'latitude'           => null,
            'longitude'          => null,
            'asn'                => null,
            'is_vpn'             => false,
            'is_tor'             => false,
            'is_datacenter'      => false,
            'device_type'        => 'desktop',
            'os'                 => null,
            'browser'            => null,
            'user_agent'         => 'Mozilla/5.0',
            'is_suspicious'      => false,
            'suspicious_reasons' => null,
            'occurred_at'        => $occurredAt,
        ], $overrides);
    }
}

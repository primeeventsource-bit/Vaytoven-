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
        $enquiryId = $this->seedMemberEnquiry($users['member']);
        $this->seedManagedListingForMember($users['admin'], $enquiryId);
        $this->seedReturningGuestLoginHistory($users['guest']);
        $this->seedPropertyViews($users);
        $this->seedHostBookings($users);
        $this->seedMemberOffer($users);

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
     *
     * @return int the enquiry's id (existing or freshly inserted)
     */
    private function seedMemberEnquiry(User $member): int
    {
        $existing = DB::table('members_enquiries')->where('email', $member->email)->first();
        if ($existing) {
            $this->command->info('  · enquiry  already present for member@');
            return (int) $existing->id;
        }

        $id = DB::table('members_enquiries')->insertGetId([
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
        return (int) $id;
    }

    /**
     * Margaret's managed listing — the one her member dashboard renders
     * analytics for. host_id points at admin because Vaytoven operates
     * managed listings on the member's behalf; the relationship to the
     * member is via converted_from_enquiry_id.
     */
    private function seedManagedListingForMember(User $admin, int $enquiryId): void
    {
        $exists = DB::table('properties')
            ->where('converted_from_enquiry_id', $enquiryId)
            ->exists();
        if ($exists) {
            $this->command->info('  · managed listing  already present');
            return;
        }

        DB::table('properties')->insert([
            'host_id'                   => $admin->id,
            'listing_source'            => 'managed',
            'converted_from_enquiry_id' => $enquiryId,
            'title'                     => 'Marriott Grande Vista Villa Suite',
            'description'               => 'Three-bedroom villa suite at Marriott Grande Vista. Two pools on-site, full kitchen, washer/dryer, 12 minutes to Disney Springs. Vaytoven manages the listing and guest experience end-to-end.',
            'latitude'                  => 28.4196,
            'longitude'                 => -81.5812,
            'city'                      => 'Orlando',
            'region'                    => 'Florida',
            'country'                   => 'US',
            'capacity'                  => 8,
            'bedrooms'                  => 3,
            'beds'                      => 4,
            'bathrooms'                 => 2.5,
            'base_nightly_cents'        => 24500,
            'cleaning_fee_cents'        => 8500,
            'cancellation_policy'       => 'moderate',
            'minimum_nights'            => 3,
            'status'                    => 'active',
            'created_at'                => now(),
            'updated_at'                => now(),
        ]);

        $this->command->info('  ✓ managed listing  Marriott Grande Vista → member dashboard');
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
     * ~180 fake property_views distributed across all demo listings (Maya's 3
     * host-listed properties + Margaret's 1 managed listing) over the last 30
     * days, weighted across seven cities so the dashboard's geo map renders
     * with real pins on first login.
     *
     * Idempotent: bails if any view already exists for the host's properties.
     * IPs use RFC 5737 documentation ranges so they can never collide with a
     * real visitor.
     *
     * @param array<string,User> $users
     */
    private function seedPropertyViews(array $users): void
    {
        $hostListingIds = DB::table('properties')
            ->where('host_id', $users['host']->id)
            ->pluck('id')
            ->all();

        $managedListingIds = DB::table('properties')
            ->where('listing_source', 'managed')
            ->pluck('id')
            ->all();

        $listingIds = array_values(array_unique(array_merge($hostListingIds, $managedListingIds)));
        if (empty($listingIds)) {
            $this->command->info('  · property views  no listings to seed against, skipping');
            return;
        }

        $hasViews = DB::table('property_views')
            ->whereIn('property_id', $listingIds)
            ->exists();
        if ($hasViews) {
            $this->command->info('  · property views  already present, skipping');
            return;
        }

        // Americas-only city set so seeded pins fit the map's restricted
        // bounds (US, Canada, Mexico, Central + South America). Weights
        // sum loosely to 180 with realistic N→S distribution.
        $cities = [
            ['city' => 'Newark',        'region' => 'NJ', 'country' => 'US', 'lat' => 40.7357,  'lng' => -74.1724,  'count' => 32],
            ['city' => 'San Francisco', 'region' => 'CA', 'country' => 'US', 'lat' => 37.7749,  'lng' => -122.4194, 'count' => 28],
            ['city' => 'Chicago',       'region' => 'IL', 'country' => 'US', 'lat' => 41.8781,  'lng' => -87.6298,  'count' => 22],
            ['city' => 'Miami',         'region' => 'FL', 'country' => 'US', 'lat' => 25.7617,  'lng' => -80.1918,  'count' => 18],
            ['city' => 'Toronto',       'region' => 'ON', 'country' => 'CA', 'lat' => 43.6532,  'lng' => -79.3832,  'count' => 20],
            ['city' => 'Vancouver',     'region' => 'BC', 'country' => 'CA', 'lat' => 49.2827,  'lng' => -123.1207, 'count' => 12],
            ['city' => 'Mexico City',   'region' => 'CDMX', 'country' => 'MX', 'lat' => 19.4326, 'lng' => -99.1332, 'count' => 18],
            ['city' => 'São Paulo',     'region' => 'SP', 'country' => 'BR', 'lat' => -23.5505, 'lng' => -46.6333,  'count' => 14],
            ['city' => 'Buenos Aires',  'region' => 'BA', 'country' => 'AR', 'lat' => -34.6037, 'lng' => -58.3816,  'count' => 10],
            ['city' => 'Lima',          'region' => 'Lima', 'country' => 'PE', 'lat' => -12.0464, 'lng' => -77.0428, 'count' => 8],
        ];

        $rows = [];
        $now = Carbon::now();
        foreach ($cities as $c) {
            for ($i = 0; $i < $c['count']; $i++) {
                $rows[] = [
                    'property_id'    => $listingIds[array_rand($listingIds)],
                    'viewer_user_id' => null,
                    'visitor_id'     => (string) Str::uuid(),
                    'ip_address'     => '198.51.100.' . random_int(2, 250),
                    'country'        => $c['country'],
                    'region'         => $c['region'],
                    'city'           => $c['city'],
                    'latitude'       => $c['lat'],
                    'longitude'      => $c['lng'],
                    'user_agent'     => 'Mozilla/5.0 (DemoUsersSeeder synthetic)',
                    'occurred_at'    => $now->copy()->subDays(random_int(0, 29))->subHours(random_int(0, 23)),
                ];
            }
        }

        // Chunk to keep inserts comfortably under MySQL's max_allowed_packet.
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('property_views')->insert($chunk);
        }

        $this->command->info('  ✓ property views  ' . count($rows) . ' rows across ' . count($cities) . ' cities and ' . count($listingIds) . ' listings');
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

    /**
     * A few bookings on Maya's listings so the host dashboard's "Recent
     * bookings" table renders with real-looking data instead of an empty
     * state. Idempotent: keyed on (property_id, traveler_id, check_in_date)
     * via existence check, so re-runs don't duplicate.
     *
     * Uses Eloquent so the booted() creating hook generates the
     * VYT-XXXXXX confirmation code and the created hook records the
     * initial booking_state_transition row.
     *
     * @param array<string,\App\Models\User> $users
     */
    private function seedHostBookings(array $users): void
    {
        $hostListings = \App\Models\Property::where('host_id', $users['host']->id)->get()->keyBy('title');
        if ($hostListings->isEmpty()) {
            return;
        }

        $samples = [
            [
                'property' => 'Cliffside Villa Uluwatu',
                'traveler' => $users['guest'],     // Daniel — returning guest
                'check_in' => now()->addDays(28),
                'nights'   => 5,
                'guests'   => 2,
                'status'   => 'confirmed',
            ],
            [
                'property' => 'Modern Cabin Lake Tahoe',
                'traveler' => $users['newclient'], // Sarah — new client
                'check_in' => now()->addDays(10),
                'nights'   => 3,
                'guests'   => 4,
                'status'   => 'pending_payment',
            ],
            [
                'property' => 'Historic Pied-à-Terre Paris',
                'traveler' => $users['guest'],
                'check_in' => now()->subDays(20),
                'nights'   => 4,
                'guests'   => 2,
                'status'   => 'completed',
            ],
        ];

        $created = 0;
        foreach ($samples as $s) {
            $listing = $hostListings[$s['property']] ?? null;
            if (! $listing) {
                continue;
            }

            $exists = \App\Models\Booking::query()
                ->where('property_id', $listing->id)
                ->where('traveler_id', $s['traveler']->id)
                ->whereDate('check_in_date', $s['check_in']->toDateString())
                ->exists();
            if ($exists) {
                continue;
            }

            $checkOut      = $s['check_in']->copy()->addDays($s['nights']);
            $subtotalCents = (int) $listing->base_nightly_cents * $s['nights'];
            $cleaningCents = (int) $listing->cleaning_fee_cents;
            $serviceCents  = (int) round($subtotalCents * 0.12);
            $taxCents      = (int) round(($subtotalCents + $cleaningCents) * 0.085);
            $totalCents    = $subtotalCents + $cleaningCents + $serviceCents + $taxCents;

            \App\Models\Booking::create([
                'property_id'         => $listing->id,
                'traveler_id'         => $s['traveler']->id,
                'check_in_date'       => $s['check_in']->toDateString(),
                'check_out_date'      => $checkOut->toDateString(),
                'guests'              => $s['guests'],
                'nightly_rate_cents'  => $listing->base_nightly_cents,
                'nights'              => $s['nights'],
                'subtotal_cents'      => $subtotalCents,
                'cleaning_fee_cents'  => $cleaningCents,
                'service_fee_cents'   => $serviceCents,
                'tax_cents'           => $taxCents,
                'total_cents'         => $totalCents,
                'cancellation_policy' => $listing->cancellation_policy?->value ?? 'moderate',
                'status'              => $s['status'],
            ]);
            $created++;
        }

        if ($created > 0) {
            $this->command->info("  ✓ host bookings  {$created} new on Maya's listings");
        } else {
            $this->command->info('  · host bookings  already present, skipping');
        }
    }

    /**
     * One pending offer to Margaret for her managed listing, so the member
     * dashboard's Offers section has something live to demo with. Sent by
     * admin (the specialist persona), expires in 14 days.
     *
     * @param array<string,\App\Models\User> $users
     */
    private function seedMemberOffer(array $users): void
    {
        $managedListing = \App\Models\Property::where('listing_source', 'managed')
            ->orderBy('id')
            ->first();
        if (! $managedListing) {
            return;
        }

        // ~mid of month, 6 months out — typical Marriott Vacation Club
        // reservation-window timing.
        $checkIn = now()->addMonths(6)->startOfMonth()->addDays(14);

        $exists = \App\Models\MemberOffer::query()
            ->where('member_user_id', $users['member']->id)
            ->where('property_id', $managedListing->id)
            ->whereDate('proposed_check_in', $checkIn->toDateString())
            ->exists();
        if ($exists) {
            $this->command->info('  · member offer  already present, skipping');
            return;
        }

        \App\Models\MemberOffer::create([
            'member_user_id'         => $users['member']->id,
            'property_id'            => $managedListing->id,
            'sent_by_user_id'        => $users['admin']->id,
            'proposed_check_in'      => $checkIn->toDateString(),
            'proposed_check_out'     => $checkIn->copy()->addDays(7)->toDateString(),
            'proposed_guests'        => 6,
            'payout_to_member_cents' => 180000,  // $1,800
            'status'                 => 'pending',
            'instructions'           => "Marriott Vacation Club's reservation window opens 12 months from check-in for owners. We've confirmed the resort has open inventory for these dates. Once you accept, log into your Marriott Vacation Club account, reserve the unit using your points, and reply with the confirmation number. We'll send the final guest names ~7 days before check-in so you can add them to the guest pass list.",
            'sent_at'                => now()->subDays(1),
            'expires_at'             => now()->addDays(13),
        ]);

        $this->command->info('  ✓ member offer  1 pending offer to Margaret ($1,800 payout, 7 nights)');
    }
}

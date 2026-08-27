<?php

namespace App\Console\Commands;

use App\Enums\AvailabilityWeekStatus;
use App\Enums\ListingType;
use App\Enums\PropertyStatus;
use App\Enums\UserRole;
use App\Models\MediaAsset;
use App\Models\Property;
use App\Models\PropertyView;
use App\Models\TermsAcceptance;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Services\Legal\LegalDocumentRegistry;
use App\Services\Listings\PhotoIngestor;
use App\Services\Listings\PublicPropertyRef;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

/**
 * Provisions a member login an underwriter can actually use.
 *
 * An underwriter is being asked to assess what the product does, so the account
 * has to behave like a real one: a live advertisement with photographs, and an
 * engagement map with enough history that it draws pins instead of an empty
 * state. A blank dashboard behind a working password answers nothing.
 *
 * Everything it creates is real, in the ordinary tables. There is no demo mode
 * and no special-cased rendering — what the underwriter sees is what a paying
 * member sees, which is the only version worth showing.
 *
 * Safe to run more than once. The account is matched by email and topped up
 * rather than duplicated, so re-running to extend the history does not leave a
 * second listing behind.
 */
class MakeUnderwritingLogin extends Command
{
    protected $signature = 'vaytoven:underwriting-login
        {--email=underwriting.review@vaytoven.com : the login to create or refresh}
        {--days=45 : how far back to spread the engagement history}
        {--new-password : issue a fresh password for an account that already exists}';

    protected $description = 'Create a member login with a live listing and engagement history, for underwriting review';

    /**
     * Where the traffic comes from.
     *
     * Real coordinates, and every city over MIN_EVENTS_PER_PIN so each one
     * actually draws. A city below the threshold is suppressed by design — it
     * would describe a person rather than an audience — so seeding one would
     * look like a bug.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: float, 4: float, 5: int}>
     */
    private const AUDIENCE = [
        ['Orlando',       'FL', 'US', 28.5383,  -81.3792, 34],
        ['New York',      'NY', 'US', 40.7128,  -74.0060, 27],
        ['Chicago',       'IL', 'US', 41.8781,  -87.6298, 19],
        ['Dallas',        'TX', 'US', 32.7767,  -96.7970, 16],
        ['Atlanta',       'GA', 'US', 33.7490,  -84.3880, 12],
        ['Los Angeles',   'CA', 'US', 34.0522, -118.2437, 11],
        ['Toronto',       'ON', 'CA', 43.6532,  -79.3832,  7],
        ['London',        null, 'GB', 51.5074,   -0.1278,  5],
    ];

    /** Split across the two kinds of traffic the map counts. */
    private const SITE_EVENTS = ['page_view', 'page.viewed', 'website.visited', 'cta_click'];

    private const AD_EVENTS = ['gallery.opened', 'advertisement.clicked', 'amenity.viewed', 'favorite.saved'];

    public function handle(PhotoIngestor $ingestor, LegalDocumentRegistry $legal): int
    {
        $email = strtolower(trim((string) $this->option('email')));
        $days  = max(7, (int) $this->option('days'));

        $existing = User::where('email', $email)->first();
        $password = null;

        if (! $existing || $this->option('new-password')) {
            $password = Str::password(16, symbols: false);
        }

        $user = $this->member($email, $password, $existing);
        $this->acceptTermsFor($user, $legal);

        $property = $this->listing($user);
        $photos   = $this->attachPhotos($property, $ingestor, $user);
        $this->availability($property);

        // Readiness is the same gate the admin activation uses. If the listing
        // cannot pass it, it stays off the public site and the command says so
        // rather than handing over a login to a broken page.
        $blockers = \App\Support\Listings\ListingReadiness::blockers($property->refresh());

        if ($blockers === []) {
            $property->forceFill(['status' => PropertyStatus::Active])->save();
        }

        $events = $this->engagement($property, $days);

        $this->report($user, $property, $password, $photos, $events, $blockers);

        return $blockers === [] ? self::SUCCESS : self::FAILURE;
    }

    private function member(string $email, ?string $password, ?User $existing): User
    {
        $user = $existing ?? new User(['email' => $email]);

        $user->fill([
            'name'       => 'Underwriting Review',
            'first_name' => 'Underwriting',
            'last_name'  => 'Review',
            'phone'      => '+1 555 018 4400',
        ]);

        if ($password !== null) {
            $user->password = Hash::make($password);
        }

        $user->forceFill([
            'role'                 => UserRole::Member,
            // An underwriter given a password should land on the dashboard,
            // not on a change-password screen with nothing behind it.
            'must_change_password' => false,
            'email_verified_at'    => $user->email_verified_at ?? now(),
        ])->save();

        return $user;
    }

    /**
     * Pre-accept the current documents.
     *
     * Otherwise the first sign-in lands on the review-and-accept screen, and
     * the reviewer either accepts terms on someone else's behalf or gives up
     * before seeing anything.
     */
    private function acceptTermsFor(User $user, LegalDocumentRegistry $legal): void
    {
        foreach ($legal->registrationRequired() as $version) {
            TermsAcceptance::firstOrCreate(
                ['user_id' => $user->id, 'terms_version_id' => $version->id],
                ['accepted_at' => now(), 'ip_address' => '127.0.0.1', 'user_agent' => 'artisan vaytoven:underwriting-login'],
            );
        }
    }

    private function listing(User $user): Property
    {
        $property = Property::where('host_id', $user->id)->orderBy('id')->first();

        if (! $property) {
            $property = new Property(['host_id' => $user->id]);
            $property->public_ref = app(PublicPropertyRef::class)->nextFor($user);
        }

        $property->fill([
            'listing_source'  => 'host',
            'title'           => 'Grande Vista Three-Bedroom Villa Suite',
            'short_description' => 'A full week for six, ten minutes from the parks.',
            'description'     => 'Seven days and six nights in a three-bedroom villa suite with a '
                .'full kitchen, a private balcony and resort access throughout the stay. Ten '
                .'minutes from the parks and twenty from the airport.',
            'address_line'    => '5925 Avenida Vista',
            'city'            => 'Orlando',
            'region'          => 'FL',
            'country'         => 'US',
            'postal_code'     => '32821',
            'capacity'        => 6,
            'bedrooms'        => 3,
            'beds'            => 4,
            'bathrooms'       => 2,
            'price_cents'     => 249900,
            'listing_type'    => ListingType::Rent,
            'minimum_nights'  => 7,
        ]);

        $property->save();

        return $property;
    }

    /**
     * Photographs, copied out of the shared library.
     *
     * Copied rather than referenced, the same as any other listing: what is on
     * a live advertisement must not change because somebody reorganised the
     * library afterwards.
     */
    private function attachPhotos(Property $property, PhotoIngestor $ingestor, User $actor): int
    {
        $have = $property->photos()->count();

        if ($have > 0) {
            return $have;
        }

        $assets = MediaAsset::query()->orderBy('id')->take(8)->get();

        if ($assets->isEmpty()) {
            return 0;
        }

        $added = 0;

        foreach ($assets as $asset) {
            try {
                $ingestor->copyAssetToProperty($asset, $property, $actor, 'other');
                $added++;
            } catch (Throwable $e) {
                $this->warn('  could not copy asset '.$asset->id.': '.$e->getMessage());
            }
        }

        if ($added > 0 && ! $property->photos()->where('is_cover', true)->exists()) {
            $property->photos()->orderBy('sort_order')->first()?->makeCover();
        }

        return $added;
    }

    private function availability(Property $property): void
    {
        $live = $property->availabilityWeeks()
            ->whereDate('ends_on', '>=', now()->toDateString())
            ->count();

        if ($live > 0) {
            return;
        }

        // Whole weeks, because that is what the programs these members belong
        // to actually trade in — seven days, six nights.
        for ($i = 1; $i <= 4; $i++) {
            $starts = now()->addWeeks($i * 2)->startOfWeek();

            $property->availabilityWeeks()->create([
                'starts_on' => $starts->toDateString(),
                'ends_on'   => $starts->copy()->addDays(7)->toDateString(),
                'status'    => AvailabilityWeekStatus::Available->value,
            ]);
        }
    }

    /**
     * The history behind the map and the Ad Views figure.
     *
     * Spread across the window rather than stamped at one moment, so the 7-day
     * and 30-day filters on the dashboard both return something and the numbers
     * move when you change them — a reviewer will try that.
     *
     * @return array{views: int, events: int, cities: int}
     */
    private function engagement(Property $property, int $days): array
    {
        $views  = 0;
        $events = 0;

        DB::transaction(function () use ($property, $days, &$views, &$events) {
            foreach (self::AUDIENCE as $i => [$city, $region, $country, $lat, $lng, $weight]) {
                for ($n = 0; $n < $weight; $n++) {
                    // Deterministic spread, weighted towards recent: no
                    // randomness, so re-running with the same window produces
                    // the same shape.
                    $ago = (int) floor((($n * 7 + $i * 3) % $days));

                    $when      = now()->subDays($ago)->subMinutes(($n * 17 + $i * 5) % 1440);
                    $visitorId = 'uw-'.$i.'-'.$n;

                    PropertyView::create([
                        'property_id' => $property->id,
                        'visitor_id'  => $visitorId,
                        'ip_address'  => null,
                        'country'     => $country,
                        'region'      => $region,
                        'city'        => $city,
                        'latitude'    => $lat,
                        'longitude'   => $lng,
                        'occurred_at' => $when,
                    ]);
                    $views++;

                    // Roughly every other visit also does something on the ad.
                    if ($n % 2 === 0) {
                        $isAdEvent = $n % 4 === 0;

                        TrackingEvent::create([
                            'event_type'        => $isAdEvent
                                ? self::AD_EVENTS[$n % count(self::AD_EVENTS)]
                                : self::SITE_EVENTS[$n % count(self::SITE_EVENTS)],
                            'visitor_id'        => $visitorId,
                            'surface'           => 'web',
                            'country'           => $country,
                            'region'            => $region,
                            'city'              => $city,
                            'latitude'          => $lat,
                            'longitude'         => $lng,
                            'device_type'       => $n % 3 === 0 ? 'mobile' : 'desktop',
                            'path'              => '/properties/'.$property->getRouteKey(),
                            // Ad interactions are matched to the listing by
                            // reference, not id — see MemberEngagementMap.
                            'subject_type'      => $isAdEvent ? 'property' : null,
                            'subject_reference' => $isAdEvent ? $property->reference : null,
                            'occurred_at'       => $when,
                        ]);
                        $events++;
                    }
                }
            }
        });

        return ['views' => $views, 'events' => $events, 'cities' => count(self::AUDIENCE)];
    }

    /** @param array<int, string> $blockers */
    private function report(User $user, Property $property, ?string $password, int $photos, array $events, array $blockers): void
    {
        $this->newLine();
        $this->line('<options=bold>Underwriting review login</>');
        $this->newLine();

        $this->line('  Sign in at   https://vaytoven.com/login');
        $this->line('  Email        '.$user->email);
        $this->line('  Password     '.($password ?? '(unchanged — pass --new-password to issue a fresh one)'));
        $this->newLine();

        $this->line('  Listing      '.$property->title);
        $this->line('  Public page  https://vaytoven.com/properties/'.$property->getRouteKey());
        $this->line('  Status       '.$property->status->value);
        $this->line('  Photos       '.$photos);
        $this->line('  Engagement   '.$events['views'].' views + '.$events['events']
            .' events across '.$events['cities'].' cities');
        $this->newLine();

        if ($blockers !== []) {
            $this->error('  The listing is NOT live. It still needs:');
            foreach ($blockers as $blocker) {
                $this->line('    - '.$blocker);
            }
            $this->newLine();
            $this->line('  The login works, but the advertisement is not public yet.');

            return;
        }

        $this->info('  The listing is live and the dashboard has a map with pins.');
        $this->newLine();
        $this->line('  The password is shown once and is not stored anywhere readable.');
        $this->line('  Re-run with --new-password to issue another.');
    }
}

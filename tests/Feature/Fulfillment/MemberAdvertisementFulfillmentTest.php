<?php

namespace Tests\Feature\Fulfillment;

use App\Enums\ActivityType;
use App\Enums\AvailabilityWeekStatus;
use App\Enums\PropertyStatus;
use App\Enums\UserRole;
use App\Models\AdvertisementFulfillmentRecord as Record;
use App\Models\Property;
use App\Models\PropertyAvailabilityWeek;
use App\Models\PropertyPhoto;
use App\Models\Role;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Services\Activity\ActivityLogQuery;
use App\Services\Fulfillment\AdvertisementAcknowledgement;
use App\Services\Fulfillment\AdvertisementFulfillment;
use App\Services\Fulfillment\FulfillmentCertificate;
use App\Services\GeoIp\GeoIpResult;
use App\Services\GeoIp\GeoIpService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

/**
 * Vaytoven activated the advertisement → the member accessed it → the member
 * accepted it. Three facts, three records, and the member's identity — never
 * the administrator's — on the last two.
 */
class MemberAdvertisementFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_IP  = '198.51.100.7';
    private const MEMBER_IP = '203.0.113.9';
    private const CHROME_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();

        // Geo resolves by IP so the test can tell whose location was stored.
        $this->app->instance(GeoIpService::class, new class implements GeoIpService {
            public function lookup(?string $ipAddress): GeoIpResult
            {
                return match ($ipAddress) {
                    '203.0.113.9'  => new GeoIpResult(country: 'US', region: 'Georgia', city: 'Atlanta', latitude: 33.749, longitude: -84.388),
                    '198.51.100.7' => new GeoIpResult(country: 'PA', region: 'Panama', city: 'Panama City'),
                    default        => GeoIpResult::empty(),
                };
            }
        });

        $this->seed(RbacSeeder::class);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create([
            'name' => 'Mike Admin', 'email' => 'mike.admin@vaytoven.test',
            'role' => UserRole::SuperAdmin, 'must_change_password' => false,
        ]);
        $user->roles()->sync([Role::where('key', 'super_admin')->firstOrFail()->id]);

        return $user;
    }

    private function member(): User
    {
        return User::factory()->create([
            'name'                 => 'John Smith',
            'email'                => 'john@example.com',
            'member_id'            => 'VAY-M-10582',
            'role'                 => UserRole::Member,
            'password'             => Hash::make('member-password-9'),
            'must_change_password' => false,
            'address_line1'        => '123 Peachtree St NE',
            'address_city'         => 'Atlanta',
            'address_state'        => 'GA',
            'address_postal_code'  => '30303',
            'address_country'      => 'US',
            'address_latitude'     => 33.7537,
            'address_longitude'    => -84.3863,
        ]);
    }

    private function asAdmin(User $admin): static
    {
        return $this->actingAs($admin)->withServerVariables(['REMOTE_ADDR' => self::ADMIN_IP])
            ->withHeader('User-Agent', self::CHROME_UA);
    }

    private function asMemberClient(): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => self::MEMBER_IP])
            ->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Version/17.0 Mobile/15E148 Safari/604.1');
    }

    /** Steps 1–3: admin creates and activates; that is ADMIN activity. */
    private function adminCreatesAndActivates(User $admin, User $member): Property
    {
        $this->asAdmin($admin)->post(route('admin.properties.store'), [
            'owner_mode'     => 'existing',
            'host_id'        => $member->id,
            'title'          => 'Ko Olina Beach Villa',
            'description'    => 'Two bedrooms by the lagoon.',
            'city'           => 'Kapolei',
            'capacity'       => 6, 'bedrooms' => 2, 'beds' => 3, 'bathrooms' => 2,
            'price_dollars'  => 250,
            'listing_type'   => 'rent',
            'status'         => 'draft',
            'listing_source' => 'managed',
            'notify_owner'   => 0,
        ])->assertRedirect();

        $property = Property::where('host_id', $member->id)->firstOrFail();

        PropertyPhoto::create(['property_id' => $property->id, 'url' => 'https://images.example.com/a.jpg', 'sort_order' => 1]);
        PropertyAvailabilityWeek::create([
            'property_id' => $property->id,
            'starts_on'   => now()->addMonth()->toDateString(),
            'ends_on'     => now()->addMonth()->addDays(7)->toDateString(),
            'status'      => AvailabilityWeekStatus::Available->value,
        ]);

        $this->asAdmin($admin)
            ->post(route('admin.properties.transition', $property), ['to' => 'active'])
            ->assertSessionHasNoErrors();

        $this->assertSame(PropertyStatus::Active, $property->refresh()->status);

        return $property;
    }

    private function tab(string $group): \Illuminate\Support\Collection
    {
        return collect(app(ActivityLogQuery::class)->paginate(['group' => $group], 500)->items());
    }

    public function test_the_full_activation_access_acceptance_flow_records_the_member_not_the_admin(): void
    {
        $admin  = $this->superAdmin();
        $member = $this->member();

        // 1–2. Admin creates and activates.
        $property = $this->adminCreatesAndActivates($admin, $member);
        $this->travel(5)->minutes();

        // 3. That is ADMIN activity, attributed to the admin, and not member activity.
        $created   = TrackingEvent::where('event_type', ActivityType::AdvertisementCreated->value)->sole();
        $activated = TrackingEvent::where('event_type', ActivityType::AdvertisementActivated->value)->sole();

        foreach ([$created, $activated] as $event) {
            $this->assertSame($admin->id, $event->actor_user_id);
            $this->assertSame('super_admin', $event->actor_role);
            $this->assertSame(self::ADMIN_IP, $event->ip_address);
        }

        $adminTab = $this->tab('admin')->pluck('id');
        $this->assertTrue($adminTab->contains($created->id));
        $this->assertTrue($adminTab->contains($activated->id));
        $this->assertFalse($this->tab('members')->pluck('id')->contains($activated->id));
        $this->assertTrue($this->tab('ads')->pluck('id')->contains($activated->id));

        // An admin opening the member's advertisement is NOT member access.
        $this->asAdmin($admin)->get(route('properties.show', $property))->assertOk();
        $this->asAdmin($admin)->get(route('member.advertisements.show', $property))->assertNotFound();
        $this->assertSame(0, Record::count(), 'Admin viewing was recorded as member access.');

        $this->post('/logout');
        $this->app['auth']->forgetGuards();

        // 4–5. Member logs in; the login carries the MEMBER's identity, IP and session.
        $this->asMemberClient()->post('/login', [
            'email' => 'john@example.com', 'password' => 'member-password-9',
        ])->assertRedirect();

        $login = TrackingEvent::where('event_type', ActivityType::LoginSucceeded->value)
            ->where('actor_user_id', $member->id)->sole();
        $this->assertSame('member', $login->actor_role);
        $this->assertSame(self::MEMBER_IP, $login->ip_address);
        $this->assertStringStartsWith('SES-', (string) $login->session_id);
        $this->assertSame('Member login', $login->activityLabel());
        $this->assertTrue($this->tab('logins')->pluck('id')->contains($login->id));

        // The login carries the member's address and its OWN comparison.
        $this->assertSame('Atlanta', $login->metadata['address_on_file']['city']);
        $this->assertSame('consistent', $login->metadata['location_comparison']['status']);
        $this->assertSame('VAY-M-10582', $login->metadata['member']['member_number']);
        $this->assertSame('mobile', $login->device_type);
        $this->assertSame('iOS', $login->platform);

        // Member First Login: its own event, separate from the login row.
        $firstLoginEvent = TrackingEvent::where('event_type', ActivityType::MemberFirstLogin->value)->sole();
        $this->assertSame($member->id, $firstLoginEvent->actor_user_id);
        $this->assertNotSame($login->id, $firstLoginEvent->id);

        // 17. The incentive comes first: trying to reach the ad lands on it.
        $this->travel(1)->minutes();
        $this->asMemberClient()->get(route('member.advertisements.show', $property))
            ->assertRedirect(route('member.incentive.show'));
        $this->assertSame(0, Record::where('event', Record::EVENT_FIRST_ACCESS)->count(), 'A redirect was recorded as advertisement access.');

        $this->asMemberClient()->get(route('member.incentive.show'))
            ->assertOk()->assertSee('in Dining Rewards')->assertSee('CONTINUE &amp; CLAIM MY REWARD', false);

        $presented = Record::where('event', Record::EVENT_INCENTIVE_PRESENTED)->sole();
        $this->assertSame($member->id, (int) $presented->user_id);
        $this->assertSame('$300 Dining Rewards', $presented->incentive_name);
        $this->assertSame('Creative Marketing Incentives', $presented->incentive_provider);
        $this->assertSame('Atlanta', $presented->address_city);
        $this->assertSame(self::MEMBER_IP, $presented->ip_address);
        $this->assertSame('consistent', $presented->location_comparison);
        $this->assertTrue($presented->metadata['on_first_login']);
        $this->assertTrue($presented->verifies());

        // Reloading does not create a second "presented".
        $this->asMemberClient()->get(route('member.incentive.show'))->assertOk();
        $this->assertSame(1, Record::where('event', Record::EVENT_INCENTIVE_PRESENTED)->count());

        $this->travel(1)->minutes();
        $this->asMemberClient()->post(route('member.incentive.acknowledge'), [
            'choice' => 'continue',
            // Forged fields are ignored: identity, time and status come from the server.
            'user_id' => $admin->id, 'ip_address' => '1.2.3.4', 'occurred_at' => '2020-01-01', 'status' => 'delivered',
        ])->assertRedirect(route('dashboard'));

        $acknowledged = Record::where('event', Record::EVENT_INCENTIVE_ACKNOWLEDGED)->sole();
        $this->assertSame($member->id, (int) $acknowledged->user_id);
        $this->assertSame(self::MEMBER_IP, $acknowledged->ip_address);
        $this->assertTrue($acknowledged->occurred_at->greaterThan($presented->occurred_at));
        $this->assertSame(0, Record::where('event', Record::EVENT_INCENTIVE_DELIVERED)->count(), 'Acknowledgement was treated as delivery.');
        // Neither incentive event is advertisement access or acceptance.
        $this->assertSame(0, Record::whereIn('event', [Record::EVENT_FIRST_ACCESS, Record::EVENT_ACCEPTED])->count());

        // 6–8. Member opens their advertisement.
        $this->travel(2)->minutes();
        $this->asMemberClient()->get(route('member.advertisements.show', $property))
            ->assertOk()
            ->assertSee(AdvertisementAcknowledgement::TEXT)
            ->assertSee('Accept &amp; Confirm Advertisement', false);

        $accessEvent = TrackingEvent::where('event_type', ActivityType::MemberAdvertisementFirstAccessed->value)->sole();
        $this->assertSame($member->id, $accessEvent->actor_user_id);
        $this->assertSame('member', $accessEvent->actor_role);
        $this->assertSame($login->session_id, $accessEvent->session_id);
        $this->assertSame('Member Advertisement Accessed', $accessEvent->activityLabel());

        $first = Record::where('event', Record::EVENT_FIRST_ACCESS)->sole();
        $this->assertSame($member->id, (int) $first->user_id);
        $this->assertSame('VAY-M-10582', $first->member_number);
        $this->assertSame('John Smith', $first->member_name);
        $this->assertSame('john@example.com', $first->member_email);
        $this->assertSame('member', $first->member_role);
        $this->assertSame($property->reference, $first->property_reference);
        $this->assertSame(route('properties.show', $property), $first->advertisement_url);
        $this->assertSame(self::MEMBER_IP, $first->ip_address);
        $this->assertSame('Atlanta', $first->city);
        $this->assertSame('mobile', $first->device_type);
        $this->assertSame('Safari', $first->browser);
        $this->assertSame('iOS', $first->platform);
        $this->assertSame($login->session_id, $first->session_id);
        $this->assertSame($accessEvent->id, (int) $first->tracking_event_id);
        $this->assertTrue($first->advertisement_activated_at->equalTo($activated->occurred_at));
        $this->assertTrue($first->first_login_at->equalTo($login->occurred_at));
        // A pointer to the login's own row — its IP/device are not copied here.
        $this->assertSame($login->id, (int) $first->first_login_event_id);
        $this->assertSame('Atlanta', $first->address_city);
        $this->assertSame('30303', $first->address_postal_code);
        $this->assertSame('consistent', $first->location_comparison);
        $this->assertNotNull($first->location_distance_miles);
        $this->assertTrue($first->verifies());

        // Returning later — including through the public page — never replaces it.
        $this->travel(1)->hours();
        $this->asMemberClient()->get(route('member.advertisements.show', $property))->assertOk();
        $this->asMemberClient()->get(route('properties.show', $property))
            ->assertOk()->assertSee(AdvertisementAcknowledgement::TEXT);

        $this->assertSame(1, Record::where('event', Record::EVENT_FIRST_ACCESS)->count());
        $this->assertSame($first->record_hash, Record::where('event', Record::EVENT_FIRST_ACCESS)->sole()->record_hash);
        // The review page and the public page are recorded under their own names.
        $this->assertSame(1, TrackingEvent::where('event_type', ActivityType::MemberAdvertisementReviewed->value)->count());
        $this->assertSame(1, TrackingEvent::where('event_type', ActivityType::MemberAdvertisementAccessed->value)->count());

        // 9–10. Member accepts: a separate record and a separate event.
        $this->asMemberClient()->post(route('member.advertisements.accept', $property), [
            'user_id' => $admin->id, 'property_id' => 999, 'ip_address' => '1.2.3.4',
            'occurred_at' => '2020-01-01 00:00:00', 'status' => 'accepted',
        ])->assertRedirect(route('member.advertisements.show', $property));

        $accepted = Record::where('event', Record::EVENT_ACCEPTED)->sole();
        $this->assertNotSame($first->id, $accepted->id);
        $this->assertSame($member->id, (int) $accepted->user_id);
        $this->assertSame(AdvertisementAcknowledgement::VERSION, $accepted->acknowledgement_version);
        $this->assertSame(AdvertisementAcknowledgement::TEXT, $accepted->acknowledgement_text);
        $this->assertSame(AdvertisementAcknowledgement::hash(), $accepted->acknowledgement_hash);
        $this->assertSame(self::MEMBER_IP, $accepted->ip_address);
        $this->assertSame($login->session_id, $accepted->session_id);
        $this->assertTrue($accepted->occurred_at->greaterThan($first->occurred_at));
        $this->assertSame($property->id, (int) $accepted->property_id);
        $this->assertTrue($accepted->occurred_at->isToday());
        $this->assertSame('consistent', $accepted->location_comparison);

        $acceptEvent = TrackingEvent::where('event_type', ActivityType::MemberAdvertisementAccepted->value)->sole();
        $this->assertSame($member->id, $acceptEvent->actor_user_id);
        $this->assertSame('Member Advertisement Accepted', $acceptEvent->activityLabel());

        // Pressing again changes nothing.
        $this->asMemberClient()->post(route('member.advertisements.accept', $property));
        $this->assertSame(1, Record::where('event', Record::EVENT_ACCEPTED)->count());

        // 12. Members tab: member events, member identity, no admin rows at all.
        $membersTab = $this->tab('members');
        $this->assertTrue($membersTab->pluck('id')->contains($accessEvent->id));
        $this->assertTrue($membersTab->pluck('id')->contains($acceptEvent->id));
        $this->assertTrue($membersTab->every(fn (TrackingEvent $e) => $e->actor_user_id === $member->id));

        // Ads tab carries both sides.
        $adsTab = $this->tab('ads')->pluck('id');
        foreach ([$created, $activated, $accessEvent, $acceptEvent] as $e) {
            $this->assertTrue($adsTab->contains($e->id), "Ads tab is missing {$e->event_type}");
        }

        // 13. Admin Activity still shows the admin's actions and none of the member's.
        $adminTab = $this->tab('admin');
        $this->assertTrue($adminTab->pluck('id')->contains($activated->id));
        $this->assertTrue($adminTab->every(fn (TrackingEvent $e) => $e->actor_user_id === $admin->id));

        // 11. The member's admin profile shows the fulfillment section.
        $this->post('/logout');
        $this->app['auth']->forgetGuards();

        $this->asAdmin($admin)->get(route('admin.members.show', $member))
            ->assertOk()
            ->assertSee('Fulfillment &amp; acceptance', false)
            ->assertSee('VAY-M-10582')
            ->assertSee('123 Peachtree St NE')
            ->assertSee('Atlanta, GA 30303')
            ->assertSee($property->reference)
            ->assertSee('Advertisement accessed &amp; accepted', false)
            ->assertSee('ACKNOWLEDGED')
            ->assertSee('No delivery evidence recorded')
            ->assertSee(self::MEMBER_IP)
            ->assertSee('Atlanta, Georgia, United States')
            ->assertSee('CONSISTENT WITH ADDRESS AREA')
            ->assertSee('iPhone')
            ->assertSee($login->session_id)
            ->assertSee('View full audit trail')
            ->assertSee('Download fulfillment certificate');

        // The timeline: every step its own entry, in the order it happened.
        $page = $this->asAdmin($admin)->get(route('admin.members.show', ['user' => $member, 'tab' => 'advertising']))
            ->assertOk()
            ->assertSee('Advertisement fulfillment timeline')
            ->assertSeeInOrder([
                'Advertisement created', 'Advertisement activated', 'Member login (first after advertisement activation)',
                'Member First Login', '$300 Dining Rewards presented', '$300 Dining Rewards acknowledged',
                'Advertisement first accessed', 'Member advertisement reviewed', 'Advertisement accepted',
            ], false)
            ->assertSee('Dining Rewards incentive');

        // Admin looking at the profile did not create evidence.
        $this->assertSame(4, Record::count());

        // 14. The certificate is built from — and matches — the stored records.
        $payload = app(FulfillmentCertificate::class)->payload($property->refresh());

        $this->assertSame('ACCEPTED', $payload['status']);
        $this->assertSame('John Smith', $payload['memberName']);
        $this->assertSame('VAY-M-10582', $payload['memberId']);
        $this->assertSame('john@example.com', $payload['memberEmail']);
        $this->assertSame($property->reference, $payload['advertisementId']);
        $this->assertSame($first->advertisement_url, $payload['advertisementUrl']);
        $this->assertSame(et($activated->occurred_at, 'm/d/Y g:i:s A'), $payload['activatedAt']);
        $this->assertSame(et($login->occurred_at, 'm/d/Y g:i:s A'), $payload['firstLoginAt']);
        $this->assertSame(et($first->occurred_at, 'm/d/Y g:i:s A'), $payload['firstAccessAt']);
        $this->assertSame(et($accepted->occurred_at, 'm/d/Y g:i:s A'), $payload['acceptedAt']);
        $this->assertSame(AdvertisementAcknowledgement::TEXT, $payload['acknowledgementText']);
        $this->assertSame(self::MEMBER_IP, $payload['ipAddress']);
        $this->assertSame('Atlanta, Georgia, United States', $payload['location']);
        $this->assertSame('CONSISTENT WITH ADDRESS AREA', $payload['comparison']);
        $this->assertSame("Approximate IP location is consistent with the member's address area.", $payload['comparisonSentence']);
        $this->assertSame('iOS', $payload['operatingSystem']);
        $this->assertSame('Safari', $payload['browser']);
        $this->assertSame(['123 Peachtree St NE', 'Atlanta, GA 30303', 'United States'], $payload['addressLines']);
        $this->assertSame("IP-based geolocation is approximate and does not establish the individual's precise physical location.", $payload['disclosure']);
        // Each event row carries its own record's context.
        $this->assertSame(['te:'.$login->id, 'fr:'.$first->id, 'fr:'.$accepted->id], $payload['events']->pluck('key')->all());
        $this->assertSame('Mobile', $payload['device']);
        $this->assertSame($accepted->session_id, $payload['sessionId']);
        $this->assertNotSame(self::ADMIN_IP, $payload['ipAddress']);
        $this->assertMatchesRegularExpression('/^VAY-FC-[0-9A-F]{12}$/', $payload['certificateNumber']);
        $this->assertSame($payload['certificateNumber'], app(FulfillmentCertificate::class)->payload($property)['certificateNumber']);

        $response = $this->asAdmin($admin)->get(route('admin.members.fulfillment-certificate', [$member, $property]));
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());

        // Downloading is itself audited, and still creates no evidence.
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'member.fulfillment_certificate.downloaded']);
        $this->assertSame(4, Record::count());
    }

    public function test_an_advertisement_nobody_opened_certifies_only_what_is_recorded(): void
    {
        $admin    = $this->superAdmin();
        $member   = $this->member();
        $property = $this->adminCreatesAndActivates($admin, $member);

        $payload = app(FulfillmentCertificate::class)->payload($property);

        $this->assertSame('NOT ACCESSED', $payload['status']);
        $this->assertSame(FulfillmentCertificate::NOT_RECORDED, $payload['firstLoginAt']);
        $this->assertSame(FulfillmentCertificate::NOT_RECORDED, $payload['firstAccessAt']);
        $this->assertSame(FulfillmentCertificate::NOT_RECORDED, $payload['acceptedAt']);
        $this->assertSame(FulfillmentCertificate::NOT_RECORDED, $payload['ipAddress']);
        // The admin's IP must not stand in for the member's.
        $this->assertNotSame(self::ADMIN_IP, $payload['ipAddress']);
        $this->assertNotSame(FulfillmentCertificate::NOT_RECORDED, $payload['activatedAt']);
    }

    public function test_acceptance_without_access_is_refused(): void
    {
        $member   = $this->member();
        $property = Property::factory()->create(['host_id' => $member->id]);

        $this->expectException(RuntimeException::class);

        app(AdvertisementFulfillment::class)->accept(request(), $property, $member);
    }

    public function test_someone_elses_advertisement_is_not_theirs_to_access_or_accept(): void
    {
        $owner    = $this->member();
        $stranger = User::factory()->create(['role' => UserRole::Member]);
        $property = Property::factory()->create(['host_id' => $owner->id]);

        $this->actingAs($stranger)->get(route('member.advertisements.show', $property))->assertNotFound();
        $this->actingAs($stranger)->post(route('member.advertisements.accept', $property))->assertNotFound();
        $this->actingAs($stranger)->get(route('properties.show', $property))->assertOk();

        $this->assertSame(0, Record::count());
    }

    /** A staff account that happens to own a listing is still staff. */
    public function test_staff_owning_a_listing_never_produce_member_evidence(): void
    {
        $admin    = $this->superAdmin();
        $property = Property::factory()->create(['host_id' => $admin->id]);

        $this->actingAs($admin)->get(route('properties.show', $property))->assertOk();
        $this->actingAs($admin)->get(route('member.advertisements.show', $property))->assertNotFound();

        $this->assertSame(0, Record::count());
        $this->assertSame(0, TrackingEvent::whereIn('event_type', ActivityType::memberOnly())->count());
    }

    public function test_evidence_cannot_be_edited_or_deleted_only_corrected(): void
    {
        $member   = $this->member();
        $property = Property::factory()->create(['host_id' => $member->id]);

        $this->actingAs($member)->get(route('member.advertisements.show', $property))->assertOk();
        $record = Record::sole();

        try {
            $record->update(['ip_address' => '1.1.1.1']);
            $this->fail('A fulfillment record was updated.');
        } catch (RuntimeException) {
        }

        try {
            $record->delete();
            $this->fail('A fulfillment record was deleted.');
        } catch (RuntimeException) {
        }

        $correction = app(AdvertisementFulfillment::class)
            ->correct($record, 'Member reports this was a shared office device.', $this->superAdmin());

        $this->assertSame(Record::EVENT_CORRECTION, $correction->event);
        $this->assertSame($record->id, (int) $correction->corrects_record_id);
        $this->assertSame($record->record_hash, Record::find($record->id)->record_hash);
        $this->assertTrue(Record::find($record->id)->verifies());
        $this->assertCount(1, app(AdvertisementFulfillment::class)->state($property)['corrections']);
    }

    /**
     * Rows written before actor_role existed: classified by the account's
     * role at read time. Historical admin activity is never shown as member
     * activity, and nothing stored is changed.
     */
    public function test_historical_admin_rows_without_a_recorded_role_leave_the_members_tab(): void
    {
        $admin  = $this->superAdmin();
        $member = $this->member();

        $legacyAdmin = TrackingEvent::create([
            'event_type' => ActivityType::AvailabilityChanged->value, 'actor_user_id' => $admin->id,
            'surface' => 'web', 'metadata' => [],
        ]);
        $legacyMember = TrackingEvent::create([
            'event_type' => ActivityType::AvailabilityChanged->value, 'actor_user_id' => $member->id,
            'surface' => 'web', 'metadata' => [],
        ]);

        $this->assertNull($legacyAdmin->actor_role);

        $this->assertSame([$legacyMember->id], $this->tab('members')->pluck('id')->all());
        $this->assertSame([$legacyAdmin->id], $this->tab('admin')->pluck('id')->all());
        $this->assertSame('Availability changed (by super admin)', $legacyAdmin->fresh()->activityLabel());
    }
}

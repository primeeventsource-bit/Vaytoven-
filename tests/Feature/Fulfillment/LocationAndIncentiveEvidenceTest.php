<?php

namespace Tests\Feature\Fulfillment;

use App\Enums\ActivityType;
use App\Enums\UserRole;
use App\Models\AdvertisementFulfillmentRecord as Record;
use App\Models\Contract;
use App\Models\Property;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Services\DocuSign\WebhookVerifier;
use App\Services\Fulfillment\MemberIncentive;
use App\Services\GeoIp\GeoIpResult;
use App\Services\GeoIp\GeoIpService;
use App\Services\Tracking\ActivityRecorder;
use App\Support\Location\AddressOnFile;
use App\Support\Location\LocationComparison as LC;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class LocationAndIncentiveEvidenceTest extends TestCase
{
    use RefreshDatabase;

    private function miami(bool $coords = true): AddressOnFile
    {
        return new AddressOnFile('123 Example Street', null, 'Miami', 'FL', '33101', 'US',
            $coords ? 25.7743 : null, $coords ? -80.1937 : null);
    }

    // --- 12. address vs. approximate IP location ----------------------------

    public function test_comparison_statuses(): void
    {
        // Same city by name, state written differently.
        $this->assertSame(LC::CONSISTENT, LC::compare($this->miami(false), 'Miami', 'Florida', 'United States')->status);

        // By distance: Miami Beach (~4 mi), Boca Raton (~41 mi), Orlando (~200 mi).
        $this->assertSame(LC::CONSISTENT, LC::compare($this->miami(), 'Miami Beach', 'Florida', 'US', 25.7907, -80.1300)->status);
        $this->assertSame(LC::NEARBY, LC::compare($this->miami(), 'Boca Raton', 'Florida', 'US', 26.3683, -80.1289)->status);
        $this->assertSame(LC::DIFFERENT, LC::compare($this->miami(), 'Orlando', 'Florida', 'US', 28.5383, -81.3792)->status);

        $this->assertSame(LC::DIFFERENT, LC::compare($this->miami(false), 'Panama City', 'Panama', 'PA')->status);
        $this->assertSame(LC::DIFFERENT, LC::compare($this->miami(false), 'Atlanta', 'Georgia', 'US')->status);

        // Refuses to guess without a distance.
        $this->assertSame(LC::UNAVAILABLE, LC::compare($this->miami(false), 'Miami Beach', 'Florida', 'US')->status);
        $this->assertSame(LC::UNAVAILABLE, LC::compare(new AddressOnFile(), 'Miami', 'Florida', 'US')->status);
        $this->assertSame(LC::UNAVAILABLE, LC::compare($this->miami(), null, null, 'US')->status);

        $this->assertSame('CONSISTENT WITH ADDRESS AREA', LC::labelFor(LC::CONSISTENT));
        $this->assertSame('SAME METRO / NEARBY AREA', LC::labelFor(LC::NEARBY));
        $this->assertSame('DIFFERENT AREA', LC::labelFor(LC::DIFFERENT));
        $this->assertSame('LOCATION UNAVAILABLE', LC::labelFor(LC::UNAVAILABLE));
    }

    /** The system never claims a physical location. */
    public function test_no_wording_claims_physical_presence(): void
    {
        foreach ([LC::CONSISTENT, LC::NEARBY, LC::DIFFERENT, LC::UNAVAILABLE] as $status) {
            $sentence = strtolower(LC::sentenceFor($status));
            $this->assertStringContainsString('approximate', $sentence);
            $this->assertStringNotContainsString('physically', $sentence);
            $this->assertStringNotContainsString('was located', $sentence);
            $this->assertStringNotContainsString('home address', $sentence);
        }
    }

    // --- 11. device / browser / OS -------------------------------------------

    public function test_device_browser_and_os_families(): void
    {
        $iphoneChrome = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 CriOS/126.0 Mobile/15E148 Safari/604.1';
        $this->assertSame('Chrome', ActivityRecorder::browser($iphoneChrome));
        $this->assertSame('iOS', ActivityRecorder::platform($iphoneChrome));
        $this->assertSame('mobile', ActivityRecorder::deviceType($iphoneChrome));
        $this->assertSame('iPhone', ActivityRecorder::deviceHint($iphoneChrome));

        $samsung = 'Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 SamsungBrowser/24.0 Chrome/117.0 Mobile Safari/537.36';
        $this->assertSame('Samsung Internet', ActivityRecorder::browser($samsung));
        $this->assertSame('Android', ActivityRecorder::platform($samsung));

        $this->assertSame('Other', ActivityRecorder::browser('Mozilla/5.0 (Windows NT 10.0) Chrome/120 Safari/537.36 OPR/106'));
        $this->assertSame('Firefox', ActivityRecorder::browser('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) FxiOS/125.0 Mobile Safari/605'));
        $this->assertSame('macOS', ActivityRecorder::platform('Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) Version/17 Safari/605'));
        $this->assertSame('Linux', ActivityRecorder::platform('Mozilla/5.0 (X11; Linux x86_64) Firefox/121'));
        $this->assertSame('Unknown', ActivityRecorder::deviceLabel(ActivityRecorder::deviceType(null)));
        $this->assertSame('Tablet', ActivityRecorder::deviceLabel('tablet'));
    }

    // --- 13. each event its own evidence ---------------------------------------

    public function test_address_changes_are_geocoded_and_recorded_as_account_changes(): void
    {
        config(['services.address_geocoder.driver' => 'census']);
        Http::fake(['geocoding.geo.census.gov/*' => Http::response([
            'result' => ['addressMatches' => [['coordinates' => ['x' => -80.1937, 'y' => 25.7743]]]],
        ])]);

        $member = User::factory()->create(['role' => UserRole::Member]);

        $this->actingAs($member)->patch(route('profile.update'), [
            'name' => $member->name, 'email' => $member->email,
            'address_line1' => '123 Example Street', 'address_city' => 'Miami',
            'address_state' => 'fl', 'address_postal_code' => '33101', 'address_country' => 'us',
        ])->assertSessionHasNoErrors();

        $member->refresh();
        $this->assertSame('FL', $member->address_state);
        $this->assertSame('US', $member->address_country);
        $this->assertEqualsWithDelta(25.7743, (float) $member->address_latitude, 0.0001);

        $change = TrackingEvent::where('event_type', ActivityType::ProfileUpdated->value)
            ->where('actor_user_id', $member->id)->get()
            ->first(fn ($e) => ($e->metadata['change'] ?? null) === 'address_on_file');
        $this->assertNotNull($change);
        $this->assertContains('address_line1', $change->metadata['changed']);
        $this->assertSame('member', $change->actor_role);
    }

    public function test_a_docusign_signature_uses_the_signers_ip_never_the_webhooks(): void
    {
        $this->app->instance(WebhookVerifier::class, new class extends WebhookVerifier {
            public function __construct() {}
            public function verify(string $raw, array $headers): bool { return true; }
        });

        $member = User::factory()->create(['role' => UserRole::Member]);
        $contract = Contract::forceCreate([
            'user_id' => $member->id, 'client_name' => $member->name, 'client_email' => $member->email,
            'contract_type' => 'member_agreement', 'title' => 'Member agreement', 'envelope_id' => 'env-123',
            'status' => Contract::STATUS_SENT,
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '162.248.184.1'])
            ->postJson('/webhooks/docusign', [
                'event' => 'recipient-signed',
                'data'  => ['envelopeId' => 'env-123', 'envelopeSummary' => ['recipients' => ['signers' => [[
                    'email' => $member->email, 'ipAddress' => '203.0.113.77',
                    'userAgent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/126 Safari/537.36',
                ]]]]],
            ])->assertOk();

        $signed = TrackingEvent::where('event_type', ActivityType::ContractSigned->value)->sole();
        $this->assertSame($member->id, $signed->actor_user_id);
        $this->assertSame('203.0.113.77', $signed->ip_address);
        $this->assertNull($signed->session_id);
        $this->assertSame('DocuSign', $signed->metadata['observed_by']);
        $this->assertSame((string) $contract->id, $signed->subject_reference);
    }

    // --- 17–21. incentive ----------------------------------------------------

    private function loginAs(User $user, string $password = 'secret-pass-9'): void
    {
        $this->post('/login', ['email' => $user->email, 'password' => $password])->assertRedirect();
    }

    public function test_staff_signing_in_never_see_or_generate_the_incentive(): void
    {
        $this->seed(RbacSeeder::class);
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin, 'password' => Hash::make('secret-pass-9'), 'must_change_password' => false]);
        Property::factory()->create(['host_id' => $admin->id]);

        $this->loginAs($admin);
        $this->get(route('dashboard'))->assertOk();
        $this->get(route('member.incentive.show'))->assertRedirect(route('dashboard'));

        $this->assertSame(0, Record::count());
        $this->assertSame(0, TrackingEvent::whereIn('event_type', ActivityType::memberOnly())->count());
    }

    public function test_a_traveler_is_not_a_program_client(): void
    {
        $traveler = User::factory()->create(['role' => UserRole::Traveler, 'password' => Hash::make('secret-pass-9')]);

        $this->loginAs($traveler);
        $this->get(route('dashboard'))->assertOk();

        $this->assertSame(0, Record::count());
    }

    /**
     * A client who signed in before this shipped still gets the incentive —
     * but no "Member First Login" is invented for a login that was not first.
     */
    public function test_an_existing_client_is_presented_without_a_false_first_login(): void
    {
        $member = User::factory()->create([
            'role' => UserRole::Member, 'password' => Hash::make('secret-pass-9'), 'last_login_at' => now()->subMonth(),
        ]);

        $this->loginAs($member);
        $this->get(route('dashboard'))->assertRedirect(route('member.incentive.show'));
        $this->get(route('member.incentive.show'))->assertOk();

        $this->assertSame(0, TrackingEvent::where('event_type', ActivityType::MemberFirstLogin->value)->count());
        $this->assertFalse(Record::where('event', Record::EVENT_INCENTIVE_PRESENTED)->sole()->metadata['on_first_login']);

        // "View my dining reward" acknowledges, then shows the reward page.
        $this->post(route('member.incentive.acknowledge'), ['choice' => 'view'])
            ->assertRedirect(route('member.incentive.reward'));
        $this->get(route('member.incentive.reward'))->assertOk()->assertSee('Being issued');
        $this->assertSame('VIEW MY DINING REWARD', Record::where('event', Record::EVENT_INCENTIVE_ACKNOWLEDGED)->sole()->metadata['button']);

        // The dashboard is reachable now; nothing else is prompted.
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_acknowledging_without_presentation_is_refused(): void
    {
        $member = User::factory()->create(['role' => UserRole::Member]);

        $this->expectException(RuntimeException::class);
        app(MemberIncentive::class)->acknowledge(Request::create('/'), $member, 'continue');
    }

    public function test_delivered_needs_staff_and_a_reference_and_is_only_status_with_evidence(): void
    {
        $this->seed(RbacSeeder::class);
        $staff = User::factory()->create(['role' => UserRole::SuperAdmin, 'must_change_password' => false]);
        $staff->roles()->sync([\App\Models\Role::where('key', 'super_admin')->firstOrFail()->id]);
        $member = User::factory()->create(['role' => UserRole::Member]);
        $incentive = app(MemberIncentive::class);

        $this->assertSame(MemberIncentive::STATUS_NOT_PRESENTED, $incentive->state($member)['status']);

        try {
            $incentive->recordDelivery($member, $member, 'email', 'CMI-1', null, Request::create('/'));
            $this->fail('A member recorded their own delivery.');
        } catch (RuntimeException) {
        }

        $this->actingAs($staff)->post(route('admin.members.incentive-delivery', $member), [
            'delivery_method' => 'email', 'delivery_reference' => '',
        ])->assertSessionHasErrors('delivery_reference');
        $this->assertSame(0, Record::count());

        $this->actingAs($staff)->post(route('admin.members.incentive-delivery', $member), [
            'delivery_method' => 'email', 'delivery_reference' => 'CMI-CERT-55120',
        ])->assertRedirect();

        $delivered = Record::where('event', Record::EVENT_INCENTIVE_DELIVERED)->sole();
        $this->assertSame($staff->id, (int) $delivered->recorded_by_user_id);
        $this->assertSame('CMI-CERT-55120', $delivered->delivery_reference);
        $this->assertNull($delivered->location_comparison, 'Staff request context was compared with the member address.');
        $this->assertSame(MemberIncentive::STATUS_DELIVERED, $incentive->state($member)['status']);

        $event = TrackingEvent::where('event_type', ActivityType::MemberIncentiveDelivered->value)->sole();
        $this->assertSame($staff->id, $event->actor_user_id);
        $this->assertSame('super_admin', $event->actor_role);
    }
}

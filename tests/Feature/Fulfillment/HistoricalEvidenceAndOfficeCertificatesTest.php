<?php

namespace Tests\Feature\Fulfillment;

use App\Enums\ActivityType;
use App\Enums\PropertyStatus;
use App\Enums\UserRole;
use App\Mail\ClientCertificatesForOffice;
use App\Models\AdvertisementFulfillmentRecord as Record;
use App\Models\Property;
use App\Models\TrackingEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class HistoricalEvidenceAndOfficeCertificatesTest extends TestCase
{
    use RefreshDatabase;

    private function event(ActivityType $type, ?User $actor, Property $property, string $at, array $extra = []): TrackingEvent
    {
        return TrackingEvent::create([
            'event_type'        => $type->value,
            'actor_user_id'     => $actor?->id,
            'surface'           => 'web',
            'subject_type'      => 'property',
            'subject_reference' => $property->reference,
            'metadata'          => [],
            'occurred_at'       => $at,
        ] + $extra);
    }

    // --- backfill -----------------------------------------------------------

    public function test_backfill_uses_only_the_owning_members_own_views_after_activation(): void
    {
        $admin    = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $member   = User::factory()->create(['role' => UserRole::Member]);
        $property = Property::factory()->create(['host_id' => $member->id]);

        $this->event(ActivityType::PropertyViewed, $member, $property, '2026-08-01 09:00:00');  // before activation
        $this->event(ActivityType::AdvertisementActivated, $admin, $property, '2026-08-02 10:00:00');
        $this->event(ActivityType::PropertyViewed, $admin, $property, '2026-08-02 10:05:00');   // admin — never evidence
        $this->event(ActivityType::PropertyViewed, null, $property, '2026-08-02 11:00:00');     // anonymous
        $evidence = $this->event(ActivityType::PropertyViewed, $member, $property, '2026-08-03 14:30:00', [
            'ip_address' => '203.0.113.50', 'city' => 'Orlando', 'session_id' => 'SES-HIST01', 'device_type' => 'desktop',
        ]);

        $this->artisan('vaytoven:backfill-advertisement-access')->assertSuccessful();
        $this->assertSame(0, Record::count(), 'A dry run wrote records.');

        $this->artisan('vaytoven:backfill-advertisement-access', ['--commit' => true])->assertSuccessful();

        $record = Record::sole();
        $this->assertSame(Record::EVENT_FIRST_ACCESS, $record->event);
        $this->assertSame(Record::SOURCE_BACKFILL, $record->source);
        $this->assertSame($evidence->id, (int) $record->source_tracking_event_id);
        $this->assertSame($member->id, (int) $record->user_id);
        $this->assertSame('2026-08-03 14:30:00', $record->occurred_at->format('Y-m-d H:i:s'));
        $this->assertSame('203.0.113.50', $record->ip_address);
        $this->assertSame('SES-HIST01', $record->session_id);
        $this->assertTrue($record->verifies());
        $this->assertSame(0, Record::where('event', Record::EVENT_ACCEPTED)->count());

        // Idempotent.
        $this->artisan('vaytoven:backfill-advertisement-access', ['--commit' => true])->assertSuccessful();
        $this->assertSame(1, Record::count());
    }

    public function test_backfill_writes_nothing_when_only_admin_activity_exists(): void
    {
        $admin    = User::factory()->create(['role' => UserRole::Admin]);
        $member   = User::factory()->create(['role' => UserRole::Member]);
        $property = Property::factory()->create(['host_id' => $member->id]);

        $this->event(ActivityType::AdvertisementActivated, $admin, $property, '2026-08-02 10:00:00');
        $this->event(ActivityType::AvailabilityChanged, $admin, $property, '2026-08-02 10:10:00');
        $this->event(ActivityType::PropertyViewed, $admin, $property, '2026-08-02 10:20:00');

        $this->artisan('vaytoven:backfill-advertisement-access', ['--commit' => true])->assertSuccessful();

        $this->assertSame(0, Record::count());
    }

    /** Vaytoven's 2026-09-16 decision: a member login after activation is recorded as access, and says so. */
    public function test_from_logins_records_access_from_the_first_member_login_after_activation(): void
    {
        $admin    = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $member   = User::factory()->create(['role' => UserRole::Member]);
        $property = Property::factory()->create(['host_id' => $member->id]);
        $noLogin  = Property::factory()->create(['host_id' => User::factory()->create(['role' => UserRole::Member])->id]);
        $unactivated = Property::factory()->create(['host_id' => User::factory()->create(['role' => UserRole::Member])->id]);

        $loginEvent = fn (User $u, string $at, array $extra = []) => TrackingEvent::create([
            'event_type' => ActivityType::LoginSucceeded->value, 'actor_user_id' => $u->id,
            'surface' => 'web', 'metadata' => [], 'occurred_at' => $at,
        ] + $extra);

        $loginEvent($member, '2026-08-01 09:00:00');                       // before activation
        $this->event(ActivityType::AdvertisementActivated, $admin, $property, '2026-08-02 10:00:00');
        $this->event(ActivityType::AdvertisementActivated, $admin, $noLogin, '2026-08-02 10:00:00');
        $loginEvent($admin, '2026-08-02 10:30:00');                        // staff — never
        $first = $loginEvent($member, '2026-08-05 14:00:00', [
            'ip_address' => '203.0.113.90', 'city' => 'Tampa', 'region' => 'Florida', 'country' => 'US',
            'device_type' => 'mobile', 'browser' => 'Safari', 'platform' => 'iOS', 'session_id' => 'SES-LOGIN1',
        ]);
        $loginEvent($member, '2026-08-09 14:00:00');                       // later — not first
        $loginEvent($unactivated->host, '2026-08-05 14:00:00');            // no recorded activation

        $this->artisan('vaytoven:backfill-advertisement-access', ['--from-logins' => true])->assertSuccessful();
        $this->assertSame(0, Record::count(), 'A dry run wrote records.');

        $this->artisan('vaytoven:backfill-advertisement-access', ['--from-logins' => true, '--commit' => true])->assertSuccessful();

        $record = Record::sole();
        $this->assertSame($property->id, (int) $record->property_id);
        $this->assertSame(Record::EVENT_FIRST_ACCESS, $record->event);
        $this->assertSame(Record::SOURCE_BACKFILL_LOGIN, $record->source);
        $this->assertSame($first->id, (int) $record->source_tracking_event_id);
        $this->assertSame('2026-08-05 14:00:00', $record->occurred_at->format('Y-m-d H:i:s'));
        $this->assertSame('203.0.113.90', $record->ip_address);
        $this->assertSame('SES-LOGIN1', $record->session_id);
        $this->assertSame('iOS', $record->platform);
        $this->assertStringContainsString('first member login after advertisement activation', strtolower($record->metadata['basis']));
        $this->assertTrue($record->verifies());
        $this->assertSame(0, Record::where('event', Record::EVENT_ACCEPTED)->count());

        $state = app(\App\Services\Fulfillment\AdvertisementFulfillment::class)->state($property);
        $this->assertSame('accessed', $state['status']);
        $this->assertSame(
            "Access recorded from the member's first login after activation",
            \App\Services\Fulfillment\EvidencePoint::fromRecord($record, $member)->note,
        );

        // Idempotent, and never overrides an existing access record.
        $this->artisan('vaytoven:backfill-advertisement-access', ['--from-logins' => true, '--commit' => true])->assertSuccessful();
        $this->assertSame(1, Record::count());
    }

    // --- office certificates -------------------------------------------------

    public function test_certificates_go_to_the_office_only_for_real_clients_once(): void
    {
        Mail::fake();
        config(['mail.office_address' => 'contact@vaytoven.com']);

        $staff = User::factory()->create(['role' => UserRole::SuperAdmin, 'email' => 'eric@vaytoven.com']);

        $client = User::factory()->create(['role' => UserRole::Member, 'email' => 'client@gmail.com', 'name' => 'Real Client']);
        Property::factory()->count(2)->create(['host_id' => $client->id]);

        $host = User::factory()->create(['role' => UserRole::Host, 'email' => 'owner@yahoo.com']);
        Property::factory()->create(['host_id' => $host->id]);

        // Not clients: no live ad, demo, test domain, staff-owned.
        $noLiveAd = User::factory()->create(['role' => UserRole::Member, 'email' => 'paused@gmail.com']);
        Property::factory()->create(['host_id' => $noLiveAd->id, 'status' => PropertyStatus::Paused->value]);
        Property::factory()->create(['host_id' => User::factory()->create(['role' => UserRole::Member, 'email' => 'x@demo.vaytoven.local'])->id]);
        Property::factory()->create(['host_id' => User::factory()->create(['role' => UserRole::Member, 'email' => 'e2e+1@vaytoven.test'])->id]);
        Property::factory()->create(['host_id' => $staff->id]);
        Property::factory()->create(['host_id' => User::factory()->create(['role' => UserRole::Member, 'email' => 'underwriting.review@vaytoven.com'])->id]);

        // Dry run: nothing sent.
        $this->artisan('vaytoven:send-client-certificates')->assertSuccessful();
        Mail::assertNothingSent();

        // Refuses without a staff actor.
        $this->artisan('vaytoven:send-client-certificates', ['--send' => true])->assertFailed();
        Mail::assertNothingSent();

        $this->artisan('vaytoven:send-client-certificates', ['--send' => true, '--actor' => 'eric@vaytoven.com'])
            ->assertSuccessful();

        Mail::assertSent(ClientCertificatesForOffice::class, 2);

        Mail::assertSent(ClientCertificatesForOffice::class, function (ClientCertificatesForOffice $mail) {
            $envelope = $mail->envelope();
            $to = collect($envelope->to)->map(fn ($a) => is_string($a) ? $a : $a->address)->all();

            return $to === ['contact@vaytoven.com']
                && $envelope->cc === [] && $envelope->bcc === []
                && ! in_array($mail->client->email, $to, true);
        });

        $clientMail = Mail::sent(ClientCertificatesForOffice::class)->first(fn ($m) => $m->client->is($client));
        $this->assertCount(3, $clientMail->attachments(), 'Expected the usage certificate plus one fulfillment record per ad.');
        $this->assertSame('NOT ACCESSED', $clientMail->summaries[0]['status']);

        // A second run skips clients already sent.
        $this->artisan('vaytoven:send-client-certificates', ['--send' => true, '--actor' => 'eric@vaytoven.com'])
            ->assertSuccessful();
        Mail::assertSent(ClientCertificatesForOffice::class, 2);
    }
    /** Every client's certificates can be downloaded from the register, no email needed. */
    public function test_the_certificates_register_offers_both_downloads_for_every_client(): void
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $staff = User::factory()->create(["role" => UserRole::SuperAdmin, "must_change_password" => false]);
        $staff->roles()->sync([\App\Models\Role::where("key", "super_admin")->firstOrFail()->id]);

        $client = User::factory()->create(["role" => UserRole::Member, "created_at" => "2026-08-01 10:00:00"]);
        $property = Property::factory()->create(["host_id" => $client->id]);

        $page = $this->actingAs($staff)->get(route("admin.fulfillment.index", ["view" => "certificates"]))->assertOk();

        $usage = route("admin.users.certificate", ["user" => $client, "from" => "2026-08-01", "to" => now()->toDateString()]);
        $fulfillment = route("admin.members.fulfillment-certificate", [$client, $property]);
        $page->assertSee(e($usage), false)->assertSee(e($fulfillment), false);

        $this->actingAs($staff)->get($usage)->assertOk()->assertHeader("Content-Type", "application/pdf");
        $this->actingAs($staff)->get($fulfillment)->assertOk()->assertHeader("Content-Type", "application/pdf");
    }
}

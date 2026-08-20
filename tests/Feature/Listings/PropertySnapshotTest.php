<?php

namespace Tests\Feature\Listings;

use App\Enums\MemberServiceOrderStatus;
use App\Enums\MemberServicePackage;
use App\Models\Property;
use App\Models\PropertySnapshot;
use App\Models\User;
use App\Services\Chargeback\EvidenceBundleService;
use App\Services\MemberServices\AdvertisingActivator;
use App\Services\MemberServices\MemberServiceOrderFactory;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Point-in-time copies of what a listing actually said.
 *
 * A listing is edited continuously. Six months later a member disputes the
 * charge and the only thing the system can otherwise show is the CURRENT
 * version, which may share nothing with what ran during the period they paid
 * for. "Here is the ad we published for you" is only evidence if it is the ad
 * that was published then.
 */
class PropertySnapshotTest extends TestCase
{
    use RefreshDatabase;

    private function paidOrder(string $email = 'ada@example.com'): \App\Models\MemberServiceOrder
    {
        $order = app(MemberServiceOrderFactory::class)->create(
            package: MemberServicePackage::Gold, weeks: 4,
            member: ['first_name' => 'Ada', 'last_name' => 'Vale', 'email' => $email, 'phone' => null],
        );

        $order->forceFill([
            'status' => MemberServiceOrderStatus::Paid,
            'paid_at' => now(),
            'nmi_transaction_id' => '424242',
        ])->save();

        return $order->refresh();
    }

    // --- capture ------------------------------------------------------------

    public function test_activating_advertising_freezes_the_listing(): void
    {
        $property = Property::factory()->create(['title' => 'Original Title']);

        app(AdvertisingActivator::class)->activate(
            $this->paidOrder(), collect([$property]), User::factory()->create(),
        );

        $snapshot = PropertySnapshot::sole();

        $this->assertSame(PropertySnapshot::REASON_ACTIVATED, $snapshot->reason);
        $this->assertSame('Original Title', $snapshot->content['title']);
        $this->assertSame($property->id, $snapshot->property_id);
    }

    /**
     * The point of the whole feature: the snapshot must keep saying what the
     * ad said, no matter what the live listing becomes afterwards.
     */
    public function test_the_snapshot_survives_the_listing_being_rewritten(): void
    {
        $property = Property::factory()->create([
            'title' => 'Seafront Villa', 'price_cents' => 40000,
        ]);

        app(AdvertisingActivator::class)->activate(
            $this->paidOrder(), collect([$property]), User::factory()->create(),
        );

        $property->update(['title' => 'Completely Different', 'price_cents' => 9900]);

        $activation = PropertySnapshot::where('reason', PropertySnapshot::REASON_ACTIVATED)->sole();

        $this->assertSame('Seafront Villa', $activation->content['title']);
        $this->assertSame(40000, $activation->content['price_cents']);
        $this->assertSame('Completely Different', $property->refresh()->title);
    }

    public function test_a_material_edit_captures_a_new_snapshot(): void
    {
        $property = Property::factory()->create(['title' => 'First']);

        $property->update(['title' => 'Second']);

        $this->assertSame(1, PropertySnapshot::count());
        $this->assertSame(PropertySnapshot::REASON_EDITED, PropertySnapshot::sole()->reason);
    }

    /**
     * Not every save. A snapshot per touch would bury the two or three that
     * matter under hundreds recording that somebody fixed a typo in a field
     * nobody advertises.
     */
    public function test_a_non_material_change_captures_nothing(): void
    {
        $property = Property::factory()->create();

        $property->update(['payout_account_id' => 99]);

        $this->assertSame(0, PropertySnapshot::count());
    }

    public function test_saving_without_changes_captures_nothing(): void
    {
        $property = Property::factory()->create(['title' => 'Unchanged']);

        $property->update(['title' => 'Unchanged']);

        $this->assertSame(0, PropertySnapshot::count());
    }

    // --- integrity -----------------------------------------------------------

    public function test_a_fresh_snapshot_verifies_against_its_own_hash(): void
    {
        $property = Property::factory()->create();
        $property->update(['title' => 'Changed']);

        $this->assertTrue(PropertySnapshot::sole()->isIntact());
    }

    /**
     * A snapshot that was altered afterwards must be detectable. One presented
     * as evidence when it has been edited is worse than having none.
     */
    public function test_an_altered_snapshot_fails_its_integrity_check(): void
    {
        $property = Property::factory()->create();
        $property->update(['title' => 'Changed']);

        $snapshot = PropertySnapshot::sole();

        // Tamper with the stored content, leaving the recorded hash alone.
        $content = $snapshot->content;
        $content['price_cents'] = 1;
        $snapshot->forceFill(['content' => $content])->save();

        $this->assertFalse($snapshot->refresh()->isIntact());
    }

    /** The hash must not depend on the order keys happen to be built in. */
    public function test_the_hash_is_stable_across_key_order(): void
    {
        $a = ['title' => 'X', 'city' => 'Y', 'capacity' => 2];
        $b = ['capacity' => 2, 'city' => 'Y', 'title' => 'X'];

        $this->assertSame(
            PropertySnapshot::hashContent($a),
            PropertySnapshot::hashContent($b),
        );
    }

    // --- evidence -------------------------------------------------------------

    public function test_snapshots_reach_the_evidence_bundle(): void
    {
        $user     = User::factory()->create(['email' => 'ada@example.com']);
        $property = Property::factory()->create(['title' => 'Evidence Cottage']);

        app(AdvertisingActivator::class)->activate(
            $this->paidOrder('ada@example.com'), collect([$property]), User::factory()->create(),
        );

        $bundle = app(EvidenceBundleService::class)->generateForUser(
            $user->id, CarbonImmutable::now()->subDays(30), CarbonImmutable::now(),
        )->toArray();

        $this->assertCount(1, $bundle['ad_snapshots']);
        $this->assertSame('Evidence Cottage', $bundle['ad_snapshots'][0]['content']['title']);
        $this->assertTrue($bundle['ad_snapshots'][0]['intact']);
    }

    public function test_the_certificate_renders_the_published_advertisement(): void
    {
        $user     = User::factory()->create(['email' => 'ada@example.com']);
        $property = Property::factory()->create(['title' => 'Certificate Cottage']);

        app(AdvertisingActivator::class)->activate(
            $this->paidOrder('ada@example.com'), collect([$property]), User::factory()->create(),
        );

        $pdf = app(\App\Services\Chargeback\ChargebackCertificateService::class)->forUser(
            $user, CarbonImmutable::now()->subDays(30), CarbonImmutable::now(),
        );

        $this->assertStringStartsWith('%PDF', $pdf);
    }
}

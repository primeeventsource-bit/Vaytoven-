<?php

namespace Tests\Feature\Offers;

use App\Enums\MemberOfferStatus;
use App\Enums\OfferDirection;
use App\Enums\OfferKind;
use App\Enums\PropertyStatus;
use App\Enums\UserRole;
use App\Models\MemberOffer;
use App\Models\Property;
use App\Models\User;
use App\Services\Offers\OfferService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BuyerOfferTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $buyer;

    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['role' => UserRole::Host]);
        $this->buyer = User::factory()->create(['role' => UserRole::Traveler]);
        $this->property = Property::factory()->create([
            'host_id' => $this->owner->id,
            'status' => PropertyStatus::Active->value,
        ]);
    }

    private function submit(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->buyer)->post(
            route('offers.store', $this->property),
            array_merge([
                'kind' => 'offer',
                'amount_dollars' => '2500',
                'message' => 'Interested in the first week of September.',
            ], $overrides),
        );
    }

    // --- Capture -----------------------------------------------------------

    public function test_submission_records_every_required_field(): void
    {
        $this->submit()->assertRedirect();

        $offer = MemberOffer::query()->sole();

        $this->assertSame(OfferDirection::FromBuyer, $offer->direction);
        $this->assertSame(OfferKind::Offer, $offer->kind);
        $this->assertSame($this->property->id, $offer->property_id);
        $this->assertSame($this->buyer->id, $offer->buyer_user_id);
        // The listing owner is who must respond.
        $this->assertSame($this->owner->id, $offer->member_user_id);
        $this->assertSame(250000, $offer->offer_amount_cents);
        $this->assertSame('Interested in the first week of September.', $offer->buyer_message);
        $this->assertNotNull($offer->submitted_ip);
        $this->assertNotNull($offer->sent_at);
        $this->assertSame(MemberOfferStatus::Active, $offer->status);
    }

    public function test_expiry_is_exactly_24_hours_after_submission(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 19:30:00'));

        $this->submit();
        $offer = MemberOffer::query()->sole();

        $this->assertSame('2026-08-11 19:30:00', $offer->expires_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    public function test_an_inquiry_carries_no_amount(): void
    {
        $this->submit(['kind' => 'inquiry', 'amount_dollars' => null])->assertRedirect();

        $offer = MemberOffer::query()->sole();

        $this->assertSame(OfferKind::Inquiry, $offer->kind);
        $this->assertNull($offer->offer_amount_cents);
    }

    public function test_an_offer_requires_an_amount(): void
    {
        $this->submit(['amount_dollars' => null])->assertSessionHasErrors('amount_dollars');

        $this->assertSame(0, MemberOffer::query()->count());
    }

    public function test_an_owner_cannot_bid_on_their_own_listing(): void
    {
        $this->actingAs($this->owner)
            ->post(route('offers.store', $this->property), ['kind' => 'offer', 'amount_dollars' => '100'])
            ->assertRedirect();

        $this->assertSame(0, MemberOffer::query()->count());
    }

    public function test_submission_is_written_to_the_audit_log(): void
    {
        $this->submit();

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'offer.submit',
            'actor_user_id' => $this->buyer->id,
        ]);
    }

    // --- Owner dashboard ---------------------------------------------------

    public function test_owner_sees_offers_on_their_listings(): void
    {
        $this->submit();

        $this->actingAs($this->owner)
            ->get(route('offers.index'))
            ->assertOk()
            ->assertSee($this->buyer->name)
            ->assertSee($this->property->title)
            ->assertSee('$2,500.00')
            ->assertSee('ACTIVE');
    }

    public function test_owner_cannot_see_offers_on_other_peoples_listings(): void
    {
        $this->submit();

        $otherOwner = User::factory()->create(['role' => UserRole::Host]);

        $this->actingAs($otherOwner)
            ->get(route('offers.index'))
            ->assertOk()
            ->assertDontSee($this->property->title);
    }

    // --- Responding --------------------------------------------------------

    public function test_owner_can_accept(): void
    {
        $this->submit();
        $offer = MemberOffer::query()->sole();

        $this->actingAs($this->owner)
            ->post(route('offers.accept', $offer))
            ->assertRedirect();

        $this->assertSame(MemberOfferStatus::Accepted, $offer->fresh()->status);
        $this->assertNotNull($offer->fresh()->responded_at);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'offer.accepted']);
    }

    public function test_owner_can_decline(): void
    {
        $this->submit();
        $offer = MemberOffer::query()->sole();

        $this->actingAs($this->owner)->post(route('offers.decline', $offer))->assertRedirect();

        $this->assertSame(MemberOfferStatus::Declined, $offer->fresh()->status);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'offer.declined']);
    }

    public function test_a_stranger_cannot_respond(): void
    {
        $this->submit();
        $offer = MemberOffer::query()->sole();

        $this->actingAs(User::factory()->create())
            ->post(route('offers.accept', $offer))
            ->assertForbidden();

        $this->assertSame(MemberOfferStatus::Active, $offer->fresh()->status);
    }

    public function test_an_expired_offer_cannot_be_accepted_even_before_the_sweep_runs(): void
    {
        $this->submit();
        $offer = MemberOffer::query()->sole();

        // Past the deadline, but the scheduled sweep has not run — status is
        // still ACTIVE in the database.
        Carbon::setTestNow(now()->addHours(25));

        $this->actingAs($this->owner)
            ->post(route('offers.accept', $offer))
            ->assertStatus(422);

        $this->assertSame(MemberOfferStatus::Active, $offer->fresh()->status);

        Carbon::setTestNow();
    }

    // --- Expiry ------------------------------------------------------------

    public function test_the_sweep_expires_offers_past_24_hours_and_keeps_the_record(): void
    {
        $this->submit();
        $offer = MemberOffer::query()->sole();

        Carbon::setTestNow(now()->addHours(24)->addMinute());

        $expired = app(OfferService::class)->expireOverdue();

        $this->assertSame(1, $expired);

        $offer->refresh();
        $this->assertSame(MemberOfferStatus::Expired, $offer->status);
        // Everything captured at submission survives expiry.
        $this->assertSame(250000, $offer->offer_amount_cents);
        $this->assertSame('Interested in the first week of September.', $offer->buyer_message);
        $this->assertNotNull($offer->submitted_ip);
        $this->assertNotNull($offer->sent_at);

        Carbon::setTestNow();
    }

    public function test_the_sweep_leaves_offers_inside_the_window_alone(): void
    {
        $this->submit();

        Carbon::setTestNow(now()->addHours(23));

        $this->assertSame(0, app(OfferService::class)->expireOverdue());
        $this->assertSame(MemberOfferStatus::Active, MemberOffer::query()->sole()->status);

        Carbon::setTestNow();
    }

    public function test_the_sweep_does_not_touch_answered_offers(): void
    {
        $this->submit();
        $offer = MemberOffer::query()->sole();
        $this->actingAs($this->owner)->post(route('offers.accept', $offer));

        Carbon::setTestNow(now()->addHours(48));

        $this->assertSame(0, app(OfferService::class)->expireOverdue());
        $this->assertSame(MemberOfferStatus::Accepted, $offer->fresh()->status);

        Carbon::setTestNow();
    }

    public function test_the_dashboard_shows_expired_before_the_sweep_catches_up(): void
    {
        $this->submit();

        Carbon::setTestNow(now()->addHours(25));

        $this->actingAs($this->owner)
            ->get(route('offers.index'))
            ->assertOk()
            ->assertSee('EXPIRED');

        Carbon::setTestNow();
    }

    public function test_the_artisan_command_runs_the_sweep(): void
    {
        $this->submit();

        Carbon::setTestNow(now()->addHours(25));

        $this->artisan('offers:expire')->assertSuccessful();

        $this->assertSame(MemberOfferStatus::Expired, MemberOffer::query()->sole()->status);

        Carbon::setTestNow();
    }

    // --- Direction isolation ----------------------------------------------
    //
    // Buyer submissions store the listing owner in member_user_id, which is
    // the same column the outbound offer flow keys on. These two tests pin
    // the separation: leaking either way would let an owner answer a buyer
    // offer through MemberOfferController, which has no expiry check.

    public function test_buyer_offers_stay_off_the_outbound_member_dashboard(): void
    {
        $this->submit();

        $member = User::factory()->create(['role' => UserRole::Member]);
        MemberOffer::query()->sole()->update(['member_user_id' => $member->id]);

        $this->actingAs($member)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee($this->property->title);
    }

    public function test_a_buyer_offer_cannot_be_answered_through_the_outbound_endpoint(): void
    {
        $this->submit();
        $offer = MemberOffer::query()->sole();

        $this->actingAs($this->owner)
            ->post(route('member.offers.accept', $offer))
            ->assertNotFound();

        $this->assertSame(MemberOfferStatus::Active, $offer->fresh()->status);
    }

    // --- Admin register ----------------------------------------------------

    public function test_admin_register_shows_submissions_across_all_listings(): void
    {
        $this->seed(RbacSeeder::class);
        $this->submit();

        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get(route('admin.offers.index'))
            ->assertOk()
            ->assertSee($this->buyer->name)
            ->assertSee($this->owner->name)
            ->assertSee($this->property->title);
    }

    public function test_a_role_without_offers_view_cannot_reach_the_admin_register(): void
    {
        $this->seed(RbacSeeder::class);
        $this->submit();

        $this->actingAs($this->buyer)
            ->get(route('admin.offers.index'))
            ->assertForbidden();
    }
}

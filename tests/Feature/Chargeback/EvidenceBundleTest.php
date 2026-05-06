<?php

namespace Tests\Feature\Chargeback;

use App\Enums\PaymentProcessor;
use App\Exceptions\NotImplementedException;
use App\Models\Booking;
use App\Models\Charge;
use App\Models\LoginSession;
use App\Models\PaymentIntent;
use App\Models\Refund;
use App\Models\TermsAcceptance;
use App\Models\TermsVersion;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Services\Chargeback\EvidenceBundleService;
use App\Services\Payments\DisputeAdapterRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvidenceBundleTest extends TestCase
{
    use RefreshDatabase;

    public function test_bundle_contains_logins_charges_refunds_and_terms(): void
    {
        $user = User::factory()->create();
        $booking = Booking::factory()->create(['traveler_id' => $user->id]);

        LoginSession::factory()->count(3)->create([
            'user_id' => $user->id,
            'occurred_at' => now()->subDays(5),
        ]);

        $intent = PaymentIntent::factory()->create(['booking_id' => $booking->id]);
        $charge = Charge::factory()->create([
            'booking_id' => $booking->id,
            'payment_intent_id' => $intent->id,
        ]);
        Refund::factory()->create([
            'charge_id' => $charge->id,
            'booking_id' => $booking->id,
        ]);

        $tos = TermsVersion::factory()->create();
        TermsAcceptance::create([
            'user_id' => $user->id,
            'terms_version_id' => $tos->id,
            'accepted_at' => now()->subDay(),
        ]);

        $bundle = $this->app->make(EvidenceBundleService::class)->generateForBooking($booking);

        $this->assertSame($booking->id, $bundle->booking_id);
        $this->assertSame($user->id, $bundle->user_id);
        $this->assertSame($booking->confirmation_code, $bundle->confirmation_code);
        $this->assertCount(3, $bundle->logins);
        $this->assertCount(1, $bundle->charges);
        $this->assertCount(1, $bundle->refunds);
        $this->assertCount(1, $bundle->terms_acceptances);
    }

    public function test_consumption_events_appear_before_passive_events(): void
    {
        $user = User::factory()->create();
        $booking = Booking::factory()->create(['traveler_id' => $user->id]);
        $svc = $this->app->make(\App\Services\Tracking\TrackingService::class);

        // Mix of consumption + passive, recorded out-of-order on the timeline:
        $svc->record('page_view', actorUserId: $user->id);             // passive
        $svc->record('booking_created', actorUserId: $user->id);       // consumption
        $svc->record('ad_click', actorUserId: $user->id);              // passive
        $svc->record('login_succeeded', actorUserId: $user->id);       // consumption
        $svc->record('search_performed', actorUserId: $user->id);      // consumption

        $bundle = $this->app->make(EvidenceBundleService::class)
            ->generateForBooking($booking, from: CarbonImmutable::now()->subDay(), to: CarbonImmutable::now()->addDay());

        // In bundle->toArray(): consumption first, then passive — per FR-10.6.
        $arr = $bundle->toArray();
        $types = array_column($arr['events'], 'event_type');
        $consumptionIndices = [];
        $passiveIndices = [];
        foreach ($types as $i => $t) {
            if (in_array($t, ['booking_created', 'login_succeeded', 'search_performed'], true)) {
                $consumptionIndices[] = $i;
            } else {
                $passiveIndices[] = $i;
            }
        }

        $this->assertCount(3, $consumptionIndices);
        $this->assertCount(2, $passiveIndices);
        $this->assertSame(3, $arr['consumption_events_count']);
        $this->assertSame(2, $arr['passive_events_count']);
        // Every consumption index must come before every passive index.
        $this->assertLessThan(min($passiveIndices), max($consumptionIndices));
    }

    public function test_to_array_includes_generated_at_timestamp(): void
    {
        $booking = Booking::factory()->create();
        $bundle = $this->app->make(EvidenceBundleService::class)->generateForBooking($booking);

        $arr = $bundle->toArray();
        $this->assertArrayHasKey('generated_at', $arr);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', $arr['generated_at']);
    }

    public function test_registry_resolves_stripe_adapter(): void
    {
        $reg = $this->app->make(DisputeAdapterRegistry::class);
        $adapter = $reg->for(PaymentProcessor::Stripe);

        $this->assertInstanceOf(
            \App\Services\Payments\Stripe\StripeDisputeAdapter::class,
            $adapter
        );
    }

    public function test_registry_has_all_ten_processors(): void
    {
        $reg = $this->app->make(DisputeAdapterRegistry::class);
        $map = $reg->all();

        $expected = [
            'stripe', 'authorizenet', 'nmi', 'nuvei', 'mes',
            'paymentcloud', 'ems', 'nexio', 'netevia', 'kurv',
        ];
        foreach ($expected as $p) {
            $this->assertArrayHasKey($p, $map, "Missing adapter for {$p}");
            $this->assertTrue(class_exists($map[$p]), "Class missing: {$map[$p]}");
        }
    }

    public function test_non_stripe_adapters_throw_not_implemented(): void
    {
        $reg = $this->app->make(DisputeAdapterRegistry::class);
        $bundle = $this->app->make(EvidenceBundleService::class)
            ->generateForBooking(Booking::factory()->create());

        $stubs = ['authorizenet', 'nmi', 'nuvei', 'mes', 'paymentcloud', 'ems', 'nexio', 'netevia', 'kurv'];

        foreach ($stubs as $proc) {
            try {
                $reg->for($proc)->submit('dp_test_'.$proc, $bundle);
                $this->fail("{$proc} adapter should throw NotImplementedException");
            } catch (NotImplementedException $e) {
                $this->assertStringContainsString('pending Phase 12', $e->getMessage());
            }
        }
    }

    public function test_registry_rejects_unknown_processor(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->app->make(DisputeAdapterRegistry::class)->for('paypal');
    }
}

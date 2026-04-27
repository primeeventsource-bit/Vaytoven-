<?php

namespace Tests\Unit;

use App\Models\Property;
use App\Services\PricingService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class PricingServiceTest extends TestCase
{
    private PricingService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        // For isolated unit tests, instantiate with explicit config.
        // In Feature tests use the container so .env values flow through.
        config()->set('vaytoven.fees.guest_bps', 1400);
        config()->set('vaytoven.fees.host_bps', 300);
        config()->set('vaytoven.fees.default_tax_bps', 850);
        $this->svc = new PricingService();
    }

    public function test_basic_quote_5_nights_at_200_per_night(): void
    {
        $property = $this->fakeProperty([
            'base_price_cents'   => 20000,  // $200/night
            'cleaning_fee_cents' => 7500,   // $75
            'min_nights'         => 1,
            'max_nights'         => 28,
            'max_guests'         => 6,
        ]);

        $quote = $this->svc->quote(
            $property,
            CarbonImmutable::parse('2026-06-01'),
            CarbonImmutable::parse('2026-06-06'),
            2,
        );

        // 5 nights × $200 = $1000 base
        $this->assertSame(100000, $quote['base_price_cents']);
        $this->assertSame(7500,   $quote['cleaning_fee_cents']);
        $this->assertSame(107500, $quote['subtotal_cents']);
        // 14% guest fee on subtotal = $150.50
        $this->assertSame(15050, $quote['guest_service_fee_cents']);
        // 3% host fee on subtotal = $32.25
        $this->assertSame(3225,  $quote['host_service_fee_cents']);
        // 8.5% tax on (subtotal + guest fee) = $10,420 cents... let me recompute
        // (107500 + 15050) * 0.085 = 122550 * 0.085 = 10416.75 → 10417
        $this->assertSame(10417, $quote['tax_cents']);
        // Total = subtotal + guest fee + tax
        $this->assertSame(132967, $quote['total_cents']);
        // Host payout = subtotal - host fee
        $this->assertSame(104275, $quote['host_payout_cents']);
    }

    public function test_minimum_nights_enforced(): void
    {
        $property = $this->fakeProperty(['min_nights' => 3]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Minimum 3 nights');

        $this->svc->quote(
            $property,
            CarbonImmutable::parse('2026-06-01'),
            CarbonImmutable::parse('2026-06-02'),
            2,
        );
    }

    public function test_extra_guest_fee_only_above_threshold(): void
    {
        $property = $this->fakeProperty([
            'base_price_cents'      => 10000,
            'cleaning_fee_cents'    => 0,
            'extra_guests_after'    => 2,
            'extra_guest_fee_cents' => 2500,
            'max_guests'            => 6,
            'min_nights'            => 1,
        ]);

        // 2 guests = no extra fee
        $q1 = $this->svc->quote($property, CarbonImmutable::parse('2026-06-01'), CarbonImmutable::parse('2026-06-03'), 2);
        $this->assertSame(0, $q1['extra_guest_fee_cents']);

        // 4 guests = (4-2) extras × $25 × 2 nights = $100
        $q2 = $this->svc->quote($property, CarbonImmutable::parse('2026-06-01'), CarbonImmutable::parse('2026-06-03'), 4);
        $this->assertSame(10000, $q2['extra_guest_fee_cents']);
    }

    public function test_cancellation_refund_flexible_policy(): void
    {
        // Flexible: full refund if more than 24h before check-in
        $now = CarbonImmutable::parse('2026-06-01 12:00');

        $this->assertSame(
            100000,
            $this->svc->cancellationRefund(100000, 'flexible', $now, $now->addHours(48))
        );

        $this->assertSame(
            0,
            $this->svc->cancellationRefund(100000, 'flexible', $now, $now->addHours(20))
        );
    }

    public function test_cancellation_refund_strict_policy(): void
    {
        $now = CarbonImmutable::parse('2026-06-01 12:00');

        // Strict: full refund only if 7+ days out
        $this->assertSame(100000, $this->svc->cancellationRefund(100000, 'strict', $now, $now->addDays(8)));
        // 50% refund if 2-7 days out
        $this->assertSame(50000,  $this->svc->cancellationRefund(100000, 'strict', $now, $now->addDays(3)));
        // No refund within 2 days
        $this->assertSame(0,      $this->svc->cancellationRefund(100000, 'strict', $now, $now->addHours(36)));
    }

    public function test_non_refundable_always_zero(): void
    {
        $now = CarbonImmutable::parse('2026-06-01 12:00');
        $this->assertSame(0, $this->svc->cancellationRefund(100000, 'non_refundable', $now, $now->addDays(30)));
    }

    private function fakeProperty(array $overrides = []): Property
    {
        $p = new Property();
        $p->forceFill(array_merge([
            'id'                    => 1,
            'base_price_cents'      => 20000,
            'cleaning_fee_cents'    => 0,
            'extra_guest_fee_cents' => 0,
            'extra_guests_after'    => 0,
            'min_nights'            => 1,
            'max_nights'            => 28,
            'max_guests'            => 4,
            'currency'              => 'USD',
        ], $overrides));
        return $p;
    }
}

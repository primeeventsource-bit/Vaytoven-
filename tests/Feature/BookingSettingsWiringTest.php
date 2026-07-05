<?php

namespace Tests\Feature;

use App\Enums\CancellationPolicy;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\CancellationPolicyConfig;
use App\Models\Property;
use App\Models\TaxRule;
use App\Models\User;
use App\Services\Bookings\BookingService;
use App\Services\Bookings\QuoteCalculator;
use App\Services\Payments\RefundCalculator;
use App\Services\Settings\SettingsRepository;
use Carbon\CarbonImmutable;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves the Settings console actually drives behavior (§9 acceptance):
 * a changed fee percentage lands on the NEXT quote, tax rules replace the
 * hardcoded 8%, booking rules come from settings, and refund tiers come
 * from the admin-editable cancellation_policies table.
 */
class BookingSettingsWiringTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $traveler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->traveler = User::factory()->create(['role' => UserRole::Traveler]);
    }

    private function makeProperty(): Property
    {
        return Property::factory()->create([
            'base_nightly_cents' => 10000,
            'cleaning_fee_cents' => 5000,
            'minimum_nights' => 1,
            'cancellation_policy' => CancellationPolicy::Moderate->value,
        ]);
    }

    private function book(Property $property, string $in, string $out): Booking
    {
        return app(BookingService::class)->create($this->traveler, $property, $in, $out, 2);
    }

    public function test_defaults_reproduce_legacy_fee_and_tax_math(): void
    {
        $booking = $this->book($this->makeProperty(), '2026-08-10', '2026-08-15');

        // 5 nights * $100 = $500; 12% fee = $60; 8% tax on (500+50+60) = $48.80
        $this->assertSame(50000, $booking->subtotal_cents);
        $this->assertSame(6000, $booking->service_fee_cents);
        $this->assertSame(4880, $booking->tax_cents);
        $this->assertSame(65880, $booking->total_cents);
    }

    public function test_changed_service_fee_pct_applies_to_next_booking(): void
    {
        $property = $this->makeProperty();
        $this->book($property, '2026-08-10', '2026-08-15'); // warms caches at 12%

        app(SettingsRepository::class)->set('fees.guest_service_pct', 20, $this->admin);

        $booking = $this->book($property, '2026-09-01', '2026-09-06');
        $this->assertSame(10000, $booking->service_fee_cents); // 20% of $500
    }

    public function test_tax_rule_replaces_hardcoded_rate(): void
    {
        (new SettingsSeeder)->run();
        TaxRule::query()->update(['rate_bps' => 1000]); // 10%
        QuoteCalculator::bustCache();

        $booking = $this->book($this->makeProperty(), '2026-08-10', '2026-08-15');
        $this->assertSame(6100, $booking->tax_cents); // 10% of (500+50+60)
    }

    public function test_max_nights_setting_is_enforced(): void
    {
        app(SettingsRepository::class)->set('booking.max_nights', 5, $this->admin);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('limited to 5 nights');
        $this->book($this->makeProperty(), '2026-08-10', '2026-08-16'); // 6 nights
    }

    public function test_advance_window_setting_is_enforced(): void
    {
        app(SettingsRepository::class)->set('booking.advance_window_days', 30, $this->admin);

        $in = CarbonImmutable::now()->addDays(60)->toDateString();
        $out = CarbonImmutable::now()->addDays(63)->toDateString();

        $this->expectException(\InvalidArgumentException::class);
        $this->book($this->makeProperty(), $in, $out);
    }

    public function test_refund_tiers_come_from_admin_editable_policy(): void
    {
        (new SettingsSeeder)->run();

        $booking = $this->book($this->makeProperty(), '2027-01-15', '2027-01-20');

        // Seeded moderate tiers reproduce legacy behavior: 2 days out = 50%.
        $cancelledAt = CarbonImmutable::parse('2027-01-13 00:00');
        $this->assertSame('partial', RefundCalculator::compute($booking, $cancelledAt)->tier);

        // Operator makes moderate fully refundable up to 1 day out — the
        // same cancellation now yields a full refund.
        CancellationPolicyConfig::query()->where('code', 'moderate')
            ->update(['refund_tiers' => json_encode([['days_before' => 1, 'refund_pct' => 100]])]);
        RefundCalculator::bustCache();

        $result = RefundCalculator::compute($booking, $cancelledAt);
        $this->assertSame('full', $result->tier);
        $this->assertSame($booking->subtotal_cents, $result->subtotal_refund_cents);
        // The service fee stays non-refundable regardless of tiers.
        $this->assertSame(0, $result->service_fee_refund_cents);
    }
}

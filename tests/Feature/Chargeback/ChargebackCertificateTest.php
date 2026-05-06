<?php

namespace Tests\Feature\Chargeback;

use App\Models\Booking;
use App\Models\Charge;
use App\Models\LoginSession;
use App\Models\PaymentIntent;
use App\Models\Refund;
use App\Models\TermsAcceptance;
use App\Models\TermsVersion;
use App\Models\User;
use App\Services\Chargeback\ChargebackCertificateService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChargebackCertificateTest extends TestCase
{
    use RefreshDatabase;

    public function test_certificate_for_booking_returns_valid_pdf_binary(): void
    {
        $user = User::factory()->create(['name' => 'Test User']);
        $booking = Booking::factory()->create(['traveler_id' => $user->id]);

        $pdf = $this->app->make(ChargebackCertificateService::class)->forBooking($booking);

        // PDFs always start with %PDF-
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(1000, strlen($pdf));
    }

    public function test_certificate_includes_login_records_in_pdf(): void
    {
        $user = User::factory()->create();
        $booking = Booking::factory()->create(['traveler_id' => $user->id]);

        LoginSession::factory()->count(3)->create([
            'user_id' => $user->id,
            'country' => 'US',
            'city' => 'San Francisco',
            'occurred_at' => now()->subDays(2),
        ]);

        $pdf = $this->app->make(ChargebackCertificateService::class)->forBooking($booking);

        // dompdf compresses output, so we can't grep raw text reliably. Smoke-check
        // size grows with login data instead.
        $emptyUser = User::factory()->create();
        $emptyBooking = Booking::factory()->create(['traveler_id' => $emptyUser->id]);
        $emptyPdf = $this->app->make(ChargebackCertificateService::class)->forBooking($emptyBooking);

        $this->assertGreaterThan(strlen($emptyPdf), strlen($pdf));
    }

    public function test_certificate_for_user_across_window_works_without_a_booking(): void
    {
        $user = User::factory()->create();
        LoginSession::factory()->count(2)->create([
            'user_id' => $user->id,
            'occurred_at' => now()->subWeek(),
        ]);

        $pdf = $this->app->make(ChargebackCertificateService::class)->forUser(
            $user,
            CarbonImmutable::now()->subMonth(),
            CarbonImmutable::now(),
        );

        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    public function test_certificate_handles_user_with_no_history_gracefully(): void
    {
        $user = User::factory()->create();
        $booking = Booking::factory()->create(['traveler_id' => $user->id]);

        // Zero logins, no terms, no charges, no events.
        $pdf = $this->app->make(ChargebackCertificateService::class)->forBooking($booking);

        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    public function test_certificate_completes_for_50_login_records(): void
    {
        $user = User::factory()->create();
        $booking = Booking::factory()->create(['traveler_id' => $user->id]);

        LoginSession::factory()->count(50)->create([
            'user_id' => $user->id,
            'occurred_at' => now()->subDays(rand(1, 60)),
        ]);

        $start = microtime(true);
        $pdf = $this->app->make(ChargebackCertificateService::class)->forBooking($booking);
        $elapsed = microtime(true) - $start;

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertLessThan(5.0, $elapsed, 'Certificate generation took longer than 5 seconds');
    }

    public function test_filename_helper_includes_confirmation_code_when_available(): void
    {
        $svc = $this->app->make(ChargebackCertificateService::class);

        $f1 = $svc->filenameFor('VYT-K3M9P2');
        $this->assertStringContainsString('VYT-K3M9P2', $f1);
        $this->assertStringEndsWith('.pdf', $f1);

        $f2 = $svc->filenameFor(userId: 42);
        $this->assertStringContainsString('user-42', $f2);
    }

    public function test_certificate_includes_terms_charges_and_refunds(): void
    {
        $user = User::factory()->create();
        $booking = Booking::factory()->create(['traveler_id' => $user->id]);

        $tos = TermsVersion::factory()->create();
        TermsAcceptance::create([
            'user_id' => $user->id,
            'terms_version_id' => $tos->id,
            'accepted_at' => now()->subDay(),
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

        // Render the bundle directly (faster than going through dompdf).
        $bundle = $this->app->make(\App\Services\Chargeback\EvidenceBundleService::class)
            ->generateForBooking($booking);

        $arr = $bundle->toArray();
        $this->assertCount(1, $arr['terms_acceptances']);
        $this->assertCount(1, $arr['charges']);
        $this->assertCount(1, $arr['refunds']);
    }
}

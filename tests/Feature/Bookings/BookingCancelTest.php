<?php

namespace Tests\Feature\Bookings;

use App\Enums\BookingStatus;
use App\Enums\CancellationPolicy;
use App\Enums\PaymentProcessor;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Charge;
use App\Models\PaymentIntent;
use App\Models\Refund;
use App\Models\TermsAcceptance;
use App\Models\User;
use App\Services\Legal\LegalDocumentRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Stripe\Service\PaymentIntentService;
use Stripe\Service\RefundService;
use Stripe\StripeClient;
use Tests\TestCase;

class BookingCancelTest extends TestCase
{
    use RefreshDatabase;

    private $stripeRefunds;
    private $stripeIntents;

    protected function setUp(): void
    {
        parent::setUp();
        app(LegalDocumentRegistry::class)->materialiseAll();

        // Wire a mocked Stripe client. Live-mode tests opt in by setting
        // services.stripe.* + keying the refund mock.
        $this->stripeIntents = Mockery::mock(PaymentIntentService::class);
        $this->stripeRefunds = Mockery::mock(RefundService::class);
        $client = Mockery::mock(StripeClient::class)->makePartial();
        $client->paymentIntents = $this->stripeIntents;
        $client->refunds = $this->stripeRefunds;
        $this->app->instance(StripeClient::class, $client);

        // Default: demo mode (no Stripe keys).
        config(['services.stripe.secret' => '', 'services.stripe.key' => '']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_cancel_form_404s_for_other_users(): void
    {
        $owner = $this->makeTraveler();
        $other = $this->makeTraveler();
        $booking = Booking::factory()->create(['traveler_id' => $owner->id]);

        $this->actingAs($other)
            ->get(route('bookings.cancel.form', $booking))
            ->assertNotFound();
    }

    public function test_cancel_form_redirects_when_already_terminal(): void
    {
        $traveler = $this->makeTraveler();
        $booking = Booking::factory()->create([
            'traveler_id' => $traveler->id,
            'status'      => BookingStatus::Cancelled->value,
        ]);

        $this->actingAs($traveler)
            ->get(route('bookings.cancel.form', $booking))
            ->assertRedirect(route('bookings.show', $booking))
            ->assertSessionHas('booking_error');
    }

    public function test_cancel_form_renders_refund_breakdown(): void
    {
        $traveler = $this->makeTraveler();
        $booking = Booking::factory()->create([
            'traveler_id'         => $traveler->id,
            'status'              => BookingStatus::Confirmed->value,
            'cancellation_policy' => CancellationPolicy::Flexible->value,
            'check_in_date'       => now()->addDays(30),
            'check_out_date'      => now()->addDays(33),
        ]);

        $resp = $this->actingAs($traveler)
            ->get(route('bookings.cancel.form', $booking));

        $resp->assertOk();
        $resp->assertSee('Cancel this booking?');
        $resp->assertSee('Refund estimate');
        $resp->assertSee('full refund', false);
    }

    public function test_cancel_transitions_status_and_records_reason(): void
    {
        $traveler = $this->makeTraveler();
        $booking = Booking::factory()->create([
            'traveler_id'         => $traveler->id,
            'status'              => BookingStatus::Confirmed->value,
            'cancellation_policy' => CancellationPolicy::Flexible->value,
            'check_in_date'       => now()->addDays(30),
            'check_out_date'      => now()->addDays(33),
        ]);

        $this->actingAs($traveler)
            ->post(route('bookings.cancel', $booking), [
                'reason' => 'plans changed',
            ])
            ->assertRedirect(route('bookings.show', $booking));

        $booking->refresh();
        $this->assertSame(BookingStatus::Cancelled, $booking->status);
        $this->assertSame('plans changed', $booking->cancelled_reason);
        $this->assertNotNull($booking->cancelled_at);
    }

    public function test_cancel_demo_mode_does_not_call_stripe(): void
    {
        $this->stripeRefunds->shouldNotReceive('create');

        $traveler = $this->makeTraveler();
        $booking = Booking::factory()->create([
            'traveler_id' => $traveler->id,
            'status'      => BookingStatus::Confirmed->value,
            'cancellation_policy' => CancellationPolicy::Flexible->value,
            'check_in_date' => now()->addDays(30),
            'check_out_date' => now()->addDays(33),
        ]);

        $this->actingAs($traveler)
            ->post(route('bookings.cancel', $booking))
            ->assertRedirect();

        $this->assertSame(0, Refund::count());
    }

    public function test_cancel_live_mode_issues_stripe_refund_when_charge_exists(): void
    {
        config(['services.stripe.secret' => 'sk_test_mock', 'services.stripe.key' => 'pk_test_mock']);

        $traveler = $this->makeTraveler();
        $booking = Booking::factory()->create([
            'traveler_id'         => $traveler->id,
            'status'              => BookingStatus::Confirmed->value,
            'cancellation_policy' => CancellationPolicy::Flexible->value,
            'check_in_date'       => now()->addDays(30),
            'check_out_date'      => now()->addDays(33),
            'subtotal_cents'      => 30000,
            'cleaning_fee_cents'  => 5000,
            'service_fee_cents'   => 3600,
            'tax_cents'           => 3088,
            'total_cents'         => 41688,
        ]);

        $intent = PaymentIntent::create([
            'booking_id'         => $booking->id,
            'processor'          => PaymentProcessor::Stripe->value,
            'external_intent_id' => 'pi_live_refund_test',
            'amount_cents'       => $booking->total_cents,
            'currency'           => 'USD',
            'status'             => 'succeeded',
        ]);
        $charge = Charge::create([
            'booking_id'         => $booking->id,
            'payment_intent_id'  => $intent->id,
            'processor'          => PaymentProcessor::Stripe->value,
            'external_charge_id' => 'ch_live_refund_test',
            'amount_cents'       => $booking->total_cents,
            'currency'           => 'USD',
        ]);

        $this->stripeRefunds->shouldReceive('create')
            ->once()
            ->andReturn((object) [
                'id'     => 're_live_test_001',
                'amount' => 38688, // flexible/full minus non-refundable service fee
            ]);

        $this->actingAs($traveler)
            ->post(route('bookings.cancel', $booking))
            ->assertRedirect();

        $this->assertSame(1, Refund::count());
        $refund = Refund::sole();
        $this->assertSame('re_live_test_001', $refund->external_refund_id);
        $this->assertSame($charge->id, $refund->charge_id);
    }

    public function test_show_page_links_to_cancel_when_status_allows(): void
    {
        $traveler = $this->makeTraveler();
        $booking = Booking::factory()->create([
            'traveler_id' => $traveler->id,
            'status'      => BookingStatus::Confirmed->value,
        ]);

        $this->actingAs($traveler)
            ->get(route('bookings.show', $booking))
            ->assertSee(route('bookings.cancel.form', $booking), false);
    }

    public function test_show_page_hides_cancel_link_for_terminal_bookings(): void
    {
        $traveler = $this->makeTraveler();
        $booking = Booking::factory()->create([
            'traveler_id' => $traveler->id,
            'status'      => BookingStatus::Cancelled->value,
        ]);

        $this->actingAs($traveler)
            ->get(route('bookings.show', $booking))
            ->assertDontSee(route('bookings.cancel.form', $booking), false);
    }

    private function makeTraveler(): User
    {
        $user = User::factory()->create([
            'role' => UserRole::Traveler,
            'email_verified_at' => now(),
        ]);
        foreach (app(LegalDocumentRegistry::class)->registrationRequired() as $version) {
            TermsAcceptance::create([
                'user_id' => $user->id,
                'terms_version_id' => $version->id,
                'accepted_at' => now(),
            ]);
        }
        return $user;
    }
}

<?php

namespace Tests\Feature\Bookings;

use App\Enums\BookingStatus;
use App\Enums\PropertyStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\PaymentIntent;
use App\Models\Property;
use App\Models\TermsAcceptance;
use App\Models\User;
use App\Services\Legal\LegalDocumentRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Stripe\Service\PaymentIntentService;
use Stripe\StripeClient;
use Tests\TestCase;

class BookingPaymentTest extends TestCase
{
    use RefreshDatabase;

    private $intents;

    protected function setUp(): void
    {
        parent::setUp();
        app(LegalDocumentRegistry::class)->materialiseAll();

        // Default: Stripe credentials are present so stripeConfigured() returns true.
        // Individual demo-mode tests blank these out.
        config([
            'services.stripe.secret' => 'sk_test_mocked',
            'services.stripe.key'    => 'pk_test_mocked',
        ]);

        $this->intents = Mockery::mock(PaymentIntentService::class);
        $stripeClient = Mockery::mock(StripeClient::class)->makePartial();
        $stripeClient->paymentIntents = $this->intents;
        $this->app->instance(StripeClient::class, $stripeClient);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_store_creates_payment_intent_when_stripe_is_live(): void
    {
        $traveler = $this->makeTraveler();
        $property = $this->makeProperty();

        $this->intents->shouldReceive('create')
            ->once()
            ->andReturn($this->stubStripeIntent('pi_live_001', 'pi_live_001_secret_xyz'));

        $this->actingAs($traveler)
            ->post(route('bookings.store', $property), [
                'check_in'  => now()->addDays(7)->toDateString(),
                'check_out' => now()->addDays(10)->toDateString(),
                'guests'    => 2,
            ])
            ->assertRedirect();

        $booking = Booking::sole();
        $intent = PaymentIntent::sole();

        $this->assertSame($booking->id, $intent->booking_id);
        $this->assertSame('pi_live_001', $intent->external_intent_id);
        $this->assertSame('pi_live_001_secret_xyz', $intent->client_secret);
        $this->assertSame($intent->id, $booking->fresh()->payment_intent_id);
    }

    public function test_store_redirects_to_pay_route_when_stripe_is_live(): void
    {
        $traveler = $this->makeTraveler();
        $property = $this->makeProperty();

        $this->intents->shouldReceive('create')
            ->andReturn($this->stubStripeIntent('pi_redirect_test', 'cs_redirect_xyz'));

        $resp = $this->actingAs($traveler)
            ->post(route('bookings.store', $property), [
                'check_in'  => now()->addDays(7)->toDateString(),
                'check_out' => now()->addDays(9)->toDateString(),
                'guests'    => 2,
            ]);

        $booking = Booking::sole();
        $resp->assertRedirect(route('bookings.pay', $booking));
    }

    public function test_store_skips_stripe_when_demo_mode(): void
    {
        config(['services.stripe.secret' => '', 'services.stripe.key' => '']);

        $this->intents->shouldNotReceive('create');

        $traveler = $this->makeTraveler();
        $property = $this->makeProperty();

        $this->actingAs($traveler)
            ->post(route('bookings.store', $property), [
                'check_in'  => now()->addDays(7)->toDateString(),
                'check_out' => now()->addDays(9)->toDateString(),
                'guests'    => 2,
            ])->assertRedirect();

        // Booking persists but no PaymentIntent row.
        $this->assertSame(1, Booking::count());
        $this->assertSame(0, PaymentIntent::count());
    }

    public function test_store_falls_back_to_show_when_stripe_throws(): void
    {
        $traveler = $this->makeTraveler();
        $property = $this->makeProperty();

        $this->intents->shouldReceive('create')
            ->andThrow(new \RuntimeException('stripe API down'));

        $this->actingAs($traveler)
            ->post(route('bookings.store', $property), [
                'check_in'  => now()->addDays(7)->toDateString(),
                'check_out' => now()->addDays(9)->toDateString(),
                'guests'    => 2,
            ])->assertRedirect();

        // The booking row still exists — we don't lose the reservation when
        // Stripe is briefly unavailable. The user lands on /bookings/{id}.
        $booking = Booking::sole();
        $this->assertSame(BookingStatus::PendingPayment, $booking->status);
        $this->assertNull($booking->payment_intent_id);
    }

    public function test_pay_page_renders_stripe_elements_with_client_secret(): void
    {
        $traveler = $this->makeTraveler();
        $booking = $this->makeBooking($traveler, 'pi_pay_render', 'cs_pay_render');

        $resp = $this->actingAs($traveler)->get(route('bookings.pay', $booking));

        $resp->assertOk();
        $resp->assertSee('Confirm and pay');
        $resp->assertSee('cs_pay_render', false);
        $resp->assertSee('pk_test_mocked', false);
        $resp->assertSee('js.stripe.com/v3', false);
    }

    public function test_pay_page_404s_for_other_users_booking(): void
    {
        $owner = $this->makeTraveler();
        $other = $this->makeTraveler();

        $booking = $this->makeBooking($owner, 'pi_other', 'cs_other');

        $this->actingAs($other)
            ->get(route('bookings.pay', $booking))
            ->assertNotFound();
    }

    public function test_pay_page_redirects_to_show_when_demo_mode(): void
    {
        config(['services.stripe.secret' => '', 'services.stripe.key' => '']);

        $traveler = $this->makeTraveler();
        $booking = Booking::factory()->create(['traveler_id' => $traveler->id]);

        $this->actingAs($traveler)
            ->get(route('bookings.pay', $booking))
            ->assertRedirect(route('bookings.show', $booking));
    }

    public function test_pay_page_redirects_to_show_when_already_confirmed(): void
    {
        $traveler = $this->makeTraveler();
        $booking = $this->makeBooking($traveler, 'pi_done', 'cs_done', BookingStatus::Confirmed);

        $this->actingAs($traveler)
            ->get(route('bookings.pay', $booking))
            ->assertRedirect(route('bookings.show', $booking));
    }

    public function test_show_page_renders_stripe_redirect_status_notice(): void
    {
        $traveler = $this->makeTraveler();
        $booking = $this->makeBooking($traveler, 'pi_redir', 'cs_redir', BookingStatus::Confirmed);

        $resp = $this->actingAs($traveler)
            ->get(route('bookings.show', $booking) . '?redirect_status=succeeded');

        $resp->assertOk();
        $resp->assertSee('Payment received');
    }

    public function test_show_page_renders_failure_notice_on_decline(): void
    {
        $traveler = $this->makeTraveler();
        $booking = $this->makeBooking($traveler, 'pi_fail', 'cs_fail');

        $resp = $this->actingAs($traveler)
            ->get(route('bookings.show', $booking) . '?redirect_status=requires_payment_method');

        $resp->assertOk();
        $resp->assertSee('declined');
    }

    public function test_show_page_renders_pay_cta_in_live_pending_state(): void
    {
        $traveler = $this->makeTraveler();
        $booking = $this->makeBooking($traveler, 'pi_pending', 'cs_pending');

        $resp = $this->actingAs($traveler)->get(route('bookings.show', $booking));

        $resp->assertOk();
        // Live + pending → primary "Pay $X" button linking to bookings.pay.
        $resp->assertSee(route('bookings.pay', $booking), false);
        $resp->assertDontSee('Demo mode');
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

    private function makeProperty(): Property
    {
        return Property::factory()->create([
            'status' => PropertyStatus::Active->value,
            'base_nightly_cents' => 15000,
            'cleaning_fee_cents' => 0,
            'minimum_nights' => 1,
            'capacity' => 6,
        ]);
    }

    private function makeBooking(User $traveler, string $intentId, string $clientSecret, BookingStatus $status = BookingStatus::PendingPayment): Booking
    {
        $booking = Booking::factory()->create([
            'traveler_id' => $traveler->id,
            'status'      => $status->value,
        ]);
        $intent = PaymentIntent::create([
            'booking_id'         => $booking->id,
            'processor'          => 'stripe',
            'external_intent_id' => $intentId,
            'client_secret'      => $clientSecret,
            'amount_cents'       => $booking->total_cents,
            'currency'           => 'USD',
            'status'             => 'requires_payment_method',
        ]);
        $booking->update(['payment_intent_id' => $intent->id]);
        return $booking->fresh();
    }

    private function stubStripeIntent(string $id, string $clientSecret): object
    {
        return (object) [
            'id'             => $id,
            'client_secret'  => $clientSecret,
            'amount'         => 38140,
            'currency'       => 'usd',
            'status'         => 'requires_payment_method',
            'metadata'       => new class { public function toArray(): array { return []; } },
        ];
    }
}

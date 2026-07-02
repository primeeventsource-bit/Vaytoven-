<?php

namespace Tests\Feature\Bookings;

use App\Enums\BookingStatus;
use App\Enums\PropertyStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Charge;
use App\Models\PaymentIntent;
use App\Models\Property;
use App\Models\TermsAcceptance;
use App\Models\User;
use App\Services\Legal\LegalDocumentRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BookingPaymentTest extends TestCase
{
    use RefreshDatabase;

    private const GATEWAY = 'https://secure.nmi.com/api/transact.php';

    protected function setUp(): void
    {
        parent::setUp();
        app(LegalDocumentRegistry::class)->materialiseAll();

        // Default: NMI credentials are present so paymentsConfigured() returns
        // true. Individual demo-mode tests blank these out.
        config([
            'services.nmi.security_key'     => 'nmi_sec_mocked',
            'services.nmi.tokenization_key' => 'nmi_tok_mocked',
        ]);
    }

    public function test_store_creates_local_intent_when_nmi_is_live(): void
    {
        Http::fake();
        $traveler = $this->makeTraveler();
        $property = $this->makeProperty();

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
        $this->assertSame("booking:{$booking->confirmation_code}", $intent->external_intent_id);
        $this->assertSame($intent->id, $booking->fresh()->payment_intent_id);

        // Intent creation is local — the gateway is only hit at charge time.
        Http::assertNothingSent();
    }

    public function test_store_redirects_to_pay_route_when_nmi_is_live(): void
    {
        $traveler = $this->makeTraveler();
        $property = $this->makeProperty();

        $resp = $this->actingAs($traveler)
            ->post(route('bookings.store', $property), [
                'check_in'  => now()->addDays(7)->toDateString(),
                'check_out' => now()->addDays(9)->toDateString(),
                'guests'    => 2,
            ]);

        $booking = Booking::sole();
        $resp->assertRedirect(route('bookings.pay', $booking));
    }

    public function test_store_skips_intent_when_demo_mode(): void
    {
        config(['services.nmi.security_key' => '', 'services.nmi.tokenization_key' => '']);

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

    public function test_pay_page_renders_collect_js_with_tokenization_key(): void
    {
        $traveler = $this->makeTraveler();
        $booking = $this->makeBooking($traveler);

        $resp = $this->actingAs($traveler)->get(route('bookings.pay', $booking));

        $resp->assertOk();
        $resp->assertSee('Confirm and pay');
        $resp->assertSee('data-tokenization-key="nmi_tok_mocked"', false);
        $resp->assertSee('secure.nmi.com/token/Collect.js', false);
        $resp->assertSee(route('bookings.pay.process', $booking), false);
    }

    public function test_pay_page_404s_for_other_users_booking(): void
    {
        $owner = $this->makeTraveler();
        $other = $this->makeTraveler();

        $booking = $this->makeBooking($owner);

        $this->actingAs($other)
            ->get(route('bookings.pay', $booking))
            ->assertNotFound();
    }

    public function test_pay_page_redirects_to_show_when_demo_mode(): void
    {
        config(['services.nmi.security_key' => '', 'services.nmi.tokenization_key' => '']);

        $traveler = $this->makeTraveler();
        $booking = Booking::factory()->create(['traveler_id' => $traveler->id]);

        $this->actingAs($traveler)
            ->get(route('bookings.pay', $booking))
            ->assertRedirect(route('bookings.show', $booking));
    }

    public function test_pay_page_redirects_to_show_when_already_confirmed(): void
    {
        $traveler = $this->makeTraveler();
        $booking = $this->makeBooking($traveler, BookingStatus::Confirmed);

        $this->actingAs($traveler)
            ->get(route('bookings.pay', $booking))
            ->assertRedirect(route('bookings.show', $booking));
    }

    public function test_process_payment_charges_token_and_confirms_booking(): void
    {
        Http::fake([
            self::GATEWAY => Http::response('response=1&responsetext=SUCCESS&authcode=123456&transactionid=1234509876&response_code=100', 200),
        ]);

        $traveler = $this->makeTraveler();
        $booking = $this->makeBooking($traveler);

        $resp = $this->actingAs($traveler)
            ->post(route('bookings.pay.process', $booking), [
                'payment_token' => 'tok_collectjs_ok',
            ]);

        $resp->assertRedirect(route('bookings.show', ['booking' => $booking, 'payment' => 'succeeded']));

        $booking->refresh();
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame(1, Charge::where('external_charge_id', '1234509876')->count());
    }

    public function test_process_payment_decline_redirects_back_with_error(): void
    {
        Http::fake([
            self::GATEWAY => Http::response('response=2&responsetext=DECLINE&transactionid=0&response_code=200', 200),
        ]);

        $traveler = $this->makeTraveler();
        $booking = $this->makeBooking($traveler);

        $resp = $this->actingAs($traveler)
            ->post(route('bookings.pay.process', $booking), [
                'payment_token' => 'tok_declined',
            ]);

        $resp->assertRedirect(route('bookings.pay', $booking));
        $resp->assertSessionHas('payment_error');

        $this->assertSame(BookingStatus::PendingPayment, $booking->fresh()->status);
        $this->assertSame(0, Charge::count());
    }

    public function test_process_payment_gateway_outage_redirects_back_with_error(): void
    {
        Http::fake([
            self::GATEWAY => Http::response('upstream timeout', 504),
        ]);

        $traveler = $this->makeTraveler();
        $booking = $this->makeBooking($traveler);

        $resp = $this->actingAs($traveler)
            ->post(route('bookings.pay.process', $booking), [
                'payment_token' => 'tok_outage',
            ]);

        $resp->assertRedirect(route('bookings.pay', $booking));
        $resp->assertSessionHas('payment_error');
        $this->assertSame(BookingStatus::PendingPayment, $booking->fresh()->status);
    }

    public function test_process_payment_requires_token(): void
    {
        Http::fake();
        $traveler = $this->makeTraveler();
        $booking = $this->makeBooking($traveler);

        $this->actingAs($traveler)
            ->post(route('bookings.pay.process', $booking), [])
            ->assertSessionHasErrors('payment_token');

        Http::assertNothingSent();
    }

    public function test_process_payment_404s_for_other_users_booking(): void
    {
        Http::fake();
        $owner = $this->makeTraveler();
        $other = $this->makeTraveler();
        $booking = $this->makeBooking($owner);

        $this->actingAs($other)
            ->post(route('bookings.pay.process', $booking), ['payment_token' => 'tok_x'])
            ->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_show_page_renders_payment_succeeded_notice(): void
    {
        $traveler = $this->makeTraveler();
        $booking = $this->makeBooking($traveler, BookingStatus::Confirmed);

        $resp = $this->actingAs($traveler)
            ->get(route('bookings.show', $booking) . '?payment=succeeded');

        $resp->assertOk();
        $resp->assertSee('Payment received');
    }

    public function test_show_page_renders_pay_cta_in_live_pending_state(): void
    {
        $traveler = $this->makeTraveler();
        $booking = $this->makeBooking($traveler);

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

    private function makeBooking(User $traveler, BookingStatus $status = BookingStatus::PendingPayment): Booking
    {
        $booking = Booking::factory()->create([
            'traveler_id' => $traveler->id,
            'status'      => $status->value,
        ]);
        $intent = PaymentIntent::create([
            'booking_id'         => $booking->id,
            'processor'          => 'nmi',
            'external_intent_id' => "booking:{$booking->confirmation_code}",
            'amount_cents'       => $booking->total_cents,
            'currency'           => 'USD',
            'status'             => 'requires_payment_method',
        ]);
        $booking->update(['payment_intent_id' => $intent->id]);
        return $booking->fresh();
    }
}

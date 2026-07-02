<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Charge;
use App\Models\HostPayoutAccount;
use App\Models\PaymentIntent;
use App\Models\Refund;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_intent_links_to_booking_and_charges(): void
    {
        $booking = Booking::factory()->create();
        $intent = PaymentIntent::factory()->create(['booking_id' => $booking->id]);
        $charge = Charge::factory()->create([
            'payment_intent_id' => $intent->id,
            'booking_id' => $booking->id,
        ]);

        $this->assertTrue($intent->booking->is($booking));
        $this->assertTrue($intent->charges->first()->is($charge));
    }

    public function test_refund_chains_to_charge_and_booking(): void
    {
        $charge = Charge::factory()->create();
        $refund = Refund::factory()->create([
            'charge_id' => $charge->id,
            'booking_id' => $charge->booking_id,
        ]);

        $this->assertTrue($refund->charge->is($charge));
        $this->assertTrue($charge->refunds->first()->is($refund));
    }

    public function test_money_amounts_are_integer_cents(): void
    {
        $intent = PaymentIntent::factory()->create(['amount_cents' => 12345]);
        $charge = Charge::factory()->create(['amount_cents' => 12345]);
        $refund = Refund::factory()->create(['amount_cents' => 5000]);

        $this->assertSame(12345, $intent->amount_cents);
        $this->assertSame(12345, $charge->amount_cents);
        $this->assertSame(5000, $refund->amount_cents);
        $this->assertIsInt($intent->amount_cents);
    }

    public function test_payment_intents_are_unique_per_processor_external_id(): void
    {
        $booking = Booking::factory()->create();
        PaymentIntent::factory()->create([
            'booking_id' => $booking->id,
            'processor' => 'nmi',
            'external_intent_id' => 'booking:VYT-DUP-TEST',
        ]);

        $this->expectException(QueryException::class);

        PaymentIntent::factory()->create([
            'booking_id' => $booking->id,
            'processor' => 'nmi',
            'external_intent_id' => 'booking:VYT-DUP-TEST',
        ]);
    }

    /**
     * The webhook idempotency contract (FR-4.3): inserting a row into
     * <processor>_events with a duplicate event_id MUST fail. Webhook
     * handlers exploit this — they insert first, and if the insert
     * succeeds the event is processed once; if it fails (unique violation)
     * the handler short-circuits as a no-op.
     */
    public function test_nmi_event_id_is_unique_for_idempotency(): void
    {
        DB::table('nmi_events')->insert([
            'event_id' => 'evt_idempotency_test',
            'event_type' => 'transaction.sale.success',
            'payload' => json_encode(['amount' => 5000]),
            'processed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('nmi_events')->insert([
            'event_id' => 'evt_idempotency_test',  // same event_id — replay
            'event_type' => 'transaction.sale.success',
            'payload' => json_encode(['amount' => 5000]),
            'processed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_all_ten_processor_events_tables_exist(): void
    {
        $processors = [
            'stripe', 'authorizenet', 'nmi', 'nuvei', 'mes',
            'paymentcloud', 'ems', 'nexio', 'netevia', 'kurv',
        ];

        foreach ($processors as $processor) {
            $this->assertTrue(
                DB::getSchemaBuilder()->hasTable("{$processor}_events"),
                "Missing idempotency table {$processor}_events"
            );
        }
    }

    public function test_host_payout_account_belongs_to_host(): void
    {
        $payout = HostPayoutAccount::factory()->create();

        $this->assertNotNull($payout->host);
        $this->assertSame('nmi', $payout->processor->value);
        $this->assertTrue($payout->payouts_enabled);
    }
}

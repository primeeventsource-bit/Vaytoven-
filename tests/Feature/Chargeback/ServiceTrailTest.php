<?php

namespace Tests\Feature\Chargeback;

use App\Enums\ActivityType;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Services\Chargeback\EvidenceBundleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The evidence bundle's service trail.
 *
 * The narrow sequence that answers "did this person agree to this and pay for
 * it". Browsing history pads a dispute file with unrelated visitor records and
 * proves nothing about consent — a card issuer reading forty page views to
 * find one payment is a worse outcome than being handed the six rows that
 * matter.
 */
class ServiceTrailTest extends TestCase
{
    use RefreshDatabase;

    private function event(User $user, ActivityType $type, string $at, array $extra = []): TrackingEvent
    {
        return TrackingEvent::create(array_merge([
            'event_type'    => $type->value,
            'actor_user_id' => $user->id,
            'surface'       => 'web',
            'ip_address'    => '73.12.44.184',
            'city'          => 'Orlando',
            'region'        => 'FL',
            'country'       => 'US',
            'device_type'   => 'mobile',
            'browser'       => 'Safari',
            'session_id'    => 'SES-TRAIL1',
            'result'        => 'completed',
            'occurred_at'   => Carbon::parse($at),
        ], $extra));
    }

    private function bundle(User $user): array
    {
        return app(EvidenceBundleService::class)
            ->generateForUser(
                $user->id,
                Carbon::parse('2026-01-01')->toImmutable(),
                Carbon::parse('2026-12-31')->toImmutable(),
            )
            ->toArray();
    }

    public function test_the_trail_holds_the_service_events_in_order(): void
    {
        $user = User::factory()->create();

        $this->event($user, ActivityType::AccountCreated, '2026-03-01 10:00');
        $this->event($user, ActivityType::ContractSigned, '2026-03-02 11:00');
        $this->event($user, ActivityType::PaymentApproved, '2026-03-02 11:30');
        $this->event($user, ActivityType::AdvertisementActivated, '2026-03-03 09:00');

        $trail = $this->bundle($user)['service_trail'];

        $this->assertCount(4, $trail);
        $this->assertSame('Account created', $trail[0]['activity']);
        $this->assertSame('Contract signed', $trail[1]['activity']);
        $this->assertSame('Payment approved', $trail[2]['activity']);
        $this->assertSame('Advertisement activated', $trail[3]['activity']);
    }

    /** The whole point: browsing must not pad the file. */
    public function test_browsing_activity_is_excluded(): void
    {
        $user = User::factory()->create();

        $this->event($user, ActivityType::PaymentApproved, '2026-03-02 11:30');

        foreach ([ActivityType::PropertyViewed, ActivityType::GalleryOpened, ActivityType::SearchPerformed] as $noise) {
            $this->event($user, $noise, '2026-03-02 12:00');
        }

        $trail = $this->bundle($user)['service_trail'];

        $this->assertCount(1, $trail);
        $this->assertSame('Payment approved', $trail[0]['activity']);
    }

    public function test_each_step_carries_its_audit_context(): void
    {
        $user = User::factory()->create();

        $this->event($user, ActivityType::PaymentApproved, '2026-03-02 11:30', [
            'subject_reference' => 'VTN-7QK2M4XP',
        ]);

        $step = $this->bundle($user)['service_trail'][0];

        $this->assertSame('73.12.44.184', $step['ip_address']);
        $this->assertSame('Orlando, FL, US', $step['approx_location']);
        $this->assertSame('mobile', $step['device']);
        $this->assertSame('Safari', $step['browser']);
        $this->assertSame('SES-TRAIL1', $step['session_id']);
        $this->assertSame('VTN-7QK2M4XP', $step['subject']);
        $this->assertSame('completed', $step['result']);
    }

    /** Another member's activity must never appear in this member's file. */
    public function test_another_members_activity_is_not_included(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        $this->event($user, ActivityType::PaymentApproved, '2026-03-02 11:30');
        $this->event($other, ActivityType::PaymentApproved, '2026-03-02 11:31');

        $this->assertCount(1, $this->bundle($user)['service_trail']);
    }

    public function test_events_outside_the_window_are_excluded(): void
    {
        $user = User::factory()->create();

        $this->event($user, ActivityType::PaymentApproved, '2025-06-01 10:00');
        $this->event($user, ActivityType::PaymentApproved, '2026-06-01 10:00');

        $this->assertCount(1, $this->bundle($user)['service_trail']);
    }

    /** A declined attempt is part of the story, not something to hide. */
    public function test_a_declined_payment_appears_in_the_trail(): void
    {
        $user = User::factory()->create();

        $this->event($user, ActivityType::PaymentDeclined, '2026-03-02 11:00', ['result' => 'failed']);
        $this->event($user, ActivityType::PaymentApproved, '2026-03-02 11:05');

        $trail = $this->bundle($user)['service_trail'];

        $this->assertCount(2, $trail);
        $this->assertSame('failed', $trail[0]['result']);
    }

    public function test_an_empty_trail_is_an_empty_array_not_a_missing_key(): void
    {
        $bundle = $this->bundle(User::factory()->create());

        $this->assertArrayHasKey('service_trail', $bundle);
        $this->assertSame([], $bundle['service_trail']);
    }
}

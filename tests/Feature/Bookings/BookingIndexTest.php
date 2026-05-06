<?php

namespace Tests\Feature\Bookings;

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\TermsAcceptance;
use App\Models\User;
use App\Services\Legal\LegalDocumentRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(LegalDocumentRegistry::class)->materialiseAll();
    }

    public function test_unauthenticated_user_redirected_to_login(): void
    {
        $this->get(route('bookings.index'))->assertRedirect(route('login'));
    }

    public function test_lists_only_signed_in_users_bookings(): void
    {
        $me = $this->makeTraveler();
        $other = $this->makeTraveler();

        Booking::factory()->create(['traveler_id' => $me->id, 'confirmation_code' => 'VYT-MINE001']);
        Booking::factory()->create(['traveler_id' => $other->id, 'confirmation_code' => 'VYT-THEIR1']);

        $resp = $this->actingAs($me)->get(route('bookings.index'));

        $resp->assertOk();
        $resp->assertSee('VYT-MINE001');
        $resp->assertDontSee('VYT-THEIR1');
    }

    public function test_renders_empty_state_when_no_bookings(): void
    {
        $traveler = $this->makeTraveler();

        $resp = $this->actingAs($traveler)->get(route('bookings.index'));

        $resp->assertOk();
        $resp->assertSee('No bookings yet');
        $resp->assertSee(route('properties.index'));
    }

    public function test_orders_newest_check_in_first(): void
    {
        $traveler = $this->makeTraveler();

        $oldest = Booking::factory()->create([
            'traveler_id'    => $traveler->id,
            'check_in_date'  => now()->subYear(),
            'check_out_date' => now()->subYear()->addDays(3),
            'confirmation_code' => 'VYT-OLDEST',
        ]);
        $newest = Booking::factory()->create([
            'traveler_id'    => $traveler->id,
            'check_in_date'  => now()->addMonths(2),
            'check_out_date' => now()->addMonths(2)->addDays(3),
            'confirmation_code' => 'VYT-NEWEST',
        ]);

        $body = $this->actingAs($traveler)->get(route('bookings.index'))->getContent();

        // Newest should appear before oldest in the rendered HTML.
        $newestPos = strpos($body, 'VYT-NEWEST');
        $oldestPos = strpos($body, 'VYT-OLDEST');
        $this->assertNotFalse($newestPos);
        $this->assertNotFalse($oldestPos);
        $this->assertLessThan($oldestPos, $newestPos);
    }

    public function test_status_pills_render_per_booking_status(): void
    {
        $traveler = $this->makeTraveler();
        Booking::factory()->create([
            'traveler_id' => $traveler->id,
            'status'      => 'confirmed',
            'confirmation_code' => 'VYT-CONF01',
        ]);
        Booking::factory()->create([
            'traveler_id' => $traveler->id,
            'status'      => 'cancelled',
            'confirmation_code' => 'VYT-CANC01',
        ]);

        $body = $this->actingAs($traveler)->get(route('bookings.index'))->getContent();

        $this->assertStringContainsString('confirmed', $body);
        $this->assertStringContainsString('cancelled', $body);
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

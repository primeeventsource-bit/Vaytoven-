<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\MemberEnquiry;
use App\Models\TermsAcceptance;
use App\Models\User;
use App\Services\Legal\LegalDocumentRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Materialise current legal versions so terms.current middleware is happy
        // when we accept them on test users.
        app(LegalDocumentRegistry::class)->materialiseAll();
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_admin_sees_operations_dashboard_with_summary_tiles(): void
    {
        $admin = $this->makeUser(UserRole::Admin);

        $resp = $this->actingAs($admin)->get('/dashboard');

        $resp->assertOk();
        $resp->assertSee('Operations dashboard');
        $resp->assertSee('New enquiries');
        $resp->assertSee('Open tickets');
        $resp->assertSee('Open disputes');
        $resp->assertSee('Help articles');
        $resp->assertSee('Legal versions in force');
    }

    public function test_super_admin_also_sees_admin_dashboard(): void
    {
        $superAdmin = $this->makeUser(UserRole::SuperAdmin);

        $this->actingAs($superAdmin)->get('/dashboard')
            ->assertOk()
            ->assertSee('Operations dashboard');
    }

    public function test_traveler_sees_user_dashboard_not_admin_view(): void
    {
        $traveler = $this->makeUser(UserRole::Traveler);

        $resp = $this->actingAs($traveler)->get('/dashboard');

        $resp->assertOk();
        $resp->assertSee('Welcome back, '.$traveler->name);
        $resp->assertSee('My bookings');
        $resp->assertDontSee('Operations dashboard');
        $resp->assertDontSee('Open disputes');
    }

    public function test_user_dashboard_only_lists_own_bookings(): void
    {
        $me = $this->makeUser(UserRole::Traveler);
        $other = $this->makeUser(UserRole::Traveler);

        Booking::factory()->create(['traveler_id' => $me->id, 'confirmation_code' => 'VYT-MINE01']);
        Booking::factory()->create(['traveler_id' => $other->id, 'confirmation_code' => 'VYT-THEIR1']);

        $resp = $this->actingAs($me)->get('/dashboard');

        $resp->assertOk();
        $resp->assertSee('VYT-MINE01');
        $resp->assertDontSee('VYT-THEIR1');
    }

    public function test_admin_dashboard_shows_enquiry_reference_and_count(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        MemberEnquiry::factory()->count(2)->create();

        $resp = $this->actingAs($admin)->get('/dashboard');

        $resp->assertOk();
        $resp->assertSee('Recent member enquiries');
        // Reference must surface so ops can quote it back to the prospect.
        $resp->assertSee(MemberEnquiry::first()->reference);
    }

    public function test_admin_dashboard_renders_with_zero_data(): void
    {
        // Empty DB should not blow up — every section guards for empties.
        $admin = $this->makeUser(UserRole::Admin);

        $resp = $this->actingAs($admin)->get('/dashboard');

        $resp->assertOk();
        $resp->assertSee('No bookings yet.');
        $resp->assertSee('No enquiries yet');
    }

    public function test_dashboard_honours_terms_current_middleware(): void
    {
        // Traveler with NO terms acceptance should be redirected to review-and-accept,
        // not see the dashboard. Validates the middleware is still wired after the
        // controller refactor.
        $traveler = User::factory()->create([
            'role' => UserRole::Traveler,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($traveler)
            ->get('/dashboard')
            ->assertRedirect(route('legal.review-and-accept'));
    }

    private function makeUser(UserRole $role): User
    {
        $user = User::factory()->create([
            'role' => $role,
            'email_verified_at' => now(),
        ]);
        // Accept current required terms so terms.current middleware lets us through.
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

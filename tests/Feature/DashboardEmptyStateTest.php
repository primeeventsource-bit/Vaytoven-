<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The host and member dashboards render a listing-views map. Its partial reads
 * $mapboxToken unconditionally, so the analytics payload has to supply it on
 * the no-listings branch too — otherwise every brand-new host and member gets
 * a 500 on their first visit, which is exactly the audience least able to
 * report it.
 */
class DashboardEmptyStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_host_with_no_listings_can_load_the_dashboard(): void
    {
        $host = User::factory()->create(['role' => UserRole::Host]);

        $this->actingAs($host)->get('/dashboard')->assertOk();
    }

    public function test_member_with_no_listings_can_load_the_dashboard(): void
    {
        $member = User::factory()->create(['role' => UserRole::Member]);

        $this->actingAs($member)->get('/dashboard')->assertOk();
    }
}

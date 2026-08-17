<?php

namespace Tests\Feature\Admin;

use App\Models\Property;
use App\Models\PropertyView;
use App\Models\Role;
use App\Models\TrackingEvent;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Platform-wide listing activity for admins.
 *
 * Hosts and members each see their own listings. This is the same panel across
 * every owner's advertisement, plus the click stream — which previously only
 * ever surfaced as a bare "events in 24h" count that tells you the SDK is
 * alive and nothing else.
 */
class AdminActivityTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $roleKey = 'super_admin'): User
    {
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create(['must_change_password' => false]);
        $user->roles()->sync([Role::where('key', $roleKey)->firstOrFail()->id]);

        return $user;
    }

    private function viewedProperty(User $owner, string $title, int $views): Property
    {
        $property = Property::factory()->create(['host_id' => $owner->id, 'title' => $title]);

        for ($i = 0; $i < $views; $i++) {
            PropertyView::create([
                'property_id' => $property->id,
                'occurred_at' => now()->subDays(2),
                'city'        => 'Austin',
                'country'     => 'US',
                'latitude'    => 30.27,
                'longitude'   => -97.74,
            ]);
        }

        return $property;
    }

    public function test_an_admin_can_see_activity_across_every_owner(): void
    {
        $staff = $this->staff();

        $ownerA = User::factory()->create(['name' => 'Owner A']);
        $ownerB = User::factory()->create(['name' => 'Owner B']);

        $this->viewedProperty($ownerA, 'Cabin On The Ridge', 3);
        $this->viewedProperty($ownerB, 'Villa By The Sea', 2);

        $this->actingAs($staff)->get(route('admin.activity.index'))
            ->assertOk()
            ->assertSee('Cabin On The Ridge')
            ->assertSee('Villa By The Sea');
    }

    public function test_it_can_be_filtered_to_one_owner(): void
    {
        $staff  = $this->staff();
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();

        $this->viewedProperty($ownerA, 'Cabin On The Ridge', 1);
        $this->viewedProperty($ownerB, 'Villa By The Sea', 1);

        $this->actingAs($staff)
            ->get(route('admin.activity.index', ['owner' => $ownerA->id]))
            ->assertOk()
            ->assertSee('Cabin On The Ridge')
            ->assertDontSee('Villa By The Sea');
    }

    public function test_it_shows_the_click_breakdown_by_cta(): void
    {
        $staff = $this->staff();

        foreach (['home_pricing_gold', 'home_pricing_gold', 'topnav_members'] as $cta) {
            TrackingEvent::create([
                'event_uuid'  => (string) Str::uuid(),
                'event_type'  => 'cta_click',
                'surface'     => 'web',
                'metadata'    => ['cta' => $cta, 'audience' => 'member'],
                'occurred_at' => now()->subDay(),
            ]);
        }

        $this->actingAs($staff)->get(route('admin.activity.index'))
            ->assertOk()
            ->assertSee('home_pricing_gold')
            ->assertSee('topnav_members');
    }

    /** An empty platform must render, not 500. */
    public function test_it_renders_with_no_listings_and_no_clicks(): void
    {
        $this->actingAs($this->staff())
            ->get(route('admin.activity.index'))
            ->assertOk()
            ->assertSee('No click events recorded');
    }

    public function test_a_role_without_reporting_access_is_refused(): void
    {
        $this->actingAs($this->staff('host'))
            ->get(route('admin.activity.index'))
            ->assertForbidden();
    }

    public function test_a_visitor_is_sent_to_login(): void
    {
        $this->get(route('admin.activity.index'))->assertRedirect(route('login'));
    }

    /**
     * A secret Mapbox token must never reach the browser: sk.* keys carry
     * scopes like create-tokens, and this page renders a map.
     */
    public function test_a_secret_mapbox_token_is_not_exposed(): void
    {
        config(['services.mapbox.token' => 'sk.secret-token-value']);

        $html = $this->actingAs($this->staff())
            ->get(route('admin.activity.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('sk.secret-token-value', $html);
    }
}

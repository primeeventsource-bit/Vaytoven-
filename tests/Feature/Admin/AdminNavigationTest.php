<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use App\Enums\UserRole;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Staff navigation.
 *
 * Every admin screen existed and none of them was linked from anywhere. The
 * only way to reach "create a user" was to know the URL, which is why it read
 * as a missing feature rather than a missing link.
 */
class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $roleKey, UserRole $column): User
    {
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create([
            'role' => $column,
            'must_change_password' => false,
        ]);
        $user->roles()->sync([Role::where('key', $roleKey)->firstOrFail()->id]);

        return $user;
    }

    public function test_an_admin_is_offered_users_and_a_create_link(): void
    {
        $response = $this->actingAs($this->staff('super_admin', UserRole::SuperAdmin))
            ->get(route('admin.users.index'))
            ->assertOk();

        $response->assertSee(route('admin.users.index'), false);
        $response->assertSee(route('admin.users.create'), false);
        $response->assertSee('New user');
    }

    public function test_the_current_section_is_marked(): void
    {
        $this->actingAs($this->staff('super_admin', UserRole::SuperAdmin))
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('aria-current="page"', false);
    }

    public function test_navigation_is_grouped_into_dropdown_sections_with_a_mobile_menu(): void
    {
        $body = $this->actingAs($this->staff('super_admin', UserRole::SuperAdmin))
            ->get('/dashboard')->assertOk()->getContent();

        foreach (['Members', 'Advertisements', 'Contracts', 'Payments', 'Activity &amp; Tracking', 'Marketing', 'Administration'] as $section) {
            $this->assertStringContainsString("<summary>{$section}</summary>", $body, "Missing section {$section}");
        }

        $this->assertStringContainsString('data-admin-nav-toggle', $body);
        $this->assertStringContainsString('ADMIN MENU', $body);
        // Every page that existed is still reachable from the menu.
        foreach (['admin.users.index', 'admin.roles.index', 'admin.properties.index', 'admin.media.index', 'admin.offers.index',
                  'admin.member-services.index', 'admin.contracts.index', 'admin.inbox.index', 'admin.activity.index',
                  'admin.activity.log', 'admin.activity.map', 'admin.settings.index', 'admin.demo-data.index',
                  'admin.hosting.service-fees', 'admin.fulfillment.index', 'staff-guide'] as $route) {
            $this->assertStringContainsString(route($route), $body, "{$route} is no longer reachable from the menu");
        }
    }

    /** Inside a filtered view, its own section and item are lit — and only one item. */
    public function test_the_current_section_and_item_are_marked_for_filtered_views(): void
    {
        $staff = $this->staff('super_admin', UserRole::SuperAdmin);

        $body = $this->actingAs($staff)->get(route('admin.activity.log', ['group' => 'members']))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<details class="vyt-anav-sec is-current"[^>]*>\s*<summary>Activity &amp; Tracking</summary>#', $body);
        $nav = substr($body, strpos($body, '<nav class="vyt-anav"'));
        $nav = substr($nav, 0, strpos($nav, '</nav>'));
        $this->assertSame(1, substr_count($nav, 'class="is-current"'), 'More than one menu item marked current');
        $this->assertSame(1, substr_count($nav, 'vyt-anav-sec is-current'), 'More than one section marked current');
        $this->assertMatchesRegularExpression('#class="is-current"\s+aria-current="page"\s*>Member activity<#', $body);

        $body = $this->actingAs($staff)->get(route('admin.fulfillment.index', ['view' => 'acceptance']))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<details class="vyt-anav-sec is-current"[^>]*>\s*<summary>Members</summary>#', $body);
        $this->assertMatchesRegularExpression('#aria-current="page"\s*>Advertisement acceptance<#', $body);

        // A detail page with no menu item still lights its section.
        $member = User::factory()->create(['role' => UserRole::Member]);
        $body = $this->actingAs($staff)->get(route('admin.members.show', $member))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<details class="vyt-anav-sec is-current"[^>]*>\s*<summary>Members</summary>#', $body);
    }

    /** A host shares this layout and must not see staff sections. */
    public function test_a_host_sees_no_admin_navigation(): void
    {
        $host = User::factory()->create([
            'role' => UserRole::Host,
            'must_change_password' => false,
        ]);

        $body = $this->actingAs($host)->get('/dashboard')->assertOk()->getContent();

        // Asserted against the rendered element, not the class name. The
        // stylesheet ships on every dashboard page whether the nav renders or
        // not, so matching "vyt-adminnav" alone would pass on the CSS and
        // prove nothing.
        $this->assertStringNotContainsString('<nav class="vyt-anav"', $body);
        $this->assertStringNotContainsString(route('admin.users.create'), $body);
        $this->assertStringNotContainsString(route('admin.users.index'), $body);
    }

    /**
     * The assertion that makes the navigation worth having.
     *
     * A tab that appears and then 403s is worse than no tab: it reads as a
     * broken product rather than a permission boundary. The nav gate and the
     * route middleware must be the same key, so every link a person is shown
     * has to actually open for them.
     */
    public function test_every_link_shown_actually_opens_for_the_viewer(): void
    {
        foreach (['support', 'member_specialist', 'super_admin'] as $roleKey) {
            $this->seed(RbacSeeder::class);
            $role = Role::where('key', $roleKey)->first();

            if (! $role) {
                continue;   // the seeder's role set is allowed to change
            }

            $user = User::factory()->create([
                'role' => $roleKey === 'super_admin' ? UserRole::SuperAdmin : UserRole::Admin,
                'must_change_password' => false,
            ]);
            $user->roles()->sync([$role->id]);

            $body = $this->actingAs($user)->get('/dashboard')->getContent();

            preg_match_all('#href="([^"]*/admin/[^"]*)"#', $body, $matches);
            $links = array_unique($matches[1]);

            $this->assertNotEmpty($links, "{$roleKey} was shown no admin links at all");

            foreach ($links as $link) {
                $status = $this->actingAs($user)->get(html_entity_decode($link))->getStatusCode();

                $this->assertNotSame(
                    403, $status,
                    "{$roleKey} is shown {$link} in the nav but is forbidden from opening it"
                );
                $this->assertLessThan(500, $status, "{$roleKey}: {$link} errored ({$status})");
                $this->assertNotSame(404, $status, "{$roleKey}: {$link} is a dead link");
            }
        }
    }
}

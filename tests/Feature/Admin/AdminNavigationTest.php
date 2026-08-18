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
        $this->assertStringNotContainsString('<nav class="vyt-adminnav"', $body);
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
                $status = $this->actingAs($user)->get($link)->getStatusCode();

                $this->assertNotSame(
                    403, $status,
                    "{$roleKey} is shown {$link} in the nav but is forbidden from opening it"
                );
            }
        }
    }
}

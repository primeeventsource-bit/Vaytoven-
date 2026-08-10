<?php

namespace Tests\Feature\Rbac;

use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Structural guard for the admin surface.
 *
 * The admin route group is authenticated but NOT role-gated at the group
 * level — every route carries its own `permission:` middleware. That design
 * is only safe if the invariant actually holds, so this test enforces it:
 * a new admin route added without a permission would otherwise be reachable
 * by any signed-in traveler, and nothing else would catch it.
 */
class RbacRouteCoverageTest extends TestCase
{
    /** @return list<string> */
    private function permissionsOn(\Illuminate\Routing\Route $route): array
    {
        $keys = [];

        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'permission:')) {
                foreach (explode(',', substr($middleware, strlen('permission:'))) as $key) {
                    $keys[] = trim($key);
                }
            }
        }

        return $keys;
    }

    public function test_every_admin_web_route_declares_a_permission(): void
    {
        $unguarded = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with((string) $route->getName(), 'admin.')) {
                continue;
            }

            if ($this->permissionsOn($route) === []) {
                $unguarded[] = $route->methods()[0].' /'.$route->uri();
            }
        }

        $this->assertSame([], $unguarded,
            'Admin routes without a permission: middleware are reachable by any authenticated user.');
    }

    public function test_every_admin_api_route_declares_a_permission(): void
    {
        $unguarded = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/admin/')) {
                continue;
            }

            if ($this->permissionsOn($route) === []) {
                $unguarded[] = $route->methods()[0].' /'.$route->uri();
            }
        }

        $this->assertSame([], $unguarded);
    }

    public function test_every_declared_permission_exists_in_the_catalog(): void
    {
        $unknown = [];

        foreach (Route::getRoutes() as $route) {
            foreach ($this->permissionsOn($route) as $key) {
                if (! PermissionCatalog::exists($key)) {
                    $unknown[$key] = '/'.$route->uri();
                }
            }
        }

        $this->assertSame([], $unknown,
            'Routes reference permission keys that PermissionCatalog does not define; they can never be granted.');
    }
}

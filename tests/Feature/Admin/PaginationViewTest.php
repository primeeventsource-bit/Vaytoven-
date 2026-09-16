<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression: the framework's Tailwind pagination rendered its arrow icons at
 * the full width of the page, because these pages do not load Tailwind.
 */
class PaginationViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_paginated_admin_pages_use_the_self_styled_pager(): void
    {
        $this->seed(RbacSeeder::class);
        $staff = User::factory()->create(['role' => UserRole::SuperAdmin, 'must_change_password' => false]);
        $staff->roles()->sync([Role::where('key', 'super_admin')->firstOrFail()->id]);
        User::factory()->count(30)->create(['role' => UserRole::Member]);

        $body = $this->actingAs($staff)->get(route('admin.fulfillment.index'))->assertOk()->getContent();

        $start = strpos($body, 'class="vy-pager"');
        $this->assertNotFalse($start, 'The self-styled pager did not render.');
        $pager = substr($body, $start, strpos($body, '</nav>', $start) - $start);

        $this->assertStringContainsString('Next ›', $pager);
        $this->assertStringContainsString('Showing 1–25 of 30', $pager);
        $this->assertStringNotContainsString('<svg', $pager, 'Pager still renders SVG arrows.');
    }
}

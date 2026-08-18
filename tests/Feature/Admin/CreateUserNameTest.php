<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Staff capture a first and last name separately.
 *
 * There was no test over admin user creation at all, so the change from one
 * combined "Full name" field to two was unverified by a green suite. These
 * cover it.
 *
 * The reason for two fields: a listing shows its owner as "John S.", which
 * needs the surname as its own value. Splitting a combined name afterwards is
 * a guess — it gets "Ada Marie Lovelace" right by luck and "van der Berg"
 * wrong. Asking once, correctly, stops the display name being a guess.
 */
class CreateUserNameTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'must_change_password' => false,
        ]);
        $user->roles()->sync([Role::where('key', 'super_admin')->firstOrFail()->id]);

        return $user;
    }

    /** @return array<string, string> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name'            => 'John',
            'last_name'             => 'Smith',
            'email'                 => 'john.smith@example.com',
            'password'              => 'Str0ng-Passw0rd!',
            'password_confirmation' => 'Str0ng-Passw0rd!',
            'role'                  => UserRole::Member->value,
        ], $overrides);
    }

    public function test_the_form_asks_for_two_names_not_one(): void
    {
        $response = $this->actingAs($this->staff())
            ->get(route('admin.users.create'))
            ->assertOk();

        $response->assertSee('name="first_name"', false);
        $response->assertSee('name="last_name"', false);
        $response->assertDontSee('name="name"', false);
    }

    public function test_creating_a_user_stores_both_parts(): void
    {
        $this->actingAs($this->staff())
            ->post(route('admin.users.store'), $this->payload())
            ->assertRedirect();

        $user = User::where('email', 'john.smith@example.com')->sole();

        $this->assertSame('John', $user->first_name);
        $this->assertSame('Smith', $user->last_name);
    }

    /**
     * `name` is composed rather than asked for. Plenty of the app still reads
     * it, and letting it drift from the parts would give one person two
     * different names depending on the screen.
     */
    public function test_the_combined_name_is_composed_from_the_parts(): void
    {
        $this->actingAs($this->staff())
            ->post(route('admin.users.store'), $this->payload());

        $this->assertSame('John Smith', User::where('email', 'john.smith@example.com')->sole()->name);
    }

    public function test_the_new_user_displays_publicly_as_first_name_and_initial(): void
    {
        $this->actingAs($this->staff())
            ->post(route('admin.users.store'), $this->payload());

        $this->assertSame('John S.', User::where('email', 'john.smith@example.com')->sole()->publicDisplayName());
    }

    public function test_a_first_name_is_required(): void
    {
        $this->actingAs($this->staff())
            ->post(route('admin.users.store'), $this->payload(['first_name' => '']))
            ->assertSessionHasErrors('first_name');

        $this->assertSame(0, User::where('email', 'john.smith@example.com')->count());
    }

    /** Mononyms exist. Refusing the account is worse than showing "Prince". */
    public function test_a_last_name_is_optional(): void
    {
        $this->actingAs($this->staff())
            ->post(route('admin.users.store'), $this->payload(['first_name' => 'Prince', 'last_name' => '']))
            ->assertRedirect();

        $user = User::where('email', 'john.smith@example.com')->sole();

        $this->assertSame('Prince', $user->name);
        $this->assertSame('Prince', $user->publicDisplayName());
    }

    // --- editing ------------------------------------------------------------------

    public function test_editing_updates_both_parts_and_the_composed_name(): void
    {
        $user = User::factory()->create([
            'first_name' => 'John', 'last_name' => 'Smith', 'name' => 'John Smith',
            'role' => UserRole::Member,
        ]);

        $this->actingAs($this->staff())
            ->patch(route('admin.users.update', $user), [
                'first_name' => 'Dana',
                'last_name'  => 'Whitfield',
                'email'      => $user->email,
                'role'       => UserRole::Member->value,
            ])
            ->assertRedirect();

        $user->refresh();

        $this->assertSame('Dana', $user->first_name);
        $this->assertSame('Whitfield', $user->last_name);
        $this->assertSame('Dana Whitfield', $user->name);
        $this->assertSame('Dana W.', $user->publicDisplayName());
    }

    /** The edit form must show the existing parts, not one merged value. */
    public function test_the_edit_form_prefills_both_parts(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Dana', 'last_name' => 'Whitfield', 'name' => 'Dana Whitfield',
        ]);

        $response = $this->actingAs($this->staff())
            ->get(route('admin.users.edit', $user))
            ->assertOk();

        $response->assertSee('value="Dana"', false);
        $response->assertSee('value="Whitfield"', false);
    }
}

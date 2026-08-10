<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin console has always told operators that deactivating an account
 * stops the user logging in. These tests are what make that true.
 */
class DeactivatedUserLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_log_in(): void
    {
        $user = User::factory()->create(['password' => 'Correct-Horse-1!']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'Correct-Horse-1!',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_deactivated_user_cannot_log_in(): void
    {
        $user = User::factory()->create([
            'password' => 'Correct-Horse-1!',
            'deactivated_at' => now(),
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'Correct-Horse-1!',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_deactivated_user_with_a_wrong_password_still_reports_a_credential_failure(): void
    {
        $user = User::factory()->create([
            'password' => 'Correct-Horse-1!',
            'deactivated_at' => now(),
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_reactivated_user_can_log_in_again(): void
    {
        $user = User::factory()->create([
            'password' => 'Correct-Horse-1!',
            'deactivated_at' => now(),
        ]);

        $user->forceFill(['deactivated_at' => null])->save();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'Correct-Horse-1!',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }
}

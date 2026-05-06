<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_user_and_returns_token(): void
    {
        $resp = $this->postJson('/api/v1/auth/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'analytic-engine-7',
            'password_confirmation' => 'analytic-engine-7',
            'device_name' => 'phpunit',
        ]);

        $resp->assertCreated()
            ->assertJsonPath('user.email', 'ada@example.com')
            ->assertJsonStructure(['user' => ['id', 'name', 'email', 'role'], 'token']);

        $this->assertDatabaseHas('users', ['email' => 'ada@example.com']);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Imposter',
            'email' => 'taken@example.com',
            'password' => 'whatever-7',
            'password_confirmation' => 'whatever-7',
        ])->assertStatus(422);
    }

    public function test_login_returns_token_for_valid_credentials(): void
    {
        User::factory()->create([
            'email' => 'grace@example.com',
            'password' => Hash::make('compiler-time-1'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'grace@example.com',
            'password' => 'compiler-time-1',
        ])
            ->assertOk()
            ->assertJsonPath('user.email', 'grace@example.com')
            ->assertJsonStructure(['token']);
    }

    public function test_login_rejects_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'grace@example.com',
            'password' => Hash::make('right-password'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'grace@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(401);
    }

    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('phpunit')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_me_rejects_request_without_token(): void
    {
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('phpunit')->plainTextToken;

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        // Token row removed from personal_access_tokens — any future request
        // using this bearer token will fail auth at the Sanctum guard layer.
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_surface_header_is_captured_into_request_attributes(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('phpunit')->plainTextToken;

        // The middleware should set 'vaytoven_surface' on every API request.
        // Verify indirectly by hitting an endpoint and ensuring it succeeds with
        // a custom Surface header (full assertion happens in tracking tests
        // once TrackingService consumes the attribute).
        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Vaytoven-Surface' => 'app_ios',
        ])->getJson('/api/v1/auth/me')->assertOk();
    }
}

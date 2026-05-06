<?php

namespace Tests\Feature\Tracking;

use App\Models\LoginSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * End-to-end test that authentication events ripple all the way through
 * to a login_sessions row, with the right auth_event + geo (NoOp returns
 * empty geo in tests, so country stays null).
 */
class TrackAuthEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_login_via_breeze_creates_login_session(): void
    {
        $user = User::factory()->create([
            'email' => 'wired@test.local',
            'password' => Hash::make('correct-password-7'),
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'correct-password-7',
        ])->assertRedirect();

        $this->assertSame(1, LoginSession::where('user_id', $user->id)->count());

        $session = LoginSession::first();
        $this->assertSame('login', $session->auth_event);
        $this->assertSame($user->id, $session->user_id);
    }

    public function test_failed_login_for_known_user_creates_failed_login_session(): void
    {
        User::factory()->create([
            'email' => 'wrongpw@test.local',
            'password' => Hash::make('right-password'),
        ]);

        $this->post('/login', [
            'email' => 'wrongpw@test.local',
            'password' => 'wrong-password',
        ])->assertRedirect();

        $this->assertSame(1, LoginSession::where('auth_event', 'failed')->count());
    }

    public function test_failed_login_for_unknown_user_does_not_leak_via_login_sessions(): void
    {
        $this->post('/login', [
            'email' => 'no-such-user@test.local',
            'password' => 'whatever',
        ])->assertRedirect();

        // No row should exist — protects against email enumeration via the
        // user_id NOT NULL constraint indirectly leaking a "this email exists" signal.
        $this->assertSame(0, LoginSession::count());
    }

    public function test_logout_creates_logout_login_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect();

        $this->assertSame(1, LoginSession::where('auth_event', 'logout')->count());
    }
}

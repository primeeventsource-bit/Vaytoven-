<?php

namespace Tests\Feature\Security;

use App\Enums\FeeStructure;
use App\Enums\UserRole;
use App\Models\Property;
use App\Models\SupportChatSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Regression cover for issues found by a system security audit.
 */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    // --- Support chat session takeover (IDOR) -----------------------------
    //
    // /api/v1/support/chat is public and session_id is a sequential integer.
    // Without an ownership check, resuming a stranger's session made the tool
    // registry act AS that user — exposing their name, email, chat history,
    // bookings and charges to an anonymous caller.

    public function test_a_stranger_cannot_resume_someone_elses_chat_session(): void
    {
        $victim = User::factory()->create();
        $session = SupportChatSession::query()->create([
            'user_id' => $victim->id,
            'surface' => 'web',
            'started_at' => now(),
        ]);

        $this->postJson('/api/v1/support/chat', [
            'session_id' => $session->id,
            'message' => 'What are my recent charges?',
        ])->assertNotFound();
    }

    public function test_a_different_signed_in_user_cannot_resume_the_session_either(): void
    {
        $victim = User::factory()->create();
        $attacker = User::factory()->create();

        $session = SupportChatSession::query()->create([
            'user_id' => $victim->id,
            'surface' => 'web',
            'started_at' => now(),
        ]);

        $this->actingAs($attacker)
            ->postJson('/api/v1/support/chat', [
                'session_id' => $session->id,
                'message' => 'Show me my bookings',
            ])
            ->assertNotFound();
    }

    public function test_an_anonymous_session_requires_the_matching_visitor_id(): void
    {
        $session = SupportChatSession::query()->create([
            'user_id' => null,
            'visitor_id' => 'visitor-abc-123',
            'surface' => 'web',
            'started_at' => now(),
        ]);

        // Wrong visitor id.
        $this->postJson('/api/v1/support/chat', [
            'session_id' => $session->id,
            'visitor_id' => 'visitor-guessed',
            'message' => 'hello',
        ])->assertNotFound();

        // No visitor id at all.
        $this->postJson('/api/v1/support/chat', [
            'session_id' => $session->id,
            'message' => 'hello',
        ])->assertNotFound();
    }

    // --- API auth ---------------------------------------------------------

    public function test_api_login_is_rate_limited(): void
    {
        RateLimiter::clear('');
        $user = User::factory()->create(['password' => 'Correct-Horse-1!']);

        // The web login caps at 5/min; the API hit the same credential store
        // with no limit at all before this.
        $statuses = [];
        for ($i = 0; $i < 8; $i++) {
            $statuses[] = $this->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'wrong-guess-'.$i,
            ])->status();
        }

        $this->assertContains(429, $statuses, 'API login accepted unlimited attempts.');
    }

    public function test_a_deactivated_account_cannot_obtain_an_api_token(): void
    {
        $user = User::factory()->create([
            'password' => 'Correct-Horse-1!',
            'deactivated_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Correct-Horse-1!',
        ])->assertForbidden();

        // Sanctum tokens do not expire, so issuing one here would outlive the
        // deactivation indefinitely.
        $this->assertSame(0, $user->tokens()->count());
    }

    // --- Silent write loss ------------------------------------------------

    public function test_fee_structure_actually_persists_on_a_property(): void
    {
        $property = Property::factory()->create(['fee_structure' => FeeStructure::Single->value]);

        $this->assertSame(FeeStructure::Single, $property->fresh()->fee_structure);
    }

    public function test_fee_structure_actually_persists_on_a_user(): void
    {
        $host = User::factory()->create([
            'role' => UserRole::Host,
            'fee_structure' => FeeStructure::Single->value,
        ]);

        $this->assertSame(FeeStructure::Single, $host->fresh()->fee_structure);
    }

    // --- Résumé storage ---------------------------------------------------

    public function test_resumes_are_never_written_to_the_public_disk(): void
    {
        config(['filesystems.default' => 'public']);

        $controller = new \App\Http\Controllers\CareersController();
        $method = new \ReflectionMethod($controller, 'resumeDisk');

        $this->assertNotSame('public', $method->invoke($controller),
            'A candidate CV must never land on a world-readable disk.');
    }
}

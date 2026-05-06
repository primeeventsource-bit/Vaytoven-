<?php

namespace Tests\Feature\Legal;

use App\Models\TermsAcceptance;
use App\Models\TermsVersion;
use App\Models\User;
use App\Services\Legal\LegalDocumentRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The /dashboard route is protected by the terms.current middleware. We use
 * it as a representative authenticated route to exercise the middleware.
 */
class EnsureCurrentTermsAcceptedTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_passes_through(): void
    {
        // Without a user, the middleware short-circuits to next() so the
        // route's normal auth middleware can handle the redirect to /login.
        // We just want to confirm we don't try to redirect to legal.
        app(LegalDocumentRegistry::class)->materialiseAll();
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_user_with_full_acceptance_can_reach_dashboard(): void
    {
        app(LegalDocumentRegistry::class)->materialiseAll();

        $user = User::factory()->create(['email_verified_at' => now()]);
        foreach (app(LegalDocumentRegistry::class)->registrationRequired() as $version) {
            TermsAcceptance::create([
                'user_id' => $user->id,
                'terms_version_id' => $version->id,
                'accepted_at' => now(),
            ]);
        }

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_user_with_no_acceptances_is_redirected_to_review(): void
    {
        app(LegalDocumentRegistry::class)->materialiseAll();

        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('legal.review-and-accept'));
    }

    public function test_user_with_stale_acceptance_is_redirected_to_review(): void
    {
        $registry = app(LegalDocumentRegistry::class);
        $registry->materialiseAll();

        $user = User::factory()->create(['email_verified_at' => now()]);
        // Accept an *old* TOS row that gets superseded by counsel's revision.
        $oldTos = TermsVersion::create([
            'kind' => 'tos',
            'version_label' => 'v0',
            'content_hash' => str_repeat('a', 64),
            'content_url' => '/legal/tos',
            'effective_at' => now()->subYear(),
            'superseded_at' => now()->subDay(),
            'created_at' => now()->subYear(),
        ]);
        TermsAcceptance::create([
            'user_id' => $user->id,
            'terms_version_id' => $oldTos->id,
            'accepted_at' => now()->subYear(),
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('legal.review-and-accept'));
    }

    public function test_legal_pages_are_allowlisted_for_stale_users(): void
    {
        app(LegalDocumentRegistry::class)->materialiseAll();

        $user = User::factory()->create(['email_verified_at' => now()]);

        // Stale user can still read the policies and access review-and-accept.
        $this->actingAs($user)->get('/legal/tos')->assertOk();
        $this->actingAs($user)->get('/legal/review-and-accept')->assertOk();
    }

    public function test_review_and_accept_post_records_missing_acceptances(): void
    {
        $registry = app(LegalDocumentRegistry::class);
        $registry->materialiseAll();

        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->post('/legal/review-and-accept', ['accept' => '1'])
            ->assertRedirect();

        $this->assertSame(2, TermsAcceptance::where('user_id', $user->id)->count());

        // After accepting, the dashboard is reachable again.
        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_review_and_accept_rejects_without_consent(): void
    {
        app(LegalDocumentRegistry::class)->materialiseAll();
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->post('/legal/review-and-accept', [])
            ->assertSessionHasErrors('accept');

        $this->assertSame(0, TermsAcceptance::where('user_id', $user->id)->count());
    }
}

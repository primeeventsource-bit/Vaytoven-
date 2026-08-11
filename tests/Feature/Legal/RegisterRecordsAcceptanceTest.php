<?php

namespace Tests\Feature\Legal;

use App\Models\TermsAcceptance;
use App\Models\User;
use App\Services\Legal\LegalDocumentRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterRecordsAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_form_lists_required_legal_documents(): void
    {
        app(LegalDocumentRegistry::class)->materialiseAll();

        $body = $this->get('/register')->assertOk()->getContent();

        $this->assertStringContainsString('accept_terms', $body);
        $this->assertStringContainsString('Tos', $body);
        $this->assertStringContainsString('Privacy', $body);
    }

    public function test_register_rejects_without_terms_acceptance(): void
    {
        app(LegalDocumentRegistry::class)->materialiseAll();

        $this->post('/register', [
            'name' => 'No Consent',
            'email' => 'no-consent@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            // accept_terms intentionally missing
        ])->assertSessionHasErrors('accept_terms');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('terms_acceptances', 0);
    }

    public function test_register_records_acceptance_for_each_required_version(): void
    {
        app(LegalDocumentRegistry::class)->materialiseAll();

        $this->post('/register', [
            'first_name' => 'Aware',
            'last_name' => 'User',
            'email' => 'aware@example.com',
            'phone' => '+1 555 010 2030',
            'password' => 'password',
            'password_confirmation' => 'password',
            'accept_terms' => '1',
        ])->assertRedirect();

        $user = User::where('email', 'aware@example.com')->sole();

        // Two required documents (TOS + Privacy) → two acceptance rows.
        $this->assertSame(2, TermsAcceptance::where('user_id', $user->id)->count());

        $kinds = TermsAcceptance::where('user_id', $user->id)
            ->with('termsVersion')
            ->get()
            ->map(fn ($a) => $a->termsVersion->kind)
            ->all();

        $this->assertEqualsCanonicalizing(['tos', 'privacy'], $kinds);
    }

    public function test_register_captures_ip_and_user_agent_with_acceptance(): void
    {
        app(LegalDocumentRegistry::class)->materialiseAll();

        $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.42',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 PhaseThirteenTest',
        ])->post('/register', [
            'first_name' => 'Logged',
            'last_name' => 'User',
            'email' => 'logged@example.com',
            'phone' => '+1 555 010 2030',
            'password' => 'password',
            'password_confirmation' => 'password',
            'accept_terms' => '1',
        ])->assertRedirect();

        $row = TermsAcceptance::first();
        $this->assertSame('203.0.113.42', $row->ip_address);
        $this->assertStringContainsString('PhaseThirteenTest', $row->user_agent);
    }
}

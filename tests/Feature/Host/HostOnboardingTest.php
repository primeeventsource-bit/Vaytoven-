<?php

namespace Tests\Feature\Host;

use App\Enums\PaymentProcessor;
use App\Enums\UserRole;
use App\Models\HostPayoutAccount;
use App\Models\TermsAcceptance;
use App\Models\User;
use App\Services\Legal\LegalDocumentRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Host payout enrollment — platform-managed since the NMI migration
 * (2026-07). No gateway-hosted KYC: enrollment creates the local record
 * and ops verifies out-of-band via the admin console.
 */
class HostOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(LegalDocumentRegistry::class)->materialiseAll();
    }

    public function test_unauthenticated_index_redirects_to_login(): void
    {
        $this->get(route('host.onboarding.index'))->assertRedirect(route('login'));
    }

    public function test_index_renders_call_to_enroll_when_no_account_yet(): void
    {
        $host = $this->makeHost();

        $resp = $this->actingAs($host)->get(route('host.onboarding.index'));

        $resp->assertOk();
        $resp->assertSee('Enroll for payouts');
    }

    public function test_index_shows_pending_status_for_existing_pending_account(): void
    {
        $host = $this->makeHost();
        HostPayoutAccount::create([
            'host_id'             => $host->id,
            'processor'           => PaymentProcessor::Nmi->value,
            'external_account_id' => "host:{$host->id}",
            'status'              => 'pending_kyc',
            'payouts_enabled'     => false,
            'charges_enabled'     => true,
        ]);

        $this->actingAs($host)
            ->get(route('host.onboarding.index'))
            ->assertOk()
            ->assertSee('Pending verification')
            ->assertSee('Enrollment received');
    }

    public function test_index_shows_verified_state_when_account_ready(): void
    {
        $host = $this->makeHost();
        HostPayoutAccount::create([
            'host_id'             => $host->id,
            'processor'           => PaymentProcessor::Nmi->value,
            'external_account_id' => "host:{$host->id}",
            'status'              => 'verified',
            'payouts_enabled'     => true,
            'charges_enabled'     => true,
        ]);

        $this->actingAs($host)
            ->get(route('host.onboarding.index'))
            ->assertOk()
            ->assertSee('Verified')
            ->assertSee('all set');
    }

    public function test_index_still_renders_legacy_stripe_account_rows(): void
    {
        // Hosts who enrolled pre-migration keep their status view.
        $host = $this->makeHost();
        HostPayoutAccount::create([
            'host_id'             => $host->id,
            'processor'           => PaymentProcessor::Stripe->value,
            'external_account_id' => 'acct_legacy_001',
            'status'              => 'verified',
            'payouts_enabled'     => true,
            'charges_enabled'     => true,
        ]);

        $this->actingAs($host)
            ->get(route('host.onboarding.index'))
            ->assertOk()
            ->assertSee('Verified');
    }

    public function test_start_creates_pending_payout_account_without_gateway_call(): void
    {
        Http::fake(); // any HTTP call would be recorded

        $host = $this->makeHost();

        $this->actingAs($host)
            ->post(route('host.onboarding.start'))
            ->assertRedirect(route('host.onboarding.index'))
            ->assertSessionHas('host_success');

        $account = HostPayoutAccount::sole();
        $this->assertSame($host->id, $account->host_id);
        $this->assertSame(PaymentProcessor::Nmi, $account->processor);
        $this->assertSame("host:{$host->id}", $account->external_account_id);
        $this->assertSame('pending_kyc', $account->status);
        $this->assertFalse($account->payouts_enabled);
        $this->assertTrue($account->charges_enabled);

        // Enrollment is local — no gateway involved.
        Http::assertNothingSent();
    }

    public function test_start_is_idempotent_for_returning_host(): void
    {
        $host = $this->makeHost();

        $this->actingAs($host)->post(route('host.onboarding.start'));
        $this->actingAs($host)
            ->post(route('host.onboarding.start'))
            ->assertRedirect(route('host.onboarding.index'))
            ->assertSessionHas('host_success');

        $this->assertSame(1, HostPayoutAccount::count());
    }

    public function test_start_does_not_duplicate_when_legacy_account_exists(): void
    {
        $host = $this->makeHost();
        HostPayoutAccount::create([
            'host_id'             => $host->id,
            'processor'           => PaymentProcessor::Stripe->value,
            'external_account_id' => 'acct_legacy_002',
            'status'              => 'verified',
            'payouts_enabled'     => true,
            'charges_enabled'     => true,
        ]);

        $this->actingAs($host)
            ->post(route('host.onboarding.start'))
            ->assertRedirect(route('host.onboarding.index'));

        $this->assertSame(1, HostPayoutAccount::count());
    }

    private function makeHost(): User
    {
        $user = User::factory()->create([
            'role' => UserRole::Host,
            'email_verified_at' => now(),
        ]);
        foreach (app(LegalDocumentRegistry::class)->registrationRequired() as $version) {
            TermsAcceptance::create([
                'user_id' => $user->id,
                'terms_version_id' => $version->id,
                'accepted_at' => now(),
            ]);
        }
        return $user;
    }
}

<?php

namespace Tests\Feature\Host;

use App\Enums\PaymentProcessor;
use App\Enums\UserRole;
use App\Models\HostPayoutAccount;
use App\Models\TermsAcceptance;
use App\Models\User;
use App\Services\Legal\LegalDocumentRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Stripe\Service\AccountLinkService;
use Stripe\Service\AccountService;
use Stripe\StripeClient;
use Tests\TestCase;

class HostOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private $accounts;
    private $accountLinks;

    protected function setUp(): void
    {
        parent::setUp();
        app(LegalDocumentRegistry::class)->materialiseAll();

        $this->accounts = Mockery::mock(AccountService::class);
        $this->accountLinks = Mockery::mock(AccountLinkService::class);
        $client = Mockery::mock(StripeClient::class)->makePartial();
        $client->accounts = $this->accounts;
        $client->accountLinks = $this->accountLinks;
        $this->app->instance(StripeClient::class, $client);

        config(['services.stripe.secret' => 'sk_test_mock', 'services.stripe.key' => 'pk_test_mock']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_unauthenticated_index_redirects_to_login(): void
    {
        $this->get(route('host.onboarding.index'))->assertRedirect(route('login'));
    }

    public function test_index_renders_call_to_start_when_no_account_yet(): void
    {
        $host = $this->makeHost();

        $resp = $this->actingAs($host)->get(route('host.onboarding.index'));

        $resp->assertOk();
        $resp->assertSee('Start verification');
        $resp->assertDontSee('Demo mode'); // live config set in setUp
    }

    public function test_index_shows_demo_banner_when_stripe_not_configured(): void
    {
        config(['services.stripe.secret' => '', 'services.stripe.key' => '']);

        $host = $this->makeHost();

        $this->actingAs($host)
            ->get(route('host.onboarding.index'))
            ->assertOk()
            ->assertSee('Demo mode');
    }

    public function test_index_shows_pending_status_for_existing_pending_account(): void
    {
        $host = $this->makeHost();
        HostPayoutAccount::create([
            'host_id'             => $host->id,
            'processor'           => PaymentProcessor::Stripe->value,
            'external_account_id' => 'acct_pending_test',
            'status'              => 'pending_kyc',
            'payouts_enabled'     => false,
            'charges_enabled'     => false,
        ]);

        $this->actingAs($host)
            ->get(route('host.onboarding.index'))
            ->assertOk()
            ->assertSee('Pending verification')
            ->assertSee('Resume onboarding');
    }

    public function test_index_shows_verified_state_when_account_ready(): void
    {
        $host = $this->makeHost();
        HostPayoutAccount::create([
            'host_id'             => $host->id,
            'processor'           => PaymentProcessor::Stripe->value,
            'external_account_id' => 'acct_verified_test',
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

    public function test_start_creates_stripe_account_then_account_link_and_redirects(): void
    {
        $host = $this->makeHost();

        $this->accounts->shouldReceive('create')
            ->once()
            ->andReturn((object) ['id' => 'acct_new_001', 'toArray' => fn () => []]);

        $this->accountLinks->shouldReceive('create')
            ->once()
            ->withArgs(function (array $params) {
                $this->assertSame('acct_new_001', $params['account']);
                $this->assertSame('account_onboarding', $params['type']);
                return true;
            })
            ->andReturn((object) ['url' => 'https://connect.stripe.com/setup/test_link']);

        $this->actingAs($host)
            ->post(route('host.onboarding.start'))
            ->assertRedirect('https://connect.stripe.com/setup/test_link');

        // HostPayoutAccount row was persisted by createConnectAccount.
        $this->assertSame(1, HostPayoutAccount::count());
        $this->assertSame('acct_new_001', HostPayoutAccount::sole()->external_account_id);
    }

    public function test_start_reuses_existing_stripe_account_for_returning_host(): void
    {
        $host = $this->makeHost();

        // Pre-existing row → no new accounts.create, only a fresh AccountLink.
        $existing = HostPayoutAccount::create([
            'host_id'             => $host->id,
            'processor'           => PaymentProcessor::Stripe->value,
            'external_account_id' => 'acct_returning_001',
            'status'              => 'pending_kyc',
            'payouts_enabled'     => false,
            'charges_enabled'     => false,
        ]);

        $this->accounts->shouldNotReceive('create');
        $this->accountLinks->shouldReceive('create')
            ->once()
            ->withArgs(function (array $params) use ($existing) {
                return $params['account'] === $existing->external_account_id;
            })
            ->andReturn((object) ['url' => 'https://connect.stripe.com/setup/returning']);

        $this->actingAs($host)
            ->post(route('host.onboarding.start'))
            ->assertRedirect('https://connect.stripe.com/setup/returning');

        $this->assertSame(1, HostPayoutAccount::count());
    }

    public function test_start_in_demo_mode_redirects_back_with_error(): void
    {
        config(['services.stripe.secret' => '', 'services.stripe.key' => '']);
        $this->accounts->shouldNotReceive('create');

        $host = $this->makeHost();

        $this->actingAs($host)
            ->post(route('host.onboarding.start'))
            ->assertRedirect(route('host.onboarding.index'))
            ->assertSessionHas('host_error');
    }

    public function test_start_falls_back_gracefully_when_stripe_throws(): void
    {
        $this->accounts->shouldReceive('create')
            ->andThrow(new \RuntimeException('stripe API down'));

        $host = $this->makeHost();

        $this->actingAs($host)
            ->post(route('host.onboarding.start'))
            ->assertRedirect(route('host.onboarding.index'))
            ->assertSessionHas('host_error');
    }

    public function test_refresh_issues_new_account_link_for_existing_account(): void
    {
        $host = $this->makeHost();
        HostPayoutAccount::create([
            'host_id'             => $host->id,
            'processor'           => PaymentProcessor::Stripe->value,
            'external_account_id' => 'acct_refresh_001',
            'status'              => 'pending_kyc',
        ]);

        $this->accountLinks->shouldReceive('create')
            ->once()
            ->andReturn((object) ['url' => 'https://connect.stripe.com/setup/refreshed']);

        $this->actingAs($host)
            ->get(route('host.onboarding.refresh'))
            ->assertRedirect('https://connect.stripe.com/setup/refreshed');
    }

    public function test_return_redirects_to_index_with_success_flash(): void
    {
        $host = $this->makeHost();

        $this->actingAs($host)
            ->get(route('host.onboarding.return'))
            ->assertRedirect(route('host.onboarding.index'))
            ->assertSessionHas('host_success');
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

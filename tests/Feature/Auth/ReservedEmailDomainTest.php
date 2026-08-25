<?php

namespace Tests\Feature\Auth;

use App\Mail\MemberFirstSignIn;
use App\Models\User;
use App\Rules\DeliverableEmailDomain;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Sign-ups on domains that can never receive mail.
 *
 * Another site's end-to-end suite kept the base URL it inherited when this
 * codebase was copied, and spent weeks registering accounts here on
 * mybluebeacon.test — 51 of them, each firing a first-sign-in notice to the
 * office about a member who did not exist.
 *
 * Blocking one domain would have fixed one copy. These are the TLDs the IETF
 * reserved so they would never resolve, which is both why test suites reach for
 * them and why no real person can be behind one.
 */
class ReservedEmailDomainTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Deployed environments are where this matters; every cloud env is 'production'.
     *
     * Flipping the environment also switches on CSRF verification, which the
     * test harness normally skips because it only skips it while the app calls
     * itself 'testing'. That is the harness, not the rule, so it is turned back
     * off rather than worked around with a token.
     */
    private function asDeployed(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    private function registration(array $overrides = []): array
    {
        return array_merge([
            'first_name'            => 'E2E',
            'last_name'             => 'Tester',
            'email'                 => 'someone@example.org',
            'phone'                 => '+1 555 010 2030',
            'password'              => 'PlaywrightPass!9876',
            'password_confirmation' => 'PlaywrightPass!9876',
            'accept_terms'          => '1',
        ], $overrides);
    }

    // --- what the rule recognises ------------------------------------------------------

    /** @return list<array{0: string}> */
    public static function reservedAddresses(): array
    {
        return [
            ['e2e+1787540470788@mybluebeacon.test'], // the actual address from the office inbox
            ['e2e+1@vaytoven.test'],
            ['someone@demo.vaytoven.local'],
            ['someone@anything.invalid'],
            ['someone@foo.example'],
            ['someone@localhost'],
        ];
    }

    #[DataProvider("reservedAddresses")]
    public function test_it_recognises_a_reserved_domain(string $email): void
    {
        $this->assertTrue(DeliverableEmailDomain::isReserved($email), $email.' cannot receive mail');
    }

    /** @return list<array{0: string}> */
    public static function realAddresses(): array
    {
        return [
            ['contact@vaytoven.com'],
            ['someone@mybluebeacon.com'],   // the live domain, not the test one
            ['someone@example.org'],
            ['someone@testing.co.uk'],      // 'test' inside a label is not the TLD
            ['someone@localhost.com'],
        ];
    }

    #[DataProvider("realAddresses")]
    public function test_it_leaves_deliverable_domains_alone(string $email): void
    {
        $this->assertFalse(DeliverableEmailDomain::isReserved($email), $email.' is deliverable');
    }

    // --- registration -------------------------------------------------------------------

    public function test_a_deployed_site_refuses_a_reserved_domain(): void
    {
        $this->asDeployed();

        $this->post('/register', $this->registration([
            'email' => 'e2e+1787540470788@mybluebeacon.test',
        ]))->assertSessionHasErrors('email');

        $this->assertSame(0, User::where('email', 'like', '%@mybluebeacon.test')->count());
    }

    public function test_a_deployed_site_still_accepts_a_real_address(): void
    {
        $this->asDeployed();

        $this->post('/register', $this->registration(['email' => 'real.person@example.org']));

        $this->assertSame(1, User::where('email', 'real.person@example.org')->count());
    }

    /**
     * Locally the reserved domains are the right thing to sign up with — our
     * own suite does it on purpose — so the rule must not fire there.
     */
    public function test_it_does_not_fire_outside_a_deployed_environment(): void
    {
        $this->post('/register', $this->registration(['email' => 'e2e+1@vaytoven.test']))
            ->assertSessionDoesntHaveErrors('email');

        $this->assertSame(1, User::where('email', 'e2e+1@vaytoven.test')->count());
    }

    // --- the office notice --------------------------------------------------------------

    /**
     * The 51 that already exist can still sign in. They must do it silently:
     * nobody paid, so there is no fulfilment to announce.
     */
    public function test_no_office_notice_for_an_account_on_a_reserved_domain(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email'                => 'e2e+1787540470788@mybluebeacon.test',
            'last_login_at'        => null,
            'must_change_password' => false,
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        Mail::assertNotSent(MemberFirstSignIn::class);
    }

    /** And a real member's first sign-in still reaches the office. */
    public function test_a_real_members_first_sign_in_still_notifies_the_office(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email'                => 'real.member@example.org',
            'last_login_at'        => null,
            'must_change_password' => false,
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        Mail::assertSent(MemberFirstSignIn::class);
    }
}

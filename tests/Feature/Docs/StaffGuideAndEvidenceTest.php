<?php

namespace Tests\Feature\Docs;

use App\Enums\MemberServiceOrderStatus;
use App\Enums\MemberServicePackage;
use App\Enums\UserRole;
use App\Models\MemberServiceOrder;
use App\Models\Role;
use App\Models\User;
use App\Services\Chargeback\ChargebackCertificateService;
use App\Services\Chargeback\EvidenceBundleService;
use App\Services\Docs\StaffGuide;
use Carbon\CarbonImmutable;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The two documents staff hand to somebody else.
 *
 * Both are PDFs generated on demand, and both are read by people who act on
 * them — a new starter learning the rules, and a card issuer deciding a
 * dispute. A wrong or empty section in either is not a cosmetic problem.
 *
 * Content is asserted against the rendered markup and the assembled payload
 * rather than against the PDF bytes. Parsing the PDF was tried first and is a
 * trap: dompdf compresses its streams and switches text runs to UTF-16 as soon
 * as a document contains any non-ASCII character, so the same assertion passes
 * alone and fails in a full run depending on which fonts were subsetted. That
 * is a property of the renderer, not of the evidence. These assert that the
 * right content reaches the template, and separately that the renderer produces
 * a real PDF.
 */
class StaffGuideAndEvidenceTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create([
            'role'                 => UserRole::SuperAdmin,
            'must_change_password' => false,
        ]);

        $user->roles()->sync([Role::where('key', 'super_admin')->firstOrFail()->id]);

        return $user;
    }

    /** The guide's markup, before dompdf turns it into a page. */
    private function guideHtml(): string
    {
        return view('docs.staff-guide', app(StaffGuide::class)->data())->render();
    }

    // --- the staff guide -----------------------------------------------------------

    public function test_a_staff_member_can_download_the_guide(): void
    {
        $response = $this->actingAs($this->staff())
            ->get(route('staff-guide'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertGreaterThan(20_000, strlen($response->getContent()), 'a one-page stub is not a guide');
    }

    public function test_a_member_cannot(): void
    {
        $this->actingAs(User::factory()->create(['must_change_password' => false]))
            ->get(route('staff-guide'))
            ->assertForbidden();
    }

    public function test_a_guest_cannot(): void
    {
        $this->get(route('staff-guide'))->assertRedirect(route('login'));
    }

    /**
     * The rules that exist to stop staff creating a dispute.
     *
     * Each has a matching constraint in the code — no booking flow, no
     * staff-entered cards, an append-only log, approximate GeoIP. A guide that
     * omits one teaches a new starter to do the thing the system is built to
     * prevent.
     */
    public function test_the_guide_states_the_rules_that_matter(): void
    {
        $this->seed(RbacSeeder::class);

        $html = $this->guideHtml();

        foreach ([
            'not a booking site'       => 'the correction staff make most often',
            'never enter card details' => 'every sale is customer-initiated',
            'never editable'           => 'the activity log',
            'approximate'              => 'GeoIP is an estimate, not a location',
            'contact@vaytoven.com'     => 'who to ask',
        ] as $phrase => $why) {
            $this->assertStringContainsStringIgnoringCase($phrase, $html, "the guide should cover: {$why}");
        }
    }

    /**
     * The guide reads roles from the database, so a role added tomorrow appears
     * without anybody remembering to edit a document.
     */
    public function test_the_guide_lists_the_roles_configured_on_this_environment(): void
    {
        $this->seed(RbacSeeder::class);

        $html = $this->guideHtml();

        $this->assertGreaterThan(0, Role::count());

        foreach (Role::pluck('name') as $name) {
            $this->assertStringContainsString($name, $html, "role '{$name}' is missing from the guide");
        }
    }

    /** Permissions too, or "why can I not see Contracts" has no answer in it. */
    public function test_the_guide_shows_what_each_role_can_do(): void
    {
        $this->seed(RbacSeeder::class);

        $html = $this->guideHtml();

        foreach (['properties.publish', 'billing.view', 'audit.view'] as $permission) {
            $this->assertStringContainsString($permission, $html);
        }
    }

    /** With no RBAC seeded it says so rather than printing an empty table. */
    public function test_the_guide_admits_when_no_roles_are_configured(): void
    {
        $this->assertSame(0, Role::count());

        $this->assertStringContainsString('No roles are configured', $this->guideHtml());
    }

    /** Every listing stage and week state, so nobody has to guess what Paused does. */
    public function test_the_guide_explains_the_listing_lifecycle(): void
    {
        $html = $this->guideHtml();

        foreach (['Draft', 'Pending Review', 'Active', 'Paused', 'Archived', 'Offer Pending'] as $stage) {
            $this->assertStringContainsString($stage, $html);
        }
    }

    public function test_the_filename_is_dated(): void
    {
        $this->assertMatchesRegularExpression(
            '/^vaytoven-staff-guide-\d{4}-\d{2}-\d{2}\.pdf$/',
            app(StaffGuide::class)->filename(),
        );
    }

    // --- the dispute evidence certificate --------------------------------------------

    private function memberWithAnOrder(): User
    {
        $member = User::factory()->create([
            'email'                => 'disputing.member@example.com',
            'must_change_password' => false,
        ]);

        MemberServiceOrder::create([
            'reference'            => 'VYT-EVIDENCE1',
            'email'                => $member->email,
            'first_name'           => 'Dana',
            'last_name'            => 'Whitfield',
            'package'              => MemberServicePackage::Gold->value,
            'weeks'                => 6,
            'price_per_week_cents' => 44900,
            'total_cents'          => 269400,
            'currency'             => 'USD',
            'status'               => MemberServiceOrderStatus::Paid->value,
            'paid_at'              => now(),
            'nmi_transaction_id'   => '9876543210',
            'nmi_authcode'         => 'AUTH42',
            'submitted_ip'         => '203.0.113.44',
        ]);

        return $member;
    }

    /** @return array<string, mixed> */
    private function certificatePayloadFor(User $member): array
    {
        $bundle = app(EvidenceBundleService::class)->generateForUser(
            $member->id,
            CarbonImmutable::now()->subYear(),
            CarbonImmutable::now(),
        );

        return app(ChargebackCertificateService::class)->viewPayload($bundle, $member);
    }

    public function test_the_evidence_certificate_downloads(): void
    {
        $member = $this->memberWithAnOrder();

        $response = $this->actingAs($this->staff())
            ->get(route('admin.users.certificate', $member))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    /**
     * The bug this covers: the bundle gathered the Member Services orders and
     * the template rendered them, but the payload between the two never carried
     * them across. Every certificate therefore printed "No Member Services
     * orders for this account" — and Member Services is the only thing that can
     * be charged back here, so the document was a signed statement on Vaytoven
     * letterhead that the disputed order did not exist.
     */
    public function test_the_certificate_payload_carries_the_member_services_order(): void
    {
        $payload = $this->certificatePayloadFor($this->memberWithAnOrder());

        $this->assertCount(1, $payload['member_service_orders']);

        $order = $payload['member_service_orders'][0];

        $this->assertSame('VYT-EVIDENCE1', $order['reference']);
        $this->assertSame(269400, $order['total_cents']);
        $this->assertSame('9876543210', $order['nmi_transaction_id'], 'the processor reference is the most useful field in the pack');
        $this->assertSame('paid', $order['status']);
    }

    /** Fulfilment travels with it, or a receipt proves a charge and nothing else. */
    public function test_the_certificate_payload_carries_the_fulfilment_sections(): void
    {
        $payload = $this->certificatePayloadFor($this->memberWithAnOrder());

        foreach (['advertising_periods', 'ad_snapshots', 'service_trail'] as $section) {
            $this->assertArrayHasKey($section, $payload, "{$section} never reaches the template");
            $this->assertIsArray($payload[$section]);
        }
    }

    /** The rendered document must show the order, not merely receive it. */
    public function test_the_certificate_template_renders_the_order(): void
    {
        $html = view('certificates.usage-certificate', $this->certificatePayloadFor($this->memberWithAnOrder()))->render();

        $this->assertStringContainsString('VYT-EVIDENCE1', $html);
        $this->assertStringContainsString('9876543210', $html);
        $this->assertStringNotContainsString('No Member Services orders', $html);
    }

    /**
     * Nothing in a dispute pack may carry card data.
     *
     * Asserted on the assembled payload rather than the PDF bytes: the rendered
     * file embeds font programs whose binary contains long digit runs that a
     * card-number pattern matches by chance, and an assertion that fails on a
     * font says nothing about card data.
     */
    public function test_the_evidence_payload_carries_no_card_data(): void
    {
        $flat = json_encode($this->certificatePayloadFor($this->memberWithAnOrder()));

        // 13-16 consecutive digits is a card number. The processor transaction
        // id in the fixture is 10, which is the real shape and must not be
        // mistaken for one.
        $this->assertDoesNotMatchRegularExpression('/\d{13,16}/', $flat, 'a card number reached the evidence bundle');

        foreach (['cvv', 'cvc', 'card_number'] as $forbidden) {
            $this->assertStringNotContainsStringIgnoringCase($forbidden, $flat);
        }
    }

    public function test_a_member_cannot_download_someone_elses_certificate(): void
    {
        $member = $this->memberWithAnOrder();

        $this->actingAs(User::factory()->create(['must_change_password' => false]))
            ->get(route('admin.users.certificate', $member))
            ->assertForbidden();
    }
}

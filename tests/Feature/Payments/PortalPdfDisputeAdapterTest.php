<?php

namespace Tests\Feature\Payments;

use App\Services\Chargeback\ChargebackCertificateService;
use App\Services\Chargeback\EvidenceBundle;
use App\Services\Payments\DisputeAdapterRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Black-box test of the PortalPdfDisputeAdapter contract for every non-Stripe
 * processor: each one must render a PDF, save it to a per-processor path, and
 * return a DisputeSubmissionResult with mode='manual_pdf' carrying both the
 * artifact path and the operator-facing portal URL in the note.
 */
class PortalPdfDisputeAdapterTest extends TestCase
{
    use RefreshDatabase;

    private const NON_STRIPE_PROCESSORS = [
        'authorizenet', 'nmi', 'nuvei', 'mes', 'paymentcloud',
        'ems', 'nexio', 'netevia', 'kurv',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // Stub the certificate service so the test doesn't depend on dompdf —
        // we're testing the adapter's behaviour, not PDF rendering.
        $this->app->instance(ChargebackCertificateService::class, new class extends ChargebackCertificateService {
            public function __construct() {}
            public function forBundle(EvidenceBundle $bundle): string
            {
                return "%PDF-1.4 stub for {$bundle->confirmation_code}";
            }
        });

        Storage::fake();
    }

    public function test_each_non_stripe_adapter_writes_pdf_and_returns_manual_pdf_result(): void
    {
        $registry = $this->app->make(DisputeAdapterRegistry::class);
        $bundle = $this->makeBundle('VYT-ABC123');

        foreach (self::NON_STRIPE_PROCESSORS as $processor) {
            $adapter = $registry->for($processor);
            $result = $adapter->submit('dp_'.$processor.'_001', $bundle);

            $this->assertSame($processor, $result->processor, "{$processor}: result.processor mismatch");
            $this->assertSame('manual_pdf', $result->mode, "{$processor}: must be manual_pdf mode");
            $this->assertNotNull($result->artifact_path, "{$processor}: artifact_path missing");
            $this->assertStringContainsString("disputes/{$processor}/", $result->artifact_path);

            Storage::assertExists($result->artifact_path);

            $this->assertStringContainsString('Upload', (string) $result->note);
            $this->assertStringContainsString(basename($result->artifact_path), (string) $result->note);
            $this->assertStringContainsString($adapter->portalUploadUrl(), (string) $result->note);
        }
    }

    public function test_external_dispute_id_is_sanitised_into_artifact_path(): void
    {
        $registry = $this->app->make(DisputeAdapterRegistry::class);
        $adapter = $registry->for('nmi');

        // Slashes and dots in a dispute id must not escape the storage path.
        $result = $adapter->submit('../weird/dispute id.pdf', $this->makeBundle('VYT-Z'));

        $this->assertStringContainsString('disputes/nmi/', $result->artifact_path);
        $this->assertStringNotContainsString('..', $result->artifact_path);
        $this->assertStringNotContainsString(' ', $result->artifact_path);
    }

    public function test_each_processor_writes_to_distinct_path(): void
    {
        $registry = $this->app->make(DisputeAdapterRegistry::class);
        $paths = [];

        foreach (self::NON_STRIPE_PROCESSORS as $processor) {
            $adapter = $registry->for($processor);
            $result = $adapter->submit('dp_shared_id', $this->makeBundle('VYT-X'));
            $paths[$processor] = $result->artifact_path;
        }

        $this->assertCount(count(self::NON_STRIPE_PROCESSORS), array_unique($paths));
    }

    private function makeBundle(string $code): EvidenceBundle
    {
        return new EvidenceBundle(
            booking_id:           1,
            user_id:              null,
            dispute_id:           42,
            confirmation_code:    $code,
            logins:               [],
            charges:              [],
            refunds:              [],
            terms_acceptances:    [],
            consumption_events:   [],
            passive_events:       [],
            contracts:            [],
            generated_at:         '2026-05-06T00:00:00Z',
        );
    }
}

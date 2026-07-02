<?php

namespace Tests\Feature\Payments;

use App\Enums\PaymentProcessor;
use App\Services\Payments\DisputeAdapter;
use App\Services\Payments\DisputeAdapterRegistry;
use App\Services\Payments\PortalPdfDisputeAdapter;
use Tests\TestCase;

class DisputeAdapterRegistryTest extends TestCase
{
    private const PROCESSORS = [
        'authorizenet', 'nmi', 'nuvei', 'mes', 'paymentcloud',
        'ems', 'nexio', 'netevia', 'kurv',
    ];

    public function test_registry_resolves_every_processor_to_portal_pdf_adapter(): void
    {
        $registry = $this->app->make(DisputeAdapterRegistry::class);

        foreach (self::PROCESSORS as $processor) {
            $adapter = $registry->for($processor);
            $this->assertInstanceOf(
                PortalPdfDisputeAdapter::class,
                $adapter,
                "{$processor} adapter must extend PortalPdfDisputeAdapter to ship a working artifact today.",
            );
            $this->assertInstanceOf(DisputeAdapter::class, $adapter);
        }
    }

    public function test_registry_accepts_payment_processor_enum(): void
    {
        $registry = $this->app->make(DisputeAdapterRegistry::class);

        // Stripe stays in the enum for historical row casting but was removed
        // from the registry with the NMI migration (2026-07) — skip it here.
        $cases = array_filter(PaymentProcessor::cases(), fn ($c) => $c !== PaymentProcessor::Stripe);
        $this->assertNotEmpty($cases, 'PaymentProcessor enum must list cases for this test to be meaningful.');

        foreach ($cases as $case) {
            $adapter = $registry->for($case);
            $this->assertInstanceOf(DisputeAdapter::class, $adapter);
        }
    }

    public function test_registry_throws_for_unknown_processor(): void
    {
        $registry = $this->app->make(DisputeAdapterRegistry::class);

        $this->expectException(\InvalidArgumentException::class);
        $registry->for('does-not-exist');
    }

    public function test_registry_throws_for_retired_stripe_processor(): void
    {
        $registry = $this->app->make(DisputeAdapterRegistry::class);

        $this->expectException(\InvalidArgumentException::class);
        $registry->for('stripe');
    }

    public function test_registry_lists_all_nine_processors(): void
    {
        $registry = $this->app->make(DisputeAdapterRegistry::class);
        $all = $registry->all();

        $this->assertCount(9, $all);
        $this->assertArrayNotHasKey('stripe', $all);
        foreach (self::PROCESSORS as $p) {
            $this->assertArrayHasKey($p, $all);
        }
    }
}

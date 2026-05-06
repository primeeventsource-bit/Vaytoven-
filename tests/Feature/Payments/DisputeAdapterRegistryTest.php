<?php

namespace Tests\Feature\Payments;

use App\Enums\PaymentProcessor;
use App\Services\Payments\DisputeAdapter;
use App\Services\Payments\DisputeAdapterRegistry;
use App\Services\Payments\PortalPdfDisputeAdapter;
use App\Services\Payments\Stripe\StripeDisputeAdapter;
use Tests\TestCase;

class DisputeAdapterRegistryTest extends TestCase
{
    private const NON_STRIPE_PROCESSORS = [
        'authorizenet', 'nmi', 'nuvei', 'mes', 'paymentcloud',
        'ems', 'nexio', 'netevia', 'kurv',
    ];

    public function test_registry_resolves_stripe_to_api_adapter(): void
    {
        $registry = $this->app->make(DisputeAdapterRegistry::class);
        $adapter = $registry->for('stripe');

        $this->assertInstanceOf(StripeDisputeAdapter::class, $adapter);
        $this->assertNotInstanceOf(PortalPdfDisputeAdapter::class, $adapter);
    }

    public function test_registry_resolves_every_non_stripe_processor_to_portal_pdf_adapter(): void
    {
        $registry = $this->app->make(DisputeAdapterRegistry::class);

        foreach (self::NON_STRIPE_PROCESSORS as $processor) {
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

        $cases = PaymentProcessor::cases();
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

    public function test_registry_lists_all_ten_processors(): void
    {
        $registry = $this->app->make(DisputeAdapterRegistry::class);
        $all = $registry->all();

        $this->assertCount(10, $all);
        $this->assertArrayHasKey('stripe', $all);
        foreach (self::NON_STRIPE_PROCESSORS as $p) {
            $this->assertArrayHasKey($p, $all);
        }
    }
}

<?php

namespace App\Services\Payments;

use App\Enums\PaymentProcessor;

/**
 * Resolves the right DisputeAdapter for a given processor (FR-10.12).
 *
 * Lazily instantiated via the container so the heavy Stripe SDK isn't
 * loaded for processors that don't need it.
 */
class DisputeAdapterRegistry
{
    /** @var array<string, class-string<DisputeAdapter>> */
    private const MAP = [
        'stripe'        => \App\Services\Payments\Stripe\StripeDisputeAdapter::class,
        'authorizenet'  => \App\Services\Payments\AuthorizeNet\AuthorizeNetDisputeAdapter::class,
        'nmi'           => \App\Services\Payments\Nmi\NmiDisputeAdapter::class,
        'nuvei'         => \App\Services\Payments\Nuvei\NuveiDisputeAdapter::class,
        'mes'           => \App\Services\Payments\Mes\MesDisputeAdapter::class,
        'paymentcloud'  => \App\Services\Payments\PaymentCloud\PaymentCloudDisputeAdapter::class,
        'ems'           => \App\Services\Payments\Ems\EmsDisputeAdapter::class,
        'nexio'         => \App\Services\Payments\Nexio\NexioDisputeAdapter::class,
        'netevia'       => \App\Services\Payments\Netevia\NeteviaDisputeAdapter::class,
        'kurv'          => \App\Services\Payments\Kurv\KurvDisputeAdapter::class,
    ];

    public function for(PaymentProcessor|string $processor): DisputeAdapter
    {
        $key = $processor instanceof PaymentProcessor ? $processor->value : $processor;
        $class = self::MAP[$key] ?? null;

        if (! $class) {
            throw new \InvalidArgumentException("No DisputeAdapter registered for processor '{$key}'");
        }

        return app($class);
    }

    /**
     * @return array<string, class-string<DisputeAdapter>>
     */
    public function all(): array
    {
        return self::MAP;
    }
}

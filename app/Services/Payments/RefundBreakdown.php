<?php

namespace App\Services\Payments;

/**
 * Immutable value object describing a refund computation.
 *
 * All amounts are integer cents. Total is the sum of the line items.
 */
final readonly class RefundBreakdown
{
    public int $total_cents;

    public function __construct(
        public int $subtotal_refund_cents,
        public int $cleaning_refund_cents,
        public int $service_fee_refund_cents,
        public int $tax_refund_cents,
        public string $tier,        // 'full' | 'partial' | 'none'
    ) {
        $this->total_cents = $subtotal_refund_cents
            + $cleaning_refund_cents
            + $service_fee_refund_cents
            + $tax_refund_cents;
    }
}

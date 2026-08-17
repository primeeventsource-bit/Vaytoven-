<?php

namespace App\Enums;

enum MemberServiceOrderStatus: string
{
    /** Activation submitted; payment link issued, not yet paid. */
    case AwaitingPayment = 'awaiting_payment';

    /** NMI approved the sale. */
    case Paid = 'paid';

    /** NMI declined or errored. The order stays payable — a decline is not
     *  the end of the road, the member can retry with another card. */
    case Failed = 'failed';

    /** The payment link's window closed before it was used. */
    case Expired = 'expired';

    /** Cancelled by staff from the admin console. */
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingPayment => 'Awaiting payment',
            self::Paid            => 'Paid',
            self::Failed          => 'Payment failed',
            self::Expired         => 'Link expired',
            self::Cancelled       => 'Cancelled',
        };
    }

    /** Can a member still pay against this order? */
    public function isPayable(): bool
    {
        return $this === self::AwaitingPayment || $this === self::Failed;
    }
}

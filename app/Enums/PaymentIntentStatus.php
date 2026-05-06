<?php

namespace App\Enums;

enum PaymentIntentStatus: string
{
    case RequiresAction = 'requires_action';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case RequiresPaymentMethod = 'requires_payment_method';
    case Canceled = 'canceled';
    case Failed = 'failed';

    public function isSettled(): bool
    {
        return $this === self::Succeeded;
    }
}

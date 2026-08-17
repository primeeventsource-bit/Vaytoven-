<?php

namespace App\Enums;

enum AdvertisingPeriodStatus: string
{
    /** Paid for, not yet switched on. */
    case Pending = 'pending';

    /** Running. */
    case Active = 'active';

    /** Ran its course. */
    case Expired = 'expired';

    /** Stopped by staff, clock held. */
    case Paused = 'paused';

    /** Called off. */
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending   => 'Pending activation',
            self::Active    => 'Active',
            self::Expired   => 'Expired',
            self::Paused    => 'Paused',
            self::Cancelled => 'Cancelled',
        };
    }
}

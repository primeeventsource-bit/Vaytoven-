<?php

namespace App\Enums;

/**
 * The three Member Services packages.
 *
 * Prices live in settings (member_services.*_price_per_week_cents) so they can
 * be changed without a deploy, but an ORDER never reads them again after it is
 * created: the price per week is snapshotted onto the row. Raising Gold from
 * $449 to $499 must not reprice a payment link a member is already holding.
 */
enum MemberServicePackage: string
{
    case Bronze = 'bronze';
    case Silver = 'silver';
    case Gold = 'gold';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Settings key holding this package's weekly rate, in cents. */
    public function settingKey(): string
    {
        return "member_services.{$this->value}_price_per_week_cents";
    }

    /** Fallback used when the settings row is missing. */
    public function defaultPricePerWeekCents(): int
    {
        return match ($this) {
            self::Bronze => 24900,   // $249
            self::Silver => 34900,   // $349
            self::Gold   => 44900,   // $449
        };
    }

    /**
     * Current weekly rate in cents, as an integer.
     *
     * Cents throughout — money is never a float. $249.00 stored as 24900
     * multiplies exactly; 249.00 in binary floating point does not.
     */
    public function currentPricePerWeekCents(): int
    {
        $value = setting($this->settingKey(), $this->defaultPricePerWeekCents());

        return max(0, (int) $value);
    }

    /** @return array<int, self> in presentation order. */
    public static function ordered(): array
    {
        return [self::Bronze, self::Silver, self::Gold];
    }
}

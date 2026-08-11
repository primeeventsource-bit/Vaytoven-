<?php

namespace App\Enums;

/** What a Trip Support request is about. */
enum SupportCategory: string
{
    case Booking = 'booking';
    case Payment = 'payment';
    case Cancellation = 'cancellation';
    case Listing = 'listing';
    case Offer = 'offer';
    case Membership = 'membership';
    case Account = 'account';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Booking => 'Booking or check-in',
            self::Payment => 'Payment or refund',
            self::Cancellation => 'Cancellation',
            self::Listing => 'A property listing',
            self::Offer => 'An inquiry or offer',
            self::Membership => 'Membership',
            self::Account => 'Account access',
            self::Other => 'Something else',
        };
    }

    /**
     * Requests that should not sit in a normal queue overnight — someone is
     * mid-trip or money has moved.
     */
    public function priority(): string
    {
        return match ($this) {
            self::Booking, self::Payment, self::Cancellation => 'high',
            default => 'normal',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}

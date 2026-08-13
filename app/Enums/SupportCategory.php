<?php

namespace App\Enums;

/**
 * What a Trip Support request is about.
 *
 * "Booking or check-in" and "Cancellation" used to be the first options a
 * visitor saw. Offering them told people Vaytoven holds their reservation and
 * can cancel it, which is exactly the misunderstanding the rest of the site
 * works to prevent — and it routed those requests to a queue that could not
 * resolve them, because Vaytoven is not a party to the stay.
 *
 * A stay problem is still a real problem, so ReachingAnOwner exists to catch
 * it honestly: we can help someone make contact and we hold the record of the
 * offer, but we cannot cancel, refund, or relocate.
 */
enum SupportCategory: string
{
    case ReachingAnOwner = 'reaching_an_owner';
    case Listing = 'listing';
    case Offer = 'offer';
    case Membership = 'membership';
    case Billing = 'billing';
    case Account = 'account';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::ReachingAnOwner => "I can't reach a listing member",
            self::Listing => 'A property listing',
            self::Offer => 'An inquiry or offer',
            self::Membership => 'Membership',
            self::Billing => 'Advertising or subscription billing',
            self::Account => 'Account access',
            self::Other => 'Something else',
        };
    }

    /**
     * Requests that should not sit in a queue overnight.
     *
     * Someone who cannot reach a listing member may have travel booked around
     * it, and billing means money has moved through Vaytoven's own merchant
     * account — the only money that ever does.
     */
    public function priority(): string
    {
        return match ($this) {
            self::ReachingAnOwner, self::Billing => 'high',
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
